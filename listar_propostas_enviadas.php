<?php
require_once "conexao_bd.php";
session_start();

// ✅ Segurança: somente investidor pode acessar
if (!isset($_SESSION['usuario_id'])) {
    exit("<p style='color: red;'>Acesso negado.</p>");
}

$usuario_id = $_SESSION['usuario_id'];

// ✅ Busca propostas enviadas
$sql = "SELECT p.*, 
       v.modelo, v.ano_fabrica, v.marca, v.quilometragem, v.preco, 
       f.caminho_foto, 
       u.nome AS nome_vendedor, u.email AS email_vendedor, u.celular AS celular_vendedor, u.cidade AS usuario_cidade,
u.estado AS usuario_estado

FROM propostas p
JOIN veiculos v ON v.id = p.veiculo_id
JOIN usuarios u ON u.id = v.usuario_id
LEFT JOIN (
    SELECT veiculo_id, MIN(ordem_exibicao) AS ordem, caminho_foto
    FROM fotos_veiculos
    GROUP BY veiculo_id
) f ON f.veiculo_id = v.id
WHERE p.id IN (
    SELECT MAX(id)
    FROM propostas
    WHERE veiculo_id IN (
        SELECT veiculo_id
        FROM propostas
        WHERE usuario_id = ?
    )
    AND status NOT IN (
        'historico',
        'historico_recusada',
        'historico_recusada_investidor',
        'historico_recusada_vendedor',
        'recusada_investidor'
      )
    GROUP BY veiculo_id
)
AND v.usuario_id != ? -- <-- garante que o usuário não seja o dono do veículo
AND p.status NOT IN (
    'historico',
    'historico_recusada',
    'historico_recusada_investidor',
    'historico_recusada_vendedor'
    
)
ORDER BY p.data_proposta DESC";


$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ii", $usuario_id, $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p>Nenhuma proposta enviada ainda.</p>";
    exit;
}

echo '<div class="propostas-recebidas-list">';

while ($p = $result->fetch_assoc()):
    $foto          = htmlspecialchars($p['caminho_foto'] ?? 'imagens/default_car.png');
    $valorProposta = number_format($p['valor'], 2, ',', '.');
    $statusOrig    = htmlspecialchars($p['status']);
    $statusClass   = str_replace('_', '-', $statusOrig);
    $propostaId    = $p['id'];
?>
<div class="card-proposta-recebida status-<?= $statusClass ?>" data-id="<?= $propostaId ?>" data-status="<?= $statusOrig ?>">
    <img src="<?= $foto ?>" class="imagem-veiculo" alt="Foto do veículo" />

    <div class="info-veiculo">
    <h3><?= htmlspecialchars($p['modelo']) ?></h3>
    <p><strong>Marca:</strong> <?= htmlspecialchars($p['marca']) ?></p>
    <p><strong>Ano Modelo:</strong> <?= htmlspecialchars($p['ano_fabrica']) ?></p>
        <p><strong>KM:</strong> <?= number_format($p['quilometragem'], 0, '', '.') ?> km</p>
       
       
        <?php if (!empty($p['usuario_cidade']) && !empty($p['usuario_estado'])): ?>
  <p><strong>Local:</strong> 
    <?= htmlspecialchars($p['usuario_cidade']) ?>/<?= htmlspecialchars($p['usuario_estado']) ?>
  </p>
<?php else: ?>
  <p><strong>Local:</strong> Não informado</p>
<?php endif; ?>


       
        <p><strong>Valor FIPE:</strong> R$ <?= number_format($p['preco'], 2, ',', '.') ?></p>
        <p><strong>Proposta Atual:</strong> <span style="color:#900;"><strong>R$ <?= $valorProposta ?></strong></span></p>

        <?php if ($statusOrig === 'aguardando_comprador'): ?>
            <p class="status-aguardando">Status: <span class="laranja">Aguardando sua resposta</span></p>
        <?php elseif ($statusOrig === 'aguardando_vendedor'): ?>
            <p class="status-aguardando">Status: <span class="laranja">Aguardando retorno do vendedor</span></p>
        <?php elseif ($statusOrig === 'aceita'): ?>
            
        <?php endif; ?>
    </div>

    <?php if ($statusOrig === 'aguardando_comprador'): ?>
        <div class="acoes-proposta">
            <button class="btn-aceitar" data-id="<?= $propostaId ?>">Aceitar</button>
            <button class="btn-negociar" data-id="<?= $propostaId ?>">Negociar</button>
            <button class="btn-recusar" data-id="<?= $propostaId ?>">Recusar</button>
        </div>

        <div class="form-negociacao" id="negociacao<?= $propostaId ?>">
            <input type="text" class="mascara-valor" id="valorNegociado<?= $propostaId ?>" placeholder="R$ 0,00" />
            <div class="texto-alerta">
                <img src="imagens/alerta.png" alt="Alerta" />
                <p>Envie uma contraproposta. O vendedor poderá aceitar ou negociar novamente.</p>
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
                <p><strong>Parabéns!</strong> Sua proposta foi aceita. Entre em contato com o vendedor:</p>
            </div>
        </div>

        <div class="contato-comprador">
            <p><strong>Nome:</strong> <?= htmlspecialchars($p['nome_vendedor']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($p['email_vendedor']) ?></p>
            <p><strong>Telefone:</strong> <?= htmlspecialchars($p['celular_vendedor']) ?></p>

            <div class="botoes-contato">
                <a href="https://wa.me/55<?= preg_replace('/\D/', '', $p['celular_vendedor']) ?>" target="_blank" class="btn-contato-whatsapp">
                    <img src="imagens/wzap.png" class="icone-whatsapp" alt="WhatsApp" />
                    WhatsApp
                </a>
                <button class="btn-contato-copiar"
                        onclick="copiarDadosComprador('<?= $p['nome_vendedor'] ?>', '<?= $p['celular_vendedor'] ?>', '<?= $p['email_vendedor'] ?>')">
                    Copiar Dados
                </button>
            </div>
        </div>

    <?php elseif ($statusOrig === 'recusada_vendedor'): ?>
        <div class="mensagem-recusa">
            <img src="imagens/cancela.png" alt="Ícone de recusa" />
            <div class="mensagem-texto">
                <p><strong>Proposta recusada!</strong> Você pode fazer uma nova proposta pelo painel “Oferta de Veículos”.</p>
            </div>
            <div style="margin-top: 10px; text-align: center;">
            
            </div>
        </div>
        <button class="btn-ok-recusa" data-id="<?= $propostaId ?>">Ok</button>
    <?php endif; ?>
</div>
<?php endwhile; ?>
</div>
