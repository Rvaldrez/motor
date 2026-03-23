<?php
require_once __DIR__ . '/includes/config.php';

if (!empty($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . '/painel.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$emailParam = isset($_GET['email']) ? trim($_GET['email']) : '';
$emailSafe = htmlspecialchars($emailParam, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>MotorGo – Confirmar E-mail</title>
    <link rel="icon" type="image/png" href="imagens/logo_motorgo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/main.css">
    <style>
        body { background: #f5f6fa; }
        .conf-wrap {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.25rem;
        }
        .conf-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2rem;
        }
        .conf-logo img { height: 42px; }

        .conf-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 460px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.05);
            text-align: center;
        }

        .conf-icon-wrap {
            width: 72px;
            height: 72px;
            background: rgba(178,34,34,0.08);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        .conf-icon-wrap i { font-size: 2rem; color: #B22222; }

        .conf-card h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1a1a1a;
            letter-spacing: -0.03em;
            margin-bottom: 0.5rem;
        }
        .conf-email-display {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #f3f4f6;
            border-radius: 8px;
            padding: 0.4rem 0.875rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.75rem;
        }
        .conf-subtitle {
            font-size: 0.9rem;
            color: #6b7280;
            line-height: 1.65;
            margin-bottom: 2rem;
        }

        /* 6-digit boxes */
        .code-input-row {
            display: flex;
            gap: 0.625rem;
            justify-content: center;
            margin-bottom: 1.75rem;
        }
        .code-digit {
            width: 52px;
            height: 60px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1.5rem;
            font-weight: 800;
            text-align: center;
            color: #1a1a1a;
            background: #fff;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            caret-color: #B22222;
            -moz-appearance: textfield;
        }
        .code-digit::-webkit-outer-spin-button,
        .code-digit::-webkit-inner-spin-button { -webkit-appearance: none; }
        .code-digit:focus {
            border-color: #B22222;
            box-shadow: 0 0 0 3px rgba(178,34,34,0.15);
        }
        .code-digit.filled { border-color: #B22222; background: #fff9f9; }
        .code-digit.is-invalid { border-color: #dc2626; box-shadow: 0 0 0 3px rgba(220,38,38,0.12); }

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
            margin-bottom: 1.25rem;
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
            font-weight: 500; margin-bottom: 1.25rem; align-items: flex-start; gap: 0.625rem; text-align: left;
        }
        .alert-box.show { display: flex; }
        .alert-box.alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-box.alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }

        .resend-wrap {
            font-size: 0.875rem;
            color: #6b7280;
        }
        .btn-resend {
            background: none;
            border: none;
            color: #B22222;
            font-weight: 700;
            cursor: pointer;
            font-size: 0.875rem;
            padding: 0;
            transition: color 0.2s;
        }
        .btn-resend:hover:not(:disabled) { color: #8B1A1A; text-decoration: underline; }
        .btn-resend:disabled { color: #9ca3af; cursor: not-allowed; text-decoration: none; }
        .countdown { font-size: 0.8125rem; color: #9ca3af; margin-left: 0.25rem; }

        .success-state { display: none; }
        .success-icon {
            width: 80px; height: 80px; background: #f0fdf4; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem;
        }
        .success-icon i { font-size: 2.25rem; color: #16a34a; }

        @media (max-width: 460px) {
            .code-digit { width: 44px; height: 54px; font-size: 1.25rem; }
            .code-input-row { gap: 0.375rem; }
        }
    </style>
</head>
<body>
<div class="conf-wrap">

    <div class="conf-logo">
        <a href="index.php"><img src="imagens/logo_motorgo.png" alt="MotorGo"></a>
    </div>

    <div class="conf-card">

        <!-- Verification state -->
        <div id="verifyState">
            <div class="conf-icon-wrap"><i class="fa-solid fa-envelope-open-text"></i></div>
            <h1>Confirme seu E-mail</h1>

            <?php if ($emailSafe): ?>
            <div class="conf-email-display">
                <i class="fa-solid fa-envelope" style="color:#B22222;font-size:0.8rem;"></i>
                <?= $emailSafe ?>
            </div>
            <?php endif; ?>

            <p class="conf-subtitle">
                Enviamos um código de 6 dígitos para o e-mail informado. Digite o código abaixo para confirmar sua conta.
            </p>

            <div class="alert-box alert-error" id="alertError" role="alert">
                <i class="fa-solid fa-circle-exclamation" style="flex-shrink:0;margin-top:1px;"></i>
                <span id="alertErrorMsg">Código inválido ou expirado.</span>
            </div>
            <div class="alert-box alert-success" id="alertSuccess" role="alert">
                <i class="fa-solid fa-circle-check" style="flex-shrink:0;margin-top:1px;"></i>
                <span id="alertSuccessMsg">Código reenviado com sucesso!</span>
            </div>

            <form id="confirmForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="email" id="emailHidden" value="<?= $emailSafe ?>">

                <div class="code-input-row" id="codeInputRow">
                    <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" class="code-digit" id="d0" autocomplete="one-time-code" aria-label="Dígito 1">
                    <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" class="code-digit" id="d1" aria-label="Dígito 2">
                    <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" class="code-digit" id="d2" aria-label="Dígito 3">
                    <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" class="code-digit" id="d3" aria-label="Dígito 4">
                    <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" class="code-digit" id="d4" aria-label="Dígito 5">
                    <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" class="code-digit" id="d5" aria-label="Dígito 6">
                </div>
                <input type="hidden" name="codigo" id="codigoHidden">

                <button type="submit" class="btn-submit" id="submitBtn">
                    <div class="spinner"></div>
                    <span class="btn-text"><i class="fa-solid fa-circle-check"></i>&nbsp;Confirmar Código</span>
                </button>
            </form>

            <div class="resend-wrap">
                Não recebeu o código?
                <button type="button" class="btn-resend" id="btnResend">Reenviar código</button>
                <span class="countdown" id="countdown"></span>
            </div>
        </div>

        <!-- Success state -->
        <div class="success-state" id="successState">
            <div class="success-icon"><i class="fa-solid fa-circle-check"></i></div>
            <h1 style="margin-bottom:0.5rem;">E-mail Confirmado!</h1>
            <p class="conf-subtitle" style="margin-bottom:1.75rem;">
                Sua conta foi ativada com sucesso. Bem-vindo à MotorGo!
            </p>
            <a href="painel.php" class="btn-submit" style="text-decoration:none;">
                <i class="fa-solid fa-gauge-high"></i>&nbsp;Ir ao Painel
            </a>
        </div>

    </div>

</div>

<script>
(function () {
    var digits = [
        document.getElementById('d0'),
        document.getElementById('d1'),
        document.getElementById('d2'),
        document.getElementById('d3'),
        document.getElementById('d4'),
        document.getElementById('d5'),
    ];
    var codigoHidden = document.getElementById('codigoHidden');
    var RESEND_COOLDOWN = 60;
    var countdownTimer = null;

    // ── Digit input handling ──────────────────────────────────
    digits.forEach(function (input, idx) {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace') {
                if (input.value === '' && idx > 0) {
                    digits[idx - 1].value = '';
                    digits[idx - 1].classList.remove('filled');
                    digits[idx - 1].focus();
                } else {
                    input.value = '';
                    input.classList.remove('filled');
                }
                e.preventDefault();
                syncHidden();
            } else if (e.key === 'ArrowLeft' && idx > 0) {
                digits[idx - 1].focus();
                e.preventDefault();
            } else if (e.key === 'ArrowRight' && idx < 5) {
                digits[idx + 1].focus();
                e.preventDefault();
            }
        });

        input.addEventListener('input', function (e) {
            var val = input.value.replace(/\D/g, '');
            input.value = val ? val[val.length - 1] : '';
            input.classList.toggle('filled', input.value !== '');
            input.classList.remove('is-invalid');
            if (input.value && idx < 5) {
                digits[idx + 1].focus();
            }
            syncHidden();
        });

        // Handle paste
        input.addEventListener('paste', function (e) {
            e.preventDefault();
            var pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
            if (!pasted) return;
            var start = idx;
            for (var i = 0; i < pasted.length && (start + i) < 6; i++) {
                digits[start + i].value = pasted[i];
                digits[start + i].classList.add('filled');
                digits[start + i].classList.remove('is-invalid');
            }
            var nextFocus = Math.min(start + pasted.length, 5);
            digits[nextFocus].focus();
            syncHidden();
        });
    });

    function syncHidden() {
        codigoHidden.value = digits.map(function (d) { return d.value; }).join('');
    }

    function getCode() {
        return digits.map(function (d) { return d.value; }).join('');
    }

    function clearDigits() {
        digits.forEach(function (d) { d.value = ''; d.classList.remove('filled', 'is-invalid'); });
        digits[0].focus();
        syncHidden();
    }

    function markDigitsInvalid() {
        digits.forEach(function (d) { d.classList.add('is-invalid'); });
    }

    // ── Alerts ────────────────────────────────────────────────
    function showAlert(type, msg) {
        document.getElementById('alertError').classList.remove('show');
        document.getElementById('alertSuccess').classList.remove('show');
        if (type === 'error') {
            document.getElementById('alertErrorMsg').textContent = msg;
            document.getElementById('alertError').classList.add('show');
        } else if (type === 'success') {
            document.getElementById('alertSuccessMsg').textContent = msg;
            document.getElementById('alertSuccess').classList.add('show');
        }
    }

    // ── Form submit ───────────────────────────────────────────
    document.getElementById('confirmForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var code = getCode();
        if (code.length !== 6) {
            markDigitsInvalid();
            showAlert('error', 'Digite o código de 6 dígitos completo.');
            return;
        }

        var btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.classList.add('loading');

        var formData = new FormData(this);
        formData.set('codigo', code);

        fetch('actions/confirmar_email.php', {
            method: 'POST',
            body: formData
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                document.getElementById('verifyState').style.display = 'none';
                document.getElementById('successState').style.display = 'block';
                setTimeout(function () {
                    window.location.href = data.redirect || 'painel.php';
                }, 2000);
            } else {
                btn.disabled = false;
                btn.classList.remove('loading');
                showAlert('error', data.message || 'Código inválido ou expirado.');
                markDigitsInvalid();
                clearDigits();
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.classList.remove('loading');
            showAlert('error', 'Erro de conexão. Tente novamente.');
        });
    });

    // ── Resend code ───────────────────────────────────────────
    function startCountdown(seconds) {
        var btn = document.getElementById('btnResend');
        var countdownEl = document.getElementById('countdown');
        btn.disabled = true;
        var remaining = seconds;

        function tick() {
            countdownEl.textContent = '(' + remaining + 's)';
            if (remaining <= 0) {
                clearInterval(countdownTimer);
                btn.disabled = false;
                countdownEl.textContent = '';
            }
            remaining--;
        }
        tick();
        countdownTimer = setInterval(tick, 1000);
    }

    document.getElementById('btnResend').addEventListener('click', function () {
        var email = document.getElementById('emailHidden').value;
        if (!email) {
            showAlert('error', 'E-mail não identificado. Tente o cadastro novamente.');
            return;
        }

        var btn = this;
        btn.disabled = true;

        var formData = new FormData();
        formData.append('email', email);
        formData.append('csrf_token', '<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>');

        fetch('actions/reenviar_codigo.php', {
            method: 'POST',
            body: formData
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                showAlert('success', 'Código reenviado! Verifique sua caixa de entrada.');
                startCountdown(RESEND_COOLDOWN);
            } else {
                btn.disabled = false;
                showAlert('error', data.message || 'Não foi possível reenviar o código.');
            }
        })
        .catch(function () {
            btn.disabled = false;
            showAlert('error', 'Erro de conexão. Tente novamente.');
        });
    });

    // Focus first digit on load
    digits[0].focus();
}());
</script>
</body>
</html>
