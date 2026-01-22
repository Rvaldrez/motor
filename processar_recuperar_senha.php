<?php
require 'conexao_bd.php';
header('Content-Type: application/json');

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
  echo json_encode(['success' => false, 'message' => 'Informe seu e-mail ou CPF.']);
  exit;
}

$sql = $mysqli->prepare("SELECT id, email FROM usuarios WHERE email = ? OR cpf = ?");
$sql->bind_param("ss", $email, $email);
$sql->execute();
$result = $sql->get_result();

if ($result->num_rows === 0) {
  echo json_encode(['success' => false, 'message' => 'E-mail ou CPF não encontrado.']);
  exit;
}

$usuario = $result->fetch_assoc();
$token = bin2hex(random_bytes(32));

$update = $mysqli->prepare("UPDATE usuarios SET token_recuperacao = ?, token_expira = NOW() + INTERVAL 1 HOUR WHERE id = ?");
$update->bind_param("si", $token, $usuario['id']);
$update->execute();

$link = "https://motorgo.co/redefinir_senha_login.php?token=$token";

$assunto = "Redefinição de Senha - MotorGo";
$mensagem = "Olá,\n\nClique no link abaixo para redefinir sua senha:\n$link\n\nSe não solicitou, ignore este e-mail.";

$headers = "From: MotorGo <nao-responda@motorgo.co>\r\n";
$headers .= "Reply-To: suporte@motorgo.co\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

mail($usuario['email'], $assunto, $mensagem, $headers);

echo json_encode(['success' => true, 'message' => 'Um link foi enviado para seu e-mail.']);
