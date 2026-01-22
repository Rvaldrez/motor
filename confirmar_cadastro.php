<?php
session_start();
require_once "conexao_bd.php";

// Verifica se o usuário está logado e precisa confirmar
if (!isset($_SESSION['usuario_id']) || 
    (!isset($_SESSION['precisa_confirmar_codigo']) && !isset($_SESSION['precisa_novo_codigo']))) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$reenviar = isset($_GET['reenviar']) || isset($_SESSION['precisa_novo_codigo']);
$mensagem_sucesso = false;

// Se precisa reenviar automaticamente
if ($reenviar) {
    // Gera novo código automaticamente
    require_once "helpers/email_proposta.php";
    
    $codigo = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $expira = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    
    $stmt = $mysqli->prepare("UPDATE usuarios SET token_confirmacao = ?, token_expira = ? WHERE id = ?");
    $stmt->bind_param("ssi", $codigo, $expira, $usuario_id);
    $stmt->execute();
    
    // Busca dados para email
    $res = $mysqli->query("
        SELECT u.nome, u.email, v.marca, v.modelo 
        FROM usuarios u 
        LEFT JOIN veiculos v ON u.id = v.usuario_id 
        WHERE u.id = $usuario_id
        ORDER BY v.id DESC LIMIT 1
    ");
    
    if ($res && $res->num_rows > 0) {
        $dados = $res->fetch_assoc();
        $mensagem = criarEmailConfirmacao($dados['nome'], $dados['marca'] ?? '', $dados['modelo'] ?? '', $codigo);
        enviarEmailProposta($dados['email'], $dados['nome'], "🔐 Novo Código de Confirmação - MotorGo", $mensagem);
        $mensagem_sucesso = true;
    }
    
    unset($_SESSION['precisa_novo_codigo']);
}

unset($_SESSION['precisa_confirmar_codigo']);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Cadastro - MotorGo</title>
    <link rel="stylesheet" href="style_veiculos.css">
    <style>
        body {
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        
        .confirmacao-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 90%;
            text-align: center;
        }
        
        .logo {
            max-width: 200px;
            margin-bottom: 30px;
        }
        
        h2 {
            color: #333;
            margin-bottom: 20px;
        }
        
        #codigoVerificacao {
            text-align: center;
            font-size: 22px !important;
            letter-spacing: 5px !important;
            width: 380px !important;
            max-width: 100%;
            padding: 15px !important;
            border: 2px solid #ddd;
            border-radius: 8px;
            margin: 20px auto !important;
            display: block !important;
            font-family: monospace;
        }
        
        .btn-confirmar {
            background: linear-gradient(135deg, #B22222 0%, #8B0000 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 20px;
        }
        
        .btn-reenviar {
            background: transparent;
            color: #B22222;
            border: 2px solid #B22222;
            padding: 10px 30px;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            margin-top: 15px;
        }
        
        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
            color: #666;
        }
        
        .logout-link {
            margin-top: 20px;
            display: inline-block;
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }
        
        .logout-link:hover {
            color: #B22222;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="confirmacao-container">
        <img src="imagens/logo_motorgo.png" alt="MotorGo" class="logo">
        
        <h2>Confirme seu Cadastro</h2>
        
        <?php if ($mensagem_sucesso): ?>
            <div class="info-box" style="background: #d4edda; color: #155724;">
                ✅ Um novo código foi enviado para seu email!
            </div>
        <?php endif; ?>
        
        <p>Digite o código de 6 dígitos enviado para o seu e-mail:</p>
        
        <form id="formConfirmacao" method="POST">
            <input type="text" id="codigoVerificacao" name="codigo" 
                   placeholder="Código Verificação" maxlength="6" required>
            
            <button type="submit" class="btn-confirmar">Confirmar Código</button>
        </form>
        
        <div style="margin-top: 30px;">
            <p style="color: #666; font-size: 14px; margin-bottom: 10px;">
                Não recebeu o código ou expirou?
            </p>
            <button type="button" id="btnReenviar" class="btn-reenviar">
                Reenviar Código
            </button>
        </div>
        
        <a href="logout.php" class="logout-link">Sair e tentar mais tarde</a>
    </div>
    
    <!-- Popup de mensagem -->
    <div id="popupMensagem" class="popup-mensagem" style="display: none;">
        <div class="popup-conteudo">
            <span id="popupTexto"></span>
            <button onclick="fecharPopup()" class="btn-fechar">Fechar</button>
        </div>
    </div>
    
    <style>
        /* Estilos do Popup */
        .popup-mensagem {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            z-index: 9999;
            display: none; /* Sempre inicia escondido */
        }
        
        .popup-mensagem[style*="flex"] {
            display: flex !important; /* Só mostra quando JavaScript definir */
        }
        
        .popup-conteudo {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            width: 90%;
            text-align: center;
            position: relative;
        }
        
        .popup-conteudo span {
            display: block;
            margin-bottom: 20px;
            font-size: 16px;
            color: #333;
            line-height: 1.5;
        }
        
        .btn-fechar {
            background: #B22222;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .btn-fechar:hover {
            background: #8B0000;
        }
    </style>
    
    <script>
        function mostrarPopup(mensagem, callback = null) {
            const popup = document.getElementById('popupMensagem');
            const popupTexto = document.getElementById('popupTexto');
            
            if (!popup || !popupTexto) {
                alert(mensagem);
                return;
            }
            
            popupTexto.innerHTML = mensagem.replace(/\n/g, "<br>");
            popup.style.display = 'flex';
            window.popupCallback = callback;
        }
        
        function fecharPopup() {
            const popup = document.getElementById('popupMensagem');
            if (popup) {
                popup.style.display = 'none';
            }
            if (window.popupCallback) {
                window.popupCallback();
                window.popupCallback = null;
            }
        }
        
        // Máscara do código
        document.getElementById('codigoVerificacao').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });
        
        // Formulário de confirmação
        document.getElementById('formConfirmacao').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const codigo = document.getElementById('codigoVerificacao').value;
            if (codigo.length !== 6) {
                mostrarPopup("⚠️ Digite o código de 6 dígitos.");
                return;
            }
            
            try {
                const response = await fetch('confirmar_codigo.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        usuario_id: <?php echo $usuario_id; ?>,
                        codigo: codigo
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    mostrarPopup("🎉 " + data.message, () => {
                        window.location.href = 'painel_veiculos.php';
                    });
                } else {
                    mostrarPopup("❌ " + data.message);
                }
            } catch (error) {
                mostrarPopup("❌ Erro ao verificar código. Tente novamente.");
            }
        });
        
        // Botão reenviar
        document.getElementById('btnReenviar').addEventListener('click', async function() {
            this.disabled = true;
            this.textContent = 'Enviando...';
            
            try {
                const response = await fetch('reenviar_codigo.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        usuario_id: <?php echo $usuario_id; ?>
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    mostrarPopup("✅ " + data.message);
                    document.getElementById('codigoVerificacao').value = '';
                    document.getElementById('codigoVerificacao').focus();
                } else {
                    mostrarPopup("❌ " + data.message);
                }
            } catch (error) {
                mostrarPopup("❌ Erro ao reenviar código.");
            } finally {
                this.disabled = false;
                this.textContent = 'Reenviar Código';
            }
        });
    </script>
