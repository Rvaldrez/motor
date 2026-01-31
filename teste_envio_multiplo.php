<?php
/**
 * Teste de envio múltiplo - Envia para apenas 3 investidores
 * Use este script para testar o envio sem esperar pelos 42 investidores
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/conexao_bd.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "====================================================\n";
echo "TESTE DE ENVIO MÚLTIPLO - 3 INVESTIDORES\n";
echo "====================================================\n\n";

// Configurações
define('EMAIL_SUBJECT', 'Teste - Novos Veículos Disponíveis - MotorGo');
define('BASE_URL', 'https://motorgo.co');

/**
 * Função para enviar email
 */
function enviarEmailTeste($destinatario, $nomeDestinatario, $assunto, $corpoHTML) {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['EMAIL_USUARIO'];
        $mail->Password   = $_ENV['EMAIL_SENHA'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 30;
        $mail->SMTPKeepAlive = false;

        $mail->setFrom($_ENV['EMAIL_USUARIO'], 'MotorGo - Teste');
        $mail->addAddress($destinatario, $nomeDestinatario);
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $corpoHTML;

        return $mail->send();
    } catch (Exception $e) {
        echo "\n  Erro detalhado: " . $e->getMessage() . "\n";
        return false;
    }
}

// Buscar apenas 3 investidores
echo "Buscando 3 investidores para teste...\n";
$sql = "SELECT id, nome, email
        FROM usuarios
        WHERE tipo = 'investidor'
          AND status_confirmacao = 'confirmado'
          AND status_cadastro = 'completo'
        ORDER BY nome ASC
        LIMIT 3";

$result = $conn->query($sql);
$investidores = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $investidores[] = $row;
    }
}

echo "✓ Encontrados: " . count($investidores) . " investidor(es)\n\n";

if (count($investidores) == 0) {
    echo "✗ Nenhum investidor encontrado!\n";
    exit(1);
}

// HTML de exemplo simples
$htmlTeste = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a1a1a; color: white; padding: 20px; text-align: center; }
        .content { background: white; padding: 30px; }
        .footer { background: #f4f4f4; padding: 15px; text-align: center; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin:0; color: #B22222;">MOTORGO</h1>
            <p style="margin:5px 0 0 0;">Newsletter de Teste</p>
        </div>
        <div class="content">
            <h2>🔧 Teste de Envio Múltiplo</h2>
            <p>Olá!</p>
            <p>Este é um <strong>email de teste</strong> do sistema de newsletter.</p>
            <p>Se você recebeu este email, significa que o sistema está funcionando corretamente!</p>
            <p><small>Data/Hora: ' . date('d/m/Y H:i:s') . '</small></p>
        </div>
        <div class="footer">
            <p>Email de teste - Sistema MotorGo Newsletter</p>
        </div>
    </div>
</body>
</html>';

// Enviar para os 3 investidores
echo "Iniciando envio de emails de teste...\n";
echo "----------------------------------------------------\n";

$sucessos = 0;
$falhas = 0;
$total = count($investidores);

for ($i = 0; $i < $total; $i++) {
    $investidor = $investidores[$i];
    $numero = $i + 1;
    
    echo "Enviando $numero/$total: " . $investidor['email'] . " (" . $investidor['nome'] . ")... ";
    flush();
    
    $enviado = enviarEmailTeste(
        $investidor['email'],
        $investidor['nome'],
        EMAIL_SUBJECT,
        $htmlTeste
    );
    
    if ($enviado) {
        echo "✓ Enviado\n";
        $sucessos++;
    } else {
        echo "✗ Falha\n";
        $falhas++;
    }
    flush();
    
    // Pequena pausa entre envios
    if ($i < $total - 1) {
        usleep(500000); // 0.5 segundos
    }
}

echo "----------------------------------------------------\n";
echo "\nResumo do teste:\n";
echo "  ✓ Enviados com sucesso: $sucessos\n";
echo "  ✗ Falhas: $falhas\n";
echo "\n";

if ($sucessos == $total) {
    echo "✅ TESTE COMPLETO COM SUCESSO!\n";
    echo "\nPróximos passos:\n";
    echo "  1. Verifique se os 3 emails foram recebidos\n";
    echo "  2. Se sim, o script enviar_newsletter_diario.php está pronto\n";
    echo "  3. Execute: php enviar_newsletter_diario.php\n";
} else {
    echo "⚠️ Alguns envios falharam. Verifique:\n";
    echo "  1. Arquivo logs/email_erros.log\n";
    echo "  2. Credenciais no .env\n";
    echo "  3. Conexão com servidor SMTP\n";
}

echo "\n====================================================\n";

$conn->close();
?>
