<?php
session_start();
header('Content-Type: application/json');
require_once "conexao_bd.php";

// ✅ Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(["success" => false, "message" => "Usuário não autenticado."]);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$veiculo_id = $_POST['veiculo_id'] ?? null;

if (!$veiculo_id || !is_numeric($veiculo_id)) {
    echo json_encode(["success" => false, "message" => "ID do veículo ausente ou inválido."]);
    exit;
}

// 🔍 Verifica se o veículo pertence ao usuário
$sql_verifica = $mysqli->prepare("SELECT id FROM veiculos WHERE id = ? AND usuario_id = ?");
$sql_verifica->bind_param("ii", $veiculo_id, $usuario_id);
$sql_verifica->execute();
$res = $sql_verifica->get_result();

if ($res->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Veículo não encontrado ou não pertence a você."]);
    exit;
}

// 📥 Dados do formulário
$placa         = trim($_POST['placa'] ?? '');
$marca         = trim($_POST['marca'] ?? '');
$modelo        = trim($_POST['modelo'] ?? '');
$ano_fabrica   = trim($_POST['ano_fabrica'] ?? '');
$quilometragem = intval(str_replace('.', '', $_POST['quilometragem'] ?? '0'));
$preco         = trim($_POST['preco'] ?? '0');

// ✅ Validação básica
if (!$placa || !$marca || !$modelo || !$ano_fabrica || !$quilometragem || !$preco) {
    echo json_encode(["success" => false, "message" => "Todos os campos são obrigatórios."]);
    exit;
}

$mysqli->begin_transaction();

try {
    // ✅ Atualiza os dados do veículo
    $stmt = $mysqli->prepare("
        UPDATE veiculos SET 
            placa = ?, marca = ?, modelo = ?, ano_fabrica = ?, quilometragem = ?, preco = ?
        WHERE id = ? AND usuario_id = ?
    ");
    $stmt->bind_param("ssssssii", $placa, $marca, $modelo, $ano_fabrica, $quilometragem, $preco, $veiculo_id, $usuario_id);
    $stmt->execute();

    // 📸 Atualização das fotos (0 a 5 = foto1 a foto6)
    for ($i = 0; $i < 6; $i++) {
        $inputName = "foto{$i}";
        if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
            continue; // Pula se não houver nova imagem
        }

        $ext = pathinfo($_FILES[$inputName]['name'], PATHINFO_EXTENSION);
        $novoNome = uniqid("veiculo_") . "." . $ext;
        $destino = "uploads/" . $novoNome;

        if (move_uploaded_file($_FILES[$inputName]['tmp_name'], $destino)) {
            // 🗑️ Remove foto anterior (se houver)
            $stmtAntiga = $mysqli->prepare("SELECT caminho_foto FROM fotos_veiculos WHERE veiculo_id = ? AND ordem_exibicao = ?");
            $ordem = $i + 1;
            $stmtAntiga->bind_param("ii", $veiculo_id, $ordem);
            $stmtAntiga->execute();
            $resFoto = $stmtAntiga->get_result();

            if ($fotoAntiga = $resFoto->fetch_assoc()) {
                if (file_exists($fotoAntiga['caminho_foto'])) {
                    unlink($fotoAntiga['caminho_foto']);
                }
            }

            // 🆕 Atualiza ou insere a nova foto
            $stmtFoto = $mysqli->prepare("
                INSERT INTO fotos_veiculos (veiculo_id, caminho_foto, ordem_exibicao)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE caminho_foto = VALUES(caminho_foto)
            ");
            $stmtFoto->bind_param("isi", $veiculo_id, $destino, $ordem);
            $stmtFoto->execute();
        } else {
            throw new Exception("Erro ao mover o arquivo da foto $ordem.");
        }
    }

    $mysqli->commit();
    echo json_encode(["success" => true, "message" => "✅ Veículo atualizado com sucesso!"]);

} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Erro na edição de veículo: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "❌ Falha ao atualizar: " . $e->getMessage()]);
}
?>
