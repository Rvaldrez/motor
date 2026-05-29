<?php
session_start();
require_once "conexao_bd.php";
require_once "helpers/email_proposta.php";
header("Content-Type: application/json");

function normalizarValorMonetario($valor): float {
    if (is_numeric($valor)) {
        return (float) $valor;
    }

    $valor = trim((string) $valor);
    $valor = str_replace(['R$', ' '], '', $valor);
    $valor = preg_replace('/[^\d,.\-]/', '', $valor);

    if ($valor === '') {
        return 0.0;
    }

    if (strpos($valor, ',') !== false) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $valor)) {
        $valor = str_replace('.', '', $valor);
    }

    return (float) $valor;
}

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'investidor') {
    echo json_encode(["success" => false, "message" => "Acesso negado."]);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$veiculo_id = $_POST['veiculo_id'] ?? null;
$valor      = $_POST['valor'] ?? null;

if (!$veiculo_id || !$valor) {
    echo json_encode(["success" => false, "message" => "Dados inválidos."]);
    exit;
}

$valorNumerico = normalizarValorMonetario($valor);
if ($valorNumerico <= 0) {
    echo json_encode(["success" => false, "message" => "Valor da proposta inválido."]);
    exit;
}

// Verifica se o veículo pertence a outro usuário e se está disponível
$verificaDono = $mysqli->prepare("
    SELECT v.usuario_id, v.em_negociacao, u.nome, u.email 
    FROM veiculos v 
    JOIN usuarios u ON v.usuario_id = u.id 
    WHERE v.id = ?
");
$verificaDono->bind_param("i", $veiculo_id);
$verificaDono->execute();
$verificaDono->bind_result($dono_id, $negociando, $nomeVendedor, $emailVendedor);
$verificaDono->fetch();
$verificaDono->close();

if (!$dono_id) {
    echo json_encode(["success" => false, "message" => "Veículo não encontrado."]);
    exit;
}

if ($dono_id == $usuario_id) {
    echo json_encode(["success" => false, "message" => "Você não pode fazer uma proposta para o seu próprio veículo."]);
    exit;
}

if ($negociando == 1) {
    echo json_encode(["success" => false, "message" => "Este veículo já está em negociação."]);
    exit;
}

// ✅ Insere proposta inicial
$stmt = $mysqli->prepare("
    INSERT INTO propostas (veiculo_id, usuario_id, valor, data_proposta, status)
    VALUES (?, ?, ?, NOW(), 'aguardando_vendedor')
");
$stmt->bind_param("iid", $veiculo_id, $usuario_id, $valorNumerico);

if ($stmt->execute()) {
    // 🔄 Recupera ID recém-criado
    $novoId = $mysqli->insert_id;

    // ✅ Atualiza o campo proposta_origem_id com o próprio ID
    $mysqli->query("UPDATE propostas SET proposta_origem_id = $novoId WHERE id = $novoId");

    // 🔄 Marca veículo como em negociação
    $update = $mysqli->prepare("UPDATE veiculos SET em_negociacao = 1 WHERE id = ?");
    $update->bind_param("i", $veiculo_id);
    $update->execute();
    $update->close();

    // 🔔 Envia e-mail para o vendedor
    $stmt_veiculo = $mysqli->prepare("SELECT marca, modelo, ano_fabrica FROM veiculos WHERE id = ?");
    $stmt_veiculo->bind_param("i", $veiculo_id);
    $stmt_veiculo->execute();
    $res = $stmt_veiculo->get_result();
    $veiculo = $res->fetch_assoc();
    $stmt_veiculo->close();

    $titulo = "Você recebeu uma proposta de negociação de veículo";
    $veiculoTitulo = "{$veiculo['marca']} {$veiculo['modelo']} ({$veiculo['ano_fabrica']})";
    $valorFormatado = number_format($valorNumerico, 2, ',', '.');

    $htmlEmail = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; border:1px solid #ccc; background-color: #fff'>
        <div style='background:#1A1A1A;padding:20px;text-align:center'>
            <img src='https://motorgo.co/imagens/logo_motorgo_blk.png' alt='MotorGo' style='max-width:180px' />
        </div>
        <div style='padding: 20px 25px'>
            <h2 style='color: #2e7d32;'>🚗 Nova Proposta Recebida</h2>
            <p>Você recebeu uma proposta para o veículo abaixo:</p>

            <div style='background: #f9f9f9; padding: 15px; margin: 20px 0; border: 1px solid #eee'>
                <p><strong>Veículo:</strong> $veiculoTitulo</p>
                <p><strong>Oferta:</strong> R$ $valorFormatado</p>
            </div>

            <p style='margin-bottom:20px'>Acesse agora sua área de propostas para responder:</p>
            <p><a href='https://motorgo.co/login.php' style='display:inline-block;padding:10px 20px;background:#2e7d32;color:white;text-decoration:none;border-radius:5px'>Ver Propostas</a></p>

            <hr style='margin: 30px 0'>
            <p style='font-size: 12px; color: #777'>
                A MotorGo é um portal que aproxima vendedores de investidores. Não validamos juridicamente as partes nem os veículos. Recomendamos consultar um despachante de confiança.
            </p>
        </div>
    </div>";

    enviarEmailProposta($emailVendedor, $nomeVendedor, $titulo, $htmlEmail);

    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "Erro ao registrar proposta: " . $stmt->error]);
}
