<?php
/**
 * MOTORGO - NEWSLETTER WEB INTERFACE
 * 
 * Interface web para envio manual da newsletter com acompanhamento em tempo real
 * Acesso: http://motorgo.co/enviar_newsletter_web.php
 * 
 * @author MotorGo Development Team
 * @version 2.0
 */

// Configurações para output em tempo real
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
@ini_set('implicit_flush', true);
ob_implicit_flush(true);

// Remove limite de tempo de execução
set_time_limit(0);
ini_set('max_execution_time', 0);

// Headers para streaming
header('Content-Type: text/html; charset=UTF-8');
header('X-Accel-Buffering: no');

// Carrega dependências
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/conexao_bd.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Carrega variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Constantes
define('EMAIL_SUBJECT', 'MotorGo - Novos Veículos Disponíveis');
define('BASE_URL', 'https://motorgo.co');

/**
 * Funções do sistema (copiadas do script principal)
 */

function buscarVeiculosNovos($mysqli) {
    $ontem = date('Y-m-d', strtotime('-1 day'));
    
    $query = "
        SELECT 
            v.id,
            v.marca,
            v.modelo,
            v.ano_fabrica,
            v.quilometragem,
            u.cidade AS usuario_cidade,
            u.estado AS usuario_estado,
            v.data_cadastro,
            (SELECT caminho_foto 
             FROM fotos_veiculos 
             WHERE veiculo_id = v.id 
             ORDER BY ordem_exibicao ASC, id ASC 
             LIMIT 1) AS foto_principal
        FROM veiculos v
        LEFT JOIN usuarios u ON v.usuario_id = u.id
        WHERE DATE(v.data_cadastro) = '$ontem'
          AND v.status = 'completo'
          AND v.em_negociacao = 0
        ORDER BY v.data_cadastro DESC
    ";
    
    $result = $mysqli->query($query);
    $veiculos = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $veiculos[] = $row;
        }
    }
    
    return $veiculos;
}

function buscarVeiculosRecentes($mysqli) {
    $query = "
        SELECT 
            v.id,
            v.marca,
            v.modelo,
            v.ano_fabrica,
            v.quilometragem,
            u.cidade AS usuario_cidade,
            u.estado AS usuario_estado,
            v.data_cadastro,
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
        LIMIT 4
    ";
    
    $result = $mysqli->query($query);
    $veiculos = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $veiculos[] = $row;
        }
    }
    
    return $veiculos;
}

function buscarInvestidores($mysqli) {
    $query = "
        SELECT 
            id,
            nome,
            email
        FROM usuarios
        WHERE tipo = 'investidor'
          AND status_confirmacao = 'confirmado'
          AND status_cadastro = 'completo'
        ORDER BY nome ASC
    ";
    
    $result = $mysqli->query($query);
    $investidores = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $investidores[] = $row;
        }
    }
    
    return $investidores;
}

