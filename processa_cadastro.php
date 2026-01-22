<?php
header("Content-Type: application/json; charset=UTF-8");
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log_custom.txt');

require_once 'conexao_bd.php';
require_once __DIR__ . '/vendor/autoload.php'; // ✅ Autoload dentro de public_html

use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ✅ Carrega variáveis de ambiente
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// ✅ Verifica se variáveis estão disponíveis
if (!isset($_ENV['EMAIL_USUARIO'], $_ENV['EMAIL_SENHA'])) {
    logErro("Variáveis de ambiente não definidas.");
    echo json_encode(["success" => false, "message" => "Erro interno: e-mail não configurado."]);
    exit;
}

// 📌 Função para log de erro
function logErro($mensagem) {
    file_put_contents(__DIR__ . '/error_log_custom.txt', date("Y-m-d H:i:s") . " - " . $mensagem . "\n", FILE_APPEND);
}

// ✅ Verifica conexão com o banco
if (!$mysqli || $mysqli->connect_error) {
    logErro("Erro ao conectar ao banco: " . $mysqli->connect_error);
    echo json_encode(["success" => false, "message" => "Erro ao conectar ao banco."]);
    exit;
}

// ✅ Verifica método POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Método inválido."]);
    exit;
}

// ✅ Captura os dados
$nome     = trim($_POST["nome"] ?? "");
$email    = trim($_POST["email"] ?? "");
$celular  = trim($_POST["celular"] ?? "");
$cpf      = trim($_POST["cpf"] ?? "");
$cep      = trim($_POST["cep"] ?? "");
$endereco = trim($_POST["endereco"] ?? "");
$cidade   = trim($_POST["cidade"] ?? "");
$estado   = trim($_POST["estado"] ?? "");
$senha    = trim($_POST["senha"] ?? "");
$termo_aceito = isset($_POST["termo_aceito"]) ? 1 : 0;


// ✅ Validação de campos obrigatórios
if (empty($nome) || empty($email) || empty($celular) || empty($cpf) || empty($cep) || empty($senha)) {
    echo json_encode(["success" => false, "message" => "Todos os campos obrigatórios devem ser preenchidos."]);
    exit;
}

// ✅ Criptografa a senha
$senha_hashed = password_hash($senha, PASSWORD_DEFAULT);

// ✅ Gera código de verificação
$token_confirmacao = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);

// ✅ Verifica duplicidade
$sql_check = "SELECT id FROM usuarios WHERE email = ? OR cpf = ?";
$stmt_check = $mysqli->prepare($sql_check);
$stmt_check->bind_param("ss", $email, $cpf);
$stmt_check->execute();
$stmt_check->store_result();

if ($stmt_check->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "E-mail ou CPF já cadastrado."]);
    exit;
}

// ✅ Insere novo usuário
$sql = "INSERT INTO usuarios (nome, email, celular, cpf, cep, endereco, cidade, estado, senha, token_confirmacao, status_confirmacao, termo_aceito) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', ?)";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ssssssssssi", $nome, $email, $celular, $cpf, $cep, $endereco, $cidade, $estado, $senha_hashed, $token_confirmacao, $termo_aceito);


if ($stmt->execute()) {
    $usuario_id = $stmt->insert_id;

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

        $mail->setFrom($_ENV['EMAIL_USUARIO'], 'Suporte MotorGo');
        $mail->addAddress($email, $nome);
        $mail->isHTML(true);
        $mail->Subject = 'Confirmação de E-mail - MotorGo';

        // ✅ Corpo HTML do e-mail
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; background-color: #ffffff; border: 1px solid #ddd; box-shadow: 0 0 10px rgba(0,0,0,0.05);'>
            <div style='background-color: #1A1A1A; padding: 30px 0; text-align: center;'>
                <img src='https://motorgo.co/imagens/logo_motorgo_blk.png' alt='MotorGo' style='max-width: 180px;'>
            </div>
            <div style='padding: 30px 25px;'>
                <h2 style='color: #2c3e50;'>🚗 Bem-vindo à MotorGo!</h2>
                <p style='font-size: 16px;'>Olá <strong>$nome</strong>,</p>
                <p style='font-size: 15px;'>Estamos quase lá! Para confirmar seu cadastro, digite o código abaixo no nosso site:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <span style='font-size: 40px; font-weight: bold; color: #e53935; letter-spacing: 10px;'>$token_confirmacao</span>
                </div>
                <p style='font-size: 14px;'>Se você não solicitou este cadastro, favor desconsiderar esta mensagem.</p>
                <br>
                <p style='font-size: 13px; color: #888;'>Atenciosamente,<br>Equipe MotorGo 🚗<br><a href='https://motorgo.com.br' style='color: #888; text-decoration: none;'>motorgo.com.br</a></p>
            </div>
        </div>";

        if ($mail->send()) {
            echo json_encode([
                "success" => true,
                "message" => "Cadastro efetuado! Código enviado para o e-mail.",
                "usuario_id" => $usuario_id,
                "email" => $email
            ]);
        } else {
            logErro("Erro ao enviar e-mail: " . $mail->ErrorInfo);
            echo json_encode([
                "success" => false,
                "message" => "Erro ao enviar e-mail.",
                "usuario_id" => $usuario_id,
                "email" => $email
            ]);
        }
    } catch (Exception $e) {
        logErro("Erro PHPMailer: " . $e->getMessage());
        echo json_encode([
            "success" => false,
            "message" => "Erro ao enviar o código de verificação.",
            "usuario_id" => $usuario_id,
            "email" => $email
        ]);
    }
} else {
    logErro("Erro ao cadastrar usuário: " . $stmt->error);
    echo json_encode([
        "success" => false,
        "message" => "Erro ao cadastrar usuário.",
        "error" => $stmt->error
    ]);
}

// ✅ Finaliza
$stmt->close();
$stmt_check->close();
$mysqli->close();
?>
