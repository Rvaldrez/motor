<?php
// IMPORTANTE: Desabilitar exibição de erros/avisos no início
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once 'conexao_bd.php';
require_once 'helpers/email_proposta.php';

// Define o header JSON no início
header('Content-Type: application/json; charset=UTF-8');

// Criar um buffer de saída para capturar qualquer output indesejado
ob_start();

try {
    // Validar código de convite primeiro
    $codigo_convite = $_POST['codigo_convite'] ?? '';

    if (empty($codigo_convite)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Código de convite não fornecido.']);
        exit;
    }

    // Verificar se o código de convite é válido
    $stmt = $mysqli->prepare("SELECT id FROM codigos_convite WHERE codigo = ? AND valido_ate > NOW()");
    if (!$stmt) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Erro ao preparar consulta.']);
        exit;
    }
    
    $stmt->bind_param("s", $codigo_convite);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows == 0) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Código de convite inválido ou expirado.']);
        exit;
    }
    $stmt->close();

    function gerarToken() {
        return str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    function validarCPF($cpf) {
        $cpf = preg_replace('/\D/', '', $cpf);
        if (strlen($cpf) != 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
        
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) return false;
        }
        return true;
    }

    // Coleta dados
    $nome = $_POST['nome'] ?? '';
    $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
    $email = $_POST['email'] ?? '';
    $celular = $_POST['celular'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $cep = $_POST['cep'] ?? '';
    $endereco = $_POST['endereco'] ?? '';
    $numero = $_POST['numero'] ?? '';
    $complemento = $_POST['complemento'] ?? '';
    $cidade = $_POST['cidade'] ?? '';
    $estado = $_POST['estado'] ?? '';
    $residencia = $_POST['residencia'] ?? '';
    $renda = $_POST['renda'] ?? '';
    $comprou_particulares = $_POST['comprou_particulares'] ?? '';
    $investe_frequente = $_POST['investe_frequente'] ?? '';
    $termo_aceito = $_POST['termo_aceito'] ?? '0';

    // Validações
    if (!validarCPF($cpf)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'CPF inválido.']);
        exit;
    }

    // ======= NOVA LÓGICA DE VERIFICAÇÃO DE DUPLICIDADE =======
    
    // Verifica se CPF ou email já existe
    $stmt = $mysqli->prepare("SELECT id, nome, email, cpf, status_confirmacao, tipo FROM usuarios WHERE cpf = ? OR email = ?");
    if (!$stmt) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Erro ao verificar duplicidade.']);
        exit;
    }
    
    $stmt->bind_param("ss", $cpf, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
// Verificar se é cadastro pendente
if ($user['status_confirmacao'] == 'pendente') {
    // Gerar novo token
    $novo_token = gerarToken();
    
    // Atualizar o token no banco E GARANTIR QUE O TIPO SEJA 'investidor'
    $stmt_update = $mysqli->prepare("UPDATE usuarios SET token_confirmacao = ?, tipo = 'investidor' WHERE id = ?");
    $stmt_update->bind_param("si", $novo_token, $user['id']);
            $stmt_update->execute();
            $stmt_update->close();
            
            // Preparar email com novo código
            $nomePrimeiro = explode(' ', $user['nome'])[0];
            $mensagem = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; border:1px solid #ccc; background-color: #fff'>
                    <div style='background:#1A1A1A;padding:20px;text-align:center'>
                        <img src='https://motorgo.co/imagens/logo_motorgo_blk.png' alt='MotorGo' style='max-width:180px' />
                    </div>
                    <div style='padding: 30px'>
                        <h2 style='color:#B22222;'>Novo Código de Confirmação</h2>
                        <p>Olá <strong>$nomePrimeiro</strong>,</p>
                        <p>Você já iniciou um cadastro anteriormente. Aqui está seu novo código de verificação:</p>
                        <div style='text-align:center; background:#f4f4f4; padding:20px; margin:20px 0; border-radius:8px;'>
                            <h1 style='color:#B22222; letter-spacing:5px; margin:0;'>$novo_token</h1>
                        </div>
                        <p>Digite este código no site para concluir seu cadastro como investidor.</p>
                        <p style='color:#666; font-size:14px; margin-top:30px;'>
                            Se você não solicitou este código, ignore este email.
                        </p>
                    </div>
                    <div style='background:#f4f4f4; padding:15px; text-align:center; font-size:12px; color:#666;'>
                        © 2025 MotorGo - Todos os direitos reservados
                    </div>
                </div>
            ";
            
            // Enviar email
            @enviarEmailProposta($user['email'], $user['nome'], "Novo Código de Confirmação - MotorGo", $mensagem);
            
            ob_clean();
            
            // Retornar resposta para redirecionar à confirmação
            echo json_encode([
                'success' => true,
                'redirect_confirmacao' => true,
                'usuario_id' => $user['id'],
                'email' => $user['email'],
                'token' => $novo_token,
                'message' => 'Você já iniciou um cadastro. Enviamos um novo código para seu email.'
            ]);
            exit;
            
        } else {
            // Já confirmado - mandar fazer login
            ob_clean();
            
            // Determinar mensagem baseada no que está duplicado
            if ($user['cpf'] == $cpf && $user['email'] == $email) {
                $mensagem = "Este CPF e email já estão cadastrados. Faça login para continuar.";
            } elseif ($user['cpf'] == $cpf) {
                $mensagem = "Este CPF já está cadastrado. Faça login para continuar.";
            } else {
                $mensagem = "Este email já está cadastrado. Faça login para continuar.";
            }
            
            echo json_encode([
                'success' => false,
                'redirect_login' => true,
                'message' => $mensagem
            ]);
            exit;
        }
    }
    $stmt->close();

    // ======= CONTINUA COM O CADASTRO NORMAL SE NÃO HOUVER DUPLICIDADE =======

    // Gera token e hash da senha
    $token = gerarToken();
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

    // Insere no banco
    $stmt = $mysqli->prepare("INSERT INTO usuarios 
        (nome, email, celular, cpf, cep, endereco, numero, complemento, cidade, estado, 
         senha, token_confirmacao, status_confirmacao, tipo, residencia, renda, 
         comprou_particulares, investe_frequente, termo_aceito, status_cadastro) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', 'investidor', ?, ?, ?, ?, ?, 'incompleto')");

    if (!$stmt) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Erro ao preparar inserção: ' . $mysqli->error]);
        exit;
    }

    $stmt->bind_param("ssssssssssssssssi",
        $nome, $email, $celular, $cpf, $cep, $endereco, $numero, $complemento,
        $cidade, $estado, $senha_hash, $token, $residencia, $renda,
        $comprou_particulares, $investe_frequente, $termo_aceito
    );

    if ($stmt->execute()) {
        $usuario_id = $mysqli->insert_id;
        
        // Enviar email com token
        $nomePrimeiro = explode(' ', $nome)[0];
        $mensagem = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; border:1px solid #ccc; background-color: #fff'>
                <div style='background:#1A1A1A;padding:20px;text-align:center'>
                    <img src='https://motorgo.co/imagens/logo_motorgo_blk.png' alt='MotorGo' style='max-width:180px' />
                </div>
                <div style='padding: 30px'>
                    <h2 style='color:#B22222;'>Confirmação de Cadastro</h2>
                    <p>Olá <strong>$nomePrimeiro</strong>,</p>
                    <p>Seu código de verificação é:</p>
                    <div style='text-align:center; background:#f4f4f4; padding:20px; margin:20px 0; border-radius:8px;'>
                        <h1 style='color:#B22222; letter-spacing:5px; margin:0;'>$token</h1>
                    </div>
                    <p>Digite este código no site para concluir seu cadastro como investidor.</p>
                    <p style='color:#666; font-size:14px; margin-top:30px;'>
                        Este código é válido por 30 minutos. Se você não solicitou este cadastro, ignore este email.
                    </p>
                </div>
                <div style='background:#f4f4f4; padding:15px; text-align:center; font-size:12px; color:#666;'>
                    © 2025 MotorGo - Todos os direitos reservados
                </div>
            </div>
        ";
        
        $emailEnviado = @enviarEmailProposta($email, $nome, "Código de Confirmação - MotorGo", $mensagem);
        
        ob_clean();
        
        if ($emailEnviado) {
            echo json_encode([
                'success' => true,
                'usuario_id' => $usuario_id,
                'token' => $token,
                'message' => 'Cadastro realizado com sucesso!'
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'usuario_id' => $usuario_id,
                'token' => $token,
                'message' => 'Cadastro realizado, mas houve erro no envio do email.'
            ]);
        }
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar: ' . $stmt->error]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    ob_clean();
    error_log("Erro em salvar_investidor_v2.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao processar cadastro.']);
}

ob_end_flush();
?>