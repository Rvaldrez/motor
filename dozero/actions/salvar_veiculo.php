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
if (($_SESSION['tipo'] ?? '') !== 'vendedor' && ($_SESSION['tipo'] ?? '') !== 'investidor' && ($_SESSION['tipo'] ?? '') !== 'administrador') {
    echo json_encode(['success' => false, 'message' => 'Acesso não permitido.']);
    exit;
}
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token inválido.']);
    exit;
}

$placa        = strtoupper(preg_replace('/\s+/', '', trim($_POST['placa']        ?? '')));
$marca        = trim($_POST['marca']        ?? '');
$modelo       = trim($_POST['modelo']       ?? '');
$ano_fabrica  = (int) ($_POST['ano_fabrica']  ?? 0);
$quilometragem = (int) ($_POST['quilometragem'] ?? 0);
$preco_raw    = preg_replace('/[^\d,\.]/', '', $_POST['preco'] ?? '');
$preco        = (float) str_replace(',', '.', str_replace('.', '', $preco_raw));
$usuario_id   = (int) $_SESSION['usuario_id'];

$erros = [];
if (!preg_match('/^[A-Z]{3}[0-9]{1}[A-Z0-9]{1}[0-9]{2}$|^[A-Z]{3}[0-9]{4}$/', $placa)) $erros[] = 'Placa inválida.';
if ($marca === '')         $erros[] = 'Marca é obrigatória.';
if ($modelo === '')        $erros[] = 'Modelo é obrigatório.';
if ($ano_fabrica < 1900 || $ano_fabrica > (int) date('Y') + 1) $erros[] = 'Ano de fabricação inválido.';
if ($quilometragem < 0)    $erros[] = 'Quilometragem inválida.';
if ($preco <= 0)           $erros[] = 'Preço inválido.';

if (!empty($erros)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $erros)]);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM veiculos WHERE placa = ? AND usuario_id = ? LIMIT 1");
$stmt->bind_param('si', $placa, $usuario_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Você já possui um veículo com esta placa cadastrado.']);
    exit;
}
$stmt->close();

$stmt = $conn->prepare(
    "INSERT INTO veiculos (usuario_id, placa, marca, modelo, ano_fabrica, quilometragem, preco, status, em_negociacao, data_cadastro)
     VALUES (?, ?, ?, ?, ?, ?, ?, 'completo', 0, NOW())"
);
$stmt->bind_param('isssiid', $usuario_id, $placa, $marca, $modelo, $ano_fabrica, $quilometragem, $preco);
if (!$stmt->execute()) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar veículo.']);
    exit;
}
$veiculo_id = (int) $conn->insert_id;
$stmt->close();

$foto_principal = '';
$upload_dir     = UPLOAD_DIR . 'fotos_veiculos/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

if (!empty($_FILES['fotos']['name'][0])) {
    $total = min(count($_FILES['fotos']['name']), 10);
    for ($i = 0; $i < $total; $i++) {
        if ($_FILES['fotos']['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($_FILES['fotos']['size'][$i] > MAX_UPLOAD_SIZE) continue;

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mime     = finfo_file($finfo, $_FILES['fotos']['tmp_name'][$i]);
        finfo_close($finfo);
        if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) continue;

        $ts       = time();
        $filename = "{$veiculo_id}_{$ts}_{$i}.jpg";
        $destPath = $upload_dir . $filename;

        if (resizeImage($_FILES['fotos']['tmp_name'][$i], $destPath, 1200, 900)) {
            $caminho = 'fotos_veiculos/' . $filename;
            $ordem   = $i + 1;
            $stmt    = $conn->prepare("INSERT INTO fotos_veiculos (veiculo_id, caminho_foto, ordem_exibicao) VALUES (?, ?, ?)");
            $stmt->bind_param('isi', $veiculo_id, $caminho, $ordem);
            $stmt->execute();
            $stmt->close();

            if ($foto_principal === '') {
                $foto_principal = $caminho;
                $upd = $conn->prepare("UPDATE veiculos SET foto_principal = ? WHERE id = ?");
                $upd->bind_param('si', $foto_principal, $veiculo_id);
                $upd->execute();
                $upd->close();
            }
        }
    }
}

echo json_encode(['success' => true, 'message' => 'Veículo cadastrado com sucesso!', 'data' => ['id' => $veiculo_id]]);
