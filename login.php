<?php
session_start();
require_once 'conexao_bd.php';

// 🚧 Redireciona se o usuário estiver com cadastro incompleto DE VEÍCULO
if (isset($_SESSION['usuario_id']) && isset($_SESSION['status_cadastro'])) {
  $status = strtolower($_SESSION['status_cadastro']);
  if (in_array($status, ['incompleto', 'incompleto1'])) {
    $usuarioId = $_SESSION['usuario_id'];
    
    // VERIFICAÇÃO: Determinar o tipo de cadastro incompleto
    $stmt = $mysqli->prepare("SELECT tipo, status_confirmacao, email FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $usuarioId);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();
    
    if ($usuario) {

      
      // APENAS VENDEDOR COM VEÍCULO INCOMPLETO
      if ($usuario['tipo'] === 'vendedor' && $status === 'incompleto') {
        // É cadastro de veículo incompleto
        header("Location: finalizar_cadastro.php?usuario_id=$usuarioId");
        exit;
      }
    }
  }
}

// ✅ Redireciona conforme o tipo
if (isset($_SESSION['usuario_id']) && isset($_SESSION['usuario_tipo'])) {
  switch ($_SESSION['usuario_tipo']) {
    case 'vendedor':
    case 'investidor':
      header("Location: painel_veiculos.php");
      break;
    case 'administrador':
      header("Location: painel_administrador.php");
      break;
    default:
      session_destroy();
      header("Location: login.php?erro=" . urlencode("Tipo de usuário inválido."));
  }
  exit;
}
?>



<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login - MotorGo</title>
  <link rel="stylesheet" href="style_login.css"/>
</head>
<body>
  <div class="login-container">
    <div class="login-image">
      <img src="imagens/login_bg.jpg" alt="Imagem de fundo"/>
    </div>
    <div class="login-form">
      <h2>Entrar em minha conta</h2>
      <p>Informe seu E-mail para continuar.</p>

      <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'cadastro_salvo'): ?>
          <div class="mensagem-sucesso" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center;">
            ✅ Suas informações foram salvas! Verifique seu email para continuar o cadastro.
          </div>
          <?php elseif ($_GET['msg'] === 'cadastro_confirmado'): ?>
  <div class="mensagem-sucesso" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center;">
    ✅ Cadastro confirmado com sucesso! Faça login para acessar seu painel.
  </div>
        <?php elseif ($_GET['msg'] === 'email_enviado'): ?>
          <div class="mensagem-sucesso" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center;">
            ✅ Email de recuperação enviado! Verifique sua caixa de entrada.
          </div>
        <?php elseif ($_GET['msg'] === 'senha_redefinida'): ?>
          <div class="mensagem-sucesso" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center;">
            ✅ Senha redefinida com sucesso! Faça login com sua nova senha.
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <form action="verificar_login.php" method="POST" id="formLogin">
        <label for="cpf_email">E-mail</label>
        <input type="text" name="cpf_email" id="cpf_email" placeholder="Digite seu E-mail" required/>

        <label for="senha">Senha</label>
        <div class="senha-wrapper">
          <input type="password" name="senha" id="senha" placeholder="Digite sua senha" required/>
          <span class="toggle-senha" onclick="toggleSenha()">
            <img src="imagens/eye.svg" alt="Mostrar senha" id="iconeOlho"/>
          </span>
        </div>

        <button type="submit" class="btn-login">Entrar</button>
      </form>

      <div class="extras">
        <a href="recuperar_senha.php">Esqueci minha senha</a>
      </div>
    </div>
  </div>

  <?php if (isset($_GET['erro'])): ?>
    <div id="popupMensagem" class="popup-mensagem" style="display: flex;">
      <div class="popup-conteudo">
        <span><?= htmlspecialchars($_GET['erro']) ?></span>
        <button onclick="document.getElementById('popupMensagem').style.display='none'" class="btn-vermelho btn-fechar">Fechar</button>
      </div>
    </div>
  <?php endif; ?>

  <script>
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
  </script>

  <div id="popupMensagem" class="popup-mensagem" style="display: none;">
    <div class="popup-conteudo">
      <span id="popupTexto"></span>
      <button onclick="document.getElementById('popupMensagem').style.display='none'" class="btn-vermelho btn-fechar">Fechar</button>
    </div>
  </div>
</body>
</html>