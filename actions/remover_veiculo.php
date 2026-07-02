<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
require_once '../includes/config.php';
require_once '../includes/conexao.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token inválido.']);
    exit;
}

$id         = (int) ($_POST['id'] ?? 0);
$usuario_id = (int) $_SESSION['usuario_id'];
$tipo       = $_SESSION['tipo'] ?? '';

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

if ($tipo === 'administrador') {
    $stmt = $conn->prepare("SELECT id, em_negociacao FROM veiculos WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
} else {
    $stmt = $conn->prepare("SELECT id, em_negociacao FROM veiculos WHERE id = ? AND usuario_id = ? LIMIT 1");
    $stmt->bind_param('ii', $id, $usuario_id);
}
$stmt->execute();
$veiculo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$veiculo) {
    echo json_encode(['success' => false, 'message' => 'Veículo não encontrado ou sem permissão.']);
    exit;
}
if ($veiculo['em_negociacao']) {
    echo json_encode(['success' => false, 'message' => 'Não é possível remover um veículo em negociação.']);
    exit;
}

// Remove fotos físicas e registros
$stmt = $conn->prepare("SELECT caminho_foto FROM fotos_veiculos WHERE veiculo_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$fotos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($fotos as $foto) {
    deleteFile(UPLOAD_DIR . $foto['caminho_foto']);
}

$stmt = $conn->prepare("DELETE FROM fotos_veiculos WHERE veiculo_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("DELETE FROM veiculos WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'message' => 'Veículo removido com sucesso.']);
