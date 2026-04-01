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
if (!in_array(($_SESSION['tipo'] ?? ''), ['investidor', 'vendedor', 'administrador'], true)) {
    echo json_encode(['success' => false, 'message' => 'Acesso não permitido.']);
    exit;
}
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token inválido.']);
    exit;
}

$veiculo_id  = (int) ($_POST['veiculo_id'] ?? 0);
$valor_raw   = preg_replace('/[^\d,\.]/', '', $_POST['valor'] ?? '');
$valor       = (float) str_replace(',', '.', str_replace('.', '', $valor_raw));
$mensagem    = trim($_POST['mensagem'] ?? '');
$usuario_id  = (int) $_SESSION['usuario_id'];

if ($veiculo_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Veículo inválido.']);
    exit;
}
if ($valor <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valor da proposta inválido.']);
    exit;
}

$stmt = $conn->prepare(
    "SELECT v.id, v.usuario_id, v.em_negociacao, v.status, u.nome AS vendedor_nome, u.email AS vendedor_email
     FROM veiculos v JOIN usuarios u ON u.id = v.usuario_id
     WHERE v.id = ? LIMIT 1"
);
$stmt->bind_param('i', $veiculo_id);
$stmt->execute();
$veiculo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$veiculo) {
    echo json_encode(['success' => false, 'message' => 'Veículo não encontrado.']);
    exit;
}
if ((int) $veiculo['usuario_id'] === $usuario_id) {
    echo json_encode(['success' => false, 'message' => 'Você não pode enviar proposta para seu próprio veículo.']);
    exit;
}
if ($veiculo['em_negociacao']) {
    echo json_encode(['success' => false, 'message' => 'Este veículo já está em negociação.']);
    exit;
}
if (!in_array($veiculo['status'], ['completo', 'disponivel'], true)) {
    echo json_encode(['success' => false, 'message' => 'Veículo indisponível.']);
    exit;
}

$stmt = $conn->prepare(
    "SELECT id FROM propostas WHERE veiculo_id = ? AND usuario_id = ? AND status IN ('aguardando_vendedor','aguardando','aguardando_comprador','contraoferta','negociando') LIMIT 1"
);
$stmt->bind_param('ii', $veiculo_id, $usuario_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Você já tem uma proposta em aberto para este veículo.']);
    exit;
}
$stmt->close();

$stmt = $conn->prepare(
    "INSERT INTO propostas (veiculo_id, usuario_id, valor, data_proposta, status, mensagem)
     VALUES (?, ?, ?, NOW(), 'aguardando_vendedor', ?)"
);
$stmt->bind_param('iids', $veiculo_id, $usuario_id, $valor, $mensagem);
if (!$stmt->execute()) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Erro ao registrar proposta.']);
    exit;
}
$stmt->close();

// Notifica vendedor
$investidor_nome = $_SESSION['nome'] ?? 'Investidor';
$htmlBody = "
<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>
  <h2 style='color:#1a1a2e'>Nova proposta recebida – MotorGo</h2>
  <p>Olá, {$veiculo['vendedor_nome']}! Você recebeu uma nova proposta.</p>
  <p><strong>Investidor:</strong> {$investidor_nome}</p>
  <p><strong>Valor:</strong> " . formatMoney($valor) . "</p>
  " . ($mensagem !== '' ? "<p><strong>Mensagem:</strong> " . htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') . "</p>" : '') . "
  <p><a href='" . SITE_URL . "/painel.php' style='color:#e63946'>Ver proposta no painel</a></p>
</div>";
sendEmail($veiculo['vendedor_email'], $veiculo['vendedor_nome'], 'MotorGo – Nova proposta recebida', $htmlBody);

echo json_encode(['success' => true, 'message' => 'Proposta enviada com sucesso!']);
