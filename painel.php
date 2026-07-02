<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$user = getCurrentUser();
$tipo = $user['tipo'];
$userId = (int) $user['id'];

$csrfToken = generateCsrfToken();

// ── Active section from query param or hash fallback ─────────
$activeSection = trim($_GET['secao'] ?? '');
$allowedSections = ['painel','veiculos','oferta','propostas','dados','ajuda'];
if (!in_array($activeSection, $allowedSections, true)) {
    $activeSection = 'painel';
}

// ── Section access control ───────────────────────────────────
// Oferta section is only for investidor and administrador
if ($activeSection === 'oferta' && $tipo === 'vendedor') {
    $activeSection = 'painel';
}

// ── Sidebar badge counts ─────────────────────────────────────
$badgeVeiculos    = 0;
$badgePropostas   = 0;

if ($tipo === 'vendedor') {
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM propostas p
        INNER JOIN veiculos v ON v.id = p.veiculo_id
        WHERE v.usuario_id = ? AND p.status IN ('aguardando', 'aguardando_vendedor', 'recebida')
          AND (p.proposta_origem_id IS NULL OR p.proposta_origem_id = 0 OR p.proposta_origem_id = p.id)
    ");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($badgePropostas);
        $stmt->fetch();
        $stmt->close();
    }

    $stmt = $conn->prepare("SELECT COUNT(*) FROM veiculos WHERE usuario_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($badgeVeiculos);
        $stmt->fetch();
        $stmt->close();
    }

} elseif ($tipo === 'investidor') {
    // Investors may also own vehicles — count proposals from both sides
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM propostas p
        INNER JOIN veiculos v ON v.id = p.veiculo_id
        WHERE (p.usuario_id = ? OR v.usuario_id = ?)
          AND p.status IN ('aguardando', 'aguardando_vendedor', 'aguardando_comprador', 'contraoferta', 'recebida')
          AND (p.proposta_origem_id IS NULL OR p.proposta_origem_id = 0 OR p.proposta_origem_id = p.id)
    ");
    if ($stmt) {
        $stmt->bind_param('ii', $userId, $userId);
        $stmt->execute();
        $stmt->bind_result($badgePropostas);
        $stmt->fetch();
        $stmt->close();
    }

} elseif ($tipo === 'administrador') {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM propostas WHERE status IN ('aguardando', 'aguardando_vendedor', 'aguardando_comprador')");
    if ($stmt) {
        $stmt->execute();
        $stmt->bind_result($badgePropostas);
        $stmt->fetch();
        $stmt->close();
    }

    $stmt = $conn->prepare("SELECT COUNT(*) FROM veiculos WHERE status = 'pendente'");
    if ($stmt) {
        $stmt->execute();
        $stmt->bind_result($badgeVeiculos);
        $stmt->fetch();
        $stmt->close();
    }
}

// ── Page title per section ───────────────────────────────────
$sectionTitles = [
    'painel'    => 'Painel',
    'veiculos'  => $tipo === 'administrador' ? 'Todos os Veículos' : 'Meus Veículos',
    'oferta'    => 'Oferta de Veículos',
    'propostas' => $tipo === 'administrador' ? 'Todas as Propostas' : 'Propostas',
    'dados'     => 'Meus Dados',
    'ajuda'     => 'Ajuda & Suporte',
];
$pageTitle = $sectionTitles[$activeSection] ?? 'Painel';

