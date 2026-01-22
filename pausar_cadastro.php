<?php
session_start();
require_once "conexao_bd.php";
require_once "helpers/email_proposta.php";
header("Content-Type: application/json");

// 🔍 Verifica se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Método não permitido."]);
    exit;
}

// 🔍 Pega usuario_id do POST (fluxo de cadastro) ou da sessão (usuário logado)
$usuario_id = null;

if (isset($_POST['usuario_id']) && !empty($_POST['usuario_id'])) {
    // Fluxo de cadastro - usuário não logado ainda
    $usuario_id = intval($_POST['usuario_id']);
} elseif (isset($_SESSION['usuario_id'])) {
    // Usuário já logado
    $usuario_id = $_SESSION['usuario_id'];
} else {
    echo json_encode(["success" => false, "message" => "Usuário não identificado."]);
    exit;
}

// 🔍 Busca dados do usuário
$stmt = $mysqli->prepare("SELECT nome, email, status_cadastro FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();

if (!$usuario) {
    echo json_encode(["success" => false, "message" => "Usuário não encontrado."]);
    exit;
}

// 🚫 Impede pausar se já estiver completo
if ($usuario['status_cadastro'] === 'completo') {
    echo json_encode(["success" => false, "message" => "Cadastro já foi finalizado."]);
    exit;
}

$mysqli->begin_transaction();

try {
    // 🗑️ Remove veículos vazios/incompletos deste usuário
    $deleteVeiculos = $mysqli->prepare("
        DELETE FROM veiculos 
        WHERE usuario_id = ? 
        AND (placa IS NULL OR placa = '' OR modelo IS NULL OR modelo = '')
    ");
    $deleteVeiculos->bind_param("i", $usuario_id);
    $deleteVeiculos->execute();
    
    // 📊 Log da limpeza
    $veiculosRemovidos = $deleteVeiculos->affected_rows;
    
    // ✅ MANTÉM status_cadastro como 'incompleto' - não altera
    // O usuário pode continuar normalmente depois
    
    $mysqli->commit();
    
    // 📧 Envia email de continuação
    $link = "https://motorgo.co/finalizar_cadastro.php?usuario_id={$usuario_id}";
    $nomeUsuario = htmlspecialchars($usuario['nome']);
    
    $htmlEmail = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; background: #fff; border: 1px solid #ddd; border-radius: 10px; overflow: hidden;'>
        <div style='background: linear-gradient(135deg, #1A1A1A 0%, #2c2c2c 100%); padding: 40px 20px; text-align: center;'>
            <img src='https://motorgo.co/imagens/logo_motorgo_blk.png' alt='MotorGo' style='max-width: 180px; margin-bottom: 15px;' />
            <h1 style='color: #fff; margin: 0; font-size: 24px;'>Continue seu cadastro! 🚗</h1>
        </div>
        
        <div style='padding: 40px 30px;'>
            <h2 style='color: #333; margin-top: 0;'>Olá, {$nomeUsuario}! 👋</h2>
            
            <p style='font-size: 16px; line-height: 1.6; color: #555;'>
                Você pausou seu cadastro na <strong>MotorGo</strong>, mas estamos aqui para ajudar você a finalizar rapidinho!
            </p>
            
            <div style='background: #f8f9fa; border-left: 4px solid #B22222; padding: 20px; margin: 25px 0;'>
                <h3 style='margin: 0 0 10px 0; color: #B22222;'>✅ O que você já fez:</h3>
                <p style='margin: 0; color: #666;'>• Dados pessoais completos</p>
            </div>
            
            <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin: 25px 0;'>
                <h3 style='margin: 0 0 10px 0; color: #856404;'>⏳ O que falta fazer:</h3>
                <p style='margin: 0; color: #856404;'>• Dados do veículo (2 minutos)</p>
                <p style='margin: 5px 0 0 0; color: #856404;'>• Upload de 6 fotos (3 minutos)</p>
            </div>
            
            <div style='text-align: center; margin: 40px 0;'>
                <a href='{$link}' style='background: linear-gradient(135deg, #B22222 0%, #8B0000 100%); color: #fff; padding: 18px 35px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px; display: inline-block; box-shadow: 0 4px 15px rgba(178, 34, 34, 0.3); transition: all 0.3s ease;'>
                    🚀 Finalizar Cadastro Agora
                </a>
            </div>
            
            <div style='background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 6px; padding: 15px; margin: 25px 0;'>
                <p style='margin: 0; font-size: 14px; color: #0066cc;'>
                    💡 <strong>Dica:</strong> Seus dados estão seguros! Este link é válido por 30 dias.
                </p>
            </div>
            
            <hr style='margin: 30px 0; border: none; border-top: 1px solid #eee;'>
            
            <p style='font-size: 13px; color: #888; margin-bottom: 5px;'>
                Se você não solicitou este cadastro, pode ignorar este email.
            </p>
            <p style='font-size: 13px; color: #888; margin: 0;'>
                Atenciosamente,<br>
                <strong>Equipe MotorGo</strong> 🚗<br>
                <a href='https://motorgo.co' style='color: #B22222;'>motorgo.co</a>
            </p>
        </div>
    </div>";
    
    $emailEnviado = enviarEmailProposta(
        $usuario['email'], 
        $usuario['nome'], 
        "Continue seu cadastro na MotorGo - Falta pouco! 🚗", 
        $htmlEmail
    );
    
    // 📝 Log da operação
    error_log("PAUSAR_CADASTRO: Usuario {$usuario_id} pausou cadastro. Veiculos vazios removidos: {$veiculosRemovidos}. Email enviado: " . ($emailEnviado ? 'SIM' : 'NAO'));
    
    echo json_encode([
        "success" => true,
        "message" => "Cadastro pausado com sucesso! Verifique seu email para continuar.",
        "veiculos_removidos" => $veiculosRemovidos,
        "email_enviado" => $emailEnviado
    ]);
    
} catch (Exception $e) {
    $mysqli->rollback();
    error_log("ERRO_PAUSAR_CADASTRO: " . $e->getMessage());
    
    echo json_encode([
        "success" => false, 
        "message" => "Erro interno. Tente novamente em alguns instantes."
    ]);
}
?>