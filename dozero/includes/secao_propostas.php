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
               (SELECT COUNT(*) FROM propostas cp WHERE cp.proposta_origem_id = p.id) AS respostas,
               (SELECT cp2.id FROM propostas cp2 WHERE cp2.proposta_origem_id = p.id ORDER BY cp2.id DESC LIMIT 1) AS ultima_contra_id,
               (SELECT cp2.valor FROM propostas cp2 WHERE cp2.proposta_origem_id = p.id ORDER BY cp2.id DESC LIMIT 1) AS ultima_contra_valor
        FROM propostas p
        INNER JOIN veiculos v ON v.id = p.veiculo_id
        INNER JOIN usuarios u ON u.id = p.usuario_id
        WHERE v.usuario_id = ? AND p.proposta_origem_id IS NULL
    ";
    $baseParams = [$userId, $userId];
    $baseTypes  = 'ii';
    $sectionTitle = 'Propostas';
    $contraparteLabel = 'Comprador';

} elseif ($tipo === 'investidor') {
    $countSql = "
        SELECT COUNT(*) FROM propostas p
        WHERE p.usuario_id = ? AND p.proposta_origem_id IS NULL
    ";
    $dataSql = "
        SELECT p.id, p.valor, p.status, p.data_proposta, p.mensagem,
               v.marca, v.modelo, v.ano_fabrica, v.id AS veiculo_id,
               u.nome AS contraparte_nome, u.email AS contraparte_email,
               (SELECT COUNT(*) FROM propostas cp WHERE cp.proposta_origem_id = p.id) AS respostas,
               (SELECT cp2.id FROM propostas cp2 WHERE cp2.proposta_origem_id = p.id ORDER BY cp2.id DESC LIMIT 1) AS ultima_contra_id,
               (SELECT cp2.valor FROM propostas cp2 WHERE cp2.proposta_origem_id = p.id ORDER BY cp2.id DESC LIMIT 1) AS ultima_contra_valor
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
               (SELECT COUNT(*) FROM propostas cp WHERE cp.proposta_origem_id = p.id) AS respostas,
               (SELECT cp2.id FROM propostas cp2 WHERE cp2.proposta_origem_id = p.id ORDER BY cp2.id DESC LIMIT 1) AS ultima_contra_id,
               (SELECT cp2.valor FROM propostas cp2 WHERE cp2.proposta_origem_id = p.id ORDER BY cp2.id DESC LIMIT 1) AS ultima_contra_valor
        FROM propostas p
        INNER JOIN veiculos v ON v.id = p.veiculo_id
        INNER JOIN usuarios u ON u.id = p.usuario_id
        WHERE p.proposta_origem_id IS NULL
    ";
    $baseParams = [];
    $baseTypes  = '';
    $sectionTitle = 'Todas as Propostas';
    $contraparteLabel = 'Comprador';
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
if ($stmtCount === false) {
    $totalCount = 0;
} else {
    $allCountParams = array_merge($baseParams, $filterParams);
    if (!empty($allCountParams)) {
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
}

$totalPages = max(1, (int) ceil($totalCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Fetch data
$finalSql = $dataSql . $filterConditions . " ORDER BY p.data_proposta DESC LIMIT ? OFFSET ?";
$stmtData = $conn->prepare($finalSql);
$propostas = [];
if ($stmtData === false) {
    // column may not exist yet – gracefully show empty list
} else {
    $allDataParams = array_merge($baseParams, $filterParams, [$perPage, $offset]);
    $allDataTypes  = $baseTypes . $filterTypes . 'ii';
    if (!empty($allDataParams)) {
        $stmtData->bind_param($allDataTypes, ...$allDataParams);
    }
    $stmtData->execute();
    $result = $stmtData->get_result();
    while ($row = $result->fetch_assoc()) {
        $propostas[] = $row;
    }
    $stmtData->close();
}

function props_statusBadge(string $status): string {
    $map = [
        'aguardando_vendedor' => ['#fef3c7','#92400e','Aguardando'],
        'aguardando_comprador'=> ['#fef3c7','#92400e','Aguardando'],
        'aguardando'          => ['#fef3c7','#92400e','Aguardando'],
        'aceita'              => ['#d1fae5','#065f46','Aceita'],
        'recusada'            => ['#fee2e2','#991b1b','Recusada'],
        'cancelada'           => ['#f3f4f6','#6b7280','Cancelada'],
        'contraproposta'      => ['#ede9fe','#5b21b6','Contraproposta'],
        'contraoferta'        => ['#ede9fe','#5b21b6','Contraproposta'],
        'negociando'          => ['#fff7ed','#c2410c','Negociando'],
        'finalizada'          => ['#dbeafe','#1e40af','Finalizada'],
        'expirada'            => ['#f3f4f6','#9ca3af','Expirada'],
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
            <option value="aguardando_vendedor" <?= $filterStatus === 'aguardando_vendedor' ? 'selected' : '' ?>>Aguardando</option>
            <option value="aceita" <?= $filterStatus === 'aceita' ? 'selected' : '' ?>>Aceita</option>
            <option value="recusada" <?= $filterStatus === 'recusada' ? 'selected' : '' ?>>Recusada</option>
            <option value="contraoferta" <?= $filterStatus === 'contraoferta' ? 'selected' : '' ?>>Contraproposta</option>
            <option value="negociando" <?= $filterStatus === 'negociando' ? 'selected' : '' ?>>Negociando</option>
            <option value="cancelada" <?= $filterStatus === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
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
                        <th>Minha Proposta</th>
                        <th>Contraproposta</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($propostas as $p): ?>
                    <?php
                    $contraId    = !empty($p['ultima_contra_id'])    ? (int)$p['ultima_contra_id']       : 0;
                    $contraValor = !empty($p['ultima_contra_valor'])  ? (float)$p['ultima_contra_valor']  : 0;
                    ?>
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
                        <td>
                            <?php if ($contraValor > 0): ?>
                            <span style="font-weight:700;color:var(--color-primary);"><?= formatMoney($contraValor) ?></span>
                            <?php else: ?>
                            <span style="color:var(--color-text-muted);font-size:0.8rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= props_statusBadge($p['status']) ?></td>
                        <td style="color:var(--color-text-muted);font-size:0.8125rem;">
                            <?= !empty($p['data_proposta']) ? date('d/m/Y H:i', strtotime($p['data_proposta'])) : '-' ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:0.375rem;flex-wrap:wrap;">
                                <!-- Detalhes sempre visível -->
                                <button class="btn-table-action btn-view" title="Detalhes"
                                        onclick="verProposta(<?= (int)$p['id'] ?>)">
                                    <i class="fa-solid fa-eye"></i>
                                </button>

                                <?php if ($tipo === 'vendedor' || $tipo === 'administrador'): ?>
                                    <?php if (in_array($p['status'], ['aguardando_vendedor', 'aguardando', 'aguardando_comprador'], true)): ?>
                                    <!-- Vendedor: aceitar / recusar / contraproposta -->
                                    <button class="btn-table-action btn-success" title="Aceitar"
                                            onclick="responderProposta(<?= (int)$p['id'] ?>, 'aceitar')">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                    <button class="btn-table-action btn-danger" title="Recusar"
                                            onclick="responderProposta(<?= (int)$p['id'] ?>, 'recusar')">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                    <button class="btn-table-action btn-contra" title="Contraproposta"
                                            onclick="abrirModalContraproposta(<?= (int)$p['id'] ?>, 'vendedor')">
                                        <i class="fa-solid fa-arrows-rotate"></i>
                                    </button>
                                    <?php endif; ?>
                                <?php elseif ($tipo === 'investidor'): ?>
                                    <?php if (in_array($p['status'], ['aguardando_vendedor', 'aguardando', 'aguardando_comprador'], true)): ?>
                                    <!-- Investidor: cancelar proposta aguardando -->
                                    <button class="btn-table-action btn-danger" title="Cancelar"
                                            onclick="responderProposta(<?= (int)$p['id'] ?>, 'cancelar')">
                                        <i class="fa-solid fa-ban"></i>
                                    </button>
                                    <?php elseif (in_array($p['status'], ['contraoferta', 'contraproposta'], true) && $contraId > 0): ?>
                                    <!-- Investidor: responder à contraproposta do vendedor -->
                                    <button class="btn-table-action btn-success" title="Aceitar contraproposta"
                                            onclick="responderProposta(<?= $contraId ?>, 'aceitar')">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                    <button class="btn-table-action btn-danger" title="Recusar contraproposta"
                                            onclick="responderProposta(<?= $contraId ?>, 'recusar')">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                    <button class="btn-table-action btn-contra" title="Nova contraproposta"
                                            onclick="abrirModalContraproposta(<?= $contraId ?>, 'comprador')">
                                        <i class="fa-solid fa-arrows-rotate"></i>
                                    </button>
                                    <?php endif; ?>
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

<!-- Modal: Contraproposta -->
<div class="modal-overlay" id="modalContraproposta" style="display:none;" role="dialog" aria-modal="true">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="contraModalTitle">Enviar Contraproposta</h3>
            <button class="modal-close" onclick="fecharModalContraproposta()" aria-label="Fechar">&times;</button>
        </div>
        <div class="modal-body">
            <div class="alert-box alert-error" id="contraError" style="display:none;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span id="contraErrorMsg"></span>
            </div>
            <form id="formContraproposta" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="proposta_id" id="contraPropostaId">
                <input type="hidden" name="acao" value="contraproposta">
                <div class="form-group">
                    <label class="form-label">Novo Valor da Proposta (R$) <span class="req">*</span></label>
                    <input type="number" name="novo_valor" id="contraNovoValor" class="form-control"
                           placeholder="Ex.: 45000" min="1" step="0.01" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Mensagem (opcional)</label>
                    <textarea name="mensagem" id="contraMensagem" class="form-control"
                              rows="3" placeholder="Informações adicionais…"
                              maxlength="500" style="resize:vertical;"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn-modal-cancel" onclick="fecharModalContraproposta()">Cancelar</button>
            <button class="btn-modal-submit" id="btnEnviarContra">
                <span class="btn-text"><i class="fa-solid fa-arrows-rotate"></i> Enviar Contraproposta</span>
                <div class="spinner"></div>
            </button>
        </div>
    </div>
</div>

<script>
// ── Helpers ───────────────────────────────────────────────────
function escHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(String(str)));
    return div.innerHTML;
}

function fmtBRL(val) {
    return 'R$ ' + parseFloat(val).toLocaleString('pt-BR', {minimumFractionDigits: 2});
}

function statusLabel(s) {
    var m = {
        aguardando_vendedor:  'Aguardando',
        aguardando_comprador: 'Aguardando',
        aguardando:           'Aguardando',
        aceita:              'Aceita',
        recusada:            'Recusada',
        contraoferta:        'Contraproposta',
        contraproposta:      'Contraproposta',
        negociando:          'Negociando',
        cancelada:           'Cancelada',
        finalizada:          'Finalizada',
    };
    return m[s] || s;
}

// ── Detalhes proposta ─────────────────────────────────────────
function verProposta(id) {
    document.getElementById('modalDetalheProposta').style.display = 'flex';
    document.getElementById('modalDetalheBody').innerHTML =
        '<div style="text-align:center;padding:2rem;"><i class="fa-solid fa-spinner fa-spin" style="font-size:1.5rem;color:var(--color-primary);"></i></div>';

    fetch('actions/get_proposta.php?id=' + id)
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success || !data.proposta) {
                document.getElementById('modalDetalheBody').innerHTML =
                    '<p style="color:var(--color-danger);text-align:center;">Erro ao carregar proposta.</p>';
                return;
            }
            var p = data.proposta;
            var html = '<div class="proposta-detalhe">';
            html += '<div class="pd-row"><span class="pd-label">Veículo</span><span class="pd-val">'
                  + escHtml(p.marca + ' ' + p.modelo + ' ' + p.ano_fabrica) + '</span></div>';
            html += '<div class="pd-row"><span class="pd-label">Vendedor</span><span class="pd-val">'
                  + escHtml(p.vendedor_nome) + '</span></div>';
            html += '<div class="pd-row"><span class="pd-label">Comprador</span><span class="pd-val">'
                  + escHtml(p.comprador_nome) + '</span></div>';

            if (p.thread && p.thread.length > 0) {
                html += '<div class="pd-row" style="flex-direction:column;align-items:flex-start;gap:0.5rem;">';
                html += '<span class="pd-label">Histórico de Negociação</span>';
                html += '<div class="thread-list">';
                p.thread.forEach(function(t) {
                    var isVend = (t.vendedor_id === t.usuario_id);
                    var side   = isVend ? 'vendedor' : 'comprador';
                    var nome   = t.usuario_nome;
                    var dt     = t.data_proposta ? new Date(t.data_proposta).toLocaleString('pt-BR') : '';
                    html += '<div class="thread-item thread-' + side + '">';
                    html += '<div class="thread-meta">'
                          + '<strong>' + escHtml(nome) + '</strong>'
                          + ' <span class="thread-role">(' + (isVend ? 'Vendedor' : 'Comprador') + ')</span>'
                          + (dt ? ' <span class="thread-dt">' + escHtml(dt) + '</span>' : '')
                          + '</div>';
                    html += '<div class="thread-valor">' + fmtBRL(t.valor) + '</div>';
                    html += '<div class="thread-status">' + escHtml(statusLabel(t.status)) + '</div>';
                    if (t.mensagem) {
                        html += '<div class="thread-msg">"' + escHtml(t.mensagem) + '"</div>';
                    }
                    html += '</div>';
                });
                html += '</div></div>';
            } else {
                html += '<div class="pd-row"><span class="pd-label">Valor</span><span class="pd-val" style="font-weight:800;color:var(--color-primary);">'
                      + fmtBRL(p.valor) + '</span></div>';
                html += '<div class="pd-row"><span class="pd-label">Status</span><span class="pd-val">'
                      + escHtml(statusLabel(p.status)) + '</span></div>';
                html += '<div class="pd-row"><span class="pd-label">Data</span><span class="pd-val">'
                      + escHtml(p.data_proposta || '-') + '</span></div>';
                if (p.mensagem) {
                    html += '<div class="pd-row"><span class="pd-label">Mensagem</span><span class="pd-val">'
                          + escHtml(p.mensagem) + '</span></div>';
                }
            }

            html += '</div>';
            document.getElementById('modalDetalheBody').innerHTML = html;
        })
        .catch(function () {
            document.getElementById('modalDetalheBody').innerHTML =
                '<p style="color:var(--color-danger);text-align:center;">Erro de conexão.</p>';
        });
}

document.getElementById('modalDetalheProposta').addEventListener('click', function (e) {
    if (e.target === this) this.style.display = 'none';
});

// ── Responder proposta (aceitar/recusar/cancelar) ─────────────
function responderProposta(id, acao) {
    var msgs = {
        aceitar:  'Aceitar esta proposta?',
        recusar:  'Recusar esta proposta?',
        cancelar: 'Cancelar esta proposta?',
    };
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
        })
        .catch(function () { alert('Erro de conexão.'); });
}

