<?php
require_once "conexao_bd.php";
session_start();

if (!isset($_SESSION['usuario_id'])) {
    exit("<p style='color: red;'>Acesso negado.</p>");
}

$usuario_id = (int) $_SESSION['usuario_id'];

// ✅ Consulta completa
$sql = "SELECT 
    p.*, 
    p.aceita_por, 
    p.usuario_id AS id_comprador,
    v.usuario_id AS id_vendedor,
    uc.nome AS nome_comprador, uc.email AS email_comprador, uc.celular AS celular_comprador,
    uv.nome AS nome_vendedor, uv.email AS email_vendedor, uv.celular AS celular_vendedor,
    uv.cidade AS cidade_vendedor, uv.estado AS estado_vendedor,
    v.modelo, v.ano_fabrica, v.marca, v.quilometragem, v.preco, f.caminho_foto
FROM propostas p
JOIN veiculos v ON v.id = p.veiculo_id
JOIN usuarios uc ON uc.id = p.usuario_id         -- comprador
JOIN usuarios uv ON uv.id = v.usuario_id         -- vendedor
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
AND (v.usuario_id = ? OR p.usuario_id = ?)
ORDER BY p.data_proposta DESC";




$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ii", $usuario_id, $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

$temPropostasVisiveis = false;
$cardsHtml = '';

while ($p = $result->fetch_assoc()):
        // Se o usuário for o comprador, não exibe a proposta neste arquivo
        if ((int)$p['id_comprador'] === $usuario_id) {
            continue;
        }
    $idAceitante = (int) $p['aceita_por'];
    $statusOrig = htmlspecialchars($p['status']);
    $propostaId = $p['id'];
    $foto = htmlspecialchars($p['caminho_foto'] ?? 'imagens/default_car.png');
    $valorProposta = number_format($p['valor'], 2, ',', '.');
    $mostrarAcoes = in_array($statusOrig, ['aguardando_vendedor', 'contraproposta_comprador', 'pendente'], true);

    $temPropostasVisiveis = true;

    ob_start();
?>
<div class="card-proposta-recebida status-<?= str_replace('_', '-', $statusOrig) ?>" data-id="<?= $propostaId ?>">
    <img src="<?= $foto ?>" class="imagem-veiculo" alt="Foto do veículo" />

    <div class="info-veiculo">
        <h3><?= htmlspecialchars($p['modelo']) ?></h3>
        <p><strong>Marca:</strong> <?= htmlspecialchars($p['marca']) ?></p>
        <p><strong>Ano Modelo:</strong> <?= htmlspecialchars($p['ano_fabrica']) ?></p>
        <p><strong>KM:</strong> <?= number_format($p['quilometragem'], 0, '', '.') ?> km</p>
        <p><strong>Local:</strong> <?= htmlspecialchars($p['cidade_vendedor']) ?>/<?= htmlspecialchars($p['estado_vendedor']) ?></p>
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

    <?php if ($statusOrig === 'recusada_investidor'): ?>
        <div class="mensagem-recusa">
            <img src="imagens/cancela.png" alt="Ícone de recusa" />
            <div class="mensagem-texto">
                <p><strong>Sua contraproposta foi recusada.</strong> Em breve você receberá novas propostas.</p>
            </div>
        </div>
        <button class="btn-ok-recusa" data-id="<?= $propostaId ?>">Ok</button>
    <?php endif; ?>

    <?php if ($mostrarAcoes): ?>
        <div class="acoes-proposta">
            <button class="btn-aceitar" data-id="<?= $propostaId ?>">Aceitar</button>
            <button class="btn-negociar" data-id="<?= $propostaId ?>">Nova Proposta</button>
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
    





    <?php if ($statusOrig === 'aceita' && ((int)$usuario_id === (int)$p['id_vendedor'])): ?>
    <?php
    // Buscar os dados do comprador original da negociação
    $compradorOriginal = null;
    $stmtOrig = $mysqli->prepare("
        SELECT u.nome, u.email, u.celular
        FROM propostas po
        JOIN usuarios u ON u.id = po.usuario_id
        WHERE (po.id = ? OR po.id = (
            SELECT proposta_origem_id FROM propostas WHERE id = ?
        ))
        ORDER BY po.id ASC
        LIMIT 1
    ");
    $stmtOrig->bind_param("ii", $p['id'], $p['id']);
    $stmtOrig->execute();
    $resOrig = $stmtOrig->get_result();
    if ($resOrig && $rowOrig = $resOrig->fetch_assoc()) {
        $compradorOriginal = $rowOrig;
    }
    ?>

    <div class="mensagem-aceita-vendedor">
        <img src="imagens/check.png" alt="Ícone de check" />
        <div class="mensagem-texto">
            <p><strong>Parabéns!</strong> Sua proposta foi aceita. Entre em contato com o comprador:</p>
        </div>
    </div>

    <?php if ($compradorOriginal): ?>
    <div class="contato-comprador">
        <p><strong>Nome:</strong> <?= htmlspecialchars($compradorOriginal['nome']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($compradorOriginal['email']) ?></p>
        <p><strong>Telefone:</strong> <?= htmlspecialchars($compradorOriginal['celular']) ?></p>

        <div class="botoes-contato">
            <a href="https://wa.me/55<?= preg_replace('/\D/', '', $compradorOriginal['celular']) ?>" target="_blank" class="btn-contato-whatsapp">
                <img src="imagens/wzap.png" class="icone-whatsapp" alt="WhatsApp" />
                WhatsApp
            </a>
            <button class="btn-contato-copiar"
                    onclick="copiarDadosComprador('<?= $compradorOriginal['nome'] ?>', '<?= $compradorOriginal['celular'] ?>', '<?= $compradorOriginal['email'] ?>')">
                Copiar Dados
            </button>
        </div>
    </div>
    <?php endif; ?>
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
