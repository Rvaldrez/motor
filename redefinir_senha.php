<?php
session_start();
require_once "conexao_bd.php";

$token = $_GET['token'] ?? '';
$erro = '';
$token_valido = false;

if ($token) {
    // Verificar se o token é válido e não expirou
    $stmt = $mysqli->prepare("SELECT id, nome, email FROM usuarios WHERE reset_token = ? AND reset_token_expira > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $usuario = $result->fetch_assoc();
        $token_valido = true;
    } else {
        $erro = "Link inválido ou expirado. Solicite um novo link de recuperação.";
    }
    $stmt->close();
}

// Processar nova senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valido) {
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    
    if (strlen($nova_senha) < 6) {
        $erro = "A senha deve ter pelo menos 6 caracteres.";
    } elseif ($nova_senha !== $confirmar_senha) {
        $erro = "As senhas não coincidem.";
    } else {
        // Criptografar nova senha
        $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        
        // Atualizar senha e limpar token
        $stmt = $mysqli->prepare("UPDATE usuarios SET senha = ?, reset_token = NULL, reset_token_expira = NULL WHERE id = ?");
        $stmt->bind_param("si", $senha_hash, $usuario['id']);
        
        if ($stmt->execute()) {
            header("Location: login1.php?msg=senha_redefinida");
            exit;
        } else {
            $erro = "Erro ao atualizar senha. Tente novamente.";
        }
        $stmt->close();
    }
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
    .mensagem-erro {
      background: #fee;
      color: #c33;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      border-left: 4px solid #c33;
    }
    
    .senha-requisitos {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
    }
    
    .senha-requisitos h4 {
      margin: 0 0 10px 0;
      color: #333;
    }
    
    .senha-requisitos ul {
      margin: 0;
      padding-left: 20px;
    }
    
    .senha-requisitos li {
      color: #666;
      margin: 5px 0;
    }
    
    .senha-match {
      font-size: 12px;
      margin-top: 5px;
    }
    
    .senha-match.valid {
      color: #28a745;
    }
    
    .senha-match.invalid {
      color: #dc3545;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-image">
      <img src="imagens/login_bg.jpg" alt="Imagem de fundo"/>
    </div>

    <div class="login-form">
      <?php if ($token_valido): ?>
        <h2>Criar Nova Senha</h2>
        <p>Olá <?= htmlspecialchars($usuario['nome']) ?>, defina sua nova senha abaixo.</p>

        <?php if ($erro): ?>
          <div class="mensagem-erro">
            ⚠️ <?= htmlspecialchars($erro) ?>
          </div>
        <?php endif; ?>

        <div class="senha-requisitos">
          <h4>Requisitos da senha:</h4>
          <ul>
            <li>Mínimo de 6 caracteres</li>
            <li>Recomendamos usar letras e números</li>
            <li>Evite senhas óbvias como "123456"</li>
          </ul>
        </div>

        <form method="POST" id="formRedefinir">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          
          <label for="nova_senha">Nova Senha</label>
          <div class="senha-wrapper">
            <input 
              type="password" 
              name="nova_senha" 
              id="nova_senha" 
              placeholder="Digite sua nova senha" 
              required
              minlength="6"
            />
            <span class="toggle-senha" onclick="toggleSenha('nova_senha', 'iconeOlho1')">
              <img src="imagens/eye.svg" alt="Mostrar senha" id="iconeOlho1"/>
            </span>
          </div>

          <label for="confirmar_senha">Confirmar Nova Senha</label>
          <div class="senha-wrapper">
            <input 
              type="password" 
              name="confirmar_senha" 
              id="confirmar_senha" 
              placeholder="Digite a senha novamente" 
              required
              minlength="6"
            />
            <span class="toggle-senha" onclick="toggleSenha('confirmar_senha', 'iconeOlho2')">
              <img src="imagens/eye.svg" alt="Mostrar senha" id="iconeOlho2"/>
            </span>
          </div>
          <div id="senhaMatch" class="senha-match"></div>

          <button type="submit" class="btn-login">Redefinir Senha</button>
        </form>

      <?php else: ?>
        <h2>Link Inválido</h2>
        <?php if ($erro): ?>
          <div class="mensagem-erro">
            ⚠️ <?= htmlspecialchars($erro) ?>
          </div>
        <?php else: ?>
          <div class="mensagem-erro">
            ⚠️ Este link é inválido ou expirou.
          </div>
        <?php endif; ?>
        <p>Por favor, solicite um novo link de recuperação.</p>
      <?php endif; ?>

      <div class="extras">
        <a href="login1.php">Voltar ao login</a>
        <?php if (!$token_valido): ?>
          | <a href="recuperar_senha1.php">Solicitar novo link</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    function toggleSenha(inputId, iconId) {
      const senhaInput = document.getElementById(inputId);
      const icone = document.getElementById(iconId);

      if (senhaInput.type === "password") {
        senhaInput.type = "text";
        icone.src = "imagens/eye-off.svg";
      } else {
        senhaInput.type = "password";
        icone.src = "imagens/eye.svg";
      }
    }

    // Verificar se as senhas coincidem
    <?php if ($token_valido): ?>
    document.getElementById('confirmar_senha').addEventListener('input', function() {
      const senha1 = document.getElementById('nova_senha').value;
      const senha2 = this.value;
      const matchDiv = document.getElementById('senhaMatch');
      
      if (senha2.length > 0) {
        if (senha1 === senha2) {
          matchDiv.textContent = '✅ As senhas coincidem';
          matchDiv.className = 'senha-match valid';
        } else {
          matchDiv.textContent = '❌ As senhas não coincidem';
          matchDiv.className = 'senha-match invalid';
        }
      } else {
        matchDiv.textContent = '';
      }
    });

    // Validar antes de enviar
    document.getElementById('formRedefinir').addEventListener('submit', function(e) {
      const senha1 = document.getElementById('nova_senha').value;
      const senha2 = document.getElementById('confirmar_senha').value;
      
      if (senha1 !== senha2) {
        e.preventDefault();
        alert('As senhas não coincidem!');
        return false;
      }
      
      if (senha1.length < 6) {
        e.preventDefault();
        alert('A senha deve ter pelo menos 6 caracteres!');
        return false;
      }
    });
    <?php endif; ?>
  </script>
</body>
</html>