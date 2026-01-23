<?php
/**
 * Script para enviar newsletter diário com novos veículos cadastrados
 * 
 * Este script deve ser executado diariamente via cron às 9h da manhã
 * Comando cron: 0 9 * * * /usr/bin/php /caminho/completo/cron/enviar_newsletter_diario.php
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Definir o timezone
date_default_timezone_set('America/Sao_Paulo');

// Incluir dependências
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../conexao_bd.php';

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// Configurar log
$logFile = __DIR__ . '/../logs/newsletter_diario.log';

/**
 * Registra mensagens no arquivo de log
 */
function registrarLog($mensagem) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $mensagem\n", FILE_APPEND);
}

registrarLog("=== Início da execução do newsletter diário ===");

try {
    // 1. Buscar veículos cadastrados ontem com status 'completo' e não em negociação
    $dataOntem = date('Y-m-d', strtotime('-1 day'));
    
    $sqlVeiculos = "
        SELECT 
            v.id,
            v.modelo,
            v.ano_fabrica,
            v.quilometragem,
            f.caminho_foto
        FROM veiculos v
        LEFT JOIN (
            SELECT veiculo_id, caminho_foto
            FROM fotos_veiculos
            WHERE ordem_exibicao = 1
            ORDER BY veiculo_id, id
        ) f ON f.veiculo_id = v.id
        WHERE DATE(v.data_cadastro) = ?
        AND v.status = 'completo'
        AND v.em_negociacao = 0
        ORDER BY v.data_cadastro DESC
    ";
    
    $stmtVeiculos = $mysqli->prepare($sqlVeiculos);
    if (!$stmtVeiculos) {
        throw new Exception("Erro ao preparar query de veículos: " . $mysqli->error);
    }
    
    $stmtVeiculos->bind_param("s", $dataOntem);
    $stmtVeiculos->execute();
    $resultVeiculos = $stmtVeiculos->get_result();
    
    $veiculos = [];
    while ($veiculo = $resultVeiculos->fetch_assoc()) {
        $veiculos[] = $veiculo;
    }
    $stmtVeiculos->close();
    
    registrarLog("Total de veículos encontrados: " . count($veiculos));
    
    // Se não há veículos novos, encerrar execução
    if (empty($veiculos)) {
        registrarLog("Nenhum veículo novo para enviar. Encerrando.");
        registrarLog("=== Fim da execução ===\n");
        exit(0);
    }
    
    // 2. Buscar usuários investidores ativos e confirmados
    $sqlUsuarios = "
        SELECT id, nome, email
        FROM usuarios
        WHERE status_cadastro = 'completo'
        AND status_confirmacao = 'confirmado'
        AND tipo = 'investidor'
    ";
    
    $resultUsuarios = $mysqli->query($sqlUsuarios);
    if (!$resultUsuarios) {
        throw new Exception("Erro ao buscar usuários: " . $mysqli->error);
    }
    
    $totalUsuarios = $resultUsuarios->num_rows;
    registrarLog("Total de usuários investidores: $totalUsuarios");
    
    if ($totalUsuarios === 0) {
        registrarLog("Nenhum usuário investidor encontrado. Encerrando.");
        registrarLog("=== Fim da execução ===\n");
        exit(0);
    }
    
    // 3. Preparar template de email
    $baseUrl = 'https://motorgo.co'; // Ajustar conforme necessário
    
    $htmlVeiculos = '';
    foreach ($veiculos as $v) {
        $foto = !empty($v['caminho_foto']) ? $baseUrl . '/' . $v['caminho_foto'] : $baseUrl . '/imagens/default_car.png';
        $modelo = htmlspecialchars($v['modelo']);
        $ano = htmlspecialchars($v['ano_fabrica']);
        $km = $v['quilometragem'] ? number_format($v['quilometragem'], 0, '', '.') . ' km' : 'N/A';
        
        $htmlVeiculos .= "
        <tr>
            <td style='padding: 20px; border-bottom: 1px solid #e0e0e0;'>
                <table width='100%' cellpadding='0' cellspacing='0'>
                    <tr>
                        <td width='200' style='padding-right: 20px;'>
                            <img src='$foto' alt='$modelo' style='width: 100%; max-width: 200px; height: auto; border-radius: 8px;' />
                        </td>
                        <td valign='top'>
                            <h3 style='margin: 0 0 10px 0; color: #333; font-size: 20px;'>$modelo</h3>
                            <p style='margin: 5px 0; color: #666; font-size: 14px;'><strong>Ano:</strong> $ano</p>
                            <p style='margin: 5px 0; color: #666; font-size: 14px;'><strong>Quilometragem:</strong> $km</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        ";
    }
    
    // 4. Enviar emails para cada usuário
    $emailsEnviados = 0;
    $emailsFalhados = 0;
    
    // Preparar statement para inserir registro de envio
    $stmtRegistro = $mysqli->prepare("INSERT INTO emails_automaticos (usuario_id, tipo) VALUES (?, 'newsletter_novo_veiculo')");
    if (!$stmtRegistro) {
        throw new Exception("Erro ao preparar registro de envio: " . $mysqli->error);
    }
    
    while ($usuario = $resultUsuarios->fetch_assoc()) {
        $usuarioId = $usuario['id'];
        $usuarioNome = $usuario['nome'];
        $usuarioEmail = $usuario['email'];
        
        try {
            // Configurar PHPMailer
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['EMAIL_USUARIO'] ?? 'sac@motorgo.co';
            $mail->Password   = $_ENV['EMAIL_SENHA'] ?? '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            $mail->CharSet    = 'UTF-8';
            
            $mail->setFrom($_ENV['EMAIL_USUARIO'] ?? 'sac@motorgo.co', 'MotorGo');
            $mail->addAddress($usuarioEmail, $usuarioNome);
            $mail->isHTML(true);
            
            $totalVeiculos = count($veiculos);
            $mail->Subject = "🚗 Novos Veículos Disponíveis - MotorGo";
            
            // Corpo do email
            $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            </head>
            <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;'>
                <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f4f4f4; padding: 20px;'>
                    <tr>
                        <td align='center'>
                            <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                                <!-- Header -->
                                <tr>
                                    <td style='background-color: #B22222; padding: 30px; text-align: center;'>
                                        <h1 style='margin: 0; color: #ffffff; font-size: 28px;'>MotorGo</h1>
                                        <p style='margin: 10px 0 0 0; color: #ffffff; font-size: 16px;'>Sua plataforma de investimento em veículos</p>
                                    </td>
                                </tr>
                                
                                <!-- Saudação -->
                                <tr>
                                    <td style='padding: 30px;'>
                                        <h2 style='margin: 0 0 20px 0; color: #333; font-size: 24px;'>Olá, $usuarioNome!</h2>
                                        <p style='margin: 0 0 20px 0; color: #666; font-size: 16px; line-height: 1.5;'>
                                            Temos <strong>$totalVeiculos " . ($totalVeiculos > 1 ? 'novos veículos' : 'novo veículo') . "</strong> cadastrado" . ($totalVeiculos > 1 ? 's' : '') . " ontem na plataforma MotorGo!
                                            Confira as oportunidades abaixo:
                                        </p>
                                    </td>
                                </tr>
                                
                                <!-- Lista de Veículos -->
                                <tr>
                                    <td>
                                        <table width='100%' cellpadding='0' cellspacing='0'>
                                            $htmlVeiculos
                                        </table>
                                    </td>
                                </tr>
                                
                                <!-- Call to Action -->
                                <tr>
                                    <td style='padding: 30px; text-align: center;'>
                                        <a href='$baseUrl/painel_investidor.php' style='display: inline-block; padding: 15px 40px; background-color: #B22222; color: #ffffff; text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold;'>
                                            Ver Todos os Veículos
                                        </a>
                                    </td>
                                </tr>
                                
                                <!-- Footer -->
                                <tr>
                                    <td style='padding: 20px 30px; background-color: #f8f8f8; border-top: 1px solid #e0e0e0;'>
                                        <p style='margin: 0 0 10px 0; color: #999; font-size: 13px; text-align: center;'>
                                            Este é um e-mail automático de notificação. Por favor, não responda.
                                        </p>
                                        <p style='margin: 0; color: #999; font-size: 13px; text-align: center;'>
                                            © " . date('Y') . " MotorGo. Todos os direitos reservados.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            ";
            
            // Versão texto plano
            $mail->AltBody = "Olá, $usuarioNome!\n\nTemos $totalVeiculos " . ($totalVeiculos > 1 ? 'novos veículos cadastrados' : 'novo veículo cadastrado') . " ontem na MotorGo.\n\nAcesse $baseUrl/painel_investidor.php para ver mais detalhes.\n\n---\nEste é um e-mail automático. Não responda.";
            
            // Enviar email
            if ($mail->send()) {
                $emailsEnviados++;
                
                // Registrar envio no banco
                $stmtRegistro->bind_param("i", $usuarioId);
                $stmtRegistro->execute();
                
                registrarLog("Email enviado com sucesso para: $usuarioEmail (ID: $usuarioId)");
            } else {
                $emailsFalhados++;
                registrarLog("Falha ao enviar email para: $usuarioEmail - Erro: " . $mail->ErrorInfo);
            }
            
        } catch (Exception $e) {
            $emailsFalhados++;
            registrarLog("Exceção ao enviar email para $usuarioEmail: " . $e->getMessage());
        }
        
        // Pequeno delay para não sobrecarregar o servidor SMTP
        usleep(100000); // 0.1 segundo
    }
    
    $stmtRegistro->close();
    
    // Resumo final
    registrarLog("=== Resumo do envio ===");
    registrarLog("Total de veículos: " . count($veiculos));
    registrarLog("Total de usuários: $totalUsuarios");
    registrarLog("Emails enviados: $emailsEnviados");
    registrarLog("Emails falhados: $emailsFalhados");
    registrarLog("=== Fim da execução ===\n");
    
} catch (Exception $e) {
    registrarLog("ERRO CRÍTICO: " . $e->getMessage());
    registrarLog("=== Fim da execução com erro ===\n");
    exit(1);
}

// Fechar conexão
$mysqli->close();
exit(0);
