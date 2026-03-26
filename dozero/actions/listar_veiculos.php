<?php
require_once '../includes/config.php';
require_once '../includes/conexao.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo '<p class="text-danger">Acesso negado.</p>';
    exit;
}

$usuario_id  = (int) $_SESSION['usuario_id'];
$tipo        = $_SESSION['tipo'] ?? '';
$secao       = trim($_GET['secao']     ?? 'oferta');
$marca_f     = trim($_GET['marca']     ?? '');
$preco_min   = isset($_GET['preco_min']) && $_GET['preco_min'] !== '' ? (float) $_GET['preco_min'] : null;
$preco_max   = isset($_GET['preco_max']) && $_GET['preco_max'] !== '' ? (float) $_GET['preco_max'] : null;
$ano_min     = isset($_GET['ano_min'])  && $_GET['ano_min']  !== '' ? (int)   $_GET['ano_min']  : null;
$ano_max     = isset($_GET['ano_max'])  && $_GET['ano_max']  !== '' ? (int)   $_GET['ano_max']  : null;
$pagina      = max(1, (int) ($_GET['pagina']    ?? 1));
$por_pagina  = min(48, max(1, (int) ($_GET['por_pagina'] ?? 12)));
$offset      = ($pagina - 1) * $por_pagina;

$where  = [];
$params = [];
$types  = '';

if ($secao === 'meus') {
    $where[]  = 'v.usuario_id = ?';
    $params[] = $usuario_id;
    $types   .= 'i';
} else {
    $where[]  = "v.status IN ('completo', 'disponivel')";
    $where[]  = 'v.em_negociacao = 0';
    $where[]  = 'v.usuario_id != ?';
    $params[] = $usuario_id;
    $types   .= 'i';
}
if ($marca_f !== '') {
    $where[]  = 'v.marca = ?';
    $params[] = $marca_f;
    $types   .= 's';
}
if ($preco_min !== null) {
    $where[]  = 'v.preco >= ?';
    $params[] = $preco_min;
    $types   .= 'd';
}
if ($preco_max !== null) {
    $where[]  = 'v.preco <= ?';
    $params[] = $preco_max;
    $types   .= 'd';
}
if ($ano_min !== null) {
    $where[]  = 'v.ano_fabrica >= ?';
    $params[] = $ano_min;
    $types   .= 'i';
}
if ($ano_max !== null) {
    $where[]  = 'v.ano_fabrica <= ?';
    $params[] = $ano_max;
    $types   .= 'i';
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM veiculos v $whereSQL");
if ($params) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_stmt->bind_result($total);
$count_stmt->fetch();
$count_stmt->close();

$total_paginas = (int) ceil($total / $por_pagina);

// Fetch vehicles
$data_params  = $params;
$data_types   = $types . 'ii';
$data_params[] = $por_pagina;
$data_params[] = $offset;

$stmt = $conn->prepare(
    "SELECT v.id, v.placa, v.marca, v.modelo, v.ano_fabrica, v.quilometragem, v.preco, v.status, v.em_negociacao, v.foto_principal,
            (SELECT COUNT(*) FROM fotos_veiculos fv WHERE fv.veiculo_id = v.id) AS total_fotos,
            u.nome AS vendedor_nome
     FROM veiculos v
     JOIN usuarios u ON u.id = v.usuario_id
     $whereSQL
     ORDER BY v.data_cadastro DESC
     LIMIT ? OFFSET ?"
);
$stmt->bind_param($data_types, ...$data_params);
$stmt->execute();
$veiculos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($veiculos)) {
    echo '<div class="col-12 text-center py-5"><p class="text-muted">Nenhum veículo encontrado.</p></div>';
    exit;
}

$upload_url = UPLOAD_URL;
foreach ($veiculos as $v):
    $foto = $v['foto_principal']
        ? htmlspecialchars($upload_url . $v['foto_principal'], ENT_QUOTES, 'UTF-8')
        : '/imagens/sem-foto.png';
    $preco = formatMoney((float) $v['preco']);
    $em_neg = $v['em_negociacao'] ? '<span class="badge bg-warning text-dark">Em negociação</span>' : '';
?>
<div class="col-12 col-sm-6 col-lg-4 col-xl-3 mb-4">
  <div class="card h-100 shadow-sm veiculo-card" data-id="<?= (int) $v['id'] ?>">
    <div class="position-relative">
      <img src="<?= $foto ?>" class="card-img-top" alt="<?= sanitize($v['marca'] . ' ' . $v['modelo']) ?>" style="height:180px;object-fit:cover">
      <?php if ($em_neg): ?>
        <div class="position-absolute top-0 end-0 p-2"><?= $em_neg ?></div>
      <?php endif; ?>
      <span class="position-absolute bottom-0 start-0 m-2 badge bg-dark">
        <i class="bi bi-images"></i> <?= (int) $v['total_fotos'] ?>
      </span>
    </div>
    <div class="card-body">
      <h6 class="card-title mb-1"><?= sanitize($v['marca'] . ' ' . $v['modelo']) ?></h6>
      <p class="text-muted small mb-1"><?= (int) $v['ano_fabrica'] ?> &bull; <?= number_format($v['quilometragem'], 0, ',', '.') ?> km</p>
      <p class="fw-bold text-danger mb-2"><?= sanitize($preco) ?></p>
      <?php if ($secao === 'oferta'): ?>
        <p class="text-muted small mb-2"><i class="bi bi-person"></i> <?= sanitize($v['vendedor_nome']) ?></p>
        <button class="btn btn-danger btn-sm w-100 btn-proposta" data-id="<?= (int) $v['id'] ?>">Fazer proposta</button>
      <?php else: ?>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary btn-sm btn-editar-veiculo" data-id="<?= (int) $v['id'] ?>"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-outline-danger btn-sm btn-remover-veiculo" data-id="<?= (int) $v['id'] ?>"><i class="bi bi-trash"></i></button>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php if ($total_paginas > 1): ?>
<div class="col-12">
  <nav aria-label="Paginação">
    <ul class="pagination justify-content-center">
      <?php if ($pagina > 1): ?>
        <li class="page-item"><a class="page-link" href="?secao=<?= sanitize($secao) ?>&pagina=<?= $pagina - 1 ?>">&laquo;</a></li>
      <?php endif; ?>
      <?php for ($p = max(1, $pagina - 2); $p <= min($total_paginas, $pagina + 2); $p++): ?>
        <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
          <a class="page-link" href="?secao=<?= sanitize($secao) ?>&pagina=<?= $p ?>"><?= $p ?></a>
        </li>
      <?php endfor; ?>
      <?php if ($pagina < $total_paginas): ?>
        <li class="page-item"><a class="page-link" href="?secao=<?= sanitize($secao) ?>&pagina=<?= $pagina + 1 ?>">&raquo;</a></li>
      <?php endif; ?>
    </ul>
  </nav>
</div>
<?php endif; ?>
