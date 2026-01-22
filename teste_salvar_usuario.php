<?php
// Teste básico - SEM conexão com banco
header("Content-Type: application/json");

// Apenas retorna os dados recebidos
echo json_encode([
    "success" => true,
    "message" => "Teste funcionando",
    "dados_recebidos" => $_POST,
    "php_version" => PHP_VERSION
]);
?>