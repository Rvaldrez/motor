<?php
session_start();
header('Content-Type: application/json');
require_once "conexao_bd.php";

// ✅ Verifica método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

// ✅ Verifica autenticação e tipo

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_tipo'], ['vendedor', 'investidor'])) {
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}




$usuario_id = $_SESSION['usuario_id'];
$veiculo_id = intval($_POST['id'] ?? 0);

if ($veiculo_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do veículo inválido.']);
    exit;
}

// ✅ Verifica se o veículo pertence ao usuário
$sql_verifica = $mysqli->prepare("SELECT id FROM veiculos WHERE id = ? AND usuario_id = ?");
$sql_verifica->bind_param("ii", $veiculo_id, $usuario_id);
$sql_verifica->execute();
$result = $sql_verifica->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Veículo não encontrado ou não pertence a você.']);
    exit;
}

// ✅ Exclui fotos do servidor
$sql_fotos = $mysqli->prepare("SELECT caminho_foto FROM fotos_veiculos WHERE veiculo_id = ?");
$sql_fotos->bind_param("i", $veiculo_id);
$sql_fotos->execute();
$result_fotos = $sql_fotos->get_result();

while ($foto = $result_fotos->fetch_assoc()) {
    $caminho = $foto['caminho_foto'];
    if (is_file($caminho) && file_exists($caminho)) {
        unlink($caminho);
    }
}

// ✅ Transação para exclusão completa
$mysqli->begin_transaction();

try {
    // Exclui fotos do banco
    $stmt_fotos = $mysqli->prepare("DELETE FROM fotos_veiculos WHERE veiculo_id = ?");
    $stmt_fotos->bind_param("i", $veiculo_id);
    $stmt_fotos->execute();

    // Exclui propostas relacionadas
    $stmt_propostas = $mysqli->prepare("DELETE FROM propostas WHERE veiculo_id = ?");
    $stmt_propostas->bind_param("i", $veiculo_id);
    $stmt_propostas->execute();

    // Exclui o veículo
    $stmt_veiculo = $mysqli->prepare("DELETE FROM veiculos WHERE id = ?");
    $stmt_veiculo->bind_param("i", $veiculo_id);
    $stmt_veiculo->execute();

    $mysqli->commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir veículo.']);
}
