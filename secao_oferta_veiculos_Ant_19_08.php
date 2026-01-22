<?php
require_once "conexao_bd.php";

$marca   = $_GET['marca'] ?? '';
$ano_de  = $_GET['ano_de'] ?? '';
$ano_ate = $_GET['ano_ate'] ?? '';
$preco   = $_GET['preco'] ?? '';
$estado = $_GET['estado'] ?? '';
$visualizacao = $_GET['visualizacao'] ?? 'cards'; // NOVO: parâmetro de visualização

$filtros = ["v.em_negociacao = 0", "v.status = 'completo'"];
$params = [];
$types  = "";

if (!empty($marca)) {
    $filtros[] = "v.marca = ?";
    $params[] = $marca;
    $types   .= "s";
}

if (!empty($ano_de)) {
    $filtros[] = "v.ano_fabrica >= ?";
    $params[] = $ano_de;
    $types   .= "i";
}

if (!empty($ano_ate)) {
    $filtros[] = "v.ano_fabrica <= ?";
    $params[] = $ano_ate;
    $types   .= "i";
}

if (!empty($estado)) {
  $filtros[] = "u.estado = ?";
  $params[] = $estado;
  $types   .= "s";
}

if (!empty($preco)) {
    switch ($preco) {
        case "1": $filtros[] = "v.preco <= 20000"; break;
        case "2": $filtros[] = "v.preco > 20000 AND v.preco <= 50000"; break;
        case "3": $filtros[] = "v.preco > 50000 AND v.preco <= 100000"; break;
        case "4": $filtros[] = "v.preco > 100000"; break;
    }
}

$where = implode(" AND ", $filtros);

$sql = "SELECT v.id, v.modelo, v.marca, v.ano_fabrica, v.quilometragem, v.preco,
               u.cidade AS usuario_cidade, u.estado AS usuario_estado
        FROM veiculos v
        LEFT JOIN usuarios u ON v.usuario_id = u.id
        WHERE $where
        ORDER BY v.data_cadastro DESC";

$stmt = $mysqli->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!-- BOTÕES DE VISUALIZAÇÃO -->
<div class="visualizacao-toggle" style="width: 100%; display: flex; gap: 10px; margin-bottom: 20px; justify-content: flex-end;">
    <button class="btn-view <?= $visualizacao === 'cards' ? 'active' : '' ?>" onclick="alterarVisualizacao('cards')" style="padding: 8px 16px; border: 1px solid #ddd; background: <?= $visualizacao === 'cards' ? '#B22222' : 'white' ?>; color: <?= $visualizacao === 'cards' ? 'white' : 'black' ?>; cursor: pointer; border-radius: 4px; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-grip"></i> Cards
    </button>
    <button class="btn-view <?= $visualizacao === 'lista' ? 'active' : '' ?>" onclick="alterarVisualizacao('lista')" style="padding: 8px 16px; border: 1px solid #ddd; background: <?= $visualizacao === 'lista' ? '#B22222' : 'white' ?>; color: <?= $visualizacao === 'lista' ? 'white' : 'black' ?>; cursor: pointer; border-radius: 4px; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-list"></i> Lista
    </button>
</div>