function gerarHTMLEmail($veiculosNovos, $veiculosRecentes, $nomeInvestidor) {
    $baseUrl = BASE_URL;
    $logoUrl = $baseUrl . '/imagens/logo_motorgo_blk.png';
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>MotorGo - Novos Veículos</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5;">
            <tr>
                <td align="center" style="padding: 20px 0;">
                    <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; max-width: 600px;">
                        <!-- Header -->
                        <tr>
                            <td style="background-color: #1a1a1a; padding: 30px; text-align: center;">
                                <img src="<?php echo $logoUrl; ?>" alt="MotorGo" style="max-width: 200px; height: auto;">
                            </td>
                        </tr>
                        
                        <!-- Saudação -->
                        <tr>
                            <td style="padding: 30px 20px 20px 20px;">
                                <h2 style="color: #333; margin: 0 0 15px 0;">Olá, <?php echo htmlspecialchars($nomeInvestidor); ?>!</h2>
                                <p style="color: #666; line-height: 1.6; margin: 0;">
                                    Confira os veículos cadastrados nas últimas 24 horas. Faça a sua oferta e garanta a oportunidade de lucrar na revenda!
                                </p>
                            </td>
                        </tr>

                        <?php if (count($veiculosNovos) > 0): ?>
                        <!-- Seção 1: Novos Veículos (24h) -->
                        <tr>
                            <td style="padding: 0 20px;">
                                <div style="background-color: #1a1a1a; color: #ffffff; padding: 15px 20px; font-size: 18px; border-left: 5px solid #B22222; margin-bottom: 20px;">
                                    🚗 Novos Veículos (Últimas 24 horas)
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0 20px 20px 20px;">
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <?php 
                                        $contador = 0;
                                        foreach ($veiculosNovos as $veiculo): 
                                            $contador++;
                                            $fotoUrl = !empty($veiculo['foto_principal']) ? $baseUrl . '/' . $veiculo['foto_principal'] : $baseUrl . '/imagens/sem-foto.jpg';
                                            $veiculoUrl = $baseUrl . '/secao_oferta_veiculos.php';
                                        ?>
                                        <td style="width: 48%; padding: 10px; vertical-align: top;" align="center">
                                            <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
                                                <tr>
                                                    <td>
                                                        <img src="<?php echo $fotoUrl; ?>" alt="<?php echo htmlspecialchars($veiculo['marca'] . ' ' . $veiculo['modelo']); ?>" style="width: 100%; height: 200px; display: block; object-fit: cover;">
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="padding: 15px; min-height: 180px;">
                                                        <h3 style="color: #333; margin: 0 0 10px 0; font-size: 16px; min-height: 40px; line-height: 1.2;">
                                                            <?php echo htmlspecialchars($veiculo['marca'] . ' ' . $veiculo['modelo']); ?>
                                                        </h3>
                                                        <p style="color: #666; margin: 5px 0; font-size: 14px;">
                                                            <strong>Ano:</strong> <?php echo $veiculo['ano_fabrica']; ?><br>
                                                            <strong>KM:</strong> <?php echo number_format($veiculo['quilometragem'], 0, ',', '.'); ?><br>
                                                            <strong>Local:</strong> <?php echo htmlspecialchars($veiculo['usuario_cidade'] . '/' . $veiculo['usuario_estado']); ?>
                                                        </p>
                                                        <a href="<?php echo $veiculoUrl; ?>" style="display: inline-block; margin-top: 10px; padding: 10px 20px; background-color: #B22222; color: #ffffff; text-decoration: none; border-radius: 5px; font-size: 14px;">
                                                            Ver detalhes
                                                        </a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                        <?php if ($contador % 2 == 0): ?>
                                        </tr><tr>
                                        <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <?php endif; ?>

                        <?php if (count($veiculosRecentes) > 0): ?>
                        <!-- Seção 2: Cadastros Recentes -->
                        <tr>
                            <td style="padding: 20px 20px 0 20px;">
                                <div style="background-color: #333333; color: #ffffff; padding: 15px 20px; font-size: 18px; border-left: 5px solid #B22222; margin-bottom: 20px;">
                                    📋 Cadastros Recentes
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0 20px 20px 20px;">
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <?php 
                                        $contador = 0;
                                        foreach ($veiculosRecentes as $veiculo): 
                                            $contador++;
                                            $fotoUrl = !empty($veiculo['foto_principal']) ? $baseUrl . '/' . $veiculo['foto_principal'] : $baseUrl . '/imagens/sem-foto.jpg';
                                            $veiculoUrl = $baseUrl . '/secao_oferta_veiculos.php';
                                        ?>
                                        <td style="width: 48%; padding: 10px; vertical-align: top;" align="center">
                                            <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
                                                <tr>
                                                    <td>
                                                        <img src="<?php echo $fotoUrl; ?>" alt="<?php echo htmlspecialchars($veiculo['marca'] . ' ' . $veiculo['modelo']); ?>" style="width: 100%; height: 200px; display: block; object-fit: cover;">
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="padding: 15px; min-height: 180px;">
                                                        <h3 style="color: #333; margin: 0 0 10px 0; font-size: 16px; min-height: 40px; line-height: 1.2;">
                                                            <?php echo htmlspecialchars($veiculo['marca'] . ' ' . $veiculo['modelo']); ?>
                                                        </h3>
                                                        <p style="color: #666; margin: 5px 0; font-size: 14px;">
                                                            <strong>Ano:</strong> <?php echo $veiculo['ano_fabrica']; ?><br>
                                                            <strong>KM:</strong> <?php echo number_format($veiculo['quilometragem'], 0, ',', '.'); ?><br>
                                                            <strong>Local:</strong> <?php echo htmlspecialchars($veiculo['usuario_cidade'] . '/' . $veiculo['usuario_estado']); ?>
                                                        </p>
                                                        <a href="<?php echo $veiculoUrl; ?>" style="display: inline-block; margin-top: 10px; padding: 10px 20px; background-color: #B22222; color: #ffffff; text-decoration: none; border-radius: 5px; font-size: 14px;">
                                                            Ver detalhes
                                                        </a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                        <?php if ($contador % 2 == 0): ?>
                                        </tr><tr>
                                        <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <?php endif; ?>

                        <!-- Footer -->
                        <tr>
                            <td style="background-color: #1a1a1a; color: #ffffff; padding: 20px; text-align: center; font-size: 12px;">
                                <p style="margin: 0 0 10px 0;">© <?php echo date('Y'); ?> MotorGo. Todos os direitos reservados.</p>
                                <p style="margin: 0;">
                                    <a href="<?php echo $baseUrl; ?>" style="color: #B22222; text-decoration: none;">www.motorgo.co</a>
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function enviarEmail($paraEmail, $paraNome, $assunto, $htmlCorpo) {
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
        $mail->Timeout    = 15;
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom($_ENV['EMAIL_USUARIO'], 'MotorGo');
        $mail->addAddress($paraEmail, $paraNome);
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $htmlCorpo;

        return $mail->send();
    } catch (Exception $e) {
        return false;
    }
}

