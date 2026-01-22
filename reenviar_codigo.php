<?php
session_start();
require_once "conexao_bd.php";
require_once "helpers/email_proposta.php";
header("Content-Type: application/json");

// Verifica se o usuário está logado
$usuario_id = $_POST['usuario_id'] ?? $_SESSION['usuario_id'] ?? null;

if (!$usuario_id) {
    echo json_encode([
        "success" => false,
        "message" => "Usuário não identificado."
    ]);
    exit;
}

$usuario_id = intval($usuario_id);

// Busca dados do usuário
$stmt = $mysqli->prepare("
    SELECT u.nome, u.email, u.status_confirmacao,
           v.marca, v.modelo
    FROM usuarios u
    LEFT JOIN veiculos v ON u.id = v.usuario_id
    WHERE u.id = ?
    ORDER BY v.id DESC
    LIMIT 1
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Usuário não encontrado."
    ]);
    exit;
}

$dados = $result->fetch_assoc();

// Verifica se já está confirmado
if ($dados['status_confirmacao'] === 'confirmado') {
    echo json_encode([
        "success" => false,
        "message" => "Este cadastro já foi confirmado."
    ]);
    exit;
}

// Gera novo código
$codigo = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
$expira = date('Y-m-d H:i:s', strtotime('+30 minutes'));

// Atualiza no banco
$stmt = $mysqli->prepare("
    UPDATE usuarios 
    SET token_confirmacao = ?, 
        token_expira = ?
    WHERE id = ?
");
$stmt->bind_param("ssi", $codigo, $expira, $usuario_id);

if ($stmt->execute()) {
    // Envia email
    $mensagem = criarEmailConfirmacao(
        $dados['nome'], 
        $dados['marca'] ?? '', 
        $dados['modelo'] ?? '', 
        $codigo
    );
    
    $emailEnviado = enviarEmailProposta(
        $dados['email'], 
        $dados['nome'], 
        "🔐 Novo Código de Confirmação - MotorGo", 
        $mensagem
    );
    
    if ($emailEnviado) {
        echo json_encode([
            "success" => true,
            "message" => "Novo código enviado para " . $dados['email']
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Erro ao enviar email. Tente novamente."
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "Erro ao gerar novo código. Tente novamente."
    ]);
}

/**
 * Template de email reutilizável
 */
function criarEmailConfirmacao($nome, $marca, $modelo, $codigo) {
    $veiculo_info = '';
    if ($marca && $modelo) {
        $veiculo_info = " para seu veículo <strong>$marca $modelo</strong>";
    }
    
    return "
    <div style='font-family:Arial,sans-serif; max-width:600px; margin:0 auto; background:#ffffff;'>
        <!-- Header com logo -->
        <div style='background:#1a1a1a; color:white; padding:30px 20px; text-align:center;'>
            <img src='https://motorgo.co/imagens/logo_motorgo_blk.png' alt='MotorGo' style='max-width:200px; height:auto; margin-bottom:15px;'>
            <h1 style='margin:0; font-size:28px; color:#ffffff;'>✅ Novo Código de Confirmação</h1>
        </div>
        
        <div style='padding:30px; background:#f8f9fa;'>
            <p style='font-size:18px; margin-bottom:20px;'>Olá <strong>{$nome}</strong>,</p>
            <p style='font-size:16px; line-height:1.6; margin-bottom:25px;'>Você solicitou um novo código de confirmação{$veiculo_info}.</p>
            
            <div style='background:white; border:2px solid #28a745; padding:25px; margin:25px 0; text-align:center; border-radius:8px;'>
                <p style='margin:0 0 10px 0; font-size:16px; color:#666;'>Seu novo código é:</p>
                <h2 style='margin:10px 0; font-size:42px; letter-spacing:8px; color:#333; font-family:monospace;'>{$codigo}</h2>
                <p style='margin:10px 0 0 0; font-size:14px; color:#999;'>Válido por 30 minutos</p>
            </div>
            
            <p style='font-size:16px; line-height:1.6; margin:25px 0;'>Digite este código na tela de confirmação para ativar seu anúncio.</p>
            
            <div style='background:#fff3cd; border:1px solid #ffeeba; padding:15px; border-radius:6px; margin:25px 0;'>
                <p style='margin:0; color:#856404; font-size:14px;'>
                    ⏰ <strong>Atenção:</strong> Após confirmar, seu anúncio será publicado imediatamente!
                </p>
            </div>
        </div>
        
        <!-- Footer -->
        <div style='background:#1a1a1a; color:#999; padding:20px; text-align:center; font-size:12px;'>
            <p style='margin:0 0 10px 0;'>© " . date('Y') . " MotorGo - Mais valor para você!</p>
            <p style='margin:0;'>
                <a href='https://motorgo.com.br' style='color:#B22222; text-decoration:none;'>www.motorgo.com.br</a> | 
                <a href='mailto:sac@motorgo.co' style='color:#B22222; text-decoration:none;'>sac@motorgo.co</a>
            </p>
        </div>
    </div>";
}
?>