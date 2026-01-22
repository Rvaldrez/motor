<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once "conexao_bd.php";
header("Content-Type: application/json");

// 🔐 Verificação de sessão e permissão
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(["success" => false, "message" => "Acesso negado. Usuário não logado."]);
    exit;
}


$usuario_id   = $_SESSION['usuario_id'];
$proposta_id  = $_POST['proposta_id'] ?? null;
$valor        = $_POST['valor'] ?? null;

// 🔎 Validação básica
if (!$proposta_id || !$valor) {
    echo json_encode(["success" => false, "message" => "Dados incompletos."]);
    exit;
}

// 🔢 Normaliza o valor
$valorLimpo = floatval($valor);
if ($valorLimpo <= 0) {
    echo json_encode(["success" => false, "message" => "Valor inválido."]);
    exit;
}

// ✅ LOG para debug
file_put_contents("debug_negociar.txt", "ID: $proposta_id | VALOR: $valorLimpo\n", FILE_APPEND);

// 🔍 Verifica se a proposta pertence ao vendedor logado
$sql = "SELECT p.id, p.status 
        FROM propostas p
        JOIN veiculos v ON p.veiculo_id = v.id
        WHERE p.id = ? AND v.usuario_id = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ii", $proposta_id, $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$proposta = $result->fetch_assoc();

if (!$proposta) {
    echo json_encode(["success" => false, "message" => "Proposta não encontrada ou acesso negado."]);
    exit;
}

// 🚫 Permite negociação apenas se estiver na vez do vendedor
if (!in_array($proposta['status'], ['pendente', 'resposta_comprador', 'aguardando_vendedor'])) {
    echo json_encode(["success" => false, "message" => "Essa proposta não está disponível para negociação no momento."]);
    exit;
}

// 🔄 Atualiza a proposta com novo valor e status
$update = $mysqli->prepare("UPDATE propostas SET valor = ?, status = 'aguardando_comprador' WHERE id = ?");
$update->bind_param("di", $valorLimpo, $proposta_id);

if ($update->execute()) {
    echo json_encode(["success" => true, "message" => "Contraproposta enviada com sucesso."]);
} else {
    echo json_encode(["success" => false, "message" => "Erro ao negociar proposta."]);
}
?>