<?php if ($visualizacao === 'lista'): ?>
    <!-- AVISO SOBRE TABELA FIPE - COM BOTÃO X PARA FECHAR -->
    <div class="aviso-fipe-unico" id="avisoFipe" style="display: block;">
        <div class="aviso-conteudo">
            <i class="fa-solid fa-circle-info"></i>
            <p>O valor indicado em Tabela FIPE é apenas para sua referência. Você pode fazer uma proposta em qualquer valor.</p>
        </div>
        <button class="btn-fechar-aviso" onclick="fecharAvisoFipe()" title="Fechar aviso">
            <i class="fa-solid fa-times"></i>
        </button>
    </div>
    
    <!-- VISUALIZAÇÃO EM LISTA DESKTOP - DUAS COLUNAS (ESTILO MOBILE) -->
    <div class="lista-desktop-container desktop-only">
        <div class="lista-duas-colunas-mobile-style">
            <?php 
            $veiculos = [];
            while ($veiculo = $result->fetch_assoc()) {
                $veiculos[] = $veiculo;
            }
            
            foreach ($veiculos as $index => $veiculo): 
                $veiculo_id = $veiculo['id'];
                $valorFipe = $veiculo['preco'];
                
                // Buscar primeira foto
                $stmtFoto = $mysqli->prepare("SELECT caminho_foto FROM fotos_veiculos WHERE veiculo_id = ? ORDER BY ordem_exibicao ASC LIMIT 1");
                $stmtFoto->bind_param("i", $veiculo_id);
                $stmtFoto->execute();
                $resFoto = $stmtFoto->get_result();
                $foto = $resFoto->fetch_assoc();
                $fotoPrincipal = htmlspecialchars($foto['caminho_foto'] ?? 'imagens/default_car.png');
                
                // Buscar histórico
                $historico = [];
                $stmtHist = $mysqli->prepare("SELECT valor FROM propostas WHERE veiculo_id = ? AND (status LIKE 'recusada_%' OR status LIKE 'historico_recusada%') ORDER BY data_proposta DESC LIMIT 3");
                $stmtHist->bind_param("i", $veiculo_id);
                $stmtHist->execute();
                $resHist = $stmtHist->get_result();
                while ($h = $resHist->fetch_assoc()) {
                    $historico[] = number_format($h['valor'], 2, ',', '.');
                }
            ?>
            <div class="card-expansivel-container">
                <div class="card-horizontal-desktop" id="cardDesktop<?= $veiculo_id ?>">
                    <div class="card-foto">
                        <img src="<?= $fotoPrincipal ?>" alt="Foto" onclick="ampliarImagem(this.src)">
                    </div>
                    <div class="card-info">
                        <h4><?= htmlspecialchars($veiculo['modelo']) ?></h4>
                        <p class="card-detalhes">
                            <?= htmlspecialchars($veiculo['marca']) ?> • 
                            <?= htmlspecialchars($veiculo['ano_fabrica']) ?> • 
                            <?= number_format($veiculo['quilometragem'], 0, '', '.') ?> km
                        </p>
                        <p class="card-local">
                            <i class="fa-solid fa-location-dot"></i> 
                            <?= htmlspecialchars($veiculo['usuario_cidade'] ?? 'Cidade') ?>/<?= htmlspecialchars($veiculo['usuario_estado'] ?? 'UF') ?>
                        </p>
                        <p class="card-valor-fipe">
                            Tabela FIPE: R$ <?= number_format($valorFipe, 2, ',', '.') ?>
                        </p>
                        
                        <?php if (!empty($historico)): ?>
                            <div class="historico-desktop-mobile">
                                <strong>Propostas Recebidas:</strong><br>
                                <?php foreach (array_reverse($historico) as $idx => $valor): ?>
                                    <?= $idx + 1 ?>ª recusa: R$ <?= $valor ?><br>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-acao">
                        <button class="btn-vermelho btn-desktop" onclick="abrirPropostaDesktop(<?= $veiculo_id ?>)">
                            Proposta
                        </button>
                    </div>
                </div>
                
                <!-- Formulário expansível DENTRO do container -->
                <div id="formPropostaDesktop<?= $veiculo_id ?>" class="form-proposta-expansivel" style="display: none;">
                    <input type="text" id="valorPropostaDesktop<?= $veiculo_id ?>" class="input-proposta-custom mascara-valor" placeholder="Informe o valor da proposta" />
                    
                    <div class="texto-alerta-desktop-mobile">
                        <img src="imagens/alerta.png" alt="Alerta">
                        Recomendamos que você faça sua própria pesquisa de mercado para garantir seu lucro. O site apenas apresenta as ofertas dos veículos de usuários.
                    </div>
                    
                    <div class="botoes-proposta-desktop-mobile">
                        <button class="btn-confirmar-custom" onclick="confirmarPropostaDesktop(<?= $veiculo_id ?>)">Confirmar</button>
                        <button class="btn-cancelar-custom" onclick="cancelarPropostaDesktop(<?= $veiculo_id ?>)">Cancelar</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- VISUALIZAÇÃO EM CARDS MOBILE -->
    <div class="lista-cards-mobile mobile-only">
        <?php 
        foreach ($veiculos as $veiculo): 
            $veiculo_id = $veiculo['id'];
            $valorFipe = $veiculo['preco'];
            
            // Buscar primeira foto
            $stmtFoto = $mysqli->prepare("SELECT caminho_foto FROM fotos_veiculos WHERE veiculo_id = ? ORDER BY ordem_exibicao ASC LIMIT 1");
            $stmtFoto->bind_param("i", $veiculo_id);
            $stmtFoto->execute();
            $resFoto = $stmtFoto->get_result();
            $foto = $resFoto->fetch_assoc();
            $fotoPrincipal = htmlspecialchars($foto['caminho_foto'] ?? 'imagens/default_car.png');
            
            // Buscar histórico
            $historico = [];
            $stmtHist = $mysqli->prepare("SELECT valor FROM propostas WHERE veiculo_id = ? AND (status LIKE 'recusada_%' OR status LIKE 'historico_recusada%') ORDER BY data_proposta DESC LIMIT 3");
            $stmtHist->bind_param("i", $veiculo_id);
            $stmtHist->execute();
            $resHist = $stmtHist->get_result();
            while ($h = $resHist->fetch_assoc()) {
                $historico[] = number_format($h['valor'], 2, ',', '.');
            }
        ?>
        <div class="card-expansivel-container-mobile">
            <div class="card-horizontal-mobile" id="cardMobile<?= $veiculo_id ?>">
                <div class="card-foto">
                    <img src="<?= $fotoPrincipal ?>" alt="Foto" onclick="ampliarImagem(this.src)">
                </div>
                <div class="card-info">
                    <h4><?= htmlspecialchars($veiculo['modelo']) ?></h4>
                    <p class="card-detalhes">
                        <?= htmlspecialchars($veiculo['marca']) ?> • 
                        <?= htmlspecialchars($veiculo['ano_fabrica']) ?> • 
                        <?= number_format($veiculo['quilometragem'], 0, '', '.') ?> km
                    </p>
                    <p class="card-local">
                        <i class="fa-solid fa-location-dot"></i> 
                        <?= htmlspecialchars($veiculo['usuario_cidade'] ?? 'Cidade') ?>/<?= htmlspecialchars($veiculo['usuario_estado'] ?? 'UF') ?>
                    </p>
                    <p class="card-valor-fipe">
                        Tabela FIPE: R$ <?= number_format($valorFipe, 2, ',', '.') ?>
                    </p>
                    
                    <?php if (!empty($historico)): ?>
                        <div class="historico-mobile">
                            <strong>Propostas Recebidas:</strong><br>
                            <?php foreach (array_reverse($historico) as $idx => $valor): ?>
                                <?= $idx + 1 ?>ª recusa: R$ <?= $valor ?><br>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-acao">
                    <button class="btn-vermelho btn-mobile" onclick="abrirPropostaListaMobile(<?= $veiculo_id ?>)">
                        Proposta
                    </button>
                </div>
            </div>
            
            <!-- Formulário expansível mobile DENTRO do container -->
            <div id="formPropostaListaMobile<?= $veiculo_id ?>" class="form-proposta-expansivel-mobile" style="display: none;">
                <input type="text" id="valorPropostaListaMobile<?= $veiculo_id ?>" class="input-proposta-custom mascara-valor" placeholder="Informe o valor da proposta" />
                
                <div class="texto-alerta-mobile">
                    <img src="imagens/alerta.png" alt="Alerta">
                    Recomendamos que você faça sua própria pesquisa de mercado para garantir seu lucro. O site apenas apresenta as ofertas dos veículos de usuários.
                </div>
                
                <div class="botoes-proposta-mobile">
                    <button class="btn-confirmar-custom" onclick="confirmarPropostaListaMobile(<?= $veiculo_id ?>)">Confirmar</button>
                    <button class="btn-cancelar-custom" onclick="cancelarPropostaListaMobile(<?= $veiculo_id ?>)">Cancelar</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
        
    <!-- AVISO SOBRE TABELA FIPE - VERSÃO CARDS -->
    <div class="aviso-fipe-unico" id="avisoFipeCards" style="display: block;">
        <div class="aviso-conteudo">
            <i class="fa-solid fa-circle-info"></i>
            <p>O valor indicado em Tabela FIPE é apenas para sua referência. Você pode fazer uma proposta em qualquer valor.</p>
        </div>
        <button class="btn-fechar-aviso" onclick="fecharAvisoFipeCards()" title="Fechar aviso">
            <i class="fa-solid fa-times"></i>
        </button>
    </div>



    
    <!-- VISUALIZAÇÃO EM CARDS (código original mantido) -->
    <?php
    $result->data_seek(0); // Resetar o ponteiro do resultado
    while ($veiculo = $result->fetch_assoc()):
        $veiculo_id  = $veiculo['id'];
        $valorFipe   = $veiculo['preco'];
        $sugeridoMin = $valorFipe * (1 - 0.2134);
        $sugeridoMax = $valorFipe * (1 - 0.0936);

        // Fotos
        $fotos = [];
        $stmtFotos = $mysqli->prepare("SELECT caminho_foto FROM fotos_veiculos WHERE veiculo_id = ? ORDER BY ordem_exibicao ASC LIMIT 6");
        $stmtFotos->bind_param("i", $veiculo_id);
        $stmtFotos->execute();
        $resFotos = $stmtFotos->get_result();
        while ($f = $resFotos->fetch_assoc()) {
            $fotos[] = $f['caminho_foto'];
        }
        $fotoPrincipal = htmlspecialchars($fotos[0] ?? 'imagens/default_car.png');
    ?>

    <div class="oferta-card" data-id="<?= $veiculo_id ?>">
      <img src="<?= $fotoPrincipal ?>" alt="Foto principal" class="oferta-main-image" id="mainImage<?= $veiculo_id ?>" onclick="ampliarImagem(this.src)">

      <?php if (count($fotos) > 1): ?>
        <div class="oferta-thumbs">
          <?php foreach ($fotos as $thumb): ?>
            <img src="<?= htmlspecialchars($thumb) ?>" class="oferta-thumb" alt="Miniatura">
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="oferta-info">
      <h3><?= htmlspecialchars($veiculo['modelo']) ?></h3>
        <p><strong>Marca:</strong> <?= htmlspecialchars($veiculo['marca']) ?></p>
        <p><strong>Ano Modelo:</strong> <?= htmlspecialchars($veiculo['ano_fabrica']) ?></p>
        <p><strong>KM:</strong> <?= number_format($veiculo['quilometragem'], 0, '', '.') ?> km</p>
        <?php if (!empty($veiculo['usuario_cidade']) && !empty($veiculo['usuario_estado'])): ?>
          <p><strong>Local:</strong> 
        <?= htmlspecialchars($veiculo['usuario_cidade']) ?>/<?= htmlspecialchars($veiculo['usuario_estado']) ?>
      </p>
    <?php else: ?>

      <p><strong>Local:</strong> Não informado</p>
    <?php endif; ?>

        <p><strong>Valor FIPE:</strong> R$ <?= number_format($valorFipe, 2, ',', '.') ?></p>
        
        <?php
    // Buscar histórico de recusas (3 últimas)
    $historico = [];
    $stmtHist = $mysqli->prepare("SELECT valor FROM propostas WHERE veiculo_id = ? AND (status LIKE 'recusada_%' OR status LIKE 'historico_recusada%') ORDER BY data_proposta DESC LIMIT 3");
    $stmtHist->bind_param("i", $veiculo_id);
    $stmtHist->execute();
    $resHist = $stmtHist->get_result();
    while ($h = $resHist->fetch_assoc()) {
        $historico[] = number_format($h['valor'], 2, ',', '.');
    }
    ?>

    <?php if (!empty($historico)): ?>
      <p><strong>Propostas Recebidas:</strong><br>
      <?php foreach (array_reverse($historico) as $index => $valor): ?>
          <?= $index + 1 ?>ª recusa: R$ <?= $valor ?><br>
        <?php endforeach; ?>
      </p>
    <?php endif; ?>

        <!-- Botão de ação -->
    <div class="btn-wrapper">
          <button class="btn-vermelho" onclick="enviarProposta(<?= $veiculo_id ?>)">Enviar Proposta</button>
          </div>

        <!-- Formulário da proposta -->
        <div id="formProposta<?= $veiculo_id ?>" class="form-proposta escondido">
          <input type="text" id="valorProposta<?= $veiculo_id ?>" class="input-proposta mascara-valor" placeholder="Informe o valor da proposta" />

          <div class="texto-alerta">
            <img src="imagens/alerta.png" alt="Alerta">
            Recomendamos que você faça sua própria pesquisa de mercado para garantir seu lucro. O site apenas apresenta as ofertas dos veículos de usuários.
          </div>

          <div class="botoes-proposta">
      <button class="btn-vermelho" onclick="confirmarProposta(<?= $veiculo_id ?>)">Confirmar</button>
      <button class="btn-vermelho btn-cancelar" onclick="cancelarProposta(<?= $veiculo_id ?>)">Cancelar</button>
    </div>
        </div>

        <!-- Histórico -->
        <div class="historico-recusas" id="historico<?= $veiculo_id ?>" style="margin-top: 10px;"></div>
      </div>
    </div>

    <?php endwhile; ?>