// ── Flash message ────────────────────────────────────────────
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>MotorGo – <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="shortcut icon" href="/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        /* ── Layout ──────────────────────────────────────── */
        body { background: var(--color-bg); overflow-x: hidden; }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* ── Sidebar ─────────────────────────────────────── */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #111111 0%, #1a1a1a 100%);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: var(--z-sidebar);
            transition: transform 0.3s ease;
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
        }
        .sidebar.collapsed { transform: translateX(calc(-1 * var(--sidebar-width))); }
        .sidebar-footer {
            padding: 1rem 0.75rem;
            border-top: 1px solid rgba(255,255,255,0.07);
            flex-shrink: 0;
        }
        .sidebar-logout {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 0.875rem;
            border-radius: var(--radius-md);
            color: rgba(255,255,255,0.45);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.18s;
            cursor: pointer;
        }
        .sidebar-logout:hover { background: rgba(220,38,38,0.15); color: #f87171; }
        .sidebar-logout i { font-size: 1rem; width: 20px; text-align: center; }

        /* ── Main layout ─────────────────────────────────── */
        .layout-main {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        .layout-main.expanded { margin-left: 0; }

        /* ── Topbar ──────────────────────────────────────── */
        .topbar {
            height: var(--topbar-height);
            background: #fff;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            gap: 1rem;
            position: sticky;
            top: 0;
            z-index: var(--z-topbar);
            box-shadow: var(--shadow-sm);
        }
        .topbar-hamburger {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            color: var(--color-text-muted);
            border-radius: var(--radius-md);
            font-size: 1.125rem;
            transition: var(--transition);
        }
        .topbar-hamburger:hover { background: var(--color-bg); color: var(--color-text); }
        .topbar-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--color-secondary);
            letter-spacing: -0.02em;
        }
        .topbar-spacer { flex: 1; }
        .topbar-user {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
        }
        .topbar-avatar {
            width: 38px;
            height: 38px;
            background: var(--color-primary);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.9375rem;
            cursor: pointer;
            flex-shrink: 0;
        }
        .topbar-user-name {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--color-text);
            max-width: 160px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .topbar-user-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 0.5rem);
            right: 0;
            background: #fff;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            min-width: 200px;
            z-index: var(--z-modal);
            overflow: hidden;
        }
        .topbar-user-dropdown.open { display: block; }
        .dropdown-header {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid var(--color-border);
            background: var(--color-bg);
        }
        .dropdown-header strong { display: block; font-size: 0.875rem; margin-bottom: 0.1rem; }
        .dropdown-header span { font-size: 0.8rem; color: var(--color-text-muted); }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            color: var(--color-text);
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
        }
        .dropdown-item:hover { background: var(--color-bg); }
        .dropdown-item i { width: 16px; text-align: center; color: var(--color-text-muted); }
        .dropdown-item.dropdown-danger { color: var(--color-danger); }
        .dropdown-item.dropdown-danger i { color: var(--color-danger); }
        .dropdown-item.dropdown-danger:hover { background: #fef2f2; }
        .dropdown-divider { height: 1px; background: var(--color-border); margin: 0.25rem 0; }

        /* ── Content area ────────────────────────────────── */
        .layout-content {
            flex: 1;
            padding: 2rem 1.5rem;
            max-width: 1200px;
        }

        /* ── Section styles ──────────────────────────────── */
        .section-page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .section-page-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--color-secondary);
            letter-spacing: -0.03em;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            margin-bottom: 0.25rem;
        }
        .section-page-title i { color: var(--color-primary); font-size: 1.25rem; }
        .section-page-subtitle { font-size: 0.875rem; color: var(--color-text-muted); margin: 0; }

        /* ── Shared form elements ────────────────────────── */
        .form-group { margin-bottom: 1.125rem; }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.45rem;
        }
        .form-label .req { color: var(--color-primary); }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-md);
            font-size: 0.9375rem;
            color: var(--color-text);
            background: #fff;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
            font-family: inherit;
        }
        .form-control:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(178,34,34,0.1); }
        .form-control::placeholder { color: #9ca3af; }
        .form-control.is-invalid { border-color: var(--color-danger) !important; box-shadow: 0 0 0 3px rgba(220,38,38,0.1) !important; }
        .invalid-feedback { display: none; font-size: 0.8125rem; color: var(--color-danger); margin-top: 0.35rem; }
        .form-control.is-invalid ~ .invalid-feedback { display: block; }

        /* ── Table card ──────────────────────────────────── */
        .table-card {
            background: #fff;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        .table-responsive { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th {
            padding: 0.75rem 1.25rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--color-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            background: var(--color-bg);
            border-bottom: 1px solid var(--color-border);
            white-space: nowrap;
        }
        .data-table td {
            padding: 0.875rem 1.25rem;
            font-size: 0.875rem;
            color: var(--color-text);
            border-bottom: 1px solid var(--color-border);
            vertical-align: middle;
        }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr:hover { background: #fafafa; }
        .table-empty {
            padding: 4rem 2rem;
            text-align: center;
        }
        .table-empty i { font-size: 2.5rem; color: #d1d5db; margin-bottom: 1rem; display: block; }
        .table-empty p { color: var(--color-text-muted); font-size: 0.9375rem; margin-bottom: 1.5rem; }
        .table-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.25rem;
            border-top: 1px solid var(--color-border);
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .pagination-info { font-size: 0.8125rem; color: var(--color-text-muted); }
        .pagination-btns { display: flex; gap: 0.25rem; }
        .btn-page {
            min-width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--color-text);
            background: #fff;
            text-decoration: none;
            transition: var(--transition);
            padding: 0 0.5rem;
        }
        .btn-page:hover { background: var(--color-bg); color: var(--color-text); }
        .btn-page.active { background: var(--color-primary); color: #fff; border-color: var(--color-primary); }

        /* ── Filter bar ──────────────────────────────────── */
        .filter-bar {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1.25rem;
        }
        .filter-search {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #fff;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 0.625rem 1rem;
            flex: 1;
            min-width: 200px;
        }
        .filter-search i { color: var(--color-text-muted); font-size: 0.875rem; flex-shrink: 0; }
        .filter-search input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 0.875rem;
            color: var(--color-text);
            background: transparent;
            font-family: inherit;
        }
        .filter-search input::placeholder { color: #9ca3af; }
        .filter-select {
            padding: 0.625rem 0.875rem;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            color: var(--color-text);
            background: #fff;
            outline: none;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
        }
        .filter-select:focus { border-color: var(--color-primary); }
        .btn-filter-apply {
            padding: 0.625rem 1.25rem;
            background: var(--color-primary);
            color: #fff;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-filter-apply:hover { background: var(--color-primary-dark); }
        .btn-filter-clear {
            padding: 0.625rem 1rem;
            background: var(--color-bg);
            color: var(--color-text-muted);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
        }
        .btn-filter-clear:hover { background: var(--color-border); color: var(--color-text); }

        /* ── Action buttons ──────────────────────────────── */
        .btn-action-primary {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            background: var(--color-primary);
            color: #fff;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-action-primary:hover { background: var(--color-primary-dark); }
        .btn-table-action {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 0.8125rem;
            transition: var(--transition);
        }
        .btn-edit { background: rgba(37,99,235,0.1); color: #1d4ed8; }
        .btn-edit:hover { background: #2563eb; color: #fff; }
        .btn-danger { background: rgba(220,38,38,0.1); color: var(--color-danger); }
        .btn-danger:hover { background: var(--color-danger); color: #fff; }
        .btn-empty-action {
            padding: 0.625rem 1.5rem;
            background: var(--color-primary);
            color: #fff;
            border: none;
            border-radius: var(--radius-full);
            font-weight: 700;
            font-size: 0.875rem;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-empty-action:hover { background: var(--color-primary-dark); }

        /* ── Modal ───────────────────────────────────────── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: var(--z-modal);
            padding: 1rem;
            backdrop-filter: blur(4px);
            opacity: 1;
            visibility: visible;
        }
        .modal-box {
            background: #fff;
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 480px;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }
        .modal-box.modal-lg { max-width: 680px; }
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--color-border);
            flex-shrink: 0;
        }
        .modal-header h3 { font-size: 1.125rem; margin: 0; }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.375rem;
            color: var(--color-text-muted);
            cursor: pointer;
            padding: 0.25rem;
            line-height: 1;
            transition: var(--transition);
        }
        .modal-close:hover { color: var(--color-text); }
        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            flex: 1;
        }
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
            flex-shrink: 0;
        }
        .btn-modal-cancel {
            padding: 0.625rem 1.25rem;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-md);
            background: #fff;
            color: var(--color-text);
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-modal-cancel:hover { background: var(--color-bg); }
        .btn-modal-submit {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.5rem;
            background: var(--color-primary);
            color: #fff;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-modal-submit:hover:not(:disabled) { background: var(--color-primary-dark); }
        .btn-modal-submit:disabled { opacity: 0.65; cursor: not-allowed; }
        .btn-modal-submit .spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.35);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }
        .btn-modal-submit.loading .spinner { display: block; }
        .btn-modal-submit.loading .btn-text { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .modal-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        /* ── Alert boxes ─────────────────────────────────── */
        .alert-box {
            display: none;
            padding: 0.875rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 1.25rem;
            align-items: flex-start;
            gap: 0.625rem;
        }
        .alert-box.show, .alert-box[style*="flex"] { display: flex !important; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }

        /* ── Toast notification ──────────────────────────── */
        .toast-container {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            z-index: var(--z-toast);
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .toast {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.25rem;
            background: #fff;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            font-size: 0.875rem;
            font-weight: 500;
            min-width: 280px;
            max-width: 380px;
            animation: slideIn 0.3s ease;
        }
        .toast-success { border-left: 4px solid var(--color-success); }
        .toast-error   { border-left: 4px solid var(--color-danger); }
        .toast-warning { border-left: 4px solid var(--color-warning); }
        @keyframes slideIn { from { transform: translateX(100%); opacity:0; } to { transform: translateX(0); opacity:1; } }

        /* ── Mobile overlay ──────────────────────────────── */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: calc(var(--z-sidebar) - 1);
        }
        .sidebar-overlay.show { display: block; }

        /* ── Responsive ──────────────────────────────────── */
        @media (max-width: 900px) {
            .sidebar { transform: translateX(calc(-1 * var(--sidebar-width))); }
            .sidebar.mobile-open { transform: translateX(0); }
            .layout-main { margin-left: 0 !important; }
            .topbar-user-name { display: none; }
            .modal-form-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .layout-content { padding: 1.25rem 1rem; }
            .section-page-header { flex-direction: column; }
        }
    </style>
</head>
<body>

<!-- Sidebar overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="layout">
    <!-- ── Sidebar ──────────────────────────────────────── -->
    <aside class="sidebar" id="sidebar">
        <!-- Logo -->
        <div class="sidebar-logo-area">
            <img src="/imagens/logo_motorgo_blk.png" alt="MotorGo" class="sidebar-logo-img"
                 onerror="this.style.display='none'">
        </div>

        <!-- User info -->
        <div style="padding:1.25rem 1.25rem 0.75rem;border-bottom:1px solid rgba(255,255,255,0.07);">
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <div style="width:40px;height:40px;background:var(--color-primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff;font-size:1rem;flex-shrink:0;">
                    <?= strtoupper(mb_substr($user['nome'], 0, 1)) ?>
                </div>
                <div style="min-width:0;">
                    <div style="font-weight:700;color:#fff;font-size:0.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <?= htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div style="font-size:0.75rem;color:rgba(255,255,255,0.4);text-transform:capitalize;">
                        <?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="sidebar-body">
            <div class="sidebar-section">
                <span class="sidebar-section-label">Menu</span>
            </div>
            <ul class="sidebar-nav-list">
                <li>
                    <a class="sidebar-nav-link <?= $activeSection === 'painel' ? 'active' : '' ?>"
                       href="painel.php?secao=painel" data-section="painel">
                        <i class="fa-solid fa-gauge-high"></i>
                        <span>Painel</span>
                    </a>
                </li>

                <?php if ($tipo === 'vendedor' || $tipo === 'investidor' || $tipo === 'administrador'): ?>
                <li>
                    <a class="sidebar-nav-link <?= $activeSection === 'veiculos' ? 'active' : '' ?>"
                       href="painel.php?secao=veiculos" data-section="veiculos">
                        <i class="fa-solid fa-car-side"></i>
                        <span><?= $tipo === 'administrador' ? 'Todos os Veículos' : 'Meus Veículos' ?></span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($tipo === 'investidor' || $tipo === 'administrador'): ?>
                <li>
                    <a class="sidebar-nav-link <?= $activeSection === 'oferta' ? 'active' : '' ?>"
                       href="painel.php?secao=oferta" data-section="oferta">
                        <i class="fa-solid fa-car-burst"></i>
                        <span>Oferta de Veículos</span>
                    </a>
                </li>
                <?php endif; ?>

                <li>
                    <a class="sidebar-nav-link <?= $activeSection === 'propostas' ? 'active' : '' ?>"
                       href="painel.php?secao=propostas" data-section="propostas">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                        <span>
                            <?= $tipo === 'administrador' ? 'Todas as Propostas' : 'Propostas' ?>
                        </span>
                    </a>
                </li>

                <?php if ($tipo === 'administrador'): ?>
                <li>
                    <a class="sidebar-nav-link"
                       href="admin.php" target="_self">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Administração</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <div class="sidebar-section" style="margin-top:1rem;">
                <span class="sidebar-section-label">Conta</span>
            </div>
            <ul class="sidebar-nav-list">
                <li>
                    <a class="sidebar-nav-link <?= $activeSection === 'dados' ? 'active' : '' ?>"
                       href="painel.php?secao=dados" data-section="dados">
                        <i class="fa-solid fa-user-pen"></i>
                        <span>Meus Dados</span>
                    </a>
                </li>
                <li>
                    <a class="sidebar-nav-link <?= $activeSection === 'ajuda' ? 'active' : '' ?>"
                       href="painel.php?secao=ajuda" data-section="ajuda">
                        <i class="fa-solid fa-circle-question"></i>
                        <span>Ajuda</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Logout -->
        <div class="sidebar-footer">
            <a href="actions/logout.php" class="sidebar-logout"
               onclick="return confirm('Deseja sair da sua conta?')">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Sair</span>
            </a>
        </div>
    </aside>

    <!-- ── Main content ──────────────────────────────────── -->
    <div class="layout-main" id="layoutMain">

        <!-- Topbar -->
        <header class="topbar">
            <button class="topbar-hamburger" id="hamburgerBtn" aria-label="Toggle sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
            <h1 class="topbar-title" id="topbarTitle">
                <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <div class="topbar-spacer"></div>
            <div class="topbar-user" id="topbarUserWrap">
                <div class="topbar-avatar" id="topbarAvatarBtn" title="Menu do usuário">
                    <?= strtoupper(mb_substr($user['nome'], 0, 1)) ?>
                </div>
                <span class="topbar-user-name">
                    <?= htmlspecialchars(explode(' ', $user['nome'])[0], ENT_QUOTES, 'UTF-8') ?>
                </span>
                <div class="topbar-user-dropdown" id="topbarDropdown">
                    <div class="dropdown-header">
                        <strong><?= htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <a href="painel.php?secao=dados" class="dropdown-item">
                        <i class="fa-solid fa-user-pen"></i> Meus Dados
                    </a>
                    <a href="painel.php?secao=ajuda" class="dropdown-item">
                        <i class="fa-solid fa-circle-question"></i> Ajuda
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="actions/logout.php" class="dropdown-item dropdown-danger">
                        <i class="fa-solid fa-right-from-bracket"></i> Sair
                    </a>
                </div>
            </div>
        </header>

        <!-- Content -->
        <main class="layout-content">

            <?php if ($flash): ?>
            <div class="alert-box alert-<?= htmlspecialchars($flash['type'] === 'error' ? 'error' : 'success', ENT_QUOTES, 'UTF-8') ?> show"
                 style="margin-bottom:1.5rem;" role="alert">
                <i class="fa-solid fa-<?= $flash['type'] === 'error' ? 'circle-exclamation' : 'circle-check' ?>"></i>
                <span><?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <?php endif; ?>

            <!-- Painel section -->
            <?php if ($activeSection === 'painel'): ?>
            <?php include __DIR__ . '/includes/secao_painel.php'; ?>

            <!-- Veiculos section -->
            <?php elseif ($activeSection === 'veiculos' && ($tipo === 'vendedor' || $tipo === 'investidor' || $tipo === 'administrador')): ?>
            <?php include __DIR__ . '/includes/secao_veiculos.php'; ?>

            <!-- Oferta section -->
            <?php elseif ($activeSection === 'oferta' && ($tipo === 'investidor' || $tipo === 'administrador')): ?>
            <?php include __DIR__ . '/includes/secao_oferta.php'; ?>

            <!-- Propostas section -->
            <?php elseif ($activeSection === 'propostas'): ?>
            <?php include __DIR__ . '/includes/secao_propostas.php'; ?>

            <!-- Dados section -->
            <?php elseif ($activeSection === 'dados'): ?>
            <?php include __DIR__ . '/includes/secao_dados.php'; ?>

            <!-- Ajuda section -->
            <?php elseif ($activeSection === 'ajuda'): ?>
            <?php include __DIR__ . '/includes/secao_ajuda.php'; ?>
            <?php endif; ?>

        </main>
    </div>
</div>

<!-- Toast container -->
<div class="toast-container" id="toastContainer"></div>

<script>
var CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

(function () {
    // ── Sidebar toggle ────────────────────────────────────────
    var sidebar   = document.getElementById('sidebar');
    var layoutMain = document.getElementById('layoutMain');
    var overlay   = document.getElementById('sidebarOverlay');
    var hamburger = document.getElementById('hamburgerBtn');
    var isMobile  = window.innerWidth <= 900;

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

    // ── Topbar dropdown ───────────────────────────────────────
    var avatarBtn = document.getElementById('topbarAvatarBtn');
    var dropdown  = document.getElementById('topbarDropdown');

    avatarBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        dropdown.classList.toggle('open');
    });

    document.addEventListener('click', function (e) {
        if (!document.getElementById('topbarUserWrap').contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });

    // ── Toast ─────────────────────────────────────────────────
    window.showToast = function (message, type) {
        type = type || 'success';
        var container = document.getElementById('toastContainer');
        var toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        var icons = { success: 'fa-circle-check', error: 'fa-circle-exclamation', warning: 'fa-triangle-exclamation' };
        var colors = { success: 'var(--color-success)', error: 'var(--color-danger)', warning: 'var(--color-warning)' };
        toast.innerHTML = '<i class="fa-solid ' + (icons[type] || 'fa-circle-check') + '" style="color:' + (colors[type] || 'green') + ';flex-shrink:0;"></i><span>' + message + '</span>';
        container.appendChild(toast);
        setTimeout(function () {
            toast.style.animation = 'slideIn 0.3s ease reverse';
            setTimeout(function () { if (toast.parentNode) container.removeChild(toast); }, 300);
        }, 4000);
    };

    // ── Section navigation helper (used by sub-sections) ─────
    window.navegarSecao = function (section) {
        window.location.href = 'painel.php?secao=' + section;
    };

    <?php if ($flash && $flash['type'] === 'success'): ?>
    setTimeout(function () {
        window.showToast(<?= json_encode($flash['message']) ?>, 'success');
    }, 500);
    <?php endif; ?>
}());
</script>
</body>
</html>
