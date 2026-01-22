<?php
include 'conexao_bd.php';

$codigo = $_POST['codigo'] ?? '';

$response = ['success' => false, 'message' => 'Código inválido ou expirado.'];

if ($codigo) {
    $stmt = $mysqli->prepare("SELECT * FROM codigos_convite WHERE codigo = ? AND valido_ate > NOW()");
    $stmt->bind_param("s", $codigo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $response['success'] = true;
    }

    $stmt->close();
}

echo json_encode($response);
?>
