<?php
session_start();
if (isset($_SESSION['usuario_id']) && isset($_SESSION['usuario_tipo'])) {
  switch ($_SESSION['usuario_tipo']) {
    case 'investidor':
    case 'vendedor':
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

      <form action="verificar_login.php" method="POST" id="formLogin">
        <label for="cpf_email">E-mail</label>
        <input type="text" name="cpf_email" id="cpf_email" placeholder="Digite seu CPF ou E-mail" required/>

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
      icone.src = "imagens/eye-off.svg"; // ícone de "olho fechado"
    } else {
      senhaInput.type = "password";
      icone.src = "imagens/eye.svg"; // ícone de "olho aberto"
    }
  }
</script>


</body>
</html>
