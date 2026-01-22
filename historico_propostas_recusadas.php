<?php
session_start();
require_once "conexao_bd.php";

header("Content-Type: application/json");

// ✅ Requer autenticação mínima
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(["success" => false, "message" => "Acesso negado."]);
    exit;
}

$veiculo_id = $_GET['veiculo_id'] ?? null;

if (!$veiculo_id || !is_numeric($veiculo_id)) {
    echo json_encode(["success" => false, "message" => "ID do veículo inválido."]);
    exit;
}

// ✅ Busca propostas recusadas do veículo
$sql = "SELECT valor, data_proposta 
        FROM propostas 
        WHERE veiculo_id = ? AND status = 'recusada'
        ORDER BY data_proposta DESC";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $veiculo_id);
$stmt->execute();
$result = $stmt->get_result();

$historico = [];
while ($row = $result->fetch_assoc()) {
    $historico[] = [
        "valor" => number_format($row['valor'], 2, ',', '.'),
        "data"  => date('d/m/Y H:i', strtotime($row['data_proposta']))
    ];
}

echo json_encode([
    "success" => true,
    "historico" => $historico
]);
