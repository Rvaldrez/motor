<?php
session_start();
require_once "conexao_bd.php";
header("Content-Type: application/json");

// 🔐 Captura dados
$usuario_id = $_POST['usuario_id'] ?? null;
$codigo = $_POST['codigo'] ?? '';

if (!$usuario_id || !$codigo) {
  echo json_encode(["success" => false, "message" => "Dados incompletos."]);
  exit;
}

$usuario_id = intval($usuario_id);
$codigo = trim($codigo);

// 🔍 Verifica o código no banco
// O usuário deve ter status_cadastro = 'completo' (já completou as etapas)
// mas status_confirmacao = 'pendente' (aguardando confirmação do email)
$stmt = $mysqli->prepare("
  SELECT id, nome, email, token_confirmacao, token_expira, status_confirmacao
  FROM usuarios 
  WHERE id = ? AND status_confirmacao = 'pendente' AND status_cadastro = 'completo'
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo json_encode(["success" => false, "message" => "Usuário não encontrado ou já confirmado."]);
  exit;
}

$usuario = $result->fetch_assoc();

// Verifica se já foi confirmado
if ($usuario['status_confirmacao'] === 'confirmado') {
  echo json_encode(["success" => false, "message" => "Este cadastro já foi confirmado."]);
  exit;
}

// ⏰ Verifica se o código expirou
if ($usuario['token_expira'] && strtotime($usuario['token_expira']) < time()) {
  echo json_encode(["success" => false, "message" => "Código expirado. Solicite um novo."]);
  exit;
}

// 🔑 Verifica se o código está correto
// Como você salva um token MD5, vamos comparar com MD5 do código digitado
// OU comparar diretamente se você salvou o código puro
if ($codigo !== $usuario['token_confirmacao'] && md5($codigo) !== $usuario['token_confirmacao']) {
  echo json_encode(["success" => false, "message" => "Código inválido. Verifique e tente novamente."]);
  exit;
}

// ✅ Código válido - confirma o cadastro
$mysqli->begin_transaction();

try {
  // Atualiza APENAS o status_confirmacao para 'confirmado'
  // status_cadastro já deve estar 'completo' pois o usuário já enviou as 6 fotos
  $stmt = $mysqli->prepare("
    UPDATE usuarios 
    SET status_confirmacao = 'confirmado',
        token_confirmacao = '',
        token_expira = NULL
    WHERE id = ?
  ");
  $stmt->bind_param("i", $usuario_id);
  $stmt->execute();
  
  // Os veículos já devem estar com status = 'completo' após enviar as 6 fotos
  // Agora que o email foi confirmado, o anúncio será exibido baseado em:
  // usuarios.status_confirmacao = 'confirmado' AND veiculos.status = 'completo'
  
  $mysqli->commit();
  
  // 🔧 IMPORTANTE: Atualiza a sessão do usuário ANTES de enviar o email
  $_SESSION['status_confirmacao'] = 'confirmado';
  
  // Força a gravação da sessão
  session_write_close();
  
  // 📧 Envia email de boas-vindas
  $mensagem = "
    <div style='font-family:Arial,sans-serif; max-width:600px; margin:0 auto;'>
      <div style='background:linear-gradient(135deg,rgb(26, 26, 26),rgb(34, 32, 32)); color:white; padding:30px; text-align:center;'>
        <h1 style='margin:0; font-size:32px;'>Parabéns!</h1>
        <p style='margin:10px 0 0 0; font-size:18px;'>Seu anúncio está ativo!</p>
      </div>
      <div style='padding:30px; background:#f8f9fa;'>
        <p style='font-size:18px;'>Olá <strong>{$usuario['nome']}</strong>,</p>
        <p>Seu cadastro foi confirmado com sucesso e seu veículo já está visível para milhares de compradores!</p>
        
        <div style='background:white; border:2px solid #28a745; padding:20px; margin:20px 0; text-align:center;'>
          <p style='margin:0; color:#28a745; font-size:20px; font-weight:bold;'>✅ Anúncio Publicado!</p>
        </div>
        
        <h3>O que acontece agora?</h3>
        <ul style='line-height:1.8;'>
          <li>Seu veículo aparecerá nas buscas imediatamente</li>
          <li>Você receberá notificações quando houver interessados</li>
          <li>Pode editar seu anúncio a qualquer momento no painel</li>
        </ul>
        
   <div style='text-align:center; margin:30px 0;'>
  <a href='https://motorgo.co/login.php?msg=cadastro_confirmado' 
     style='display:inline-block; padding:15px 40px; background:#B22222; 
     color:white; text-decoration:none; border-radius:5px; font-size:18px; font-weight:bold;'>
    Acessar Meu Painel
  </a>
</div>
        
        <p style='color:#666; font-size:14px; border-top:1px solid #ddd; padding-top:20px; margin-top:30px;'>
          💡 <strong>Dica:</strong> Mantenha seu anúncio atualizado e responda rapidamente aos interessados para vender mais rápido!
        </p>
      </div>
    </div>
  ";
  
  // Envia email usando a função helper
  require_once "helpers/email_proposta.php";
  enviarEmailProposta($usuario['email'], $usuario['nome'], "🎉 Seu anúncio está no ar! - MotorGo", $mensagem);
  
  // Limpa variáveis de sessão temporárias
  unset($_SESSION['codigo_confirmacao']);
  unset($_SESSION['codigo_expira']);
  unset($_SESSION['precisa_confirmar_codigo']);
  unset($_SESSION['precisa_novo_codigo']);
  
  echo json_encode([
    "success" => true,
    "message" => "Cadastro confirmado com sucesso!"
  ]);
  
} catch (Exception $e) {
  $mysqli->rollback();
  echo json_encode([
    "success" => false,
    "message" => "Erro ao confirmar cadastro: " . $e->getMessage()
  ]);
}

$stmt->close();
$mysqli->close();
?>