<?php
// Definindo as credenciais do banco de dados
$host = "127.0.0.1";
$usuario = "u218663118_motorgo";
$senha = "MotorGo@2025_Vic";
$banco = "u218663118_motorgo";

// Tentando a conexão com o banco de dados
$mysqli = new mysqli($host, $usuario, $senha, $banco);

// Verificando se houve erro na conexão
if ($mysqli->connect_error) {
    die("Erro na conexão: " . $mysqli->connect_error);
}

// Definindo o charset para evitar problemas com acentuação
$mysqli->set_charset("utf8");
?>
