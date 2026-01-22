<?php
// INÍCIO CRÍTICO - Captura qualquer saída
ob_start();

// Desativa exibição de erros
error_reporting(0);
ini_set('display_errors', 0);

// Função para enviar JSON limpo
function enviarJSON($dados) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados);
    exit();
}

// Função para email de fotos pendentes - MOVIDA PARA O INÍCIO
function criarEmailFotosPendentes($nome, $marca, $modelo, $link) {
    return "
    <div style='font-family:Arial,sans-serif; max-width:600px; margin:0 auto; background:#ffffff;'>
        <!-- Header com logo -->
        <div style='background:#1a1a1a; color:white; padding:30px 20px; text-align:center;'>
            <img src='https://motorgo.co/imagens/logo_motorgo_blk.png' alt='MotorGo' style='max-width:200px; height:auto; margin-bottom:15px;'>
            <h1 style='margin:0; font-size:28px; color:#ffffff;'>Complete seu Anúncio!</h1>
        </div>
        
        <div style='padding:30px; background:#f8f9fa;'>
            <p style='font-size:18px; margin-bottom:20px;'>Olá <strong>{$nome}</strong>,</p>
            <p style='font-size:16px; line-height:1.6; margin-bottom:25px;'>Seu veículo <strong>{$marca} {$modelo}</strong> foi cadastrado, mas ainda não está visível no site.</p>
            
            <div style='background:white; border-left:4px solid #dc3545; padding:20px; margin:25px 0; border-radius:0 8px 8px 0;'>
                <p style='margin:0; color:#721c24; font-size:16px;'><strong>⚠️ Importante:</strong> São necessárias 6 fotos para ativar seu anúncio!</p>
            </div>
            
            <p style='font-size:16px; line-height:1.6; margin:25px 0;'>Para ativar seu anúncio, você precisa adicionar as 6 fotos obrigatórias do veículo:</p>
            
            <div style='text-align:center; margin:35px 0;'>
                <a href='{$link}' style='display:inline-block; padding:18px 45px; background:linear-gradient(135deg, #B22222 0%, #8B0000 100%); 
                    color:white; text-decoration:none; border-radius:8px; font-size:18px; font-weight:bold;
                    box-shadow:0 4px 15px rgba(178, 34, 34, 0.3);'>
                    📷 Adicionar 6 Fotos Agora
                </a>
            </div>
            
            <div style='background:#e8f5e8; border:1px solid #d4edda; padding:15px; border-radius:6px; margin:25px 0;'>
                <p style='margin:0; color:#155724; font-size:14px;'>
                    💡 <strong>Dica:</strong> Tire fotos de todos os ângulos: frente, traseira, laterais e interior!
                </p>
            </div>
        </div>
        
        <!-- Footer -->
        <div style='background:#1a1a1a; color:#999; padding:20px; text-align:center; font-size:12px;'>
            <p style='margin:0 0 10px 0;'>© " . date('Y') . " MotorGo - Mais valor para você!</p>
            <p style='margin:0;'>
                <a href='https://motorgo.com.br' style='color:#B22222; text-decoration:none;'>www.motorgo.com.br</a> | 
                <a href='mailto:sac@motorgo.co' style='color:#B22222; text-decoration:none;'>sac@motorgo.co</a>
            </p>
        </div>
    </div>";
}

// Inicia sessão
session_start();

// Inclui arquivos necessários
try {
    if (!file_exists("conexao_bd.php")) {
        enviarJSON(["success" => false, "message" => "Erro de configuração do servidor"]);
    }
    
    require_once "conexao_bd.php";
    
    if (!isset($mysqli) || $mysqli->connect_error) {
        enviarJSON(["success" => false, "message" => "Erro ao conectar com o banco de dados"]);
    }
    
    // Inclui helper de email se existir
    if (file_exists("helpers/email_proposta.php")) {
        require_once "helpers/email_proposta.php";
    }
    
} catch (Exception $e) {
    enviarJSON(["success" => false, "message" => "Erro interno do servidor"]);
}

