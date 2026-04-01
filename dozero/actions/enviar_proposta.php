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
$valor       = parseCurrency($_POST['valor'] ?? '');
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
$emailBody = "<p>Olá, <strong>" . htmlspecialchars($veiculo['vendedor_nome'], ENT_QUOTES, 'UTF-8') . "</strong>!</p>
<p>Você recebeu uma nova proposta para o seu veículo.</p>
<table cellpadding='0' cellspacing='0' style='margin:16px 0;'>
  <tr><td style='padding:5px 0;color:#6b7280;min-width:120px;'>Investidor</td>
      <td style='padding:5px 0;font-weight:bold;'>" . htmlspecialchars($investidor_nome, ENT_QUOTES, 'UTF-8') . "</td></tr>
  <tr><td style='padding:5px 0;color:#6b7280;'>Valor ofertado</td>
      <td style='padding:5px 0;font-weight:bold;color:#e63946;font-size:18px;'>" . formatMoney($valor) . "</td></tr>
" . ($mensagem !== '' ? "  <tr><td style='padding:5px 0;color:#6b7280;vertical-align:top;'>Mensagem</td>
      <td style='padding:5px 0;font-style:italic;'>\"" . htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') . "\"</td></tr>" : '') . "
</table>
<p style='color:#6b7280;font-size:13px;'>Acesse o painel para aceitar, recusar ou enviar uma contraproposta.</p>";
$htmlBody = buildEmailHtml('Nova proposta recebida', $emailBody, 'Ver proposta no painel', SITE_URL . '/painel.php?secao=propostas');
sendEmail($veiculo['vendedor_email'], $veiculo['vendedor_nome'], 'MotorGo – Nova proposta recebida', $htmlBody);

echo json_encode(['success' => true, 'message' => 'Proposta enviada com sucesso!']);
