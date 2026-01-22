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
$stmt = $mysqli->prepare("SELECT id, nome, email, senha, tipo, status_cadastro, status_confirmacao, token_expira FROM usuarios WHERE email = ? OR cpf = ?");
$stmt->bind_param("ss", $cpf_email, $cpf_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    erroPopup("❌ Usuário não encontrado.");
}

$usuario = $result->fetch_assoc();

// 🔑 Valida senha
if (!password_verify($senha, $usuario['senha'])) {
    erroPopup("❌ Senha incorreta.");
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
    // O formulário vai direto para etapa 4 (confirmação) e gera novo token
    header("Location: cadastro_investidor.php?continuar=true&email=" . urlencode($usuario['email']) . "&id=" . $usuario['id']);
    exit;
}

// 🚧 Redireciona para finalizar APENAS se for vendedor com cadastro incompleto
if ($tipo === 'vendedor' && in_array($status, ['incompleto', 'incompleto1'])) {
    session_write_close();
    header("Location: finalizar_cadastro.php?usuario_id=" . $usuario['id']);
    exit;
}

// 🔐 VERIFICAÇÃO PARA VENDEDOR: Se cadastro completo mas confirmação pendente
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
        erroPopup("⚠️ Tipo de usuário inválido ou cadastro incompleto.");
}
?>