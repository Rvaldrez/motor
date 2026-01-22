<?php 
$token = $_GET['token'] ?? ''; 

// Se não tem token, redireciona
if (empty($token)) {
    header("Location: login.php?erro=" . urlencode("Link inválido."));
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Redefinir Senha - MotorGo</title>
  <link rel="stylesheet" href="style_login.css"/>
  <style>
    .senha-requisitos {
      font-size: 12px;
      color: #666;
      margin-top: 5px;
      margin-bottom: 15px;
    }
    .mensagem {
      padding: 10px;
      border-radius: 5px;
      margin-top: 15px;
      text-align: center;
    }
    .mensagem.sucesso {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    .mensagem.erro {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-image">
      <img src="imagens/login_bg.jpg" alt="Imagem de fundo"/>
    </div>
    <div class="login-form">
      <h2>Redefinir Senha</h2>
      <p>Crie uma nova senha para sua conta.</p>

      <form id="formRedefinir">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"/>

        <label for="nova_senha">Nova Senha</label>
        <div class="senha-wrapper">
          <input type="password" id="nova_senha" name="nova_senha" required 
                 placeholder="Digite a nova senha" minlength="6" />
          <span class="toggle-senha" onclick="toggleSenha('nova_senha', 'iconeOlho1')">
            <img src="imagens/eye.svg" alt="Mostrar senha" id="iconeOlho1"/>
          </span>
        </div>
        <div class="senha-requisitos">
          Mínimo de 6 caracteres
        </div>

        <label for="confirmar_senha">Confirmar Nova Senha</label>
        <div class="senha-wrapper">
          <input type="password" id="confirmar_senha" name="confirmar_senha" required 
                 placeholder="Confirme a nova senha" minlength="6" />
          <span class="toggle-senha" onclick="toggleSenha('confirmar_senha', 'iconeOlho2')">
            <img src="imagens/eye.svg" alt="Mostrar senha" id="iconeOlho2"/>
          </span>
        </div>

        <button type="submit" class="btn-login" id="btnSubmit">Salvar Nova Senha</button>
      </form>

      <div id="mensagem" style="display: none;"></div>

      <div class="extras">
        <a href="login.php">Voltar ao login</a>
      </div>
    </div>
  </div>

  <script>
    // Função para mostrar/ocultar senha
    function toggleSenha(inputId, iconeId) {
      const senhaInput = document.getElementById(inputId);
      const icone = document.getElementById(iconeId);

      if (senhaInput.type === "password") {
        senhaInput.type = "text";
        icone.src = "imagens/eye-off.svg";
      } else {
        senhaInput.type = "password";
        icone.src = "imagens/eye.svg";
      }
    }

    // Valida se as senhas coincidem em tempo real
    document.getElementById("confirmar_senha").addEventListener("input", function() {
      const novaSenha = document.getElementById("nova_senha").value;
      const confirmarSenha = this.value;
      
      if (confirmarSenha && novaSenha !== confirmarSenha) {
        this.setCustomValidity("As senhas não coincidem");
      } else {
        this.setCustomValidity("");
      }
    });

    // Processa o formulário
    document.getElementById("formRedefinir").addEventListener("submit", function(e) {
      e.preventDefault();
      
      const form = e.target;
      const dados = new URLSearchParams(new FormData(form));
      const mensagemDiv = document.getElementById("mensagem");
      const btnSubmit = document.getElementById("btnSubmit");
      
      // Validação adicional
      const novaSenha = document.getElementById("nova_senha").value;
      const confirmarSenha = document.getElementById("confirmar_senha").value;
      
      if (novaSenha !== confirmarSenha) {
        mensagemDiv.className = "mensagem erro";
        mensagemDiv.textContent = "As senhas não coincidem!";
        mensagemDiv.style.display = "block";
        return;
      }

      // Desabilita o botão durante o envio
      btnSubmit.disabled = true;
      btnSubmit.textContent = "Salvando...";

      fetch("salvar_nova_senha_login.php", {
        method: "POST",
        body: dados
      })
      .then(res => res.json())
      .then(data => {
        mensagemDiv.style.display = "block";
        
        if (data.success) {
          mensagemDiv.className = "mensagem sucesso";
          mensagemDiv.innerHTML = "✅ " + data.message;
          
          // Redireciona após 2 segundos
          setTimeout(() => {
            window.location.href = "login.php?msg=senha_redefinida";
          }, 2000);
        } else {
          mensagemDiv.className = "mensagem erro";
          mensagemDiv.innerHTML = "❌ " + data.message;
          
          // Reabilita o botão em caso de erro
          btnSubmit.disabled = false;
          btnSubmit.textContent = "Salvar Nova Senha";
        }
      })
      .catch(() => {
        mensagemDiv.style.display = "block";
        mensagemDiv.className = "mensagem erro";
        mensagemDiv.innerHTML = "❌ Erro ao redefinir senha. Tente novamente.";
        
        btnSubmit.disabled = false;
        btnSubmit.textContent = "Salvar Nova Senha";
      });
    });
  </script>
</body>
</html>