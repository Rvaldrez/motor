<?php
session_start();
require_once 'conexao_bd.php';

header('Content-Type: application/json');

$email = $_POST['email'] ?? '';
$codigo = $_POST['codigo'] ?? '';
$usuario_id = $_POST['usuario_id'] ?? '';

// Limpar código
$codigo = trim($codigo);

if (empty($email) || empty($codigo)) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit;
}

// Verificar código
$stmt = $mysqli->prepare("SELECT id, nome FROM usuarios 
                          WHERE email = ? AND token_confirmacao = ? 
                          AND status_confirmacao = 'pendente'");
$stmt->bind_param("ss", $email, $codigo);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $usuario = $result->fetch_assoc();
    
    // Atualizar status
    $stmt_update = $mysqli->prepare("UPDATE usuarios 
                                     SET status_confirmacao = 'confirmado',
                                         status_cadastro = 'completo',
                                         tipo = 'investidor'
                                     WHERE id = ?");
    $stmt_update->bind_param("i", $usuario['id']);
    
    if ($stmt_update->execute()) {
        // NÃO CRIAR SESSÃO AQUI - deixar o usuário fazer login
        // Remover estas linhas:
        // $_SESSION['usuario_id'] = $usuario['id'];
        // $_SESSION['usuario_nome'] = $usuario['nome'];
        // $_SESSION['usuario_tipo'] = 'investidor';
        
        echo json_encode([
            'success' => true, 
            'message' => 'Cadastro confirmado!',
            'redirect_login' => true  // Nova flag
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao confirmar cadastro.']);
    }
} else {
    // Verificar se já foi confirmado
    $stmt_check = $mysqli->prepare("SELECT status_confirmacao FROM usuarios WHERE email = ?");
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $check_result = $stmt_check->get_result();
    
    if ($check_result->num_rows > 0) {
        $check_data = $check_result->fetch_assoc();
        if ($check_data['status_confirmacao'] == 'confirmado') {
            echo json_encode(['success' => false, 'message' => 'Cadastro já confirmado anteriormente.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Código inválido.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Email não encontrado.']);
    }
}
?>