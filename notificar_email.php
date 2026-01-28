<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

require 'vendor/autoload.php'; // se usar Composer
require_once 'conexao_bd.php';

// Carrega variáveis de ambiente
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

function enviarEmailNotificacao($destinatarioEmail, $destinatarioNome, $tipo, $dados = []) {
    $mail = new PHPMailer(true);

    try {
        // Configuração SMTP
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['EMAIL_USUARIO'];
        $mail->Password   = $_ENV['EMAIL_SENHA'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($_ENV['EMAIL_USUARIO'], 'MotorGo');
        $mail->addAddress($destinatarioEmail, $destinatarioNome);
        $mail->isHTML(true);
        $mail->Subject = "📩 Nova atualização na sua negociação - MotorGo";

        // Conteúdo
        $conteudo = "<p>Olá <strong>$destinatarioNome</strong>,</p>";

        switch ($tipo) {
            case 'proposta_recebida':
                $conteudo .= "<p>Você recebeu uma <strong>nova proposta</strong> no seu veículo <strong>{$dados['modelo']}</strong>.</p>";
                break;

            case 'contraproposta_recebida':
                $conteudo .= "<p>Você recebeu uma <strong>contraproposta</strong> do vendedor para o veículo <strong>{$dados['modelo']}</strong>.</p>";
                break;

            case 'proposta_aceita':
                $conteudo .= "<p>Parabéns! Sua <strong>proposta foi aceita</strong> para o veículo <strong>{$dados['modelo']}</strong>.</p>";
                break;

            default:
                $conteudo .= "<p>Há uma nova atualização na sua negociação.</p>";
        }

        $conteudo .= "<br><p>Acesse sua conta no <a href='https://www.seusite.com.br'>MotorGo</a> para continuar a negociação.</p>";
        $conteudo .= "<br><p style='font-size: 13px; color: #999;'>Este é um e-mail automático. Não responda.</p>";

        $mail->Body = $conteudo;
        $mail->AltBody = strip_tags($conteudo);

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
