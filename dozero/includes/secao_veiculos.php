<?php
/**
 * Seção: Meus Veículos (vendedor + admin)
 * $conn, $user, $tipo, $csrfToken available from painel.php
 */
$userId = (int) $user['id'];

$page = max(1, (int) ($_GET['vp'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$search = trim($_GET['vs'] ?? '');
$filterStatus = trim($_GET['vstatus'] ?? '');

if ($tipo === 'administrador') {
    $countSql = "SELECT COUNT(*) FROM veiculos v INNER JOIN usuarios u ON u.id = v.usuario_id WHERE 1=1";
    $dataSql  = "SELECT v.*, u.nome AS vendedor_nome FROM veiculos v INNER JOIN usuarios u ON u.id = v.usuario_id WHERE 1=1";
    $params = [];
    $types  = '';
} else {
    $countSql = "SELECT COUNT(*) FROM veiculos v WHERE v.usuario_id = ?";
    $dataSql  = "SELECT v.*, ? AS vendedor_nome FROM veiculos v WHERE v.usuario_id = ?";
    $params = [$userId, $userId];
    $types  = 'ii';
}

$conditions = '';
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
if ($filterStatus !== '') {
    $conditions .= " AND v.status = ?";
    $filterParams[] = $filterStatus;
    $filterTypes .= 's';
}

// Count
$stmtCount = $conn->prepare($countSql . $conditions);
if (!empty($params) || !empty($filterParams)) {
    $allParams = array_merge($params, $filterParams);
    $allTypes  = $types . $filterTypes;
    // Re-bind for count (without vendor_nome param for count query)
    if ($tipo === 'administrador') {
        if (!empty($filterParams)) {
            $stmtCount->bind_param($filterTypes, ...$filterParams);
        }
    } else {
        // count query for vendor has only usuario_id
        $countParams = [$userId];
        $countTypes  = 'i';
        if (!empty($filterParams)) {
            $countParams = array_merge($countParams, $filterParams);
            $countTypes .= $filterTypes;
        }
        $stmtCount->bind_param($countTypes, ...$countParams);
    }
}
$stmtCount->execute();
$stmtCount->bind_result($totalCount);
$stmtCount->fetch();
$stmtCount->close();

$totalPages = max(1, (int) ceil($totalCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Fetch data
$finalDataSql = $dataSql . $conditions . " ORDER BY v.data_cadastro DESC LIMIT ? OFFSET ?";
$stmtData = $conn->prepare($finalDataSql);

if ($tipo === 'administrador') {
    $finalParams = array_merge($filterParams, [$perPage, $offset]);
    $finalTypes  = $filterTypes . 'ii';
} else {
    $finalParams = array_merge([$user['nome'], $userId], $filterParams, [$perPage, $offset]);
    $finalTypes  = 'si' . $filterTypes . 'ii';
}

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

function veiculos_statusBadge(string $status): string {
    $map = [
        'disponivel'    => ['#d1fae5','#065f46','Disponível'],
        'em_negociacao' => ['#fef3c7','#92400e','Em Negociação'],
        'vendido'       => ['#dbeafe','#1e40af','Vendido'],
        'pausado'       => ['#f3f4f6','#6b7280','Pausado'],
        'pendente'      => ['#fef3c7','#92400e','Pendente'],
        'reprovado'     => ['#fee2e2','#991b1b','Reprovado'],
    ];
    $d = $map[$status] ?? ['#f3f4f6','#6b7280', ucfirst($status)];
    return '<span style="background:' . $d[0] . ';color:' . $d[1] . ';padding:2px 10px;border-radius:9999px;font-size:0.75rem;font-weight:600;">'
        . htmlspecialchars($d[2], ENT_QUOTES, 'UTF-8') . '</span>';
}
?>

<div class="section-page">
    <div class="section-page-header">
        <div>
            <h2 class="section-page-title">
                <i class="fa-solid fa-car-side"></i>
                <?= $tipo === 'administrador' ? 'Todos os Veículos' : 'Meus Veículos' ?>
            </h2>
            <p class="section-page-subtitle">
                <?= (int)$totalCount ?> veículo<?= $totalCount !== 1 ? 's' : '' ?> encontrado<?= $totalCount !== 1 ? 's' : '' ?>
            </p>
        </div>
        <?php if ($tipo !== 'administrador'): ?>
        <button class="btn-action-primary" id="btnNovoVeiculo">
            <i class="fa-solid fa-plus"></i> Cadastrar Veículo
        </button>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <form method="get" class="filter-bar" id="veiculosFilterForm">
        <input type="hidden" name="secao" value="veiculos">
        <div class="filter-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="vs" placeholder="Buscar por marca, modelo ou placa…" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <select name="vstatus" class="filter-select">
            <option value="">Todos os status</option>
            <option value="disponivel" <?= $filterStatus === 'disponivel' ? 'selected' : '' ?>>Disponível</option>
            <option value="em_negociacao" <?= $filterStatus === 'em_negociacao' ? 'selected' : '' ?>>Em Negociação</option>
            <option value="vendido" <?= $filterStatus === 'vendido' ? 'selected' : '' ?>>Vendido</option>
            <option value="pausado" <?= $filterStatus === 'pausado' ? 'selected' : '' ?>>Pausado</option>
            <option value="pendente" <?= $filterStatus === 'pendente' ? 'selected' : '' ?>>Pendente</option>
        </select>
        <button type="submit" class="btn-filter-apply">Filtrar</button>
        <?php if ($search !== '' || $filterStatus !== ''): ?>
        <a href="?secao=veiculos" class="btn-filter-clear">Limpar</a>
        <?php endif; ?>
    </form>

    <!-- Table -->
    <div class="table-card">
        <?php if (empty($veiculos)): ?>
        <div class="table-empty">
            <i class="fa-solid fa-car"></i>
            <p><?= $search !== '' || $filterStatus !== '' ? 'Nenhum veículo encontrado para os filtros aplicados.' : 'Nenhum veículo cadastrado ainda.' ?></p>
            <?php if ($tipo !== 'administrador'): ?>
            <button class="btn-empty-action" id="btnNovoVeiculoEmpty">Cadastrar Primeiro Veículo</button>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <?php if ($tipo === 'administrador'): ?><th>Vendedor</th><?php endif; ?>
                        <th>Foto</th>
                        <th>Veículo</th>
                        <th>Ano</th>
                        <th>Km</th>
                        <th>Preço</th>
                        <th>Status</th>
                        <th>Cadastrado</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($veiculos as $v): ?>
                    <?php
                    $fotoPath = $v['foto_principal'] ?? '';
                    if ($fotoPath !== '') {
                        $fotoUrl = (strncmp($fotoPath, 'uploads/', 8) === 0)
                            ? SITE_URL . '/' . $fotoPath
                            : UPLOAD_URL . $fotoPath;
                    } else {
                        $fotoUrl = '';
                    }
                    ?>
                    <tr>
                        <td style="color:var(--color-text-muted);font-size:0.8125rem;">#<?= (int)$v['id'] ?></td>
                        <?php if ($tipo === 'administrador'): ?>
                        <td><?= htmlspecialchars($v['vendedor_nome'], ENT_QUOTES, 'UTF-8') ?></td>
                        <?php endif; ?>
                        <td>
                            <?php if ($fotoUrl !== ''): ?>
                            <img src="<?= htmlspecialchars($fotoUrl, ENT_QUOTES, 'UTF-8') ?>"
                                 alt="Foto"
                                 style="width:64px;height:48px;object-fit:cover;border-radius:8px;"
                                 loading="lazy">
                            <?php else: ?>
                            <div style="width:64px;height:48px;background:#f3f4f6;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                                <i class="fa-solid fa-car" style="color:#d1d5db;font-size:1rem;"></i>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight:600;font-size:0.875rem;">
                                <?= htmlspecialchars($v['marca'] . ' ' . $v['modelo'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($v['ano_fabrica'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $v['quilometragem'] !== null ? number_format((int)$v['quilometragem'], 0, ',', '.') . ' km' : '-' ?></td>
                        <td style="font-weight:600;"><?= formatMoney((float)($v['preco'] ?? 0)) ?></td>
                        <td><?= veiculos_statusBadge($v['status'] ?? 'pendente') ?></td>
                        <td style="color:var(--color-text-muted);font-size:0.8125rem;">
                            <?= !empty($v['data_cadastro']) ? date('d/m/Y', strtotime($v['data_cadastro'])) : '-' ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:0.375rem;">
                                <?php if ($tipo !== 'administrador'): ?>
                                <button class="btn-table-action btn-edit"
                                        title="Editar"
                                        onclick="editarVeiculo(<?= (int)$v['id'] ?>)">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <?php endif; ?>
                                <button class="btn-table-action btn-danger"
                                        title="Remover"
                                        onclick="removerVeiculo(<?= (int)$v['id'] ?>, '<?= htmlspecialchars(addslashes($v['marca'] . ' ' . $v['modelo']), ENT_QUOTES, 'UTF-8') ?>')">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="table-pagination">
            <span class="pagination-info">Página <?= $page ?> de <?= $totalPages ?></span>
            <div class="pagination-btns">
                <?php if ($page > 1): ?>
                <a href="?secao=veiculos&vp=<?= $page - 1 ?>&vs=<?= urlencode($search) ?>&vstatus=<?= urlencode($filterStatus) ?>" class="btn-page">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                <?php
                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                <a href="?secao=veiculos&vp=<?= $i ?>&vs=<?= urlencode($search) ?>&vstatus=<?= urlencode($filterStatus) ?>"
                   class="btn-page <?= $i === $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                <a href="?secao=veiculos&vp=<?= $page + 1 ?>&vs=<?= urlencode($search) ?>&vstatus=<?= urlencode($filterStatus) ?>" class="btn-page">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Cadastrar/Editar Veículo -->
<div class="modal-overlay" id="modalVeiculo" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="modalVeiculoTitle">
    <div class="modal-box modal-lg">
        <div class="modal-header">
            <h3 id="modalVeiculoTitle">Cadastrar Veículo</h3>
            <button class="modal-close" onclick="fecharModalVeiculo()" aria-label="Fechar">&times;</button>
        </div>
        <div class="modal-body">
            <div class="alert-box alert-error" id="modalVeiculoError" style="display:none;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span id="modalVeiculoErrorMsg"></span>
            </div>
            <form id="formVeiculo" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="veiculo_id" id="veiculoId" value="">

                <div class="modal-form-grid">
                    <div class="form-group">
                        <label class="form-label">Placa <span class="req">*</span></label>
                        <input type="text" name="placa" id="inputPlaca" class="form-control" placeholder="ABC-1234 ou ABC1D23" maxlength="8" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Marca <span class="req">*</span></label>
                        <input type="text" name="marca" id="inputMarca" class="form-control" placeholder="Ex.: Toyota" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Modelo <span class="req">*</span></label>
                        <input type="text" name="modelo" id="inputModelo" class="form-control" placeholder="Ex.: Corolla" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ano de Fabricação <span class="req">*</span></label>
                        <input type="number" name="ano_fabrica" id="inputAno" class="form-control" placeholder="2020" min="1950" max="<?= date('Y') + 1 ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quilometragem <span class="req">*</span></label>
                        <input type="number" name="quilometragem" id="inputKm" class="form-control" placeholder="50000" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Preço (R$) <span class="req">*</span></label>
                        <input type="number" name="preco" id="inputPreco" class="form-control" placeholder="45000" min="0" step="0.01" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Fotos do Veículo</label>
                    <input type="file" name="fotos[]" id="inputFotos" class="form-control" multiple accept="image/jpeg,image/png,image/webp">
                    <small style="color:var(--color-text-muted);font-size:0.8rem;">Máximo 5MB por foto. Formatos: JPG, PNG, WebP.</small>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn-modal-cancel" onclick="fecharModalVeiculo()">Cancelar</button>
            <button class="btn-modal-submit" id="btnSalvarVeiculo">
                <span class="btn-text"><i class="fa-solid fa-floppy-disk"></i> Salvar</span>
                <div class="spinner"></div>
            </button>
        </div>
    </div>
</div>

<script>
function abrirModalVeiculo() {
    document.getElementById('modalVeiculoTitle').textContent = 'Cadastrar Veículo';
    document.getElementById('formVeiculo').reset();
    document.getElementById('veiculoId').value = '';
    document.getElementById('modalVeiculoError').style.display = 'none';
    document.getElementById('modalVeiculo').style.display = 'flex';
}

function fecharModalVeiculo() {
    document.getElementById('modalVeiculo').style.display = 'none';
}

function editarVeiculo(id) {
    document.getElementById('modalVeiculoTitle').textContent = 'Editar Veículo';
    document.getElementById('veiculoId').value = id;
    document.getElementById('modalVeiculoError').style.display = 'none';
    document.getElementById('modalVeiculo').style.display = 'flex';

    fetch('actions/carregar_veiculo.php?id=' + id)
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success && data.data) {
                var v = data.data;
                document.getElementById('inputPlaca').value   = v.placa || '';
                document.getElementById('inputMarca').value   = v.marca || '';
                document.getElementById('inputModelo').value  = v.modelo || '';
                document.getElementById('inputAno').value     = v.ano_fabrica || '';
                document.getElementById('inputKm').value      = v.quilometragem || '';
                document.getElementById('inputPreco').value   = v.preco || '';
            }
        });
}

function removerVeiculo(id, nome) {
    if (!confirm('Remover "' + nome + '"? Esta ação não pode ser desfeita.')) return;
    var fd = new FormData();
    fd.append('veiculo_id', id);
    fd.append('csrf_token', '<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>');
    fetch('actions/remover_veiculo.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                window.location.href = '?secao=veiculos';
            } else {
                alert(data.message || 'Erro ao remover veículo.');
            }
        });
}

var btnNovo = document.getElementById('btnNovoVeiculo');
if (btnNovo) btnNovo.addEventListener('click', abrirModalVeiculo);
var btnNovoEmpty = document.getElementById('btnNovoVeiculoEmpty');
if (btnNovoEmpty) btnNovoEmpty.addEventListener('click', abrirModalVeiculo);

document.getElementById('btnSalvarVeiculo').addEventListener('click', function () {
    var form = document.getElementById('formVeiculo');
    var inputs = form.querySelectorAll('[required]');
    var valid = true;
    inputs.forEach(function (input) {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            valid = false;
        }
    });
    if (!valid) return;

    var btn = this;
    btn.disabled = true;
    btn.classList.add('loading');

    var formData = new FormData(form);
    var isEdit = !!document.getElementById('veiculoId').value;
    var url = isEdit ? 'actions/editar_veiculo.php' : 'actions/salvar_veiculo.php';

    fetch(url, { method: 'POST', body: formData })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                window.location.href = '?secao=veiculos';
            } else {
                btn.disabled = false;
                btn.classList.remove('loading');
                var errBox = document.getElementById('modalVeiculoError');
                document.getElementById('modalVeiculoErrorMsg').textContent = data.message || 'Erro ao salvar veículo.';
                errBox.style.display = 'flex';
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.classList.remove('loading');
        });
});

document.getElementById('modalVeiculo').addEventListener('click', function (e) {
    if (e.target === this) fecharModalVeiculo();
});
</script>
