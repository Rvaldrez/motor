<?php
require_once "conexao_bd.php";

// Configurações de paginação
$itens_por_pagina = 20;
$pagina_atual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Parâmetros de filtro
$marca   = $_GET['marca'] ?? '';
$ano_de  = $_GET['ano_de'] ?? '';
$ano_ate = $_GET['ano_ate'] ?? '';
$preco   = $_GET['preco'] ?? '';
$estado = $_GET['estado'] ?? '';
$visualizacao = $_GET['visualizacao'] ?? 'cards';

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

// Contar total de registros para paginação
$sql_count = "SELECT COUNT(*) as total
              FROM veiculos v
              LEFT JOIN usuarios u ON v.usuario_id = u.id
              WHERE $where";

$stmt_count = $mysqli->prepare($sql_count);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_registros = $result_count->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $itens_por_pagina);

// Query principal com LIMIT e OFFSET
$sql = "SELECT v.id, v.modelo, v.marca, v.ano_fabrica, v.quilometragem, v.preco,
               u.cidade AS usuario_cidade, u.estado AS usuario_estado
        FROM veiculos v
        LEFT JOIN usuarios u ON v.usuario_id = u.id
        WHERE $where
        ORDER BY v.data_cadastro DESC
        LIMIT ? OFFSET ?";

// Adicionar os parâmetros de LIMIT e OFFSET
$params[] = $itens_por_pagina;
$params[] = $offset;
$types .= "ii";

$stmt = $mysqli->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!-- BOTÕES DE VISUALIZAÇÃO -->
<div class="visualizacao-toggle" style="width: 100%; display: flex; gap: 10px; margin-bottom: 20px; justify-content: flex-end;">
    <button class="btn-view <?= $visualizacao === 'cards' ? 'active' : '' ?>" 
            onclick="alterarVisualizacao('cards')" 
            style="padding: 8px 16px; border: 1px solid #ddd; background: <?= $visualizacao === 'cards' ? '#B22222' : 'white' ?>; color: <?= $visualizacao === 'cards' ? 'white' : 'black' ?>; cursor: pointer; border-radius: 4px; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-grip"></i> Cards
    </button>
    <button class="btn-view <?= $visualizacao === 'lista' ? 'active' : '' ?>" 
            onclick="alterarVisualizacao('lista')" 
            style="padding: 8px 16px; border: 1px solid #ddd; background: <?= $visualizacao === 'lista' ? '#B22222' : 'white' ?>; color: <?= $visualizacao === 'lista' ? 'white' : 'black' ?>; cursor: pointer; border-radius: 4px; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-list"></i> Lista
    </button>
</div>

<?php if ($visualizacao === 'lista'): ?>
    <!-- AVISO SOBRE TABELA FIPE - COM BOTÃO X PARA FECHAR -->
    <div class="aviso-fipe-unico" id="avisoFipe" style="display: block;">
        <div class="aviso-conteudo">
            <i class="fa-solid fa-circle-info"></i>
            <p>Antes de enviar a proposta, verifique o preço de mercado do veículo e inclua a sua margem de lucro.</p>
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
                            VALOR: Faça sua proposta
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
            <p>Antes de enviar a proposta, verifique o preço de mercado do veículo e inclua a sua margem de lucro.</p>
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

        <p><strong>Valor:</strong> Faça sua proposta </p>
        
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

