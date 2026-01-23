<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../conexao_bd.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

/**
 * Envia e-mail estilizado com PHPMailer
 * 
 * @param string $paraEmail
 * @param string $paraNome
 * @param string $titulo
 * @param string $htmlCorpo
 * @return bool
 */
function enviarEmailProposta($paraEmail, $paraNome, $titulo, $htmlCorpo) {
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

        $mail->setFrom($_ENV['EMAIL_USUARIO'], 'MotorGo');
        $mail->addAddress($paraEmail, $paraNome);
        $mail->isHTML(true);
        $mail->Subject = $titulo;
        $mail->Body    = $htmlCorpo;

        return $mail->send();
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/../logs/email_erros.log', date('Y-m-d H:i:s') . " - Erro ao enviar e-mail: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}
