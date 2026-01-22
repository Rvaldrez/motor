<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login - MotorGo</title>
  <link rel="stylesheet" href="style_login.css"/>
  <style>
    /* Estilos para mensagens inline */
    .mensagem-erro-inline {
      background: #fee;
      color: #c33;
      padding: 12px 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      border-left: 4px solid #c33;
      font-size: 14px;
      display: none;
      animation: slideDown 0.3s ease-out;
    }
    
    .mensagem-sucesso-inline {
      background: #d4edda;
      color: #155724;
      padding: 12px 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      border-left: 4px solid #28a745;
      font-size: 14px;
      animation: slideDown 0.3s ease-out;
    }
    
    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .popup-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      animation: fadeIn 0.3s ease-out;
    }
    
    .popup-overlay.show {
      display: flex;
    }
    
    .popup-content {
      background: white;
      padding: 30px;
      border-radius: 12px;
      max-width: 400px;
      width: 90%;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
      animation: scaleIn 0.3s ease-out;
    }
    
    .popup-header {
      display: flex;
      align-items: center;
      margin-bottom: 15px;
    }
    
    .popup-icon {
      width: 24px;
      height: 24px;
      margin-right: 10px;
    }
    
    .popup-icon.error {
      color: #dc3545;
    }
    
    .popup-icon.success {
      color: #28a745;
    }
    
    .popup-title {
      font-size: 18px;
      font-weight: 600;
      color: #333;
    }
    
    .popup-message {
      color: #666;
      line-height: 1.5;
      margin-bottom: 20px;
    }
    
    .popup-button {
      background: #b22222;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      width: 100%;
      transition: background 0.2s;
    }
    
    .popup-button:hover {
      background: #8b0000;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    
    @keyframes scaleIn {
      from {
        opacity: 0;
        transform: scale(0.9);
      }
      to {
        opacity: 1;
        transform: scale(1);
      }
    }
    
    /* Ajuste no campo de input para harmonizar com mensagens */
    .login-form input:focus {
      border-color: #b22222;
      outline: none;
      box-shadow: 0 0 0 3px rgba(178, 34, 34, 0.1);
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-image">
      <img src="imagens/login_bg.jpg" alt="Imagem de fundo"/>
    </div>
    <div class="login-form">
      <h2>Entrar em minha conta</h2>
      <p>Informe seu E-mail ou CPF para continuar.</p>

      <!-- Mensagem de erro inline (oculta por padrão) -->
      <div id="mensagemErroInline" class="mensagem-erro-inline"></div>

      <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'cadastro_salvo'): ?>
          <div class="mensagem-sucesso-inline">
            ✅ Suas informações foram salvas! Verifique seu email para continuar o cadastro.
          </div>
        <?php elseif ($_GET['msg'] === 'cadastro_confirmado'): ?>
          <div class="mensagem-sucesso-inline">
            ✅ Cadastro confirmado com sucesso! Faça login para acessar seu painel.
          </div>
        <?php elseif ($_GET['msg'] === 'email_enviado'): ?>
          <div class="mensagem-sucesso-inline">
            ✅ Email de recuperação enviado! Verifique sua caixa de entrada.
          </div>
        <?php elseif ($_GET['msg'] === 'senha_redefinida'): ?>
          <div class="mensagem-sucesso-inline">
            ✅ Senha redefinida com sucesso! Faça login com sua nova senha.
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <form action="verificar_login1.php" method="POST" id="formLogin">
        <label for="cpf_email">E-mail ou CPF</label>
        <input 
          type="text" 
          name="cpf_email" 
          id="cpf_email" 
          placeholder="Digite seu E-mail ou CPF" 
          required
        />

        <label for="senha">Senha</label>
        <div class="senha-wrapper">
          <input 
            type="password" 
            name="senha" 
            id="senha" 
            placeholder="Digite sua senha" 
            required
          />
          <span class="toggle-senha" onclick="toggleSenha()">
            <img src="imagens/eye.svg" alt="Mostrar senha" id="iconeOlho"/>
          </span>
        </div>

        <button type="submit" class="btn-login">Entrar</button>
      </form>

      <div class="extras">
        <a href="recuperar_senha1.php">Esqueci minha senha</a>
      </div>
    </div>
  </div>

  <!-- Popup Modal Melhorado -->
  <div id="popupModal" class="popup-overlay">
    <div class="popup-content">
      <div class="popup-header">
        <span id="popupIcon" class="popup-icon"></span>
        <h3 id="popupTitle" class="popup-title">Atenção</h3>
      </div>
      <div id="popupMessage" class="popup-message"></div>
      <button onclick="fecharPopup()" class="popup-button">Fechar</button>
    </div>
  </div>

  <script>
    // Aplicar máscara de CPF automaticamente
    document.getElementById('cpf_email').addEventListener('input', function(e) {
      let value = e.target.value;
      
      // Remove tudo exceto números para verificar se é CPF
      let numbersOnly = value.replace(/\D/g, '');
      
      // Se tem apenas números e até 11 dígitos, aplica máscara de CPF
      if (numbersOnly.length > 0 && numbersOnly.length <= 11 && !value.includes('@')) {
        value = numbersOnly.slice(0, 11);
        if (value.length > 3) {
          value = value.replace(/(\d{3})(\d)/, '$1.$2');
        }
        if (value.length > 7) {
          value = value.replace(/(\d{3})\.(\d{3})(\d)/, '$1.$2.$3');
        }
        if (value.length > 11) {
          value = value.replace(/(\d{3})\.(\d{3})\.(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
        }
        e.target.value = value;
      }
    });

    function toggleSenha() {
      const senhaInput = document.getElementById("senha");
      const icone = document.getElementById("iconeOlho");

      if (senhaInput.type === "password") {
        senhaInput.type = "text";
        icone.src = "imagens/eye-off.svg";
      } else {
        senhaInput.type = "password";
        icone.src = "imagens/eye.svg";
      }
    }

    function mostrarPopup(mensagem, tipo = 'error') {
      const modal = document.getElementById('popupModal');
      const icon = document.getElementById('popupIcon');
      const title = document.getElementById('popupTitle');
      const message = document.getElementById('popupMessage');
      
      // Configurar ícone e título baseado no tipo
      if (tipo === 'success') {
        icon.innerHTML = '✅';
        icon.className = 'popup-icon success';
        title.textContent = 'Sucesso';
      } else {
        icon.innerHTML = '❌';
        icon.className = 'popup-icon error';
        title.textContent = 'Atenção';
      }
      
      message.textContent = mensagem;
      modal.classList.add('show');
    }

    function mostrarErroInline(mensagem) {
      const div = document.getElementById('mensagemErroInline');
      div.textContent = '⚠️ ' + mensagem;
      div.style.display = 'block';
      
      // Auto-ocultar após 5 segundos
      setTimeout(() => {
        div.style.display = 'none';
      }, 5000);
    }

    function fecharPopup() {
      document.getElementById('popupModal').classList.remove('show');
    }

    // Verificar se há erro na URL
    <?php if (isset($_GET['erro'])): ?>
      // Usar mensagem inline para erros de login
      mostrarErroInline('<?= htmlspecialchars($_GET['erro']) ?>');
    <?php endif; ?>

    // Interceptar submissão do formulário para validação adicional
    document.getElementById('formLogin').addEventListener('submit', function(e) {
      const cpfEmail = document.getElementById('cpf_email').value.trim();
      const senha = document.getElementById('senha').value;
      
      // Validação básica
      if (!cpfEmail || !senha) {
        e.preventDefault();
        mostrarErroInline('Por favor, preencha todos os campos.');
        return false;
      }
      
      // Validar formato de CPF se parecer ser CPF
      const cpfLimpo = cpfEmail.replace(/\D/g, '');
      if (cpfLimpo.length > 0 && cpfLimpo.length !== 11 && !cpfEmail.includes('@')) {
        e.preventDefault();
        mostrarErroInline('CPF inválido. Digite os 11 dígitos do CPF.');
        return false;
      }
    });
  </script>
</body>
</html>