<!-- CONTROLES DE PAGINAÇÃO MODERNOS -->
<?php if ($total_paginas > 1): ?>
<div class="paginacao-moderna">
    <!-- Seta Anterior -->
    <button class="btn-nav-pagina <?= $pagina_atual == 1 ? 'disabled' : '' ?>" 
            onclick="<?= $pagina_atual > 1 ? 'irParaPagina(' . ($pagina_atual - 1) . ')' : '' ?>"
            <?= $pagina_atual == 1 ? 'disabled' : '' ?>>
        <i class="fa-solid fa-chevron-left"></i>
    </button>
    
    <!-- Números das Páginas -->
    <div class="numeros-paginas-moderno">
        <?php
        // Lógica para mostrar páginas de forma inteligente
        $range = 2; // Páginas visíveis ao redor da atual
        $start = max(1, $pagina_atual - $range);
        $end = min($total_paginas, $pagina_atual + $range);
        
        // Sempre mostrar primeira página
        if ($start > 1): ?>
            <button class="btn-pagina" onclick="irParaPagina(1)">1</button>
            <?php if ($start > 2): ?>
                <span class="dots">...</span>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php for ($i = $start; $i <= $end; $i++): ?>
            <button class="btn-pagina <?= $i == $pagina_atual ? 'active' : '' ?>" 
                    onclick="irParaPagina(<?= $i ?>)">
                <?= $i ?>
            </button>
        <?php endfor; ?>
        
        <?php if ($end < $total_paginas): ?>
            <?php if ($end < $total_paginas - 1): ?>
                <span class="dots">...</span>
            <?php endif; ?>
            <button class="btn-pagina" onclick="irParaPagina(<?= $total_paginas ?>)">
                <?= $total_paginas ?>
            </button>
        <?php endif; ?>
    </div>
    
    <!-- Seta Próxima -->
    <button class="btn-nav-pagina <?= $pagina_atual == $total_paginas ? 'disabled' : '' ?>" 
            onclick="<?= $pagina_atual < $total_paginas ? 'irParaPagina(' . ($pagina_atual + 1) . ')' : '' ?>"
            <?= $pagina_atual == $total_paginas ? 'disabled' : '' ?>>
        <i class="fa-solid fa-chevron-right"></i>
    </button>
</div>
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

/* ========== PAGINAÇÃO MODERNA ========== */
.paginacao-moderna {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin: 40px auto;
    padding: 0;
}

