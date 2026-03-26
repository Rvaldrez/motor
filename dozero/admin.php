<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireAdmin();

$user = getCurrentUser();
$csrfToken = generateCsrfToken();

// ── Active section ───────────────────────────────────────────
$activeSection = trim($_GET['secao'] ?? '');
$allowedSections = ['overview','usuarios','veiculos','propostas'];
if (!in_array($activeSection, $allowedSections, true)) {
    $activeSection = 'overview';
}

// ── Stats (always needed for sidebar badges) ─────────────────
$stmt = $conn->prepare("SELECT COUNT(*) FROM usuarios");
$stmt->execute(); $stmt->bind_result($statUsuarios); $stmt->fetch(); $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM veiculos");
$stmt->execute(); $stmt->bind_result($statVeiculos); $stmt->fetch(); $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM propostas WHERE proposta_origem_id IS NULL");
$stmt->execute(); $stmt->bind_result($statPropostas); $stmt->fetch(); $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM propostas WHERE status = 'aguardando' AND proposta_origem_id IS NULL");
$stmt->execute(); $stmt->bind_result($statPendentes); $stmt->fetch(); $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE status_confirmacao = 'pendente'");
$stmt->execute(); $stmt->bind_result($statUsuariosPendentes); $stmt->fetch(); $stmt->close();

// ── Data per section ─────────────────────────────────────────
$usuarios  = [];
$veiculos  = [];
$propostas = [];

// Pagination
$perPage = 15;

// ── Usuarios section ─────────────────────────────────────────
$uPage        = max(1, (int) ($_GET['up'] ?? 1));
$uSearch      = trim($_GET['us'] ?? '');
$uFilterTipo  = trim($_GET['utipo'] ?? '');
$uFilterStatus = trim($_GET['ustatus'] ?? '');

$uConditions = ' WHERE 1=1';
$uParams     = [];
$uTypes      = '';

if ($uSearch !== '') {
    $uConditions .= " AND (u.nome LIKE ? OR u.email LIKE ? OR u.cpf LIKE ?)";
    $like = '%' . $uSearch . '%';
    $uParams[] = $like; $uParams[] = $like; $uParams[] = $like;
    $uTypes .= 'sss';
}
if ($uFilterTipo !== '') {
    $uConditions .= " AND u.tipo = ?";
    $uParams[] = $uFilterTipo;
    $uTypes .= 's';
}
if ($uFilterStatus !== '') {
    $uConditions .= " AND u.status_confirmacao = ?";
    $uParams[] = $uFilterStatus;
    $uTypes .= 's';
}

$uCountStmt = $conn->prepare("SELECT COUNT(*) FROM usuarios u" . $uConditions);
if (!empty($uParams)) $uCountStmt->bind_param($uTypes, ...$uParams);
$uCountStmt->execute();
$uCountStmt->bind_result($uTotal);
$uCountStmt->fetch();
$uCountStmt->close();

$uTotalPages = max(1, (int) ceil($uTotal / $perPage));
$uPage = min($uPage, $uTotalPages);
$uOffset = ($uPage - 1) * $perPage;

