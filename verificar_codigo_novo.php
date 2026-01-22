<?php
session_start();
require_once "conexao_bd.php";

$cpf_email = trim($_POST['cpf_email'] ?? '');
$senha     = trim($_POST['senha'] ?? '');

function erroPopup($mensagem) {
    echo "
    <!DOCTYPE html>
    <html lang='pt-BR'>
    <head>
      <meta charset='UTF-8'>
      <title>Erro</title>
      <style>
        .popup-mensagem {
          display: flex;
          align-items: center;
          justify-content: center;
          position: fixed;
          top: 0; left: 0;
          width: 100%;
          height: 100%;
          background: rgba(0,0,0,0.5);
          z-index: 9999;
        }
        .popup-conteudo {
          background: #fff;
          padding: 30px;
          border-radius: 8px;
          text-align: center;
          box-shadow: 0 0 10px rgba(0,0,0,0.2);
          max-width: 400px;
        }
        .btn-fechar {
          margin-top: 15px;
          background: #b22222;
          color: #fff;
          border: none;
          padding: 10px 20px;
          cursor: pointer;
          border-radius: 4px;
        }
      </style>
    </head>
    <body>
      <div id='popupMensagem' class='popup-mensagem'>
        <div class='popup-conteudo'>
          <span id='popupTexto'>" . nl2br(htmlspecialchars($mensagem)) . "</span><br>
          <button onclick='fecharPopup()' class='btn-fechar'>Fechar</button>
        </div>
      </div>
      <script>
        function fecharPopup() {
          document.getElementById('popupMensagem').style.display = 'none';
          window.history.back();
        }
      </script>
    </body>
    </html>";
    exit;
}

// 🔒 Verifica campos obrigatórios
if (empty($cpf_email) || empty($senha)) {
    erroPopup("⚠️ Preencha todos os campos obrigatórios.");
}

// 🔍 Busca usuário por e-mail ou CPF
$stmt = $mysqli->prepare("
    SELECT u.id, u.nome, u.senha, u.tipo, u.status_cadastro, u.status_confirmacao,
           v.id as veiculo_id, v.status as veiculo_status,
           COUNT(f.id) as total_fotos
    FROM usuarios u
    LEFT JOIN veiculos v ON u.id = v.usuario_id
    LEFT JOIN fotos_veiculos f ON v.id = f.veiculo_id
    WHERE u.email = ? OR u.cpf = ?
    GROUP BY u.id, v.id
    ORDER BY v.id DESC
    LIMIT 1
");
$stmt->bind_param("ss", $cpf_email, $cpf_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    erroPopup("❌ Usuário não encontrado.");
}

$usuario = $result->fetch_assoc();

// 🔑 Valida senha
if (!password_verify($senha, $usuario['senha'])) {
    erroPopup("❌ Senha incorreta.");
}

// 🧠 Normaliza campos
$tipo = strtolower(trim($usuario['tipo']));
$status_cadastro = strtolower(trim($usuario['status_cadastro']));
$status_confirmacao = strtolower(trim($usuario['status_confirmacao']));
$veiculo_status = strtolower(trim($usuario['veiculo_status'] ?? ''));
$total_fotos = intval($usuario['total_fotos']);

// ✅ Salva sessão
$_SESSION['usuario_id']   = $usuario['id'];
$_SESSION['usuario_nome'] = $usuario['nome'];
$_SESSION['usuario_tipo'] = $tipo;
$_SESSION['status_cadastro'] = $status_cadastro;

// 🚧 VERIFICAÇÕES DE CADASTRO INCOMPLETO

// 1. Se o cadastro está incompleto (Etapa 1 ou 2 não finalizadas)
if (in_array($status_cadastro, ['incompleto', 'incompleto1'])) {
    session_write_close();
    header("Location: finalizar_cadastro.php?usuario_id=" . $usuario['id']);
    exit;
}

// 2. Se tem veículo mas sem fotos (precisa completar Etapa 3)
if ($usuario['veiculo_id'] && $total_fotos < 6 && $veiculo_status === 'incompleto') {
    session_write_close();
    header("Location: finalizar_cadastro.php?id=" . $usuario['veiculo_id']);
    exit;
}

// 3. Se enviou fotos mas não confirmou o código
if ($status_confirmacao === 'pendente' && $total_fotos >= 6) {
    // Verifica se o token ainda é válido
    $stmt = $mysqli->prepare("
        SELECT token_expira FROM usuarios 
        WHERE id = ? AND token_expira > NOW()
    ");
    $stmt->bind_param("i", $usuario['id']);
    $stmt->execute();
    $token_result = $stmt->get_result();
    
    if ($token_result->num_rows > 0) {
        // Token ainda válido - redireciona para tela de confirmação
        $_SESSION['precisa_confirmar_codigo'] = true;
        session_write_close();
        header("Location: confirmar_cadastro.php");
        exit;
    } else {
        // Token expirado - gera novo código e redireciona
        $_SESSION['precisa_novo_codigo'] = true;
        session_write_close();
        header("Location: confirmar_cadastro.php?reenviar=1");
        exit;
    }
}

// ✅ Redireciona conforme tipo de usuário
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
        erroPopup("⚠️ Tipo de usuário inválido.");
}
?>