<?php
// ============================================================
//  MotorGo – Conexão com o Banco de Dados (MySQLi)
// ============================================================

require_once __DIR__ . '/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    if (APP_ENV === 'development') {
        die('Erro de conexão: ' . htmlspecialchars($conn->connect_error, ENT_QUOTES, 'UTF-8'));
    }
    // Produção: mensagem genérica para não expor credenciais
    die('Serviço temporariamente indisponível. Tente novamente em instantes.');
}

if (!$conn->set_charset('utf8mb4')) {
    if (APP_ENV === 'development') {
        die('Erro ao definir charset: ' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
    }
    die('Erro interno. Tente novamente em instantes.');
}

// Fuso horário do MySQL alinhado com a aplicação
$conn->query("SET time_zone = '-03:00'");