// ── Contraproposta modal ──────────────────────────────────────
function abrirModalContraproposta(propostaId, papel) {
    document.getElementById('contraPropostaId').value = propostaId;
    document.getElementById('contraNovoValor').value  = '';
    document.getElementById('contraMensagem').value   = '';
    document.getElementById('contraError').style.display = 'none';
    document.getElementById('btnEnviarContra').disabled = false;
    document.getElementById('btnEnviarContra').classList.remove('loading');
    document.getElementById('contraModalTitle').textContent =
        papel === 'vendedor' ? 'Enviar Contraproposta ao Comprador' : 'Enviar Nova Proposta ao Vendedor';
    document.getElementById('modalContraproposta').style.display = 'flex';
}

function fecharModalContraproposta() {
    document.getElementById('modalContraproposta').style.display = 'none';
}

document.getElementById('btnEnviarContra').addEventListener('click', function () {
    var novoValor = document.getElementById('contraNovoValor');
    if (!novoValor.value || parseFloat(novoValor.value) <= 0) {
        novoValor.classList.add('is-invalid');
        return;
    }
    novoValor.classList.remove('is-invalid');

    var btn = this;
    btn.disabled = true;
    btn.classList.add('loading');

    var fd = new FormData(document.getElementById('formContraproposta'));

    fetch('actions/responder_proposta.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                fecharModalContraproposta();
                window.location.href = '?secao=propostas';
            } else {
                btn.disabled = false;
                btn.classList.remove('loading');
                document.getElementById('contraErrorMsg').textContent = data.message || 'Erro ao enviar.';
                document.getElementById('contraError').style.display = 'flex';
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.classList.remove('loading');
            document.getElementById('contraErrorMsg').textContent = 'Erro de conexão.';
            document.getElementById('contraError').style.display = 'flex';
        });
});

