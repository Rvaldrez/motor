
<!-- CADASTRAR VEÍCULO -->
<section id="cadastrarVeiculo" class="section">
    <h2>Cadastrar Novo Veículo</h2>
    <form id="formVeiculoPainel" enctype="multipart/form-data">
      <div class="form-group"><input type="text" name="placa" placeholder="Placa" id="placaPainel" required></div>
      <div class="form-group"><select name="marca" id="marcaPainel" required onchange="carregarModelosPainel()"></select></div>
      <div class="form-group"><select name="modelo" id="modeloPainel" required onchange="carregarAnoPainel()"></select></div>
      <div class="form-group"><select name="ano_fabrica" id="anoPainel" required onchange="carregarPrecoPainel()"></select></div>
      <div class="form-group"><input type="text" name="quilometragem" id="kmPainel" placeholder="Quilometragem" required></div>
      <input type="hidden" name="preco" id="precoPainel">

      <div class="upload-foto">
        <p>Envie 6 fotos:</p>
        <div class="foto-grid">
        <?php for ($i = 1; $i <= 6; $i++): ?>
  <div class="camera-upload" onclick="document.getElementById('foto<?= $i ?>Painel').click()">
  <input type="file" name="foto<?= $i ?>" id="foto<?= $i ?>Painel" accept="image/*" onchange="mostrarMiniatura(event, 'foto<?= $i ?>Painel')">

    <div class="miniatura" id="miniatura-foto<?= $i ?>Painel"></div>
    <img src="imagens/camera.png" class="camera-icon" />
  </div>
<?php endfor; ?>
        </div>
      </div>

      <button type="button" class="btn-vermelho" onclick="enviarVeiculoDoPainel()">Cadastrar Veículo</button>
    </form>
  </section>


