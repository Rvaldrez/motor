<?php
// Arquivo de teste para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'conexao_bd.php';

// Testa a conexão
if ($mysqli->connect_error) {
    die("Conexão falhou: " . $mysqli->connect_error);
}

echo "Conexão OK<br>";

// Verifica se a tabela usuarios existe
$result = $mysqli->query("SHOW COLUMNS FROM usuarios");
if ($result) {
    echo "Estrutura da tabela usuarios:<br>";
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")<br>";
    }
} else {
    echo "Erro ao verificar tabela: " . $mysqli->error;
}

// Verifica códigos de convite
$result = $mysqli->query("SELECT * FROM codigos_convite WHERE valido_ate > NOW()");
if ($result) {
    echo "<br>Códigos de convite válidos:<br>";
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['codigo'] . " (válido até " . $row['valido_ate'] . ")<br>";
    }
}

// Verifica se o helpers/email_proposta.php existe
if (file_exists('helpers/email_proposta.php')) {
    echo "<br>Arquivo helpers/email_proposta.php encontrado ✓";
} else {
    echo "<br>ERRO: Arquivo helpers/email_proposta.php NÃO encontrado!";
}
?>