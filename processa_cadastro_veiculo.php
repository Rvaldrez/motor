<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require "conexao_bd.php";

// ✅ Permite qualquer usuário logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(["success" => false, "message" => "Sessão inválida."]);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];


// 🔹 Função para converter mensagens técnicas em mensagens amigáveis
function mensagemAmigavel($erro) {
    if (str_contains($erro, 'Duplicate entry') && str_contains($erro, 'placa')) {
        return "Já existe um veículo cadastrado com esta placa.";
    }
    if (str_contains($erro, 'constraint') || str_contains($erro, 'foreign key')) {
        return "Há um problema com os dados informados. Verifique e tente novamente.";
    }
    if (str_contains($erro, 'prepare')) {
        return "Erro interno ao preparar os dados. Tente novamente.";
    }
    return "Ocorreu um erro ao cadastrar o veículo. Tente novamente.";
}

$mysqli->begin_transaction();

try {
    // Captura e validação
    $placa = trim($_POST['placa'] ?? "");
    $marca = trim($_POST['marca'] ?? "");
    $modelo = trim($_POST['modelo'] ?? "");
    $ano_fabrica = trim($_POST['ano_fabrica'] ?? "");
    $quilometragem = isset($_POST['quilometragem']) ? str_replace('.', '', $_POST['quilometragem']) : "";
    $quilometragem = floatval($quilometragem);
    $preco = trim($_POST['preco'] ?? "");

    if (!$placa || !$marca || !$modelo || !$ano_fabrica || !$quilometragem || !$preco) {
        throw new Exception("Todos os campos do veículo são obrigatórios.");
    }

    // Inserção do veículo
    $stmt = $mysqli->prepare("INSERT INTO veiculos (placa, marca, modelo, ano_fabrica, quilometragem, preco, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssi", $placa, $marca, $modelo, $ano_fabrica, $quilometragem, $preco, $usuario_id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception("Erro ao cadastrar veículo.");
    }

    $veiculo_id = $mysqli->insert_id;

    // Upload das fotos
    $pastaDestino = "uploads/";
    if (!is_dir($pastaDestino)) {
        mkdir($pastaDestino, 0777, true);
    }

    for ($i = 1; $i <= 6; $i++) {
        if (!empty($_FILES["foto$i"]["name"])) {
            $extensao = pathinfo($_FILES["foto$i"]["name"], PATHINFO_EXTENSION);
            $nomeArquivo = uniqid("veiculo_") . "." . $extensao;
            $caminhoArquivo = $pastaDestino . $nomeArquivo;

            if (move_uploaded_file($_FILES["foto$i"]["tmp_name"], $caminhoArquivo)) {
                $stmt = $mysqli->prepare("INSERT INTO fotos_veiculos (veiculo_id, caminho_foto, ordem_exibicao) VALUES (?, ?, ?)");
                $stmt->bind_param("isi", $veiculo_id, $caminhoArquivo, $i);
                $stmt->execute();

                if ($stmt->affected_rows === 0) {
                    throw new Exception("Erro ao salvar foto {$i} no banco.");
                }
            } else {
                throw new Exception("Erro ao fazer upload da foto {$i}.");
            }
        }
    }

    $mysqli->commit();

    echo json_encode([
        "success" => true,
        "message" => "✅ Veículo e fotos cadastrados com sucesso!",
        "usuario_id" => $usuario_id,
        "veiculo_id" => $veiculo_id
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode([
        "success" => false,
        "message" => mensagemAmigavel($e->getMessage())
    ]);
}
?>
