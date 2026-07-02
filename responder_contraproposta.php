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

if (!isset($_SESSION['usuario_id'])) {
  echo json_encode(["success" => false, "message" => "Acesso negado."]);
  exit;
}

$usuario_id = $_SESSION['usuario_id'];
$proposta_id = $_POST['proposta_id'] ?? null;
$acao = $_POST['acao'] ?? null;
$valor = $_POST['valor'] ?? null;

if (!$proposta_id || !in_array($acao, ['aceita', 'recusada', 'negociar'])) {
  echo json_encode(["success" => false, "message" => "Dados inválidos."]);
  exit;
}

// ✅ Consulta a proposta apenas pela ID
$sql = "SELECT p.*, 
               u.nome AS nome_vendedor, u.email AS email_vendedor,
               v.modelo, v.marca, v.ano_fabrica, v.quilometragem, v.preco,
               v.usuario_id AS veiculo_dono_id
        FROM propostas p
        JOIN veiculos v ON v.id = p.veiculo_id
        JOIN usuarios u ON u.id = v.usuario_id
        WHERE p.id = ?";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $proposta_id);
$stmt->execute();
$result = $stmt->get_result();
$proposta = $result->fetch_assoc();

if (!$proposta) {
  echo json_encode(["success" => false, "message" => "Proposta não encontrada."]);
  exit;
}

$status = $proposta['status'];
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

if (
    ($status === 'aguardando_comprador' && !$usuarioEhComprador) ||
    ($status === 'aguardando_vendedor' && !$usuarioEhVendedor)
) {
    echo json_encode(["success" => false, "message" => "Essa proposta não está aguardando sua resposta."]);
    exit;
}

// 🟡 Se for negociação, cria nova proposta e move a atual para histórico
if ($acao === 'negociar') {
  $valor_limpo = normalizarValorMonetario($valor);

  if ($valor_limpo <= 0) {
    echo json_encode(["success" => false, "message" => "Informe um valor válido."]);
    exit;
  }

  // Atualiza a proposta atual para histórico
  $stmtHist = $mysqli->prepare("UPDATE propostas SET status = 'historico' WHERE id = ?");
  $stmtHist->bind_param("i", $proposta_id);
  if (!$stmtHist->execute()) {
    echo json_encode(["success" => false, "message" => "Erro ao arquivar proposta anterior."]);
    exit;
  }

  // Insere nova contraproposta com status atualizado
  $status_novo = $usuarioEhComprador ? 'aguardando_vendedor' : 'aguardando_comprador';
  $stmtNova = $mysqli->prepare("
    INSERT INTO propostas (veiculo_id, usuario_id, valor, data_proposta, status, proposta_origem_id)
    VALUES (?, ?, ?, NOW(), ?, ?)
  ");

  $stmtNova->bind_param("iidsi", $proposta['veiculo_id'], $usuario_id, $valor_limpo, $status_novo, $propostaRaizId);

  if ($stmtNova->execute()) {
    // Envia e-mail para o vendedor informando da nova contraproposta
    $nomeVendedor = $proposta['nome_vendedor'];
    $emailVendedor = $proposta['email_vendedor'];
    $valorProposta = number_format($valor_limpo, 2, ',', '.');
    $veiculoTitulo = "{$proposta['marca']} {$proposta['modelo']} ({$proposta['ano_fabrica']})";

    $htmlEmail = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; background: #fff; border:1px solid #ccc'>
        <div style='background:#1A1A1A;padding:20px;text-align:center'>
            <img src='https://motorgo.co/imagens/logo_motorgo_blk.png' alt='MotorGo' style='max-width:180px' />
        </div>
        <div style='padding: 20px 25px'>
            <h2 style='color: #333;'>📨 Nova Contraproposta Recebida</h2>
            <p>Você recebeu uma nova contraproposta para o seu veículo:</p>
            <div style='background: #f9f9f9; border: 1px solid #eee; padding: 15px; margin-top: 10px'>
                <p><strong>Veículo:</strong> $veiculoTitulo</p>
                <p><strong>Nova oferta:</strong> R$ $valorProposta</p>
            </div>
            <p>Acesse o painel da MotorGo para analisar e responder.</p>
            <p><a href='https://motorgo.co/login.php'>➡ Ver Propostas Recebidas</a></p>
            <hr style='margin: 30px 0'>
            <p style='font-size: 12px; color: #777'>
                A MotorGo é um portal que aproxima vendedores de investidores. Não validamos juridicamente as partes nem os veículos. Recomendamos consultar um despachante de confiança.
            </p>
        </div>
    </div>";

    enviarEmailProposta($emailVendedor, $nomeVendedor, "Nova Contraproposta Recebida", $htmlEmail);

    echo json_encode(["success" => true, "message" => "Contraproposta enviada com sucesso."]);
  } else {
    echo json_encode(["success" => false, "message" => "Erro ao criar nova proposta."]);
  }

  exit;
}

// ✅ Aceita ou recusa
$stmt = $mysqli->prepare("UPDATE propostas SET status = ? WHERE id = ?");
$stmt->bind_param("si", $acao, $proposta_id);

if ($stmt->execute()) {
  $mensagem = ($acao === 'aceita') ? "Você aceitou a contraproposta." : "Proposta recusada.";

  if ($acao === 'recusada') {
    $nomeVendedor = $proposta['nome_vendedor'];
    $emailVendedor = $proposta['email_vendedor'];
    $valorProposta = number_format($proposta['valor'], 2, ',', '.');
    $veiculoTitulo = "{$proposta['marca']} {$proposta['modelo']} ({$proposta['ano_fabrica']})";

    $htmlEmail = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; background: #fff; border:1px solid #ccc'>
        <div style='background:#1A1A1A;padding:20px;text-align:center'>
            <img src='https://motorgo.co/imagens/logo_motorgo_blk.png' alt='MotorGo' style='max-width:180px' />
        </div>
        <div style='padding: 20px 25px'>
            <h2 style='color: #333;'>❌ Proposta de Veículo Recusada</h2>
            <p>O investidor recusou sua proposta abaixo:</p>
            <div style='background: #f9f9f9; border: 1px solid #eee; padding: 15px; margin-top: 10px'>
                <p><strong>Veículo:</strong> $veiculoTitulo</p>
                <p><strong>Última oferta:</strong> R$ $valorProposta</p>
            </div>
            <p>Seu veículo continuará visível no portal da MotorGo para novas ofertas.</p>
            <p>Acesse seu painel administrativo em <a href='https://motorgo.co/login.php'>Propostas Recebidas</a>.</p>
            <hr style='margin: 30px 0'>
            <p style='font-size: 12px; color: #777'>
                A MotorGo é um portal que aproxima vendedores de investidores. Não validamos juridicamente as partes nem os veículos. Recomendamos consultar um despachante de confiança.
            </p>
        </div>
    </div>";

    enviarEmailProposta($emailVendedor, $nomeVendedor, "Proposta de Veículo Recusada", $htmlEmail);
  }

  // 🔄 Libera o veículo para novas propostas
  $updateNegociacao = $mysqli->prepare("UPDATE veiculos SET em_negociacao = 0 WHERE id = (SELECT veiculo_id FROM propostas WHERE id = ?)");
  $updateNegociacao->bind_param("i", $proposta_id);
  $updateNegociacao->execute();
  $updateNegociacao->close();

  echo json_encode(["success" => true, "message" => $mensagem]);
} else {
  echo json_encode(["success" => false, "message" => "Erro ao atualizar proposta."]);
}
