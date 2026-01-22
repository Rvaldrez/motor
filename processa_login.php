<?php
// 🚀 Ativar exibição de erros para depuração (REMOVA EM PRODUÇÃO)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 🚀 Configurar resposta como JSON antes de qualquer saída
header("Content-Type: application/json");

// 🚀 Incluir a conexão com o banco de dados
require_once "conexao_bd.php";

// 🚀 Verificar se a conexão com o banco foi carregada corretamente
if (!isset($mysqli) || $mysqli->connect_error) {
    echo json_encode([
        "success" => false,
        "message" => "Erro ao conectar ao banco de dados.",
        "error"   => $mysqli->connect_error
    ]);
    exit;
}

// 🚀 Capturar entrada do usuário
$email = trim($_POST["email"] ?? "");
$senha = trim($_POST["senha"] ?? "");

// 🚀 Validar se os campos foram preenchidos
if (empty($email) || empty($senha)) {
    echo json_encode(["success" => false, "message" => "Email e senha são obrigatórios."]);
    exit;
}

// 🚀 Buscar usuário no banco
$sql = "SELECT id, senha, nome, tipo, status_confirmacao FROM usuarios WHERE email = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Erro na consulta SQL.",
        "error"   => $mysqli->error
    ]);
    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

// 🚀 Verificar se o usuário existe
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $hash_senha = $row["senha"];

    // 🚀 Verificar se a conta está confirmada
    if ($row["status_confirmacao"] !== "confirmado") {
        echo json_encode(["success" => false, "message" => "Conta não confirmada. Verifique seu e-mail."]);
        exit;
    }

    // 🚀 Verificar a senha com `password_verify()`
    if (password_verify($senha, $hash_senha)) {
        // 🚀 Iniciar sessão se ainda não estiver iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['usuario_id']   = $row['id'];
        $_SESSION['nome_usuario'] = $row['nome'];
        $_SESSION['usuario_tipo'] = $row['tipo']; 
        

        echo json_encode([
            "success"    => true,
            "message"    => "Login efetuado com sucesso!",
            "usuario_id" => $row['id']  // ✅ Retorna o ID do usuário para uso no frontend
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Senha incorreta"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Usuário não encontrado"]);
}

// 🚀 Fechar Conexões
$stmt->close();
$mysqli->close();
?>
