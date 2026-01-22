<?php
require_once 'conexao_bd.php'; // Arquivo com a conexão ao BD

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $codigo = trim($_POST["codigo"]);

    if (empty($codigo)) {
        echo json_encode(["success" => false, "message" => "Código inválido."]);
        exit;
    }

    // Verifica se o código existe e está pendente
    $sql = "SELECT id FROM usuarios WHERE token_confirmacao = ? AND status_confirmacao = 'pendente'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $codigo);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Atualiza status para 'confirmado'
        $sql_update = "UPDATE usuarios SET status_confirmacao = 'confirmado', token_confirmacao = NULL WHERE token_confirmacao = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("s", $codigo);
        if ($stmt_update->execute()) {
            echo json_encode(["success" => true, "message" => "Conta verificada com sucesso!"]);
        } else {
            echo json_encode(["success" => false, "message" => "Erro ao confirmar a conta."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Código inválido ou expirado."]);
    }

    $stmt->close();
    $conn->close();
}
?>
