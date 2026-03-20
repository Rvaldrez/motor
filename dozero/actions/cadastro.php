<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
require_once '../includes/config.php';
require_once '../includes/conexao.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token inválido.']);
    exit;
}

$nome             = trim($_POST['nome']             ?? '');
$email            = trim($_POST['email']            ?? '');
$celular          = trim($_POST['celular']          ?? '');
$cpf              = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
$tipo             = trim($_POST['tipo']             ?? '');
$cep              = trim($_POST['cep']              ?? '');
$endereco         = trim($_POST['endereco']         ?? '');
$numero           = trim($_POST['numero']           ?? '');
$complemento      = trim($_POST['complemento']      ?? '');
$bairro           = trim($_POST['bairro']           ?? '');
$cidade           = trim($_POST['cidade']           ?? '');
$estado           = trim($_POST['estado']           ?? '');
$senha            = $_POST['senha']                 ?? '';
$confirmar_senha  = $_POST['confirmar_senha']       ?? '';
$termo_aceito     = $_POST['termo_aceito']          ?? '0';

$erros = [];
if ($nome === '')       $erros[] = 'Nome é obrigatório.';
if (!validateEmail($email)) $erros[] = 'E-mail inválido.';
if ($celular === '')    $erros[] = 'Celular é obrigatório.';
if (!validateCpf($cpf)) $erros[] = 'CPF inválido.';
if (!in_array($tipo, ['vendedor','investidor'], true)) $erros[] = 'Tipo de conta inválido.';
if ($cep === '')        $erros[] = 'CEP é obrigatório.';
if ($endereco === '')   $erros[] = 'Endereço é obrigatório.';
if ($numero === '')     $erros[] = 'Número é obrigatório.';
if ($bairro === '')     $erros[] = 'Bairro é obrigatório.';
if ($cidade === '')     $erros[] = 'Cidade é obrigatória.';
if ($estado === '')     $erros[] = 'Estado é obrigatório.';
if (strlen($senha) < 8) $erros[] = 'Senha deve ter no mínimo 8 caracteres.';
if ($senha !== $confirmar_senha) $erros[] = 'As senhas não coincidem.';
if ($termo_aceito !== '1') $erros[] = 'Você deve aceitar os termos.';

if (!empty($erros)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $erros)]);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'E-mail já cadastrado.']);
    exit;
}
$stmt->close();

$stmt = $conn->prepare("SELECT id FROM usuarios WHERE cpf = ? LIMIT 1");
$stmt->bind_param('s', $cpf);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'CPF já cadastrado.']);
    exit;
}
$stmt->close();

$hash   = password_hash($senha, PASSWORD_DEFAULT);
$codigo = generateCode(6);
$token  = generateToken();
$expira = date('Y-m-d H:i:s', strtotime('+2 hours'));

$stmt = $conn->prepare(
    "INSERT INTO usuarios (nome, email, celular, cpf, tipo, cep, endereco, numero, complemento, bairro, cidade, estado, senha, token_confirmacao, token_expira, status_confirmacao, status_cadastro, termo_aceito, data_cadastro)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', 'incompleto', 1, NOW())"
);
$stmt->bind_param('sssssssssssssss', $nome, $email, $celular, $cpf, $tipo, $cep, $endereco, $numero, $complemento, $bairro, $cidade, $estado, $hash, $codigo, $expira);

if (!$stmt->execute()) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar cadastro. Tente novamente.']);
    exit;
}
$stmt->close();

$htmlBody = "
<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>
  <h2 style='color:#1a1a2e'>Bem-vindo à MotorGo, {$nome}!</h2>
  <p>Use o código abaixo para confirmar seu e-mail:</p>
  <div style='font-size:36px;font-weight:bold;letter-spacing:8px;color:#e63946;text-align:center;padding:20px;background:#f8f9fa;border-radius:8px'>{$codigo}</div>
  <p style='color:#666;font-size:13px'>Este código expira em 2 horas.</p>
</div>";
sendEmail($email, $nome, 'MotorGo – Confirme seu e-mail', $htmlBody);

echo json_encode([
    'success'  => true,
    'message'  => 'Cadastro realizado! Verifique seu e-mail.',
    'redirect' => '../confirmar_email.php?email=' . urlencode($email)
]);
