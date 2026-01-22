<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Recuperar Senha - MotorGo</title>
  <link rel="stylesheet" href="style_login.css"/>
</head>
<body>
  <div class="login-container">
    <div class="login-image">
      <img src="imagens/login_bg.jpg" alt="Imagem de fundo"/>
    </div>

    <div class="login-form">
      <h2>Recuperar Senha</h2>
      <p>Informe seu E-mail ou CPF para enviarmos um link de redefinição.</p>

      <form id="formRecuperacao" method="POST">
        <label for="email_cpf">E-mail ou CPF</label>
        <input type="text" name="email_cpf" id="email_cpf" placeholder="Digite seu e-mail ou CPF" required/>

        <button type="submit" class="btn-login">Enviar link de recuperação</button>
      </form>

      <div class="extras">
        <a href="login.php">Voltar ao login</a>
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
      if (value.length <= 11 && /^\d+$/.test(this.value.replace(/[\.-]/g, ""))) {
        value = value.slice(0, 11);
        value = value.replace(/(\d{3})(\d)/, "$1.$2");
        value = value.replace(/(\d{3})(\d)/, "$1.$2");
        value = value.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
        this.value = value;
      }
    });

    // Função para mostrar popup
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
      
      // Validação básica
      if (emailCpf.length === 0) {
        mostrarPopup("⚠️ Por favor, informe seu e-mail ou CPF.");
        return;
      }
      
      // Se parece ser CPF, valida o formato
      const cpfLimpo = emailCpf.replace(/\D/g, "");
      if (cpfLimpo.length > 0 && cpfLimpo.length !== 11) {
        mostrarPopup("⚠️ CPF inválido. Verifique e tente novamente.");
        return;
      }

      // Mostra loader
      const btnSubmit = this.querySelector('button[type="submit"]');
      btnSubmit.disabled = true;
      btnSubmit.textContent = "Enviando...";

      fetch("processar_recuperar_senha.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({ email: emailCpf })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          mostrarPopup("✅ " + data.message, () => {
            window.location.href = "login.php?msg=email_enviado";
          });
        } else {
          mostrarPopup("❌ " + data.message);
        }
      })
      .catch(err => {
        console.error(err);
        mostrarPopup("❌ Erro ao enviar o link. Tente novamente.");
      })
      .finally(() => {
        btnSubmit.disabled = false;
        btnSubmit.textContent = "Enviar link de recuperação";
      });
    });

    // Verifica se veio com mensagem de sucesso
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'senha_redefinida'): ?>
      mostrarPopup("✅ Senha redefinida com sucesso! Faça login com sua nova senha.");
    <?php endif; ?>
  </script>
</body>
</html>