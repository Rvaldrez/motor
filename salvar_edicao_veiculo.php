

<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once "conexao_bd.php";

// Função para retornar JSON e encerrar
function jsonResponse($success, $message, $extra = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

// Verifica sessão
if (!isset($_SESSION['usuario_id'])) {
    jsonResponse(false, "Acesso negado.");
}

$usuario_id = $_SESSION['usuario_id'];
$veiculo_id = $_POST['veiculo_id'] ?? null;

if (!$veiculo_id) {
    jsonResponse(false, "ID do veículo não informado.");
}

// Verifica se o veículo pertence ao usuário
$sql = $mysqli->prepare("SELECT * FROM veiculos WHERE id = ? AND usuario_id = ?");
$sql->bind_param("ii", $veiculo_id, $usuario_id);
$sql->execute();
$result = $sql->get_result();

if ($result->num_rows === 0) {
    jsonResponse(false, "Veículo não encontrado ou acesso negado.");
}

// Captura dados alteráveis
$quilometragem = trim($_POST['quilometragem'] ?? "");
$quilometragem = str_replace(['.', ','], ['', '.'], $quilometragem);
$quilometragem = floatval($quilometragem);

// Atualiza quilometragem
$stmt = $mysqli->prepare("UPDATE veiculos SET quilometragem = ? WHERE id = ?");
$stmt->bind_param("di", $quilometragem, $veiculo_id);
$stmt->execute();

// 🖼️ Upload de fotos
$pastaDestino = "uploads/";
if (!is_dir($pastaDestino)) {
    mkdir($pastaDestino, 0777, true);
}

$maxFileSize = 5 * 1024 * 1024; // 5MB
$extensoesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];
$errosUpload = [];

for ($i = 0; $i < 6; $i++) {
    if (isset($_FILES["foto$i"]) && $_FILES["foto$i"]["error"] === UPLOAD_ERR_OK) {

        $ext = strtolower(pathinfo($_FILES["foto$i"]["name"], PATHINFO_EXTENSION));
        $ordem = $i + 1;

        if (!in_array($ext, $extensoesPermitidas)) {
            $errosUpload[] = "Foto $ordem: formato inválido ($ext).";
            continue;
        }

        if ($_FILES["foto$i"]["size"] > $maxFileSize) {
            $errosUpload[] = "Foto $ordem: excede o limite de 5MB.";
            continue;
        }

        $novoNome = uniqid("foto_") . "." . $ext;
        $caminhoCompleto = $pastaDestino . $novoNome;

        // Verifica se já existe uma foto naquela ordem
        $verifica = $mysqli->prepare("SELECT id, caminho_foto FROM fotos_veiculos WHERE veiculo_id = ? AND ordem_exibicao = ?");
        $verifica->bind_param("ii", $veiculo_id, $ordem);
        $verifica->execute();
        $foto = $verifica->get_result()->fetch_assoc();

        if (move_uploaded_file($_FILES["foto$i"]["tmp_name"], $caminhoCompleto)) {
            if ($foto) {
                // Apaga imagem antiga se existir
                if (!empty($foto['caminho_foto']) && file_exists($foto['caminho_foto'])) {
                    unlink($foto['caminho_foto']);
                }
                $upd = $mysqli->prepare("UPDATE fotos_veiculos SET caminho_foto = ? WHERE id = ?");
                $upd->bind_param("si", $caminhoCompleto, $foto['id']);
            } else {
                $upd = $mysqli->prepare("INSERT INTO fotos_veiculos (veiculo_id, caminho_foto, ordem_exibicao) VALUES (?, ?, ?)");
                $upd->bind_param("isi", $veiculo_id, $caminhoCompleto, $ordem);
            }
            $upd->execute();
        } else {
            $errosUpload[] = "Erro ao salvar a Foto $ordem.";
        }
    }
}

// ✅ Resposta final
if (!empty($errosUpload)) {
    session_write_close();
    jsonResponse(false, "Veículo atualizado com falhas em algumas fotos.", ['errors' => $errosUpload]);
} else {
    session_write_close();
    jsonResponse(true, "✅ Veículo atualizado com sucesso.");
}
