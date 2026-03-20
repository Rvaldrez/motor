<?php
/**
 * Seção: Propostas
 * $conn, $user, $tipo, $csrfToken available from painel.php
 */
$userId = (int) $user['id'];

$page = max(1, (int) ($_GET['pp'] ?? 1));
$perPage = 10;

$filterStatus = trim($_GET['pstatus'] ?? '');

if ($tipo === 'vendedor') {
    $countSql = "
        SELECT COUNT(*) FROM propostas p
        INNER JOIN veiculos v ON v.id = p.veiculo_id
        WHERE v.usuario_id = ? AND p.proposta_origem_id IS NULL
    ";
    $dataSql = "
        SELECT p.id, p.valor, p.status, p.data_proposta, p.mensagem,
               v.marca, v.modelo, v.ano_fabrica, v.id AS veiculo_id,
               u.nome AS contraparte_nome, u.email AS contraparte_email,
               (SELECT COUNT(*) FROM propostas cp WHERE cp.proposta_origem_id = p.id) AS respostas
        FROM propostas p
        INNER JOIN veiculos v ON v.id = p.veiculo_id
        INNER JOIN usuarios u ON u.id = p.usuario_id
        WHERE v.usuario_id = ? AND p.proposta_origem_id IS NULL
    ";
    $baseParams = [$userId, $userId];
    $baseTypes  = 'ii';
    $sectionTitle = 'Propostas Recebidas';
    $contraparteLabel = 'Investidor';

} elseif ($tipo === 'investidor') {
    $countSql = "
        SELECT COUNT(*) FROM propostas p
        WHERE p.usuario_id = ? AND p.proposta_origem_id IS NULL
    ";
    $dataSql = "
        SELECT p.id, p.valor, p.status, p.data_proposta, p.mensagem,
               v.marca, v.modelo, v.ano_fabrica, v.id AS veiculo_id,
               u.nome AS contraparte_nome, u.email AS contraparte_email,
               (SELECT COUNT(*) FROM propostas cp WHERE cp.proposta_origem_id = p.id) AS respostas
        FROM propostas p
        INNER JOIN veiculos v ON v.id = p.veiculo_id
        INNER JOIN usuarios u ON u.id = v.usuario_id
        WHERE p.usuario_id = ? AND p.proposta_origem_id IS NULL
    ";
    $baseParams = [$userId, $userId];
    $baseTypes  = 'ii';
    $sectionTitle = 'Minhas Propostas';
    $contraparteLabel = 'Vendedor';

} else { // administrador
    $countSql = "SELECT COUNT(*) FROM propostas p INNER JOIN veiculos v ON v.id = p.veiculo_id WHERE p.proposta_origem_id IS NULL";
    $dataSql = "
        SELECT p.id, p.valor, p.status, p.data_proposta, p.mensagem,
               v.marca, v.modelo, v.ano_fabrica, v.id AS veiculo_id,
               u.nome AS contraparte_nome, u.email AS contraparte_email,
               (SELECT COUNT(*) FROM propostas cp WHERE cp.proposta_origem_id = p.id) AS respostas
        FROM propostas p
        INNER JOIN veiculos v ON v.id = p.veiculo_id
        INNER JOIN usuarios u ON u.id = p.usuario_id
        WHERE p.proposta_origem_id IS NULL
    ";
    $baseParams = [];
    $baseTypes  = '';
    $sectionTitle = 'Todas as Propostas';
    $contraparteLabel = 'Investidor';
}

$filterConditions = '';
$filterParams = [];
$filterTypes  = '';

if ($filterStatus !== '') {
    $filterConditions .= " AND p.status = ?";
    $filterParams[] = $filterStatus;
    $filterTypes .= 's';
}

// Count
$stmtCount = $conn->prepare($countSql . $filterConditions);
$allCountParams = array_merge($baseParams, $filterParams);
$allCountTypes  = $baseTypes . $filterTypes;
if (!empty($allCountParams)) {
    // Count queries have only one bind for userId (first occurrence) per type
    if ($tipo === 'vendedor' || $tipo === 'investidor') {
        $cp = array_merge([(int)$userId], $filterParams);
        $ct = 'i' . $filterTypes;
        $stmtCount->bind_param($ct, ...$cp);
    } elseif (!empty($filterParams)) {
        $stmtCount->bind_param($filterTypes, ...$filterParams);
    }
}
$stmtCount->execute();
$stmtCount->bind_result($totalCount);
$stmtCount->fetch();
$stmtCount->close();

