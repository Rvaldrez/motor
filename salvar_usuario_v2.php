<?php
// Configuração inicial
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Mas capturamos os erros
ob_start();

// Função para enviar JSON
function enviarJSON($dados) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados);
    exit();
}

// Testa se recebeu dados
if (empty($_POST)) {
    enviarJSON([
        "success" => false,
        "message" => "Nenhum dado recebido",
        "method" => $_SERVER['REQUEST_METHOD']
    ]);
}

// Tenta conectar com o banco
try {
    session_start();
    
    // Verifica se o arquivo existe
    if (!file_exists("conexao_bd.php")) {
        enviarJSON([
            "success" => false,
            "message" => "Arquivo conexao_bd.php não encontrado"
        ]);
    }
    
    // Inclui conexão
    require_once "conexao_bd.php";
    
    // Verifica conexão
    if (!isset($mysqli)) {
        enviarJSON([
            "success" => false,
            "message" => "Variável mysqli não definida"
        ]);
    }
    
    if ($mysqli->connect_error) {
        enviarJSON([
            "success" => false,
            "message" => "Erro de conexão: " . $mysqli->connect_error
        ]);
    }
    
} catch (Exception $e) {
    enviarJSON([
        "success" => false,
        "message" => "Exceção: " . $e->getMessage()
    ]);
}

// Validação básica
if (empty($_POST['nome']) || empty($_POST['email'])) {
    enviarJSON([
        "success" => false,
        "message" => "Nome e email são obrigatórios"
    ]);
}

// Prepara dados
$nome = $_POST['nome'] ?? '';
$email = $_POST['email'] ?? '';
$cpf = $_POST['cpf'] ?? '';
$cep = $_POST['cep'] ?? '';
$celular = $_POST['celular'] ?? '';
$senha = password_hash($_POST['senha'] ?? '123456', PASSWORD_DEFAULT);
$endereco = $_POST['endereco'] ?? '';
$cidade = $_POST['cidade'] ?? '';
$estado = $_POST['estado'] ?? '';

// Tenta inserir
try {
    $sql = "INSERT INTO usuarios (
        nome, email, celular, cpf, cep, endereco, cidade, estado, senha,
        tipo, status_cadastro, status_confirmacao, termo_aceito
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?,
        'vendedor', 'incompleto', 'pendente', 1
    )";
    
    $stmt = $mysqli->prepare($sql);
    
    if (!$stmt) {
        enviarJSON([
            "success" => false,
            "message" => "Erro ao preparar: " . $mysqli->error
        ]);
    }
    
    $stmt->bind_param(
        "sssssssss",
        $nome, $email, $celular, $cpf, $cep,
        $endereco, $cidade, $estado, $senha
    );
    
    if ($stmt->execute()) {
        $usuario_id = $mysqli->insert_id;
        
        $_SESSION['usuario_id'] = $usuario_id;
        $_SESSION['usuario_nome'] = $nome;
        
        enviarJSON([
            "success" => true,
            "message" => "Usuário cadastrado com sucesso!",
            "usuario_id" => $usuario_id
        ]);
    } else {
        enviarJSON([
            "success" => false,
            "message" => "Erro ao executar: " . $stmt->error
        ]);
    }
    
} catch (Exception $e) {
    enviarJSON([
        "success" => false,
        "message" => "Exceção SQL: " . $e->getMessage()
    ]);
}
?>