function registrarEnvioEmail($mysqli, $usuarioId, $email, $assunto, $status, $veiculosEnviados, $erroMensagem = null) {
    $stmt = $mysqli->prepare("
        INSERT INTO newsletter (usuario_id, email, assunto, status, veiculos_enviados, erro_mensagem)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("isssis", $usuarioId, $email, $assunto, $status, $veiculosEnviados, $erroMensagem);
    $stmt->execute();
    $stmt->close();
}

// Verifica se é uma ação POST (enviar newsletter)
$acao = isset($_GET['acao']) ? $_GET['acao'] : '';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MotorGo - Envio de Newsletter</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #1a1a1a 0%, #333 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .content {
            padding: 30px;
        }
        
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #B22222;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 4px;
        }
        
        .info-box h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
        }
        
        .info-value {
            color: #333;
            font-weight: bold;
        }
        
        .btn {
            display: inline-block;
            padding: 15px 40px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #B22222 0%, #8B0000 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(178, 34, 34, 0.4);
        }
        
        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .progress-container {
            display: none;
            margin-top: 25px;
        }
        
        .progress-bar-wrapper {
            background: #e9ecef;
            height: 30px;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 15px;
        }
        
        .progress-bar {
            background: linear-gradient(90deg, #B22222 0%, #FF4444 100%);
            height: 100%;
            width: 0%;
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 12px;
        }
        
        .log-container {
            background: #1e1e1e;
            color: #00ff00;
            padding: 20px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            max-height: 500px;
            overflow-y: auto;
            line-height: 1.6;
        }
        
        .log-line {
            margin-bottom: 5px;
            white-space: pre-wrap;
        }
        
        .log-success {
            color: #00ff00;
        }
        
        .log-error {
            color: #ff4444;
        }
        
        .log-info {
            color: #ffaa00;
        }
        
        .summary {
            display: none;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 20px;
            border-radius: 6px;
            margin-top: 20px;
        }
        
        .summary h3 {
            margin-bottom: 15px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #c3e6cb;
        }
        
        .summary-row:last-child {
            border-bottom: none;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
        }
        
        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
            margin-right: 10px;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            margin-right: 15px;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(108, 117, 125, 0.4);
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            overflow: auto;
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 2% auto;
            padding: 0;
            width: 95%;
            max-width: 1200px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #1a1a1a 0%, #333 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 24px;
        }
        
        .close {
            color: white;
            font-size: 35px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
            transition: all 0.3s;
        }
        
        .close:hover {
            color: #B22222;
            transform: scale(1.1);
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .preview-frame {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            background: white;
            min-height: 500px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚗 MotorGo Newsletter</h1>
            <p>Sistema de Envio Manual de Newsletter para Investidores</p>
        </div>
        
        <div class="content">
            <?php
            if ($acao === 'enviar'):
                // Modo de envio - streaming de progresso
                ?>
                <div class="progress-container" style="display: block;">
                    <div class="progress-bar-wrapper">
                        <div class="progress-bar" id="progressBar">0%</div>
                    </div>
                    <div class="log-container" id="logContainer"></div>
                    <div class="summary" id="summary"></div>
                </div>
                
                <script>
                    const logContainer = document.getElementById('logContainer');
                    const progressBar = document.getElementById('progressBar');
                    const summary = document.getElementById('summary');
                    
                    function addLog(message, type = 'info') {
                        const line = document.createElement('div');
                        line.className = 'log-line log-' + type;
                        line.textContent = message;
                        logContainer.appendChild(line);
                        logContainer.scrollTop = logContainer.scrollHeight;
                    }
                    
                    function updateProgress(current, total) {
                        const percent = Math.round((current / total) * 100);
                        progressBar.style.width = percent + '%';
                        progressBar.textContent = percent + '%';
                    }
                    
                    function showSummary(data) {
                        summary.innerHTML = `
                            <h3>📊 Resumo do Envio</h3>
                            <div class="summary-row">
                                <span>✅ Enviados com sucesso:</span>
                                <strong>${data.sucessos}</strong>
                            </div>
                            <div class="summary-row">
                                <span>❌ Falhas:</span>
                                <strong>${data.falhas}</strong>
                            </div>
                            <div class="summary-row">
                                <span>📧 Total de investidores:</span>
                                <strong>${data.total}</strong>
                            </div>
                            <div class="summary-row">
                                <span>🚗 Veículos novos (24h):</span>
                                <strong>${data.veiculosNovos}</strong>
                            </div>
                            <div class="summary-row">
                                <span>📋 Cadastros recentes:</span>
                                <strong>${data.veiculosRecentes}</strong>
                            </div>
                            <div class="summary-row">
                                <span>⏱️ Tempo de execução:</span>
                                <strong>${data.tempo} segundos</strong>
                            </div>
                        `;
                        summary.style.display = 'block';
                    }
                </script>
                
                <?php
                flush();
                
                $tempoInicio = microtime(true);
                
                echo "<script>addLog('════════════════════════════════════════════════════', 'info');</script>";
                echo "<script>addLog('MOTORGO - ENVIO DE NEWSLETTER', 'info');</script>";
                echo "<script>addLog('Início: " . date('Y-m-d H:i:s') . "', 'info');</script>";
                echo "<script>addLog('════════════════════════════════════════════════════', 'info');</script>";
                flush();
                
                // Busca veículos
                echo "<script>addLog('\\n🔍 Buscando veículos cadastrados ontem...', 'info');</script>";
                flush();
                
                $veiculosNovos = buscarVeiculosNovos($mysqli);
                echo "<script>addLog('✓ Encontrados: " . count($veiculosNovos) . " veículo(s) das últimas 24h', 'success');</script>";
                flush();
                
                if (count($veiculosNovos) > 0) {
                    foreach ($veiculosNovos as $v) {
                        echo "<script>addLog('  - " . addslashes($v['marca'] . ' ' . $v['modelo'] . ' (' . $v['ano_fabrica'] . ')') . "', 'info');</script>";
                        flush();
                    }
                }
                
                echo "<script>addLog('\\n🔍 Buscando cadastros recentes...', 'info');</script>";
                flush();
                
                $veiculosRecentes = buscarVeiculosRecentes($mysqli);
                echo "<script>addLog('✓ Encontrados: " . count($veiculosRecentes) . " veículo(s) recentes', 'success');</script>";
                flush();
                
                $totalVeiculos = count($veiculosNovos) + count($veiculosRecentes);
                
                // Verifica se há veículos novos (requisito para enviar)
                if (count($veiculosNovos) == 0) {
                    echo "<script>addLog('\\n⚠️ ATENÇÃO: Nenhum veículo novo nas últimas 24h', 'error');</script>";
                    echo "<script>addLog('Newsletter NÃO será enviada.', 'error');</script>";
                    echo "<script>addLog('Requisito: Deve haver pelo menos 1 veículo cadastrado nas últimas 24h', 'info');</script>";
                    flush();
                    exit;
                }
                
                // Busca investidores
                echo "<script>addLog('\\n🔍 Buscando investidores ativos...', 'info');</script>";
                flush();
                
                $investidores = buscarInvestidores($mysqli);
                echo "<script>addLog('✓ Encontrados: " . count($investidores) . " investidor(es)', 'success');</script>";
                flush();
                
                if (count($investidores) == 0) {
                    echo "<script>addLog('\\n⚠️ Nenhum investidor ativo encontrado. Newsletter não enviada.', 'error');</script>";
                    flush();
                    exit;
                }
                
                // Inicia envio
                echo "<script>addLog('\\n📧 Iniciando envio de emails...', 'info');</script>";
                echo "<script>addLog('────────────────────────────────────────────────────', 'info');</script>";
                flush();
                
                $contador = 0;
                $sucessos = 0;
                $falhas = 0;
                $total = count($investidores);
                
                foreach ($investidores as $investidor) {
                    $contador++;
                    
                    echo "<script>addLog('Enviando " . $contador . "/" . $total . ": " . addslashes($investidor['email']) . " (" . addslashes($investidor['nome']) . ")...', 'info');</script>";
                    echo "<script>updateProgress(" . $contador . ", " . $total . ");</script>";
                    flush();
                    
                    // Gera HTML do email
                    $htmlEmail = gerarHTMLEmail($veiculosNovos, $veiculosRecentes, $investidor['nome']);
                    
                    // Envia email
                    $enviado = enviarEmail(
                        $investidor['email'],
                        $investidor['nome'],
                        EMAIL_SUBJECT,
                        $htmlEmail
                    );
                    
                    if ($enviado) {
                        $sucessos++;
                        echo "<script>addLog('  ✓ Enviado com sucesso!', 'success');</script>";
                        $status = 'enviado';
                        $erroMsg = null;
                    } else {
                        $falhas++;
                        echo "<script>addLog('  ✗ Falha no envio', 'error');</script>";
                        $status = 'falha';
                        $erroMsg = 'Erro SMTP';
                    }
                    
                    // Registra no banco
                    registrarEnvioEmail(
                        $mysqli,
                        $investidor['id'],
                        $investidor['email'],
                        EMAIL_SUBJECT,
                        $status,
                        $totalVeiculos,
                        $erroMsg
                    );
                    
                    flush();
                    usleep(300000); // 0.3 segundo entre emails
                }
                
                $tempoFim = microtime(true);
                $tempoExecucao = round($tempoFim - $tempoInicio);
                
                echo "<script>addLog('────────────────────────────────────────────────────', 'info');</script>";
                echo "<script>addLog('\\n✅ ENVIO CONCLUÍDO!', 'success');</script>";
                echo "<script>addLog('Fim: " . date('Y-m-d H:i:s') . "', 'info');</script>";
                flush();
                
                $summaryData = [
                    'sucessos' => $sucessos,
                    'falhas' => $falhas,
                    'total' => $total,
                    'veiculosNovos' => count($veiculosNovos),
                    'veiculosRecentes' => count($veiculosRecentes),
                    'tempo' => $tempoExecucao
                ];
                
                echo "<script>showSummary(" . json_encode($summaryData) . ");</script>";
                flush();
                
            else:
                // Modo de visualização - mostra informações e botão
                
                // Busca informações
                $veiculosNovos = buscarVeiculosNovos($mysqli);
                $veiculosRecentes = buscarVeiculosRecentes($mysqli);
                $investidores = buscarInvestidores($mysqli);
                $totalVeiculos = count($veiculosNovos) + count($veiculosRecentes);
                
                ?>
                
                <?php if (count($veiculosNovos) == 0): ?>
                <div class="alert alert-warning">
                    <strong>⚠️ Atenção:</strong> Não há veículos novos cadastrados nas últimas 24 horas.
                    <br><br>
                    A newsletter <strong>NÃO será enviada</strong> porque o requisito é ter pelo menos 1 veículo cadastrado nas últimas 24h.
                </div>
                <?php endif; ?>
                
                <div class="info-box">
                    <h3>📊 Resumo da Newsletter</h3>
                    <div class="info-row">
                        <span class="info-label">🚗 Veículos novos (Últimas 24h):</span>
                        <span class="info-value"><?php echo count($veiculosNovos); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">📋 Cadastros recentes:</span>
                        <span class="info-value"><?php echo count($veiculosRecentes); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">📦 Total de veículos:</span>
                        <span class="info-value"><?php echo $totalVeiculos; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">📧 Investidores ativos:</span>
                        <span class="info-value"><?php echo count($investidores); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">⏱️ Tempo estimado:</span>
                        <span class="info-value">~<?php echo round(count($investidores) * 2.5); ?> segundos</span>
                    </div>
                </div>
                
                <?php if (count($veiculosNovos) > 0): ?>
                <div class="info-box">
                    <h3>🚗 Veículos das Últimas 24h</h3>
                    <?php foreach ($veiculosNovos as $veiculo): ?>
                    <div class="info-row">
                        <span class="info-label">
                            <?php echo htmlspecialchars($veiculo['marca'] . ' ' . $veiculo['modelo']); ?>
                        </span>
                        <span class="info-value">
                            <?php echo $veiculo['ano_fabrica']; ?> | <?php echo htmlspecialchars($veiculo['usuario_cidade']); ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div style="text-align: center; margin-top: 30px;">
                    <?php if (count($veiculosNovos) > 0 && count($investidores) > 0): ?>
                    <button class="btn btn-secondary" onclick="mostrarPreview()">
                        👁️ Visualizar Preview do Email
                    </button>
                    <button class="btn btn-primary" onclick="iniciarEnvio()" id="btnEnviar">
                        🚀 Enviar Newsletter Agora
                    </button>
                    <p style="margin-top: 15px; color: #666; font-size: 14px;">
                        Ao clicar, a newsletter será enviada para <?php echo count($investidores); ?> investidor(es)
                    </p>
                    <?php else: ?>
                    <button class="btn btn-secondary" onclick="mostrarPreview()" <?php echo count($veiculosNovos) == 0 ? 'disabled' : ''; ?>>
                        👁️ Visualizar Preview do Email
                    </button>
                    <button class="btn btn-primary" disabled>
                        🚀 Enviar Newsletter Agora
                    </button>
                    <p style="margin-top: 15px; color: #666; font-size: 14px;">
                        <?php if (count($veiculosNovos) == 0): ?>
                        Não é possível enviar sem veículos novos nas últimas 24h
                        <?php elseif (count($investidores) == 0): ?>
                        Não há investidores ativos no sistema
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                </div>
                
                <!-- Modal de Preview -->
                <div id="previewModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2>👁️ Preview da Newsletter</h2>
                            <span class="close" onclick="fecharPreview()">&times;</span>
                        </div>
                        <div class="modal-body">
                            <p style="color: #666; margin-bottom: 20px;">
                                Esta é uma prévia de como o email aparecerá para os investidores.
                                O nome "Investidor Exemplo" será substituído pelo nome real de cada destinatário.
                            </p>
                            <div class="preview-frame" id="previewContent">
                                <?php if (count($veiculosNovos) > 0): ?>
                                <?php echo gerarHTMLEmail($veiculosNovos, $veiculosRecentes, 'Investidor Exemplo'); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <script>
                    function iniciarEnvio() {
                        if (confirm('Confirma o envio da newsletter para <?php echo count($investidores); ?> investidor(es)?')) {
                            document.getElementById('btnEnviar').disabled = true;
                            document.getElementById('btnEnviar').innerHTML = '<span class="spinner"></span> Enviando...';
                            window.location.href = '?acao=enviar';
                        }
                    }
                    
                    function mostrarPreview() {
                        document.getElementById('previewModal').style.display = 'block';
                        document.body.style.overflow = 'hidden';
                    }
                    
                    function fecharPreview() {
                        document.getElementById('previewModal').style.display = 'none';
                        document.body.style.overflow = 'auto';
                    }
                    
                    // Fechar modal ao clicar fora
                    window.onclick = function(event) {
                        const modal = document.getElementById('previewModal');
                        if (event.target == modal) {
                            fecharPreview();
                        }
                    }
                    
                    // Fechar modal com ESC
                    document.addEventListener('keydown', function(event) {
                        if (event.key === 'Escape') {
                            fecharPreview();
                        }
                    });
                </script>
                <?php
            endif;
            ?>
        </div>
    </div>
</body>
</html>
