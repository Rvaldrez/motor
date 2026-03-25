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
$tipo         = $_SESSION['tipo'] ?? '';

$acoes_validas = ['aceitar', 'recusar', 'contraproposta', 'cancelar'];
if ($proposta_id <= 0 || !in_array($acao, $acoes_validas, true)) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

// ── Load proposal with vehicle owner + buyer info ──────────────
$stmt = $conn->prepare(
    "SELECT p.id, p.veiculo_id, p.usuario_id AS prop_usuario_id, p.valor, p.status, p.proposta_origem_id,
            v.usuario_id AS vendedor_id,
            u_prop.nome AS prop_usuario_nome, u_prop.email AS prop_usuario_email,
            u_vend.nome AS vendedor_nome, u_vend.email AS vendedor_email
     FROM propostas p
     JOIN veiculos v ON v.id = p.veiculo_id
     JOIN usuarios u_prop ON u_prop.id = p.usuario_id
     JOIN usuarios u_vend ON u_vend.id = v.usuario_id
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

$vendedor_id     = (int) $proposta['vendedor_id'];
$prop_usuario_id = (int) $proposta['prop_usuario_id'];

// Resolve the original buyer (root proposal owner)
$root_id           = $proposta['proposta_origem_id'] ? (int) $proposta['proposta_origem_id'] : (int) $proposta['id'];
$root_comprador_id = $prop_usuario_id;
if ($proposta['proposta_origem_id']) {
    $stmtR = $conn->prepare("SELECT usuario_id FROM propostas WHERE id = ? LIMIT 1");
    $stmtR->bind_param('i', $root_id);
    $stmtR->execute();
    $rootRow = $stmtR->get_result()->fetch_assoc();
    $stmtR->close();
    if ($rootRow) {
        $root_comprador_id = (int) $rootRow['usuario_id'];
    }
}

$isAdmin     = ($tipo === 'administrador');
$isVendedor  = $isAdmin || ($usuario_id === $vendedor_id);
$isComprador = ($usuario_id === $root_comprador_id);

if (!$isVendedor && !$isComprador) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão para responder esta proposta.']);
    exit;
}

// ══════════════════════════════════════════════════════════════
//  VENDEDOR actions: aceitar / recusar / contraproposta
//  on proposals waiting for the seller (aguardando_vendedor)
// ══════════════════════════════════════════════════════════════
if ($isVendedor && !$isComprador) {
    if (!in_array($proposta['status'], ['aguardando_vendedor', 'aguardando_comprador', 'aguardando', 'contraoferta'], true)) {
        echo json_encode(['success' => false, 'message' => 'Esta proposta não pode ser respondida.']);
        exit;
    }

    // Helper: who to notify (original buyer of the root proposal)
    $stmtBuyer = $conn->prepare(
        "SELECT u.nome, u.email FROM propostas p JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = ? LIMIT 1"
    );
    $stmtBuyer->bind_param('i', $root_id);
    $stmtBuyer->execute();
    $buyer = $stmtBuyer->get_result()->fetch_assoc();
    $stmtBuyer->close();

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

        if ($buyer) {
            $notify_subject = 'MotorGo – Sua proposta foi aceita!';
            $notify_html    = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'><h2 style='color:#1a1a2e'>Proposta aceita – MotorGo</h2><p>Olá, {$buyer['nome']}! Sua proposta de " . formatMoney((float) $proposta['valor']) . " foi <strong>aceita</strong>. Entre em contato pelo painel para finalizar o negócio.</p><p><a href='" . SITE_URL . "/painel.php' style='color:#e63946'>Acessar painel</a></p></div>";
        }

    } elseif ($acao === 'recusar') {
        $stmt = $conn->prepare("UPDATE propostas SET status = 'recusada' WHERE id = ?");
        $stmt->bind_param('i', $proposta_id);
        $stmt->execute();
        $stmt->close();

        if ($buyer) {
            $notify_subject = 'MotorGo – Sua proposta foi recusada';
            $notify_html    = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'><h2 style='color:#1a1a2e'>Proposta recusada – MotorGo</h2><p>Olá, {$buyer['nome']}! Sua proposta de " . formatMoney((float) $proposta['valor']) . " foi <strong>recusada</strong>. Você pode fazer uma nova oferta.</p><p><a href='" . SITE_URL . "/painel.php' style='color:#e63946'>Acessar painel</a></p></div>";
        }

    } elseif ($acao === 'contraproposta') {
        if ($novo_valor <= 0) {
            echo json_encode(['success' => false, 'message' => 'Informe um valor válido para a contraproposta.']);
            exit;
        }

        // Mark current proposal as "contraoferta" (waiting for buyer)
        $stmt = $conn->prepare("UPDATE propostas SET status = 'contraoferta' WHERE id = ?");
        $stmt->bind_param('i', $proposta_id);
        $stmt->execute();
        $stmt->close();

        // Insert counter-proposal row attributed to the original buyer
        // (indicates it is an offer *to* the buyer that they must accept/refuse)
        $stmt = $conn->prepare(
            "INSERT INTO propostas (veiculo_id, usuario_id, valor, data_proposta, status, proposta_origem_id, mensagem)
             VALUES (?, ?, ?, NOW(), 'contraoferta', ?, ?)"
        );
        $stmt->bind_param('iidis', $proposta['veiculo_id'], $root_comprador_id, $novo_valor, $root_id, $mensagem);
        $stmt->execute();
        $stmt->close();

        if ($buyer) {
            $notify_subject = 'MotorGo – Você recebeu uma contraproposta';
            $notify_html    = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'><h2 style='color:#1a1a2e'>Contraproposta – MotorGo</h2><p>Olá, {$buyer['nome']}! O vendedor enviou uma contraproposta de <strong>" . formatMoney($novo_valor) . "</strong>.</p>" . ($mensagem !== '' ? "<p><strong>Mensagem:</strong> " . htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') . "</p>" : '') . "<p><a href='" . SITE_URL . "/painel.php' style='color:#e63946'>Ver no painel</a></p></div>";
        }
    }

    if ($notify_subject && $notify_html && $buyer) {
        sendEmail($buyer['email'], $buyer['nome'], $notify_subject, $notify_html);
    }

    echo json_encode(['success' => true, 'message' => 'Resposta registrada com sucesso!']);
    exit;
}

