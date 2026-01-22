<?php
// ✅ ARQUIVO DE TESTE PARA DEBUGAR
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");

echo json_encode(["debug" => "Iniciando teste..."]);

// Teste 1: Verificar se o arquivo de conexão existe
if (!file_exists("conexao_bd.php")) {
    echo json_encode(["erro" => "Arquivo conexao_bd.php não encontrado"]);
    exit;
}

// Teste 2: Tentar incluir o arquivo de conexão
try {
    require_once "conexao_bd.php";
    echo json_encode(["debug" => "Arquivo conexao_bd.php carregado com sucesso"]);
} catch (Exception $e) {
    echo json_encode(["erro" => "Erro ao carregar conexao_bd.php: " . $e->getMessage()]);
    exit;
}

// Teste 3: Verificar se a variável $mysqli existe
if (!isset($mysqli)) {
    echo json_encode(["erro" => "Variável mysqli não foi definida no conexao_bd.php"]);
    exit;
}

// Teste 4: Verificar conexão
if ($mysqli->connect_error) {
    echo json_encode(["erro" => "Erro de conexão: " . $mysqli->connect_error]);
    exit;
}

// Teste 5: Verificar se a tabela usuarios existe
$result = $mysqli->query("SHOW TABLES LIKE 'usuarios'");
if ($result->num_rows === 0) {
    echo json_encode(["erro" => "Tabela 'usuarios' não existe no banco de dados"]);
    exit;
}

// Teste 6: Verificar estrutura da tabela
$result = $mysqli->query("DESCRIBE usuarios");
$campos = [];
while ($row = $result->fetch_assoc()) {
    $campos[] = $row['Field'];
}

echo json_encode([
    "success" => true,
    "debug" => "Todos os testes passaram",
    "campos_tabela" => $campos
]);
?>