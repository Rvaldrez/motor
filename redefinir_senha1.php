<?php
session_start();
require_once "conexao_bd.php";

$token = $_GET['token'] ?? '';
$erro = '';
$token_valido = false;
$usuario = null;

// Log para debug (remover em produção)
error_log("Token recebido: " . $token);

if (empty($token)) {
    $erro = "Token não fornecido. Use o link completo enviado por e-mail.";
} else {
    // Primeiro, verificar se o token existe (mesmo que expirado)
    $stmt = $mysqli->prepare("SELECT id, nome, email, reset_token_expira FROM usuarios WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $erro = "Link inválido. Este link pode já ter sido usado ou não existe.";
    } else {
        $usuario_temp = $result->fetch_assoc();
        
        // Verificar se expirou
        $agora = new DateTime();
        $expira = new DateTime($usuario_temp['reset_token_expira']);
        
        if ($agora > $expira) {
            $erro = "Este link expirou. Por favor, solicite um novo link de recuperação.";
            
            // Limpar token expirado
            $stmt_clean = $mysqli->prepare("UPDATE usuarios SET reset_token = NULL, reset_token_expira = NULL WHERE id = ?");
            $stmt_clean->bind_param("i", $usuario_temp['id']);
            $stmt_clean->execute();
            $stmt_clean->close();
        } else {
            // Token válido
            $token_valido = true;
            $usuario = $usuario_temp;
        }
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
            // Redirecionar com mensagem de sucesso
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
  <title><?= $token_valido ? 'Criar Nova Senha' : 'Link Inválido' ?> - MotorGo</title>
  <link rel="stylesheet" href="style_login.css"/>
  <style>
    .mensagem-erro {
      background: #fee;
      color: #c33;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      border-left: 4px solid #c33;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .mensagem-erro::before {
      content: "⚠️";
      font-size: 20px;
    }
    
    .senha-requisitos {
      background: #f8f9fa;
      padding: 15px 20px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
      border-left: 3px solid #b22222;
    }
    
    .senha-requisitos h4 {
      margin: 0 0 12px 0;
      color: #333;
      font-size: 14px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .senha-requisitos ul {
      margin: 0;
      padding-left: 0;
      list-style: none;
    }
    
    .senha-requisitos li {
      color: #666;
      margin: 8px 0;
      padding-left: 20px;
      position: relative;
    }
    
    .senha-requisitos li::before {
      content: "•";
      color: #b22222;
      font-weight: bold;
      position: absolute;
      left: 0;
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
    
    .login-form input:focus {
      border-color: #b22222;
      outline: none;
      box-shadow: 0 0 0 3px rgba(178, 34, 34, 0.1);
    }
    
    /* Barra de força da senha */
    .senha-forca-container {
      margin-top: 10px;
      margin-bottom: 15px;
    }
    
    .senha-forca-label {
      font-size: 12px;
      color: #666;
      margin-bottom: 5px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .senha-forca-texto {
      font-weight: 600;
    }
    
    .senha-forca-texto.fraca { color: #dc3545; }
    .senha-forca-texto.media { color: #ffc107; }
    .senha-forca-texto.forte { color: #28a745; }
    
    .senha-forca-barra {
      width: 100%;
      height: 8px;
      background: #e9ecef;
      border-radius: 4px;
      overflow: hidden;
      position: relative;
    }
    
    .senha-forca-preenchimento {
      height: 100%;
      transition: width 0.3s ease, background-color 0.3s ease;
      border-radius: 4px;
    }
    
    .senha-forca-preenchimento.fraca {
      width: 33%;
      background: linear-gradient(90deg, #dc3545, #ff6b6b);
    }
    
    .senha-forca-preenchimento.media {
      width: 66%;
      background: linear-gradient(90deg, #ffc107, #ffcd38);
    }
    
    .senha-forca-preenchimento.forte {
      width: 100%;
      background: linear-gradient(90deg, #28a745, #5cb85c);
    }
    
    /* Estilo para o link inválido */
    .link-invalido-container {
      text-align: center;
      padding: 20px 0;
    }
    
    .link-invalido-container h2 {
      color: #333;
      margin-bottom: 20px;
    }
    
    .link-invalido-container p {
      color: #666;
      margin: 15px 0;
    }
    
    .btn-secundario {
      background: transparent;
      color: #b22222;
      border: 2px solid #b22222;
      padding: 10px 20px;
      border-radius: 6px;
      text-decoration: none;
      display: inline-block;
      margin: 10px 5px;
      transition: all 0.3s;
    }
    
    .btn-secundario:hover {
      background: #b22222;
      color: white;
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
        <!-- Formulário para redefinir senha -->
        <h2>Criar Nova Senha</h2>
        <p>Olá <strong><?= htmlspecialchars($usuario['nome']) ?></strong>, defina sua nova senha abaixo.</p>

        <?php if ($erro): ?>
          <div class="mensagem-erro">
            <?= htmlspecialchars($erro) ?>
          </div>
        <?php endif; ?>

        <div class="senha-requisitos">
          <h4>Requisitos da senha:</h4>
          <ul>
            <li>Mínimo de 6 caracteres</li>
            <li>Recomendamos usar letras, números e símbolos</li>
            <li>Evite senhas óbvias como "123456" ou "senha"</li>
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
          <div id="forcaSenhaContainer" class="senha-forca-container" style="display: none;">
            <div class="senha-forca-label">
              <span>Força da senha:</span>
              <span id="forcaSenhaTexto" class="senha-forca-texto"></span>
            </div>
            <div class="senha-forca-barra">
              <div id="forcaSenhaBarra" class="senha-forca-preenchimento"></div>
            </div>
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

          <button type="submit" class="btn-login" id="btnSubmit">Redefinir Senha</button>
        </form>

      <?php else: ?>
        <!-- Mensagem de link inválido -->
        <div class="link-invalido-container">
          <h2>Link Inválido</h2>
          
          <div class="mensagem-erro">
            <?= htmlspecialchars($erro) ?>
          </div>
          
          <p>Por favor, solicite um novo link de recuperação.</p>
          
          <div style="margin-top: 30px;">
            <a href="login1.php" class="btn-secundario">Voltar ao login</a>
            <a href="recuperar_senha1.php" class="btn-login" style="text-decoration: none; display: inline-block;">Solicitar novo link</a>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($token_valido): ?>
        <div class="extras" style="margin-top: 20px;">
          <a href="login1.php">Voltar ao login</a>
        </div>
      <?php endif; ?>
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

    <?php if ($token_valido): ?>
    // Verificar força da senha
    document.getElementById('nova_senha').addEventListener('input', function() {
      const senha = this.value;
      const container = document.getElementById('forcaSenhaContainer');
      const textoDiv = document.getElementById('forcaSenhaTexto');
      const barraDiv = document.getElementById('forcaSenhaBarra');
      
      if (senha.length === 0) {
        container.style.display = 'none';
        return;
      }
      
      container.style.display = 'block';
      let forca = 0;
      
      // Critérios de força
      if (senha.length >= 6) forca++;
      if (senha.length >= 10) forca++;
      if (/[a-z]/.test(senha)) forca++;
      if (/[A-Z]/.test(senha)) forca++;
      if (/[0-9]/.test(senha)) forca++;
      if (/[^a-zA-Z0-9]/.test(senha)) forca++;
      
      // Exibir resultado com barra visual
      if (forca <= 2) {
        textoDiv.textContent = 'Fraca';
        textoDiv.className = 'senha-forca-texto fraca';
        barraDiv.className = 'senha-forca-preenchimento fraca';
      } else if (forca <= 4) {
        textoDiv.textContent = 'Média';
        textoDiv.className = 'senha-forca-texto media';
        barraDiv.className = 'senha-forca-preenchimento media';
      } else {
        textoDiv.textContent = 'Forte';
        textoDiv.className = 'senha-forca-texto forte';
        barraDiv.className = 'senha-forca-preenchimento forte';
      }
      
      // Verificar se as senhas coincidem
      verificarSenhas();
    });
    
    // Verificar se as senhas coincidem
    function verificarSenhas() {
      const senha1 = document.getElementById('nova_senha').value;
      const senha2 = document.getElementById('confirmar_senha').value;
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
    }
    
    document.getElementById('confirmar_senha').addEventListener('input', verificarSenhas);

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
      
      // Desabilitar botão para evitar múltiplos envios
      document.getElementById('btnSubmit').disabled = true;
      document.getElementById('btnSubmit').textContent = 'Processando...';
    });
    <?php endif; ?>
  </script>
</body>
</html>