// ══════════════════════════════════════════════════════════════
//  COMPRADOR/INVESTIDOR actions
// ══════════════════════════════════════════════════════════════
if ($isComprador) {
    // cancelar: buyer cancels their pending proposal
    if ($acao === 'cancelar') {
        if (!in_array($proposta['status'], ['aguardando_vendedor', 'aguardando_comprador', 'aguardando'], true)) {
            echo json_encode(['success' => false, 'message' => 'Proposta não pode ser cancelada neste momento.']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE propostas SET status = 'cancelada' WHERE id = ?");
        $stmt->bind_param('i', $proposta_id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Proposta cancelada.']);
        exit;
    }

    // aceitar / recusar / contraproposta: buyer responding to a counter-proposal row
    if (!in_array($proposta['status'], ['contraoferta'], true)) {
        echo json_encode(['success' => false, 'message' => 'Não há contraproposta pendente para responder.']);
        exit;
    }

    $notify_subject = '';
    $notify_html    = '';

    if ($acao === 'aceitar') {
        $stmt = $conn->prepare("UPDATE propostas SET status = 'aceita' WHERE id = ?");
        $stmt->bind_param('i', $proposta_id);
        $stmt->execute();
        $stmt->close();

        // Also mark root proposal as accepted
        $stmt = $conn->prepare("UPDATE propostas SET status = 'aceita' WHERE id = ?");
        $stmt->bind_param('i', $root_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE veiculos SET em_negociacao = 1 WHERE id = ?");
        $stmt->bind_param('i', $proposta['veiculo_id']);
        $stmt->execute();
        $stmt->close();

        $notify_subject = 'MotorGo – Contraproposta aceita!';
        $notify_html    = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'><h2 style='color:#1a1a2e'>Contraproposta aceita – MotorGo</h2><p>Olá, {$proposta['vendedor_nome']}! Sua contraproposta de " . formatMoney((float) $proposta['valor']) . " foi <strong>aceita</strong>. Entre em contato pelo painel para finalizar o negócio.</p><p><a href='" . SITE_URL . "/painel.php' style='color:#e63946'>Acessar painel</a></p></div>";
        sendEmail($proposta['vendedor_email'], $proposta['vendedor_nome'], $notify_subject, $notify_html);

        echo json_encode(['success' => true, 'message' => 'Contraproposta aceita!']);
        exit;

    } elseif ($acao === 'recusar') {
        $stmt = $conn->prepare("UPDATE propostas SET status = 'recusada' WHERE id = ?");
        $stmt->bind_param('i', $proposta_id);
        $stmt->execute();
        $stmt->close();

        $notify_subject = 'MotorGo – Contraproposta recusada';
        $notify_html    = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'><h2 style='color:#1a1a2e'>Contraproposta recusada – MotorGo</h2><p>Olá, {$proposta['vendedor_nome']}! O comprador recusou sua contraproposta. Você pode enviar uma nova oferta.</p><p><a href='" . SITE_URL . "/painel.php' style='color:#e63946'>Acessar painel</a></p></div>";
        sendEmail($proposta['vendedor_email'], $proposta['vendedor_nome'], $notify_subject, $notify_html);

        echo json_encode(['success' => true, 'message' => 'Contraproposta recusada.']);
        exit;

    } elseif ($acao === 'contraproposta') {
        if ($novo_valor <= 0) {
            echo json_encode(['success' => false, 'message' => 'Informe um valor válido para a contraproposta.']);
            exit;
        }

        // Mark current counter as superseded
        $stmt = $conn->prepare("UPDATE propostas SET status = 'negociando' WHERE id = ?");
        $stmt->bind_param('i', $proposta_id);
        $stmt->execute();
        $stmt->close();

        // Insert new proposal from buyer back to seller
        $stmt = $conn->prepare(
            "INSERT INTO propostas (veiculo_id, usuario_id, valor, data_proposta, status, proposta_origem_id, mensagem)
             VALUES (?, ?, ?, NOW(), 'aguardando_vendedor', ?, ?)"
        );
        $stmt->bind_param('iidis', $proposta['veiculo_id'], $root_comprador_id, $novo_valor, $root_id, $mensagem);
        $stmt->execute();
        $stmt->close();

        $notify_subject = 'MotorGo – Nova contraproposta recebida';
        $notify_html    = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'><h2 style='color:#1a1a2e'>Nova contraproposta – MotorGo</h2><p>Olá, {$proposta['vendedor_nome']}! O comprador enviou uma nova contraproposta de <strong>" . formatMoney($novo_valor) . "</strong>.</p>" . ($mensagem !== '' ? "<p><strong>Mensagem:</strong> " . htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') . "</p>" : '') . "<p><a href='" . SITE_URL . "/painel.php' style='color:#e63946'>Ver no painel</a></p></div>";
        sendEmail($proposta['vendedor_email'], $proposta['vendedor_nome'], $notify_subject, $notify_html);

        echo json_encode(['success' => true, 'message' => 'Contraproposta enviada com sucesso!']);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Ação não permitida.']);

