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

$proposta_id  = (int) ($_POST['proposta_id'] ?? 0);
$acao         = trim($_POST['acao']          ?? '');
$novo_valor_r = preg_replace('/[^\d,\.]/', '', $_POST['novo_valor'] ?? '');
$novo_valor   = (float) str_replace(',', '.', str_replace('.', '', $novo_valor_r));
$mensagem     = trim($_POST['mensagem']      ?? '');
$usuario_id   = (int) $_SESSION['usuario_id'];

if ($proposta_id <= 0 || !in_array($acao, ['aceitar', 'recusar', 'contraproposta'], true)) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$stmt = $conn->prepare(
    "SELECT p.id, p.veiculo_id, p.usuario_id AS investidor_id, p.valor, p.status,
            v.usuario_id AS vendedor_id,
            u_inv.nome AS investidor_nome, u_inv.email AS investidor_email
     FROM propostas p
     JOIN veiculos v ON v.id = p.veiculo_id
     JOIN usuarios u_inv ON u_inv.id = p.usuario_id
     WHERE p.id = ? LIMIT 1"
);
$stmt->bind_param('i', $proposta_id);
$stmt->execute();
$proposta = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$proposta) {
    echo json_encode(['success' => false, 'message' => 'Proposta não encontrada.']);
    exit;
}

$tipo = $_SESSION['tipo'] ?? '';
if ($tipo !== 'administrador' && (int) $proposta['vendedor_id'] !== $usuario_id) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão para responder esta proposta.']);
    exit;
}
if (!in_array($proposta['status'], ['aguardando_vendedor', 'contraoferta'], true)) {
    echo json_encode(['success' => false, 'message' => 'Esta proposta não pode ser respondida.']);
    exit;
}

$notify_subject = '';
$notify_html    = '';

if ($acao === 'aceitar') {
    $stmt = $conn->prepare("UPDATE propostas SET status = 'aceita' WHERE id = ?");
    $stmt->bind_param('i', $proposta_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE veiculos SET em_negociacao = 1 WHERE id = ?");
    $stmt->bind_param('i', $proposta['veiculo_id']);
    $stmt->execute();
    $stmt->close();

    $notify_subject = 'MotorGo – Sua proposta foi aceita!';
    $notify_html    = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'><h2 style='color:#1a1a2e'>Proposta aceita – MotorGo</h2><p>Olá, {$proposta['investidor_nome']}! Sua proposta de " . formatMoney((float) $proposta['valor']) . " foi <strong>aceita</strong>. Entre em contato pelo painel para finalizar o negócio.</p><p><a href='" . SITE_URL . "/dozero/painel.php' style='color:#e63946'>Acessar painel</a></p></div>";

} elseif ($acao === 'recusar') {
    $stmt = $conn->prepare("UPDATE propostas SET status = 'recusada' WHERE id = ?");
    $stmt->bind_param('i', $proposta_id);
    $stmt->execute();
    $stmt->close();

    $notify_subject = 'MotorGo – Sua proposta foi recusada';
    $notify_html    = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'><h2 style='color:#1a1a2e'>Proposta recusada – MotorGo</h2><p>Olá, {$proposta['investidor_nome']}! Sua proposta de " . formatMoney((float) $proposta['valor']) . " foi <strong>recusada</strong>. Você pode fazer uma nova oferta.</p><p><a href='" . SITE_URL . "/dozero/painel.php' style='color:#e63946'>Acessar painel</a></p></div>";

} elseif ($acao === 'contraproposta') {
    if ($novo_valor <= 0) {
        echo json_encode(['success' => false, 'message' => 'Informe um valor válido para a contraproposta.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE propostas SET status = 'contraoferta' WHERE id = ?");
    $stmt->bind_param('i', $proposta_id);
    $stmt->execute();
    $stmt->close();

    $investidor_id = (int) $proposta['investidor_id'];
    $veiculo_id    = (int) $proposta['veiculo_id'];
    $stmt = $conn->prepare(
        "INSERT INTO propostas (veiculo_id, usuario_id, valor, data_proposta, status, proposta_origem_id, mensagem)
         VALUES (?, ?, ?, NOW(), 'contraoferta', ?, ?)"
    );
    $stmt->bind_param('iidis', $veiculo_id, $investidor_id, $novo_valor, $proposta_id, $mensagem);
    $stmt->execute();
    $stmt->close();

    $notify_subject = 'MotorGo – Você recebeu uma contraproposta';
    $notify_html    = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'><h2 style='color:#1a1a2e'>Contraproposta – MotorGo</h2><p>Olá, {$proposta['investidor_nome']}! O vendedor enviou uma contraproposta de <strong>" . formatMoney($novo_valor) . "</strong>.</p>" . ($mensagem !== '' ? "<p><strong>Mensagem:</strong> " . htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') . "</p>" : '') . "<p><a href='" . SITE_URL . "/dozero/painel.php' style='color:#e63946'>Ver no painel</a></p></div>";
}

if ($notify_subject && $notify_html) {
    sendEmail($proposta['investidor_email'], $proposta['investidor_nome'], $notify_subject, $notify_html);
}

echo json_encode(['success' => true, 'message' => 'Resposta registrada com sucesso!']);
