<?php
/**
 * ============================================================================
 * SCRIPT DE NEWSLETTER DIÁRIA - NOVOS VEÍCULOS PARA INVESTIDORES
 * ============================================================================
 * 
 * Este script envia diariamente emails aos investidores sobre veículos
 * recém-cadastrados no sistema.
 * 
 * ⚠️  IMPORTANTE: ESTE SCRIPT DEVE SER EXECUTADO VIA LINHA DE COMANDO (CLI)
 * ⚠️  NÃO EXECUTE PELO NAVEGADOR/BROWSER!
 * 
 * REQUISITOS:
 * - PHPMailer instalado via Composer
 * - Arquivo .env com credenciais SMTP
 * - Acesso ao banco de dados MySQL
 * - Tabela newsletter criada
 * 
 * USO CORRETO:
 * ssh usuario@servidor
 * cd /caminho/para/motor
 * php enviar_newsletter_diario.php
 * 
 * AGENDAMENTO (CronJob):
 * 0 9 * * * /usr/bin/php /caminho/completo/para/enviar_newsletter_diario.php
 * 
 * ============================================================================
 */

// ============================================================================
// VERIFICAÇÃO: BLOQUEAR EXECUÇÃO VIA BROWSER
// ============================================================================

// Verifica se está sendo executado via linha de comando (CLI)
if (php_sapi_name() !== 'cli') {
    // Não está em CLI = está no browser
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Erro - Execução não permitida</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 50px; background: #f5f5f5; }
            .error-box { background: #fff; border-left: 5px solid #d32f2f; padding: 20px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
            h1 { color: #d32f2f; margin-top: 0; }
            code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
            .correct { background: #e8f5e9; border-left-color: #4caf50; padding: 15px; margin: 15px 0; }
            .correct h3 { color: #4caf50; margin-top: 0; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>⛔ ERRO: Execução não permitida via navegador!</h1>
            <p><strong>Este script deve ser executado via linha de comando (CLI), NÃO pelo navegador.</strong></p>
            
            <h3>Por que não funciona no navegador?</h3>
            <ul>
                <li>⏱️ Timeout do PHP/Browser (30-60 segundos)</li>
                <li>📧 Enviar 42 emails leva ~60-90 segundos</li>
                <li>🔌 Conexão do navegador expira</li>
                <li>💾 Limitações de memória e recursos</li>
            </ul>
            
            <div class="correct">
                <h3>✅ Forma CORRETA de executar:</h3>
                <p>Conecte via SSH ao servidor e execute:</p>
                <pre><code>ssh usuario@servidor
cd /caminho/para/motor
php enviar_newsletter_diario.php</code></pre>
            </div>
            
            <h3>📖 Documentação:</h3>
            <p>Leia o arquivo <code>IMPORTANTE_NAO_USAR_BROWSER.md</code> para mais detalhes.</p>
        </div>
    </body>
    </html>
    ');
}

// ============================================================================
// CONFIGURAÇÕES DE TEMPO PARA CLI
// ============================================================================

// Remove limite de tempo de execução (só funciona em CLI)
set_time_limit(0);
ini_set('max_execution_time', '0');
ini_set('memory_limit', '256M');

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
 * Busca os 4 veículos mais recentes DOS DIAS ANTERIORES
 * Exclui veículos cadastrados nas últimas 24 horas (por data, não por ID)
 */
function buscarVeiculosRecentes($mysqli) {
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
              AND DATE(v.data_cadastro) < CURDATE() - INTERVAL 1 DAY
            ORDER BY v.data_cadastro DESC
            LIMIT 4";
    
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
 * 
 * @param array $veiculosNovos - Veículos cadastrados nas últimas 24h
 * @param array $veiculosRecentes - 4 veículos mais recentes (independente da data)
 * @param string $nomeInvestidor - Nome do investidor
 */
function gerarHTMLEmail($veiculosNovos, $veiculosRecentes, $nomeInvestidor) {
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
        
        .email-header img.logo {
            max-width: 200px;
            height: auto;
            margin-bottom: 15px;
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
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: space-between;
        }
        
        /* Título de seção */
        .section-title {
            background-color: #1a1a1a;
            color: #ffffff;
            padding: 15px 20px;
            margin: 0;
            font-size: 20px;
            font-weight: bold;
            border-left: 5px solid #B22222;
        }
        
        .section-title.secondary {
            background-color: #333333;
            margin-top: 20px;
        }
        
        /* Card de veículo */
        .veiculo-card {
            background-color: #ffffff;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            flex: 0 0 calc(50% - 10px);
            box-sizing: border-box;
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
            .veiculos-container {
                flex-direction: column;
            }
            
            .veiculo-card {
                flex: 0 0 100%;
            }
            
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
            <img src="<?php echo BASE_URL; ?>/imagens/logo_motorgo_blk.png" alt="MotorGo Logo" class="logo">
            <h1>Novos Veículos Disponíveis</h1>
        </div>
        
        <!-- Introdução -->
        <div class="email-intro">
            <h2>Olá, <?php echo htmlspecialchars($nomeInvestidor); ?>!</h2>
            <p>Confira os veículos cadastrados nas últimas 24 horas. Faça a sua oferta e garanta a oportunidade de lucrar na revenda!</p>
        </div>
        
        <!-- Veículos das Últimas 24h -->
        <?php if (count($veiculosNovos) > 0): ?>
        <h2 class="section-title">🚗 Novos Veículos (Últimas 24 horas)</h2>
        <div class="veiculos-container">
            <?php foreach ($veiculosNovos as $veiculo): 
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
                    <a href="<?php echo BASE_URL; ?>/painel_investidor.php" class="btn-cta">Ver detalhes</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Cadastros Recentes -->
        <?php if (count($veiculosRecentes) > 0): ?>
        <h2 class="section-title secondary">📋 Cadastros Recentes</h2>
        <div class="veiculos-container">
            <?php foreach ($veiculosRecentes as $veiculo): 
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
                    <a href="<?php echo BASE_URL; ?>/painel_investidor.php" class="btn-cta">Ver detalhes</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Mensagem quando não há veículos -->
        <?php if (count($veiculosNovos) == 0 && count($veiculosRecentes) == 0): ?>
        <div class="sem-veiculos">
            <p>Nenhum veículo disponível no momento. Fique atento às próximas oportunidades!</p>
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
        
        // Configurações de timeout AUMENTADAS para evitar travamentos
        $mail->Timeout    = 60;  // Timeout de 60 segundos (aumentado de 30)
        $mail->SMTPKeepAlive = false;  // Não manter conexão aberta entre emails
        
        // Debug desabilitado (remover para troubleshooting)
        $mail->SMTPDebug = 0;
        
        // Opções adicionais de timeout
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom($_ENV['EMAIL_USUARIO'], 'MotorGo');
        $mail->addAddress($destinatario, $nomeDestinatario);
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $corpoHTML;

        return $mail->send();
    } catch (Exception $e) {
        // Log de erros detalhado
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $errorMsg = date('Y-m-d H:i:s') . " - Erro ao enviar e-mail para $destinatario: " . $e->getMessage() . "\n";
        file_put_contents($logDir . '/email_erros.log', $errorMsg, FILE_APPEND);
        
        // Também exibir erro no CLI para diagnóstico imediato
        error_log($errorMsg);
        
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

// Buscar veículos novos (últimas 24h)
echo "Buscando veículos cadastrados ontem (últimas 24h)...\n";
$veiculosNovos = buscarVeiculosNovos($mysqli);
echo "✓ Encontrados: " . count($veiculosNovos) . " veículo(s)\n\n";

if (count($veiculosNovos) > 0) {
    echo "Veículos novos (24h):\n";
    foreach ($veiculosNovos as $v) {
        echo "  - " . $v['marca'] . " " . $v['modelo'] . " (" . $v['ano_fabrica'] . ")\n";
    }
    echo "\n";
}

// Buscar veículos recentes (4 mais recentes DOS DIAS ANTERIORES)
echo "Buscando os 4 cadastros mais recentes (dias anteriores)...\n";
$veiculosRecentes = buscarVeiculosRecentes($mysqli);
echo "✓ Encontrados: " . count($veiculosRecentes) . " veículo(s)\n\n";

if (count($veiculosRecentes) > 0) {
    echo "Cadastros recentes:\n";
    foreach ($veiculosRecentes as $v) {
        echo "  - " . $v['marca'] . " " . $v['modelo'] . " (" . $v['ano_fabrica'] . ")\n";
    }
    echo "\n";
}

$totalVeiculos = count($veiculosNovos) + count($veiculosRecentes);

// Buscar investidores
echo "Buscando investidores ativos...\n";
$investidores = buscarInvestidores($mysqli);
echo "✓ Encontrados: " . count($investidores) . " investidor(es)\n\n";

// Enviar emails (enviar SOMENTE se houver veículos novos das últimas 24h)
if (count($investidores) > 0 && count($veiculosNovos) > 0) {
    echo "Iniciando envio de emails...\n";
    echo "----------------------------------------------------\n";
    
    $sucessos = 0;
    $falhas = 0;
    $total = count($investidores);
    $contador = 0;
    
    foreach ($investidores as $investidor) {
        $contador++;
        echo "Enviando $contador/$total: " . $investidor['email'] . " (" . $investidor['nome'] . ")... ";
        flush(); // Força a saída imediata na tela
        
        // Gerar HTML do email com AMBAS as seções
        $htmlEmail = gerarHTMLEmail($veiculosNovos, $veiculosRecentes, $investidor['nome']);
        
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
        flush(); // Força a saída imediata na tela
        
        // Registrar no banco
        registrarEnvioEmail(
            $mysqli,
            $investidor['id'],
            $investidor['email'],
            EMAIL_SUBJECT,
            $status,
            $totalVeiculos,  // Total de veículos enviados (novos + recentes)
            null  // Sem mensagem de erro (poderia capturar do PHPMailer)
        );
        
        // Pausa entre envios para evitar sobrecarga do servidor SMTP (reduzido para 0.5s)
        usleep(500000); // 0.5 segundos
    }
    
    echo "----------------------------------------------------\n";
    echo "\n📊 RESUMO DO ENVIO:\n";
    echo "  ✓ Enviados com sucesso: $sucessos\n";
    echo "  ✗ Falhas: $falhas\n";
    echo "  📧 Total de investidores: $total\n";
    echo "  🚗 Veículos novos (24h): " . count($veiculosNovos) . "\n";
    echo "  📋 Cadastros recentes: " . count($veiculosRecentes) . "\n";
    echo "  📦 Total de veículos: $totalVeiculos\n";
    echo "  ⏱️  Tempo estimado: ~" . round($total * 2.5) . " segundos\n";
} elseif (count($veiculosNovos) == 0) {
    echo "⚠ Nenhum veículo novo cadastrado nas últimas 24h. Newsletter não enviada.\n";
    if (count($veiculosRecentes) > 0) {
        echo "   Há " . count($veiculosRecentes) . " veículo(s) recente(s), mas newsletter só é enviada com veículos das últimas 24h.\n";
    }
} elseif (count($investidores) == 0) {
    echo "⚠ Nenhum investidor ativo encontrado. Newsletter não enviada.\n";
}

// Fechar conexão
$mysqli->close();

echo "\n====================================================\n";
echo "CONCLUSÃO: " . date('Y-m-d H:i:s') . "\n";
echo "====================================================\n";

?>
