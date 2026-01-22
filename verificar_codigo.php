<?php
header("Content-Type: application/json; charset=UTF-8");
session_start();

require 'conexao_bd.php';

if (!$mysqli || $mysqli->connect_error) {
    echo json_encode(["success" => false, "message" => "Erro de conexão com o banco."]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"] ?? "");
    $codigoDigitado = trim($_POST["codigo"] ?? "");

    if (empty($email) || empty($codigoDigitado)) {
        echo json_encode(["success" => false, "message" => "E-mail ou código não informado."]);
        exit;
    }

    $query = "SELECT * FROM usuarios WHERE email = ? AND token_confirmacao = ? AND status_confirmacao = 'pendente'";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ss", $email, $codigoDigitado);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $usuario = $result->fetch_assoc();
        $usuario_id = $usuario['id'];

        // ✅ Atualiza status e tipo
        $updateQuery = "UPDATE usuarios 
                        SET status_confirmacao = 'confirmado', 
                            tipo = COALESCE(tipo, 'vendedor') 
                        WHERE id = ?";
        $stmt = $mysqli->prepare($updateQuery);
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();

        // ✅ Armazena na sessão se desejar reutilizar
        $_SESSION['usuario_id'] = $usuario_id;

        echo json_encode([
            "success" => true,
            "message" => "Cadastro confirmado com sucesso!",
            "usuario_id" => $usuario_id
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Código inválido ou expirado."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Requisição inválida."]);
}
?>
