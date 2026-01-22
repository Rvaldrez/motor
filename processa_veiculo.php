<?php
// 🚀 Ativar exibição de erros para depuração (remova em produção)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 🚀 Configurar resposta como JSON
header("Content-Type: application/json");

// 🚀 Incluir a conexão com o banco de dados e iniciar sessão
require_once "conexao_bd.php";
session_start();

// 🚀 Verificar se a conexão foi carregada corretamente
if (!isset($mysqli) || $mysqli->connect_error) {
    echo json_encode(["success" => false, "message" => "Erro ao conectar ao banco de dados.", "error" => $mysqli->connect_error]);
    exit;
}

// 🚀 Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(["success" => false, "message" => "Usuário não autenticado."]);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];  // ID do usuário logado

// 🚀 Capturar entrada do usuário
$placa = trim($_POST["placa"] ?? "");
$marca = trim($_POST["marca"] ?? "");
$modelo = trim($_POST["modelo"] ?? "");
$ano_fabrica = trim($_POST["ano_fabrica"] ?? "");
$quilometragem = trim($_POST["quilometragem"] ?? "");
$preco = trim($_POST["preco"] ?? "");

// 🚀 Validar se todos os campos obrigatórios foram preenchidos
if (empty($placa) || empty($marca) || empty($modelo) || empty($ano_fabrica) || empty($quilometragem) || empty($preco)) {
    echo json_encode(["success" => false, "message" => "Todos os campos são obrigatórios."]);
    exit;
}

// 🚀 Garantir que `quilometragem` e `preco` sejam numéricos
$quilometragem = filter_var($quilometragem, FILTER_SANITIZE_NUMBER_INT);
$preco = filter_var($preco, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

if (!is_numeric($quilometragem) || !is_numeric($preco)) {
    echo json_encode(["success" => false, "message" => "Quilometragem e preço devem ser numéricos."]);
    exit;
}

// 🚀 Verificar se a placa já existe no banco
$query_check = "SELECT id FROM veiculos WHERE placa = ?";
$stmt_check = $mysqli->prepare($query_check);
$stmt_check->bind_param("s", $placa);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Já existe um veículo cadastrado com essa placa."]);
    exit;
}

// 🚀 Inserir o veículo no banco de dados
$query = "INSERT INTO veiculos (placa, marca, modelo, ano_fabrica, quilometragem, preco, usuario_id, data_cadastro) 
          VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

$stmt = $mysqli->prepare($query);
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Erro na preparação da consulta.", "error" => $mysqli->error]);
    exit;
}

$stmt->bind_param("sssiddi", $placa, $marca, $modelo, $ano_fabrica, $quilometragem, $preco, $usuario_id);
$success = $stmt->execute();

if (!$success) {
    echo json_encode(["success" => false, "message" => "Erro ao cadastrar veículo.", "error" => $stmt->error]);
    exit;
}

// 🚀 Capturar o ID do veículo recém-cadastrado
$veiculo_id = $stmt->insert_id;

// 🚀 Fechar a conexão do veículo
$stmt->close();

// 🚀 Processar Upload das Fotos 🚀
if (!empty($_FILES['fotos']['name'][0])) {
    $diretorio_upload = "uploads/";

    // 🔹 Criar o diretório se não existir
    if (!is_dir($diretorio_upload)) {
        mkdir($diretorio_upload, 0777, true);
    }

    foreach ($_FILES['fotos']['tmp_name'] as $key => $tmp_name) {
        $nome_original = basename($_FILES['fotos']['name'][$key]);
        $extensao = pathinfo($nome_original, PATHINFO_EXTENSION);

        // 🚀 Verificar extensões permitidas
        $extensoes_permitidas = ["jpg", "jpeg", "png", "webp"];
        if (!in_array(strtolower($extensao), $extensoes_permitidas)) {
            continue; // Pula arquivos inválidos
        }

        $novo_nome = uniqid("veiculo_") . "." . $extensao;
        $caminho_foto = $diretorio_upload . $novo_nome;

        // 🚀 Mover a foto para o diretório de uploads
        if (move_uploaded_file($tmp_name, $caminho_foto)) {
            $sql_foto = "INSERT INTO fotos_veiculos (veiculo_id, caminho_foto) VALUES (?, ?)";
            $stmt_foto = $mysqli->prepare($sql_foto);
            $stmt_foto->bind_param("is", $veiculo_id, $caminho_foto);
            $stmt_foto->execute();
        }
    }
}

// 🚀 Retornar sucesso
echo json_encode(["success" => true, "message" => "Veículo e fotos cadastrados com sucesso!"]);
$mysqli->close();
?>
