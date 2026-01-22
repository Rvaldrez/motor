<?php
require 'conexao_bd.php';
header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['usuario_id'])) {
  echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
  exit;
}

$foto_id = $_POST['foto_id'] ?? null;

if (!$foto_id) {
  echo json_encode(['success' => false, 'message' => 'Foto não identificada']);
  exit;
}

// Busca foto
$stmt = $mysqli->prepare("SELECT caminho_foto, veiculo_id FROM fotos_veiculos WHERE id = ?");
$stmt->bind_param("i", $foto_id);
$stmt->execute();
$result = $stmt->get_result();
$foto = $result->fetch_assoc();

if (!$foto) {
  echo json_encode(['success' => false, 'message' => 'Foto não encontrada']);
  exit;
}

// Apagar no banco
$stmt = $mysqli->prepare("DELETE FROM fotos_veiculos WHERE id = ?");
$stmt->bind_param("i", $foto_id);
$stmt->execute();

// Apagar do servidor
if (file_exists($foto['caminho_foto'])) {
  unlink($foto['caminho_foto']);
}

echo json_encode(['success' => true]);
