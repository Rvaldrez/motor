<?php
session_start();
require_once "conexao_bd.php";
require_once "helpers/email_proposta.php";

header("Content-Type: application/json");

// ✅ Verifica sessão e método
if ($_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !isset($_SESSION['usuario_id'])) {
    echo json_encode(["success" => false, "message" => "Acesso negado."]);
    exit;
}

$usuario_id   = $_SESSION['usuario_id'];
$proposta_id  = $_POST['proposta_id'] ?? null;

if (!$proposta_id) {
    echo json_encode(["success" => false, "message" => "ID da proposta ausente."]);
    exit;
}

// ✅ Consulta proposta sem limitar por usuário
$sql = "
    SELECT p.*, 
           u.nome AS nome_comprador, u.email AS email_comprador,
           vend.nome AS nome_vendedor, vend.email AS email_vendedor,
           v.modelo, v.marca, v.ano_fabrica, v.id AS veiculo_id, v.usuario_id AS veiculo_dono_id
    FROM propostas p
    JOIN veiculos v ON p.veiculo_id = v.id
    JOIN usuarios u ON p.usuario_id = u.id
    JOIN usuarios vend ON v.usuario_id = vend.id
    WHERE p.id = ?
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $proposta_id);
$stmt->execute();
$result = $stmt->get_result();
$proposta = $result->fetch_assoc();
$stmt->close();

if (!$proposta) {
    echo json_encode(["success" => false, "message" => "Proposta não encontrada ou acesso negado."]);
    exit;
}

// ✅ Verifica se o usuário é vendedor
$usuarioEhVendedor = ($usuario_id === $proposta['veiculo_dono_id']);

// ✅ Descobre o comprador original da negociação
$propostaRaizId = $proposta['proposta_origem_id'] ?: $proposta['id'];
$stmtRaiz = $mysqli->prepare("SELECT usuario_id FROM propostas WHERE id = ?");
$stmtRaiz->bind_param("i", $propostaRaizId);
$stmtRaiz->execute();
$resultRaiz = $stmtRaiz->get_result();
$origem = $resultRaiz->fetch_assoc();
$stmtRaiz->close();

$usuarioEhComprador = ($usuario_id === $origem['usuario_id']);

// ✅ Bloqueia acesso se o usuário não tem relação com a proposta
if (!$usuarioEhVendedor && !$usuarioEhComprador) {
    echo json_encode(["success" => false, "message" => "Proposta não pertence a você."]);
    exit;
}

// ✅ Impede recusa duplicada
if (in_array($proposta['status'], [
    'aceita', 
    'recusada_investidor', 
    'recusada_vendedor', 
    'historico_recusada_investidor', 
    'historico_recusada_vendedor'
])) {
    echo json_encode(["success" => false, "message" => "Essa proposta já foi encerrada."]);
    exit;
}

$statusFinal = $usuarioEhVendedor ? 'recusada_vendedor' : 'recusada_investidor';

// ✅ Atualiza status
$update = $mysqli->prepare("UPDATE propostas SET status = ? WHERE id = ?");
$update->bind_param("si", $statusFinal, $proposta_id);

if ($update->execute()) {
    $veiculoTitulo = "{$proposta['marca']} {$proposta['modelo']} ({$proposta['ano_fabrica']})";
    $valorProposta = number_format($proposta['valor'], 2, ',', '.');

    if ($usuarioEhVendedor) {
        // ✉️ Email para comprador
        $nomeComprador  = $proposta['nome_comprador'];
        $emailComprador = $proposta['email_comprador'];

        $html = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; background: #fff; border:1px solid #ccc'>
            <div style='background:#1A1A1A;padding:20px;text-align:center'>
                <img src='https://motorgo.co/imagens/logo_motorgo_blk.png' alt='MotorGo' style='max-width:180px' />
            </div>
            <div style='padding: 20px 25px'>
                <h2 style='color: #c62828;'>❌ Proposta Recusada</h2>
                <p>O vendedor recusou sua proposta para o veículo:</p>
                <div style='background: #f9f9f9; padding: 15px; border: 1px solid #eee; margin: 20px 0'>
                    <p><strong>Veículo:</strong> $veiculoTitulo</p>
                    <p><strong>Última oferta:</strong> R$ $valorProposta</p>
                </div>
                <p>Se desejar, você pode fazer uma nova proposta em <strong>Oferta de Veículos</strong>.</p>
                <p><a href='https://motorgo.co/login.php' style='color:#e53935;'>Acessar minha conta</a></p>
                <hr style='margin: 30px 0'>
                <p style='font-size: 12px; color: #777'>
                    A MotorGo é um portal que aproxima vendedores de investidores. Recomendamos consultar um despachante.
                </p>
            </div>
        </div>";

        enviarEmailProposta($emailComprador, $nomeComprador, "Proposta de Veículo Recusada", $html);
    }

    if ($usuarioEhComprador) {
        // ✉️ Email para vendedor
        $nomeVendedor  = $proposta['nome_vendedor'];
        $emailVendedor = $proposta['email_vendedor'];

        $html = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; background: #fff; border:1px solid #ccc'>
            <div style='background:#1A1A1A;padding:20px;text-align:center'>
                <img src='https://motorgo.co/imagens/logo_motorgo_blk.png' alt='MotorGo' style='max-width:180px' />
            </div>
            <div style='padding: 20px 25px'>
                <h2 style='color: #c62828;'>❌ Proposta Recusada pelo Investidor</h2>
                <p>O investidor recusou a contraproposta referente ao veículo:</p>
                <div style='background: #f9f9f9; padding: 15px; border: 1px solid #eee; margin: 20px 0'>
                    <p><strong>Veículo:</strong> $veiculoTitulo</p>
                </div>
                <p>Seu veículo continua disponível para novos interessados. Acompanhe seu painel para novas propostas.</p>
                <hr style='margin: 30px 0'>
                <p style='font-size: 12px; color: #777'>
                    A MotorGo é um portal que aproxima vendedores de investidores. Recomendamos consultar um despachante.
                </p>
            </div>
        </div>";

        enviarEmailProposta($emailVendedor, $nomeVendedor, "Proposta de Veículo Recusada", $html);
    }

    // 🔄 Libera o veículo para novas propostas
    $liberar = $mysqli->prepare("UPDATE veiculos SET em_negociacao = 0 WHERE id = ?");
    $liberar->bind_param("i", $proposta['veiculo_id']);
    $liberar->execute();
    $liberar->close();

    echo json_encode(["success" => true, "message" => "Proposta recusada com sucesso."]);
} else {
    echo json_encode(["success" => false, "message" => "Erro ao atualizar proposta."]);
}

$update->close();
?>