$totalPages = max(1, (int) ceil($totalCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Fetch data
$finalSql = $dataSql . $filterConditions . " ORDER BY p.data_proposta DESC LIMIT ? OFFSET ?";
$stmtData = $conn->prepare($finalSql);
$allDataParams = array_merge($baseParams, $filterParams, [$perPage, $offset]);
$allDataTypes  = $baseTypes . $filterTypes . 'ii';
if (!empty($allDataParams)) {
    $stmtData->bind_param($allDataTypes, ...$allDataParams);
}
$stmtData->execute();
$result = $stmtData->get_result();
$propostas = [];
while ($row = $result->fetch_assoc()) {
    $propostas[] = $row;
}
$stmtData->close();

function props_statusBadge(string $status): string {
    $map = [
        'aguardando'     => ['#fef3c7','#92400e','Aguardando'],
        'aceita'         => ['#d1fae5','#065f46','Aceita'],
        'recusada'       => ['#fee2e2','#991b1b','Recusada'],
        'cancelada'      => ['#f3f4f6','#6b7280','Cancelada'],
        'contraproposta' => ['#ede9fe','#5b21b6','Contraproposta'],
        'finalizada'     => ['#dbeafe','#1e40af','Finalizada'],
        'expirada'       => ['#f3f4f6','#9ca3af','Expirada'],
    ];
    $d = $map[$status] ?? ['#f3f4f6','#6b7280', ucfirst($status)];
    return '<span style="background:' . $d[0] . ';color:' . $d[1] . ';padding:2px 10px;border-radius:9999px;font-size:0.75rem;font-weight:600;">'
        . htmlspecialchars($d[2], ENT_QUOTES, 'UTF-8') . '</span>';
}
?>

<div class="section-page">
    <div class="section-page-header">
        <div>
            <h2 class="section-page-title">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <?= htmlspecialchars($sectionTitle, ENT_QUOTES, 'UTF-8') ?>
            </h2>
            <p class="section-page-subtitle"><?= (int)$totalCount ?> proposta<?= $totalCount !== 1 ? 's' : '' ?></p>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" class="filter-bar" id="propostasFilterForm">
        <input type="hidden" name="secao" value="propostas">
        <select name="pstatus" class="filter-select">
            <option value="">Todos os status</option>
            <option value="aguardando" <?= $filterStatus === 'aguardando' ? 'selected' : '' ?>>Aguardando</option>
            <option value="aceita" <?= $filterStatus === 'aceita' ? 'selected' : '' ?>>Aceita</option>
            <option value="recusada" <?= $filterStatus === 'recusada' ? 'selected' : '' ?>>Recusada</option>
            <option value="contraproposta" <?= $filterStatus === 'contraproposta' ? 'selected' : '' ?>>Contraproposta</option>
            <option value="cancelada" <?= $filterStatus === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
            <option value="finalizada" <?= $filterStatus === 'finalizada' ? 'selected' : '' ?>>Finalizada</option>
        </select>
        <button type="submit" class="btn-filter-apply">Filtrar</button>
        <?php if ($filterStatus !== ''): ?>
        <a href="?secao=propostas" class="btn-filter-clear">Limpar</a>
        <?php endif; ?>
    </form>

    <div class="table-card">
        <?php if (empty($propostas)): ?>
        <div class="table-empty">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            <p>Nenhuma proposta encontrada.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Veículo</th>
                        <th><?= htmlspecialchars($contraparteLabel, ENT_QUOTES, 'UTF-8') ?></th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($propostas as $p): ?>
                    <tr>
                        <td style="color:var(--color-text-muted);font-size:0.8125rem;">#<?= (int)$p['id'] ?></td>
                        <td>
                            <div style="font-weight:600;font-size:0.875rem;">
                                <?= htmlspecialchars($p['marca'] . ' ' . $p['modelo'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div style="font-size:0.8rem;color:var(--color-text-muted);">
                                <?= htmlspecialchars($p['ano_fabrica'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:0.875rem;font-weight:500;">
                                <?= htmlspecialchars($p['contraparte_nome'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div style="font-size:0.8rem;color:var(--color-text-muted);">
                                <?= htmlspecialchars($p['contraparte_email'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </td>
                        <td style="font-weight:700;"><?= formatMoney((float)$p['valor']) ?></td>
                        <td><?= props_statusBadge($p['status']) ?></td>
                        <td style="color:var(--color-text-muted);font-size:0.8125rem;">
                            <?= !empty($p['data_proposta']) ? date('d/m/Y H:i', strtotime($p['data_proposta'])) : '-' ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:0.375rem;flex-wrap:wrap;">
                                <button class="btn-table-action btn-view" title="Detalhes"
                                        onclick="verProposta(<?= (int)$p['id'] ?>)">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <?php if ($tipo === 'vendedor' && $p['status'] === 'aguardando'): ?>
                                <button class="btn-table-action btn-success" title="Aceitar"
                                        onclick="responderProposta(<?= (int)$p['id'] ?>, 'aceitar')">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                                <button class="btn-table-action btn-danger" title="Recusar"
                                        onclick="responderProposta(<?= (int)$p['id'] ?>, 'recusar')">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                                <?php elseif ($tipo === 'investidor' && $p['status'] === 'aguardando'): ?>
                                <button class="btn-table-action btn-danger" title="Cancelar"
                                        onclick="responderProposta(<?= (int)$p['id'] ?>, 'cancelar')">
                                    <i class="fa-solid fa-ban"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="table-pagination">
            <span class="pagination-info">Página <?= $page ?> de <?= $totalPages ?></span>
            <div class="pagination-btns">
                <?php if ($page > 1): ?>
                <a href="?secao=propostas&pp=<?= $page - 1 ?>&pstatus=<?= urlencode($filterStatus) ?>" class="btn-page">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                <?php
                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                <a href="?secao=propostas&pp=<?= $i ?>&pstatus=<?= urlencode($filterStatus) ?>"
                   class="btn-page <?= $i === $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                <a href="?secao=propostas&pp=<?= $page + 1 ?>&pstatus=<?= urlencode($filterStatus) ?>" class="btn-page">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Detalhes proposta -->
<div class="modal-overlay" id="modalDetalheProposta" style="display:none;" role="dialog" aria-modal="true">
    <div class="modal-box modal-lg">
        <div class="modal-header">
            <h3>Detalhes da Proposta</h3>
            <button class="modal-close" onclick="document.getElementById('modalDetalheProposta').style.display='none'" aria-label="Fechar">&times;</button>
        </div>
        <div class="modal-body" id="modalDetalheBody">
            <div style="text-align:center;padding:2rem;">
                <i class="fa-solid fa-spinner fa-spin" style="font-size:1.5rem;color:var(--color-primary);"></i>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-modal-cancel" onclick="document.getElementById('modalDetalheProposta').style.display='none'">Fechar</button>
        </div>
    </div>
</div>

<script>
function verProposta(id) {
    document.getElementById('modalDetalheProposta').style.display = 'flex';
    document.getElementById('modalDetalheBody').innerHTML =
        '<div style="text-align:center;padding:2rem;"><i class="fa-solid fa-spinner fa-spin" style="font-size:1.5rem;color:var(--color-primary);"></i></div>';

    fetch('actions/get_proposta.php?id=' + id)
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success && data.proposta) {
                var p = data.proposta;
                var html = '<div class="proposta-detalhe">';
                html += '<div class="pd-row"><span class="pd-label">Veículo</span><span class="pd-val">' + escHtml(p.marca + ' ' + p.modelo + ' ' + p.ano_fabrica) + '</span></div>';
                html += '<div class="pd-row"><span class="pd-label">Valor da Proposta</span><span class="pd-val" style="font-weight:800;color:var(--color-primary);">R$ ' + parseFloat(p.valor).toLocaleString('pt-BR', {minimumFractionDigits:2}) + '</span></div>';
                html += '<div class="pd-row"><span class="pd-label">Status</span><span class="pd-val">' + escHtml(p.status) + '</span></div>';
                html += '<div class="pd-row"><span class="pd-label">Data</span><span class="pd-val">' + escHtml(p.data_proposta || '-') + '</span></div>';
                if (p.mensagem) {
                    html += '<div class="pd-row"><span class="pd-label">Mensagem</span><span class="pd-val">' + escHtml(p.mensagem) + '</span></div>';
                }
                html += '</div>';
                document.getElementById('modalDetalheBody').innerHTML = html;
            } else {
                document.getElementById('modalDetalheBody').innerHTML = '<p style="color:var(--color-danger);text-align:center;">Erro ao carregar proposta.</p>';
            }
        })
        .catch(function () {
            document.getElementById('modalDetalheBody').innerHTML = '<p style="color:var(--color-danger);text-align:center;">Erro de conexão.</p>';
        });
}

function responderProposta(id, acao) {
    var msgs = { aceitar: 'Aceitar esta proposta?', recusar: 'Recusar esta proposta?', cancelar: 'Cancelar esta proposta?' };
    if (!confirm(msgs[acao] || 'Confirmar ação?')) return;

    var fd = new FormData();
    fd.append('proposta_id', id);
    fd.append('acao', acao);
    fd.append('csrf_token', '<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>');

    fetch('actions/responder_proposta.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                window.location.href = '?secao=propostas';
            } else {
                alert(data.message || 'Erro ao processar ação.');
            }
        });
}

function escHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(String(str)));
    return div.innerHTML;
}

document.getElementById('modalDetalheProposta').addEventListener('click', function (e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<style>
.proposta-detalhe { display: flex; flex-direction: column; gap: 0.875rem; }
.pd-row { display: flex; justify-content: space-between; align-items: flex-start; padding: 0.625rem 0; border-bottom: 1px solid var(--color-border); }
.pd-row:last-child { border-bottom: none; }
.pd-label { font-size: 0.8125rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.04em; }
.pd-val { font-size: 0.9375rem; font-weight: 500; color: var(--color-text); text-align: right; max-width: 60%; }
.btn-success { background: rgba(22,163,74,0.1); color: #15803d; }
.btn-success:hover { background: #16a34a; color: #fff; }
.btn-view { background: rgba(37,99,235,0.1); color: #1d4ed8; }
.btn-view:hover { background: #2563eb; color: #fff; }
</style>
