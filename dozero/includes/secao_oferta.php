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

$conditions = " AND v.status IN ('disponivel', 'completo') AND (v.em_negociacao = 0 OR v.em_negociacao IS NULL)";
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
           COALESCE(v.foto_principal, fv_first.caminho_foto) AS foto_exibir
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

// Fetch marcas for filter dropdown
$marcasResult = $conn->query("SELECT DISTINCT marca FROM veiculos WHERE status IN ('disponivel', 'completo') AND (em_negociacao = 0 OR em_negociacao IS NULL) ORDER BY marca ASC");
$marcas = [];
while ($m = $marcasResult->fetch_row()) {
    $marcas[] = $m[0];
}

/**
 * Returns the full URL for a vehicle photo.
 * New system photos are stored as 'fotos_veiculos/filename.jpg' → served from UPLOAD_URL.
 * Old system photos are stored as 'uploads/fotos_veiculos/id/filename.jpg' → served from SITE_URL root.
 */
function oferta_fotoUrl(string $path): string {
    if ($path === '') return '';
    if (strncmp($path, 'uploads/', 8) === 0) {
        return SITE_URL . '/' . $path;
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
    <div class="vehicle-cards-grid" id="vehicleGrid">
        <?php foreach ($veiculos as $v): ?>
        <div class="vehicle-card" data-id="<?= (int)$v['id'] ?>">
            <div class="vehicle-card-image">
                <?php if (!empty($v['foto_exibir'])): ?>
                <img src="<?= htmlspecialchars(oferta_fotoUrl($v['foto_exibir']), ENT_QUOTES, 'UTF-8') ?>"
                     alt="<?= htmlspecialchars($v['marca'] . ' ' . $v['modelo'], ENT_QUOTES, 'UTF-8') ?>"
                     loading="lazy">
                <?php else: ?>
                <div class="vehicle-card-no-img">
                    <i class="fa-solid fa-car-side"></i>
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
                <div class="vehicle-card-price"><?= formatMoney((float)$v['preco']) ?></div>
                <?php if ((int)$v['veiculo_usuario_id'] === $userId): ?>
                <span class="badge-seu-veiculo">
                    <i class="fa-solid fa-lock"></i> Seu veículo
                </span>
                <?php else: ?>
                <button class="btn-proposta" onclick="abrirModalProposta(<?= (int)$v['id'] ?>, '<?= htmlspecialchars(addslashes($v['marca'] . ' ' . $v['modelo'] . ' ' . $v['ano_fabrica']), ENT_QUOTES, 'UTF-8') ?>', <?= (float)$v['preco'] ?>)">
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
                    <input type="number" name="valor" id="propostaValor" class="form-control"
                           placeholder="Ex.: 42000" min="1" step="0.01" required>
                    <small style="color:var(--color-text-muted);font-size:0.8rem;">Informe o valor que deseja pagar pelo veículo.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Mensagem (opcional)</label>
                    <textarea name="mensagem" id="propostaMensagem" class="form-control"
                              rows="3" placeholder="Informações adicionais sobre sua proposta…"
                              maxlength="500" style="resize:vertical;"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn-modal-cancel" onclick="fecharModalProposta()">Cancelar</button>
            <button class="btn-modal-submit" id="btnEnviarProposta">
                <span class="btn-text"><i class="fa-solid fa-paper-plane"></i> Enviar Proposta</span>
                <div class="spinner"></div>
            </button>
        </div>
    </div>
</div>

<style>
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
.vehicle-card-image img { width: 100%; height: 100%; object-fit: cover; }
.vehicle-card-no-img {
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
}
.vehicle-card-no-img i { font-size: 3rem; color: #d1d5db; }
.vehicle-card-photo-count {
    position: absolute; bottom: 8px; right: 8px;
    background: rgba(0,0,0,0.55); color: #fff;
    font-size: 0.75rem; font-weight: 600;
    padding: 2px 8px; border-radius: 9999px;
    display: flex; align-items: center; gap: 0.3rem;
}
.vehicle-card-badge-proposta {
    position: absolute; top: 8px; left: 8px;
    background: rgba(22,163,74,0.9); color: #fff;
    font-size: 0.75rem; font-weight: 700;
    padding: 3px 10px; border-radius: 9999px;
    display: flex; align-items: center; gap: 0.3rem;
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
</style>

<script>
function abrirModalProposta(id, nome, preco) {
    document.getElementById('propostaVeiculoId').value = id;
    document.getElementById('propostaVeiculoNome').textContent = nome;
    document.getElementById('propostaVeiculoPreco').textContent = 'Preço: R$ ' + preco.toLocaleString('pt-BR', {minimumFractionDigits: 2});
    document.getElementById('propostaValor').value = '';
    document.getElementById('propostaMensagem').value = '';
    document.getElementById('propostaError').style.display = 'none';
    document.getElementById('propostaSucesso').style.display = 'none';
    document.getElementById('btnEnviarProposta').disabled = false;
    document.getElementById('btnEnviarProposta').classList.remove('loading');
    document.getElementById('modalProposta').style.display = 'flex';
}

function fecharModalProposta() {
    document.getElementById('modalProposta').style.display = 'none';
}

document.getElementById('btnEnviarProposta').addEventListener('click', function () {
    var valor = document.getElementById('propostaValor');
    if (!valor.value || parseFloat(valor.value) <= 0) {
        valor.classList.add('is-invalid');
        return;
    }
    valor.classList.remove('is-invalid');

    var btn = this;
    btn.disabled = true;
    btn.classList.add('loading');

    var formData = new FormData(document.getElementById('formProposta'));

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
