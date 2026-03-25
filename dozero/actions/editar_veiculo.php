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

$id           = (int) ($_POST['id'] ?? 0);
$placa        = strtoupper(preg_replace('/[\s\-]/', '', trim($_POST['placa']        ?? '')));
$marca        = trim($_POST['marca']        ?? '');
$modelo       = trim($_POST['modelo']       ?? '');
$ano_fabrica  = (int) ($_POST['ano_fabrica']  ?? 0);
$quilometragem = (int) ($_POST['quilometragem'] ?? 0);
$preco_raw    = preg_replace('/[^\d,\.]/', '', $_POST['preco'] ?? '');
$preco        = (float) str_replace(',', '.', str_replace('.', '', $preco_raw));
$usuario_id   = (int) $_SESSION['usuario_id'];
$tipo         = $_SESSION['tipo'] ?? '';

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

if ($tipo === 'administrador') {
    $stmt = $conn->prepare("SELECT id FROM veiculos WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
} else {
    $stmt = $conn->prepare("SELECT id FROM veiculos WHERE id = ? AND usuario_id = ? LIMIT 1");
    $stmt->bind_param('ii', $id, $usuario_id);
}
$stmt->execute();
$result  = $stmt->get_result();
$veiculo = $result->fetch_assoc();
$stmt->close();

if (!$veiculo) {
    echo json_encode(['success' => false, 'message' => 'Veículo não encontrado ou sem permissão.']);
    exit;
}

$erros = [];
if (!preg_match('/^[A-Z]{3}[0-9]{1}[A-Z0-9]{1}[0-9]{2}$|^[A-Z]{3}[0-9]{4}$/', $placa)) $erros[] = 'Placa inválida.';
if ($marca === '')        $erros[] = 'Marca é obrigatória.';
if ($modelo === '')       $erros[] = 'Modelo é obrigatório.';
if ($ano_fabrica < 1900 || $ano_fabrica > (int) date('Y') + 1) $erros[] = 'Ano inválido.';
if ($preco <= 0)          $erros[] = 'Preço inválido.';
if (!empty($erros)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $erros)]);
    exit;
}

$stmt = $conn->prepare("UPDATE veiculos SET placa=?, marca=?, modelo=?, ano_fabrica=?, quilometragem=?, preco=? WHERE id=?");
$stmt->bind_param('sssiidi', $placa, $marca, $modelo, $ano_fabrica, $quilometragem, $preco, $id);
$stmt->execute();
$stmt->close();

// Remove fotos solicitadas
$fotos_remover = $_POST['fotos_remover'] ?? [];
if (!empty($fotos_remover) && is_array($fotos_remover)) {
    foreach ($fotos_remover as $foto_id) {
        $foto_id = (int) $foto_id;
        if ($foto_id <= 0) continue;
        $sf = $conn->prepare("SELECT caminho_foto FROM fotos_veiculos WHERE id = ? AND veiculo_id = ?");
        $sf->bind_param('ii', $foto_id, $id);
        $sf->execute();
        $row = $sf->get_result()->fetch_assoc();
        $sf->close();
        if ($row) {
            deleteFile(UPLOAD_DIR . $row['caminho_foto']);
            $df = $conn->prepare("DELETE FROM fotos_veiculos WHERE id = ?");
            $df->bind_param('i', $foto_id);
            $df->execute();
            $df->close();
        }
    }
}

// Adiciona novas fotos
$upload_dir = UPLOAD_DIR . 'fotos_veiculos/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

if (!empty($_FILES['fotos']['name'][0])) {
    $om  = $conn->prepare("SELECT MAX(ordem_exibicao) AS max_o FROM fotos_veiculos WHERE veiculo_id = ?");
    $om->bind_param('i', $id);
    $om->execute();
    $max_o = (int) ($om->get_result()->fetch_assoc()['max_o'] ?? 0);
    $om->close();

    $total = min(count($_FILES['fotos']['name']), 10);
    for ($i = 0; $i < $total; $i++) {
        if ($_FILES['fotos']['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($_FILES['fotos']['size'][$i] > MAX_UPLOAD_SIZE) continue;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['fotos']['tmp_name'][$i]);
        finfo_close($finfo);
        if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) continue;

        $filename = "{$id}_" . time() . "_{$i}.jpg";
        $destPath = $upload_dir . $filename;
        if (resizeImage($_FILES['fotos']['tmp_name'][$i], $destPath, 1200, 900)) {
            $caminho = 'fotos_veiculos/' . $filename;
            $ordem   = ++$max_o;
            $ins = $conn->prepare("INSERT INTO fotos_veiculos (veiculo_id, caminho_foto, ordem_exibicao) VALUES (?, ?, ?)");
            $ins->bind_param('isi', $id, $caminho, $ordem);
            $ins->execute();
            $ins->close();
        }
    }
}

// Atualiza foto_principal
$fp = $conn->prepare("SELECT caminho_foto FROM fotos_veiculos WHERE veiculo_id = ? ORDER BY ordem_exibicao ASC LIMIT 1");
$fp->bind_param('i', $id);
$fp->execute();
$fp_row = $fp->get_result()->fetch_assoc();
$fp->close();
$nova_principal = $fp_row ? $fp_row['caminho_foto'] : '';
$upd = $conn->prepare("UPDATE veiculos SET foto_principal = ? WHERE id = ?");
if ($upd) {
    $upd->bind_param('si', $nova_principal, $id);
    $upd->execute();
    $upd->close();
}

echo json_encode(['success' => true, 'message' => 'Veículo atualizado com sucesso!']);