document.getElementById('modalContraproposta').addEventListener('click', function (e) {
    if (e.target === this) fecharModalContraproposta();
});
</script>

<style>
.proposta-detalhe { display: flex; flex-direction: column; gap: 0.875rem; }
.pd-row { display: flex; justify-content: space-between; align-items: flex-start; padding: 0.625rem 0; border-bottom: 1px solid var(--color-border); }
.pd-row:last-child { border-bottom: none; }
.pd-label { font-size: 0.8125rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.04em; }
.pd-val { font-size: 0.9375rem; font-weight: 500; color: var(--color-text); text-align: right; max-width: 65%; }
.btn-success { background: rgba(22,163,74,0.1); color: #15803d; }
.btn-success:hover { background: #16a34a; color: #fff; }
.btn-view { background: rgba(37,99,235,0.1); color: #1d4ed8; }
.btn-view:hover { background: #2563eb; color: #fff; }
.btn-contra { background: rgba(124,58,237,0.1); color: #7c3aed; }
.btn-contra:hover { background: #7c3aed; color: #fff; }
/* Negotiation thread */
.thread-list { display: flex; flex-direction: column; gap: 0.75rem; width: 100%; }
.thread-item { padding: 0.75rem 1rem; border-radius: var(--radius-lg, 0.75rem); border: 1px solid var(--color-border); }
.thread-vendedor { background: #f0fdf4; border-color: #bbf7d0; }
.thread-comprador { background: #eff6ff; border-color: #bfdbfe; }
.thread-meta { font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: 0.3rem; }
.thread-role { font-style: italic; }
.thread-dt { margin-left: 0.5rem; }
.thread-valor { font-size: 1rem; font-weight: 800; color: var(--color-primary); }
.thread-status { font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); margin-top: 0.2rem; }
.thread-msg { font-size: 0.8125rem; color: var(--color-text-muted); margin-top: 0.4rem; font-style: italic; }
</style>
