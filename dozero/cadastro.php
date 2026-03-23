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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>MotorGo – Criar Conta</title>
    <link rel="icon" type="image/png" href="imagens/logo_motorgo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/main.css">
    <style>
        body { background: #f5f6fa; }

        .cad-wrap {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 2.5rem 1.25rem 4rem;
        }

        /* Header */
        .cad-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2.5rem;
        }
        .cad-header a {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            margin-bottom: 0.5rem;
        }
        .cad-header img { height: 40px; }
        .cad-header p {
            font-size: 0.9rem;
            color: #6b7280;
            margin: 0;
        }

        /* Progress */
        .progress-bar-wrap {
            width: 100%;
            max-width: 560px;
            margin-bottom: 2rem;
        }
        .progress-steps {
            display: flex;
            align-items: center;
            position: relative;
        }
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 24px;
            right: 24px;
            height: 2px;
            background: #e5e7eb;
            z-index: 0;
        }
        .progress-fill {
            position: absolute;
            top: 20px;
            left: 24px;
            height: 2px;
            background: #B22222;
            z-index: 0;
            transition: width 0.4s ease;
        }
        .step-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            z-index: 1;
        }
        .step-circle {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #e5e7eb;
            color: #9ca3af;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            border: 2px solid #e5e7eb;
        }
        .step-item.active .step-circle {
            background: #B22222;
            border-color: #B22222;
            color: #fff;
            box-shadow: 0 4px 14px rgba(178,34,34,0.35);
        }
        .step-item.done .step-circle {
            background: #16a34a;
            border-color: #16a34a;
            color: #fff;
        }
        .step-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #9ca3af;
            white-space: nowrap;
        }
        .step-item.active .step-label { color: #B22222; }
        .step-item.done .step-label { color: #16a34a; }

        /* Form card */
        .cad-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 560px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.05);
        }
        .cad-step-content { display: none; }
        .cad-step-content.active { display: block; }
        .step-title {
            font-size: 1.375rem;
            font-weight: 800;
            color: #1a1a1a;
            letter-spacing: -0.03em;
            margin-bottom: 0.25rem;
        }
        .step-subtitle {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 2rem;
        }

        /* Form */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .form-group { margin-bottom: 1.25rem; }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.45rem;
        }
        .form-label .req { color: #B22222; }
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
        .form-control.is-invalid { border-color: #dc2626 !important; box-shadow: 0 0 0 3px rgba(220,38,38,0.1) !important; }
        .form-control.is-valid { border-color: #16a34a; }
        .invalid-feedback {
            font-size: 0.8125rem;
            color: #dc2626;
            margin-top: 0.35rem;
            display: none;
        }
        .form-control.is-invalid ~ .invalid-feedback { display: block; }

        /* Type selection (radio cards) */
        .tipo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .tipo-card {
            position: relative;
        }
        .tipo-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .tipo-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            padding: 1.5rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
            background: #fafafa;
        }
        .tipo-label:hover {
            border-color: #B22222;
            background: #fff9f9;
        }
        .tipo-card input:checked + .tipo-label {
            border-color: #B22222;
            background: #fff9f9;
            box-shadow: 0 0 0 3px rgba(178,34,34,0.1);
        }
        .tipo-icon {
            width: 52px;
            height: 52px;
            background: rgba(178,34,34,0.08);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .tipo-icon i { font-size: 1.375rem; color: #B22222; }
        .tipo-card input:checked + .tipo-label .tipo-icon {
            background: #B22222;
        }
        .tipo-card input:checked + .tipo-label .tipo-icon i { color: #fff; }
        .tipo-name { font-size: 1rem; font-weight: 700; color: #1a1a1a; }
        .tipo-desc { font-size: 0.8rem; color: #6b7280; line-height: 1.4; }

        /* CEP button */
        .cep-group {
            display: flex;
            gap: 0.5rem;
        }
        .cep-group .form-control { flex: 1; }
        .btn-cep {
            padding: 0.75rem 1rem;
            background: #f3f4f6;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #374151;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        .btn-cep:hover:not(:disabled) { background: #e5e7eb; }
        .btn-cep:disabled { opacity: 0.5; cursor: not-allowed; }

        /* Password strength */
        .pw-strength-wrap { margin-top: 0.5rem; }
        .pw-strength-bar {
            height: 4px;
            background: #e5e7eb;
            border-radius: 9999px;
            overflow: hidden;
            margin-bottom: 0.35rem;
        }
        .pw-strength-fill {
            height: 100%;
            width: 0;
            border-radius: 9999px;
            transition: width 0.3s ease, background 0.3s ease;
        }
        .pw-strength-label {
            font-size: 0.75rem;
            color: #9ca3af;
            font-weight: 500;
        }
        /* Visibility toggle */
        .input-group { position: relative; }
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
            font-size: 0.9375rem;
        }
        .input-toggle:hover { color: #6b7280; }

        /* Terms */
        .form-check {
            display: flex;
            align-items: flex-start;
            gap: 0.625rem;
            margin-bottom: 1.5rem;
        }
        .form-check input[type="checkbox"] {
            width: 17px;
            height: 17px;
            accent-color: #B22222;
            cursor: pointer;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .form-check label {
            font-size: 0.875rem;
            color: #6b7280;
            cursor: pointer;
            line-height: 1.5;
        }
        .form-check label a { color: #B22222; font-weight: 600; }

        /* Buttons row */
        .btn-row {
            display: flex;
            gap: 0.75rem;
        }
        .btn-back {
            padding: 0.875rem 1.5rem;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            background: #fff;
            color: #374151;
            font-weight: 600;
            font-size: 0.9375rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .btn-back:hover { background: #f3f4f6; }
        .btn-next {
            flex: 1;
            padding: 0.875rem 1.5rem;
            background: #B22222;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s;
            box-shadow: 0 4px 14px rgba(178,34,34,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .btn-next:hover:not(:disabled) { background: #8B1A1A; transform: translateY(-1px); }
        .btn-next:disabled { opacity: 0.65; cursor: not-allowed; }
        .btn-next .spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.35);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }
        .btn-next.loading .spinner { display: block; }
        .btn-next.loading .btn-text { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Alert */
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
        .alert-box.alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-box.alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }

        /* Login link */
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.875rem;
            color: #6b7280;
        }
        .login-link a { color: #B22222; font-weight: 700; }

        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
            .cad-card { padding: 1.75rem 1.25rem; }
        }
    </style>
</head>
<body>
<div class="cad-wrap">

    <!-- Header -->
    <div class="cad-header">
        <a href="index.php">
            <img src="imagens/logo_motorgo.png" alt="MotorGo">
        </a>
        <p>Crie sua conta e comece a investir</p>
    </div>

    <!-- Progress -->
    <div class="progress-bar-wrap">
        <div class="progress-steps" id="progressSteps">
            <div class="progress-fill" id="progressFill" style="width:0%"></div>
            <div class="step-item active" id="stepIndicator1">
                <div class="step-circle"><i class="fa-solid fa-user" id="stepIcon1"></i></div>
                <span class="step-label">Dados Pessoais</span>
            </div>
            <div class="step-item" id="stepIndicator2">
                <div class="step-circle"><i class="fa-solid fa-map-location-dot" id="stepIcon2"></i></div>
                <span class="step-label">Endereço</span>
            </div>
            <div class="step-item" id="stepIndicator3">
                <div class="step-circle"><i class="fa-solid fa-key" id="stepIcon3"></i></div>
                <span class="step-label">Acesso</span>
            </div>
        </div>
    </div>

    <!-- Card -->
    <div class="cad-card">
        <div class="alert-box alert-error" id="alertError" role="alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span id="alertErrorMsg">Erro ao processar o cadastro.</span>
        </div>

        <form id="cadForm" novalidate autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <!-- ── Step 1: Dados Pessoais ────────────────── -->
            <div class="cad-step-content active" id="cadStep1">
                <div class="step-title">Dados Pessoais</div>
                <p class="step-subtitle">Preencha suas informações básicas para criar a conta.</p>

                <div class="form-group">
                    <label class="form-label" for="nome">Nome completo <span class="req">*</span></label>
                    <input type="text" id="nome" name="nome" class="form-control" placeholder="Seu nome completo" autocomplete="name">
                    <span class="invalid-feedback">Informe seu nome completo (mínimo 3 caracteres).</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">E-mail <span class="req">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="seu@email.com" autocomplete="email">
                    <span class="invalid-feedback">Informe um e-mail válido.</span>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="celular">Celular <span class="req">*</span></label>
                        <input type="tel" id="celular" name="celular" class="form-control" placeholder="(11) 99999-9999" maxlength="15" autocomplete="tel">
                        <span class="invalid-feedback">Informe um celular válido.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="cpf">CPF <span class="req">*</span></label>
                        <input type="text" id="cpf" name="cpf" class="form-control" placeholder="000.000.000-00" maxlength="14" autocomplete="off">
                        <span class="invalid-feedback">CPF inválido.</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Tipo de Conta <span class="req">*</span></label>
                    <div class="tipo-grid">
                        <div class="tipo-card">
                            <input type="radio" name="tipo" id="tipoVendedor" value="vendedor">
                            <label class="tipo-label" for="tipoVendedor">
                                <div class="tipo-icon"><i class="fa-solid fa-car-side"></i></div>
                                <span class="tipo-name">Vendedor</span>
                                <span class="tipo-desc">Cadastre veículos e receba propostas de investidores.</span>
                            </label>
                        </div>
                        <div class="tipo-card">
                            <input type="radio" name="tipo" id="tipoInvestidor" value="investidor">
                            <label class="tipo-label" for="tipoInvestidor">
                                <div class="tipo-icon"><i class="fa-solid fa-chart-line"></i></div>
                                <span class="tipo-name">Investidor</span>
                                <span class="tipo-desc">Explore veículos disponíveis e envie propostas de compra.</span>
                            </label>
                        </div>
                    </div>
                    <input type="hidden" id="tipoHidden" name="tipo_val">
                    <span class="invalid-feedback" id="tipoError" style="display:none;">Selecione o tipo de conta.</span>
                </div>

                <div class="btn-row">
                    <button type="button" class="btn-next" id="btnStep1">
                        <span class="btn-text">Continuar <i class="fa-solid fa-arrow-right"></i></span>
                    </button>
                </div>
            </div>

            <!-- ── Step 2: Endereço ──────────────────────── -->
            <div class="cad-step-content" id="cadStep2">
                <div class="step-title">Endereço</div>
                <p class="step-subtitle">Informe seu endereço. Use o botão para buscar pelo CEP.</p>

                <div class="form-group">
                    <label class="form-label" for="cep">CEP <span class="req">*</span></label>
                    <div class="cep-group">
                        <input type="text" id="cep" name="cep" class="form-control" placeholder="00000-000" maxlength="9">
                        <button type="button" class="btn-cep" id="btnBuscarCep">
                            <i class="fa-solid fa-magnifying-glass"></i> Buscar
                        </button>
                    </div>
                    <span class="invalid-feedback" id="cepError">CEP inválido ou não encontrado.</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="endereco">Rua / Logradouro <span class="req">*</span></label>
                    <input type="text" id="endereco" name="endereco" class="form-control" placeholder="Nome da rua" autocomplete="street-address">
                    <span class="invalid-feedback">Informe o endereço.</span>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="numero">Número <span class="req">*</span></label>
                        <input type="text" id="numero" name="numero" class="form-control" placeholder="123">
                        <span class="invalid-feedback">Informe o número.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="complemento">Complemento</label>
                        <input type="text" id="complemento" name="complemento" class="form-control" placeholder="Apto, bloco…">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="bairro">Bairro <span class="req">*</span></label>
                    <input type="text" id="bairro" name="bairro" class="form-control" placeholder="Nome do bairro">
                    <span class="invalid-feedback">Informe o bairro.</span>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="cidade">Cidade <span class="req">*</span></label>
                        <input type="text" id="cidade" name="cidade" class="form-control" placeholder="Sua cidade">
                        <span class="invalid-feedback">Informe a cidade.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="estado">Estado <span class="req">*</span></label>
                        <select id="estado" name="estado" class="form-control">
                            <option value="">Selecione</option>
                            <option value="AC">AC</option><option value="AL">AL</option><option value="AP">AP</option>
                            <option value="AM">AM</option><option value="BA">BA</option><option value="CE">CE</option>
                            <option value="DF">DF</option><option value="ES">ES</option><option value="GO">GO</option>
                            <option value="MA">MA</option><option value="MT">MT</option><option value="MS">MS</option>
                            <option value="MG">MG</option><option value="PA">PA</option><option value="PB">PB</option>
                            <option value="PR">PR</option><option value="PE">PE</option><option value="PI">PI</option>
                            <option value="RJ">RJ</option><option value="RN">RN</option><option value="RS">RS</option>
                            <option value="RO">RO</option><option value="RR">RR</option><option value="SC">SC</option>
                            <option value="SP">SP</option><option value="SE">SE</option><option value="TO">TO</option>
                        </select>
                        <span class="invalid-feedback">Selecione o estado.</span>
                    </div>
                </div>

                <div class="btn-row">
                    <button type="button" class="btn-back" id="btnBack2">
                        <i class="fa-solid fa-arrow-left"></i> Voltar
                    </button>
                    <button type="button" class="btn-next" id="btnStep2">
                        <span class="btn-text">Continuar <i class="fa-solid fa-arrow-right"></i></span>
                    </button>
                </div>
            </div>

            <!-- ── Step 3: Acesso ────────────────────────── -->
            <div class="cad-step-content" id="cadStep3">
                <div class="step-title">Dados de Acesso</div>
                <p class="step-subtitle">Crie uma senha segura para proteger sua conta.</p>

                <div class="form-group">
                    <label class="form-label" for="senha">Senha <span class="req">*</span></label>
                    <div class="input-group">
                        <input type="password" id="senha" name="senha" class="form-control" placeholder="Mínimo 8 caracteres" autocomplete="new-password">
                        <button type="button" class="input-toggle" id="toggleSenha" aria-label="Mostrar senha">
                            <i class="fa-regular fa-eye" id="toggleSenhaIcon"></i>
                        </button>
                    </div>
                    <div class="pw-strength-wrap">
                        <div class="pw-strength-bar">
                            <div class="pw-strength-fill" id="pwStrengthFill"></div>
                        </div>
                        <span class="pw-strength-label" id="pwStrengthLabel">Informe uma senha</span>
                    </div>
                    <span class="invalid-feedback">A senha deve ter pelo menos 8 caracteres.</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirmar_senha">Confirmar Senha <span class="req">*</span></label>
                    <div class="input-group">
                        <input type="password" id="confirmar_senha" name="confirmar_senha" class="form-control" placeholder="Repita a senha" autocomplete="new-password">
                        <button type="button" class="input-toggle" id="toggleConfirmar" aria-label="Mostrar senha">
                            <i class="fa-regular fa-eye" id="toggleConfirmarIcon"></i>
                        </button>
                    </div>
                    <span class="invalid-feedback">As senhas não conferem.</span>
                </div>

                <div class="form-check">
                    <input type="checkbox" id="termo_aceito" name="termo_aceito" value="1">
                    <label for="termo_aceito">
                        Li e aceito os <a href="#" target="_blank">Termos de Uso</a> e a
                        <a href="#" target="_blank">Política de Privacidade</a> da MotorGo.
                    </label>
                </div>
                <span class="invalid-feedback" id="termoError" style="display:none;margin-top:-1rem;margin-bottom:1rem;">Você deve aceitar os termos para continuar.</span>

                <div class="btn-row">
                    <button type="button" class="btn-back" id="btnBack3">
                        <i class="fa-solid fa-arrow-left"></i> Voltar
                    </button>
                    <button type="submit" class="btn-next" id="btnSubmit">
                        <div class="spinner"></div>
                        <span class="btn-text"><i class="fa-solid fa-check"></i> Criar Conta</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <p class="login-link">Já tem conta? <a href="login.php">Entrar</a></p>

</div>

<script>
(function () {
    var currentStep = 1;
    var totalSteps = 3;

    // ── Step navigation ──────────────────────────────────────
    function goToStep(n) {
        document.getElementById('cadStep' + currentStep).classList.remove('active');
        document.getElementById('cadStep' + n).classList.add('active');

        // Update progress indicators
        for (var i = 1; i <= totalSteps; i++) {
            var indicator = document.getElementById('stepIndicator' + i);
            var circle = indicator.querySelector('.step-circle');
            var icon = document.getElementById('stepIcon' + i);
            indicator.classList.remove('active', 'done');
            if (i < n) {
                indicator.classList.add('done');
                circle.innerHTML = '<i class="fa-solid fa-check"></i>';
            } else if (i === n) {
                indicator.classList.add('active');
                // Restore icon
                var icons = ['fa-user', 'fa-map-location-dot', 'fa-key'];
                circle.innerHTML = '<i class="fa-solid ' + icons[i-1] + '"></i>';
            } else {
                var icons = ['fa-user', 'fa-map-location-dot', 'fa-key'];
                circle.innerHTML = '<i class="fa-solid ' + icons[i-1] + '"></i>';
            }
        }

        // Update fill line
        var fillPct = ((n - 1) / (totalSteps - 1)) * 100;
        document.getElementById('progressFill').style.width = fillPct + '%';

        currentStep = n;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ── Mark invalid field ────────────────────────────────────
    function markInvalid(el, show) {
        if (show === false) {
            el.classList.remove('is-invalid');
        } else {
            el.classList.add('is-invalid');
            el.addEventListener('input', function () { el.classList.remove('is-invalid'); }, { once: true });
            el.addEventListener('change', function () { el.classList.remove('is-invalid'); }, { once: true });
        }
    }

    // ── CPF validation ────────────────────────────────────────
    function validarCpf(cpf) {
        cpf = cpf.replace(/\D/g, '');
        if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
        var sum = 0;
        for (var i = 0; i < 9; i++) sum += parseInt(cpf[i]) * (10 - i);
        var r = sum % 11;
        var d1 = r < 2 ? 0 : 11 - r;
        if (parseInt(cpf[9]) !== d1) return false;
        sum = 0;
        for (var i = 0; i < 10; i++) sum += parseInt(cpf[i]) * (11 - i);
        r = sum % 11;
        var d2 = r < 2 ? 0 : 11 - r;
        return parseInt(cpf[10]) === d2;
    }

    // ── Masks ─────────────────────────────────────────────────
    document.getElementById('celular').addEventListener('input', function () {
        var v = this.value.replace(/\D/g, '').substring(0, 11);
        if (v.length > 6) {
            this.value = '(' + v.substring(0,2) + ') ' + v.substring(2,7) + '-' + v.substring(7);
        } else if (v.length > 2) {
            this.value = '(' + v.substring(0,2) + ') ' + v.substring(2);
        } else if (v.length > 0) {
            this.value = '(' + v;
        }
    });

    document.getElementById('cpf').addEventListener('input', function () {
        var v = this.value.replace(/\D/g, '').substring(0, 11);
        if (v.length > 9) {
            this.value = v.substring(0,3) + '.' + v.substring(3,6) + '.' + v.substring(6,9) + '-' + v.substring(9);
        } else if (v.length > 6) {
            this.value = v.substring(0,3) + '.' + v.substring(3,6) + '.' + v.substring(6);
        } else if (v.length > 3) {
            this.value = v.substring(0,3) + '.' + v.substring(3);
        } else {
            this.value = v;
        }
    });

    document.getElementById('cep').addEventListener('input', function () {
        var v = this.value.replace(/\D/g, '').substring(0, 8);
        this.value = v.length > 5 ? v.substring(0,5) + '-' + v.substring(5) : v;
    });

    // ── Step 1 validation ─────────────────────────────────────
    document.getElementById('btnStep1').addEventListener('click', function () {
        var nome = document.getElementById('nome');
        var email = document.getElementById('email');
        var celular = document.getElementById('celular');
        var cpf = document.getElementById('cpf');
        var tipoError = document.getElementById('tipoError');
        var valid = true;

        if (nome.value.trim().length < 3) { markInvalid(nome); valid = false; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) { markInvalid(email); valid = false; }
        var celDigits = celular.value.replace(/\D/g, '');
        if (celDigits.length < 10) { markInvalid(celular); valid = false; }
        if (!validarCpf(cpf.value)) { markInvalid(cpf); valid = false; }

        var tipoChecked = document.querySelector('input[name="tipo"]:checked');
        if (!tipoChecked) {
            tipoError.style.display = 'block';
            valid = false;
        } else {
            tipoError.style.display = 'none';
            document.getElementById('tipoHidden').value = tipoChecked.value;
        }

        if (valid) goToStep(2);
    });

    // ── CEP lookup ────────────────────────────────────────────
    document.getElementById('btnBuscarCep').addEventListener('click', function () {
        var cepVal = document.getElementById('cep').value.replace(/\D/g, '');
        var cepError = document.getElementById('cepError');
        if (cepVal.length !== 8) {
            document.getElementById('cep').classList.add('is-invalid');
            cepError.style.display = 'block';
            return;
        }
        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

        fetch('https://viacep.com.br/ws/' + cepVal + '/json/')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> Buscar';
                if (data.erro) {
                    document.getElementById('cep').classList.add('is-invalid');
                    cepError.style.display = 'block';
                    return;
                }
                document.getElementById('cep').classList.remove('is-invalid');
                cepError.style.display = 'none';
                document.getElementById('endereco').value = data.logradouro || '';
                document.getElementById('bairro').value = data.bairro || '';
                document.getElementById('cidade').value = data.localidade || '';
                var estadoSel = document.getElementById('estado');
                for (var i = 0; i < estadoSel.options.length; i++) {
                    if (estadoSel.options[i].value === data.uf) {
                        estadoSel.selectedIndex = i;
                        break;
                    }
                }
                document.getElementById('numero').focus();
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> Buscar';
                document.getElementById('cep').classList.add('is-invalid');
                cepError.style.display = 'block';
            });
    });

    // ── Step 2 validation ─────────────────────────────────────
    document.getElementById('btnBack2').addEventListener('click', function () { goToStep(1); });

    document.getElementById('btnStep2').addEventListener('click', function () {
        var fields = ['cep', 'endereco', 'numero', 'bairro', 'cidade', 'estado'];
        var valid = true;
        fields.forEach(function (id) {
            var el = document.getElementById(id);
            if (!el.value.trim()) { markInvalid(el); valid = false; }
        });
        var cepDigits = document.getElementById('cep').value.replace(/\D/g, '');
        if (cepDigits.length !== 8) { markInvalid(document.getElementById('cep')); valid = false; }
        if (valid) goToStep(3);
    });

    // ── Password strength ─────────────────────────────────────
    document.getElementById('senha').addEventListener('input', function () {
        var pw = this.value;
        var score = 0;
        if (pw.length >= 8) score++;
        if (/[A-Z]/.test(pw)) score++;
        if (/[0-9]/.test(pw)) score++;
        if (/[^A-Za-z0-9]/.test(pw)) score++;

        var fill = document.getElementById('pwStrengthFill');
        var label = document.getElementById('pwStrengthLabel');
        var pct = (score / 4) * 100;
        fill.style.width = pct + '%';
        var levels = [
            { color: '#dc2626', text: 'Muito fraca' },
            { color: '#d97706', text: 'Fraca' },
            { color: '#ca8a04', text: 'Média' },
            { color: '#16a34a', text: 'Forte' },
            { color: '#15803d', text: 'Muito forte' },
        ];
        var lvl = levels[score] || levels[0];
        fill.style.background = lvl.color;
        label.style.color = lvl.color;
        label.textContent = pw.length === 0 ? 'Informe uma senha' : lvl.text;
    });

    // ── Toggle password visibility ────────────────────────────
    function setupToggle(btnId, iconId, inputId) {
        document.getElementById(btnId).addEventListener('click', function () {
            var input = document.getElementById(inputId);
            var icon = document.getElementById(iconId);
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon.className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
        });
    }
    setupToggle('toggleSenha', 'toggleSenhaIcon', 'senha');
    setupToggle('toggleConfirmar', 'toggleConfirmarIcon', 'confirmar_senha');

    // ── Step 3 navigation back ────────────────────────────────
    document.getElementById('btnBack3').addEventListener('click', function () { goToStep(2); });

    // ── Form submit ───────────────────────────────────────────
    document.getElementById('cadForm').addEventListener('submit', function (e) {
        e.preventDefault();

        var senha = document.getElementById('senha');
        var confirmar = document.getElementById('confirmar_senha');
        var termo = document.getElementById('termo_aceito');
        var termoError = document.getElementById('termoError');
        var valid = true;

        if (senha.value.length < 8) { markInvalid(senha); valid = false; }
        if (confirmar.value !== senha.value || !confirmar.value) { markInvalid(confirmar); valid = false; }
        if (!termo.checked) {
            termoError.style.display = 'block';
            valid = false;
        } else {
            termoError.style.display = 'none';
        }
        if (!valid) return;

        var btn = document.getElementById('btnSubmit');
        btn.disabled = true;
        btn.classList.add('loading');

        // Merge tipo from radio
        var tipoChecked = document.querySelector('input[name="tipo"]:checked');
        if (tipoChecked) document.getElementById('tipoHidden').value = tipoChecked.value;

        var formData = new FormData(this);

        fetch('actions/cadastro.php', {
            method: 'POST',
            body: formData
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                var email = encodeURIComponent(document.getElementById('email').value.trim());
                window.location.href = 'confirmar_email.php?email=' + email;
            } else {
                btn.disabled = false;
                btn.classList.remove('loading');
                var errBox = document.getElementById('alertError');
                document.getElementById('alertErrorMsg').textContent = data.message || 'Erro ao cadastrar. Tente novamente.';
                errBox.classList.add('show');
                errBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.classList.remove('loading');
            document.getElementById('alertErrorMsg').textContent = 'Erro de conexão. Tente novamente.';
            document.getElementById('alertError').classList.add('show');
        });
    });

}());
</script>
</body>
</html>
