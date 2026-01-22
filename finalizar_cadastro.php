<?php
session_start();
require_once "conexao_bd.php";
header('Content-Type: text/html; charset=utf-8');

// 🔒 Verificação de parâmetros de entrada
$usuario_id = isset($_GET['usuario_id']) && is_numeric($_GET['usuario_id']) ? intval($_GET['usuario_id']) : null;
$veiculo_id = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : null;

// 🔧 Logs para debug
error_log("=== FINALIZAR_CADASTRO.PHP ===");
error_log("usuario_id: " . ($usuario_id ?? 'NULL'));
error_log("veiculo_id: " . ($veiculo_id ?? 'NULL'));

// 🔧 Função de erro melhorada
function exibirErroAmigavel($titulo, $mensagem, $botaoTexto = "Ir para Cadastro", $redirecionamento = "cadastro_veiculos.php") {
  echo "
  <!DOCTYPE html>
  <html lang='pt-BR'>
  <head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>MotorGo - $titulo</title>
    <style>
      body { 
        font-family: Arial, sans-serif; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        height: 100vh; 
        margin: 0;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
      }
      .container { 
        background: #fff; 
        border-radius: 15px;
        padding: 40px; 
        box-shadow: 0 15px 35px rgba(0,0,0,0.1); 
        max-width: 500px; 
        text-align: center;
      }
      .icone { font-size: 48px; margin-bottom: 20px; }
      h2 { color: #333; margin-bottom: 15px; }
      p { color: #666; line-height: 1.5; margin-bottom: 30px; }
      .btn { 
        background: #B22222; 
        color: #fff; 
        border: none; 
        padding: 12px 30px; 
        border-radius: 8px;
        cursor: pointer; 
        font-size: 16px;
        font-weight: bold;
        text-decoration: none;
        display: inline-block;
        transition: background 0.3s;
      }
      .btn:hover { background: #8B0000; }
    </style>
  </head>
  <body>
    <div class='container'>
      <div class='icone'>⚠️</div>
      <h2>$titulo</h2>
      <p>$mensagem</p>
      <a href='$redirecionamento' class='btn'>$botaoTexto</a>
    </div>
  </body>
  </html>";
  exit;
}

// ❌ Nenhum parâmetro fornecido
if (!$usuario_id && !$veiculo_id) {
  error_log("ERRO: Nenhum parâmetro fornecido");
  exibirErroAmigavel(
    "Link inválido",
    "O link que você clicou não contém as informações necessárias. Por favor, inicie um novo cadastro.",
    "Novo Cadastro"
  );
}

$dados = null;
$tipoAcesso = null;

// 🔍 BUSCA POR USUÁRIO ID (mais comum)
if ($usuario_id) {
  error_log("Buscando por usuario_id: $usuario_id");
  
  $stmt = $mysqli->prepare("
    SELECT 
      u.id AS usuario_id,
      u.nome, u.email, u.status_cadastro,
      v.id AS veiculo_id,
      v.status AS status_veiculo,
      v.placa, v.modelo, v.marca, v.ano_fabrica
    FROM usuarios u
    LEFT JOIN veiculos v ON v.usuario_id = u.id AND v.status IN ('incompleto', 'completo')
    WHERE u.id = ?
    ORDER BY v.id DESC LIMIT 1
  ");
  
  if (!$stmt) {
    error_log("ERRO ao preparar query para usuario_id: " . $mysqli->error);
    exibirErroAmigavel(
      "Erro interno",
      "Houve um problema com o banco de dados. Tente novamente em alguns minutos.",
      "Tentar Novamente"
    );
  }
  
  $stmt->bind_param("i", $usuario_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $dados = $result->fetch_assoc();
  $tipoAcesso = 'usuario';
  
  error_log("Resultado da busca por usuario_id: " . ($dados ? "ENCONTRADO" : "NÃO ENCONTRADO"));
  if ($dados) {
    error_log("Dados: nome=" . $dados['nome'] . ", veiculo_id=" . ($dados['veiculo_id'] ?? 'NULL'));
  }
}

// 🔍 BUSCA POR VEÍCULO ID (caso específico)
if (!$dados && $veiculo_id) {
  error_log("Buscando por veiculo_id: $veiculo_id");
  
  $stmt = $mysqli->prepare("
    SELECT 
      v.id AS veiculo_id,
      v.status AS status_veiculo,
      v.placa, v.modelo, v.marca, v.ano_fabrica,
      u.id AS usuario_id,
      u.nome, u.email, u.status_cadastro
    FROM veiculos v
    INNER JOIN usuarios u ON u.id = v.usuario_id
    WHERE v.id = ?
  ");
  
  if (!$stmt) {
    error_log("ERRO ao preparar query para veiculo_id: " . $mysqli->error);
    exibirErroAmigavel(
      "Erro interno",
      "Houve um problema com o banco de dados. Tente novamente em alguns minutos.",
      "Tentar Novamente"
    );
  }
  
  $stmt->bind_param("i", $veiculo_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $dados = $result->fetch_assoc();
  $tipoAcesso = 'veiculo';
  
  error_log("Resultado da busca por veiculo_id: " . ($dados ? "ENCONTRADO" : "NÃO ENCONTRADO"));
}

// ❌ Nenhum dado encontrado
if (!$dados) {
  error_log("ERRO: Nenhum dado encontrado para os parâmetros fornecidos");
  exibirErroAmigavel(
    "Cadastro não encontrado",
    "Não encontramos suas informações de cadastro. Isso pode acontecer se o link expirou ou se o cadastro já foi finalizado.",
    "Novo Cadastro"
  );
}

// 🔒 Verifica se é necessário continuar
$statusCadastro = strtolower(trim($dados['status_cadastro'] ?? ''));
$statusVeiculo = strtolower(trim($dados['status_veiculo'] ?? ''));
$temDadosVeiculo = !empty($dados['placa']) && !empty($dados['modelo']) && !empty($dados['marca']);

error_log("Status verificados: cadastro=$statusCadastro, veiculo=$statusVeiculo, temDados=" . ($temDadosVeiculo ? 'SIM' : 'NÃO'));

// ✅ Verifica se ainda precisa continuar
$podeAcessar = in_array($statusCadastro, ['incompleto']) || 
               in_array($statusVeiculo, ['incompleto']);

if (!$podeAcessar && $statusCadastro === 'completo') {
  error_log("INFO: Cadastro já finalizado");
  exibirErroAmigavel(
    "Cadastro já finalizado",
    "Seu cadastro já foi finalizado com sucesso! Se precisar fazer alterações, acesse seu painel de controle.",
    "Fazer Login", 
    "login.php"
  );
}

// 🎯 LÓGICA INTELIGENTE PARA DETERMINAR ETAPA
$etapaDestino = 'etapa2'; // Padrão: sempre volta para dados do veículo

// 🔍 Se tem dados do veículo E status é incompleto = vai para fotos
if ($temDadosVeiculo && $statusVeiculo === 'incompleto') {
  $etapaDestino = 'etapa3';
}

// 🔍 Se NÃO tem dados do veículo OU não tem veículo = sempre vai para dados
if (!$temDadosVeiculo || !$dados['veiculo_id']) {
  $etapaDestino = 'etapa2';
}

error_log("Decisão da etapa - temDados: " . ($temDadosVeiculo ? 'SIM' : 'NÃO') . 
          ", veiculo_id: " . ($dados['veiculo_id'] ?? 'NULL') . 
          ", statusVeiculo: $statusVeiculo -> Etapa: $etapaDestino");

// ✅ CONFIGURAR SESSÃO
$_SESSION['usuario_id'] = $dados['usuario_id'];
$_SESSION['usuario_nome'] = $dados['nome'] ?? '';
$_SESSION['usuario_tipo'] = 'vendedor';
$_SESSION['status_cadastro'] = $statusCadastro;
$_SESSION['continuando_cadastro'] = true; // FLAG IMPORTANTE!
$_SESSION['email_usuario'] = $dados['email']; // SALVA O EMAIL PARA EVITAR CONFLITO
session_write_close();

error_log("Sessão configurada para usuario_id: " . $dados['usuario_id']);

// 🔄 INTERFACE DE REDIRECIONAMENTO MELHORADA
echo "
<!DOCTYPE html>
<html lang='pt-BR'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
  <title>MotorGo - Continuando seu cadastro...</title>
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
      padding: 0;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      color: #333;
    }
    .container {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      padding: 50px;
      text-align: center;
      box-shadow: 0 20px 40px rgba(0,0,0,0.1);
      max-width: 500px;
      width: 90%;
    }
    .logo {
      max-width: 200px;
      margin-bottom: 30px;
    }
    .spinner {
      border: 4px solid #f3f3f3;
      border-top: 4px solid #B22222;
      border-radius: 50%;
      width: 50px;
      height: 50px;
      animation: spin 1s linear infinite;
      margin: 0 auto 30px;
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    .welcome-message {
      font-size: 28px;
      font-weight: bold;
      color: #333;
      margin-bottom: 15px;
    }
    .sub-message {
      font-size: 16px;
      color: #666;
      margin-bottom: 10px;
      line-height: 1.5;
    }
    .etapa-info {
      background: #f8f9fa;
      border-left: 4px solid #B22222;
      padding: 20px;
      margin: 25px 0;
      border-radius: 0 10px 10px 0;
    }
    .manual-link {
      margin-top: 30px;
      padding: 15px 30px;
      background: #B22222;
      color: white;
      text-decoration: none;
      border-radius: 10px;
      display: inline-block;
      font-weight: bold;
      transition: all 0.3s ease;
    }
    .manual-link:hover {
      background: #8B0000;
      transform: translateY(-2px);
    }
    .progress-bar {
      width: 100%;
      height: 6px;
      background: #e9ecef;
      border-radius: 3px;
      margin: 20px 0;
      overflow: hidden;
    }
    .progress-fill {
      height: 100%;
      background: linear-gradient(90deg, #B22222, #FF6B6B);
      width: " . ($etapaDestino === 'etapa2' ? '66' : '100') . "%;
      transition: width 2s ease;
    }
  </style>
</head>
<body>
  <div class='container'>
    <img src='imagens/logo_motorgo.png' alt='MotorGo' class='logo' />
    
    <div class='spinner'></div>
    
    <div class='welcome-message'>
      Bem-vindo de volta, " . htmlspecialchars($dados['nome']) . "! 
    </div>
    
    <div class='sub-message'>
      Estamos preparando tudo para você continuar seu cadastro...
    </div>
    
    <div class='progress-bar'>
      <div class='progress-fill'></div>
    </div>
    
    <div class='etapa-info'>
      <strong>📍 Próxima etapa:</strong> " . ($etapaDestino === 'etapa2' ? 'Dados do Veículo' : 'Fotos do Veículo') . "<br>
      <small>Falta pouco para finalizar! ⚡</small>
    </div>
    
  <a href='cadastro_veiculos.php?from_email=1' class='manual-link' id='manualLink' style='display:none'>
  ➡️ Continuar Manualmente
</a>
  </div>

  <script>
    // ✅ Configuração da sessão com melhor debug
    console.log('🔧 Configurando sessionStorage...');
    sessionStorage.clear();
    
    const usuarioId = '{$dados['usuario_id']}';
    const etapa = '{$etapaDestino}';
    const nomeUsuario = '" . addslashes($dados['nome']) . "';
    const veiculoId = '" . ($dados['veiculo_id'] ?? '') . "';
    const emailUsuario = '" . addslashes($dados['email']) . "';
    
    sessionStorage.setItem('usuario_id', usuarioId);
    sessionStorage.setItem('etapa', etapa);
    sessionStorage.setItem('pular_etapa1', 'true');
    sessionStorage.setItem('nome_usuario', nomeUsuario);
    sessionStorage.setItem('continuando_cadastro', 'true'); // FLAG IMPORTANTE!
    sessionStorage.setItem('email_usuario', emailUsuario); // SALVA EMAIL
    
    // Só salva veiculo_id se realmente existir e tiver dados
    if (veiculoId && veiculoId !== '' && " . ($temDadosVeiculo ? 'true' : 'false') . ") {
      sessionStorage.setItem('veiculo_id', veiculoId);
      console.log('✅ veiculo_id salvo:', veiculoId);
    } else {
      console.log('ℹ️ veiculo_id NÃO salvo - usuário vai para etapa de dados');
    }
    
    // ✅ Debug info
    console.log('🎯 Redirecionamento MotorGo');
    console.log('👤 Usuario:', '{$dados['nome']}' + ' (ID: {$dados['usuario_id']})');
    console.log('📍 Etapa destino:', '{$etapaDestino}');
    console.log('📊 Status cadastro:', '{$statusCadastro}');
    console.log('🚗 Tem dados veículo:', " . ($temDadosVeiculo ? 'true' : 'false') . ");
    console.log('📧 Email salvo:', emailUsuario);
    
    // ✅ Redirecionamento com delay
    setTimeout(function() {
      try {
        window.location.href = 'cadastro_veiculos.php?from_email=1';
      } catch(e) {
        console.error('❌ Erro no redirecionamento:', e);
        document.getElementById('manualLink').style.display = 'inline-block';
      }
    }, 2500);
    
    // ✅ Fallback manual
    setTimeout(function() {
      document.getElementById('manualLink').style.display = 'inline-block';
    }, 8000);
  </script>
</body>
</html>
";
?>