<?php endif; ?>

<style>
/* ========== ESTILOS GERAIS ========== */
.visualizacao-toggle {
    display: flex !important;
    gap: 10px;
    margin-bottom: 20px;
    justify-content: flex-end;
    width: 100%;
    clear: both;
}

.btn-view {
    padding: 8px 16px !important;
    border: 1px solid #ddd !important;
    background: white !important;
    color: black !important;
    cursor: pointer !important;
    border-radius: 4px !important;
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    transition: all 0.3s ease !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
}

.btn-view:hover {
    background: #f5f5f5 !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
}

.btn-view.active {
    background: #B22222 !important;
    color: white !important;
    border-color: #B22222 !important;
}

.btn-view i {
    font-size: 16px !important;
}

.oferta-list {
    clear: both;
    width: 100%;
}

/* ========== AVISO TABELA FIPE COM BOTÃO X CORRIGIDO ========== */
.aviso-fipe-unico {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    border: 1px solid #ffd93d;
    border-radius: 8px;
    padding: 16px 50px 16px 20px; /* ✅ ESPAÇO EXTRA À DIREITA PARA O BOTÃO X */
    margin-bottom: 25px;
    display: flex;
    align-items: flex-start;
    gap: 15px;
    box-shadow: 0 3px 10px rgba(255, 193, 7, 0.2);
    position: relative;
}

