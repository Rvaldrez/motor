<?php
/**
 * Teste de envio de email único
 * Envia um email de teste para verificar se a configuração SMTP está funcionando
 * 
 * ⚠️ IMPORTANTE: Edite a variável $emailTeste antes de executar!
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/conexao_bd.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "====================================================\n";
echo "TESTE DE ENVIO ÚNICO\n";
echo "====================================================\n\n";

// ⚠️ MUDE AQUI PARA SEU EMAIL DE TESTE!
$emailTeste = "r.robertovj@gmail.com";  // ⚠️ EDITE ESTA LINHA!
$nomeTeste = "Teste Newsletter";

// Verificar se o email foi alterado
if ($emailTeste === "seu_email@dominio.com") {
    echo "✗ ERRO: Você precisa editar o arquivo e alterar a variável \$emailTeste!\n";
    echo "  Edite a linha 18 e coloque seu email real.\n\n";
    exit(1);
}

echo "Enviando email de teste para: $emailTeste\n";
echo "De: " . $_ENV['EMAIL_USUARIO'] . "\n\n";

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

    $mail->setFrom($_ENV['EMAIL_USUARIO'], 'MotorGo - Teste');
    $mail->addAddress($emailTeste, $nomeTeste);
    $mail->isHTML(true);
    $mail->Subject = 'Teste - Newsletter MotorGo';
    
    // Email de teste simples mas profissional
    $mail->Body = '
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
            .success { color: #28a745; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1 style="margin:0; color: #B22222;">MOTORGO</h1>
                <p style="margin:5px 0 0 0;">Sistema de Newsletter</p>
            </div>
            <div class="content">
                <h2>✅ Teste de Configuração</h2>
                <p>Olá, ' . htmlspecialchars($nomeTeste) . '!</p>
                <p class="success">Se você está recebendo este email, significa que o sistema de newsletter está configurado corretamente!</p>
                <p><strong>Configurações verificadas:</strong></p>
                <ul>
                    <li>✓ Conexão SMTP funcionando</li>
                    <li>✓ Credenciais do .env corretas</li>
                    <li>✓ PHPMailer instalado</li>
                    <li>✓ Envio de HTML funcionando</li>
                </ul>
                <p>Próximos passos:</p>
                <ol>
                    <li>Execute o teste completo com <code>teste_newsletter.php</code></li>
                    <li>Teste o envio real com veículos usando <code>enviar_newsletter_diario.php</code></li>
                    <li>Configure o CronJob para execução automática diária</li>
                </ol>
            </div>
            <div class="footer">
                <p>Este é um email de teste do sistema MotorGo Newsletter</p>
                <p>Data/Hora: ' . date('d/m/Y H:i:s') . '</p>
            </div>
        </div>
    </body>
    </html>';

    if ($mail->send()) {
        echo "✓ Email enviado com SUCESSO!\n\n";
        echo "Próximos passos:\n";
        echo "  1. Verifique a caixa de entrada de: $emailTeste\n";
        echo "  2. Se não recebeu, verifique a pasta de SPAM\n";
        echo "  3. Se recebeu, prossiga para o teste completo\n";
    }
} catch (Exception $e) {
    echo "✗ ERRO ao enviar: " . $e->getMessage() . "\n\n";
    echo "Possíveis causas:\n";
    echo "  1. Credenciais incorretas no .env (EMAIL_USUARIO ou EMAIL_SENHA)\n";
    echo "  2. Servidor SMTP não está acessível\n";
    echo "  3. Porta 465 bloqueada no firewall\n";
    echo "  4. Arquivo .env não existe ou não foi carregado\n\n";
    echo "Verificações:\n";
    echo "  - EMAIL_USUARIO no .env: " . (isset($_ENV['EMAIL_USUARIO']) ? $_ENV['EMAIL_USUARIO'] : 'NÃO CONFIGURADO') . "\n";
    echo "  - EMAIL_SENHA no .env: " . (isset($_ENV['EMAIL_SENHA']) ? '***' . substr($_ENV['EMAIL_SENHA'], -3) : 'NÃO CONFIGURADO') . "\n";
}

echo "\n====================================================\n";
?>
