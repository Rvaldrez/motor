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

$foto_id    = (int) ($_POST['foto_id']    ?? 0);
$veiculo_id = (int) ($_POST['veiculo_id'] ?? 0);
$usuario_id = (int) $_SESSION['usuario_id'];
$tipo       = $_SESSION['tipo'] ?? '';

if ($foto_id <= 0 || $veiculo_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}

// Verify ownership
if ($tipo === 'administrador') {
    $stmt = $conn->prepare(
        "SELECT fv.id, fv.caminho_foto FROM fotos_veiculos fv
         WHERE fv.id = ? AND fv.veiculo_id = ?"
    );
    $stmt->bind_param('ii', $foto_id, $veiculo_id);
} else {
    $stmt = $conn->prepare(
        "SELECT fv.id, fv.caminho_foto FROM fotos_veiculos fv
         INNER JOIN veiculos v ON v.id = fv.veiculo_id
         WHERE fv.id = ? AND fv.veiculo_id = ? AND v.usuario_id = ?"
    );
    $stmt->bind_param('iii', $foto_id, $veiculo_id, $usuario_id);
}
$stmt->execute();
$foto = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$foto) {
    echo json_encode(['success' => false, 'message' => 'Foto não encontrada ou sem permissão.']);
    exit;
}

if (empty($_FILES['foto']['tmp_name']) || ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Arquivo inválido.']);
    exit;
}
if ($_FILES['foto']['size'] > MAX_UPLOAD_SIZE) {
    echo json_encode(['success' => false, 'message' => 'Arquivo muito grande (máx. 5MB).']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $_FILES['foto']['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
    echo json_encode(['success' => false, 'message' => 'Formato não permitido (JPG, PNG ou WebP).']);
    exit;
}

// Delete old file
deleteFile(UPLOAD_DIR . $foto['caminho_foto']);

// Save new file
$upload_dir = UPLOAD_DIR . 'fotos_veiculos/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$filename = $veiculo_id . '_' . time() . '_r' . $foto_id . '.jpg';
$destPath = $upload_dir . $filename;

if (!resizeImage($_FILES['foto']['tmp_name'], $destPath, 1200, 900)) {
    echo json_encode(['success' => false, 'message' => 'Erro ao processar imagem.']);
    exit;
}

$novo_caminho = 'fotos_veiculos/' . $filename;
$upd = $conn->prepare("UPDATE fotos_veiculos SET caminho_foto = ? WHERE id = ?");
$upd->bind_param('si', $novo_caminho, $foto_id);
$upd->execute();
$upd->close();

// Update foto_principal on the vehicle (in case the main photo was replaced)
$fp = $conn->prepare(
    "SELECT caminho_foto FROM fotos_veiculos
     WHERE veiculo_id = ? ORDER BY IFNULL(ordem_exibicao, 0) ASC, id ASC LIMIT 1"
);
$fp->bind_param('i', $veiculo_id);
$fp->execute();
$fp_row = $fp->get_result()->fetch_assoc();
$fp->close();
if ($fp_row) {
    $upd2 = $conn->prepare("UPDATE veiculos SET foto_principal = ? WHERE id = ?");
    $upd2->bind_param('si', $fp_row['caminho_foto'], $veiculo_id);
    $upd2->execute();
    $upd2->close();
}

echo json_encode(['success' => true, 'url' => resolvePhotoUrl($novo_caminho)]);