.aviso-conteudo {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    flex: 1;
    width: 100%; /* ✅ GARANTE QUE OCUPE TODO O ESPAÇO DISPONÍVEL */
}

.aviso-fipe-unico i {
    color: #f39c12;
    font-size: 24px;
    flex-shrink: 0;
    margin-top: 2px;
}

.aviso-fipe-unico p {
    margin: 0;
    font-size: 15px;
    color: #856404;
    line-height: 1.5;
    font-weight: 500;
    flex: 1;
    padding-right: 10px; /* ✅ ESPAÇO EXTRA PARA NÃO ENCOSTAR NO X */
}

.btn-fechar-aviso {
    position: absolute !important;
    top: 12px !important;
    right: 15px !important;
    background: transparent !important;
    border: none !important;
    color: #856404 !important;
    font-size: 18px !important;
    cursor: pointer !important;
    padding: 6px !important;
    border-radius: 50% !important;
    transition: all 0.3s ease !important;
    width: 28px !important;
    height: 28px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    z-index: 10 !important;
}

.btn-fechar-aviso:hover {
    background: rgba(133, 100, 4, 0.15) !important;
    color: #664d03 !important;
    transform: scale(1.1) !important;
}

/* ========== CONTAINERS EXPANSÍVEIS ========== */
.card-expansivel-container,
.card-expansivel-container-mobile {
    width: 100%;
    margin-bottom: 15px;
}

