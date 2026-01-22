<?php
require_once "conexao_bd.php";
session_start();

// ✅ Validação de sessão
if (!isset($_SESSION['usuario_id'])) {
    exit("<p style='color: red;'>Acesso negado.</p>");
}

  
  $usuario_id = $_SESSION['usuario_id'];
  $usuario_tipo = $_SESSION['usuario_tipo'];



// ✅ Consulta principal com exclusão de propostas do histórico e recusadas pelo próprio vendedor
$sql = "SELECT p.*, u.nome AS nome_comprador, u.email AS email_comprador, u.celular AS celular_comprador,
       v.modelo, v.ano_fabrica, v.marca, v.quilometragem, v.preco, f.caminho_foto
FROM propostas p
JOIN usuarios u ON u.id = p.usuario_id
JOIN veiculos v ON v.id = p.veiculo_id
LEFT JOIN (
    SELECT veiculo_id, MIN(ordem_exibicao) AS ordem, caminho_foto
    FROM fotos_veiculos
    GROUP BY veiculo_id
) f ON f.veiculo_id = v.id
WHERE p.id IN (
    SELECT MAX(id)
    FROM propostas
    WHERE status NOT IN (
        'historico',
        'historico_recusada',
        'recusada_vendedor',
        'historico_recusada_vendedor'
    )
    GROUP BY veiculo_id
)
AND v.usuario_id = ?
ORDER BY p.data_proposta DESC";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

$temPropostasVisiveis = false;
$cardsHtml = '';

