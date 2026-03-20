<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/dozero/painel.php');
    exit;
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>MotorGo – Entrar</title>
    <link rel="icon" type="image/png" href="imagens/logo_motorgo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/main.css">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            background: #fff;
        }
        .auth-wrap {
            display: flex;
            min-height: 100vh;
        }

        /* ── Left Panel ──────────────────────────────── */
        .auth-left {
            flex: 0 0 44%;
            background: linear-gradient(160deg, #111111 0%, #1e1e1e 40%, #2d0a0a 100%);
            display: flex;
            flex-direction: column;
            padding: 3rem;
            position: relative;
            overflow: hidden;
        }
        .auth-left::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -20%;
            width: 70%;
            height: 80%;
            background: radial-gradient(ellipse, rgba(178,34,34,0.22) 0%, transparent 65%);
            pointer-events: none;
        }
        .auth-left::after {
            content: '';
            position: absolute;
            bottom: -20%;
            left: -10%;
            width: 55%;
            height: 60%;
            background: radial-gradient(ellipse, rgba(178,34,34,0.12) 0%, transparent 65%);
            pointer-events: none;
        }
        .auth-left-logo {
            position: relative;
            z-index: 1;
        }
        .auth-left-logo img {
            height: 44px;
            filter: brightness(0) invert(1);
        }
        .auth-left-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            z-index: 1;
        }
        .auth-left-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(178,34,34,0.2);
            border: 1px solid rgba(178,34,34,0.35);
            color: #e87070;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0.3rem 0.75rem;
            border-radius: 9999px;
            margin-bottom: 1.5rem;
            width: fit-content;
        }
        .auth-left h2 {
            font-size: 2.25rem;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.2;
            letter-spacing: -0.03em;
            margin-bottom: 1rem;
        }
        .auth-left h2 span { color: #e87070; }
        .auth-left p {
            font-size: 1rem;
            color: rgba(255,255,255,0.55);
            line-height: 1.7;
            max-width: 360px;
            margin-bottom: 2.5rem;
        }
        .auth-feature-list { list-style: none; display: flex; flex-direction: column; gap: 0.875rem; }
        .auth-feature-list li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: rgba(255,255,255,0.7);
            font-size: 0.9rem;
        }
        .auth-feature-list li .chk {
            width: 24px;
            height: 24px;
            background: rgba(22,163,74,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .auth-feature-list li .chk i { color: #4ade80; font-size: 0.625rem; }
        .auth-left-car {
            position: absolute;
            bottom: 3rem;
            right: 0;
            font-size: 9rem;
            color: rgba(178,34,34,0.12);
            pointer-events: none;
            line-height: 1;
        }

        /* ── Right Panel ─────────────────────────────── */
        .auth-right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 2rem;
            background: #fff;
        }
        .auth-form-box {
            width: 100%;
            max-width: 420px;
        }
        .auth-form-header {
            margin-bottom: 2.25rem;
        }
        .auth-form-header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #1a1a1a;
            letter-spacing: -0.03em;
            margin-bottom: 0.375rem;
        }
        .auth-form-header p {
            font-size: 0.9375rem;
            color: #6b7280;
            margin-bottom: 0;
        }

        /* Form elements */
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.9375rem;
            color: #1a1a1a;
            background: #fff;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }
        .form-control:focus {
            border-color: #B22222;
            box-shadow: 0 0 0 3px rgba(178,34,34,0.1);
        }
        .form-control::placeholder { color: #9ca3af; }
        .form-control.is-invalid {
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
        }
        .invalid-feedback {
            display: none;
            font-size: 0.8125rem;
            color: #dc2626;
            margin-top: 0.35rem;
        }
        .form-control.is-invalid ~ .invalid-feedback { display: block; }

        /* Password input with toggle */
        .input-group {
            position: relative;
        }
        .input-group .form-control { padding-right: 2.75rem; }
        .input-toggle {
            position: absolute;
            right: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 0.25rem;
            font-size: 1rem;
            transition: color 0.2s;
        }
        .input-toggle:hover { color: #6b7280; }

        /* Checkbox + remember */
        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .form-check input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #B22222;
            cursor: pointer;
            flex-shrink: 0;
        }
        .form-check label {
            font-size: 0.875rem;
            color: #6b7280;
            cursor: pointer;
        }
        .form-row-between {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        .link-forgot {
            font-size: 0.875rem;
            color: #B22222;
            font-weight: 500;
            text-decoration: none;
        }
        .link-forgot:hover { text-decoration: underline; color: #8B1A1A; }

        /* Submit button */
        .btn-submit {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.875rem 1.5rem;
            background: #B22222;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 4px 16px rgba(178,34,34,0.3);
        }
        .btn-submit:hover:not(:disabled) {
            background: #8B1A1A;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(178,34,34,0.4);
        }
        .btn-submit:disabled { opacity: 0.65; cursor: not-allowed; }
        .btn-submit .spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.35);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }
        .btn-submit.loading .spinner { display: block; }
        .btn-submit.loading .btn-text { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Divider / register link */
        .auth-divider {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 1.5rem 0;
        }
        .auth-divider hr { flex: 1; border: none; border-top: 1px solid #e5e7eb; }
        .auth-divider span { font-size: 0.8125rem; color: #9ca3af; white-space: nowrap; }
        .auth-register-link {
            text-align: center;
            font-size: 0.9rem;
            color: #6b7280;
        }
        .auth-register-link a {
            color: #B22222;
            font-weight: 700;
            text-decoration: none;
        }
        .auth-register-link a:hover { text-decoration: underline; }

        /* Alert / toast */
        .alert-box {
            display: none;
            padding: 0.875rem 1rem;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 1.25rem;
            align-items: flex-start;
            gap: 0.625rem;
        }
        .alert-box.show { display: flex; }
        .alert-box.alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alert-box.alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .alert-box i { margin-top: 1px; flex-shrink: 0; }

        /* Responsive */
        @media (max-width: 900px) {
            .auth-left { display: none; }
            .auth-right { padding: 2rem 1.25rem; }
        }
    </style>
</head>
<body>
<div class="auth-wrap">

    <!-- Left Panel -->
    <div class="auth-left">
        <div class="auth-left-logo">
            <img src="imagens/logo_motorgo_blk.png" alt="MotorGo" onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
            <span style="display:none;font-size:1.5rem;font-weight:800;color:#fff;letter-spacing:-0.04em;">Motor<span style="color:#e87070;">Go</span></span>
        </div>
        <div class="auth-left-body">
            <div class="auth-left-tag">
                <i class="fa-solid fa-lock" style="font-size:0.6rem;"></i>
                Acesso Seguro
            </div>
            <h2>Bem-vindo<br>de <span>volta.</span></h2>
            <p>Entre na sua conta e continue acompanhando seus investimentos em veículos.</p>
            <ul class="auth-feature-list">
                <li>
                    <span class="chk"><i class="fa-solid fa-check"></i></span>
                    Portfólio completo de veículos
                </li>
                <li>
                    <span class="chk"><i class="fa-solid fa-check"></i></span>
                    Propostas em tempo real
                </li>
                <li>
                    <span class="chk"><i class="fa-solid fa-check"></i></span>
                    Dados protegidos e criptografados
                </li>
            </ul>
        </div>
        <div class="auth-left-car">
            <i class="fa-solid fa-car-side"></i>
        </div>
    </div>

    <!-- Right Panel -->
    <div class="auth-right">
        <div class="auth-form-box">
            <div class="auth-form-header">
                <h1>Entrar na conta</h1>
                <p>Informe seu e-mail e senha para acessar.</p>
            </div>

            <div class="alert-box alert-error" id="alertError" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span id="alertErrorMsg">Credenciais inválidas.</span>
            </div>
            <div class="alert-box alert-success" id="alertSuccess" role="alert">
                <i class="fa-solid fa-circle-check"></i>
                <span id="alertSuccessMsg">Login realizado! Redirecionando…</span>
            </div>

            <form id="loginForm" novalidate autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label class="form-label" for="email">E-mail</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="seu@email.com"
                        autocomplete="email"
                        required
                    >
                    <span class="invalid-feedback">Informe um e-mail válido.</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="senha">Senha</label>
                    <div class="input-group">
                        <input
                            type="password"
                            id="senha"
                            name="senha"
                            class="form-control"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="input-toggle" id="toggleSenha" aria-label="Mostrar senha">
                            <i class="fa-regular fa-eye" id="toggleSenhaIcon"></i>
                        </button>
                    </div>
                    <span class="invalid-feedback">A senha é obrigatória.</span>
                </div>

                <div class="form-row-between">
                    <label class="form-check" style="margin-bottom:0;">
                        <input type="checkbox" name="lembrar" id="lembrar">
                        <span>Lembrar de mim</span>
                    </label>
                    <a href="recuperar_senha.php" class="link-forgot">Esqueceu a senha?</a>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <div class="spinner"></div>
                    <span class="btn-text"><i class="fa-solid fa-right-to-bracket"></i>&nbsp;Entrar</span>
                </button>
            </form>

            <div class="auth-divider">
                <hr><span>ou</span><hr>
            </div>

            <p class="auth-register-link">
                Não tem conta? <a href="cadastro.php">Cadastre-se gratuitamente</a>
            </p>
        </div>
    </div>

</div>

<script>
(function () {
    // Password visibility toggle
    var toggleBtn = document.getElementById('toggleSenha');
    var senhaInput = document.getElementById('senha');
    var toggleIcon = document.getElementById('toggleSenhaIcon');

    toggleBtn.addEventListener('click', function () {
        var isPassword = senhaInput.type === 'password';
        senhaInput.type = isPassword ? 'text' : 'password';
        toggleIcon.className = isPassword ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
    });

    // Helpers
    function showAlert(type, msg) {
        document.getElementById('alertError').classList.remove('show');
        document.getElementById('alertSuccess').classList.remove('show');
        if (type === 'error') {
            document.getElementById('alertErrorMsg').textContent = msg;
            document.getElementById('alertError').classList.add('show');
        } else {
            document.getElementById('alertSuccessMsg').textContent = msg;
            document.getElementById('alertSuccess').classList.add('show');
        }
    }

    function setLoading(loading) {
        var btn = document.getElementById('submitBtn');
        btn.disabled = loading;
        if (loading) {
            btn.classList.add('loading');
        } else {
            btn.classList.remove('loading');
        }
    }

    function markInvalid(input) {
        input.classList.add('is-invalid');
        input.addEventListener('input', function () {
            input.classList.remove('is-invalid');
        }, { once: true });
    }

    // Form submit
    document.getElementById('loginForm').addEventListener('submit', function (e) {
        e.preventDefault();

        var emailInput = document.getElementById('email');
        var senhaInputEl = document.getElementById('senha');
        var valid = true;

        if (!emailInput.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value.trim())) {
            markInvalid(emailInput);
            valid = false;
        }
        if (!senhaInputEl.value.trim()) {
            markInvalid(senhaInputEl);
            valid = false;
        }
        if (!valid) return;

        setLoading(true);

        var formData = new FormData(this);

        fetch('actions/login.php', {
            method: 'POST',
            body: formData
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                showAlert('success', data.message || 'Login realizado! Redirecionando…');
                setTimeout(function () {
                    window.location.href = data.redirect || 'painel.php';
                }, 800);
            } else {
                setLoading(false);
                showAlert('error', data.message || 'E-mail ou senha incorretos.');
                if (data.field === 'email') markInvalid(emailInput);
                if (data.field === 'senha') markInvalid(senhaInputEl);
            }
        })
        .catch(function () {
            setLoading(false);
            showAlert('error', 'Erro de conexão. Tente novamente.');
        });
    });
}());
</script>
</body>
</html>
