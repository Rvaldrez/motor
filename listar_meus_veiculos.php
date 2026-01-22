<?php
session_start();
require_once "conexao_bd.php";

// ✅ Verifica se o usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
  http_response_code(403);
  exit;
}



// ✅ Libera o bloqueio da sessão para permitir múltiplas requisições simultâneas
session_write_close();

$usuario_id = $_SESSION['usuario_id'];

$sql_veiculos = "SELECT v.id, v.placa, v.modelo, v.ano_fabrica, v.quilometragem, v.marca,
u.cidade AS usuario_cidade, u.estado AS usuario_estado,
f.caminho_foto
FROM veiculos v
LEFT JOIN fotos_veiculos f ON v.id = f.veiculo_id
LEFT JOIN usuarios u ON v.usuario_id = u.id
WHERE v.usuario_id = ?
GROUP BY v.id";
    $stmt_veiculos = $mysqli->prepare($sql_veiculos);
    $stmt_veiculos->bind_param("i", $usuario_id);
    $stmt_veiculos->execute();
    $result_veiculos = $stmt_veiculos->get_result();

    while ($veiculo = $result_veiculos->fetch_assoc()): ?>
     <div class="card-veiculo" data-id="<?= $veiculo['id'] ?>">
        <img src="<?= $veiculo['caminho_foto'] ?: 'imagens/default_car.png' ?>" alt="Foto do Veículo" class="card-veiculo-img">
        <div class="card-veiculo-info">
          <h3 class="card-veiculo-modelo"><?= $veiculo['modelo'] ?></h3>
          <p><strong>Placa:</strong> <?= $veiculo['placa'] ?></p>
          <p><strong>Marca:</strong> <?= $veiculo['marca'] ?></p>
          <p><strong>Ano Modelo:</strong> <?= htmlspecialchars($veiculo['ano_fabrica']) ?></p>
          <p><strong>KM:</strong> <?= number_format($veiculo['quilometragem'], 0, '', '.') ?> km</p>
          <p><strong>Local:</strong>
            <?= htmlspecialchars($veiculo['usuario_cidade']) ?>/<?= htmlspecialchars($veiculo['usuario_estado']) ?>
          </p>
          <div class="card-veiculo-botoes">
            <button class="card-veiculo-btn-editar" data-id="<?= $veiculo['id'] ?>">Editar</button>
            <button onclick="removerVeiculo(<?= $veiculo['id'] ?>)" class="card-veiculo-btn-remover">Remover</button>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
</section>







      
 