if ($activeSection === 'usuarios' || $activeSection === 'overview') {
    $uDataStmt = $conn->prepare("
        SELECT u.id, u.nome, u.email, u.tipo, u.status_confirmacao, u.status_cadastro,
               u.data_cadastro, u.celular,
               (SELECT COUNT(*) FROM veiculos v WHERE v.usuario_id = u.id) AS total_veiculos,
               (SELECT COUNT(*) FROM propostas p WHERE p.usuario_id = u.id) AS total_propostas
        FROM usuarios u" . $uConditions . "
        ORDER BY u.data_cadastro DESC LIMIT ? OFFSET ?
    ");
    $uAllParams = array_merge($uParams, [$perPage, $uOffset]);
    $uAllTypes  = $uTypes . 'ii';
    if (!empty($uAllParams)) $uDataStmt->bind_param($uAllTypes, ...$uAllParams);
    $uDataStmt->execute();
    $uResult = $uDataStmt->get_result();
    while ($row = $uResult->fetch_assoc()) $usuarios[] = $row;
    $uDataStmt->close();
}

// ── Veiculos section ─────────────────────────────────────────
$vPage        = max(1, (int) ($_GET['vp'] ?? 1));
$vSearch      = trim($_GET['vs'] ?? '');
$vFilterStatus = trim($_GET['vstatus'] ?? '');

$vConditions = ' WHERE 1=1';
$vParams     = [];
$vTypes      = '';

if ($vSearch !== '') {
    $vConditions .= " AND (v.marca LIKE ? OR v.modelo LIKE ? OR v.placa LIKE ? OR u.nome LIKE ?)";
    $like = '%' . $vSearch . '%';
    $vParams[] = $like; $vParams[] = $like; $vParams[] = $like; $vParams[] = $like;
    $vTypes .= 'ssss';
}
if ($vFilterStatus !== '') {
    $vConditions .= " AND v.status = ?";
    $vParams[] = $vFilterStatus;
    $vTypes .= 's';
}

$vBase = " FROM veiculos v INNER JOIN usuarios u ON u.id = v.usuario_id";

$vCountStmt = $conn->prepare("SELECT COUNT(*)" . $vBase . $vConditions);
if (!empty($vParams)) $vCountStmt->bind_param($vTypes, ...$vParams);
$vCountStmt->execute();
$vCountStmt->bind_result($vTotal);
$vCountStmt->fetch();
$vCountStmt->close();

$vTotalPages = max(1, (int) ceil($vTotal / $perPage));
$vPage = min($vPage, $vTotalPages);
$vOffset = ($vPage - 1) * $perPage;

if ($activeSection === 'veiculos') {
    $vDataStmt = $conn->prepare("
        SELECT v.id, v.placa, v.marca, v.modelo, v.ano_fabrica, v.quilometragem,
               v.preco, v.status, v.data_cadastro, v.foto_principal,
               u.nome AS vendedor_nome, u.id AS vendedor_id
        " . $vBase . $vConditions . "
        ORDER BY v.data_cadastro DESC LIMIT ? OFFSET ?
    ");
    $vAllParams = array_merge($vParams, [$perPage, $vOffset]);
    $vAllTypes  = $vTypes . 'ii';
    if (!empty($vAllParams)) $vDataStmt->bind_param($vAllTypes, ...$vAllParams);
    $vDataStmt->execute();
    $vResult = $vDataStmt->get_result();
    while ($row = $vResult->fetch_assoc()) $veiculos[] = $row;
    $vDataStmt->close();
}

// ── Propostas section ─────────────────────────────────────────
$pPage        = max(1, (int) ($_GET['pp'] ?? 1));
$pFilterStatus = trim($_GET['pstatus'] ?? '');

$pConditions = ' WHERE p.proposta_origem_id IS NULL';
$pParams     = [];
$pTypes      = '';

if ($pFilterStatus !== '') {
    $pConditions .= " AND p.status = ?";
    $pParams[] = $pFilterStatus;
    $pTypes .= 's';
}

$pCountStmt = $conn->prepare("SELECT COUNT(*) FROM propostas p" . $pConditions);
if (!empty($pParams)) $pCountStmt->bind_param($pTypes, ...$pParams);
$pCountStmt->execute();
$pCountStmt->bind_result($pTotal);
$pCountStmt->fetch();
$pCountStmt->close();

$pTotalPages = max(1, (int) ceil($pTotal / $perPage));
$pPage = min($pPage, $pTotalPages);
$pOffset = ($pPage - 1) * $perPage;

if ($activeSection === 'propostas') {
    $pDataStmt = $conn->prepare("
        SELECT p.id, p.valor, p.status, p.data_proposta, p.mensagem,
               v.marca, v.modelo, v.ano_fabrica,
               inv.nome AS investidor_nome, inv.email AS investidor_email,
               vend.nome AS vendedor_nome
        FROM propostas p
        INNER JOIN veiculos v ON v.id = p.veiculo_id
        INNER JOIN usuarios inv ON inv.id = p.usuario_id
        INNER JOIN usuarios vend ON vend.id = v.usuario_id
        " . $pConditions . "
        ORDER BY p.data_proposta DESC LIMIT ? OFFSET ?
    ");
    $pAllParams = array_merge($pParams, [$perPage, $pOffset]);
    $pAllTypes  = $pTypes . 'ii';
    if (!empty($pAllParams)) $pDataStmt->bind_param($pAllTypes, ...$pAllParams);
    $pDataStmt->execute();
    $pResult = $pDataStmt->get_result();
    while ($row = $pResult->fetch_assoc()) $propostas[] = $row;
    $pDataStmt->close();
}

// ── Page title ───────────────────────────────────────────────
$pageTitles = [
    'overview'  => 'Visão Geral',
    'usuarios'  => 'Usuários',
    'veiculos'  => 'Veículos',
    'propostas' => 'Propostas',
];
$pageTitle = $pageTitles[$activeSection];

// ── Helpers ──────────────────────────────────────────────────
function admin_badge(string $status, array $presets = []): string {
    $defaults = [
        'disponivel'     => ['#d1fae5','#065f46','Disponível'],
        'em_negociacao'  => ['#fef3c7','#92400e','Em Negociação'],
        'vendido'        => ['#dbeafe','#1e40af','Vendido'],
        'pausado'        => ['#f3f4f6','#6b7280','Pausado'],
        'pendente'       => ['#fef3c7','#92400e','Pendente'],
        'reprovado'      => ['#fee2e2','#991b1b','Reprovado'],
        'aguardando'     => ['#fef3c7','#92400e','Aguardando'],
        'aceita'         => ['#d1fae5','#065f46','Aceita'],
        'recusada'       => ['#fee2e2','#991b1b','Recusada'],
        'cancelada'      => ['#f3f4f6','#6b7280','Cancelada'],
        'contraproposta' => ['#ede9fe','#5b21b6','Contraproposta'],
        'finalizada'     => ['#dbeafe','#1e40af','Finalizada'],
        'confirmado'     => ['#d1fae5','#065f46','Confirmado'],
        'vendedor'       => ['#dbeafe','#1e40af','Vendedor'],
        'investidor'     => ['#d1fae5','#065f46','Investidor'],
        'administrador'  => ['#fce7f3','#9d174d','Administrador'],
        'completo'       => ['#d1fae5','#065f46','Completo'],
        'incompleto'     => ['#fef3c7','#92400e','Incompleto'],
    ];
    $map = array_merge($defaults, $presets);
    $d = $map[$status] ?? ['#f3f4f6','#6b7280', ucfirst(str_replace('_',' ',$status))];
    return '<span style="background:' . $d[0] . ';color:' . $d[1] . ';padding:2px 10px;border-radius:9999px;font-size:0.75rem;font-weight:600;">'
        . htmlspecialchars($d[2], ENT_QUOTES, 'UTF-8') . '</span>';
}

function admin_paginationHtml(int $current, int $total, string $secao, array $extra = []): string {
    if ($total <= 1) return '';
    $paramStr = '';
    foreach ($extra as $k => $v) {
        $paramStr .= '&' . urlencode($k) . '=' . urlencode($v);
    }
    $pageParam = ['overview' => 'up', 'usuarios' => 'up', 'veiculos' => 'vp', 'propostas' => 'pp'];
    $pp = $pageParam[$secao] ?? 'p';

    $html = '<div class="table-pagination">';
    $html .= '<span class="pagination-info">Página ' . $current . ' de ' . $total . '</span>';
    $html .= '<div class="pagination-btns">';

    if ($current > 1) {
        $html .= '<a href="admin.php?secao=' . $secao . '&' . $pp . '=' . ($current - 1) . $paramStr . '" class="btn-page"><i class="fa-solid fa-chevron-left"></i></a>';
    }
    $start = max(1, $current - 2);
    $end   = min($total, $current + 2);
    for ($i = $start; $i <= $end; $i++) {
        $html .= '<a href="admin.php?secao=' . $secao . '&' . $pp . '=' . $i . $paramStr . '" class="btn-page ' . ($i === $current ? 'active' : '') . '">' . $i . '</a>';
    }
    if ($current < $total) {
        $html .= '<a href="admin.php?secao=' . $secao . '&' . $pp . '=' . ($current + 1) . $paramStr . '" class="btn-page"><i class="fa-solid fa-chevron-right"></i></a>';
    }
    $html .= '</div></div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>MotorGo Admin – <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/png" href="/imagens/logo_motorgo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        body { background: var(--color-bg); }
        .layout { display: flex; min-height: 100vh; }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #0d0d0d 0%, #1a0505 100%);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: var(--z-sidebar);
            transition: transform 0.3s ease;
        }
        .sidebar.collapsed { transform: translateX(calc(-1 * var(--sidebar-width))); }
        .sidebar-footer { padding: 1rem 0.75rem; border-top: 1px solid rgba(255,255,255,0.07); flex-shrink: 0; }
        .sidebar-logout {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.7rem 0.875rem; border-radius: var(--radius-md);
            color: rgba(255,255,255,0.45); font-size: 0.875rem; font-weight: 500;
            text-decoration: none; transition: all 0.18s; cursor: pointer;
        }
        .sidebar-logout:hover { background: rgba(220,38,38,0.15); color: #f87171; }
        .sidebar-logout i { width: 20px; text-align: center; }

        /* Layout main */
        .layout-main { flex: 1; margin-left: var(--sidebar-width); display: flex; flex-direction: column; min-height: 100vh; transition: margin-left 0.3s; }
        .layout-main.expanded { margin-left: 0; }

        /* Topbar */
        .topbar {
            height: var(--topbar-height); background: #fff; border-bottom: 1px solid var(--color-border);
            display: flex; align-items: center; padding: 0 1.5rem; gap: 1rem;
            position: sticky; top: 0; z-index: var(--z-topbar); box-shadow: var(--shadow-sm);
        }
        .topbar-hamburger { background: none; border: none; cursor: pointer; padding: 0.5rem; color: var(--color-text-muted); border-radius: var(--radius-md); font-size: 1.125rem; transition: var(--transition); }
        .topbar-hamburger:hover { background: var(--color-bg); color: var(--color-text); }
        .topbar-title { font-size: 1.125rem; font-weight: 700; color: var(--color-secondary); letter-spacing: -0.02em; }
        .topbar-spacer { flex: 1; }
        .admin-badge {
            display: flex; align-items: center; gap: 0.375rem;
            background: rgba(178,34,34,0.08); color: var(--color-primary);
            font-size: 0.75rem; font-weight: 700; padding: 0.3rem 0.75rem;
            border-radius: var(--radius-full); border: 1px solid rgba(178,34,34,0.15);
        }

        /* Content */
        .layout-content { flex: 1; padding: 2rem 1.5rem; }

        /* Section styles */
        .section-page-header { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; }
        .section-page-title { font-size: 1.5rem; font-weight: 800; color: var(--color-secondary); letter-spacing: -0.03em; display: flex; align-items: center; gap: 0.625rem; margin-bottom: 0.25rem; }
        .section-page-title i { color: var(--color-primary); font-size: 1.25rem; }
        .section-page-subtitle { font-size: 0.875rem; color: var(--color-text-muted); margin: 0; }

        /* Overview stats */
        .admin-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .admin-stat-card {
            background: #fff;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
        }
        .admin-stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .admin-stat-icon { width: 52px; height: 52px; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
        .admin-stat-value { display: block; font-size: 2rem; font-weight: 800; color: var(--color-secondary); letter-spacing: -0.04em; line-height: 1; }
        .admin-stat-label { font-size: 0.8125rem; color: var(--color-text-muted); font-weight: 500; }
        .admin-stat-sub { font-size: 0.8125rem; color: var(--color-text-muted); margin-top: 0.125rem; }

        /* Filter bar */
        .filter-bar { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
        .filter-search { display: flex; align-items: center; gap: 0.5rem; background: #fff; border: 1.5px solid var(--color-border); border-radius: var(--radius-md); padding: 0.625rem 1rem; flex: 1; min-width: 200px; }
        .filter-search i { color: var(--color-text-muted); font-size: 0.875rem; flex-shrink: 0; }
        .filter-search input { flex: 1; border: none; outline: none; font-size: 0.875rem; color: var(--color-text); background: transparent; font-family: inherit; }
        .filter-search input::placeholder { color: #9ca3af; }
        .filter-select { padding: 0.625rem 0.875rem; border: 1.5px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; color: var(--color-text); background: #fff; outline: none; cursor: pointer; transition: var(--transition); font-family: inherit; }
        .filter-select:focus { border-color: var(--color-primary); }
        .btn-filter-apply { padding: 0.625rem 1.25rem; background: var(--color-primary); color: #fff; border: none; border-radius: var(--radius-md); font-size: 0.875rem; font-weight: 700; cursor: pointer; transition: var(--transition); }
        .btn-filter-apply:hover { background: var(--color-primary-dark); }
        .btn-filter-clear { padding: 0.625rem 1rem; background: var(--color-bg); color: var(--color-text-muted); border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.875rem; font-weight: 600; text-decoration: none; transition: var(--transition); }
        .btn-filter-clear:hover { background: var(--color-border); color: var(--color-text); }

        /* Table card */
        .table-card { background: #fff; border: 1px solid var(--color-border); border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-sm); }
        .table-responsive { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { padding: 0.75rem 1.25rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.06em; background: var(--color-bg); border-bottom: 1px solid var(--color-border); white-space: nowrap; }
        .data-table td { padding: 0.875rem 1.25rem; font-size: 0.875rem; color: var(--color-text); border-bottom: 1px solid var(--color-border); vertical-align: middle; }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr:hover { background: #fafafa; }
        .table-empty { padding: 4rem 2rem; text-align: center; }
        .table-empty i { font-size: 2.5rem; color: #d1d5db; margin-bottom: 1rem; display: block; }
        .table-empty p { color: var(--color-text-muted); font-size: 0.9375rem; margin: 0; }
        .table-pagination { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-top: 1px solid var(--color-border); flex-wrap: wrap; gap: 0.5rem; }
        .pagination-info { font-size: 0.8125rem; color: var(--color-text-muted); }
        .pagination-btns { display: flex; gap: 0.25rem; }
        .btn-page { min-width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.8125rem; font-weight: 600; color: var(--color-text); background: #fff; text-decoration: none; transition: var(--transition); padding: 0 0.5rem; }
        .btn-page:hover { background: var(--color-bg); color: var(--color-text); }
        .btn-page.active { background: var(--color-primary); color: #fff; border-color: var(--color-primary); }

        /* Action buttons */
        .btn-table-action { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border: none; border-radius: var(--radius-md); cursor: pointer; font-size: 0.8125rem; transition: var(--transition); }
        .btn-activate   { background: rgba(22,163,74,0.1); color: #15803d; }
        .btn-activate:hover { background: #16a34a; color: #fff; }
        .btn-deactivate { background: rgba(217,119,6,0.1); color: #b45309; }
        .btn-deactivate:hover { background: #d97706; color: #fff; }
        .btn-danger-sm  { background: rgba(220,38,38,0.1); color: var(--color-danger); }
        .btn-danger-sm:hover { background: var(--color-danger); color: #fff; }
        .btn-view-sm    { background: rgba(37,99,235,0.1); color: #1d4ed8; }
        .btn-view-sm:hover { background: #2563eb; color: #fff; }

        /* Sidebar overlay */
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: calc(var(--z-sidebar) - 1); }
        .sidebar-overlay.show { display: block; }

        @media (max-width: 900px) {
            .sidebar { transform: translateX(calc(-1 * var(--sidebar-width))); }
            .sidebar.mobile-open { transform: translateX(0); }
            .layout-main { margin-left: 0 !important; }
        }
        @media (max-width: 640px) {
            .layout-content { padding: 1.25rem 1rem; }
            .admin-stats-grid { grid-template-columns: 1fr 1fr; }
        }

        /* Alert */
        .alert-box { display: none; padding: 0.875rem 1rem; border-radius: var(--radius-md); font-size: 0.875rem; font-weight: 500; margin-bottom: 1.25rem; align-items: flex-start; gap: 0.625rem; }
        .alert-box.show, .alert-box[style*="flex"] { display: flex !important; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo-area" style="background:rgba(178,34,34,0.15);">
            <img src="/imagens/logo_motorgo.png" alt="MotorGo" class="sidebar-logo-img" onerror="this.style.display='none'">
            <span class="sidebar-logo-wordmark">Motor<em>Go</em> <span style="font-size:0.625rem;background:rgba(255,255,255,0.15);padding:1px 6px;border-radius:4px;font-weight:600;letter-spacing:0.08em;">ADMIN</span></span>
        </div>

        <div class="sidebar-body">
            <div class="sidebar-section">
                <span class="sidebar-section-label">Administração</span>
            </div>
            <ul class="sidebar-nav-list">
                <li>
                    <a class="sidebar-nav-link <?= $activeSection === 'overview' ? 'active' : '' ?>" href="admin.php?secao=overview">
                        <i class="fa-solid fa-chart-pie"></i> <span>Visão Geral</span>
                    </a>
                </li>
                <li>
                    <a class="sidebar-nav-link <?= $activeSection === 'usuarios' ? 'active' : '' ?>" href="admin.php?secao=usuarios">
                        <i class="fa-solid fa-users"></i> <span>Usuários</span>
                        <?php if ($statUsuariosPendentes > 0): ?>
                        <span class="sidebar-badge"><?= (int)$statUsuariosPendentes ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a class="sidebar-nav-link <?= $activeSection === 'veiculos' ? 'active' : '' ?>" href="admin.php?secao=veiculos">
                        <i class="fa-solid fa-car-side"></i> <span>Veículos</span>
                    </a>
                </li>
                <li>
                    <a class="sidebar-nav-link <?= $activeSection === 'propostas' ? 'active' : '' ?>" href="admin.php?secao=propostas">
                        <i class="fa-solid fa-file-invoice-dollar"></i> <span>Propostas</span>
                        <?php if ($statPendentes > 0): ?>
                        <span class="sidebar-badge"><?= (int)$statPendentes ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>

            <div class="sidebar-section" style="margin-top:1rem;">
                <span class="sidebar-section-label">Navegação</span>
            </div>
            <ul class="sidebar-nav-list">
                <li>
                    <a class="sidebar-nav-link" href="painel.php">
                        <i class="fa-solid fa-gauge-high"></i> <span>Meu Painel</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-footer">
            <a href="actions/logout.php" class="sidebar-logout" onclick="return confirm('Sair?')">
                <i class="fa-solid fa-right-from-bracket"></i> <span>Sair</span>
            </a>
        </div>
    </aside>

    <!-- Main -->
    <div class="layout-main" id="layoutMain">
        <header class="topbar">
            <button class="topbar-hamburger" id="hamburgerBtn"><i class="fa-solid fa-bars"></i></button>
            <h1 class="topbar-title"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            <div class="topbar-spacer"></div>
            <div class="admin-badge">
                <i class="fa-solid fa-shield-halved"></i>
                <?= htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        </header>

        <main class="layout-content">

            <!-- ── OVERVIEW ──────────────────────────────── -->
            <?php if ($activeSection === 'overview'): ?>
            <div class="section-page-header">
                <div>
                    <h2 class="section-page-title"><i class="fa-solid fa-chart-pie"></i> Visão Geral</h2>
                    <p class="section-page-subtitle">Estatísticas gerais da plataforma MotorGo.</p>
                </div>
            </div>

            <div class="admin-stats-grid">
                <a href="admin.php?secao=usuarios" class="admin-stat-card" style="text-decoration:none;">
                    <div class="admin-stat-icon" style="background:rgba(37,99,235,0.1);">
                        <i class="fa-solid fa-users" style="color:#2563eb;"></i>
                    </div>
                    <div>
                        <span class="admin-stat-value"><?= (int)$statUsuarios ?></span>
                        <span class="admin-stat-label">Usuários</span>
                        <?php if ($statUsuariosPendentes > 0): ?>
                        <span class="admin-stat-sub" style="color:var(--color-warning);">
                            <i class="fa-solid fa-clock" style="font-size:0.7rem;"></i> <?= (int)$statUsuariosPendentes ?> pendentes
                        </span>
                        <?php endif; ?>
                    </div>
                </a>
                <a href="admin.php?secao=veiculos" class="admin-stat-card">
                    <div class="admin-stat-icon" style="background:rgba(22,163,74,0.1);">
                        <i class="fa-solid fa-car" style="color:#16a34a;"></i>
                    </div>
                    <div>
                        <span class="admin-stat-value"><?= (int)$statVeiculos ?></span>
                        <span class="admin-stat-label">Veículos</span>
                    </div>
                </a>
                <a href="admin.php?secao=propostas" class="admin-stat-card">
                    <div class="admin-stat-icon" style="background:rgba(217,119,6,0.1);">
                        <i class="fa-solid fa-file-invoice-dollar" style="color:#d97706;"></i>
                    </div>
                    <div>
                        <span class="admin-stat-value"><?= (int)$statPropostas ?></span>
                        <span class="admin-stat-label">Propostas</span>
                        <?php if ($statPendentes > 0): ?>
                        <span class="admin-stat-sub" style="color:var(--color-warning);">
                            <i class="fa-solid fa-clock" style="font-size:0.7rem;"></i> <?= (int)$statPendentes ?> pendentes
                        </span>
                        <?php endif; ?>
                    </div>
                </a>
                <div class="admin-stat-card" style="cursor:default;">
                    <div class="admin-stat-icon" style="background:rgba(178,34,34,0.1);">
                        <i class="fa-solid fa-circle-check" style="color:var(--color-primary);"></i>
                    </div>
                    <div>
                        <span class="admin-stat-value"><?= (int)$statPendentes ?></span>
                        <span class="admin-stat-label">Prop. Pendentes</span>
                    </div>
                </div>
            </div>

            <!-- Recent users -->
            <h3 style="font-size:1.125rem;margin-bottom:1rem;">Usuários Recentes</h3>
            <div class="table-card" style="margin-bottom:2rem;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Cadastro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($usuarios)): ?>
                            <tr><td colspan="6" style="text-align:center;color:var(--color-text-muted);padding:2rem;">Nenhum usuário encontrado.</td></tr>
                            <?php else: ?>
                            <?php foreach (array_slice($usuarios, 0, 8) as $u): ?>
                            <tr>
                                <td style="color:var(--color-text-muted);font-size:0.8125rem;">#<?= (int)$u['id'] ?></td>
                                <td style="font-weight:600;"><?= htmlspecialchars($u['nome'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= admin_badge($u['tipo']) ?></td>
                                <td><?= admin_badge($u['status_confirmacao']) ?></td>
                                <td style="font-size:0.8125rem;color:var(--color-text-muted);">
                                    <?= !empty($u['data_cadastro']) ? date('d/m/Y', strtotime($u['data_cadastro'])) : '-' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div style="padding:1rem 1.25rem;border-top:1px solid var(--color-border);">
                    <a href="admin.php?secao=usuarios" style="font-size:0.8125rem;font-weight:600;color:var(--color-primary);">Ver todos os usuários →</a>
                </div>
            </div>

            <!-- ── USUARIOS ───────────────────────────────── -->
            <?php elseif ($activeSection === 'usuarios'): ?>
            <div class="section-page-header">
                <div>
                    <h2 class="section-page-title"><i class="fa-solid fa-users"></i> Usuários</h2>
                    <p class="section-page-subtitle"><?= (int)$uTotal ?> usuário<?= $uTotal !== 1 ? 's' : '' ?> cadastrado<?= $uTotal !== 1 ? 's' : '' ?></p>
                </div>
            </div>

            <form method="get" class="filter-bar">
                <input type="hidden" name="secao" value="usuarios">
                <div class="filter-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="us" placeholder="Buscar por nome, e-mail ou CPF…" value="<?= htmlspecialchars($uSearch, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <select name="utipo" class="filter-select">
                    <option value="">Todos os tipos</option>
                    <option value="vendedor" <?= $uFilterTipo === 'vendedor' ? 'selected' : '' ?>>Vendedor</option>
                    <option value="investidor" <?= $uFilterTipo === 'investidor' ? 'selected' : '' ?>>Investidor</option>
                    <option value="administrador" <?= $uFilterTipo === 'administrador' ? 'selected' : '' ?>>Administrador</option>
                </select>
                <select name="ustatus" class="filter-select">
                    <option value="">Todos os status</option>
                    <option value="confirmado" <?= $uFilterStatus === 'confirmado' ? 'selected' : '' ?>>Confirmado</option>
                    <option value="pendente" <?= $uFilterStatus === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                </select>
                <button type="submit" class="btn-filter-apply">Filtrar</button>
                <?php if ($uSearch !== '' || $uFilterTipo !== '' || $uFilterStatus !== ''): ?>
                <a href="admin.php?secao=usuarios" class="btn-filter-clear">Limpar</a>
                <?php endif; ?>
            </form>

            <div class="table-card">
                <?php if (empty($usuarios)): ?>
                <div class="table-empty">
                    <i class="fa-solid fa-users"></i>
                    <p>Nenhum usuário encontrado.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Celular</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Cadastro</th>
                                <th>Veículos</th>
                                <th>Propostas</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td style="color:var(--color-text-muted);font-size:0.8125rem;">#<?= (int)$u['id'] ?></td>
                                <td>
                                    <div style="font-weight:600;"><?= htmlspecialchars($u['nome'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div style="font-size:0.8rem;color:var(--color-text-muted);"><?= admin_badge($u['status_cadastro']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($u['celular'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= admin_badge($u['tipo']) ?></td>
                                <td><?= admin_badge($u['status_confirmacao']) ?></td>
                                <td style="font-size:0.8125rem;color:var(--color-text-muted);">
                                    <?= !empty($u['data_cadastro']) ? date('d/m/Y', strtotime($u['data_cadastro'])) : '-' ?>
                                </td>
                                <td style="text-align:center;"><?= (int)$u['total_veiculos'] ?></td>
                                <td style="text-align:center;"><?= (int)$u['total_propostas'] ?></td>
                                <td>
                                    <div style="display:flex;gap:0.375rem;flex-wrap:nowrap;">
                                        <?php if ($u['status_confirmacao'] !== 'confirmado'): ?>
                                        <button class="btn-table-action btn-activate" title="Ativar conta"
                                                onclick="toggleUser(<?= (int)$u['id'] ?>, 'ativar', '<?= htmlspecialchars(addslashes($u['nome']), ENT_QUOTES, 'UTF-8') ?>')">
                                            <i class="fa-solid fa-check"></i>
                                        </button>
                                        <?php else: ?>
                                        <button class="btn-table-action btn-deactivate" title="Suspender conta"
                                                onclick="toggleUser(<?= (int)$u['id'] ?>, 'suspender', '<?= htmlspecialchars(addslashes($u['nome']), ENT_QUOTES, 'UTF-8') ?>')">
                                            <i class="fa-solid fa-ban"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn-table-action btn-danger-sm" title="Remover usuário"
                                                onclick="removerUsuario(<?= (int)$u['id'] ?>, '<?= htmlspecialchars(addslashes($u['nome']), ENT_QUOTES, 'UTF-8') ?>')">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?= admin_paginationHtml($uPage, $uTotalPages, 'usuarios', ['us' => $uSearch, 'utipo' => $uFilterTipo, 'ustatus' => $uFilterStatus]) ?>
                <?php endif; ?>
            </div>

            <!-- ── VEICULOS ────────────────────────────────── -->
            <?php elseif ($activeSection === 'veiculos'): ?>
            <div class="section-page-header">
                <div>
                    <h2 class="section-page-title"><i class="fa-solid fa-car-side"></i> Veículos</h2>
                    <p class="section-page-subtitle"><?= (int)$vTotal ?> veículo<?= $vTotal !== 1 ? 's' : '' ?> no sistema</p>
                </div>
            </div>

            <form method="get" class="filter-bar">
                <input type="hidden" name="secao" value="veiculos">
                <div class="filter-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="vs" placeholder="Buscar placa, marca, modelo, vendedor…" value="<?= htmlspecialchars($vSearch, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <select name="vstatus" class="filter-select">
                    <option value="">Todos os status</option>
                    <option value="disponivel" <?= $vFilterStatus === 'disponivel' ? 'selected' : '' ?>>Disponível</option>
                    <option value="em_negociacao" <?= $vFilterStatus === 'em_negociacao' ? 'selected' : '' ?>>Em Negociação</option>
                    <option value="vendido" <?= $vFilterStatus === 'vendido' ? 'selected' : '' ?>>Vendido</option>
                    <option value="pausado" <?= $vFilterStatus === 'pausado' ? 'selected' : '' ?>>Pausado</option>
                    <option value="pendente" <?= $vFilterStatus === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                </select>
                <button type="submit" class="btn-filter-apply">Filtrar</button>
                <?php if ($vSearch !== '' || $vFilterStatus !== ''): ?>
                <a href="admin.php?secao=veiculos" class="btn-filter-clear">Limpar</a>
                <?php endif; ?>
            </form>

            <div class="table-card">
                <?php if (empty($veiculos)): ?>
                <div class="table-empty">
                    <i class="fa-solid fa-car"></i>
                    <p>Nenhum veículo encontrado.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Vendedor</th>
                                <th>Placa</th>
                                <th>Veículo</th>
                                <th>Ano</th>
                                <th>Km</th>
                                <th>Preço</th>
                                <th>Status</th>
                                <th>Cadastro</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($veiculos as $v): ?>
                            <tr>
                                <td style="color:var(--color-text-muted);font-size:0.8125rem;">#<?= (int)$v['id'] ?></td>
                                <td><?= htmlspecialchars($v['vendedor_nome'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span style="font-family:monospace;font-weight:700;background:#f3f4f6;padding:2px 8px;border-radius:6px;font-size:0.8125rem;letter-spacing:0.08em;">
                                        <?= htmlspecialchars(strtoupper($v['placa'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td style="font-weight:600;"><?= htmlspecialchars($v['marca'] . ' ' . $v['modelo'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($v['ano_fabrica'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= $v['quilometragem'] !== null ? number_format((int)$v['quilometragem'], 0, ',', '.') . ' km' : '-' ?></td>
                                <td style="font-weight:600;"><?= formatMoney((float)($v['preco'] ?? 0)) ?></td>
                                <td><?= admin_badge($v['status'] ?? 'pendente') ?></td>
                                <td style="font-size:0.8125rem;color:var(--color-text-muted);">
                                    <?= !empty($v['data_cadastro']) ? date('d/m/Y', strtotime($v['data_cadastro'])) : '-' ?>
                                </td>
                                <td>
                                    <button class="btn-table-action btn-danger-sm" title="Remover"
                                            onclick="removerVeiculo(<?= (int)$v['id'] ?>, '<?= htmlspecialchars(addslashes($v['marca'] . ' ' . $v['modelo']), ENT_QUOTES, 'UTF-8') ?>')">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?= admin_paginationHtml($vPage, $vTotalPages, 'veiculos', ['vs' => $vSearch, 'vstatus' => $vFilterStatus]) ?>
                <?php endif; ?>
            </div>

            <!-- ── PROPOSTAS ──────────────────────────────── -->
            <?php elseif ($activeSection === 'propostas'): ?>
            <div class="section-page-header">
                <div>
                    <h2 class="section-page-title"><i class="fa-solid fa-file-invoice-dollar"></i> Propostas</h2>
                    <p class="section-page-subtitle"><?= (int)$pTotal ?> proposta<?= $pTotal !== 1 ? 's' : '' ?> no sistema</p>
                </div>
            </div>

            <form method="get" class="filter-bar">
                <input type="hidden" name="secao" value="propostas">
                <select name="pstatus" class="filter-select">
                    <option value="">Todos os status</option>
                    <option value="aguardando" <?= $pFilterStatus === 'aguardando' ? 'selected' : '' ?>>Aguardando</option>
                    <option value="aceita" <?= $pFilterStatus === 'aceita' ? 'selected' : '' ?>>Aceita</option>
                    <option value="recusada" <?= $pFilterStatus === 'recusada' ? 'selected' : '' ?>>Recusada</option>
                    <option value="contraproposta" <?= $pFilterStatus === 'contraproposta' ? 'selected' : '' ?>>Contraproposta</option>
                    <option value="cancelada" <?= $pFilterStatus === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                    <option value="finalizada" <?= $pFilterStatus === 'finalizada' ? 'selected' : '' ?>>Finalizada</option>
                </select>
                <button type="submit" class="btn-filter-apply">Filtrar</button>
                <?php if ($pFilterStatus !== ''): ?>
                <a href="admin.php?secao=propostas" class="btn-filter-clear">Limpar</a>
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
                                <th>Investidor</th>
                                <th>Vendedor</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Data</th>
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
                                    <div style="font-weight:500;font-size:0.875rem;"><?= htmlspecialchars($p['investidor_nome'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div style="font-size:0.8rem;color:var(--color-text-muted);"><?= htmlspecialchars($p['investidor_email'], ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td><?= htmlspecialchars($p['vendedor_nome'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td style="font-weight:700;"><?= formatMoney((float)$p['valor']) ?></td>
                                <td><?= admin_badge($p['status']) ?></td>
                                <td style="font-size:0.8125rem;color:var(--color-text-muted);">
                                    <?= !empty($p['data_proposta']) ? date('d/m/Y H:i', strtotime($p['data_proposta'])) : '-' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?= admin_paginationHtml($pPage, $pTotalPages, 'propostas', ['pstatus' => $pFilterStatus]) ?>
                <?php endif; ?>
            </div>

            <?php endif; ?>

        </main>
    </div>
</div>

<script>
var CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

(function () {
    var sidebar    = document.getElementById('sidebar');
    var layoutMain = document.getElementById('layoutMain');
    var overlay    = document.getElementById('sidebarOverlay');
    var hamburger  = document.getElementById('hamburgerBtn');
    var isMobile   = window.innerWidth <= 900;

    function toggleSidebar() {
        if (isMobile) {
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('show');
        } else {
            sidebar.classList.toggle('collapsed');
            layoutMain.classList.toggle('expanded');
        }
    }

    hamburger.addEventListener('click', toggleSidebar);
    overlay.addEventListener('click', function () {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('show');
    });
    window.addEventListener('resize', function () {
        isMobile = window.innerWidth <= 900;
        if (!isMobile) {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('show');
        }
    });
}());

function adminAction(url, data, onSuccess) {
    var fd = new FormData();
    fd.append('csrf_token', CSRF_TOKEN);
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    fetch(url, { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) { if (onSuccess) onSuccess(d); else window.location.reload(); }
            else { alert(d.message || 'Erro ao processar ação.'); }
        })
        .catch(function () { alert('Erro de conexão.'); });
}

function toggleUser(id, acao, nome) {
    var msg = acao === 'ativar' ? 'Ativar conta de "' + nome + '"?' : 'Suspender conta de "' + nome + '"?';
    if (!confirm(msg)) return;
    adminAction('actions/admin_usuario.php', { usuario_id: id, acao: acao });
}

function removerUsuario(id, nome) {
    if (!confirm('Remover permanentemente o usuário "' + nome + '"? Esta ação não pode ser desfeita.')) return;
    adminAction('actions/admin_usuario.php', { usuario_id: id, acao: 'remover' });
}

function removerVeiculo(id, nome) {
    if (!confirm('Remover o veículo "' + nome + '"? Esta ação não pode ser desfeita.')) return;
    adminAction('actions/remover_veiculo.php', { veiculo_id: id });
}
</script>
</body>
</html>
