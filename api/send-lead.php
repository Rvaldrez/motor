<?php
// Headers para API
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Responde OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Pega os dados
$input = json_decode(file_get_contents('php://input'), true);

// Valida dados básicos
if (!$input || !isset($input['nome']) || !isset($input['email']) || !isset($input['whatsapp'])) {
    echo json_encode(['error' => 'Dados incompletos']);
    exit;
}

$nome = $input['nome'];
$email = $input['email'];
$whatsapp = $input['whatsapp'];

// Inclui o PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

// Carrega as variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Cria o email
try {
    $mail = new PHPMailer(true);
    
    // Configurações do servidor (IGUAL ao seu email_proposta.php)
    $mail->isSMTP();
    $mail->Host       = 'smtp.hostinger.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['EMAIL_USUARIO'];
    $mail->Password   = $_ENV['EMAIL_SENHA'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8';

    // Destinatários
    $mail->setFrom($_ENV['EMAIL_USUARIO'], 'MotorGo');
    $mail->addAddress('sac@motorgo.co', 'SAC MotorGo');
    
    // Conteúdo
    $mail->isHTML(true);
    $mail->Subject = "🚗 Nova Solicitação Código VIP - $nome";
    
    // Corpo do email SIMPLES
    $mail->Body = "
    <h2>Nova Solicitação de Código VIP</h2>
    <p><strong>Nome:</strong> $nome</p>
    <p><strong>Email:</strong> $email</p>
    <p><strong>WhatsApp:</strong> $whatsapp</p>
    <p><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>
    <hr>
    <p><small>Email automático da landing page</small></p>
    ";
    
    // Envia
    $mail->send();
    
    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Código VIP solicitado com sucesso!'
    ]);
    
} catch (Exception $e) {
    // Se der erro no email, salva em CSV como backup
    $csv_file = __DIR__ . '/../leads_backup.csv';
    $is_new = !file_exists($csv_file);
    
    $fp = fopen($csv_file, 'a');
    if ($is_new) {
        fputcsv($fp, ['Data/Hora', 'Nome', 'Email', 'WhatsApp']);
    }
    fputcsv($fp, [date('Y-m-d H:i:s'), $nome, $email, $whatsapp]);
    fclose($fp);
    
    // Retorna sucesso mesmo assim (para não perder o lead)
    echo json_encode([
        'success' => true,
        'message' => 'Solicitação recebida! Entraremos em contato.',
        'debug' => 'Email falhou mas lead salvo em CSV'
    ]);
}
?>