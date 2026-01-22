<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Recuperar Senha - MotorGo</title>
  <link rel="stylesheet" href="style_login.css"/>
  <style>
    /* Estilos adicionais para mensagens inline */
    .mensagem-inline {
      padding: 12px 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
      display: none;
      animation: slideDown 0.3s ease-out;
    }
    
    .mensagem-inline.erro {
      background: #fee;
      color: #c33;
      border-left: 4px solid #c33;
    }
    
    .mensagem-inline.sucesso {
      background: #d4edda;
      color: #155724;
      border-left: 4px solid #28a745;
    }
    
    .mensagem-inline.info {
      background: #d1ecf1;
      color: #0c5460;
      border-left: 4px solid #17a2b8;
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
    
    /* Indicador de carregamento */
    .btn-login.loading {
      position: relative;
      color: transparent;
    }
    
    .btn-login.loading::after {
      content: "";
      position: absolute;
      width: 16px;
      height: 16px;
      top: 50%;
      left: 50%;
      margin-left: -8px;
      margin-top: -8px;
      border: 2px solid #ffffff;
      border-radius: 50%;
      border-top-color: transparent;
      animation: spinner 0.6s linear infinite;
    }
    
    @keyframes spinner {
      to { transform: rotate(360deg); }
    }
    
    /* Melhorias no campo de input */
    .login-form input:focus {
      border-color: #b22222;
      outline: none;
      box-shadow: 0 0 0 3px rgba(178, 34, 34, 0.1);
    }
    
    .input-hint {
      font-size: 12px;
      color: #666;
      margin-top: 5px;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-image">
      <img src="imagens/login_bg.jpg" alt="Imagem de fundo"/>
    </div>

    <div class="login-form">
      <h2>Recuperar Senha</h2>
      <p>Informe seu E-mail ou CPF para enviarmos um link de redefinição.</p>

      <!-- Mensagem inline para feedback -->
      <div id="mensagemInline" class="mensagem-inline"></div>

      <form id="formRecuperacao" method="POST">
        <label for="email_cpf">E-mail ou CPF</label>
        <input 
          type="text" 
          name="email_cpf" 
          id="email_cpf" 
          placeholder="Digite seu e-mail ou CPF" 
          required
        />
        <div class="input-hint">Você pode usar o e-mail cadastrado ou seu CPF</div>

        <button type="submit" class="btn-login" id="btnSubmit">
          Enviar link de recuperação
        </button>
      </form>

      <div class="extras">
        <a href="login1.php">Voltar ao login</a>
      </div>
    </div>
  </div>

  <!-- 🔹 Popup reutilizável -->
  <div id="popupMensagem" class="popup-mensagem" style="display: none;">
    <div class="popup-conteudo">
      <span id="popupTexto"></span>
      <button onclick="fecharPopup()" class="btn-vermelho btn-fechar">Fechar</button>
    </div>
  </div>

  <script>
    // Detecta se é CPF e aplica máscara
    document.getElementById("email_cpf").addEventListener("input", function () {
      let value = this.value.replace(/\D/g, "");
      
      // Se tem apenas números e até 11 dígitos, assume que é CPF
      if (value.length <= 11 && /^\d+$/.test(this.value.replace(/[\.-]/g, "")) && !this.value.includes('@')) {
        value = value.slice(0, 11);
        if (value.length > 3) {
          value = value.replace(/(\d{3})(\d)/, "$1.$2");
        }
        if (value.length > 7) {
          value = value.replace(/(\d{3})\.(\d{3})(\d)/, "$1.$2.$3");  
        }
        if (value.length > 11) {
          value = value.replace(/(\d{3})\.(\d{3})\.(\d{3})(\d{1,2})/, "$1.$2.$3-$4");
        }
        this.value = value;
      }
    });

    // Função para mostrar mensagem inline
    function mostrarMensagemInline(msg, tipo = 'erro') {
      const div = document.getElementById('mensagemInline');
      div.className = 'mensagem-inline ' + tipo;
      
      // Adicionar ícone baseado no tipo
      let icone = '⚠️';
      if (tipo === 'sucesso') icone = '✅';
      if (tipo === 'info') icone = 'ℹ️';
      
      div.innerHTML = icone + ' ' + msg;
      div.style.display = 'block';
      
      // Auto-ocultar após 5 segundos para erros, não ocultar para sucesso
      if (tipo === 'erro') {
        setTimeout(() => {
          div.style.display = 'none';
        }, 5000);
      }
    }

    // Função para mostrar popup (compatibilidade)
    function mostrarPopup(msg, callback = null) {
      const popup = document.getElementById("popupMensagem");
      const texto = document.getElementById("popupTexto");
      texto.innerHTML = msg.replace(/\n/g, "<br>");
      popup.style.display = "flex";
      window.popupCallback = callback;
    }
    
    // Função para fechar popup
    function fecharPopup() {
      document.getElementById('popupMensagem').style.display = 'none';
      if (window.popupCallback) {
        window.popupCallback();
        window.popupCallback = null;
      }
    }

    // Submete recuperação de senha
    document.getElementById("formRecuperacao").addEventListener("submit", function (e) {
      e.preventDefault();

      const emailCpf = document.getElementById("email_cpf").value.trim();
      const btnSubmit = document.getElementById("btnSubmit");
      
      // Validação básica
      if (emailCpf.length === 0) {
        mostrarMensagemInline("Por favor, informe seu e-mail ou CPF.");
        return;
      }
      
      // Se parece ser CPF, valida o formato
      const cpfLimpo = emailCpf.replace(/\D/g, "");
      if (cpfLimpo.length > 0 && cpfLimpo.length !== 11 && !emailCpf.includes('@')) {
        mostrarMensagemInline("CPF inválido. Verifique e tente novamente.");
        return;
      }
      
      // Validação básica de e-mail
      if (emailCpf.includes('@') && !emailCpf.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
        mostrarMensagemInline("E-mail inválido. Verifique e tente novamente.");
        return;
      }

      // Mostra loader
      btnSubmit.disabled = true;
      btnSubmit.classList.add('loading');
      btnSubmit.textContent = "Enviando...";

      // Limpar mensagens anteriores
      document.getElementById('mensagemInline').style.display = 'none';

      fetch("processar_recuperar_senha1.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({ email: emailCpf })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          mostrarMensagemInline(data.message, 'sucesso');
          // Limpar o campo
          document.getElementById("email_cpf").value = "";
          // Redirecionar após 3 segundos
          setTimeout(() => {
            window.location.href = "login1.php?msg=email_enviado";
          }, 3000);
        } else {
          mostrarMensagemInline(data.message, 'erro');
        }
      })
      .catch(err => {
        console.error(err);
        mostrarMensagemInline("Erro ao enviar o link. Tente novamente.", 'erro');
      })
      .finally(() => {
        btnSubmit.disabled = false;
        btnSubmit.classList.remove('loading');
        btnSubmit.textContent = "Enviar link de recuperação";
      });
    });

    // Verificar se veio com mensagem de sucesso
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'senha_redefinida'): ?>
      mostrarMensagemInline("Senha redefinida com sucesso! Faça login com sua nova senha.", 'sucesso');
    <?php endif; ?>
  </script>
</body>
</html>