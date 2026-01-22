<?php
session_start();
require_once "conexao_bd.php";
require_once "helpers/email_proposta.php";
header("Content-Type: application/json");

// ✅ Verifica autenticação e método
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['usuario_id'], $_SESSION['usuario_tipo'])) {
    echo json_encode(["success" => false, "message" => "Acesso negado."]);
    exit;
}

$usuario_id   = $_SESSION['usuario_id'];
$proposta_id  = intval($_POST['proposta_id'] ?? 0);
$novo_valor   = floatval($_POST['novo_valor'] ?? 0);

if ($proposta_id <= 0 || $novo_valor <= 0) {
    echo json_encode(["success" => false, "message" => "Dados inválidos."]);
    exit;
}

// ✅ Consulta a proposta
$sql = "
    SELECT p.*, 
           u.nome AS nome_comprador, u.email AS email_comprador,
           vend.nome AS nome_vendedor, vend.email AS email_vendedor,
           v.modelo, v.marca, v.ano_fabrica, v.quilometragem,
           v.usuario_id AS veiculo_dono_id
    FROM propostas p
    JOIN veiculos v ON p.veiculo_id = v.id
    JOIN usuarios u ON p.usuario_id = u.id
    JOIN usuarios vend ON v.usuario_id = vend.id
    WHERE p.id = ? AND (p.usuario_id = ? OR v.usuario_id = ?)
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("iii", $proposta_id, $usuario_id, $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$proposta = $result->fetch_assoc();

if (!$proposta) {
    echo json_encode(["success" => false, "message" => "Proposta não encontrada ou acesso negado."]);
    exit;
}




// ✅ Atualiza proposta antiga para histórico
$stmtHist = $mysqli->prepare("UPDATE propostas SET status = 'historico' WHERE id = ?");
$stmtHist->bind_param("i", $proposta_id);
$stmtHist->execute();
$stmtHist->close();

// ✅ Define status da nova proposta com base na posição do usuário
$usuarioEhVendedor = ($usuario_id === $proposta['veiculo_dono_id']);
$status_novo = $usuarioEhVendedor ? 'aguardando_comprador' : 'aguardando_vendedor';

// ✅ Cria nova contraproposta



$stmtNova = $mysqli->prepare("
    INSERT INTO propostas (veiculo_id, usuario_id, valor, data_proposta, status, proposta_origem_id)
    VALUES (?, ?, ?, NOW(), ?, ?)
");

$stmtNova->bind_param(
    "iidsi",
    $proposta['veiculo_id'],
    $usuario_id, // Aqui deve ser quem está fazendo a nova contraproposta
    $novo_valor,
    $status_novo,
    $proposta_id
);


if ($stmtNova->execute()) {
    // ✅ Envio de e-mail para quem deve responder
    $veiculo        = "{$proposta['marca']} {$proposta['modelo']} ({$proposta['ano_fabrica']})";
    $valorFormatado = number_format($novo_valor, 2, ',', '.');

    if ($usuarioEhVendedor) {
        $emailDestino = $proposta['email_comprador'];
        $nomeDestino  = $proposta['nome_comprador'];
    } else {
        $emailDestino = $proposta['email_vendedor'];
        $nomeDestino  = $proposta['nome_vendedor'];
    }

    $htmlEmail = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; background: #fff; border:1px solid #ccc'>
        <div style='background:#1A1A1A;padding:20px;text-align:center'>
            <img src='https://motorgo.co/imagens/logo_motorgo_blk.png' alt='MotorGo' style='max-width:180px' />
        </div>
        <div style='padding: 20px 25px'>
            <h2 style='color: #333;'>🚗 Você recebeu uma nova contraproposta</h2>
            <p>Um usuário fez uma nova oferta para o veículo abaixo:</p>

            <div style='background: #f9f9f9; border: 1px solid #eee; padding: 15px; margin-top: 10px'>
                <p><strong>Veículo:</strong> $veiculo</p>
                <p><strong>Nova oferta:</strong> R$ $valorFormatado</p>
            </div>

            <p>👉 <a href='https://motorgo.co/login.php' style='color:#e53935;text-decoration:none;font-weight:bold;'>Clique aqui</a> e responda o mais breve possível acessando sua área de propostas.</p>

            <hr style='margin: 30px 0'>
            <p style='font-size: 12px; color: #777'>
                A MotorGo é um portal que aproxima vendedores de investidores. Recomendamos a contratação de um despachante.
            </p>
        </div>
    </div>";

    enviarEmailProposta($emailDestino, $nomeDestino, "Você recebeu uma nova contraproposta", $htmlEmail);

    echo json_encode(["success" => true, "message" => "Contraproposta enviada com sucesso."]);
} else {
    echo json_encode(["success" => false, "message" => "Erro ao processar contraproposta."]);
}
