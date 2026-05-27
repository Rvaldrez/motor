<?php
session_start();

$usuarioLogado = isset($_SESSION['usuario_id']);
$tipoUsuario = strtolower(trim($_SESSION['usuario_tipo'] ?? ''));
$statusConfirmacao = strtolower(trim($_SESSION['status_confirmacao'] ?? ''));

if ($usuarioLogado && $tipoUsuario === 'investidor' && $statusConfirmacao === 'pendente') {
    $_SESSION['precisa_confirmar_codigo'] = true;
    header("Location: confirmar_cadastro.php");
    exit;
}

if ($usuarioLogado && $tipoUsuario === 'investidor' && $statusConfirmacao === 'confirmado') {
    header("Location: painel_veiculos.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Investidor - MotorGo</title>
    <link rel="stylesheet" href="style_veiculos.css">
</head>
<body style="background:#f5f5f5;">
<?php if ($usuarioLogado && $tipoUsuario === 'vendedor'): ?>
    <main class="main-content" style="max-width:720px;margin:40px auto;">
        <div style="margin-bottom:16px;">
            <a href="painel_veiculos.php" class="btn-vermelho" style="text-decoration:none;display:inline-block;">Voltar ao painel</a>
        </div>
        <?php include 'secao_ativar_investidor.php'; ?>
    </main>
<?php else: ?>
    <main class="main-content" style="max-width:720px;margin:40px auto;background:#fff;padding:32px;border-radius:12px;">
        <h2 style="margin-top:0;">Cadastro de Investidor</h2>
        <p>Para seguir como investidor, faça login ou inicie seu cadastro.</p>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <a href="login.php" class="btn-vermelho" style="text-decoration:none;display:inline-block;">Entrar</a>
            <a href="invista_em_carros.php#form" class="btn-vermelho" style="text-decoration:none;display:inline-block;">Quero me cadastrar</a>
        </div>
    </main>
<?php endif; ?>
</body>
</html>
