<?php
/**
 * Seção: Oferta de Veículos (investidor)
 * $conn, $user, $tipo, $csrfToken available from painel.php
 */
$userId = (int) $user['id'];

$page = max(1, (int) ($_GET['op'] ?? 1));
$perPage = 9;
$offset = ($page - 1) * $perPage;

$search      = trim($_GET['os'] ?? '');
$filterMarca = trim($_GET['omarca'] ?? '');
$filterMax   = trim($_GET['omax'] ?? '');
$filterMin   = trim($_GET['omin'] ?? '');

$conditions = " AND v.em_negociacao = 0 AND v.status IN ('completo', 'disponivel')";
$filterParams = [];
$filterTypes  = '';

if ($search !== '') {
    $conditions .= " AND (v.marca LIKE ? OR v.modelo LIKE ? OR v.placa LIKE ?)";
    $like = '%' . $search . '%';
    $filterParams[] = $like;
    $filterParams[] = $like;
    $filterParams[] = $like;
    $filterTypes .= 'sss';
}
if ($filterMarca !== '') {
    $conditions .= " AND v.marca = ?";
    $filterParams[] = $filterMarca;
    $filterTypes .= 's';
}
if ($filterMin !== '' && is_numeric($filterMin)) {
    $conditions .= " AND v.preco >= ?";
    $filterParams[] = (float)$filterMin;
    $filterTypes .= 'd';
}
if ($filterMax !== '' && is_numeric($filterMax)) {
    $conditions .= " AND v.preco <= ?";
    $filterParams[] = (float)$filterMax;
    $filterTypes .= 'd';
}

$countSql = "SELECT COUNT(*) FROM veiculos v INNER JOIN usuarios u ON u.id = v.usuario_id WHERE 1=1" . $conditions;
$stmtCount = $conn->prepare($countSql);
if ($stmtCount === false) {
    $totalCount = 0;
} else {
    if (!empty($filterParams)) {
        $stmtCount->bind_param($filterTypes, ...$filterParams);
    }
    $stmtCount->execute();
    $stmtCount->bind_result($totalCount);
    $stmtCount->fetch();
    $stmtCount->close();
}

