<?php
session_start();
require_once "conexao_bd.php";
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["valido" => false, "erro" => "Método inválido."]);
    exit;
}

$cpf = trim($_POST['cpf'] ?? '');

if (empty($cpf)) {
    echo json_encode(["valido" => false, "erro" => "CPF é obrigatório."]);
    exit;
}

// 🔹 Função para validar CPF (algoritmo oficial)
function validarCPF($cpf) {
    // Remove caracteres não numéricos
    $cpf = preg_replace('/\D/', '', $cpf);
    
    // Verifica se tem 11 dígitos
    if (strlen($cpf) !== 11) {
        return false;
    }
    
    // Verifica se não é uma sequência de números iguais
    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    
    // Validação do primeiro dígito verificador
    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += intval($cpf[$i]) * (10 - $i);
    }
    $resto = $soma % 11;
    $digito1 = ($resto < 2) ? 0 : 11 - $resto;
    
    if (intval($cpf[9]) !== $digito1) {
        return false;
    }
    
    // Validação do segundo dígito verificador
    $soma = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma += intval($cpf[$i]) * (11 - $i);
    }
    $resto = $soma % 11;
    $digito2 = ($resto < 2) ? 0 : 11 - $resto;
    
    return intval($cpf[10]) === $digito2;
}

// 🔹 Validação do formato do CPF
if (!validarCPF($cpf)) {
    echo json_encode([
        "valido" => false, 
        "erro" => "CPF inválido. Verifique os números digitados."
    ]);
    exit;
}

// 🔹 Verificação no banco se CPF já existe
try {
    // Busca tanto pelo CPF formatado quanto pelo CPF limpo
    $cpfLimpo = preg_replace('/\D/', '', $cpf);
    
    $stmt = $mysqli->prepare("
        SELECT id, nome, email, status_cadastro 
        FROM usuarios 
        WHERE cpf = ? 
           OR REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Erro ao preparar consulta.");
    }
    
    $stmt->bind_param("ss", $cpf, $cpfLimpo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $usuario = $result->fetch_assoc();
        
        // Se o usuário está com cadastro incompleto, orienta melhor
        if ($usuario['status_cadastro'] === 'incompleto') {
            echo json_encode([
                "valido" => false,
                "erro" => "Olá {$usuario['nome']}! Você já iniciou seu cadastro.\n Faça login para continuar de onde parou.",
                "redirect" => true // Flag para o frontend saber que pode redirecionar
            ]);
        } else {
            echo json_encode([
                "valido" => false,
                "erro" => "Este CPF já está cadastrado. Faça login para acessar sua conta.",
                "redirect" => true
            ]);
        }
    } else {
        echo json_encode([
            "valido" => true,
            "mensagem" => "CPF válido."
        ]);
    }
    
} catch (Exception $e) {
    error_log("Erro verificar_cpf.php: " . $e->getMessage());
    echo json_encode([
        "valido" => false,
        "erro" => "Erro interno. Tente novamente em alguns instantes."
    ]);
}

$mysqli->close();
?>