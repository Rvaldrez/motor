<?php
session_start();
require_once "conexao_bd.php";

// Configurar header para JSON
header('Content-Type: application/json; charset=utf-8');

// Função para enviar resposta JSON
function enviarResposta($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// Receber dados
$email_cpf = trim($_POST['email'] ?? '');

// Validação básica
if (empty($email_cpf)) {
    enviarResposta(false, "Por favor, informe seu e-mail ou CPF.");
}

// Limpar CPF se for o caso
$cpf_limpo = preg_replace('/\D/', '', $email_cpf);
$is_cpf = strlen($cpf_limpo) === 11;

// Buscar usuário por email ou CPF
if ($is_cpf) {
    $stmt = $mysqli->prepare("SELECT id, nome, email FROM usuarios WHERE cpf = ?");
    $stmt->bind_param("s", $cpf_limpo);
} else {
    $stmt = $mysqli->prepare("SELECT id, nome, email FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email_cpf);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    if ($is_cpf) {
        enviarResposta(false, "CPF não encontrado em nossa base de dados.");
    } else {
        enviarResposta(false, "E-mail não encontrado em nossa base de dados.");
    }
}

$usuario = $result->fetch_assoc();
$stmt->close();

// Gerar token único
$token = bin2hex(random_bytes(32));
$token_expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

// Salvar token no banco
$stmt = $mysqli->prepare("UPDATE usuarios SET reset_token = ?, reset_token_expira = ? WHERE id = ?");
$stmt->bind_param("ssi", $token, $token_expira, $usuario['id']);

if (!$stmt->execute()) {
    enviarResposta(false, "Erro ao processar solicitação. Tente novamente.");
}
$stmt->close();

// Criar link de recuperação
$link = "https://" . $_SERVER['HTTP_HOST'] . "/redefinir_senha1.php?token=" . $token;

// Criar e-mail HTML
$assunto = "MotorGo - Recuperação de Senha";
$mensagem = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #b22222; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background:rgba(249, 249, 249, 0.87); padding: 30px; border-radius: 0 0 8px 8px; }
        .button { display: inline-block; padding: 12px 30px; background: #b22222; color: white !important; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Recuperação de Senha</h1>
        </div>
        <div class='content'>
            <p>Olá <strong>{$usuario['nome']}</strong>,</p>
            <p>Recebemos uma solicitação para redefinir sua senha na MotorGo.</p>
            <p>Para criar uma nova senha, clique no botão abaixo:</p>
            <center>
                <a href='{$link}' class='button'>Redefinir Minha Senha</a>
            </center>
            <p><small>Ou copie e cole este link no seu navegador:</small><br>
            <small>{$link}</small></p>
            <p><strong>⚠️ Este link expira em 1 hora.</strong></p>
            <p>Se você não solicitou esta recuperação, ignore este e-mail.</p>
        </div>
        <div class='footer'>
            <p>© 2024 MotorGo - Todos os direitos reservados</p>
        </div>
    </div>
</body>
</html>
";

// Configurar headers do e-mail
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= "From: MotorGo <noreply@motorgo.com.br>" . "\r\n";

// Enviar e-mail
if (mail($usuario['email'], $assunto, $mensagem, $headers)) {
    // Mensagem personalizada baseada no tipo de entrada
    if ($is_cpf) {
        $msg = "Link de recuperação enviado para o e-mail cadastrado com este CPF.\nVerifique sua caixa de entrada e spam.";
    } else {
        $msg = "Link de recuperação enviado!\nVerifique sua caixa de entrada e spam.";
    }
    enviarResposta(true, $msg);
} else {
    error_log("Erro ao enviar email de recuperação para: " . $usuario['email']);
    enviarResposta(false, "Erro ao enviar e-mail. Tente novamente em alguns minutos.");
}
?>