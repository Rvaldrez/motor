<?php
/**
 * Seção: Meus Dados
 * $conn, $user, $tipo, $csrfToken available from painel.php
 */
$userId = (int) $user['id'];

// Fetch full user data
$stmt = $conn->prepare("
    SELECT id, nome, email, celular, cpf, cep, endereco, numero, complemento, bairro,
           cidade, estado, tipo, status_confirmacao, status_cadastro, data_cadastro
    FROM usuarios WHERE id = ?
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

$tipoBadgeColors = [
    'vendedor'       => ['#dbeafe','#1e40af'],
    'investidor'     => ['#d1fae5','#065f46'],
    'administrador'  => ['#fce7f3','#9d174d'],
];
$tc = $tipoBadgeColors[$userData['tipo'] ?? ''] ?? ['#f3f4f6','#6b7280'];
?>

<div class="section-page" style="max-width:800px;">
    <div class="section-page-header">
        <div>
            <h2 class="section-page-title"><i class="fa-solid fa-user-pen"></i> Meus Dados</h2>
            <p class="section-page-subtitle">Gerencie suas informações pessoais e de conta.</p>
        </div>
    </div>

    <!-- Profile card -->
    <div class="dados-profile-card">
        <div class="profile-avatar">
            <?= strtoupper(mb_substr($userData['nome'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="profile-info">
            <h3><?= htmlspecialchars($userData['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?></h3>
            <p><?= htmlspecialchars($userData['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.5rem;">
                <span style="background:<?= $tc[0] ?>;color:<?= $tc[1] ?>;padding:2px 12px;border-radius:9999px;font-size:0.75rem;font-weight:700;">
                    <?= htmlspecialchars(ucfirst($userData['tipo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span style="background:<?= $userData['status_confirmacao'] === 'confirmado' ? '#d1fae5' : '#fef3c7' ?>;color:<?= $userData['status_confirmacao'] === 'confirmado' ? '#065f46' : '#92400e' ?>;padding:2px 12px;border-radius:9999px;font-size:0.75rem;font-weight:700;">
                    <?= $userData['status_confirmacao'] === 'confirmado' ? 'E-mail Confirmado' : 'E-mail Pendente' ?>
                </span>
            </div>
        </div>
        <div class="profile-meta">
            <span style="font-size:0.8125rem;color:var(--color-text-muted);">
                <i class="fa-solid fa-calendar-days"></i>
                Membro desde <?= !empty($userData['data_cadastro']) ? date('M/Y', strtotime($userData['data_cadastro'])) : '-' ?>
            </span>
        </div>
    </div>

    <!-- Alerts -->
    <div class="alert-box alert-error" id="dadosAlertError" style="display:none;">
        <i class="fa-solid fa-circle-exclamation"></i>
        <span id="dadosAlertErrorMsg">Erro ao salvar dados.</span>
    </div>
    <div class="alert-box alert-success" id="dadosAlertSuccess" style="display:none;">
        <i class="fa-solid fa-circle-check"></i>
        <span id="dadosAlertSuccessMsg">Dados atualizados com sucesso!</span>
    </div>

    <!-- Edit form -->
    <div class="dados-section-card">
        <div class="dados-section-header">
            <h4><i class="fa-solid fa-id-card"></i> Informações Pessoais</h4>
        </div>
        <form id="formDados" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="acao" value="atualizar_dados">

            <div class="dados-form-grid">
                <div class="form-group">
                    <label class="form-label">Nome Completo <span class="req">*</span></label>
                    <input type="text" name="nome" class="form-control"
                           value="<?= htmlspecialchars($userData['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">E-mail</label>
                    <input type="email" class="form-control"
                           value="<?= htmlspecialchars($userData['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           disabled style="background:#f9fafb;cursor:not-allowed;">
                    <small style="color:var(--color-text-muted);font-size:0.8rem;">O e-mail não pode ser alterado.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Celular <span class="req">*</span></label>
                    <input type="tel" name="celular" id="dadosCelular" class="form-control"
                           value="<?= htmlspecialchars($userData['celular'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="(11) 99999-9999" maxlength="15" required>
                </div>
                <div class="form-group">
                    <label class="form-label">CPF</label>
                    <input type="text" class="form-control"
                           value="<?= htmlspecialchars($userData['cpf'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           disabled style="background:#f9fafb;cursor:not-allowed;">
                </div>
            </div>

            <div class="dados-section-header" style="margin-top:1.5rem;">
                <h4><i class="fa-solid fa-map-location-dot"></i> Endereço</h4>
            </div>

            <div class="dados-form-grid">
                <div class="form-group">
                    <label class="form-label">CEP</label>
                    <input type="text" name="cep" id="dadosCep" class="form-control"
                           value="<?= htmlspecialchars($userData['cep'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="00000-000" maxlength="9">
                </div>
                <div class="form-group">
                    <label class="form-label">Endereço</label>
                    <input type="text" name="endereco" class="form-control"
                           value="<?= htmlspecialchars($userData['endereco'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Rua, Avenida…">
                </div>
                <div class="form-group">
                    <label class="form-label">Número</label>
                    <input type="text" name="numero" class="form-control"
                           value="<?= htmlspecialchars($userData['numero'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="123">
                </div>
                <div class="form-group">
                    <label class="form-label">Complemento</label>
                    <input type="text" name="complemento" class="form-control"
                           value="<?= htmlspecialchars($userData['complemento'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Apto, bloco…">
                </div>
                <div class="form-group">
                    <label class="form-label">Bairro</label>
                    <input type="text" name="bairro" class="form-control"
                           value="<?= htmlspecialchars($userData['bairro'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Seu bairro">
                </div>
                <div class="form-group">
                    <label class="form-label">Cidade</label>
                    <input type="text" name="cidade" class="form-control"
                           value="<?= htmlspecialchars($userData['cidade'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Sua cidade">
                </div>
                <div class="form-group">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-control">
                        <option value="">Selecione</option>
                        <?php
                        $estados = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                        foreach ($estados as $uf):
                        ?>
                        <option value="<?= $uf ?>" <?= ($userData['estado'] ?? '') === $uf ? 'selected' : '' ?>>
                            <?= $uf ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="margin-top:1.5rem;">
                <button type="submit" class="btn-modal-submit" id="btnSalvarDados">
                    <span class="btn-text"><i class="fa-solid fa-floppy-disk"></i> Salvar Alterações</span>
                    <div class="spinner"></div>
                </button>
            </div>
        </form>
    </div>

    <!-- Password change -->
    <div class="dados-section-card" style="margin-top:1.5rem;">
        <div class="dados-section-header">
            <h4><i class="fa-solid fa-lock"></i> Alterar Senha</h4>
        </div>

        <div class="alert-box alert-error" id="senhaAlertError" style="display:none;">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span id="senhaAlertErrorMsg"></span>
        </div>
        <div class="alert-box alert-success" id="senhaAlertSuccess" style="display:none;">
            <i class="fa-solid fa-circle-check"></i>
            <span>Senha alterada com sucesso!</span>
        </div>

        <form id="formSenha" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="acao" value="alterar_senha">

            <div class="dados-form-grid">
                <div class="form-group" style="grid-column:1/-1">
                    <label class="form-label">Senha Atual <span class="req">*</span></label>
                    <div style="position:relative;">
                        <input type="password" name="senha_atual" id="dadosSenhaAtual" class="form-control"
                               placeholder="••••••••" autocomplete="current-password" required
                               style="padding-right:2.75rem;">
                        <button type="button" class="input-toggle" onclick="toggleInput('dadosSenhaAtual',this)" style="position:absolute;right:0.875rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#9ca3af;cursor:pointer;">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Nova Senha <span class="req">*</span></label>
                    <div style="position:relative;">
                        <input type="password" name="nova_senha" id="dadosNovaSenha" class="form-control"
                               placeholder="Mínimo 8 caracteres" autocomplete="new-password" required
                               style="padding-right:2.75rem;">
                        <button type="button" class="input-toggle" onclick="toggleInput('dadosNovaSenha',this)" style="position:absolute;right:0.875rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#9ca3af;cursor:pointer;">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirmar Nova Senha <span class="req">*</span></label>
                    <div style="position:relative;">
                        <input type="password" name="confirmar_nova_senha" id="dadosConfirmar" class="form-control"
                               placeholder="Repita a nova senha" autocomplete="new-password" required
                               style="padding-right:2.75rem;">
                        <button type="button" class="input-toggle" onclick="toggleInput('dadosConfirmar',this)" style="position:absolute;right:0.875rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#9ca3af;cursor:pointer;">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div style="margin-top:1.25rem;">
                <button type="submit" class="btn-modal-submit" id="btnSalvarSenha">
                    <span class="btn-text"><i class="fa-solid fa-key"></i> Alterar Senha</span>
                    <div class="spinner"></div>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.dados-profile-card {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    background: #fff;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    padding: 1.5rem 2rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm);
    flex-wrap: wrap;
}
.profile-avatar {
    width: 64px;
    height: 64px;
    background: var(--color-primary);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    font-weight: 800;
    flex-shrink: 0;
}
.profile-info { flex: 1; }
.profile-info h3 { font-size: 1.25rem; margin-bottom: 0.125rem; }
.profile-info p { color: var(--color-text-muted); font-size: 0.875rem; margin: 0; }
.profile-meta { margin-left: auto; }
.dados-section-card {
    background: #fff;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.dados-section-header {
    padding: 1.125rem 1.5rem;
    border-bottom: 1px solid var(--color-border);
    background: var(--color-bg);
}
.dados-section-header h4 {
    font-size: 0.9375rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.dados-section-header h4 i { color: var(--color-primary); }
.dados-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    padding: 1.5rem;
    padding-bottom: 0.5rem;
}
.dados-form-grid .form-group { margin-bottom: 0; }
@media (max-width: 640px) {
    .dados-form-grid { grid-template-columns: 1fr; }
    .dados-profile-card { flex-direction: column; align-items: flex-start; }
    .profile-meta { margin-left: 0; }
}
</style>

<script>
// Celular mask
document.getElementById('dadosCelular').addEventListener('input', function () {
    var v = this.value.replace(/\D/g, '').substring(0, 11);
    if (v.length > 6) {
        this.value = '(' + v.substring(0,2) + ') ' + v.substring(2,7) + '-' + v.substring(7);
    } else if (v.length > 2) {
        this.value = '(' + v.substring(0,2) + ') ' + v.substring(2);
    } else {
        this.value = v.length > 0 ? '(' + v : v;
    }
});

// CEP mask
document.getElementById('dadosCep').addEventListener('input', function () {
    var v = this.value.replace(/\D/g, '').substring(0, 8);
    this.value = v.length > 5 ? v.substring(0,5) + '-' + v.substring(5) : v;
});

function toggleInput(inputId, btn) {
    var input = document.getElementById(inputId);
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.querySelector('i').className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
}

// Save personal data
document.getElementById('formDados').addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = document.getElementById('btnSalvarDados');
    btn.disabled = true;
    btn.classList.add('loading');

    fetch('actions/atualizar_dados.php', { method: 'POST', body: new FormData(this) })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled = false;
            btn.classList.remove('loading');
            if (data.success) {
                document.getElementById('dadosAlertError').style.display = 'none';
                document.getElementById('dadosAlertSuccessMsg').textContent = data.message || 'Dados atualizados com sucesso!';
                document.getElementById('dadosAlertSuccess').style.display = 'flex';
                if (data.nome) {
                    document.querySelectorAll('.topbar-user-name').forEach(function (el) { el.textContent = data.nome; });
                }
            } else {
                document.getElementById('dadosAlertSuccess').style.display = 'none';
                document.getElementById('dadosAlertErrorMsg').textContent = data.message || 'Erro ao salvar dados.';
                document.getElementById('dadosAlertError').style.display = 'flex';
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.classList.remove('loading');
            document.getElementById('dadosAlertErrorMsg').textContent = 'Erro de conexão.';
            document.getElementById('dadosAlertError').style.display = 'flex';
        });
});

// Change password
document.getElementById('formSenha').addEventListener('submit', function (e) {
    e.preventDefault();
    var nova = document.getElementById('dadosNovaSenha');
    var confirmar = document.getElementById('dadosConfirmar');

    if (nova.value.length < 8) {
        nova.classList.add('is-invalid');
        return;
    }
    if (nova.value !== confirmar.value) {
        confirmar.classList.add('is-invalid');
        return;
    }

    var btn = document.getElementById('btnSalvarSenha');
    btn.disabled = true;
    btn.classList.add('loading');

    fetch('actions/alterar_senha.php', { method: 'POST', body: new FormData(this) })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled = false;
            btn.classList.remove('loading');
            if (data.success) {
                document.getElementById('senhaAlertError').style.display = 'none';
                document.getElementById('senhaAlertSuccess').style.display = 'flex';
                document.getElementById('formSenha').reset();
            } else {
                document.getElementById('senhaAlertSuccess').style.display = 'none';
                document.getElementById('senhaAlertErrorMsg').textContent = data.message || 'Erro ao alterar senha.';
                document.getElementById('senhaAlertError').style.display = 'flex';
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.classList.remove('loading');
            document.getElementById('senhaAlertErrorMsg').textContent = 'Erro de conexão.';
            document.getElementById('senhaAlertError').style.display = 'flex';
        });
});
</script>
