<?php
if (!isset($_SESSION)) session_start();
include 'conexao_bd.php';

$usuario_id = $_SESSION['usuario_id'] ?? null;
$msg = '';
$msg_class = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_SESSION['usuario_tipo'] ?? '') === 'vendedor') {
    $residencia = $_POST['residencia'] ?? '';
    $renda = $_POST['renda'] ?? '';
    $comprou_particulares = $_POST['comprou_particulares'] ?? '';
    $investe_frequente = $_POST['investe_frequente'] ?? '';

    $stmt = $mysqli->prepare("
        UPDATE usuarios 
        SET residencia = ?, renda = ?, comprou_particulares = ?, investe_frequente = ?, tipo = 'investidor'
        WHERE id = ?
    ");
    $stmt->bind_param("ssssi", $residencia, $renda, $comprou_particulares, $investe_frequente, $usuario_id);

    if ($stmt->execute()) {
        $_SESSION['usuario_tipo'] = 'investidor';
        $msg = "✅ Perfil de investidor ativado com sucesso!";
        $msg_class = 'success';
    } else {
        $msg = "❌ Erro ao atualizar cadastro: " . $stmt->error;
        $msg_class = 'error';
    }

    $stmt->close();
}
?>

<div class="formulario">
  <h2>Ativar Perfil de Investidor</h2>
  <p>Complete os dados abaixo para ativar seu perfil como investidor e acessar veículos disponíveis para compra.</p>

  <?php if (!empty($msg)): ?>
    <div class="popup-mensagem <?= htmlspecialchars($msg_class) ?>">
      <p><?= htmlspecialchars($msg) ?></p>
    </div>
  <?php endif; ?>

  <form method="POST" action="">
    <label for="residencia">Tipo de Residência</label>
    <select name="residencia" id="residencia" required>
        <option value="">Escolha</option>
        <option value="propria">Própria</option>
        <option value="alugada">Alugada</option>
        <option value="financiada">Financiada</option>
    </select>

    <label for="renda">Faixa de Renda</label>
    <select name="renda" id="renda" required>
        <option value="">Escolha</option>
        <option value="ate_5000">Até R$ 5.000,00</option>
        <option value="5001_a_10000">R$ 5.001,00 a R$ 10.000,00</option>
        <option value="10001_a_20000">R$ 10.001,00 a R$ 20.000,00</option>
        <option value="acima_20000">Acima de R$ 20.000,00</option>
    </select>

    <label for="comprou_particulares">Já comprou veículos de particulares?</label>
    <select name="comprou_particulares" id="comprou_particulares" required>
        <option value="">Escolha</option>
        <option value="sim">Sim</option>
        <option value="nao">Não</option>
    </select>

    <label for="investe_frequente">Faz investimentos com frequência?</label>
    <select name="investe_frequente" id="investe_frequente" required>
        <option value="">Escolha</option>
        <option value="sim">Sim</option>
        <option value="nao">Não</option>
    </select>

    <button type="submit" class="btn-avancar">Ativar Perfil Investidor</button>
  </form>
</div>
