<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$loggedIn = !empty($_SESSION['usuario_id']);
$nomeUsuario = $loggedIn ? htmlspecialchars($_SESSION['nome'] ?? '', ENT_QUOTES, 'UTF-8') : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MotorGo – A plataforma inteligente para investir em veículos com segurança e rentabilidade.">
    <meta name="keywords" content="investimento, veículos, carros, MotorGo, rentabilidade">
    <meta name="author" content="MotorGo">
    <meta property="og:title" content="MotorGo – Invista em Veículos">
    <meta property="og:description" content="Multiplique seu capital investindo em veículos com a MotorGo.">
    <meta property="og:image" content="<?= IMAGES_URL ?>logo_motorgo.png">
    <meta property="og:type" content="website">
    <title>MotorGo – Invista em Veículos</title>
    <link rel="icon" type="image/png" href="/imagens/logo_motorgo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/main.css">
    <style>
        /* ── Landing Reset ─────────────────────────────── */
        body { background: #ffffff; }

        /* ── Header / Navbar ───────────────────────────── */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: var(--z-topbar);
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid transparent;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .navbar.scrolled {
            border-bottom-color: var(--color-border);
            box-shadow: var(--shadow-sm);
        }
        .navbar-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
            height: 70px;
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        .navbar-logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            flex-shrink: 0;
        }
        .navbar-logo img {
            height: 40px;
            width: auto;
            object-fit: contain;
        }
        .navbar-nav {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-left: auto;
        }
        .navbar-nav a {
            padding: 0.5rem 0.875rem;
            color: var(--color-text);
            font-size: 0.9rem;
            font-weight: 500;
            border-radius: var(--radius-md);
            transition: var(--transition);
        }
        .navbar-nav a:hover { background: var(--color-bg); color: var(--color-primary); }
        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: 1rem;
        }
        .btn-outline-nav {
            padding: 0.5rem 1.25rem;
            border: 1.5px solid var(--color-primary);
            color: var(--color-primary);
            border-radius: var(--radius-full);
            font-weight: 600;
            font-size: 0.875rem;
            background: transparent;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        .btn-outline-nav:hover { background: var(--color-primary); color: #fff; }
        .btn-primary-nav {
            padding: 0.5rem 1.25rem;
            background: var(--color-primary);
            color: #fff;
            border: none;
            border-radius: var(--radius-full);
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        .btn-primary-nav:hover { background: var(--color-primary-dark); color: #fff; }
        .navbar-hamburger {
            display: none;
            background: none;
            border: none;
            font-size: 1.25rem;
            color: var(--color-text);
            cursor: pointer;
            padding: 0.5rem;
            margin-left: auto;
        }
        .navbar-mobile-menu {
            display: none;
            flex-direction: column;
            background: #fff;
            border-top: 1px solid var(--color-border);
            padding: 1rem 1.5rem;
            gap: 0.5rem;
        }
        .navbar-mobile-menu.open { display: flex; }
        .navbar-mobile-menu a {
            padding: 0.625rem 0;
            color: var(--color-text);
            font-weight: 500;
            border-bottom: 1px solid var(--color-border);
        }
        .navbar-mobile-menu a:last-child { border-bottom: none; }

        /* ── Hero ──────────────────────────────────────── */
        .hero {
            min-height: 100vh;
            background: linear-gradient(135deg, #0d0d0d 0%, #1a1a1a 40%, #2d0a0a 80%, #1a0505 100%);
            display: flex;
            align-items: center;
            padding: 8rem 1.5rem 5rem;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: -20%;
            right: -10%;
            width: 60%;
            height: 80%;
            background: radial-gradient(ellipse at center, rgba(178,34,34,0.18) 0%, transparent 70%);
            pointer-events: none;
        }
        .hero::after {
            content: '';
            position: absolute;
            bottom: -10%;
            left: -5%;
            width: 40%;
            height: 60%;
            background: radial-gradient(ellipse at center, rgba(178,34,34,0.10) 0%, transparent 70%);
            pointer-events: none;
        }
        .hero-inner {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(178,34,34,0.15);
            border: 1px solid rgba(178,34,34,0.3);
            color: #e87070;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0.35rem 0.875rem;
            border-radius: var(--radius-full);
            margin-bottom: 1.5rem;
        }
        .hero-eyebrow i { font-size: 0.7rem; }
        .hero h1 {
            font-size: clamp(2.5rem, 5vw, 3.75rem);
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.03em;
            line-height: 1.1;
            margin-bottom: 1.5rem;
        }
        .hero h1 span { color: var(--color-primary-light); }
        .hero-subtitle {
            font-size: 1.125rem;
            color: rgba(255,255,255,0.65);
            line-height: 1.7;
            margin-bottom: 2.5rem;
            max-width: 480px;
        }
        .hero-ctas {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .btn-hero-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 2rem;
            background: var(--color-primary);
            color: #fff;
            border-radius: var(--radius-full);
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            transition: var(--transition);
            box-shadow: 0 4px 20px rgba(178,34,34,0.4);
        }
        .btn-hero-primary:hover {
            background: var(--color-primary-dark);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 6px 28px rgba(178,34,34,0.5);
        }
        .btn-hero-secondary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 2rem;
            background: rgba(255,255,255,0.08);
            color: rgba(255,255,255,0.85);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: var(--radius-full);
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            transition: var(--transition);
        }
        .btn-hero-secondary:hover {
            background: rgba(255,255,255,0.14);
            color: #fff;
        }
        .hero-dashboard-banner {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
            padding: 0.75rem 1.25rem;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--radius-lg);
            color: rgba(255,255,255,0.6);
            font-size: 0.875rem;
            font-weight: 500;
            width: fit-content;
        }
        .hero-dashboard-banner a {
            color: #e87070;
            font-weight: 700;
            text-decoration: underline;
        }
        /* Hero visual / car mockup */
        .hero-visual {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .hero-card-mockup {
            width: 100%;
            max-width: 420px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--radius-xl);
            padding: 2rem;
            backdrop-filter: blur(10px);
        }
        .hero-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        .hero-card-badge {
            background: rgba(22,163,74,0.15);
            color: #4ade80;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.3rem 0.75rem;
            border-radius: var(--radius-full);
            border: 1px solid rgba(22,163,74,0.25);
        }
        .hero-car-icon-wrap {
            width: 100%;
            height: 160px;
            background: linear-gradient(135deg, rgba(178,34,34,0.12), rgba(178,34,34,0.05));
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(178,34,34,0.15);
        }
        .hero-car-icon-wrap i { font-size: 5rem; color: rgba(178,34,34,0.6); }
        .hero-card-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .hero-stat-item { text-align: center; }
        .hero-stat-value {
            font-size: 1.375rem;
            font-weight: 800;
            color: #ffffff;
            display: block;
        }
        .hero-stat-label {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.45);
        }
        .hero-card-divider {
            height: 1px;
            background: rgba(255,255,255,0.07);
            margin: 1rem 0;
        }
        .hero-card-btn {
            display: block;
            width: 100%;
            text-align: center;
            padding: 0.75rem;
            background: var(--color-primary);
            color: #fff;
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: 0.875rem;
            text-decoration: none;
            transition: var(--transition);
        }
        .hero-card-btn:hover { background: var(--color-primary-dark); color: #fff; }

        /* ── Stats ─────────────────────────────────────── */
        .stats-section {
            background: #fff;
            padding: 5rem 1.5rem;
        }
        .stats-inner {
            max-width: 1200px;
            margin: 0 auto;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
        }
        .stat-box {
            text-align: center;
            padding: 2.5rem 1.5rem;
            background: var(--color-bg);
            border-radius: var(--radius-xl);
            border: 1px solid var(--color-border);
            transition: var(--transition);
        }
        .stat-box:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: rgba(178,34,34,0.2);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            background: rgba(178,34,34,0.08);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        .stat-icon i { font-size: 1.5rem; color: var(--color-primary); }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--color-secondary);
            letter-spacing: -0.04em;
            display: block;
        }
        .stat-label {
            font-size: 1rem;
            color: var(--color-text-muted);
            font-weight: 500;
        }

        /* ── How It Works ──────────────────────────────── */
        .how-section {
            padding: 6rem 1.5rem;
            background: linear-gradient(180deg, #fff 0%, #f9fafb 100%);
        }
        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }
        .section-tag {
            display: inline-block;
            background: rgba(178,34,34,0.08);
            color: var(--color-primary);
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0.35rem 0.875rem;
            border-radius: var(--radius-full);
            margin-bottom: 1rem;
        }
        .section-title {
            font-size: clamp(1.75rem, 3vw, 2.5rem);
            color: var(--color-secondary);
            margin-bottom: 0.75rem;
        }
        .section-subtitle {
            font-size: 1.0625rem;
            color: var(--color-text-muted);
            max-width: 520px;
            margin: 0 auto;
            line-height: 1.7;
        }
        .how-grid {
            max-width: 960px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            position: relative;
        }
        .how-grid::before {
            content: '';
            position: absolute;
            top: 2.75rem;
            left: calc(16.66% + 1rem);
            right: calc(16.66% + 1rem);
            height: 2px;
            background: linear-gradient(90deg, var(--color-primary), var(--color-primary-light), var(--color-primary));
            opacity: 0.25;
        }
        .how-step {
            text-align: center;
            padding: 2rem 1.5rem;
            background: #fff;
            border-radius: var(--radius-xl);
            border: 1px solid var(--color-border);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
        }
        .how-step:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); }
        .how-step-number {
            width: 56px;
            height: 56px;
            background: var(--color-primary);
            color: #fff;
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.375rem;
            font-weight: 800;
            margin: 0 auto 1.25rem;
            box-shadow: 0 4px 16px rgba(178,34,34,0.35);
        }
        .how-step h3 {
            font-size: 1.125rem;
            margin-bottom: 0.625rem;
        }
        .how-step p {
            font-size: 0.9rem;
            color: var(--color-text-muted);
            line-height: 1.65;
            margin-bottom: 0;
        }

        /* ── Features ──────────────────────────────────── */
        .features-section {
            padding: 6rem 1.5rem;
            background: #fff;
        }
        .features-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }
        .feature-card {
            padding: 2rem;
            background: var(--color-bg);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            transition: var(--transition);
        }
        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: rgba(178,34,34,0.2);
            background: #fff;
        }
        .feature-icon {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, rgba(178,34,34,0.12), rgba(178,34,34,0.06));
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
            border: 1px solid rgba(178,34,34,0.1);
        }
        .feature-icon i { font-size: 1.25rem; color: var(--color-primary); }
        .feature-card h3 {
            font-size: 1.0625rem;
            margin-bottom: 0.5rem;
        }
        .feature-card p {
            font-size: 0.875rem;
            color: var(--color-text-muted);
            line-height: 1.65;
            margin-bottom: 0;
        }

        /* ── CTA Section ───────────────────────────────── */
        .cta-section {
            padding: 6rem 1.5rem;
            background: linear-gradient(135deg, var(--color-primary), #8B1A1A);
            text-align: center;
        }
        .cta-inner { max-width: 640px; margin: 0 auto; }
        .cta-section h2 {
            font-size: clamp(1.75rem, 3vw, 2.5rem);
            color: #fff;
            margin-bottom: 1rem;
        }
        .cta-section p { color: rgba(255,255,255,0.75); font-size: 1.0625rem; margin-bottom: 2.5rem; }
        .btn-cta {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 2.25rem;
            background: #fff;
            color: var(--color-primary);
            border-radius: var(--radius-full);
            font-weight: 700;
            font-size: 1.0625rem;
            text-decoration: none;
            transition: var(--transition);
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .btn-cta:hover {
            background: var(--color-bg);
            color: var(--color-primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 28px rgba(0,0,0,0.25);
        }

        /* ── Footer ─────────────────────────────────────── */
        .footer {
            background: #0d0d0d;
            padding: 4rem 1.5rem 2rem;
        }
        .footer-inner {
            max-width: 1200px;
            margin: 0 auto;
        }
        .footer-top {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 3rem;
            padding-bottom: 3rem;
            border-bottom: 1px solid rgba(255,255,255,0.07);
        }
        .footer-brand img { height: 38px; margin-bottom: 1rem; }
        .footer-brand p {
            font-size: 0.875rem;
            color: rgba(255,255,255,0.45);
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }
        .footer-social { display: flex; gap: 0.625rem; }
        .footer-social a {
            width: 36px;
            height: 36px;
            background: rgba(255,255,255,0.07);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,0.5);
            font-size: 0.875rem;
            transition: var(--transition);
        }
        .footer-social a:hover { background: var(--color-primary); color: #fff; }
        .footer-col h4 {
            font-size: 0.8125rem;
            font-weight: 700;
            color: rgba(255,255,255,0.9);
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 1.25rem;
        }
        .footer-col ul { list-style: none; display: flex; flex-direction: column; gap: 0.625rem; }
        .footer-col ul a {
            font-size: 0.875rem;
            color: rgba(255,255,255,0.45);
            transition: var(--transition);
        }
        .footer-col ul a:hover { color: rgba(255,255,255,0.85); }
        .footer-bottom {
            padding-top: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .footer-bottom p {
            font-size: 0.8125rem;
            color: rgba(255,255,255,0.3);
            margin-bottom: 0;
        }

        /* ── Responsive ─────────────────────────────────── */
        @media (max-width: 1024px) {
            .features-grid { grid-template-columns: repeat(2, 1fr); }
            .footer-top { grid-template-columns: 1fr 1fr; gap: 2rem; }
        }
        @media (max-width: 768px) {
            .navbar-nav, .navbar-actions { display: none; }
            .navbar-hamburger { display: block; }
            .hero-inner { grid-template-columns: 1fr; }
            .hero-visual { display: none; }
            .hero { min-height: auto; padding: 7rem 1.5rem 4rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .how-grid { grid-template-columns: 1fr; }
            .how-grid::before { display: none; }
            .features-grid { grid-template-columns: 1fr; }
            .footer-top { grid-template-columns: 1fr; gap: 2rem; }
            .footer-bottom { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

<!-- ── Navbar ──────────────────────────────────────────────── -->
<header class="navbar" id="navbar">
    <div class="navbar-inner">
        <a href="index.php" class="navbar-logo">
            <img src="/imagens/logo_motorgo.png" alt="MotorGo">
        </a>
        <nav class="navbar-nav">
            <a href="#como-funciona">Como Funciona</a>
            <a href="#sobre">Sobre</a>
            <a href="#contato">Contato</a>
        </nav>
        <div class="navbar-actions">
            <?php if ($loggedIn): ?>
                <a href="painel.php" class="btn-outline-nav">
                    <i class="fa-solid fa-gauge-high"></i>&nbsp;Meu Painel
                </a>
                <a href="logout.php" class="btn-primary-nav">Sair</a>
            <?php else: ?>
                <a href="login.php" class="btn-outline-nav">Entrar</a>
                <a href="cadastro.php" class="btn-primary-nav">Cadastrar</a>
            <?php endif; ?>
        </div>
        <button class="navbar-hamburger" id="hamburgerBtn" aria-label="Menu">
            <i class="fa-solid fa-bars" id="hamburgerIcon"></i>
        </button>
    </div>
    <div class="navbar-mobile-menu" id="mobileMenu">
        <a href="#como-funciona">Como Funciona</a>
        <a href="#sobre">Sobre</a>
        <a href="#contato">Contato</a>
        <?php if ($loggedIn): ?>
            <a href="painel.php"><i class="fa-solid fa-gauge-high"></i> Meu Painel</a>
            <a href="logout.php">Sair</a>
        <?php else: ?>
            <a href="login.php">Entrar</a>
            <a href="cadastro.php" style="color:var(--color-primary);font-weight:700;">Cadastrar</a>
        <?php endif; ?>
    </div>
</header>

<!-- ── Hero ────────────────────────────────────────────────── -->
<section class="hero" id="inicio">
    <div class="hero-inner">
        <div class="hero-content">
            <div class="hero-eyebrow">
                <i class="fa-solid fa-circle-check"></i>
                Plataforma Certificada
            </div>
            <h1>Invista em Carros.<br><span>Multiplique</span> Seu Capital.</h1>
            <p class="hero-subtitle">
                A MotorGo conecta investidores a oportunidades reais no mercado automotivo.
                Segurança, transparência e rentabilidade em cada negociação.
            </p>
            <div class="hero-ctas">
                <a href="cadastro.php" class="btn-hero-primary">
                    <i class="fa-solid fa-rocket"></i>
                    Começar Agora
                </a>
                <a href="#como-funciona" class="btn-hero-secondary">
                    <i class="fa-solid fa-play"></i>
                    Saiba Mais
                </a>
            </div>
            <?php if ($loggedIn): ?>
            <div class="hero-dashboard-banner">
                <i class="fa-solid fa-user-circle"></i>
                Olá, <strong style="color:rgba(255,255,255,0.8);margin:0 4px;"><?= $nomeUsuario ?></strong>!
                <a href="painel.php">Ir ao Painel →</a>
            </div>
            <?php endif; ?>
        </div>
        <div class="hero-visual">
            <div class="hero-card-mockup">
                <div class="hero-card-top">
                    <span style="color:rgba(255,255,255,0.55);font-size:0.8125rem;font-weight:600;">Oportunidade Destacada</span>
                    <span class="hero-card-badge"><i class="fa-solid fa-circle" style="font-size:0.5rem;"></i> Disponível</span>
                </div>
                <div class="hero-car-icon-wrap">
                    <i class="fa-solid fa-car-side"></i>
                </div>
                <div class="hero-card-stats">
                    <div class="hero-stat-item">
                        <span class="hero-stat-value">R$ 45k</span>
                        <span class="hero-stat-label">Valor Estimado</span>
                    </div>
                    <div class="hero-stat-item">
                        <span class="hero-stat-value">+18%</span>
                        <span class="hero-stat-label">Retorno Previsto</span>
                    </div>
                </div>
                <div class="hero-card-divider"></div>
                <a href="cadastro.php" class="hero-card-btn">Ver Oportunidade</a>
            </div>
        </div>
    </div>
</section>

<!-- ── Stats ────────────────────────────────────────────────── -->
<section class="stats-section">
    <div class="stats-inner">
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-icon"><i class="fa-solid fa-car"></i></div>
                <span class="stat-number">500+</span>
                <span class="stat-label">Veículos Cadastrados</span>
            </div>
            <div class="stat-box">
                <div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                <span class="stat-number">R$ 10M+</span>
                <span class="stat-label">Negociados na Plataforma</span>
            </div>
            <div class="stat-box">
                <div class="stat-icon"><i class="fa-solid fa-star"></i></div>
                <span class="stat-number">98%</span>
                <span class="stat-label">Satisfação dos Usuários</span>
            </div>
        </div>
    </div>
</section>

<!-- ── How It Works ─────────────────────────────────────────── -->
<section class="how-section" id="como-funciona">
    <div style="max-width:1200px;margin:0 auto;">
        <div class="section-header">
            <span class="section-tag">Como Funciona</span>
            <h2 class="section-title">Simples, Rápido e Seguro</h2>
            <p class="section-subtitle">Em apenas 3 passos você começa a investir no mercado automotivo.</p>
        </div>
        <div class="how-grid">
            <div class="how-step">
                <div class="how-step-number">1</div>
                <h3>Cadastre-se</h3>
                <p>Crie sua conta gratuitamente em minutos. Preencha seus dados, confirme seu e-mail e comece.</p>
            </div>
            <div class="how-step">
                <div class="how-step-number">2</div>
                <h3>Encontre Veículos</h3>
                <p>Navegue pelo catálogo de veículos disponíveis, veja fotos, especificações e valores de mercado.</p>
            </div>
            <div class="how-step">
                <div class="how-step-number">3</div>
                <h3>Faça Propostas</h3>
                <p>Envie propostas aos vendedores, negocie valores e finalize investimentos lucrativos.</p>
            </div>
        </div>
    </div>
</section>

<!-- ── Features ─────────────────────────────────────────────── -->
<section class="features-section" id="sobre">
    <div style="max-width:1200px;margin:0 auto;">
        <div class="section-header">
            <span class="section-tag">Diferenciais</span>
            <h2 class="section-title">Por Que Escolher a MotorGo?</h2>
            <p class="section-subtitle">Desenvolvemos uma plataforma pensada em cada detalhe para sua melhor experiência.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <h3>Segurança</h3>
                <p>Todos os usuários são verificados. Dados protegidos com criptografia e autenticação segura em cada acesso.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-eye"></i></div>
                <h3>Transparência</h3>
                <p>Histórico completo de negociações, preços de mercado e documentação acessível para investidores e vendedores.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-chart-line"></i></div>
                <h3>Rendimento</h3>
                <p>Identifique veículos com alto potencial de valorização e maximize seus retornos com inteligência de mercado.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-bolt"></i></div>
                <h3>Facilidade</h3>
                <p>Interface intuitiva para negociar, acompanhar propostas e gerenciar seu portfólio de investimentos.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-headset"></i></div>
                <h3>Suporte</h3>
                <p>Time especializado disponível para orientar suas decisões e resolver dúvidas em tempo real.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-microchip"></i></div>
                <h3>Tecnologia</h3>
                <p>Plataforma construída com tecnologia de ponta, garantindo desempenho, disponibilidade e inovação contínua.</p>
            </div>
        </div>
    </div>
</section>

<!-- ── CTA ───────────────────────────────────────────────────── -->
<section class="cta-section">
    <div class="cta-inner">
        <h2>Comece a investir hoje.</h2>
        <p>Junte-se a centenas de investidores que já estão multiplicando seu capital com veículos na MotorGo.</p>
        <a href="cadastro.php" class="btn-cta">
            <i class="fa-solid fa-rocket"></i>
            Criar Conta Gratuita
        </a>
    </div>
</section>

<!-- ── Footer ───────────────────────────────────────────────── -->
<footer class="footer" id="contato">
    <div class="footer-inner">
        <div class="footer-top">
            <div class="footer-brand">
                <img src="/imagens/logo_motorgo.png" alt="MotorGo" style="filter:brightness(0) invert(1);">
                <p>A plataforma inteligente para investir em veículos com segurança, transparência e rentabilidade.</p>
                <div class="footer-social">
                    <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
                    <a href="#" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                </div>
            </div>
            <div class="footer-col">
                <h4>Plataforma</h4>
                <ul>
                    <li><a href="cadastro.php">Cadastrar</a></li>
                    <li><a href="login.php">Entrar</a></li>
                    <li><a href="#como-funciona">Como Funciona</a></li>
                    <li><a href="#sobre">Sobre Nós</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Suporte</h4>
                <ul>
                    <li><a href="mailto:suporte@motorgo.co">E-mail</a></li>
                    <li><a href="#">Central de Ajuda</a></li>
                    <li><a href="#">Política de Privacidade</a></li>
                    <li><a href="#">Termos de Uso</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Contato</h4>
                <ul>
                    <li><a href="mailto:contato@motorgo.co">contato@motorgo.co</a></li>
                    <li><a href="#">São Paulo, SP</a></li>
                    <li><a href="#">CNPJ: 00.000.000/0001-00</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2025 MotorGo. Todos os direitos reservados.</p>
            <p>Feito com <i class="fa-solid fa-heart" style="color:var(--color-primary);font-size:0.7rem;"></i> para investidores inteligentes.</p>
        </div>
    </div>
</footer>

<script>
(function () {
    // Sticky header shadow
    var navbar = document.getElementById('navbar');
    window.addEventListener('scroll', function () {
        if (window.scrollY > 20) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });

    // Mobile menu toggle
    var hamburger = document.getElementById('hamburgerBtn');
    var hamburgerIcon = document.getElementById('hamburgerIcon');
    var mobileMenu = document.getElementById('mobileMenu');
    hamburger.addEventListener('click', function () {
        var isOpen = mobileMenu.classList.toggle('open');
        hamburgerIcon.className = isOpen ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
    });

    // Close mobile menu on link click
    mobileMenu.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            mobileMenu.classList.remove('open');
            hamburgerIcon.className = 'fa-solid fa-bars';
        });
    });

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            var target = document.querySelector(this.getAttribute('href'));
            if (!target) return;
            e.preventDefault();
            var offset = 80;
            var top = target.getBoundingClientRect().top + window.pageYOffset - offset;
            window.scrollTo({ top: top, behavior: 'smooth' });
        });
    });
}());
</script>
</body>
</html>
