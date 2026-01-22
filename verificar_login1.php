<?php
session_start();
require_once "conexao_bd.php";

$cpf_email = trim($_POST['cpf_email'] ?? '');
$senha     = trim($_POST['senha'] ?? '');

// Função para redirecionar com erro
function redirecionarComErro($mensagem) {
    header("Location: login1.php?erro=" . urlencode($mensagem));
    exit;
}

// 🔒 Verifica campos obrigatórios
if (empty($cpf_email) || empty($senha)) {
    redirecionarComErro("Preencha todos os campos obrigatórios.");
}

// Limpar CPF se for o caso (remover pontos e traços)
$cpf_limpo = preg_replace('/\D/', '', $cpf_email);
$is_cpf = strlen($cpf_limpo) === 11;

// 🔍 Busca usuário por e-mail ou CPF
if ($is_cpf) {
    // Buscar por CPF
    $stmt = $mysqli->prepare("SELECT id, nome, email, senha, tipo, status_cadastro, status_confirmacao, token_expira FROM usuarios WHERE cpf = ?");
    $stmt->bind_param("s", $cpf_limpo);
} else {
    // Buscar por email
    $stmt = $mysqli->prepare("SELECT id, nome, email, senha, tipo, status_cadastro, status_confirmacao, token_expira FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $cpf_email);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    if ($is_cpf) {
        redirecionarComErro("CPF não encontrado. Verifique os dados e tente novamente.");
    } else {
        redirecionarComErro("E-mail não encontrado. Verifique os dados e tente novamente.");
    }
}

$usuario = $result->fetch_assoc();

// 🔐 Valida senha
if (!password_verify($senha, $usuario['senha'])) {
    redirecionarComErro("Senha incorreta. Tente novamente ou clique em Esqueci Minha Senha.");
}

// 🧠 Normaliza campos
$tipo = strtolower(trim($usuario['tipo'] ?? 'vendedor')); // Define vendedor como padrão se NULL
$status = strtolower(trim($usuario['status_cadastro']));
$status_confirmacao = strtolower(trim($usuario['status_confirmacao']));

// ✅ Salva sessão
$_SESSION['usuario_id']   = $usuario['id'];
$_SESSION['usuario_nome'] = $usuario['nome'];
$_SESSION['usuario_email'] = $usuario['email'];
$_SESSION['usuario_tipo'] = $tipo;
$_SESSION['status_cadastro'] = $status;
$_SESSION['status_confirmacao'] = $status_confirmacao;

// 🔴 NOVA LÓGICA SIMPLIFICADA: Redireciona investidor pendente para cadastro_investidor.php
if (($tipo === 'investidor' || $tipo === '') && $status_confirmacao === 'pendente') {
    session_write_close();
    
    // Redireciona para cadastro_investidor.php com parâmetros especiais
    header("Location: cadastro_investidor.php?continuar=true&email=" . urlencode($usuario['email']) . "&id=" . $usuario['id']);
    exit;
}

// 🚧 Redireciona para finalizar APENAS se for vendedor com cadastro incompleto
if ($tipo === 'vendedor' && in_array($status, ['incompleto', 'incompleto1'])) {
    session_write_close();
    header("Location: finalizar_cadastro.php?usuario_id=" . $usuario['id']);
    exit;
}

// 🔍 VERIFICAÇÃO PARA VENDEDOR: Se cadastro completo mas confirmação pendente
if ($tipo === 'vendedor' && $status === 'completo' && $status_confirmacao === 'pendente') {
    // Verifica se tem código expirado
    $token_expira = $usuario['token_expira'];
    
    $codigo_expirado = false;
    if ($token_expira) {
        $codigo_expirado = strtotime($token_expira) < time();
    }
    
    if ($codigo_expirado) {
        $_SESSION['precisa_novo_codigo'] = true;
    }
    
    $_SESSION['precisa_confirmar_codigo'] = true;
    session_write_close();
    header("Location: confirmar_cadastro.php");
    exit;
}

// ✅ Redireciona conforme tipo para o painel
switch ($tipo) {
    case 'vendedor':
    case 'investidor':
        header("Location: painel_veiculos.php");
        break;
    case 'administrador':
        header("Location: painel_administrador.php");
        break;
    default:
        session_destroy();
        redirecionarComErro("Tipo de usuário inválido. Entre em contato com o suporte.");
}
?>