<?php
require_once '../includes/config.php';
require_once '../includes/conexao.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$id         = (int) ($_GET['id'] ?? 0);
$usuario_id = (int) $_SESSION['usuario_id'];
$tipo       = $_SESSION['tipo'] ?? '';

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

// Fetch the requested proposal
$stmt = $conn->prepare("
    SELECT p.id, p.veiculo_id, p.usuario_id AS prop_usuario_id, p.valor, p.status,
           p.data_proposta, p.mensagem, p.proposta_origem_id,
           v.marca, v.modelo, v.ano_fabrica, v.preco AS preco_veiculo,
           v.usuario_id AS vendedor_id,
           u_prop.nome AS prop_usuario_nome,
           u_vend.nome AS vendedor_nome
    FROM propostas p
    JOIN veiculos v ON v.id = p.veiculo_id
    JOIN usuarios u_prop ON u_prop.id = p.usuario_id
    JOIN usuarios u_vend ON u_vend.id = v.usuario_id
    WHERE p.id = ? LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$proposta = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$proposta) {
    echo json_encode(['success' => false, 'message' => 'Proposta não encontrada.']);
    exit;
}

$vendedor_id     = (int) $proposta['vendedor_id'];
$prop_usuario_id = (int) $proposta['prop_usuario_id'];

// Resolve root proposal for permission check
$root_id = $proposta['proposta_origem_id'] ? (int) $proposta['proposta_origem_id'] : $proposta['id'];
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

// Permission check: must be vendedor, the original buyer, or admin
if ($tipo !== 'administrador' && $usuario_id !== $vendedor_id && $usuario_id !== $root_comprador_id) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit;
}

// Fetch full negotiation thread (root + all counter-proposals)
$stmtT = $conn->prepare("
    SELECT p.id, p.usuario_id, p.valor, p.status, p.data_proposta, p.mensagem, p.proposta_origem_id,
           u.nome AS usuario_nome,
           v.usuario_id AS vendedor_id
    FROM propostas p
    JOIN usuarios u ON u.id = p.usuario_id
    JOIN veiculos v ON v.id = p.veiculo_id
    WHERE p.id = ? OR p.proposta_origem_id = ?
    ORDER BY p.id ASC
");
$stmtT->bind_param('ii', $root_id, $root_id);
$stmtT->execute();
$resT = $stmtT->get_result();
$thread = [];
while ($row = $resT->fetch_assoc()) {
    $thread[] = [
        'id'                => (int) $row['id'],
        'usuario_id'        => (int) $row['usuario_id'],
        'usuario_nome'      => $row['usuario_nome'],
        'vendedor_id'       => (int) $row['vendedor_id'],
        'valor'             => (float) $row['valor'],
        'status'            => $row['status'],
        'data_proposta'     => $row['data_proposta'],
        'mensagem'          => $row['mensagem'] ?? '',
        'proposta_origem_id'=> $row['proposta_origem_id'],
    ];
}
$stmtT->close();

// Build clean response
$data = [
    'id'                => (int) $proposta['id'],
    'veiculo_id'        => (int) $proposta['veiculo_id'],
    'vendedor_id'       => $vendedor_id,
    'comprador_id'      => $root_comprador_id,
    'marca'             => $proposta['marca'],
    'modelo'            => $proposta['modelo'],
    'ano_fabrica'       => $proposta['ano_fabrica'],
    'preco_veiculo'     => (float) $proposta['preco_veiculo'],
    'valor'             => (float) $proposta['valor'],
    'status'            => $proposta['status'],
    'data_proposta'     => $proposta['data_proposta'],
    'mensagem'          => $proposta['mensagem'] ?? '',
    'proposta_origem_id'=> $proposta['proposta_origem_id'],
    'root_id'           => $root_id,
    'vendedor_nome'     => $proposta['vendedor_nome'],
    'comprador_nome'    => $proposta['prop_usuario_nome'],
    'thread'            => $thread,
];

echo json_encode(['success' => true, 'proposta' => $data]);
