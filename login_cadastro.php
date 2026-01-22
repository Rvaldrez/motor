<?php
require_once 'config.php';
require_once 'twilio.php'; // Inclui função de envio via Twilio

// Conectar ao banco de dados
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    die("Erro de conexão: " . $mysqli->connect_error);
}

// Função para gerar token aleatório de 6 dígitos
function gerarToken() {
    return rand(100000, 999999);
}

// Login do Usuário
if (isset($_POST['login_email'], $_POST['login_senha'])) {
    $email = $_POST['login_email'];
    $senha = $_POST['login_senha'];

    $query = "SELECT * FROM usuarios WHERE email = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($senha, $user['senha'])) {
            echo "Login bem-sucedido!";
            // Redireciona para a página principal ou painel
        } else {
            echo "Senha incorreta!";
        }
    } else {
        echo "E-mail não encontrado!";
    }
}

// Cadastro de Novo Usuário
if (isset($_POST['cadastro_email'], $_POST['cadastro_senha'], $_POST['cadastro_celular'])) {
    $email = $_POST['cadastro_email'];
    $senha = password_hash($_POST['cadastro_senha'], PASSWORD_DEFAULT); // Armazena a senha de forma segura
    $celular = $_POST['cadastro_celular'];
    $token = gerarToken();

    // Verificar se o e-mail ou celular já está cadastrado
    $query = "SELECT * FROM usuarios WHERE email = ? OR celular = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('ss', $email, $celular);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "E-mail ou celular já cadastrados!";
    } else {
        // Inserir novo usuário no banco com status 'pendente'
        $query = "INSERT INTO usuarios (email, senha, celular, status_confirmacao, token_confirmacao) VALUES (?, ?, ?, 'pendente', ?)";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('ssss', $email, $senha, $celular, $token);
        $stmt->execute();

        // Enviar código via WhatsApp
        if (enviarWhatsAppConfirmacao($celular, $token)) {
            echo "Cadastro bem-sucedido! Verifique seu WhatsApp para o código de ativação.";
        } else {
            echo "Erro ao enviar o código de confirmação!";
        }
    }
}

// Ativar a conta após verificar o código enviado via WhatsApp
if (isset($_POST['token_confirmacao'], $_POST['email'])) {
    $token = $_POST['token_confirmacao'];
    $email = $_POST['email'];

    $query = "SELECT * FROM usuarios WHERE email = ? AND token_confirmacao = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('ss', $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        // Ativar conta
        $query = "UPDATE usuarios SET status_confirmacao = 'confirmado' WHERE email = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        echo "Conta ativada com sucesso!";
    } else {
        echo "Código inválido ou expirado!";
    }
}
?>