.numeros-paginas-moderno {
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Botões de navegação (setas) */
.btn-nav-pagina {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: none;
    background: white;
    color: #B22222;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    font-size: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.btn-nav-pagina:hover:not(.disabled) {
    background: #B22222;
    color: white;
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(178, 34, 34, 0.3);
}

.btn-nav-pagina.disabled {
    opacity: 0.3;
    cursor: not-allowed;
    pointer-events: none;
}

/* Botões de página */
.btn-pagina {
    min-width: 40px;
    height: 40px;
    padding: 0 12px;
    border: none;
    background: white;
    color: #666;
    cursor: pointer;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

.btn-pagina:hover:not(.active) {
    background: #f5f5f5;
    color: #B22222;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.12);
}

.btn-pagina.active {
    background: #333;
    color: white;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(51, 51, 51, 0.3);
    transform: scale(1.05);
}

/* Reticências */
.dots {
    color: #999;
    font-weight: 600;
    padding: 0 8px;
    user-select: none;
}

/* Responsividade */
@media (max-width: 768px) {
    .paginacao-moderna {
        margin: 30px auto;
        gap: 6px;
    }
    
    .btn-nav-pagina,
    .btn-pagina {
        width: 36px;
        height: 36px;
        min-width: 36px;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        vertical-align: middle;
    }
    
    /* Correção específica para o botão ativo no mobile */
    .btn-pagina.active {
        transform: none; /* Remove o scale no mobile */
    }
    
    .numeros-paginas-moderno {
        gap: 3px;
        display: flex;
        align-items: center;
        height: 36px; /* Altura fixa no mobile */
    }
    
    /* Esconder algumas páginas no mobile para economizar espaço */
    .btn-pagina:not(.active):not(:first-child):not(:last-child) {
        display: none;
    }
    
    .btn-pagina.active,
    .btn-pagina.active + .btn-pagina,
    .btn-pagina.active + .btn-pagina + .btn-pagina {
        display: inline-flex;
    }
    
    .dots {
        padding: 0 4px;
    }
}

@media (max-width: 420px) {
    .btn-nav-pagina,
    .btn-pagina {
        width: 32px;
        height: 32px;
        min-width: 32px;
        font-size: 13px;
    }
    
    .btn-nav-pagina {
        font-size: 14px;
    }
}

/* Animação suave ao mudar de página */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.oferta-card,
.card-horizontal-desktop,
.card-horizontal-mobile {
    animation: fadeIn 0.5s ease-out;
}

/* ========== AVISO TABELA FIPE EM FORMATO DE BARRA ========== */
.aviso-fipe-unico {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    border-left: 4px solid #f39c12;
    border-top: 1px solid #ffd93d;
    border-right: 1px solid #ffd93d;
    border-bottom: 1px solid #ffd93d;
    padding: 12px 50px 12px 15px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 2px 8px rgba(255, 193, 7, 0.15);
    position: relative;
    width: 100%;
    border-radius: 0;
    min-height: 50px;
}

.aviso-conteudo {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    width: 100%;
}

.aviso-fipe-unico i {
    color: #f39c12;
    font-size: 20px;
    flex-shrink: 0;
    align-self: flex-start;
    margin-top: 2px;
}

.aviso-fipe-unico p {
    margin: 0;
    font-size: 14px;
    color: #856404;
    line-height: 1.4;
    font-weight: 500;
    flex: 1;
}

.btn-fechar-aviso {
    position: absolute !important;
    top: 0 !important;
    bottom: 0 !important;
    right: 15px !important;
    margin: auto 0 !important;
    background: transparent !important;
    border: none !important;
    color: #856404 !important;
    font-size: 18px !important;
    cursor: pointer !important;
    padding: 4px !important;
    border-radius: 50% !important;
    transition: all 0.3s ease !important;
    width: 26px !important;
    height: 26px !important;
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
    margin-top: -1px;
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
    border-radius: 8px 8px 0 0;
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
    border-radius: 8px 8px 0 0;
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
    
    /* Ajustes para paginação mobile */
    .paginacao-container {
        flex-direction: column;
        gap: 15px;
        padding: 15px;
    }
    
    .paginacao-controles {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .btn-paginacao {
        padding: 6px 10px;
        min-width: 32px;
        height: 32px;
        font-size: 13px;
    }
    
    .numeros-paginas {
        gap: 3px;
    }
    
    .ir-para-pagina {
        width: 100%;
        justify-content: center;
    }
    
    .ir-para-pagina input {
        width: 50px;
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
    
    /* Esconder alguns botões de paginação em telas muito pequenas */
    .btn-primeira,
    .btn-ultima {
        display: none;
    }
    
    .numeros-paginas {
        gap: 2px;
    }
    
    .btn-paginacao {
        padding: 5px 8px;
        min-width: 28px;
        height: 28px;
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
// ========== FUNÇÕES DE PAGINAÇÃO ==========
function irParaPagina(pagina) {
    // Manter os parâmetros atuais e adicionar a página
    const marca = document.getElementById('filtroMarca')?.value || '';
    const anoDe = document.getElementById('filtroAnoDe')?.value || '';
    const anoAte = document.getElementById('filtroAnoAte')?.value || '';
    const preco = document.getElementById('filtroPreco')?.value || '';
    const estado = document.getElementById('filtroEstado')?.value || '';
    
    const params = new URLSearchParams({
        marca: marca,
        ano_de: anoDe,
        ano_ate: anoAte,
        preco: preco,
        estado: estado,
        visualizacao: window.visualizacaoAtual || 'cards',
        pagina: pagina
    });
    
    // Fazer scroll suave para o topo
    window.scrollTo({ top: 0, behavior: 'smooth' });
    
    // Carregar a nova página via AJAX
    fetch(`secao_oferta_veiculos.php?${params}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('listaOfertaVeiculos').innerHTML = html;
        })
        .catch(error => console.error('Erro ao carregar página:', error));
}

function irParaPaginaInput() {
    const input = document.getElementById('inputPagina');
    const pagina = parseInt(input.value);
    
    if (pagina && pagina > 0) {
        irParaPagina(pagina);
    }
}

function irParaPaginaEnter(event) {
    if (event.key === 'Enter') {
        irParaPaginaInput();
    }
}

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