$totalPages = max(1, (int) ceil($totalCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$dataSql = "
    SELECT v.id, v.marca, v.modelo, v.ano_fabrica, v.quilometragem, v.preco,
           v.status, v.data_cadastro,
           v.usuario_id AS veiculo_usuario_id,
           u.nome AS vendedor_nome,
           fv_first.caminho_foto AS foto_exibir
    FROM veiculos v
    INNER JOIN usuarios u ON u.id = v.usuario_id
    LEFT JOIN fotos_veiculos fv_first ON fv_first.id = (
        SELECT id FROM fotos_veiculos
        WHERE veiculo_id = v.id
        ORDER BY IFNULL(ordem_exibicao, 0) ASC, id ASC
        LIMIT 1
    )
    WHERE 1=1" . $conditions . "
    ORDER BY v.data_cadastro DESC LIMIT ? OFFSET ?
";

$finalParams = array_merge($filterParams, [$perPage, $offset]);
$finalTypes  = $filterTypes . 'ii';

$stmtData = $conn->prepare($dataSql);
$_ofertaDbError = '';
if ($stmtData === false) {
    $veiculos = [];
    $_ofertaDbError = $conn->error;
} else {
    if (!empty($finalParams)) {
        $stmtData->bind_param($finalTypes, ...$finalParams);
    }
    $stmtData->execute();
    $result = $stmtData->get_result();
    $veiculos = [];
    while ($row = $result->fetch_assoc()) {
        $veiculos[] = $row;
    }
    $stmtData->close();
}

// Fetch minha_proposta counts separately (avoids proposta_origem_id dependency)
if (!empty($veiculos)) {
    $veiculoIds = implode(',', array_map('intval', array_column($veiculos, 'id')));
    $propSql = "SELECT veiculo_id, COUNT(*) AS cnt FROM propostas
                WHERE usuario_id = ? AND veiculo_id IN ($veiculoIds)
                GROUP BY veiculo_id";
    $stmtProp = $conn->prepare($propSql);
    $minhaProposta = [];
    if ($stmtProp) {
        $stmtProp->bind_param('i', $userId);
        $stmtProp->execute();
        $propResult = $stmtProp->get_result();
        while ($pr = $propResult->fetch_assoc()) {
            $minhaProposta[(int)$pr['veiculo_id']] = (int)$pr['cnt'];
        }
        $stmtProp->close();
    }
    foreach ($veiculos as &$v) {
        $v['minha_proposta'] = $minhaProposta[(int)$v['id']] ?? 0;
    }
    unset($v);
}

// Fetch all photos per vehicle for gallery display
$allFotosByVehicle = [];
if (!empty($veiculos)) {
    $veiculoIdsStr = implode(',', array_map('intval', array_column($veiculos, 'id')));
    $fotosSql = "SELECT veiculo_id, caminho_foto FROM fotos_veiculos
                 WHERE veiculo_id IN ($veiculoIdsStr)
                 ORDER BY IFNULL(ordem_exibicao, 0) ASC, id ASC";
    $fotosRes = $conn->query($fotosSql);
    if ($fotosRes) {
        while ($frow = $fotosRes->fetch_assoc()) {
            $vid = (int)$frow['veiculo_id'];
            $allFotosByVehicle[$vid][] = oferta_fotoUrl($frow['caminho_foto']);
        }
    }
    // Fallback for vehicles with no fotos_veiculos records: use foto_exibir
    foreach ($veiculos as &$vf) {
        $vid = (int)$vf['id'];
        if (!isset($allFotosByVehicle[$vid]) && !empty($vf['foto_exibir'])) {
            $allFotosByVehicle[$vid] = [oferta_fotoUrl($vf['foto_exibir'])];
        }
    }
    unset($vf);
}

// Fetch marcas for filter dropdown
$marcasResult = $conn->query("SELECT DISTINCT marca FROM veiculos WHERE em_negociacao = 0 ORDER BY marca ASC");
$marcas = [];
while ($m = $marcasResult->fetch_row()) {
    $marcas[] = $m[0];
}

/**
 * Returns the full URL for a vehicle photo.
 * New system photos are stored as 'fotos_veiculos/filename.jpg' → served from UPLOAD_URL.
 * Old system photos are stored as 'uploads/fotos_veiculos/id/filename.jpg' → served from LEGACY_PHOTO_BASE_URL (motorgo.co).
 */
function oferta_fotoUrl(string $path): string {
    if ($path === '') return '';
    if (strncmp($path, 'uploads/', 8) === 0) {
        return LEGACY_PHOTO_BASE_URL . '/' . $path;
    }
    return UPLOAD_URL . $path;
}
?>

<div class="section-page">
    <div class="section-page-header">
        <div>
            <h2 class="section-page-title">
                <i class="fa-solid fa-car-burst"></i> Oferta de Veículos
            </h2>
            <p class="section-page-subtitle">
                <?= (int)$totalCount ?> veículo<?= $totalCount !== 1 ? 's' : '' ?> disponível<?= $totalCount !== 1 ? 'is' : '' ?> para investimento
            </p>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" class="filter-bar filter-bar-extended" id="ofertaFilterForm">
        <input type="hidden" name="secao" value="oferta">
        <div class="filter-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="os" placeholder="Buscar marca, modelo…" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <select name="omarca" class="filter-select">
            <option value="">Todas as marcas</option>
            <?php foreach ($marcas as $m): ?>
            <option value="<?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?>" <?= $filterMarca === $m ? 'selected' : '' ?>>
                <?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?>
            </option>
            <?php endforeach; ?>
        </select>
        <input type="number" name="omin" placeholder="Preço mínimo" class="filter-select" value="<?= htmlspecialchars($filterMin, ENT_QUOTES, 'UTF-8') ?>" min="0" step="1000">
        <input type="number" name="omax" placeholder="Preço máximo" class="filter-select" value="<?= htmlspecialchars($filterMax, ENT_QUOTES, 'UTF-8') ?>" min="0" step="1000">
        <button type="submit" class="btn-filter-apply">Filtrar</button>
        <?php if ($search !== '' || $filterMarca !== '' || $filterMin !== '' || $filterMax !== ''): ?>
        <a href="?secao=oferta" class="btn-filter-clear">Limpar</a>
        <?php endif; ?>
    </form>

    <!-- Vehicle cards grid -->
    <?php if (!empty($_ofertaDbError)): ?>
    <div style="background:#fee2e2;color:#991b1b;padding:1rem 1.25rem;border-radius:8px;margin-bottom:1rem;font-size:0.875rem;">
        <strong>Erro de banco de dados (Oferta):</strong> <?= htmlspecialchars($_ofertaDbError, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>
    <?php if (empty($veiculos)): ?>
    <div class="table-card">
        <div class="table-empty">
            <i class="fa-solid fa-car"></i>
            <p>Nenhum veículo disponível no momento.</p>
        </div>
    </div>
    <?php else: ?>

    <!-- Disclaimer sobre preços sugeridos -->
    <div class="oferta-disclaimer">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span>Os preços dos veículos são <strong>sugeridos pelo sistema</strong>. Você deve fazer sua própria pesquisa de mercado antes de fazer uma proposta, sob seu próprio risco.</span>
    </div>

    <div class="vehicle-cards-grid" id="vehicleGrid">
        <?php foreach ($veiculos as $v):
            $todasFotos   = $allFotosByVehicle[(int)$v['id']] ?? [];
            if (empty($todasFotos) && !empty($v['foto_exibir'])) {
                $todasFotos = [oferta_fotoUrl($v['foto_exibir'])];
            }
            $totalFotos   = count($todasFotos);
            $fotoPrincipal = !empty($todasFotos) ? $todasFotos[0] : '';
            $fotosJson     = htmlspecialchars(json_encode($todasFotos), ENT_QUOTES, 'UTF-8');
            $precoSugerido = round((float)$v['preco'] * 0.70, 2);
        ?>
        <div class="vehicle-card" data-id="<?= (int)$v['id'] ?>">
            <div class="vehicle-card-image<?= $totalFotos > 0 ? ' has-photos' : '' ?>"
                 <?= $totalFotos > 0 ? 'onclick="abrirGaleria(this)" role="button" tabindex="0" aria-label="Ver fotos"' : '' ?>>
                <?php if ($fotoPrincipal !== ''): ?>
                <img src="<?= htmlspecialchars($fotoPrincipal, ENT_QUOTES, 'UTF-8') ?>"
                     alt="<?= htmlspecialchars($v['marca'] . ' ' . $v['modelo'], ENT_QUOTES, 'UTF-8') ?>"
                     data-fotos="<?= $fotosJson ?>"
                     loading="lazy">
                <?php else: ?>
                <div class="vehicle-card-no-img">
                    <i class="fa-solid fa-car-side"></i>
                </div>
                <?php endif; ?>
                <?php if ($totalFotos > 1): ?>
                <div class="vehicle-card-photo-count">
                    <i class="fa-solid fa-images"></i> <?= $totalFotos ?>
                </div>
                <?php endif; ?>
                <?php if ((int)($v['minha_proposta'] ?? 0) > 0): ?>
                <div class="vehicle-card-badge-proposta">
                    <i class="fa-solid fa-paper-plane"></i> Proposta Enviada
                </div>
                <?php endif; ?>
            </div>
            <div class="vehicle-card-body">
                <h3 class="vehicle-card-title">
                    <?= htmlspecialchars($v['marca'] . ' ' . $v['modelo'], ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <div class="vehicle-card-specs">
                    <span><i class="fa-solid fa-calendar-days"></i> <?= htmlspecialchars($v['ano_fabrica'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span><i class="fa-solid fa-gauge-high"></i> <?= number_format((int)$v['quilometragem'], 0, ',', '.') ?> km</span>
                </div>
                <div class="vehicle-card-seller">
                    <i class="fa-solid fa-user"></i>
                    <?= htmlspecialchars($v['vendedor_nome'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
            <div class="vehicle-card-footer">
                <div>
                    <div class="vehicle-card-price-label">Preço sugerido</div>
                    <div class="vehicle-card-price"><?= formatMoney($precoSugerido) ?></div>
                </div>
                <?php if ((int)$v['veiculo_usuario_id'] === $userId): ?>
                <span class="badge-seu-veiculo">
                    <i class="fa-solid fa-lock"></i> Seu veículo
                </span>
                <?php else: ?>
                <button class="btn-proposta" type="button" onclick="abrirModalProposta(<?= (int)$v['id'] ?>, '<?= htmlspecialchars(addslashes($v['marca'] . ' ' . $v['modelo'] . ' ' . $v['ano_fabrica']), ENT_QUOTES, 'UTF-8') ?>', <?= (float)$v['preco'] ?>, <?= $precoSugerido ?>)">
                    <i class="fa-solid fa-paper-plane"></i>
                    <?= (int)($v['minha_proposta'] ?? 0) > 0 ? 'Nova Proposta' : 'Fazer Proposta' ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="table-pagination" style="margin-top:1.5rem;">
        <span class="pagination-info">Página <?= $page ?> de <?= $totalPages ?></span>
        <div class="pagination-btns">
            <?php if ($page > 1): ?>
            <a href="?secao=oferta&op=<?= $page - 1 ?>&os=<?= urlencode($search) ?>&omarca=<?= urlencode($filterMarca) ?>&omin=<?= urlencode($filterMin) ?>&omax=<?= urlencode($filterMax) ?>" class="btn-page">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <?php endif; ?>
            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
            <a href="?secao=oferta&op=<?= $i ?>&os=<?= urlencode($search) ?>&omarca=<?= urlencode($filterMarca) ?>&omin=<?= urlencode($filterMin) ?>&omax=<?= urlencode($filterMax) ?>"
               class="btn-page <?= $i === $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?secao=oferta&op=<?= $page + 1 ?>&os=<?= urlencode($search) ?>&omarca=<?= urlencode($filterMarca) ?>&omin=<?= urlencode($filterMin) ?>&omax=<?= urlencode($filterMax) ?>" class="btn-page">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal: Galeria de Fotos -->
<div id="galeriaModal" class="galeria-overlay" style="display:none;" role="dialog" aria-modal="true" aria-label="Galeria de fotos">
    <button class="galeria-close-btn" onclick="fecharGaleria()" aria-label="Fechar galeria">
        <i class="fa-solid fa-xmark"></i>
    </button>
    <div class="galeria-container">
        <button class="galeria-nav galeria-prev" id="galeriaPrev" onclick="navegarGaleria(-1)" aria-label="Foto anterior">
            <i class="fa-solid fa-chevron-left"></i>
        </button>
        <div class="galeria-main-wrap">
            <img id="galeriaFotoMain" src="" alt="Foto do veículo">
        </div>
        <button class="galeria-nav galeria-next" id="galeriaNxt" onclick="navegarGaleria(1)" aria-label="Próxima foto">
            <i class="fa-solid fa-chevron-right"></i>
        </button>
    </div>
    <div class="galeria-counter" id="galeriaCounter">1 / 1</div>
    <div class="galeria-thumbs" id="galeriaThumbs"></div>
</div>

<!-- Modal: Fazer Proposta -->
<div class="modal-overlay" id="modalProposta" style="display:none;" role="dialog" aria-modal="true">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Fazer Proposta</h3>
            <button class="modal-close" onclick="fecharModalProposta()" aria-label="Fechar">&times;</button>
        </div>
        <div class="modal-body">
            <div class="alert-box alert-error" id="propostaError" style="display:none;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span id="propostaErrorMsg"></span>
            </div>
            <div class="alert-box alert-success" id="propostaSucesso" style="display:none;">
                <i class="fa-solid fa-circle-check"></i>
                <span>Proposta enviada com sucesso!</span>
            </div>

            <div class="proposta-vehicle-info" id="propostaVeiculoInfo">
                <i class="fa-solid fa-car-side"></i>
                <div>
                    <strong id="propostaVeiculoNome"></strong>
                    <span id="propostaVeiculoPreco" style="color:var(--color-text-muted);font-size:0.875rem;display:block;"></span>
                </div>
            </div>

            <form id="formProposta" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="veiculo_id" id="propostaVeiculoId">

                <div class="form-group">
                    <label class="form-label">Valor da Proposta (R$) <span class="req">*</span></label>
                    <input type="text" name="valor" id="propostaValor" class="form-control"
                           placeholder="Digite o valor" required inputmode="numeric">
                    <div class="alert-box alert-warning" style="display:flex;margin-top:0.625rem;font-size:0.82rem;padding:0.6rem 0.875rem;">
                        <i class="fa-solid fa-triangle-exclamation" style="font-size:1.25rem;flex-shrink:0;"></i>
                        <span>Valor sugerido baseado no mercado. Faça sua própria pesquisa para definir o preço ideal e lucrar.</span>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn-modal-cancel" onclick="fecharModalProposta()">Cancelar</button>
            <button class="btn-modal-submit" id="btnEnviarProposta" type="button">
                <span class="btn-text"><i class="fa-solid fa-paper-plane"></i> Enviar Proposta</span>
                <div class="spinner"></div>
            </button>
        </div>
    </div>
</div>

<style>
/* ── Disclaimer ───────────────────────────────────────── */
.oferta-disclaimer {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: var(--radius-lg);
    padding: 0.875rem 1.125rem;
    font-size: 0.875rem;
    color: #78350f;
    margin-bottom: 1.25rem;
    line-height: 1.5;
}
.oferta-disclaimer i { color: #d97706; font-size: 1rem; flex-shrink: 0; margin-top: 0.1rem; }

/* ── Cards grid ───────────────────────────────────────── */
.vehicle-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.25rem;
}
.vehicle-card {
    background: #fff;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    overflow: hidden;
    transition: var(--transition);
    box-shadow: var(--shadow-sm);
    display: flex;
    flex-direction: column;
}
.vehicle-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: rgba(178,34,34,0.2); }
.vehicle-card-image {
    height: 180px;
    background: #f3f4f6;
    position: relative;
    overflow: hidden;
}
.vehicle-card-image.has-photos { cursor: zoom-in; }
.vehicle-card-image.has-photos::after {
    content: '';
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0);
    transition: background 0.2s;
}
.vehicle-card-image.has-photos:hover::after { background: rgba(0,0,0,0.12); }
.vehicle-card-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s; }
.vehicle-card-image.has-photos:hover img { transform: scale(1.04); }
.vehicle-card-no-img {
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
}
.vehicle-card-no-img i { font-size: 3rem; color: #d1d5db; }
.vehicle-card-photo-count {
    position: absolute; bottom: 8px; right: 8px;
    background: rgba(0,0,0,0.60); color: #fff;
    font-size: 0.75rem; font-weight: 600;
    padding: 3px 9px; border-radius: 9999px;
    display: flex; align-items: center; gap: 0.3rem;
    z-index: 1;
    pointer-events: none;
}
.vehicle-card-badge-proposta {
    position: absolute; top: 8px; left: 8px;
    background: rgba(22,163,74,0.9); color: #fff;
    font-size: 0.75rem; font-weight: 700;
    padding: 3px 10px; border-radius: 9999px;
    display: flex; align-items: center; gap: 0.3rem;
    z-index: 1;
    pointer-events: none;
}
.vehicle-card-body { padding: 1rem 1.25rem; flex: 1; }
.vehicle-card-title { font-size: 1rem; font-weight: 700; margin-bottom: 0.5rem; }
.vehicle-card-specs {
    display: flex; gap: 1rem; flex-wrap: wrap;
    font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: 0.5rem;
}
.vehicle-card-specs span { display: flex; align-items: center; gap: 0.3rem; }
.vehicle-card-seller {
    font-size: 0.8125rem; color: var(--color-text-muted);
    display: flex; align-items: center; gap: 0.375rem;
}
.vehicle-card-footer {
    padding: 1rem 1.25rem;
    border-top: 1px solid var(--color-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}
.vehicle-card-price-label {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--color-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.15rem;
}
.vehicle-card-price { font-size: 1.125rem; font-weight: 800; color: var(--color-primary); }
.btn-proposta {
    padding: 0.5rem 1rem;
    background: var(--color-primary);
    color: #fff;
    border: none;
    border-radius: var(--radius-full);
    font-size: 0.8125rem;
    font-weight: 700;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 0.375rem;
    white-space: nowrap;
}
.btn-proposta:hover { background: var(--color-primary-dark); }
.badge-seu-veiculo {
    padding: 0.4rem 0.875rem;
    background: #f3f4f6;
    color: #6b7280;
    border: 1px solid #d1d5db;
    border-radius: var(--radius-full);
    font-size: 0.8125rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}
.proposta-vehicle-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: var(--color-bg);
    border-radius: var(--radius-lg);
    padding: 1rem;
    margin-bottom: 1.25rem;
}
.proposta-vehicle-info i { font-size: 1.5rem; color: var(--color-primary); }
.filter-bar-extended { flex-wrap: wrap; }

/* ── Galeria / Lightbox ───────────────────────────────── */
.galeria-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.93);
    z-index: 9999;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.5rem 1rem 1rem;
    gap: 0.75rem;
}
.galeria-close-btn {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(255,255,255,0.12);
    border: none;
    color: #fff;
    width: 44px; height: 44px;
    border-radius: 50%;
    font-size: 1.25rem;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.2s;
    z-index: 1;
}
.galeria-close-btn:hover { background: rgba(255,255,255,0.25); }
.galeria-container {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    max-width: 880px;
    flex: 1;
    min-height: 0;
}
.galeria-main-wrap {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 0;
    height: 100%;
}
.galeria-main-wrap img {
    max-width: 100%;
    max-height: 65vh;
    object-fit: contain;
    border-radius: 10px;
    display: block;
    box-shadow: 0 8px 40px rgba(0,0,0,0.5);
    transition: opacity 0.2s;
}
.galeria-nav {
    flex-shrink: 0;
    background: rgba(255,255,255,0.13);
    border: none;
    color: #fff;
    width: 48px; height: 48px;
    border-radius: 50%;
    font-size: 1.125rem;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.2s;
}
.galeria-nav:hover:not(:disabled) { background: rgba(255,255,255,0.28); }
.galeria-nav:disabled { opacity: 0.25; cursor: default; }
.galeria-counter {
    color: rgba(255,255,255,0.75);
    font-size: 0.875rem;
    font-weight: 500;
    letter-spacing: 0.04em;
}
.galeria-thumbs {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    flex-wrap: wrap;
    max-width: 880px;
    padding: 0 0.5rem;
}
.galeria-thumb {
    width: 72px; height: 54px;
    object-fit: cover;
    border-radius: 6px;
    cursor: pointer;
    opacity: 0.5;
    border: 2px solid transparent;
    transition: opacity 0.18s, border-color 0.18s, transform 0.18s;
    flex-shrink: 0;
}
.galeria-thumb:hover { opacity: 0.85; transform: scale(1.06); }
.galeria-thumb.active { opacity: 1; border-color: #fff; transform: scale(1.06); }
</style>

<script>
/* ── Galeria de fotos ─────────────────────────────────── */
var _galeriaFotos  = [];
var _galeriaIndex  = 0;

function abrirGaleria(el) {
    var img = el.querySelector('img');
    var fotos = [];
    if (img && img.dataset.fotos) {
        try { fotos = JSON.parse(img.dataset.fotos); } catch (e) {}
    }
    if (!fotos || fotos.length === 0) {
        if (img && img.src) fotos = [img.src];
        else return;
    }
    _galeriaFotos = fotos;
    _galeriaIndex = 0;
    _atualizarGaleria();
    document.getElementById('galeriaModal').style.display = 'flex';
}

function fecharGaleria() {
    document.getElementById('galeriaModal').style.display = 'none';
}

function navegarGaleria(dir) {
    if (_galeriaFotos.length <= 1) return;
    _galeriaIndex = (_galeriaIndex + dir + _galeriaFotos.length) % _galeriaFotos.length;
    _atualizarGaleria();
}

function _atualizarGaleria() {
    var fotos = _galeriaFotos;
    var idx   = _galeriaIndex;
    var mainImg = document.getElementById('galeriaFotoMain');
    mainImg.style.opacity = '0';
    mainImg.src = fotos[idx];
    mainImg.onload = function () { mainImg.style.opacity = '1'; };

    document.getElementById('galeriaCounter').textContent = (idx + 1) + ' / ' + fotos.length;

    var prev = document.getElementById('galeriaPrev');
    var next = document.getElementById('galeriaNxt');
    prev.disabled = fotos.length <= 1;
    next.disabled = fotos.length <= 1;

    var thumbsEl = document.getElementById('galeriaThumbs');
    thumbsEl.innerHTML = '';
    fotos.forEach(function (url, i) {
        var t = document.createElement('img');
        t.src = url;
        t.className = 'galeria-thumb' + (i === idx ? ' active' : '');
        t.alt = 'Foto ' + (i + 1);
        t.addEventListener('click', (function (index) {
            return function () { _galeriaIndex = index; _atualizarGaleria(); };
        })(i));
        thumbsEl.appendChild(t);
    });
}

document.getElementById('galeriaModal').addEventListener('click', function (e) {
    if (e.target === this) fecharGaleria();
});

document.addEventListener('keydown', function (e) {
    var modal = document.getElementById('galeriaModal');
    if (!modal || modal.style.display === 'none') return;
    if (e.key === 'Escape')      { fecharGaleria(); }
    if (e.key === 'ArrowLeft')   { navegarGaleria(-1); }
    if (e.key === 'ArrowRight')  { navegarGaleria(1); }
});

/* ── Modal de Proposta ───────────────────────────────── */
function _formatBRL(value) {
    var n = Math.round(Math.abs(parseInt(value, 10) || 0));
    return 'R$ ' + n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}
function _parseBRL(str) {
    var digits = (str || '').replace(/\D/g, '');
    return digits ? parseInt(digits, 10) : 0;
}
function _applyMoedaMask(input) {
    input.addEventListener('input', function () {
        var digits = this.value.replace(/\D/g, '');
        if (!digits) { this.value = ''; return; }
        this.value = _formatBRL(parseInt(digits, 10));
    });
}

function abrirModalProposta(id, nome, precoFipe, precoSugerido) {
    document.getElementById('propostaVeiculoId').value = id;
    document.getElementById('propostaVeiculoNome').textContent = nome;
    document.getElementById('propostaVeiculoPreco').textContent =
        'Preço sugerido: ' + _formatBRL(precoSugerido);
    document.getElementById('propostaValor').value = '';
    document.getElementById('propostaError').style.display = 'none';
    document.getElementById('propostaSucesso').style.display = 'none';
    document.getElementById('formProposta').style.display = '';
    document.getElementById('btnEnviarProposta').disabled = false;
    document.getElementById('btnEnviarProposta').classList.remove('loading');
    document.getElementById('modalProposta').style.display = 'flex';
}

// Apply mask once DOM is ready
_applyMoedaMask(document.getElementById('propostaValor'));

function fecharModalProposta() {
    document.getElementById('modalProposta').style.display = 'none';
}

document.getElementById('btnEnviarProposta').addEventListener('click', function () {
    var valorInput = document.getElementById('propostaValor');
    var valorNum   = _parseBRL(valorInput.value);
    if (!valorNum || valorNum <= 0) {
        valorInput.classList.add('is-invalid');
        return;
    }
    valorInput.classList.remove('is-invalid');

    var btn = this;
    btn.disabled = true;
    btn.classList.add('loading');

    var formData = new FormData(document.getElementById('formProposta'));
    // Send integer reais value (mask stores full integer, no cents division)
    formData.set('valor', valorNum.toString());

    fetch('actions/enviar_proposta.php', { method: 'POST', body: formData })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                document.getElementById('propostaError').style.display = 'none';
                document.getElementById('propostaSucesso').style.display = 'flex';
                document.getElementById('formProposta').style.display = 'none';
                setTimeout(function () {
                    fecharModalProposta();
                    window.location.href = '?secao=propostas';
                }, 1800);
            } else {
                btn.disabled = false;
                btn.classList.remove('loading');
                document.getElementById('propostaErrorMsg').textContent = data.message || 'Erro ao enviar proposta.';
                document.getElementById('propostaError').style.display = 'flex';
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.classList.remove('loading');
            document.getElementById('propostaErrorMsg').textContent = 'Erro de conexão. Tente novamente.';
            document.getElementById('propostaError').style.display = 'flex';
        });
});

document.getElementById('modalProposta').addEventListener('click', function (e) {
    if (e.target === this) fecharModalProposta();
});
</script>
