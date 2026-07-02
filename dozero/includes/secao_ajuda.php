<?php
/**
 * Seção: Ajuda & Suporte
 * $conn, $user, $tipo, $csrfToken available from painel.php
 */
?>

<div class="section-page" style="max-width:800px;">
    <div class="section-page-header">
        <div>
            <h2 class="section-page-title"><i class="fa-solid fa-circle-question"></i> Ajuda & Suporte</h2>
            <p class="section-page-subtitle">Tire suas dúvidas e entre em contato com nossa equipe.</p>
        </div>
    </div>

    <!-- Quick links -->
    <div class="help-quick-grid">
        <a href="mailto:suporte@motorgo.co" class="help-quick-card">
            <div class="help-quick-icon" style="background:rgba(37,99,235,0.1);">
                <i class="fa-solid fa-envelope" style="color:#2563eb;"></i>
            </div>
            <div>
                <strong>E-mail</strong>
                <p>suporte@motorgo.co</p>
            </div>
        </a>
        <a href="https://wa.me/5511999999999" target="_blank" rel="noopener noreferrer" class="help-quick-card">
            <div class="help-quick-icon" style="background:rgba(22,163,74,0.1);">
                <i class="fa-brands fa-whatsapp" style="color:#16a34a;"></i>
            </div>
            <div>
                <strong>WhatsApp</strong>
                <p>Atendimento rápido</p>
            </div>
        </a>
        <div class="help-quick-card" style="cursor:default;">
            <div class="help-quick-icon" style="background:rgba(217,119,6,0.1);">
                <i class="fa-solid fa-clock" style="color:#d97706;"></i>
            </div>
            <div>
                <strong>Horário</strong>
                <p>Seg–Sex, 9h–18h</p>
            </div>
        </div>
    </div>

    <!-- FAQ -->
    <div class="dados-section-card" style="margin-bottom:1.5rem;">
        <div class="dados-section-header">
            <h4><i class="fa-solid fa-circle-question"></i> Perguntas Frequentes</h4>
        </div>
        <div class="faq-list">
            <?php
            $faqs = [
                [
                    'q' => 'Como cadastrar um veículo?',
                    'a' => 'Acesse o menu "Meus Veículos" e clique em "Cadastrar Veículo". Preencha todas as informações e adicione fotos do veículo.',
                ],
                [
                    'q' => 'Como funciona o processo de proposta?',
                    'a' => 'O investidor localiza um veículo na seção "Oferta de Veículos", clica em "Fazer Proposta" e informa o valor desejado. O vendedor recebe a notificação e pode aceitar, recusar ou fazer uma contraproposta.',
                ],
                [
                    'q' => 'Quanto tempo leva para uma proposta ser respondida?',
                    'a' => 'O prazo de resposta depende do vendedor. Em geral, as propostas são respondidas em até 48 horas. Você receberá uma notificação por e-mail quando houver uma atualização.',
                ],
                [
                    'q' => 'Como redefinir minha senha?',
                    'a' => 'Acesse a seção "Meus Dados" e utilize o formulário "Alterar Senha". Caso tenha esquecido a senha atual, faça logout e utilize a opção "Esqueceu a senha?" na página de login.',
                ],
                [
                    'q' => 'Meus dados estão seguros?',
                    'a' => 'Sim. Utilizamos criptografia e boas práticas de segurança para proteger seus dados. Nunca compartilhamos suas informações com terceiros sem sua autorização.',
                ],
                [
                    'q' => 'Como remover um veículo cadastrado?',
                    'a' => 'Acesse "Meus Veículos", localize o veículo desejado e clique no botão de remover (ícone de lixeira). Atenção: veículos com propostas ativas não podem ser removidos.',
                ],
            ];
            foreach ($faqs as $idx => $faq):
            ?>
            <div class="faq-item" id="faq<?= $idx ?>">
                <button class="faq-question" onclick="toggleFaq(<?= $idx ?>)" aria-expanded="false">
                    <span><?= htmlspecialchars($faq['q'], ENT_QUOTES, 'UTF-8') ?></span>
                    <i class="fa-solid fa-chevron-down faq-icon"></i>
                </button>
                <div class="faq-answer" id="faqAnswer<?= $idx ?>" style="display:none;">
                    <p><?= htmlspecialchars($faq['a'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Contact form -->
    <div class="dados-section-card">
        <div class="dados-section-header">
            <h4><i class="fa-solid fa-paper-plane"></i> Enviar Mensagem</h4>
        </div>

        <div style="padding:1.5rem;">
            <div class="alert-box alert-error" id="helpAlertError" style="display:none;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span id="helpAlertErrorMsg"></span>
            </div>
            <div class="alert-box alert-success" id="helpAlertSuccess" style="display:none;">
                <i class="fa-solid fa-circle-check"></i>
                <span>Mensagem enviada! Responderemos em até 24 horas.</span>
            </div>

            <form id="formContato" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label class="form-label">Assunto <span class="req">*</span></label>
                    <select name="assunto" class="form-control" required>
                        <option value="">Selecione o assunto</option>
                        <option value="proposta">Dúvidas sobre propostas</option>
                        <option value="veiculo">Cadastro de veículo</option>
                        <option value="conta">Problema na conta</option>
                        <option value="pagamento">Pagamento</option>
                        <option value="outro">Outro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Mensagem <span class="req">*</span></label>
                    <textarea name="mensagem" class="form-control" rows="5"
                              placeholder="Descreva sua dúvida ou problema em detalhes…"
                              maxlength="1000" style="resize:vertical;" required></textarea>
                </div>

                <button type="submit" class="btn-modal-submit" id="btnEnviarContato">
                    <span class="btn-text"><i class="fa-solid fa-paper-plane"></i> Enviar Mensagem</span>
                    <div class="spinner"></div>
                </button>
            </form>
        </div>
    </div>
</div>

<style>
.help-quick-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.help-quick-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    background: #fff;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    text-decoration: none;
    color: var(--color-text);
    transition: var(--transition);
    box-shadow: var(--shadow-sm);
}
a.help-quick-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: rgba(178,34,34,0.2); }
.help-quick-icon {
    width: 44px;
    height: 44px;
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.25rem;
}
.help-quick-card strong { display: block; font-size: 0.9375rem; margin-bottom: 0.125rem; }
.help-quick-card p { margin: 0; font-size: 0.8125rem; color: var(--color-text-muted); }
.faq-list { padding: 0.5rem 0; }
.faq-item { border-bottom: 1px solid var(--color-border); }
.faq-item:last-child { border-bottom: none; }
.faq-question {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.125rem 1.5rem;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--color-text);
    text-align: left;
    gap: 1rem;
    transition: color 0.2s;
}
.faq-question:hover { color: var(--color-primary); }
.faq-icon { transition: transform 0.25s ease; flex-shrink: 0; color: var(--color-text-muted); font-size: 0.875rem; }
.faq-item.open .faq-icon { transform: rotate(180deg); }
.faq-answer { padding: 0 1.5rem 1.25rem; }
.faq-answer p { font-size: 0.9rem; color: var(--color-text-muted); line-height: 1.7; margin: 0; }
@media (max-width: 640px) {
    .help-quick-grid { grid-template-columns: 1fr; }
}
</style>

