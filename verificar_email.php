<?php
session_start();
require_once "conexao_bd.php";
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["valido" => false, "erro" => "Método inválido."]);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(["valido" => false, "erro" => "Email é obrigatório."]);
    exit;
}

// 🔹 Normaliza o email
$email = strtolower($email);

// 🔹 Validação de formato
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "valido" => false,
        "erro" => "Formato de email inválido."
    ]);
    exit;
}

// 🔹 Lista de domínios temporários/descartáveis
$dominiosTemporarios = [
    '10minutemail.com', 'tempmail.com', 'guerrillamail.com',
    'mailinator.com', 'throwaway.email', 'temp-mail.org',
    'yopmail.com', 'maildrop.cc', 'temp-mail.ru',
    'disposablemail.com', 'tempmail.net', 'fakemailgenerator.com',
    'temp-mail.io', 'temporary-mail.net', 'getairmail.com',
    'mohmal.com', 'tempinbox.com', 'emailondeck.com',
    'sharklasers.com', 'guerrillamail.info', 'guerrillamail.biz',
    'guerrillamail.org', 'guerrillamail.de', 'spam4.me'
];

// 🔹 Verifica se é email temporário
$dominio = explode('@', $email)[1] ?? '';
if (in_array($dominio, $dominiosTemporarios)) {
    echo json_encode([
        "valido" => false,
        "erro" => "Emails temporários não são permitidos.\n\nPor favor, use um email permanente como Gmail, Yahoo, Outlook ou Hotmail."
    ]);
    exit;
}

// 🔹 Validação adicional de formato mais rigorosa
$regexRigoroso = '/^[a-zA-Z0-9]([a-zA-Z0-9._-]*[a-zA-Z0-9])?@[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?\.[a-zA-Z]{2,}$/';
if (!preg_match($regexRigoroso, $email)) {
    echo json_encode([
        "valido" => false,
        "erro" => "Formato de email inválido. Verifique se digitou corretamente."
    ]);
    exit;
}

// 🔹 Verificação no banco se email já existe
try {
    $stmt = $mysqli->prepare("SELECT id, nome, status_cadastro FROM usuarios WHERE email = ?");
    
    if (!$stmt) {
        throw new Exception("Erro ao preparar consulta.");
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $usuario = $result->fetch_assoc();
        
        // Se o usuário está com cadastro incompleto, orienta melhor
        if ($usuario['status_cadastro'] === 'incompleto') {
            echo json_encode([
                "valido" => false,
                "erro" => "Olá {$usuario['nome']}! Você já iniciou seu cadastro.\nFaça login para continuar de onde parou.",
                "redirect" => true // Flag para o frontend saber que pode redirecionar
            ]);
        } else {
            echo json_encode([
                "valido" => false,
                "erro" => "Este email já está cadastrado. Faça login para acessar sua conta.",
                "redirect" => true
            ]);
        }
    } else {
        echo json_encode([
            "valido" => true,
            "mensagem" => "Email válido."
        ]);
    }
    
} catch (Exception $e) {
    error_log("Erro verificar-email.php: " . $e->getMessage());
    echo json_encode([
        "valido" => false,
        "erro" => "Erro interno. Tente novamente em alguns instantes."
    ]);
}

$mysqli->close();
?>