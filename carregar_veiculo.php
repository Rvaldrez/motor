<?php
session_start();
require_once "conexao_bd.php";

header('Content-Type: application/json; charset=utf-8');

// Função auxiliar para enviar resposta e encerrar
function respostaJson($success, $mensagem, $dadosExtras = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $mensagem
    ], $dadosExtras));
    exit;
}

// ✅ Verifica sessão
if (!isset($_SESSION['usuario_id'])) {
    respostaJson(false, "Sessão expirada ou inválida. Faça login novamente.");
}

$usuario_id = $_SESSION['usuario_id'];
$veiculo_id = $_GET['id'] ?? null;

if (!$veiculo_id || !is_numeric($veiculo_id)) {
    respostaJson(false, "ID do veículo não fornecido ou inválido.");
}

// ✅ Busca o veículo do usuário
$stmt = $mysqli->prepare("SELECT * FROM veiculos WHERE id = ? AND usuario_id = ?");
$stmt->bind_param("ii", $veiculo_id, $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$veiculo = $result->fetch_assoc();

if (!$veiculo) {
    respostaJson(false, "Veículo não encontrado ou você não tem permissão.");
}

// ✅ Busca as fotos do veículo
$stmtFotos = $mysqli->prepare("SELECT caminho_foto FROM fotos_veiculos WHERE veiculo_id = ? ORDER BY ordem_exibicao");
$stmtFotos->bind_param("i", $veiculo_id);
$stmtFotos->execute();
$resultFotos = $stmtFotos->get_result();

$fotos = [];
while ($foto = $resultFotos->fetch_assoc()) {
    $fotos[] = $foto['caminho_foto'];
}

// ✅ Adiciona as fotos e thumbnail
$veiculo['fotos'] = $fotos;
$veiculo['caminho_foto'] = ($fotos[0] ?? 'imagens/default_car.png') . '?t=' . time(); // força atualização do cache

// ✅ Resposta final
respostaJson(true, "Veículo carregado com sucesso.", ['veiculo' => $veiculo]);
