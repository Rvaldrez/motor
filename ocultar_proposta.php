<?php
session_start();
require_once "conexao_bd.php";
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['usuario_id'], $_SESSION['usuario_tipo'])) {
    echo json_encode(["success" => false, "message" => "Acesso negado."]);
    exit;
}

$usuario_id    = $_SESSION['usuario_id'];
$usuario_tipo  = $_SESSION['usuario_tipo'];
$proposta_id   = $_POST['proposta_id'] ?? null;
$novo_status   = $_POST['novo_status'] ?? null;

if (!$proposta_id || !$novo_status) {
    echo json_encode(["success" => false, "message" => "Dados incompletos."]);
    exit;
}

$sql = "
    SELECT p.id, p.status, p.usuario_id AS investidor_id, v.usuario_id AS vendedor_id
    FROM propostas p
    JOIN veiculos v ON v.id = p.veiculo_id
    WHERE p.id = ? AND (p.usuario_id = ? OR v.usuario_id = ?)
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("iii", $proposta_id, $usuario_id, $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Proposta não encontrada ou não pertence a você."]);
    exit;
}

$proposta = $result->fetch_assoc();
$statusAtual = $proposta['status'];

$permitidos = [
    'historico_recusada_vendedor',
    'historico_recusada_investidor'
];

if (!in_array($novo_status, $permitidos)) {
    echo json_encode(["success" => false, "message" => "Status inválido."]);
    exit;
}

// Só permite se status atual for uma recusa
if ($statusAtual !== 'recusada_vendedor' && $statusAtual !== 'recusada_investidor') {
    echo json_encode(["success" => false, "message" => "Essa proposta não pode ser movida para o histórico."]);
    exit;
}

// Atualiza
$update = $mysqli->prepare("UPDATE propostas SET status = ? WHERE id = ?");
$update->bind_param("si", $novo_status, $proposta_id);

if ($update->execute()) {
    echo json_encode(["success" => true, "message" => "Proposta movida para o histórico."]);
} else {
    echo json_encode(["success" => false, "message" => "Erro ao atualizar proposta."]);
}

$update->close();