<script>
function toggleFaq(idx) {
    var item = document.getElementById('faq' + idx);
    var answer = document.getElementById('faqAnswer' + idx);
    var isOpen = item.classList.contains('open');

    // Close all
    document.querySelectorAll('.faq-item').forEach(function (el) {
        el.classList.remove('open');
        el.querySelector('.faq-answer').style.display = 'none';
        el.querySelector('.faq-question').setAttribute('aria-expanded', 'false');
    });

    if (!isOpen) {
        item.classList.add('open');
        answer.style.display = 'block';
        item.querySelector('.faq-question').setAttribute('aria-expanded', 'true');
    }
}

document.getElementById('formContato').addEventListener('submit', function (e) {
    e.preventDefault();
    var assunto = this.querySelector('[name="assunto"]');
    var mensagem = this.querySelector('[name="mensagem"]');
    var valid = true;
    if (!assunto.value) { assunto.classList.add('is-invalid'); valid = false; }
    if (!mensagem.value.trim()) { mensagem.classList.add('is-invalid'); valid = false; }
    if (!valid) return;

    var btn = document.getElementById('btnEnviarContato');
    btn.disabled = true;
    btn.classList.add('loading');

    fetch('actions/contato.php', { method: 'POST', body: new FormData(this) })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled = false;
            btn.classList.remove('loading');
            if (data.success) {
                document.getElementById('helpAlertError').style.display = 'none';
                document.getElementById('helpAlertSuccess').style.display = 'flex';
                document.getElementById('formContato').reset();
            } else {
                document.getElementById('helpAlertSuccess').style.display = 'none';
                document.getElementById('helpAlertErrorMsg').textContent = data.message || 'Erro ao enviar mensagem.';
                document.getElementById('helpAlertError').style.display = 'flex';
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.classList.remove('loading');
            document.getElementById('helpAlertErrorMsg').textContent = 'Erro de conexão. Tente novamente.';
            document.getElementById('helpAlertError').style.display = 'flex';
        });
});
</script>