</body>
</html>

<?php
/**
 * Email de confirmação reutilizável
 */
function criarEmailConfirmacao($nome, $marca, $modelo, $codigo) {
    $veiculo_info = '';
    if ($marca && $modelo) {
        $veiculo_info = " para seu veículo <strong>$marca $modelo</strong>";
    }
    
    return "
    <div style='font-family:Arial,sans-serif; max-width:600px; margin:0 auto; background:#ffffff;'>
        <!-- Header com logo -->
        <div style='background:#1a1a1a; color:white; padding:30px 20px; text-align:center;'>
            <img src='https://motorgo.co/imagens/logo_motorgo.png' alt='MotorGo' style='max-width:200px; height:auto; margin-bottom:15px;'>
            <h1 style='margin:0; font-size:28px; color:#ffffff;'>✅ Quase lá!</h1>
        </div>
        
        <div style='padding:30px; background:#f8f9fa;'>
            <p style='font-size:18px; margin-bottom:20px;'>Olá <strong>{$nome}</strong>,</p>
            <p style='font-size:16px; line-height:1.6; margin-bottom:25px;'>Seu cadastro{$veiculo_info} está quase pronto para ser publicado!</p>
            
            <div style='background:white; border:2px solid #28a745; padding:25px; margin:25px 0; text-align:center; border-radius:8px;'>
                <p style='margin:0 0 10px 0; font-size:16px; color:#666;'>Seu código de confirmação é:</p>
                <h2 style='margin:10px 0; font-size:42px; letter-spacing:8px; color:#333; font-family:monospace;'>{$codigo}</h2>
                <p style='margin:10px 0 0 0; font-size:14px; color:#999;'>Válido por 30 minutos</p>
            </div>
            
            <p style='font-size:16px; line-height:1.6; margin:25px 0;'>Digite este código na tela de confirmação para ativar seu anúncio.</p>
            
            <div style='background:#e8f5e8; border:1px solid #d4edda; padding:15px; border-radius:6px; margin:25px 0;'>
                <p style='margin:0; color:#155724; font-size:14px;'>
                    🔒 <strong>Segurança:</strong> Este código garante que apenas você pode ativar o anúncio.
                </p>
            </div>
        </div>
        
        <!-- Footer -->
        <div style='background:#1a1a1a; color:#999; padding:20px; text-align:center; font-size:12px;'>
            <p style='margin:0 0 10px 0;'>© " . date('Y') . " MotorGo - Mais valor para você!</p>
            <p style='margin:0;'>
                <a href='https://motorgo.com.br' style='color:#B22222; text-decoration:none;'>www.motorgo.com.br</a> | 
                <a href='mailto:sac@motorgo.co' style='color:#B22222; text-decoration:none;'>sac@motorgo.co</a>
            </p>
        </div>
    </div>";
}
?>