/* ========== FORMULÁRIOS EXPANSÍVEIS (ESTILO CARDS) ========== */
.form-proposta-expansivel,
.form-proposta-expansivel-mobile {
    width: 100%;
    padding: 15px;
    background: #f5f5f5;
    border-radius: 0 0 8px 8px;
    border: 1px solid #e0e0e0;
    border-top: none;
    margin-top: -1px; /* Para conectar com o card acima */
    animation: expandDown 0.3s ease-out;
}

@keyframes expandDown {
    from {
        opacity: 0;
        max-height: 0;
        padding-top: 0;
        padding-bottom: 0;
    }
    to {
        opacity: 1;
        max-height: 200px;
        padding-top: 15px;
        padding-bottom: 15px;
    }
}

/* ========== INPUTS E BOTÕES CUSTOMIZADOS ========== */
.input-proposta-custom {
    width: 100% !important;
    max-width: 250px !important;
    margin-bottom: 10px !important;
    padding: 10px !important;
    font-size: 14px !important;
    border: 1px solid #ddd !important;
    border-radius: 4px !important;
    box-sizing: border-box !important;
}

.btn-confirmar-custom {
    flex: 1 !important;
    padding: 8px !important;
    border-radius: 4px !important;
    font-size: 13px !important;
    background: #B22222 !important;
    color: white !important;
    border: none !important;
    cursor: pointer !important;
    transition: all 0.3s ease !important;
}

