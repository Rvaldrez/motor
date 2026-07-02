<?php
require_once __DIR__ . '/includes/config.php';

if (!empty($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . '/painel.php');
    exit;
}

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$hasToken = ($token !== '' && strlen($token) >= 32 && preg_match('/^[a-f0-9]+$/', $token));
$pageTitle = $hasToken ? 'Redefinir Senha' : 'Recuperar Senha';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
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
    <link rel="stylesheet" href="css/main.css">
    <style>
        body { background: #f5f6fa; }
        .rec-wrap {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.25rem;
        }
        .rec-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2rem;
        }
        .rec-logo a { display: block; }
        .rec-logo img { height: 42px; }
        .rec-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.05);
        }
        .rec-icon-wrap {
            width: 64px;
            height: 64px;
            background: rgba(178,34,34,0.08);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        .rec-icon-wrap i { font-size: 1.75rem; color: #B22222; }
        .rec-card h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1a1a1a;
            letter-spacing: -0.03em;
            text-align: center;
            margin-bottom: 0.5rem;
        }
        .rec-card .rec-subtitle {
            font-size: 0.9rem;
            color: #6b7280;
            text-align: center;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .form-group { margin-bottom: 1.25rem; }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.45rem;
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
        .form-control.is-invalid { border-color: #dc2626; box-shadow: 0 0 0 3px rgba(220,38,38,0.1); }
        .invalid-feedback { display: none; font-size: 0.8125rem; color: #dc2626; margin-top: 0.35rem; }
        .form-control.is-invalid ~ .invalid-feedback { display: block; }

        .input-group { position: relative; }
        .input-group .form-control { padding-right: 2.75rem; }
        .input-toggle {
            position: absolute; right: 0.875rem; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #9ca3af; cursor: pointer; padding: 0.25rem; font-size: 0.9375rem;
        }
        .input-toggle:hover { color: #6b7280; }

        .pw-strength-wrap { margin-top: 0.5rem; }
        .pw-strength-bar { height: 4px; background: #e5e7eb; border-radius: 9999px; overflow: hidden; margin-bottom: 0.35rem; }
        .pw-strength-fill { height: 100%; width: 0; border-radius: 9999px; transition: width 0.3s, background 0.3s; }
        .pw-strength-label { font-size: 0.75rem; color: #9ca3af; font-weight: 500; }

        .btn-submit {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.875rem;
            background: #B22222;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s;
            box-shadow: 0 4px 14px rgba(178,34,34,0.3);
        }
        .btn-submit:hover:not(:disabled) { background: #8B1A1A; transform: translateY(-1px); }
        .btn-submit:disabled { opacity: 0.65; cursor: not-allowed; }
        .btn-submit .spinner {
            display: none; width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.35);
            border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite;
        }
        .btn-submit.loading .spinner { display: block; }
        .btn-submit.loading .btn-text { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .alert-box {
            display: none; padding: 0.875rem 1rem; border-radius: 10px; font-size: 0.875rem;
            font-weight: 500; margin-bottom: 1.25rem; align-items: flex-start; gap: 0.625rem;
        }
        .alert-box.show { display: flex; }
        .alert-box.alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-box.alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }

        .rec-back-link {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            justify-content: center;
            margin-top: 1.5rem;
            font-size: 0.875rem;
            color: #6b7280;
            text-decoration: none;
            font-weight: 500;
        }
        .rec-back-link:hover { color: #B22222; }

        .success-state { display: none; text-align: center; }
        .success-state .success-icon {
            width: 72px;
            height: 72px;
            background: #f0fdf4;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
        }
        .success-state .success-icon i { font-size: 2rem; color: #16a34a; }
        .success-state h2 { font-size: 1.375rem; font-weight: 800; color: #1a1a1a; margin-bottom: 0.5rem; }
        .success-state p { font-size: 0.9rem; color: #6b7280; line-height: 1.65; margin-bottom: 1.5rem; }
    </style>
</head>
<body>
<div class="rec-wrap">

    <div class="rec-logo">
        <a href="index.php"><img src="/imagens/logo_motorgo.png" alt="MotorGo"></a>
    </div>

    <div class="rec-card">

        <?php if ($hasToken): ?>
        <!-- ── Reset password form (token present) ────── -->
        <div class="rec-icon-wrap"><i class="fa-solid fa-lock-open"></i></div>
        <h1>Nova Senha</h1>
        <p class="rec-subtitle">Digite e confirme sua nova senha abaixo.</p>

        <div class="alert-box alert-error" id="alertError" role="alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span id="alertErrorMsg">Erro ao redefinir senha.</span>
        </div>

        <div id="resetFormWrap">
            <form id="resetForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label class="form-label" for="novaSenha">Nova Senha</label>
                    <div class="input-group">
                        <input type="password" id="novaSenha" name="senha" class="form-control" placeholder="Mínimo 8 caracteres" autocomplete="new-password" required>
                        <button type="button" class="input-toggle" id="toggleNova" aria-label="Mostrar senha">
                            <i class="fa-regular fa-eye" id="toggleNovaIcon"></i>
                        </button>
                    </div>
                    <div class="pw-strength-wrap">
                        <div class="pw-strength-bar"><div class="pw-strength-fill" id="pwFill"></div></div>
                        <span class="pw-strength-label" id="pwLabel">Informe uma senha</span>
                    </div>
                    <span class="invalid-feedback">A senha deve ter pelo menos 8 caracteres.</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirmarSenha">Confirmar Nova Senha</label>
                    <div class="input-group">
                        <input type="password" id="confirmarSenha" name="confirmar_senha" class="form-control" placeholder="Repita a senha" autocomplete="new-password" required>
                        <button type="button" class="input-toggle" id="toggleConfirmar" aria-label="Mostrar senha">
                            <i class="fa-regular fa-eye" id="toggleConfirmarIcon"></i>
                        </button>
                    </div>
                    <span class="invalid-feedback">As senhas não conferem.</span>
                </div>

                <button type="submit" class="btn-submit" id="submitResetBtn">
                    <div class="spinner"></div>
                    <span class="btn-text"><i class="fa-solid fa-key"></i>&nbsp;Salvar Nova Senha</span>
                </button>
            </form>
        </div>

        <div class="success-state" id="resetSuccess">
            <div class="success-icon"><i class="fa-solid fa-circle-check"></i></div>
            <h2>Senha Redefinida!</h2>
            <p>Sua senha foi alterada com sucesso. Você já pode entrar com a nova senha.</p>
            <a href="login.php" class="btn-submit" style="text-decoration:none;">
                <i class="fa-solid fa-right-to-bracket"></i>&nbsp;Entrar Agora
            </a>
        </div>

        <?php else: ?>
        <!-- ── Request recovery form (no token) ──────── -->
        <div class="rec-icon-wrap"><i class="fa-solid fa-envelope-circle-check"></i></div>
        <h1>Recuperar Senha</h1>
        <p class="rec-subtitle">Informe o e-mail da sua conta e enviaremos um link para redefinir sua senha.</p>

        <div class="alert-box alert-error" id="alertError" role="alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span id="alertErrorMsg">E-mail não encontrado.</span>
        </div>

        <div id="recoveryFormWrap">
            <form id="recoveryForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label class="form-label" for="emailRecovery">E-mail da conta</label>
                    <input type="email" id="emailRecovery" name="email" class="form-control" placeholder="seu@email.com" autocomplete="email" required>
                    <span class="invalid-feedback">Informe um e-mail válido.</span>
                </div>

                <button type="submit" class="btn-submit" id="submitRecoveryBtn">
                    <div class="spinner"></div>
                    <span class="btn-text"><i class="fa-solid fa-paper-plane"></i>&nbsp;Enviar Link de Recuperação</span>
                </button>
            </form>
        </div>

        <div class="success-state" id="recoverySuccess">
            <div class="success-icon"><i class="fa-solid fa-envelope-circle-check"></i></div>
            <h2>E-mail Enviado!</h2>
            <p>Enviamos as instruções para redefinir sua senha. Verifique sua caixa de entrada e a pasta de spam.</p>
            <a href="login.php" class="btn-submit" style="text-decoration:none;">
                <i class="fa-solid fa-right-to-bracket"></i>&nbsp;Voltar ao Login
            </a>
        </div>
        <?php endif; ?>

    </div>

    <a href="login.php" class="rec-back-link">
        <i class="fa-solid fa-arrow-left"></i> Voltar ao login
    </a>

</div>

<script>
(function () {
    function setLoading(btn, loading) {
        btn.disabled = loading;
        btn.classList.toggle('loading', loading);
    }

    function showAlert(type, msg) {
        var box = document.getElementById('alertError');
        if (type === 'error') {
            document.getElementById('alertErrorMsg').textContent = msg;
            box.className = 'alert-box alert-error show';
        } else {
            box.classList.remove('show');
        }
    }

    function markInvalid(el) {
        el.classList.add('is-invalid');
        el.addEventListener('input', function () { el.classList.remove('is-invalid'); }, { once: true });
    }

    <?php if ($hasToken): ?>
    // ── Password strength ────────────────────────────────────
    document.getElementById('novaSenha').addEventListener('input', function () {
        var pw = this.value;
        var score = 0;
        if (pw.length >= 8) score++;
        if (/[A-Z]/.test(pw)) score++;
        if (/[0-9]/.test(pw)) score++;
        if (/[^A-Za-z0-9]/.test(pw)) score++;
        var fill = document.getElementById('pwFill');
        var label = document.getElementById('pwLabel');
        fill.style.width = ((score / 4) * 100) + '%';
        var levels = [
            { c: '#dc2626', t: 'Muito fraca' }, { c: '#d97706', t: 'Fraca' },
            { c: '#ca8a04', t: 'Média' }, { c: '#16a34a', t: 'Forte' }, { c: '#15803d', t: 'Muito forte' }
        ];
        var lvl = levels[score];
        fill.style.background = lvl.c;
        label.style.color = lvl.c;
        label.textContent = pw.length === 0 ? 'Informe uma senha' : lvl.t;
    });

    // ── Toggles ──────────────────────────────────────────────
    function setupToggle(btnId, iconId, inputId) {
        document.getElementById(btnId).addEventListener('click', function () {
            var input = document.getElementById(inputId);
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            document.getElementById(iconId).className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
        });
    }
    setupToggle('toggleNova', 'toggleNovaIcon', 'novaSenha');
    setupToggle('toggleConfirmar', 'toggleConfirmarIcon', 'confirmarSenha');

    // ── Reset form submit ─────────────────────────────────────
    document.getElementById('resetForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var senha = document.getElementById('novaSenha');
        var confirmar = document.getElementById('confirmarSenha');
        var valid = true;
        if (senha.value.length < 8) { markInvalid(senha); valid = false; }
        if (confirmar.value !== senha.value || !confirmar.value) { markInvalid(confirmar); valid = false; }
        if (!valid) return;

        var btn = document.getElementById('submitResetBtn');
        setLoading(btn, true);

        fetch('actions/redefinir_senha.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                document.getElementById('resetFormWrap').style.display = 'none';
                document.getElementById('resetSuccess').style.display = 'block';
                showAlert('clear', '');
            } else {
                setLoading(btn, false);
                showAlert('error', data.message || 'Token inválido ou expirado.');
            }
        })
        .catch(function () {
            setLoading(btn, false);
            showAlert('error', 'Erro de conexão. Tente novamente.');
        });
    });

    <?php else: ?>
    // ── Recovery form submit ──────────────────────────────────
    document.getElementById('recoveryForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var emailInput = document.getElementById('emailRecovery');
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value.trim())) {
            markInvalid(emailInput);
            return;
        }

        var btn = document.getElementById('submitRecoveryBtn');
        setLoading(btn, true);

        fetch('actions/recuperar_senha.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                document.getElementById('recoveryFormWrap').style.display = 'none';
                document.getElementById('recoverySuccess').style.display = 'block';
                showAlert('clear', '');
            } else {
                setLoading(btn, false);
                showAlert('error', data.message || 'E-mail não encontrado em nossa base.');
            }
        })
        .catch(function () {
            setLoading(btn, false);
            showAlert('error', 'Erro de conexão. Tente novamente.');
        });
    });
    <?php endif; ?>
}());
</script>
</body>
</html>
