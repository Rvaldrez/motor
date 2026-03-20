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

if ($tipo === 'administrador') {
    $stmt = $conn->prepare(
        "SELECT v.*, u.nome AS vendedor_nome FROM veiculos v JOIN usuarios u ON u.id = v.usuario_id WHERE v.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $id);
} else {
    $stmt = $conn->prepare(
        "SELECT v.*, u.nome AS vendedor_nome FROM veiculos v JOIN usuarios u ON u.id = v.usuario_id WHERE v.id = ? AND v.usuario_id = ? LIMIT 1"
    );
    $stmt->bind_param('ii', $id, $usuario_id);
}
$stmt->execute();
$veiculo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$veiculo) {
    echo json_encode(['success' => false, 'message' => 'Veículo não encontrado ou sem permissão.']);
    exit;
}

$stmt = $conn->prepare(
    "SELECT id, caminho_foto, ordem_exibicao FROM fotos_veiculos WHERE veiculo_id = ? ORDER BY ordem_exibicao ASC"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$fotos_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$fotos = array_map(function ($f) {
    return [
        'id'             => (int) $f['id'],
        'caminho_foto'   => $f['caminho_foto'],
        'url'            => UPLOAD_URL . $f['caminho_foto'],
        'ordem_exibicao' => (int) $f['ordem_exibicao'],
    ];
}, $fotos_raw);

echo json_encode([
    'success' => true,
    'data'    => [
        'id'            => (int) $veiculo['id'],
        'placa'         => $veiculo['placa'],
        'marca'         => $veiculo['marca'],
        'modelo'        => $veiculo['modelo'],
        'ano_fabrica'   => (int) $veiculo['ano_fabrica'],
        'quilometragem' => (int) $veiculo['quilometragem'],
        'preco'         => (float) $veiculo['preco'],
        'status'        => $veiculo['status'],
        'em_negociacao' => (bool) $veiculo['em_negociacao'],
        'foto_principal' => $veiculo['foto_principal'],
        'foto_principal_url' => $veiculo['foto_principal'] ? UPLOAD_URL . $veiculo['foto_principal'] : null,
        'vendedor_nome' => $veiculo['vendedor_nome'],
        'data_cadastro' => $veiculo['data_cadastro'],
        'fotos'         => $fotos,
    ],
]);