.btn-confirmar-custom:hover {
    background: #8B0000 !important;
}

.btn-cancelar-custom {
    flex: 1 !important;
    padding: 8px !important;
    border-radius: 4px !important;
    font-size: 13px !important;
    background: #6c757d !important;
    color: white !important;
    border: none !important;
    cursor: pointer !important;
    transition: all 0.3s ease !important;
}

.btn-cancelar-custom:hover {
    background: #5a6268 !important;
}

/* ========== DESKTOP - DUAS COLUNAS ESTILO MOBILE ========== */
.lista-desktop-container {
    width: 100%;
}

.lista-duas-colunas-mobile-style {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.card-horizontal-desktop {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px 8px 0 0; /* ✅ MUDANÇA: bordas arredondadas só em cima */
    display: flex;
    align-items: flex-start;
    padding: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    position: relative;
}

.card-horizontal-desktop:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transform: translateY(-1px);
}

.texto-alerta-desktop-mobile {
    background: #fff3cd;
    border: 1px solid #ffd93d;
    padding: 8px;
    border-radius: 4px;
    margin: 8px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #856404;
}

.texto-alerta-desktop-mobile img {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

.botoes-proposta-desktop-mobile {
    display: flex;
    gap: 10px;
    max-width: 250px;
}

.btn-desktop {
    padding: 10px 20px;
    font-size: 14px;
    white-space: nowrap;
    background: #B22222;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-desktop:hover {
    background: #8B0000;
    transform: translateY(-1px);
}

.historico-desktop-mobile {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    padding: 8px;
    border-radius: 4px;
    margin: 8px 0;
    font-size: 11px;
    color: #666;
}

/* ========== MOBILE ========== */
.desktop-only {
    display: block;
}

.mobile-only {
    display: none;
}

.card-horizontal-mobile {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px 8px 0 0; /* ✅ MUDANÇA: bordas arredondadas só em cima */
    display: flex;
    align-items: flex-start;
    padding: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    position: relative;
}

.card-horizontal-mobile:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transform: translateY(-1px);
}

/* Foto do card */
.card-foto {
    flex-shrink: 0;
    margin-right: 12px;
}

.card-foto img {
    width: 90px;
    height: 90px;
    object-fit: cover;
    border-radius: 6px;
    cursor: pointer;
}

/* Informações do card */
.card-info {
    flex: 1;
    min-width: 0;
}

.card-info h4 {
    margin: 0 0 6px 0;
    font-size: 16px;
    font-weight: 600;
    color: #333;
    line-height: 1.2;
}

.card-detalhes {
    margin: 0 0 4px 0;
    font-size: 13px;
    color: #666;
}

.card-local {
    margin: 0 0 8px 0;
    font-size: 12px;
    color: #888;
}

.card-local i {
    font-size: 11px;
    margin-right: 2px;
}

.card-valor-fipe {
    margin: 0 0 8px 0;
    font-size: 11px;
    color: #B22222;
    font-weight: 500;
    background: #fff5f5;
    padding: 4px 8px;
    border-radius: 4px;
    display: inline-block;
    border: 1px solid #ffe0e0;
}

