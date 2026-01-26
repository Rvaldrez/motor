<?php
/**
 * ============================================================================
 * SCRIPT DE NEWSLETTER DIÁRIA - NOVOS VEÍCULOS PARA INVESTIDORES
 * ============================================================================
 * 
 * Este script envia diariamente emails aos investidores sobre veículos
 * recém-cadastrados no sistema.
 * 
 * REQUISITOS:
 * - PHPMailer instalado via Composer
 * - Arquivo .env com credenciais SMTP
 * - Acesso ao banco de dados MySQL
 * - Tabela emails_automaticos criada
 * 
 * AGENDAMENTO:
 * Execute via CronJob diariamente às 9:00 AM:
 * 0 9 * * * /usr/bin/php /caminho/completo/para/enviar_newsletter_diario.php
 * 
 * ============================================================================
 */

// ============================================================================
// CARREGAR DEPENDÊNCIAS
// ============================================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Carregar autoload do Composer
require_once __DIR__ . '/vendor/autoload.php';

// Carregar conexão com banco de dados
require_once __DIR__ . '/conexao_bd.php';

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// ============================================================================
// CONFIGURAÇÕES
// ============================================================================

// Configurações do Email
define('EMAIL_SUBJECT', 'Novos Veículos Disponíveis - MotorGo');

// URL base do sistema (para links e imagens)
define('BASE_URL', 'https://motorgo.co');

// ============================================================================
// FUNÇÕES AUXILIARES
// ============================================================================

/**
 * Busca veículos cadastrados ontem com status completo e não em negociação
 */
function buscarVeiculosNovos($mysqli) {
    $sql = "SELECT 
                v.id,
                v.modelo,
                v.marca,
                v.ano_fabrica,
                v.quilometragem,
                v.preco,
                u.cidade AS usuario_cidade,
                u.estado AS usuario_estado,
                (SELECT caminho_foto 
                 FROM fotos_veiculos 
                 WHERE veiculo_id = v.id 
                 ORDER BY ordem_exibicao ASC, id ASC 
                 LIMIT 1) AS foto_principal
            FROM veiculos v
            LEFT JOIN usuarios u ON v.usuario_id = u.id
            WHERE v.status = 'completo'
              AND v.em_negociacao = 0
              AND DATE(v.data_cadastro) = DATE(DATE_SUB(NOW(), INTERVAL 1 DAY))
            ORDER BY v.data_cadastro DESC";
    
    $result = $mysqli->query($sql);
    $veiculos = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $veiculos[] = $row;
        }
    }
    
    return $veiculos;
}

/**
 * Busca investidores ativos para receber a newsletter
 */
function buscarInvestidores($mysqli) {
    $sql = "SELECT id, nome, email
            FROM usuarios
            WHERE tipo = 'investidor'
              AND status_confirmacao = 'confirmado'
              AND status_cadastro = 'completo'
            ORDER BY nome ASC";
    
    $result = $mysqli->query($sql);
    $investidores = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $investidores[] = $row;
        }
    }
    
    return $investidores;
}

/**
 * Gera o HTML do email com os veículos
 */