// Log para debug (opcional)
error_log("=== SALVAR_FOTOS.PHP DEBUG ===");
error_log("POST: " . json_encode($_POST));
error_log("FILES: " . json_encode(array_map(function($file) {
    return [
        'name' => $file['name'] ?? 'sem nome',
        'size' => $file['size'] ?? 0,
        'error' => $file['error'] ?? 'desconhecido'
    ];
}, $_FILES)));

// Verificação da sessão e parâmetros
$usuario_id = $_SESSION['usuario_id'] ?? $_POST['usuario_id'] ?? null;
$veiculo_id = $_POST['veiculo_id'] ?? null;
$pular_fotos = $_POST['pular_fotos'] ?? false;

if (!$usuario_id || !$veiculo_id) {
    enviarJSON(["success" => false, "message" => "Usuário ou veículo não identificado."]);
}

$veiculo_id = intval($veiculo_id);
$usuario_id = intval($usuario_id);

// Se está pulando fotos, apenas marca como incompleto e envia email
if ($pular_fotos === 'true') {
    // Atualiza status para incompleto (sem fotos)
    $stmt = $mysqli->prepare("UPDATE veiculos SET status = 'incompleto' WHERE id = ? AND usuario_id = ?");
    $stmt->bind_param("ii", $veiculo_id, $usuario_id);
    
    if (!$stmt->execute()) {
        enviarJSON([
            "success" => false,
            "message" => "Erro ao atualizar status do veículo."
        ]);
    }
    
    // Busca dados do usuário para email
    $res = $mysqli->query("
        SELECT u.nome, u.email, v.marca, v.modelo 
        FROM usuarios u 
        JOIN veiculos v ON u.id = v.usuario_id 
        WHERE v.id = $veiculo_id
    ");
    
    if ($res && $res->num_rows > 0) {
        $dados = $res->fetch_assoc();
        
        // Gera o link para continuar depois
        $link = "https://motorgo.co/finalizar_cadastro.php?id={$veiculo_id}";
        $mensagem = criarEmailFotosPendentes($dados['nome'], $dados['marca'], $dados['modelo'], $link);
        
        // Envia o email
        enviarEmailProposta($dados['email'], $dados['nome'], "📷 Adicione fotos para ativar seu anúncio - MotorGo", $mensagem);
        
        error_log("Email enviado para: " . $dados['email'] . " - Link: " . $link);
    }
    
    // Destruir a sessão antes de retornar sucesso
    session_destroy();
    
    // Remove o cookie de sessão
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    enviarJSON([
        "success" => true,
        "status" => "incompleto",
        "message" => "Cadastro salvo sem fotos. Email enviado para completar depois.",
        "redirect" => "login.php?msg=cadastro_salvo"
    ]);
}

// PROCESSAMENTO NORMAL DE FOTOS
$caminhoFotos = "uploads/fotos_veiculos/{$veiculo_id}/";
if (!is_dir($caminhoFotos)) {
    mkdir($caminhoFotos, 0777, true);
}

$fotosSalvas = [];
$totalEnviadas = 0;
$errosUpload = [];

// Verifica cada campo de foto
for ($i = 1; $i <= 6; $i++) {
    $campo = "foto{$i}";
    error_log("Verificando campo: {$campo}");
    
    if (isset($_FILES[$campo])) {
        error_log("Campo {$campo} existe - Error: " . $_FILES[$campo]['error'] . ", Size: " . $_FILES[$campo]['size']);
        
        if ($_FILES[$campo]['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $errosUpload[] = "Foto {$i}: formato inválido ({$ext})";
                continue;
            }

            // Garante nome único
            $nomeFinal = "foto{$i}_" . uniqid() . "." . $ext;
            $caminhoCompleto = $caminhoFotos . $nomeFinal;

            if (move_uploaded_file($_FILES[$campo]['tmp_name'], $caminhoCompleto)) {
                $fotosSalvas[] = [
                    'caminho' => $caminhoCompleto,
                    'ordem' => $i
                ];
                $totalEnviadas++;
                error_log("✅ Foto {$i} salva com sucesso");
            } else {
                $errosUpload[] = "Foto {$i}: erro ao mover arquivo";
                error_log("❌ Erro ao mover foto {$i}");
            }
        } else {
            // Traduz erros de upload
            $erroMsg = match($_FILES[$campo]['error']) {
                UPLOAD_ERR_INI_SIZE => "tamanho excede limite do servidor",
                UPLOAD_ERR_FORM_SIZE => "tamanho excede limite do formulário",
                UPLOAD_ERR_PARTIAL => "upload parcial",
                UPLOAD_ERR_NO_FILE => "nenhum arquivo enviado",
                default => "erro desconhecido (" . $_FILES[$campo]['error'] . ")"
            };
            $errosUpload[] = "Foto {$i}: {$erroMsg}";
        }
    } else {
        error_log("Campo {$campo} NÃO existe no \$_FILES");
    }
}

error_log("Total de fotos processadas: {$totalEnviadas}");

// VALIDAÇÃO CRÍTICA: EXIGE EXATAMENTE 6 FOTOS
if ($totalEnviadas < 6) {
    // Remove fotos já enviadas para não deixar lixo
    foreach ($fotosSalvas as $foto) {
        if (file_exists($foto['caminho'])) {
            unlink($foto['caminho']);
        }
    }
    
    // Remove o diretório se estiver vazio
    if (is_dir($caminhoFotos)) {
        @rmdir($caminhoFotos);
    }
    
    $mensagemErro = "É obrigatório enviar 6 fotos do veículo. Você enviou apenas {$totalEnviadas}.";
    if (!empty($errosUpload)) {
        $mensagemErro .= "\n\nErros encontrados:\n" . implode("\n", $errosUpload);
    }
    
    enviarJSON([
        "success" => false,
        "message" => $mensagemErro,
        "fotos_enviadas" => $totalEnviadas,
        "fotos_necessarias" => 6,
        "debug_errors" => $errosUpload
    ]);
}

// Se chegou aqui, tem exatamente 6 fotos
$novoStatus = 'completo';

$mysqli->begin_transaction();

try {
    // Atualiza status do veículo
    $stmt = $mysqli->prepare("UPDATE veiculos SET status = ? WHERE id = ? AND usuario_id = ?");
    $stmt->bind_param("sii", $novoStatus, $veiculo_id, $usuario_id);
    $stmt->execute();
    
    // Salva fotos no banco
    foreach ($fotosSalvas as $foto) {
        $stmt = $mysqli->prepare("INSERT INTO fotos_veiculos (veiculo_id, caminho_foto, ordem_exibicao) VALUES (?, ?, ?)");
        $stmt->bind_param("isi", $veiculo_id, $foto['caminho'], $foto['ordem']);
        $stmt->execute();
    }
    
    // GERA CÓDIGO DE CONFIRMAÇÃO DE 6 DÍGITOS
    $codigo = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    $expira = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    
    // Salva código na sessão
    $_SESSION['codigo_confirmacao'] = $codigo;
    $_SESSION['codigo_expira'] = $expira;
    
    // Atualiza usuário com o token de 6 dígitos APENAS AGORA
    $stmt = $mysqli->prepare("
        UPDATE usuarios 
        SET status_cadastro = 'completo',
            token_confirmacao = ?,
            token_expira = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssi", $codigo, $expira, $usuario_id);
    $stmt->execute();
    
    error_log("✅ Token de confirmação gerado: {$codigo} para usuário ID: {$usuario_id}");
    
    // Busca dados para email
    $res = $mysqli->query("
        SELECT u.nome, u.email, v.marca, v.modelo 
        FROM usuarios u 
        JOIN veiculos v ON u.id = v.usuario_id 
        WHERE u.id = $usuario_id
    ");
    
    if ($res && $res->num_rows > 0) {
        $dados = $res->fetch_assoc();
        
        $mensagem = criarEmailConfirmacao($dados['nome'], $dados['marca'], $dados['modelo'], $codigo);
        enviarEmailProposta($dados['email'], $dados['nome'], "🔐 Código de Confirmação - MotorGo", $mensagem);
        
        error_log("📧 Email de confirmação enviado para: {$dados['email']}");
    }
    
    $mysqli->commit();
    
    enviarJSON([
        "success" => true,
        "status" => "completo",
        "fotos_recebidas" => $totalEnviadas,
        "precisa_confirmacao" => true,
        "message" => "Fotos enviadas com sucesso! Verifique seu email para confirmar o cadastro."
    ]);
    
} catch (Exception $e) {
    $mysqli->rollback();
    
    // Remove fotos em caso de erro
    foreach ($fotosSalvas as $foto) {
        if (file_exists($foto['caminho'])) {
            unlink($foto['caminho']);
        }
    }
    
    enviarJSON([
        "success" => false,
        "message" => "Erro ao processar fotos: " . $e->getMessage()
    ]);
}

/**
 * Email de confirmação (6 fotos enviadas)
 */
function criarEmailConfirmacao($nome, $marca, $modelo, $codigo) {
    return "
    <div style='font-family:Arial,sans-serif; max-width:600px; margin:0 auto; background:#ffffff;'>
        <!-- Header com logo -->
        <div style='background:#1a1a1a; color:white; padding:30px 20px; text-align:center;'>
            <img src='https://motorgo.co/imagens/logo_motorgo_blk.png' alt='MotorGo' style='max-width:200px; height:auto; margin-bottom:15px;'>
            <h1 style='margin:0; font-size:28px; color:#ffffff;'>✅ Quase lá!</h1>
        </div>
        
        <div style='padding:30px; background:#f8f9fa;'>
            <p style='font-size:18px; margin-bottom:20px;'>Olá <strong>{$nome}</strong>,</p>
            <p style='font-size:16px; line-height:1.6; margin-bottom:25px;'>Seu veículo <strong>{$marca} {$modelo}</strong> está quase pronto para ser publicado!</p>
            
            <div style='background:white; border:2px solid #28a745; padding:25px; margin:25px 0; text-align:center; border-radius:8px;'>
                <p style='margin:0 0 10px 0; font-size:16px; color:#666;'>Seu código de confirmação é:</p>
                <h2 style='margin:10px 0; font-size:42px; letter-spacing:8px; color:#333; font-family:monospace;'>{$codigo}</h2>
                <p style='margin:10px 0 0 0; font-size:14px; color:#999;'>Válido por 30 minutos</p>
            </div>
            
            <p style='font-size:16px; line-height:1.6; margin:25px 0;'>Digite este código na tela de cadastro para ativar seu anúncio.</p>
            
            <div style='background:#e8f5e8; border:1px solid #d4edda; padding:15px; border-radius:6px; margin:25px 0;'>
                <p style='margin:0; color:#155724; font-size:14px;'>
                    🔒 <strong>Segurança:</strong> Este código garante que apenas você pode ativar o anúncio.
                </p>
            </div>
        </div>
        
        <!-- Footer -->
        <div style='background:#1a1a1a; color:#999; padding:20px; text-align:center; font-size:12px;'>
            <p style='margin:0 0 10px 0;'>© " . date('Y') . " MotorGo - Mais valor para você!</p>
            <p style='margin:0;'>
                <a href='https://motorgo.com.br' style='color:#B22222; text-decoration:none;'>www.motorgo.com.br</a> | 
                <a href='mailto:sac@motorgo.co' style='color:#B22222; text-decoration:none;'>sac@motorgo.co</a>
            </p>
        </div>
    </div>";
}
?>