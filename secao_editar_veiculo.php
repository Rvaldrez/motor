<!-- EDITAR VEÍCULO -->
<section id="edicaoVeiculo" class="section">
  <h2>Editar Veículo</h2>

  <form id="formEditarVeiculo" enctype="multipart/form-data">
    <input type="hidden" name="veiculo_id" id="editar_veiculo_id">

    <div class="form-group">
      <label>Placa:</label>
      <input type="text" name="placa" id="editar_placa" readonly>
    </div>

    <div class="form-group">
      <label>Marca:</label>
      <input type="text" name="marca" id="editar_marca" readonly>
    </div>

    <div class="form-group">
      <label>Modelo:</label>
      <input type="text" name="modelo" id="editar_modelo" readonly>
    </div>

    <div class="form-group">
      <label>Ano:</label>
      <input type="text" name="ano_fabrica" id="editar_ano_fabrica" readonly>
    </div>

    <div class="form-group">
      <label>Quilometragem:</label>
      <input type="text" name="quilometragem" id="editar_km" required>
    </div>

    <div class="upload-foto">
      <label>Fotos:</label>
      <div class="foto-grid" id="editar_fotos_grid">
        <!-- As fotos serão carregadas via JS -->
      </div>
    </div> <!-- ✅ Fechamento correto da div.upload-foto -->

    <div class="botoes-edicao">
      <button type="submit" class="btn-vermelho">Salvar Alterações</button>
      <button type="button" onclick="showSection('meusVeiculos')" class="btn-vermelho" style="background-color: #555;">Cancelar</button>
    </div>
  </form>
</section>