function gerarHTMLEmail($veiculos, $nomeInvestidor) {
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo EMAIL_SUBJECT; ?></title>
    <style>
        /* Reset básico */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        
        /* Container principal */
        .email-container {
            max-width: 700px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        
        /* Header */
        .email-header {
            background-color: #1a1a1a;
            padding: 30px 20px;
            text-align: center;
        }
        
        .email-header h1 {
            color: #B22222;
            font-size: 28px;
            margin: 0;
            font-weight: bold;
        }
        
        .email-header .logo-text {
            color: #ffffff;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        /* Introdução */
        .email-intro {
            padding: 30px 20px;
            background-color: #ffffff;
        }
        
        .email-intro h2 {
            color: #333;
            font-size: 22px;
            margin-bottom: 15px;
        }
        
        .email-intro p {
            color: #555;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        
        /* Container de veículos */
        .veiculos-container {
            padding: 20px;
            background-color: #f9f9f9;
        }
        
        /* Card de veículo */
        .veiculo-card {
            background-color: #ffffff;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .veiculo-card img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            display: block;
        }
        
        .veiculo-info {
            padding: 20px;
        }
        
        .veiculo-info h3 {
            color: #B22222;
            font-size: 20px;
            margin-bottom: 12px;
            font-weight: bold;
        }
        
        .veiculo-info p {
            color: #333;
            font-size: 15px;
            line-height: 1.8;
            margin-bottom: 8px;
        }
        
        .veiculo-info strong {
            color: #000;
        }
        
        .veiculo-preco {
            font-size: 18px;
            color: #B22222;
            font-weight: bold;
            margin-top: 15px;
        }
        
        /* Botão CTA */
        .btn-cta {
            display: inline-block;
            background: linear-gradient(135deg, #B22222 0%, #8B0000 100%);
            color: #ffffff !important;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
            margin-top: 15px;
            text-align: center;
        }
        
        .btn-cta:hover {
            background: linear-gradient(135deg, #8B0000 0%, #B22222 100%);
        }
        
        /* Footer */
        .email-footer {
            background-color: #1a1a1a;
            padding: 25px 20px;
            text-align: center;
        }
        
        .email-footer p {
            color: #999;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 5px;
        }
        
        .email-footer a {
            color: #B22222;
            text-decoration: none;
        }
        
        /* Responsividade */
        @media only screen and (max-width: 600px) {
            .email-header h1 {
                font-size: 22px;
            }
            
            .email-intro h2 {
                font-size: 18px;
            }
            
            .veiculo-card img {
                height: 200px;
            }
            
            .veiculo-info h3 {
                font-size: 18px;
            }
            
            .veiculo-info p {
                font-size: 14px;
            }
        }
        
        /* Mensagem quando não há veículos */
        .sem-veiculos {
            padding: 40px 20px;
            text-align: center;
            color: #666;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <div class="logo-text">MOTORGO</div>
            <h1>Novos Veículos Disponíveis</h1>
        </div>
        
        <!-- Introdução -->
        <div class="email-intro">
            <h2>Olá, <?php echo htmlspecialchars($nomeInvestidor); ?>!</h2>
            <p>Temos <?php echo count($veiculos); ?> <?php echo count($veiculos) == 1 ? 'novo veículo' : 'novos veículos'; ?> disponível<?php echo count($veiculos) == 1 ? '' : 'eis'; ?> para investimento cadastrado<?php echo count($veiculos) == 1 ? '' : 's'; ?> nas últimas 24 horas.</p>
            <p>Confira abaixo as oportunidades e garanta o melhor investimento!</p>
        </div>
        
        <!-- Veículos -->
        <?php if (count($veiculos) > 0): ?>
        <div class="veiculos-container">
            <?php foreach ($veiculos as $veiculo): 
                // Usar foto do veículo ou placeholder base64 se não houver foto
                if (!empty($veiculo['foto_principal'])) {
                    $foto = BASE_URL . '/' . $veiculo['foto_principal'];
                } else {
                    // Placeholder SVG inline em base64 - não depende de arquivo externo
                    $foto = 'data:image/svg+xml;base64,' . base64_encode('<?xml version="1.0" encoding="UTF-8"?><svg width="400" height="300" xmlns="http://www.w3.org/2000/svg"><rect width="400" height="300" fill="#e0e0e0"/><text x="50%" y="50%" font-family="Arial" font-size="24" fill="#666" text-anchor="middle" dominant-baseline="middle">Sem Imagem</text></svg>');
                }
                $preco = number_format($veiculo['preco'], 2, ',', '.');
                $km = number_format($veiculo['quilometragem'], 0, '', '.');
                $localizacao = trim($veiculo['usuario_cidade'] . '/' . $veiculo['usuario_estado']);
                if ($localizacao == '/') $localizacao = 'Não informado';
            ?>
            <div class="veiculo-card">
                <img src="<?php echo htmlspecialchars($foto); ?>" alt="<?php echo htmlspecialchars($veiculo['modelo']); ?>">
                <div class="veiculo-info">
                    <h3><?php echo htmlspecialchars($veiculo['marca'] . ' ' . $veiculo['modelo']); ?></h3>
                    <p><strong>Ano de Fabricação:</strong> <?php echo htmlspecialchars($veiculo['ano_fabrica']); ?></p>
                    <p><strong>Quilometragem:</strong> <?php echo htmlspecialchars($km); ?> km</p>
                    <p><strong>Localização:</strong> <?php echo htmlspecialchars($localizacao); ?></p>
                    <p class="veiculo-preco">Valor FIPE: R$ <?php echo htmlspecialchars($preco); ?></p>
                    <a href="<?php echo BASE_URL; ?>/painel_investidor.php" class="btn-cta">Ver Detalhes e Investir</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="sem-veiculos">
            <p>Nenhum veículo novo disponível hoje. Fique atento às próximas oportunidades!</p>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="email-footer">
            <p><strong>MotorGo</strong> - Sua plataforma de investimento em veículos</p>
            <p>Este é um email automático. Para dúvidas, entre em contato: <a href="mailto:<?php echo SMTP_FROM_EMAIL; ?>"><?php echo SMTP_FROM_EMAIL; ?></a></p>
            <p>&copy; <?php echo date('Y'); ?> MotorGo. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>
    <?php
    return ob_get_clean();
}

/**
 * Envia email usando PHPMailer (baseado em helpers/email_proposta.php)
 */
function enviarEmail($destinatario, $nomeDestinatario, $assunto, $corpoHTML) {
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
        $mail->addAddress($destinatario, $nomeDestinatario);
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $corpoHTML;

        return $mail->send();
    } catch (Exception $e) {
        // Log de erros
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        file_put_contents($logDir . '/email_erros.log', date('Y-m-d H:i:s') . " - Erro ao enviar e-mail para $destinatario: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

/**
 * Registra envio de email na tabela newsletter
 */
function registrarEnvioEmail($mysqli, $usuarioId, $email, $assunto, $status, $qtdVeiculos = 0, $erroMensagem = null) {
    // Verifica se a tabela existe, senão cria
    $mysqli->query("CREATE TABLE IF NOT EXISTS newsletter (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        email VARCHAR(255) NOT NULL,
        assunto VARCHAR(255) NOT NULL,
        status VARCHAR(50) NOT NULL,
        veiculos_enviados INT DEFAULT 0,
        data_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
        erro_mensagem TEXT NULL,
        INDEX idx_usuario (usuario_id),
        INDEX idx_email (email),
        INDEX idx_status (status),
        INDEX idx_data (data_envio),
        INDEX idx_data_status (data_envio, status),
        INDEX idx_usuario_data (usuario_id, data_envio)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $stmt = $mysqli->prepare(
        "INSERT INTO newsletter (usuario_id, email, assunto, status, veiculos_enviados, erro_mensagem, data_envio) 
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    
    if ($stmt) {
        $stmt->bind_param("isssis", $usuarioId, $email, $assunto, $status, $qtdVeiculos, $erroMensagem);
        $stmt->execute();
        $stmt->close();
    }
}

// ============================================================================
// EXECUÇÃO PRINCIPAL
// ============================================================================

echo "====================================================\n";
echo "NEWSLETTER DIÁRIA - NOVOS VEÍCULOS\n";
echo "Início: " . date('Y-m-d H:i:s') . "\n";
echo "====================================================\n\n";

// Usar conexão do banco já estabelecida em conexao_bd.php
echo "✓ Conectado ao banco de dados\n\n";

// Buscar veículos novos
echo "Buscando veículos cadastrados ontem...\n";
$veiculos = buscarVeiculosNovos($mysqli);
echo "✓ Encontrados: " . count($veiculos) . " veículo(s)\n\n";

if (count($veiculos) > 0) {
    echo "Veículos encontrados:\n";
    foreach ($veiculos as $v) {
        echo "  - " . $v['marca'] . " " . $v['modelo'] . " (" . $v['ano_fabrica'] . ")\n";
    }
    echo "\n";
}

// Buscar investidores
echo "Buscando investidores ativos...\n";
$investidores = buscarInvestidores($mysqli);
echo "✓ Encontrados: " . count($investidores) . " investidor(es)\n\n";

// Enviar emails
if (count($investidores) > 0 && count($veiculos) > 0) {
    echo "Iniciando envio de emails...\n";
    echo "----------------------------------------------------\n";
    
    $sucessos = 0;
    $falhas = 0;
    
    foreach ($investidores as $investidor) {
        echo "Enviando para: " . $investidor['email'] . " (" . $investidor['nome'] . ")... ";
        
        // Gerar HTML do email
        $htmlEmail = gerarHTMLEmail($veiculos, $investidor['nome']);
        
        // Enviar email
        $enviado = enviarEmail(
            $investidor['email'],
            $investidor['nome'],
            EMAIL_SUBJECT,
            $htmlEmail
        );
        
        if ($enviado) {
            echo "✓ Enviado\n";
            $sucessos++;
            $status = 'enviado';
        } else {
            echo "✗ Falha\n";
            $falhas++;
            $status = 'erro';
        }
        
        // Registrar no banco
        registrarEnvioEmail(
            $mysqli,
            $investidor['id'],
            $investidor['email'],
            EMAIL_SUBJECT,
            $status,
            count($veiculos),  // Quantidade de veículos enviados
            null  // Sem mensagem de erro (poderia capturar do PHPMailer)
        );
        
        // Pausa entre envios para evitar sobrecarga do servidor SMTP
        sleep(1);
    }
    
    echo "----------------------------------------------------\n";
    echo "\nResumo:\n";
    echo "  ✓ Enviados com sucesso: $sucessos\n";
    echo "  ✗ Falhas: $falhas\n";
} elseif (count($veiculos) == 0) {
    echo "⚠ Nenhum veículo novo para enviar. Newsletter não enviada.\n";
} elseif (count($investidores) == 0) {
    echo "⚠ Nenhum investidor ativo encontrado. Newsletter não enviada.\n";
}

// Fechar conexão
$mysqli->close();

echo "\n====================================================\n";
echo "CONCLUSÃO: " . date('Y-m-d H:i:s') . "\n";
echo "====================================================\n";

?>
