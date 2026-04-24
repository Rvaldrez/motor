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

// Ensure proposta_origem_id column exists (migration for old DBs)
$_chk = $conn->query("SHOW COLUMNS FROM propostas LIKE 'proposta_origem_id'");
if ($_chk && $_chk->num_rows === 0) {
    $conn->query("ALTER TABLE propostas ADD COLUMN proposta_origem_id INT(11) DEFAULT NULL, ADD INDEX idx_propostas_origem (proposta_origem_id)");
}
if ($_chk) { $_chk->free(); }

$proposta_id  = (int) ($_POST['proposta_id'] ?? 0);
$acao         = trim($_POST['acao']          ?? '');
$novo_valor   = parseCurrency($_POST['novo_valor'] ?? '');
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
            u_prop.nome AS prop_usuario_nome, u_prop.email AS prop_usuario_email, u_prop.celular AS prop_usuario_celular,
            u_vend.nome AS vendedor_nome, u_vend.email AS vendedor_email, u_vend.celular AS vendedor_celular
     FROM propostas p
     JOIN veiculos v ON v.id = p.veiculo_id
     LEFT JOIN usuarios u_prop ON u_prop.id = p.usuario_id
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
    if (!in_array($proposta['status'], ['aguardando_vendedor', 'aguardando', 'pendente', 'contraproposta_comprador', 'resposta_comprador'], true)) {
        echo json_encode(['success' => false, 'message' => 'Esta proposta não pode ser respondida.']);
        exit;
    }

    // Helper: who to notify (original buyer of the root proposal)
    $stmtBuyer = $conn->prepare(
        "SELECT u.nome, u.email, u.celular FROM propostas p JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = ? LIMIT 1"
    );
    $stmtBuyer->bind_param('i', $root_id);
    $stmtBuyer->execute();
    $buyer = $stmtBuyer->get_result()->fetch_assoc();
    $stmtBuyer->close();

    if ($acao === 'aceitar') {
        // Mark this proposal as accepted
        $stmt = $conn->prepare("UPDATE propostas SET status = 'aceita' WHERE id = ?");
        $stmt->bind_param('i', $proposta_id);
        $stmt->execute();
        $stmt->close();

        // Also mark root proposal as accepted for consistent display
        if ((int)$proposta_id !== $root_id) {
            $stmt = $conn->prepare("UPDATE propostas SET status = 'aceita' WHERE id = ?");
            $stmt->bind_param('i', $root_id);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $conn->prepare("UPDATE veiculos SET em_negociacao = 1 WHERE id = ?");
        $stmt->bind_param('i', $proposta['veiculo_id']);
        $stmt->execute();
        $stmt->close();

        // Email buyer with seller's contact info
        if ($buyer) {
            $vendNome    = htmlspecialchars($proposta['vendedor_nome'],    ENT_QUOTES, 'UTF-8');
            $vendEmail   = htmlspecialchars($proposta['vendedor_email'],   ENT_QUOTES, 'UTF-8');
            $vendCelular = htmlspecialchars($proposta['vendedor_celular'] ?? '', ENT_QUOTES, 'UTF-8');
            $contactBox  = "<div style='background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:16px;margin:16px 0;'>
  <p style='margin:0 0 8px;font-weight:bold;color:#166534;'>📞 Dados do Vendedor</p>
  <table cellpadding='0' cellspacing='0'>
    <tr><td style='padding:3px 12px 3px 0;color:#374151;'>Nome:</td><td style='padding:3px 0;font-weight:bold;'>{$vendNome}</td></tr>
    <tr><td style='padding:3px 12px 3px 0;color:#374151;'>E-mail:</td><td style='padding:3px 0;'>{$vendEmail}</td></tr>" .
    ($vendCelular !== '' ? "<tr><td style='padding:3px 12px 3px 0;color:#374151;'>Celular:</td><td style='padding:3px 0;'>{$vendCelular}</td></tr>" : '') . "
  </table>
</div>";
            $bodyBuyer = "<p>Olá, <strong>" . htmlspecialchars($buyer['nome'], ENT_QUOTES, 'UTF-8') . "</strong>!</p>
<p>🎉 Boa notícia! Sua proposta de <strong style='color:#e63946;'>" . formatMoney((float) $proposta['valor']) . "</strong> foi <strong>aceita</strong>.</p>
<p>Entre em contato com o vendedor para finalizar o negócio:</p>
{$contactBox}";
            sendEmail($buyer['email'], $buyer['nome'], 'MotorGo – Sua proposta foi aceita!',
                buildEmailHtml('Proposta Aceita! 🎉', $bodyBuyer, 'Acessar painel', SITE_URL . '/painel.php?secao=propostas'));
        }

        // Email vendor with buyer's contact info
        if ($buyer) {
            $buyNome    = htmlspecialchars($buyer['nome'],    ENT_QUOTES, 'UTF-8');
            $buyEmail   = htmlspecialchars($buyer['email'],   ENT_QUOTES, 'UTF-8');
            $buyCelular = htmlspecialchars($buyer['celular'] ?? '', ENT_QUOTES, 'UTF-8');
            $contactBoxV = "<div style='background:#eff6ff;border:1px solid #93c5fd;border-radius:6px;padding:16px;margin:16px 0;'>
  <p style='margin:0 0 8px;font-weight:bold;color:#1d4ed8;'>📞 Dados do Comprador</p>
  <table cellpadding='0' cellspacing='0'>
    <tr><td style='padding:3px 12px 3px 0;color:#374151;'>Nome:</td><td style='padding:3px 0;font-weight:bold;'>{$buyNome}</td></tr>
    <tr><td style='padding:3px 12px 3px 0;color:#374151;'>E-mail:</td><td style='padding:3px 0;'>{$buyEmail}</td></tr>" .
    ($buyCelular !== '' ? "<tr><td style='padding:3px 12px 3px 0;color:#374151;'>Celular:</td><td style='padding:3px 0;'>{$buyCelular}</td></tr>" : '') . "
  </table>
</div>";
            $bodyVend = "<p>Olá, <strong>" . htmlspecialchars($proposta['vendedor_nome'], ENT_QUOTES, 'UTF-8') . "</strong>!</p>
<p>Você aceitou uma proposta de <strong style='color:#e63946;'>" . formatMoney((float) $proposta['valor']) . "</strong>.</p>
<p>Entre em contato com o comprador para finalizar o negócio:</p>
{$contactBoxV}";
            sendEmail($proposta['vendedor_email'], $proposta['vendedor_nome'], 'MotorGo – Proposta aceita',
                buildEmailHtml('Proposta Aceita! 🎉', $bodyVend, 'Acessar painel', SITE_URL . '/painel.php?secao=propostas'));
        }

        echo json_encode(['success' => true, 'message' => 'Proposta aceita! Os dados de contato foram enviados por e-mail.']);
        exit;

    } elseif ($acao === 'recusar') {
        $stmt = $conn->prepare("UPDATE propostas SET status = 'recusada' WHERE id = ?");
        $stmt->bind_param('i', $proposta_id);
        $stmt->execute();
        $stmt->close();

        // Also update the root proposal to recusada (so investidor sees terminal state)
        $stmt = $conn->prepare("UPDATE propostas SET status = 'recusada' WHERE id = ?");
        $stmt->bind_param('i', $root_id);
        $stmt->execute();
        $stmt->close();

        // Unlock the vehicle (allow new proposals)
        $stmt = $conn->prepare("UPDATE veiculos SET em_negociacao = 0 WHERE id = ?");
        $stmt->bind_param('i', $proposta['veiculo_id']);
        $stmt->execute();
        $stmt->close();

        if ($buyer) {
            $bodyRecused = "<p>Olá, <strong>" . htmlspecialchars($buyer['nome'], ENT_QUOTES, 'UTF-8') . "</strong>!</p>
<p>Infelizmente sua proposta de <strong>" . formatMoney((float) $proposta['valor']) . "</strong> foi <strong>recusada</strong>.</p>
<p>Você pode enviar uma nova oferta com um valor diferente ou buscar outros veículos disponíveis.</p>";
            sendEmail($buyer['email'], $buyer['nome'], 'MotorGo – Proposta recusada',
                buildEmailHtml('Proposta Recusada', $bodyRecused, 'Ver veículos disponíveis', SITE_URL . '/painel.php?secao=propostas'));
        }

        echo json_encode(['success' => true, 'message' => 'Proposta recusada.']);
        exit;

    } elseif ($acao === 'contraproposta') {
        if ($novo_valor <= 0) {
            echo json_encode(['success' => false, 'message' => 'Informe um valor válido para a contraproposta.']);
            exit;
        }

        // Mark current proposal as waiting for buyer response
        $stmt = $conn->prepare("UPDATE propostas SET status = 'contraoferta' WHERE id = ?");
        $stmt->bind_param('i', $proposta_id);
        $stmt->execute();
        $stmt->close();

        // Insert counter-proposal row directed to the original buyer
        $stmt = $conn->prepare(
            "INSERT INTO propostas (veiculo_id, usuario_id, valor, data_proposta, status, proposta_origem_id, mensagem)
             VALUES (?, ?, ?, NOW(), 'contraoferta', ?, ?)"
        );
        $stmt->bind_param('iidis', $proposta['veiculo_id'], $root_comprador_id, $novo_valor, $root_id, $mensagem);
        $stmt->execute();
        $stmt->close();

        if ($buyer) {
            $bodyContra = "<p>Olá, <strong>" . htmlspecialchars($buyer['nome'], ENT_QUOTES, 'UTF-8') . "</strong>!</p>
<p>O vendedor enviou uma <strong>contraproposta</strong>:</p>
<table cellpadding='0' cellspacing='0' style='margin:16px 0;'>
  <tr><td style='padding:5px 0;color:#6b7280;min-width:120px;'>Novo valor</td>
      <td style='padding:5px 0;font-weight:bold;color:#e63946;font-size:18px;'>" . formatMoney($novo_valor) . "</td></tr>" .
($mensagem !== '' ? "  <tr><td style='padding:5px 0;color:#6b7280;vertical-align:top;'>Mensagem</td>
      <td style='padding:5px 0;font-style:italic;'>\"" . htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') . "\"</td></tr>" : '') . "
</table>
<p>Acesse o painel para aceitar, recusar ou enviar uma nova proposta.</p>";
            sendEmail($buyer['email'], $buyer['nome'], 'MotorGo – Contraproposta recebida',
                buildEmailHtml('Contraproposta Recebida', $bodyContra, 'Ver no painel', SITE_URL . '/painel.php?secao=propostas'));
        }

        echo json_encode(['success' => true, 'message' => 'Contraproposta enviada com sucesso!']);
        exit;
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
        if (!in_array($proposta['status'], ['aguardando_vendedor', 'aguardando_comprador', 'aguardando', 'pendente'], true)) {
            echo json_encode(['success' => false, 'message' => 'Proposta não pode ser cancelada neste momento.']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE propostas SET status = 'cancelada' WHERE id = ?");
        $stmt->bind_param('i', $proposta_id);
        $stmt->execute();
        $stmt->close();

        // Also mark root proposal as cancelled for consistent display
        if ((int)$proposta_id !== $root_id) {
            $stmt = $conn->prepare("UPDATE propostas SET status = 'cancelada' WHERE id = ?");
            $stmt->bind_param('i', $root_id);
            $stmt->execute();
            $stmt->close();
        }

        // Unlock vehicle: allow new proposals from other buyers
        $stmt = $conn->prepare("UPDATE veiculos SET em_negociacao = 0 WHERE id = ?");
        $stmt->bind_param('i', $proposta['veiculo_id']);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Proposta cancelada.']);
        exit;
    }

    // aceitar / recusar / contraproposta: buyer responding to a counter-proposal row
    // Also handles old-system 'aguardando_comprador' status (vendor sent counter by updating same row)
    if (!in_array($proposta['status'], ['contraoferta', 'aguardando_comprador'], true)) {
        echo json_encode(['success' => false, 'message' => 'Não há contraproposta pendente para responder.']);
        exit;
    }

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

        // Fetch buyer info (current user)
        $stmtBuyer2 = $conn->prepare("SELECT nome, email, celular FROM usuarios WHERE id = ? LIMIT 1");
        $stmtBuyer2->bind_param('i', $root_comprador_id);
        $stmtBuyer2->execute();
        $buyerInfo = $stmtBuyer2->get_result()->fetch_assoc();
        $stmtBuyer2->close();

        // Email vendor with buyer's contact info
        $buyNome    = htmlspecialchars($buyerInfo['nome']    ?? '', ENT_QUOTES, 'UTF-8');
        $buyEmail   = htmlspecialchars($buyerInfo['email']   ?? '', ENT_QUOTES, 'UTF-8');
        $buyCelular = htmlspecialchars($buyerInfo['celular'] ?? '', ENT_QUOTES, 'UTF-8');
        $contactBoxV = "<div style='background:#eff6ff;border:1px solid #93c5fd;border-radius:6px;padding:16px;margin:16px 0;'>
  <p style='margin:0 0 8px;font-weight:bold;color:#1d4ed8;'>📞 Dados do Comprador</p>
  <table cellpadding='0' cellspacing='0'>
    <tr><td style='padding:3px 12px 3px 0;color:#374151;'>Nome:</td><td style='padding:3px 0;font-weight:bold;'>{$buyNome}</td></tr>
    <tr><td style='padding:3px 12px 3px 0;color:#374151;'>E-mail:</td><td style='padding:3px 0;'>{$buyEmail}</td></tr>" .
($buyCelular !== '' ? "<tr><td style='padding:3px 12px 3px 0;color:#374151;'>Celular:</td><td style='padding:3px 0;'>{$buyCelular}</td></tr>" : '') . "
  </table>
</div>";
        $bodyVend = "<p>Olá, <strong>" . htmlspecialchars($proposta['vendedor_nome'], ENT_QUOTES, 'UTF-8') . "</strong>!</p>
<p>🎉 Sua contraproposta de <strong style='color:#e63946;'>" . formatMoney((float) $proposta['valor']) . "</strong> foi <strong>aceita</strong>!</p>
<p>Entre em contato com o comprador para finalizar o negócio:</p>
{$contactBoxV}";
        sendEmail($proposta['vendedor_email'], $proposta['vendedor_nome'], 'MotorGo – Contraproposta aceita!',
            buildEmailHtml('Contraproposta Aceita! 🎉', $bodyVend, 'Acessar painel', SITE_URL . '/painel.php?secao=propostas'));

        // Email buyer with vendor's contact info
        $vendNome    = htmlspecialchars($proposta['vendedor_nome'],    ENT_QUOTES, 'UTF-8');
        $vendEmail   = htmlspecialchars($proposta['vendedor_email'],   ENT_QUOTES, 'UTF-8');
        $vendCelular = htmlspecialchars($proposta['vendedor_celular'] ?? '', ENT_QUOTES, 'UTF-8');
        $contactBoxB = "<div style='background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:16px;margin:16px 0;'>
  <p style='margin:0 0 8px;font-weight:bold;color:#166534;'>📞 Dados do Vendedor</p>
  <table cellpadding='0' cellspacing='0'>
    <tr><td style='padding:3px 12px 3px 0;color:#374151;'>Nome:</td><td style='padding:3px 0;font-weight:bold;'>{$vendNome}</td></tr>
    <tr><td style='padding:3px 12px 3px 0;color:#374151;'>E-mail:</td><td style='padding:3px 0;'>{$vendEmail}</td></tr>" .
($vendCelular !== '' ? "<tr><td style='padding:3px 12px 3px 0;color:#374151;'>Celular:</td><td style='padding:3px 0;'>{$vendCelular}</td></tr>" : '') . "
  </table>
</div>";
        $bodyBuyer = "<p>Olá, <strong>" . htmlspecialchars($buyerInfo['nome'] ?? '', ENT_QUOTES, 'UTF-8') . "</strong>!</p>
<p>🎉 Você aceitou uma contraproposta de <strong style='color:#e63946;'>" . formatMoney((float) $proposta['valor']) . "</strong>!</p>
<p>Entre em contato com o vendedor para finalizar o negócio:</p>
{$contactBoxB}";
        if ($buyerInfo) {
            sendEmail($buyerInfo['email'], $buyerInfo['nome'], 'MotorGo – Proposta aceita!',
                buildEmailHtml('Proposta Aceita! 🎉', $bodyBuyer, 'Acessar painel', SITE_URL . '/painel.php?secao=propostas'));
        }

        echo json_encode(['success' => true, 'message' => 'Contraproposta aceita! Os dados de contato foram enviados por e-mail.']);
        exit;

    } elseif ($acao === 'recusar') {
        $stmt = $conn->prepare("UPDATE propostas SET status = 'recusada' WHERE id = ?");
        $stmt->bind_param('i', $proposta_id);
        $stmt->execute();
        $stmt->close();

        // Mark root proposal as recusada so table shows terminal state
        $stmt = $conn->prepare("UPDATE propostas SET status = 'recusada' WHERE id = ?");
        $stmt->bind_param('i', $root_id);
        $stmt->execute();
        $stmt->close();

        // Unlock vehicle for new proposals
        $stmt = $conn->prepare("UPDATE veiculos SET em_negociacao = 0 WHERE id = ?");
        $stmt->bind_param('i', $proposta['veiculo_id']);
        $stmt->execute();
        $stmt->close();

        $bodyRecusedV = "<p>Olá, <strong>" . htmlspecialchars($proposta['vendedor_nome'], ENT_QUOTES, 'UTF-8') . "</strong>!</p>
<p>O comprador recusou sua contraproposta de <strong>" . formatMoney((float) $proposta['valor']) . "</strong>.</p>
<p>O negócio não foi fechado. O veículo está disponível para novas propostas.</p>";
        sendEmail($proposta['vendedor_email'], $proposta['vendedor_nome'], 'MotorGo – Contraproposta recusada',
            buildEmailHtml('Contraproposta Recusada', $bodyRecusedV, 'Acessar painel', SITE_URL . '/painel.php?secao=propostas'));

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

        $bodyNewContra = "<p>Olá, <strong>" . htmlspecialchars($proposta['vendedor_nome'], ENT_QUOTES, 'UTF-8') . "</strong>!</p>
<p>O comprador enviou uma nova contraproposta:</p>
<table cellpadding='0' cellspacing='0' style='margin:16px 0;'>
  <tr><td style='padding:5px 0;color:#6b7280;min-width:120px;'>Novo valor</td>
      <td style='padding:5px 0;font-weight:bold;color:#e63946;font-size:18px;'>" . formatMoney($novo_valor) . "</td></tr>" .
($mensagem !== '' ? "  <tr><td style='padding:5px 0;color:#6b7280;vertical-align:top;'>Mensagem</td>
      <td style='padding:5px 0;font-style:italic;'>\"" . htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') . "\"</td></tr>" : '') . "
</table>
<p>Acesse o painel para aceitar, recusar ou enviar uma nova contraproposta.</p>";
        sendEmail($proposta['vendedor_email'], $proposta['vendedor_nome'], 'MotorGo – Nova contraproposta recebida',
            buildEmailHtml('Nova Contraproposta Recebida', $bodyNewContra, 'Ver no painel', SITE_URL . '/painel.php?secao=propostas'));

        echo json_encode(['success' => true, 'message' => 'Contraproposta enviada com sucesso!']);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Ação não permitida.']);

