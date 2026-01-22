<?php
session_start();
require_once 'conexao_bd.php';
require_once 'helpers/email_proposta.php';

header('Content-Type: application/json');

function gerarToken() {
    return str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
}

$email = $_POST['email'] ?? '';
$usuario_id = $_POST['usuario_id'] ?? '';

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email não fornecido.']);
    exit;
}

// Gerar NOVO token
$novo_token = gerarToken();

// Atualizar token e buscar dados do usuário
$stmt = $mysqli->prepare("UPDATE usuarios SET token_confirmacao = ? 
                          WHERE email = ? AND status_confirmacao = 'pendente'");
$stmt->bind_param("ss", $novo_token, $email);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    // Buscar nome do usuário
    $stmt2 = $mysqli->prepare("SELECT nome FROM usuarios WHERE email = ?");
    $stmt2->bind_param("s", $email);
    $stmt2->execute();
    $result = $stmt2->get_result();
    $usuario = $result->fetch_assoc();
    
    $nome = $usuario['nome'];
    $nomePrimeiro = explode(' ', $nome)[0];
    
    // Enviar email com NOVO token
    $mensagem = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; border:1px solid #ccc; background-color: #fff'>
            <div style='background:#1A1A1A;padding:20px;text-align:center'>
                <img src='https://motorgo.co/imagens/logo_motorgo_blk.png' alt='MotorGo' style='max-width:180px' />
            </div>
            <div style='padding: 30px'>
                <h2 style='color:#B22222;'>Novo Código de Confirmação</h2>
                <p>Olá <strong>$nomePrimeiro</strong>,</p>
                <p>Você solicitou um novo código. Seu código de verificação é:</p>
                <div style='text-align:center; background:#f4f4f4; padding:20px; margin:20px 0; border-radius:8px;'>
                    <h1 style='color:#B22222; letter-spacing:5px; margin:0;'>$novo_token</h1>
                </div>
                <p>Digite este código no site para concluir seu cadastro.</p>
            </div>
        </div>
    ";
    
    if (enviarEmailProposta($email, $nome, "Novo Código - MotorGo", $mensagem)) {
        echo json_encode(['success' => true, 'message' => 'Novo código enviado com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao enviar email.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado ou já confirmado.']);
}
?>