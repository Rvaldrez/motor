<?php
session_start();
require_once "conexao_bd.php";

// Mensagem padrão
$mensagem = "";

// Verificações de segurança
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $mensagem = "❌ Token inválido.";
} elseif (!isset($_SESSION['email_token']) || !isset($_SESSION['novo_email'])) {
    $mensagem = "❌ Sessão expirada. Solicite novamente a alteração de e-mail.";
} elseif ($_GET['token'] !== $_SESSION['email_token']) {
    $mensagem = "❌ Token incorreto. Solicite novamente.";
} else {
    $novo_email = $_SESSION['novo_email'];
    $usuario_id = $_SESSION['usuario_id'];

    $stmt = $mysqli->prepare("UPDATE usuarios SET email = ? WHERE id = ?");
    $stmt->bind_param("si", $novo_email, $usuario_id);

    try {
        $stmt->execute();
        unset($_SESSION['novo_email'], $_SESSION['email_token']);
        $mensagem = "✅ E-mail atualizado com sucesso!";
    } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate entry')) {
            $mensagem = "❌ Este e-mail já está cadastrado em outra conta.";
        } else {
            $mensagem = "❌ Ocorreu um erro ao atualizar o e-mail.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <title>Confirmação de E-mail | MotorGo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="style_dashboard.css" />

</head>
<body>

<!-- POPUP DE MENSAGEM -->
<div class="popup-mensagem">
  <div class="popup-conteudo">
    <span><?= htmlspecialchars($mensagem) ?></span>
    <button class="btn-fechar" onclick="window.location.href='painel_vendedor.php'">Voltar ao Painel</button>
  </div>
</div>

</body>
</html>