.historico-mobile {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    padding: 8px;
    border-radius: 4px;
    margin: 8px 0;
    font-size: 11px;
    color: #666;
}

/* Botão de ação */
.card-acao {
    flex-shrink: 0;
    margin-left: 10px;
    align-self: center;
}

.btn-mobile {
    padding: 10px 20px;
    font-size: 14px;
    white-space: nowrap;
    background: #B22222;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-mobile:hover {
    background: #8B0000;
    transform: translateY(-1px);
}

/* Formulário de proposta mobile */
.texto-alerta-mobile {
    background: #fff3cd;
    border: 1px solid #ffd93d;
    padding: 8px;
    border-radius: 4px;
    margin: 8px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    color: #856404;
}

.texto-alerta-mobile img {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

.botoes-proposta-mobile {
    display: flex;
    gap: 10px;
}

/* ========== MEDIA QUERIES ========== */
@media (max-width: 1200px) {
    .lista-duas-colunas-mobile-style {
        grid-template-columns: 1fr;
        gap: 15px;
    }
}

@media (max-width: 768px) {
    .desktop-only {
        display: none !important;
    }
    
    .mobile-only {
        display: block !important;
    }
    
    .visualizacao-toggle {
        justify-content: center;
    }
    
    .btn-view {
        font-size: 12px !important;
        padding: 6px 12px !important;
    }
    
    .aviso-fipe-unico {
        padding: 12px 40px 12px 16px;
        gap: 12px;
    }
    
    .aviso-fipe-unico i {
        font-size: 20px;
    }
    
    .aviso-fipe-unico p {
        font-size: 13px;
    }
    
    .btn-fechar-aviso {
        top: 8px !important;
        right: 10px !important;
        font-size: 16px !important;
        width: 24px !important;
        height: 24px !important;
    }
}

/* Para telas muito pequenas (menos de 420px) */
@media (max-width: 420px) {
    .card-horizontal-mobile, .card-horizontal-desktop {
        padding: 10px;
    }
    
    .card-foto img {
        width: 75px;
        height: 75px;
    }
    
    .card-info h4 {
        font-size: 14px;
    }
    
    .card-detalhes {
        font-size: 12px;
    }
    
    .card-valor-fipe {
        font-size: 10px;
        padding: 3px 6px;
    }
    
    .btn-mobile, .btn-desktop {
        padding: 8px 16px;
        font-size: 13px;
    }
    
    .aviso-fipe-unico {
        padding: 10px 35px 10px 12px;
    }
    
    .aviso-fipe-unico p {
        font-size: 12px;
    }
}

/* Para telas entre 421px e 768px */
@media (min-width: 421px) and (max-width: 768px) {
    .card-horizontal-mobile, .card-horizontal-desktop {
        padding: 16px;
    }
    
    .card-foto img {
        width: 100px;
        height: 100px;
    }
    
    .card-info h4 {
        font-size: 17px;
    }
    
    .btn-mobile, .btn-desktop {
        padding: 12px 24px;
        font-size: 15px;
    }
}
</style>

<script>
// ========== FUNÇÃO PARA FECHAR O AVISO FIPE CORRIGIDA ========== 
function fecharAvisoFipe() {
    console.log('🔄 Tentando fechar aviso FIPE...'); // Debug
    const aviso = document.getElementById('avisoFipe');
    if (aviso) {
        console.log('✅ Elemento encontrado, fechando...'); // Debug
        aviso.style.display = 'none';
        aviso.style.visibility = 'hidden'; // Garantia extra
    } else {
        console.log('❌ Elemento avisoFipe não encontrado'); // Debug
    }
}

// ✅ GARANTIR QUE A FUNÇÃO ESTEJA NO ESCOPO GLOBAL
window.fecharAvisoFipe = fecharAvisoFipe;

// ✅ ADICIONAR LISTENER EXTRA PARA GARANTIR FUNCIONAMENTO
document.addEventListener('DOMContentLoaded', function() {
    const btnFechar = document.querySelector('.btn-fechar-aviso');
    if (btnFechar) {
        btnFechar.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('🔄 Click no botão X detectado'); // Debug
            fecharAvisoFipe();
        });
    }
});
</script>