while ($p = $result->fetch_assoc()):
    $statusOrig     = htmlspecialchars($p['status']);
    $propostaId     = $p['id'];
    $foto           = htmlspecialchars($p['caminho_foto'] ?? 'imagens/default_car.png');
    $valorProposta  = number_format($p['valor'], 2, ',', '.');

    // ✅ Se o investidor recusou, mostra o card com botão OK
    if ($statusOrig === 'recusada_investidor') {
        $temPropostasVisiveis = true;
        ob_start();
        ?>
        <div class="card-proposta-recebida status-recusada" data-id="<?= $propostaId ?>">
            <img src="<?= $foto ?>" class="imagem-veiculo" alt="Foto do veículo" />
            <div class="info-veiculo">
            <h3><?= htmlspecialchars($p['modelo']) ?></h3>
           <p><strong>Marca:</strong> <?= htmlspecialchars($p['marca']) ?></p>
           <p><strong>Ano Modelo:</strong> <?= htmlspecialchars($p['ano_fabrica']) ?></p>
                <p><strong>KM:</strong> <?= number_format($p['quilometragem'], 0, '', '.') ?> km</p>
                <p><strong>Valor FIPE:</strong> R$ <?= number_format($p['preco'], 2, ',', '.') ?></p>
                <p><strong>Oferta Recusada:</strong> R$ <?= $valorProposta ?></p>
            </div>
            <div class="mensagem-recusa">
                <img src="imagens/cancela.png" alt="Ícone de recusa" />
                <div class="mensagem-texto">
                <p><strong>Sua contraproposta foi recusada.</strong> Em breve você receberá novas propostas.</p>
                </div>
                <div style="margin-top: 10px; text-align: center;">
                    
                </div>
            </div>
            <button class="btn-ok-recusa" data-id="<?= $propostaId ?>">Ok</button>
        </div>
        <?php
        $cardsHtml .= ob_get_clean();
        continue;
    }

    $temPropostasVisiveis = true;
    $statusClass = str_replace('_', '-', $statusOrig);

    ob_start();
    ?>
    <div class="card-proposta-recebida status-<?= $statusClass ?>" data-id="<?= $propostaId ?>">
        <img src="<?= $foto ?>" class="imagem-veiculo" alt="Foto do veículo" />

        <div class="info-veiculo">
        <h3><?= htmlspecialchars($p['modelo']) ?></h3>
        <p><strong>Marca:</strong> <?= htmlspecialchars($p['marca']) ?></p>
        <p><strong>Ano Modelo:</strong> <?= htmlspecialchars($p['ano_fabrica']) ?></p>
            <p><strong>KM:</strong> <?= number_format($p['quilometragem'], 0, '', '.') ?> km</p>
            <p><strong>Valor FIPE:</strong> R$ <?= number_format($p['preco'], 2, ',', '.') ?></p>
            <p><strong>Proposta Atual:</strong> <span style="color:#900;"><strong>R$ <?= $valorProposta ?></strong></span></p>

            <?php
            $statusMsg = '';
            if ($statusOrig === 'aguardando_comprador') {
                $statusMsg = 'Aguardando retorno do comprador';
            } elseif (in_array($statusOrig, ['aguardando_vendedor', 'pendente', 'contraproposta_comprador'])) {
                $statusMsg = 'Aguardando sua resposta';
            }

            if ($statusMsg): ?>
                <p class="status-aguardando">Status: <span class="laranja"><?= $statusMsg ?></span></p>
            <?php endif; ?>
        </div>

        <?php
        $exibirBotoes = in_array($statusOrig, ['aguardando_vendedor', 'contraproposta_comprador']);
        ?>

        <?php if ($exibirBotoes): ?>
        <div class="acoes-proposta">
            <button class="btn-aceitar" data-id="<?= $propostaId ?>">Aceitar</button>
            <button class="btn-negociar" data-id="<?= $propostaId ?>">Negociar</button>
            <button class="btn-recusar" data-id="<?= $propostaId ?>">Recusar</button>
        </div>

        <div class="form-negociacao" id="negociacao<?= $propostaId ?>">
            <input type="text" id="valorNegociado<?= $propostaId ?>" class="mascara-valor" placeholder="R$ 0,00" />
            <div class="texto-alerta-negociacao">
                <img src="imagens/alerta.png" alt="Alerta" />
                <p>Envie uma contraproposta. A outra parte poderá aceitar ou negociar novamente.</p>
            </div>
            <div class="botoes-negociacao">
            <button class="btn-enviar-contraproposta" data-id="<?= $propostaId ?>">Enviar</button>
            <button class="btn-cancelar" data-id="<?= $propostaId ?>">Cancelar</button>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($statusOrig === 'aceita'): ?>
    <div class="mensagem-aceita-vendedor">
        <img src="imagens/check.png" alt="Ícone de check" />
        <div class="mensagem-texto">
        <p><strong>Parabéns!</strong> Sua proposta foi aceita. Entre em contato com o comprador:</p>
        </div>
    </div>

    <div class="contato-comprador">
        <p><strong>Nome:</strong> <?= htmlspecialchars($p['nome_comprador']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($p['email_comprador']) ?></p>
        <p><strong>Telefone:</strong> <?= htmlspecialchars($p['celular_comprador']) ?></p>

        <div class="botoes-contato">
            <a href="https://wa.me/55<?= preg_replace('/\D/', '', $p['celular_comprador']) ?>" target="_blank" class="btn-contato-whatsapp">
                <img src="imagens/wzap.png" class="icone-whatsapp" alt="WhatsApp" />
                WhatsApp
            </a>
            <button class="btn-contato-copiar"
                    onclick="copiarDadosComprador('<?= $p['nome_comprador'] ?>', '<?= $p['celular_comprador'] ?>', '<?= $p['email_comprador'] ?>')">
                Copiar Dados
            </button>
        </div>
    </div>
<?php endif; ?>

    </div>
    <?php
    $cardsHtml .= ob_get_clean();
endwhile;

if ($temPropostasVisiveis) {
    echo '<div class="propostas-recebidas-list">' . $cardsHtml . '</div>';
} else {
    echo '<div class="propostas-vazia"><p style="text-align: left; font-size: 16px; color: #555; margin-top: 20px;">Você não tem propostas recebidas no momento.</p></div>';
}
?>
