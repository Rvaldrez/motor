<?php
/**
 * DIAGNÓSTICO SMTP - MotorGo Newsletter
 * 
 * Script para testar conexão SMTP antes de enviar newsletter
 * Ajuda a identificar problemas de conexão, credenciais, portas bloqueadas, etc.
 * 
 * Uso: php teste_smtp_diagnostico.php
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "====================================================\n";
echo "DIAGNÓSTICO SMTP - MOTORGO NEWSLETTER\n";
echo "====================================================\n\n";

// 1. Verificar arquivo .env
echo "1. Verificando arquivo .env...\n";
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    echo "   ❌ ERRO: Arquivo .env não encontrado!\n";
    echo "   💡 Crie o arquivo .env baseado no .env.example\n";
    echo "   💡 Comando: cp .env.example .env\n\n";
    exit(1);
}
echo "   ✓ Arquivo .env encontrado\n";

if (!is_readable($envFile)) {
    echo "   ❌ ERRO: Arquivo .env não pode ser lido!\n";
    echo "   💡 Verifique permissões: chmod 644 .env\n\n";
    exit(1);
}
echo "   ✓ Arquivo .env é legível\n\n";

// 2. Verificar credenciais
echo "2. Verificando credenciais...\n";
$emailUsuario = $_ENV['EMAIL_USUARIO'] ?? null;
$emailSenha = $_ENV['EMAIL_SENHA'] ?? null;

if (empty($emailUsuario)) {
    echo "   ❌ ERRO: EMAIL_USUARIO não configurado no .env\n";
    echo "   💡 Adicione: EMAIL_USUARIO=seu_email@dominio.com\n\n";
    exit(1);
}
echo "   ✓ EMAIL_USUARIO: $emailUsuario\n";

if (empty($emailSenha)) {
    echo "   ❌ ERRO: EMAIL_SENHA não configurada no .env\n";
    echo "   💡 Adicione: EMAIL_SENHA=sua_senha_smtp\n\n";
    exit(1);
}
echo "   ✓ EMAIL_SENHA: " . str_repeat('*', strlen($emailSenha)) . " (" . strlen($emailSenha) . " caracteres)\n\n";

// 3. Testar conexão SMTP em múltiplas portas
echo "3. Testando conexão SMTP...\n\n";

$host = 'smtp.hostinger.com';
$configuracoes = [
    [
        'porta' => 465,
        'seguranca' => PHPMailer::ENCRYPTION_SMTPS,
        'nome' => 'SMTPS (SSL)',
        'timeout' => 15
    ],
    [
        'porta' => 587,
        'seguranca' => PHPMailer::ENCRYPTION_STARTTLS,
        'nome' => 'STARTTLS',
        'timeout' => 15
    ],
    [
        'porta' => 25,
        'seguranca' => '',
        'nome' => 'Sem criptografia',
        'timeout' => 10
    ]
];

$sucessoConexao = false;
$portaFuncionando = null;

foreach ($configuracoes as $config) {
    echo "   Tentativa: {$host}:{$config['porta']} ({$config['nome']})\n";
    echo "   Conectando";
    
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $emailUsuario;
        $mail->Password = $emailSenha;
        $mail->SMTPSecure = $config['seguranca'];
        $mail->Port = $config['porta'];
        $mail->Timeout = $config['timeout'];
        $mail->CharSet = 'UTF-8';
        
        // Desabilitar verificação SSL para testes
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Debug modo 2 para ver conexão
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = function($str, $level) {
            echo ".";
        };
        
        $mail->setFrom($emailUsuario, 'MotorGo Teste');
        $mail->addAddress($emailUsuario, 'Teste');
        $mail->isHTML(true);
        $mail->Subject = 'Teste SMTP Diagnóstico';
        $mail->Body = '<p>Este é um email de teste do diagnóstico SMTP.</p>';
        
        // Tentar enviar
        if ($mail->send()) {
            echo "\n   ✓ Conectado e autenticado com sucesso!\n";
            echo "   ✓ Email de teste enviado com sucesso!\n";
            echo "   ✓ Porta {$config['porta']} está funcionando!\n\n";
            $sucessoConexao = true;
            $portaFuncionando = $config['porta'];
            break;  // Sucesso, não precisa testar outras portas
        }
    } catch (Exception $e) {
        echo "\n   ❌ Falha: " . $e->getMessage() . "\n\n";
        continue;  // Tentar próxima porta
    }
}

echo "====================================================\n";

if ($sucessoConexao) {
    echo "RESULTADO: ✓ SMTP FUNCIONANDO\n";
    echo "====================================================\n\n";
    echo "✅ Conexão SMTP está OK na porta $portaFuncionando!\n";
    echo "✅ Credenciais estão corretas\n";
    echo "✅ Email de teste foi enviado para: $emailUsuario\n\n";
    echo "📧 Verifique sua caixa de entrada (pode estar no spam)\n\n";
    echo "PRÓXIMO PASSO:\n";
    echo "  Execute a newsletter: php enviar_newsletter_diario.php\n\n";
    exit(0);
} else {
    echo "RESULTADO: ❌ SMTP NÃO ESTÁ FUNCIONANDO\n";
    echo "====================================================\n\n";
    echo "POSSÍVEIS CAUSAS:\n\n";
    echo "1. 🔒 Credenciais Incorretas\n";
    echo "   - Verifique EMAIL_USUARIO e EMAIL_SENHA no arquivo .env\n";
    echo "   - Certifique-se de usar a senha correta (não a senha do email, mas senha SMTP)\n\n";
    
    echo "2. 🚫 Portas Bloqueadas\n";
    echo "   - Todas as portas testadas (465, 587, 25) falharam\n";
    echo "   - Entre em contato com seu provedor de hospedagem\n";
    echo "   - Peça para desbloquear portas SMTP\n\n";
    
    echo "3. 🌐 Servidor SMTP Incorreto\n";
    echo "   - Servidor atual: $host\n";
    echo "   - Verifique se este é o servidor correto para seu email\n\n";
    
    echo "4. 🔧 Configuração do Servidor\n";
    echo "   - Firewall pode estar bloqueando conexões SMTP\n";
    echo "   - SELinux ou outras políticas de segurança\n\n";
    
    echo "PRÓXIMOS PASSOS:\n";
    echo "  1. Verifique arquivo .env (nano .env)\n";
    echo "  2. Confirme credenciais com provedor de email\n";
    echo "  3. Entre em contato com suporte da hospedagem\n";
    echo "  4. Peça para desbloquear portas SMTP (465, 587)\n\n";
    
    echo "LOGS:\n";
    echo "  Verifique: logs/email_erros.log para mais detalhes\n\n";
    exit(1);
}
