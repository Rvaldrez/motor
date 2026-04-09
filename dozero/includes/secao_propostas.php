<?php
/**
 * Seção: Propostas
 * $conn, $user, $tipo, $csrfToken available from painel.php
 */

// Ensure proposta_origem_id column exists (migration for old DBs)
$_chk = $conn->query("SHOW COLUMNS FROM propostas LIKE 'proposta_origem_id'");
if ($_chk && $_chk->num_rows === 0) {
    $conn->query("ALTER TABLE propostas ADD COLUMN proposta_origem_id INT(11) DEFAULT NULL, ADD INDEX idx_propostas_origem (proposta_origem_id)");
}
if ($_chk) { $_chk->free(); }

$userId = (int) $user['id'];

$page = max(1, (int) ($_GET['pp'] ?? 1));
$perPage = 10;

$filterStatus = trim($_GET['pstatus'] ?? '');

if ($tipo === 'vendedor') {
    $countSql = "
        SELECT COUNT(*) FROM propostas p
        INNER JOIN veiculos v ON v.id = p.veiculo_id
        WHERE v.usuario_id = ? AND (p.proposta_origem_id IS NULL OR p.proposta_origem_id = 0)
    ";
    $dataSql = "
        SELECT p.id, p.valor, p.status, p.data_proposta, p.mensagem,
               v.marca, v.modelo, v.ano_fabrica, v.id AS veiculo_id,
               u.nome AS contraparte_nome, u.email AS contraparte_email, u.celular AS contraparte_celular,
               (SELECT COUNT(*) FROM propostas cp WHERE cp.proposta_origem_id = p.id) AS respostas,
               (SELECT cp2.id    FROM propostas cp2 WHERE cp2.proposta_origem_id = p.id ORDER BY cp2.id DESC LIMIT 1) AS ultima_contra_id,
               (SELECT cp2.valor FROM propostas cp2 WHERE cp2.proposta_origem_id = p.id ORDER BY cp2.id DESC LIMIT 1) AS ultima_contra_valor,
               (SELECT cp2.status FROM propostas cp2 WHERE cp2.proposta_origem_id = p.id ORDER BY cp2.id DESC LIMIT 1) AS ultima_contra_status
        FROM propostas p
        INNER JOIN veiculos v ON v.id = p.veiculo_id
        LEFT JOIN usuarios u ON u.id = p.usuario_id
        WHERE v.usuario_id = ? AND (p.proposta_origem_id IS NULL OR p.proposta_origem_id = 0)
    ";
    $baseParams = [$userId];
    $baseTypes  = 'i';
    $sectionTitle = 'Propostas Recebidas';
    $contraparteLabel = 'Comprador';

} elseif ($tipo === 'investidor') {
    $countSql = "
        SELECT COUNT(*) FROM propostas p
        WHERE p.usuario_id = ? AND (p.proposta_origem_id IS NULL OR p.proposta_origem_id = 0)
    ";
    $dataSql = "
        SELECT p.id, p.valor, p.status, p.data_proposta, p.mensagem,
               v.marca, v.modelo, v.ano_fabrica, v.id AS veiculo_id,
               u.nome AS contraparte_nome, u.email AS contraparte_email, u.celular AS contraparte_celular,
               (SELECT COUNT(*) FROM propostas cp WHERE cp.proposta_origem_id = p.id) AS respostas,
               (SELECT cp2.id    FROM propostas cp2 WHERE cp2.proposta_origem_id = p.id ORDER BY cp2.id DESC LIMIT 1) AS ultima_contra_id,
               (SELECT cp2.valor FROM propostas cp2 WHERE cp2.proposta_origem_id = p.id ORDER BY cp2.id DESC LIMIT 1) AS ultima_contra_valor,
               (SELECT cp2.status FROM propostas cp2 WHERE cp2.proposta_origem_id = p.id ORDER BY cp2.id DESC LIMIT 1) AS ultima_contra_status
        FROM propostas p
        INNER JOIN veiculos v ON v.id = p.veiculo_id
        INNER JOIN usuarios u ON u.id = v.usuario_id
        WHERE p.usuario_id = ? AND (p.proposta_origem_id IS NULL OR p.proposta_origem_id = 0)
    ";
    $baseParams = [$userId];
    $baseTypes  = 'i';
    $sectionTitle = 'Minhas Propostas';
    $contraparteLabel = 'Vendedor';

} else { // administrador
    $countSql = "SELECT COUNT(*) FROM propostas p INNER JOIN veiculos v ON v.id = p.veiculo_id WHERE (p.proposta_origem_id IS NULL OR p.proposta_origem_id = 0)";
    $dataSql = "
        SELECT p.id, p.valor, p.status, p.data_proposta, p.mensagem,
               v.marca, v.modelo, v.ano_fabrica, v.id AS veiculo_id,
               u.nome AS contraparte_nome, u.email AS contraparte_email, u.celular AS contraparte_celular,
               (SELECT COUNT(*) FROM propostas cp WHERE cp.proposta_origem_id = p.id) AS respostas,
               (SELECT cp2.id    FROM propostas cp2 WHERE cp2.proposta_origem_id = p.id ORDER BY cp2.id DESC LIMIT 1) AS ultima_contra_id,
               (SELECT cp2.valor FROM propostas cp2 WHERE cp2.proposta_origem_id = p.id ORDER BY cp2.id DESC LIMIT 1) AS ultima_contra_valor,
               (SELECT cp2.status FROM propostas cp2 WHERE cp2.proposta_origem_id = p.id ORDER BY cp2.id DESC LIMIT 1) AS ultima_contra_status
        FROM propostas p
        INNER JOIN veiculos v ON v.id = p.veiculo_id
        INNER JOIN usuarios u ON u.id = p.usuario_id
        WHERE (p.proposta_origem_id IS NULL OR p.proposta_origem_id = 0)
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

// Compute effective status & action ID for each proposal
foreach ($propostas as &$p) {
    $hasChild = !empty($p['ultima_contra_id']);
    $p['effective_status'] = $hasChild ? ($p['ultima_contra_status'] ?? $p['status']) : $p['status'];
    $p['effective_id']     = $hasChild ? (int)$p['ultima_contra_id'] : (int)$p['id'];
}
unset($p);
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
            <option value="aceita"              <?= $filterStatus === 'aceita'              ? 'selected' : '' ?>>Aceita</option>
            <option value="recusada"            <?= $filterStatus === 'recusada'            ? 'selected' : '' ?>>Recusada</option>
            <option value="contraoferta"        <?= $filterStatus === 'contraoferta'        ? 'selected' : '' ?>>Contraproposta</option>
            <option value="negociando"          <?= $filterStatus === 'negociando'          ? 'selected' : '' ?>>Negociando</option>
            <option value="cancelada"           <?= $filterStatus === 'cancelada'           ? 'selected' : '' ?>>Cancelada</option>
        </select>
        <button type="submit" class="btn-filter-apply">Filtrar</button>
        <?php if ($filterStatus !== ''): ?>
        <a href="?secao=propostas" class="btn-filter-clear">Limpar</a>
        <?php endif; ?>
    </form>

    <?php if (empty($propostas)): ?>
    <div class="table-card">
        <div class="table-empty">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            <p>Nenhuma proposta encontrada.</p>
        </div>
    </div>
    <?php else: ?>

    <!-- Proposal Cards -->
    <div class="prop-cards">
        <?php foreach ($propostas as $p):
            $effStatus = $p['effective_status'];
            $effId     = (int) $p['effective_id'];
            $rootId    = (int) $p['id'];
            $hasHistory = (int)($p['respostas'] ?? 0) > 0;
            // Determine if the offer is in a terminal refused state
            $isRecusada = in_array($effStatus, ['recusada', 'cancelada'], true);
            // For display, use effective_status badge
        ?>
        <div class="prop-card" id="pcard-<?= $rootId ?>">

            <!-- Card Header -->
            <div class="pc-header">
                <div class="pc-vehicle">
                    <i class="fa-solid fa-car" style="color:var(--color-primary);margin-right:0.5rem;"></i>
                    <strong><?= htmlspecialchars($p['marca'] . ' ' . $p['modelo'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="pc-year"><?= htmlspecialchars($p['ano_fabrica'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="pc-header-right">
                    <?= props_statusBadge($effStatus) ?>
                    <span class="pc-date"><?= !empty($p['data_proposta']) ? date('d/m/Y H:i', strtotime($p['data_proposta'])) : '-' ?></span>
                </div>
            </div>

            <!-- Card Body -->
            <div class="pc-body">
                <div class="pc-info-row">
                    <div class="pc-info-item">
                        <span class="pc-info-label"><?= htmlspecialchars($contraparteLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="pc-info-val">
                            <?= htmlspecialchars($p['contraparte_nome'] ?? 'Usuário não encontrado', ENT_QUOTES, 'UTF-8') ?>
                            <small><?= htmlspecialchars($p['contraparte_email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></small>
                        </span>
                    </div>
                    <div class="pc-info-item">
                        <span class="pc-info-label">Proposta Inicial</span>
                        <span class="pc-info-val pc-valor"><?= formatMoney((float)$p['valor']) ?></span>
                    </div>
                    <?php if (!empty($p['ultima_contra_valor'])): ?>
                    <div class="pc-info-item">
                        <span class="pc-info-label">Última Contraproposta</span>
                        <span class="pc-info-val pc-valor pc-contra"><?= formatMoney((float)$p['ultima_contra_valor']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($p['mensagem'])): ?>
                    <div class="pc-info-item pc-info-full">
                        <span class="pc-info-label">Mensagem</span>
                        <span class="pc-info-val"><?= htmlspecialchars($p['mensagem'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Inline History Thread -->
                <div class="pc-history-wrap <?= $isRecusada ? 'phw-recusada' : '' ?>"
                     id="phw-<?= $rootId ?>"
                     data-rootid="<?= $rootId ?>">
                    <?php if ($isRecusada): ?>
                    <!-- For refused proposals, always show the load history trigger -->
                    <div class="pc-history-header" onclick="toggleHistory(<?= $rootId ?>)" id="phh-<?= $rootId ?>" style="cursor:pointer;">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span>Histórico da Negociação</span>
                        <i class="fa-solid fa-chevron-down pc-toggle-icon" id="pti-<?= $rootId ?>"></i>
                    </div>
                    <?php elseif ($hasHistory): ?>
                    <div class="pc-history-header" onclick="toggleHistory(<?= $rootId ?>)" id="phh-<?= $rootId ?>" style="cursor:pointer;">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span>Histórico de Negociação (<?= (int)$p['respostas'] ?> rodada<?= (int)$p['respostas'] !== 1 ? 's' : '' ?>)</span>
                        <i class="fa-solid fa-chevron-down pc-toggle-icon" id="pti-<?= $rootId ?>"></i>
                    </div>
                    <?php endif; ?>

                    <?php if ($isRecusada || $hasHistory): ?>
                    <div class="pc-history-body" id="phb-<?= $rootId ?>" style="display:<?= $isRecusada ? 'block' : 'none' ?>;">
                        <div class="pc-history-loading" id="phl-<?= $rootId ?>">
                            <i class="fa-solid fa-spinner fa-spin"></i> Carregando histórico…
                        </div>
                        <div class="pc-thread-list" id="ptl-<?= $rootId ?>"></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card Footer: action buttons -->
            <div class="pc-footer">
                <?php if ($tipo === 'vendedor' || $tipo === 'administrador'): ?>
                    <?php if (in_array($effStatus, ['aguardando_vendedor', 'aguardando', 'aguardando_comprador'], true)): ?>
                    <button class="pc-btn pc-btn-success" onclick="responderProposta(<?= $effId ?>, 'aceitar')">
                        <i class="fa-solid fa-check"></i> Aceitar
                    </button>
                    <button class="pc-btn pc-btn-danger"  onclick="responderProposta(<?= $effId ?>, 'recusar')">
                        <i class="fa-solid fa-xmark"></i> Recusar
                    </button>
                    <button class="pc-btn pc-btn-contra"  onclick="abrirModalContraproposta(<?= $effId ?>, 'vendedor')">
                        <i class="fa-solid fa-arrows-rotate"></i> Contraproposta
                    </button>
                    <?php elseif (in_array($effStatus, ['contraoferta','contraproposta','negociando'], true)): ?>
                    <span class="pc-waiting-msg"><i class="fa-solid fa-hourglass-half"></i> Aguardando resposta do comprador</span>
                    <?php elseif ($effStatus === 'aceita'): ?>
                    <div class="pc-accepted-info">
                        <i class="fa-solid fa-circle-check" style="color:var(--color-success,#16a34a);font-size:1.1rem;"></i>
                        <strong>Negócio fechado!</strong>
                        <span>Entre em contato com o comprador:</span>
                        <span><i class="fa-solid fa-user"></i> <?= htmlspecialchars($p['contraparte_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                        <a href="mailto:<?= htmlspecialchars($p['contraparte_email'], ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($p['contraparte_email'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <?php if (!empty($p['contraparte_celular'])): ?>
                        <a href="https://wa.me/55<?= preg_replace('/\D/', '', $p['contraparte_celular']) ?>" target="_blank" rel="noopener">
                            <i class="fa-brands fa-whatsapp" style="color:#16a34a;"></i> <?= htmlspecialchars($p['contraparte_celular'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                <?php elseif ($tipo === 'investidor'): ?>
                    <?php if (in_array($effStatus, ['aguardando_vendedor', 'aguardando', 'aguardando_comprador'], true)): ?>
                    <button class="pc-btn pc-btn-danger" onclick="responderProposta(<?= $effId ?>, 'cancelar')">
                        <i class="fa-solid fa-ban"></i> Cancelar Proposta
                    </button>
                    <?php elseif (in_array($effStatus, ['contraoferta','contraproposta'], true)): ?>
                    <button class="pc-btn pc-btn-success" onclick="responderProposta(<?= $effId ?>, 'aceitar')">
                        <i class="fa-solid fa-check"></i> Aceitar Oferta
                    </button>
                    <button class="pc-btn pc-btn-danger"  onclick="responderProposta(<?= $effId ?>, 'recusar')">
                        <i class="fa-solid fa-xmark"></i> Recusar
                    </button>
                    <button class="pc-btn pc-btn-contra"  onclick="abrirModalContraproposta(<?= $effId ?>, 'comprador')">
                        <i class="fa-solid fa-arrows-rotate"></i> Nova Proposta
                    </button>
                    <?php elseif ($effStatus === 'aceita'): ?>
                    <div class="pc-accepted-info">
                        <i class="fa-solid fa-circle-check" style="color:var(--color-success,#16a34a);font-size:1.1rem;"></i>
                        <strong>Negócio fechado!</strong>
                        <span>Entre em contato com o vendedor:</span>
                        <span><i class="fa-solid fa-user"></i> <?= htmlspecialchars($p['contraparte_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                        <a href="mailto:<?= htmlspecialchars($p['contraparte_email'], ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($p['contraparte_email'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <?php if (!empty($p['contraparte_celular'])): ?>
                        <a href="https://wa.me/55<?= preg_replace('/\D/', '', $p['contraparte_celular']) ?>" target="_blank" rel="noopener">
                            <i class="fa-brands fa-whatsapp" style="color:#16a34a;"></i> <?= htmlspecialchars($p['contraparte_celular'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php elseif ($isRecusada): ?>
                    <div class="pc-recusada-info">
                        <i class="fa-solid fa-circle-xmark" style="color:var(--color-danger);"></i>
                        <span>Oferta recusada. Você pode enviar uma nova proposta para este veículo.</span>
                    </div>
                    <a href="?secao=oferta" class="pc-btn pc-btn-primary">
                        <i class="fa-solid fa-plus"></i> Nova Proposta
                    </a>
                    <?php elseif ($effStatus === 'negociando'): ?>
                    <span class="pc-waiting-msg"><i class="fa-solid fa-hourglass-half"></i> Aguardando resposta do vendedor</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="table-pagination" style="background:#fff;border:1px solid var(--color-border);border-radius:var(--radius-xl);padding:1rem 1.25rem;margin-top:1rem;">
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
                    <label class="form-label">Novo Valor (R$) <span class="req">*</span></label>
                    <input type="text" name="novo_valor" id="contraNovoValor" class="form-control"
                           placeholder="Ex.: 45.000" inputmode="numeric" required>
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
                <span class="btn-text"><i class="fa-solid fa-arrows-rotate"></i> Enviar</span>
                <div class="spinner"></div>
            </button>
        </div>
    </div>
</div>

<script>
var _historyCache = {};
var _historyOpen  = {};

function escHtml(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(String(str)));
    return d.innerHTML;
}
function fmtBRL(val) {
    var n = Math.round(parseFloat(val) || 0);
    return 'R$\u00a0' + n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',00';
}
function statusLabel(s) {
    var m = {
        aguardando_vendedor:  'Aguardando',
        aguardando_comprador: 'Aguardando',
        aguardando:           'Aguardando',
        aceita:               'Aceita ✓',
        recusada:             'Recusada ✗',
        contraoferta:         'Contraproposta',
        contraproposta:       'Contraproposta',
        negociando:           'Negociando',
        cancelada:            'Cancelada',
        finalizada:           'Finalizada',
    };
    return m[s] || s;
}

function renderThread(rootId, thread, vendedorId) {
    var list = document.getElementById('ptl-' + rootId);
    if (!list) return;
    if (!thread || thread.length === 0) {
        list.innerHTML = '<p style="color:var(--color-text-muted);font-size:0.85rem;text-align:center;">Sem histórico adicional.</p>';
        return;
    }
    var html = '';
    thread.forEach(function(t, idx) {
        var isVend  = (parseInt(t.vendedor_id) === parseInt(t.usuario_id));
        var side    = isVend ? 'vend' : 'comp';
        var dt      = t.data_proposta ? new Date(t.data_proposta).toLocaleString('pt-BR') : '';
        var isLast  = (idx === thread.length - 1);
        html += '<div class="pt-item pt-' + side + (isLast ? ' pt-last' : '') + '">';
        html += '<div class="pt-avatar">' + (isVend ? '<i class="fa-solid fa-store"></i>' : '<i class="fa-solid fa-user"></i>') + '</div>';
        html += '<div class="pt-content">';
        html += '<div class="pt-meta"><strong>' + escHtml(t.usuario_nome) + '</strong>'
             +  '<span class="pt-role">(' + (isVend ? 'Vendedor' : 'Comprador') + ')</span>'
             +  (dt ? '<span class="pt-dt">' + escHtml(dt) + '</span>' : '')
             +  '</div>';
        html += '<div class="pt-valor">' + fmtBRL(t.valor) + '</div>';
        html += '<div class="pt-status">' + statusLabel(t.status) + '</div>';
        if (t.mensagem) {
            html += '<div class="pt-msg">"' + escHtml(t.mensagem) + '"</div>';
        }
        html += '</div></div>';
    });
    list.innerHTML = html;
}

function toggleHistory(rootId) {
    var body  = document.getElementById('phb-' + rootId);
    var icon  = document.getElementById('pti-' + rootId);
    if (!body) return;

    var isOpen = _historyOpen[rootId];
    if (isOpen) {
        body.style.display = 'none';
        if (icon) icon.style.transform = '';
        _historyOpen[rootId] = false;
        return;
    }

    body.style.display = 'block';
    if (icon) icon.style.transform = 'rotate(180deg)';
    _historyOpen[rootId] = true;

    if (_historyCache[rootId]) {
        renderThread(rootId, _historyCache[rootId].thread, _historyCache[rootId].vendedorId);
        document.getElementById('phl-' + rootId).style.display = 'none';
        return;
    }

    fetch('actions/get_proposta.php?id=' + rootId)
        .then(function(r){ return r.json(); })
        .then(function(data){
            document.getElementById('phl-' + rootId).style.display = 'none';
            if (data.success && data.proposta) {
                _historyCache[rootId] = { thread: data.proposta.thread, vendedorId: data.proposta.vendedor_id };
                renderThread(rootId, data.proposta.thread, data.proposta.vendedor_id);
            } else {
                document.getElementById('ptl-' + rootId).innerHTML =
                    '<p style="color:var(--color-danger);text-align:center;">Erro ao carregar histórico.</p>';
            }
        })
        .catch(function(){
            document.getElementById('phl-' + rootId).style.display = 'none';
            document.getElementById('ptl-' + rootId).innerHTML =
                '<p style="color:var(--color-danger);text-align:center;">Erro de conexão.</p>';
        });
}

// ── Responder (aceitar/recusar/cancelar) ─────────────────────
function responderProposta(id, acao) {
    var msgs = {
        aceitar:  'Aceitar esta proposta/oferta?',
        recusar:  'Recusar esta proposta?',
        cancelar: 'Cancelar sua proposta?',
    };
    if (!confirm(msgs[acao] || 'Confirmar ação?')) return;

    var fd = new FormData();
    fd.append('proposta_id', id);
    fd.append('acao', acao);
    fd.append('csrf_token', '<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>');

    fetch('actions/responder_proposta.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (data.success) {
                window.location.href = '?secao=propostas';
            } else {
                alert(data.message || 'Erro ao processar ação.');
            }
        })
        .catch(function(){ alert('Erro de conexão.'); });
}

// ── Modal Contraproposta ──────────────────────────────────────
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

// Currency mask for counter-offer input
(function() {
    function _fmtContra(n) {
        return 'R$ ' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    var contraInput = document.getElementById('contraNovoValor');
    if (contraInput) {
        contraInput.addEventListener('input', function() {
            var digits = this.value.replace(/\D/g, '');
            if (!digits) { this.value = ''; return; }
            this.value = _fmtContra(parseInt(digits, 10));
        });
    }
})();

document.getElementById('btnEnviarContra').addEventListener('click', function() {
    var novoValor = document.getElementById('contraNovoValor');
    var digits = novoValor.value.replace(/\D/g, '');
    var numVal = digits ? parseInt(digits, 10) : 0;
    if (!numVal || numVal <= 0) {
        novoValor.classList.add('is-invalid');
        return;
    }
    novoValor.classList.remove('is-invalid');
    var btn = this;
    btn.disabled = true;
    btn.classList.add('loading');

    var fd = new FormData(document.getElementById('formContraproposta'));
    fd.set('novo_valor', numVal.toString());

    fetch('actions/responder_proposta.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (data.success) {
                fecharModalContraproposta();
                window.location.href = '?secao=propostas';
            } else {
                btn.disabled = false;
                btn.classList.remove('loading');
                document.getElementById('contraErrorMsg').textContent = data.message || 'Erro.';
                document.getElementById('contraError').style.display = 'flex';
            }
        })
        .catch(function(){
            btn.disabled = false;
            btn.classList.remove('loading');
            document.getElementById('contraErrorMsg').textContent = 'Erro de conexão.';
            document.getElementById('contraError').style.display = 'flex';
        });
});
document.getElementById('modalContraproposta').addEventListener('click', function(e) {
    if (e.target === this) fecharModalContraproposta();
});

// Auto-expand refused cards on load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.phw-recusada').forEach(function(el) {
        var rootId = parseInt(el.getAttribute('data-rootid'), 10);
        if (!isNaN(rootId) && rootId > 0) toggleHistory(rootId);
    });
});
</script>

<style>
/* ── Proposal Cards ────────────────────────────────────── */
.prop-cards { display: flex; flex-direction: column; gap: 1rem; }

.prop-card {
    background: #fff;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    transition: box-shadow 0.2s;
}
.prop-card:hover { box-shadow: var(--shadow-md, 0 4px 16px rgba(0,0,0,0.1)); }

/* Header */
.pc-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.875rem 1.25rem;
    background: var(--color-bg, #f8f9fa);
    border-bottom: 1px solid var(--color-border);
    flex-wrap: wrap;
    gap: 0.5rem;
}
.pc-vehicle { display: flex; align-items: center; font-size: 0.9375rem; gap: 0.25rem; }
.pc-year { font-size: 0.8125rem; color: var(--color-text-muted); margin-left: 0.4rem; }
.pc-header-right { display: flex; align-items: center; gap: 0.75rem; }
.pc-date { font-size: 0.8rem; color: var(--color-text-muted); }

/* Body */
.pc-body { padding: 1rem 1.25rem; }
.pc-info-row { display: flex; flex-wrap: wrap; gap: 1.25rem; margin-bottom: 1rem; }
.pc-info-item { display: flex; flex-direction: column; gap: 0.2rem; min-width: 140px; }
.pc-info-item.pc-info-full { flex: 1 1 100%; }
.pc-info-label { font-size: 0.75rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.04em; }
.pc-info-val { font-size: 0.875rem; color: var(--color-text); }
.pc-info-val small { display: block; font-size: 0.75rem; color: var(--color-text-muted); }
.pc-valor { font-weight: 800; font-size: 1rem; color: var(--color-text); }
.pc-contra { color: var(--color-primary); }

/* History section */
.pc-history-wrap { border-top: 1px solid var(--color-border); margin-top: 0.5rem; }
.pc-history-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 0;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--color-text-muted);
    user-select: none;
}
.pc-history-header:hover { color: var(--color-primary); }
.pc-toggle-icon { margin-left: auto; transition: transform 0.2s; font-size: 0.75rem; }
.pc-history-body { padding: 0.75rem 0; }
.pc-history-loading { text-align: center; padding: 1rem; color: var(--color-text-muted); font-size: 0.875rem; }

/* Thread items */
.pc-thread-list { display: flex; flex-direction: column; gap: 0; }
.pt-item {
    display: flex;
    gap: 0.75rem;
    padding: 0.75rem 0.25rem;
    border-left: 2px solid var(--color-border);
    margin-left: 0.75rem;
    padding-left: 1rem;
    position: relative;
}
.pt-item::before {
    content: '';
    position: absolute;
    left: -6px;
    top: 1rem;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--color-border);
    border: 2px solid #fff;
}
.pt-vend { border-left-color: #16a34a; }
.pt-vend::before { background: #16a34a; }
.pt-comp { border-left-color: var(--color-primary); }
.pt-comp::before { background: var(--color-primary); }
.pt-last { border-left-color: transparent; }
.pt-avatar {
    width: 32px; height: 32px;
    background: var(--color-bg);
    border: 1px solid var(--color-border);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem;
    color: var(--color-text-muted);
    flex-shrink: 0;
}
.pt-content { flex: 1; }
.pt-meta { font-size: 0.8rem; color: var(--color-text-muted); margin-bottom: 0.2rem; display: flex; flex-wrap: wrap; gap: 0.25rem 0.5rem; align-items: baseline; }
.pt-meta strong { color: var(--color-text); }
.pt-role { font-style: italic; }
.pt-dt { font-size: 0.75rem; }
.pt-valor { font-size: 0.9375rem; font-weight: 800; color: var(--color-primary); }
.pt-status { font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); }
.pt-msg { font-size: 0.8125rem; color: var(--color-text-muted); font-style: italic; margin-top: 0.2rem; }

/* Footer */
.pc-footer {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding: 0.75rem 1.25rem;
    border-top: 1px solid var(--color-border);
    background: var(--color-bg, #f8f9fa);
}
.pc-btn {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.45rem 1rem;
    border: none; border-radius: var(--radius-full, 9999px);
    font-size: 0.8125rem; font-weight: 700;
    cursor: pointer; transition: var(--transition);
    text-decoration: none;
}
.pc-btn-success { background: rgba(22,163,74,0.1); color: #15803d; }
.pc-btn-success:hover { background: #16a34a; color: #fff; }
.pc-btn-danger  { background: rgba(220,38,38,0.1); color: var(--color-danger); }
.pc-btn-danger:hover  { background: var(--color-danger); color: #fff; }
.pc-btn-contra  { background: rgba(124,58,237,0.1); color: #7c3aed; }
.pc-btn-contra:hover  { background: #7c3aed; color: #fff; }
.pc-btn-primary { background: var(--color-primary); color: #fff; }
.pc-btn-primary:hover { background: var(--color-primary-dark); color: #fff; }
.pc-waiting-msg { font-size: 0.8125rem; color: var(--color-text-muted); display: flex; align-items: center; gap: 0.35rem; }
.pc-recusada-info { font-size: 0.8125rem; color: var(--color-text-muted); display: flex; align-items: center; gap: 0.35rem; flex: 1; }
.pc-accepted-info {
    display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem 1rem;
    font-size: 0.8125rem; flex: 1;
}
.pc-accepted-info strong { color: #15803d; }
.pc-accepted-info span, .pc-accepted-info a {
    display: inline-flex; align-items: center; gap: 0.3rem;
    color: var(--color-text-muted); text-decoration: none;
}
.pc-accepted-info a:hover { color: var(--color-primary); text-decoration: underline; }
</style>
