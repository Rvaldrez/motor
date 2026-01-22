<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require_once "conexao_bd.php";

$usuario_id = $_SESSION['usuario_id'];
$sql_user = "SELECT nome, email, celular, cpf, cep, endereco, cidade, estado, numero, complemento FROM usuarios WHERE id = ?";
$stmt_user = $mysqli->prepare($sql_user);
$stmt_user->bind_param("i", $usuario_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$usuario = $result_user->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Painel do Vendedor</title>
  <link rel="stylesheet" href="css/style_dashboard.css" />
  <link rel="stylesheet" href="css/propostas_recebidas.css">
  <link rel="stylesheet" href="style_veiculos.css">
  <link rel="stylesheet" href="style.css">
  <script src="js/funcoes_propostas_vendedor.js"></script>
  <script src="https://unpkg.com/compressorjs@1.2.1/dist/compressor.min.js"></script>
</head>
<body>

<!-- TOPO MOBILE -->
<div class="topbar">
  <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
  <img src="imagens/logo_motorgo_blk.png" alt="Logo MotorGo" class="logo-mobile" />
</div>

<!-- SIDEBAR -->
<aside class="sidebar">
  <img src="imagens/logo_motorgo_blk.png" alt="Logo MotorGo" class="logo" />
  <ul>
    <li onclick="showSection('dados')">Dados Pessoais</li>
    <li onclick="showSection('veiculos')">Meus Veículos</li>
    <li onclick="showSection('propostas')">Propostas Recebidas</li>
    <li onclick="showSection('cadastrarVeiculo')">Cadastrar Veículo</li>
    <li onclick="showSection('ajuda')">Ajuda</li>
    <li id="logoutLink">Sair</li>
  </ul>
</aside>


<!-- ✅ OVERLAY fora da sidebar -->
<div id="overlay" onclick="toggleSidebar()" style="display: none;"></div>


<!-- CONTEÚDO PRINCIPAL -->
<main class="main-content">
  <!-- DADOS PESSOAIS -->
  <section id="dados" class="section">
    <h2>Meus Dados</h2>
    <form id="formDados" method="POST" action="atualizar_dados.php">
      <div class="form-group"><label>Nome:</label><input type="text" name="nome" value="<?= $usuario['nome'] ?>" required></div>
      <div class="form-group"><label>Email:</label><input type="email" name="email" id="novoEmail" value="<?= $usuario['email'] ?>" required></div>
      <div class="form-group"><label>Celular:</label><input type="text" name="celular" value="<?= $usuario['celular'] ?>" required></div>
      <div class="form-group"><label>CPF:</label><input type="text" value="<?= $usuario['cpf'] ?>" readonly></div>
      <div class="form-group"><label>CEP:</label><input type="text" name="cep" id="cep" value="<?= $usuario['cep'] ?>" required></div>
      <div class="form-group"><label>Endereço:</label><input type="text" name="endereco" id="endereco" value="<?= $usuario['endereco'] ?>" required></div>
      <div class="form-row">
        <div class="form-group half"><label>Número:</label><input type="text" name="numero" value="<?= $usuario['numero'] ?>"></div>
        <div class="form-group half"><label>Complemento:</label><input type="text" name="complemento" value="<?= $usuario['complemento'] ?>"></div>
      </div>
      <div class="form-group"><label>Cidade:</label><input type="text" name="cidade" id="cidade" value="<?= $usuario['cidade'] ?>" required></div>
      <div class="form-group"><label>Estado:</label><input type="text" name="estado" id="estado" value="<?= $usuario['estado'] ?>" required></div>
      <button type="submit" class="btn-vermelho">Salvar Alterações</button>
    </form>
  </section>

  <!-- MEUS VEÍCULOS -->
  <section id="veiculos" class="section">
    <h2>Meus Veículos</h2>
    <div class="vehicle-list">
      <?php
      $sql_veiculos = "SELECT v.id, v.placa, v.modelo, v.ano_fabrica, v.quilometragem, v.marca, f.caminho_foto
                       FROM veiculos v
                       LEFT JOIN fotos_veiculos f ON v.id = f.veiculo_id
                       WHERE v.usuario_id = ?
                       GROUP BY v.id";
      $stmt_veiculos = $mysqli->prepare($sql_veiculos);
      $stmt_veiculos->bind_param("i", $usuario_id);
      $stmt_veiculos->execute();
      $result_veiculos = $stmt_veiculos->get_result();

      while ($veiculo = $result_veiculos->fetch_assoc()): ?>
        <div class="vehicle-card" data-id="<?= $veiculo['id'] ?>">
          <img src="<?= $veiculo['caminho_foto'] ?: 'imagens/default_car.png' ?>" alt="Foto do Veículo">
          <div class="vehicle-info">
            <h3><?= $veiculo['modelo'] ?> (<?= $veiculo['ano_fabrica'] ?>)</h3>
            <p><strong>Placa:</strong> <?= $veiculo['placa'] ?></p>
            <p><strong>Marca:</strong> <?= $veiculo['marca'] ?></p>
            <p><strong>KM:</strong> <?= number_format($veiculo['quilometragem'], 0, '', '.') ?> km</p>
            <div class="botoes-veiculo">
            <button onclick="abrirEdicaoVeiculo(<?= $veiculo['id'] ?>)" class="btn-editar">Editar</button>
              <button onclick="removerVeiculo(<?= $veiculo['id'] ?>)" class="btn-remover">Remover</button>

            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  </section>


  <!-- PROPOSTAS -->
  <section id="propostas" class="section">
  <h2>Propostas Recebidas</h2>
  <div id="containerPropostasRecebidas">
    <p>Carregando propostas...</p>
  </div>
</section>

<script>
function carregarPropostasRecebidas() {
  const container = document.getElementById("containerPropostasRecebidas");
  container.innerHTML = "<p>Carregando propostas...</p>";

  fetch("listar_propostas_recebidas.php")
    .then(res => res.text())
    .then(html => {
      container.innerHTML = html;
    })
    .catch(() => {
      container.innerHTML = "<p style='color:red;'>Erro ao carregar propostas.</p>";
    });
}

// ✅ Carrega ao abrir a aba de propostas
document.querySelector('li[onclick="showSection(\'propostas\')"]').addEventListener("click", carregarPropostasRecebidas);
</script>


  <!-- AJUDA -->
  <section id="ajuda" class="section">
    <h2>Fale com o Suporte</h2>
    <form action="enviar_ajuda.php" method="POST" id="formDados">
      <div class="form-group"><label>Nome:</label><input type="text" name="nome" value="<?= $usuario['nome'] ?>" required></div>
      <div class="form-group"><label>Email:</label><input type="email" name="email" value="<?= $usuario['email'] ?>" required></div>
      <div class="form-group"><label>Mensagem:</label><textarea name="mensagem" rows="5" required></textarea></div>
      <button type="submit" class="btn-vermelho">Enviar</button>
    </form>
  </section>

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
</main>


<!-- EDITAR VEÍCULO -->
<section id="editarVeiculo" class="section">
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

    <div class="form-group">
      <label>Fotos:</label>
      <div class="foto-grid" id="editar_fotos_grid">
        <!-- As fotos serão carregadas via JS -->
      </div>
    </div>

    <button type="submit" class="btn-vermelho">Salvar Alterações</button>
    <button type="button" onclick="showSection('veiculos')" class="btn-vermelho" style="background-color: #555;">Cancelar</button>
  </form>
</section>






<!-- SCRIPTS -->
<script>
function toggleSidebar() {
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.getElementById('overlay');

  const isOpen = sidebar.classList.toggle('active');
  if (window.innerWidth <= 768) {
    overlay.style.display = isOpen ? 'block' : 'none';
  }
}

function showSection(sectionId) {
  document.querySelectorAll('.section').forEach(sec => sec.classList.remove('active'));
  document.getElementById(sectionId).classList.add('active');

  if (window.innerWidth <= 768) {
    document.querySelector('.sidebar').classList.remove('active');
    document.getElementById('overlay').style.display = 'none';
  }
}

document.getElementById("logoutLink").addEventListener("click", () => {
  window.location.href = "logout.php";
});

function removerVeiculo(id) {
  if (!confirm("Deseja remover este veículo?")) return;
  fetch("remover_veiculo.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({ id: id })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) location.reload();
    else alert("Erro ao remover veículo.");
  });
}

function carregarModelosPainel() {
  const marca = document.getElementById('marcaPainel').value;
  fetch(`carregar_modelos.php?marca_id=${marca}`)
    .then(res => res.json())
    .then(modelos => {
      const select = document.getElementById('modeloPainel');
      select.innerHTML = '<option value="">Selecione um Modelo</option>';
      modelos.forEach(m => {
        select.innerHTML += `<option value="${m.id}">${m.nome}</option>`;
      });
    });
}

function carregarAnoPainel() {
  const marca = document.getElementById('marcaPainel').value;
  const modelo = document.getElementById('modeloPainel').value;
  fetch(`carregar_ano.php?marca_id=${marca}&modelo_id=${modelo}`)
    .then(res => res.json())
    .then(anos => {
      const select = document.getElementById('anoPainel');
      select.innerHTML = '<option value="">Selecione o Ano</option>';
      anos.forEach(a => {
        select.innerHTML += `<option value="${a.ano}">${a.ano}</option>`;
      });
    });
}

function carregarPrecoPainel() {
  const modelo = document.getElementById('modeloPainel').value;
  const ano = document.getElementById('anoPainel').value;
  fetch(`carregar_preco.php?modelo_id=${modelo}&ano=${ano}`)
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        document.getElementById('precoPainel').value = data.preco;
      }
    });
}

function mostrarMiniatura(event, fotoId) {
  const input = document.getElementById(fotoId);
  const file = input.files[0];
  const miniatura = document.getElementById(`miniatura-${fotoId}`);
  const container = input.closest(".camera-upload");
  const cameraIcon = container.querySelector('.camera-icon');

  if (!file) return;

  new Compressor(file, {
    quality: 0.6,
    success(compressed) {
      const reader = new FileReader();
      reader.onload = function (e) {
        miniatura.innerHTML = `<img src="${e.target.result}" class="foto-preview" alt="Miniatura">`;
        miniatura.style.display = 'flex';
        cameraIcon.style.display = 'none';
      };
      reader.readAsDataURL(compressed);
    },
    error(err) {
      alert("Erro ao processar imagem: " + err.message);
    }
  });
}

function enviarVeiculoDoPainel() {
  const form = document.getElementById('formVeiculoPainel');
  const placa = form.querySelector('input[name="placa"]').value.trim();
  const marca = document.getElementById('marcaPainel').value;
  const modelo = document.getElementById('modeloPainel').value;
  const ano = document.getElementById('anoPainel').value;
  const quilometragem = document.getElementById('kmPainel').value.trim();

  // ✅ Valida campos obrigatórios
  if (!placa) {
    mostrarPopup("⚠️ Informe a placa do veículo.");
    return;
  }

  if (!marca) {
    mostrarPopup("⚠️ Selecione a marca do veículo.");
    return;
  }

  if (!modelo) {
    mostrarPopup("⚠️ Selecione o modelo do veículo.");
    return;
  }

  if (!ano) {
    mostrarPopup("⚠️ Selecione o ano do veículo.");
    return;
  }

  if (!quilometragem || quilometragem === '0') {
    mostrarPopup("⚠️ Informe a quilometragem.");
    return;
  }

  // ✅ Valida as 6 fotos
  let todasFotosSelecionadas = true;
  for (let i = 1; i <= 6; i++) {
    const inputFoto = document.getElementById(`foto${i}Painel`);
    const temImagem = inputFoto && (
      inputFoto.compressedFile || (inputFoto.files && inputFoto.files.length > 0)
    );

    if (!temImagem) {
      todasFotosSelecionadas = false;
      break;
    }
  }

  if (!todasFotosSelecionadas) {
    mostrarPopup("⚠️ Por favor, envie as 6 fotos do veículo.");
    return;
  }

  // ✅ Monta o FormData
  const formData = new FormData();
  form.querySelectorAll("input, select").forEach(input => {
    if (input.name && input.type !== "file") {
      formData.append(input.name, input.value);
    }
  });

  formData.append("usuario_id", <?= $_SESSION['usuario_id'] ?>);

  for (let i = 1; i <= 6; i++) {
    const input = document.getElementById(`foto${i}Painel`);
    if (input && input.compressedFile) {
      formData.append(`foto${i}`, input.compressedFile);
    } else if (input && input.files.length > 0) {
      formData.append(`foto${i}`, input.files[0]);
    }
  }


  // ✅ Envia via fetch
  document.getElementById("loader").style.display = "flex";

const botao = document.querySelector('#formVeiculoPainel button');
botao.disabled = true;

fetch('processa_cadastro_veiculo.php', {
  method: 'POST',
  body: formData
})
.then(res => res.json())
.then(data => {
  if (data.success) {
    mostrarPopup("✅ Veículo cadastrado com sucesso!");
    setTimeout(() => {
      localStorage.setItem("secao_ativa", "veiculos");
      location.reload(); // Não precisa esconder loader aqui
    }, 1500);
  } else {
    botao.disabled = false;
    document.getElementById("loader").style.display = "none";
    mostrarPopup("❌ Erro ao cadastrar veículo: " + (data.message || "erro desconhecido."));
  }
})
.catch(() => {
  botao.disabled = false;
  document.getElementById("loader").style.display = "none";
  mostrarPopup("❌ Erro de conexão. Tente novamente.");
});



}




document.querySelector('li[onclick="showSection(\'cadastrarVeiculo\')"]').addEventListener("click", () => {
  fetch('carregar_marcas.php')
    .then(res => res.json())
    .then(marcas => {
      const select = document.getElementById('marcaPainel');
      select.innerHTML = '<option value="">Selecione uma Marca</option>';
      marcas.forEach(m => {
        select.innerHTML += `<option value="${m.id}">${m.nome}</option>`;
      });
    });
});


document.getElementById("formDados").addEventListener("submit", function (e) {
  e.preventDefault();

  const form = e.target;
  const formData = new FormData(form);

  fetch("atualizar_dados.php", {
    method: "POST",
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
        mostrarPopup(data.message || "✅ Dados atualizados com sucesso!");
    } else {
        mostrarPopup("❌ " + (data.message || "Ocorreu um erro ao atualizar os dados."));
    }
  })
  .catch(() => {
    mostrarPopup("❌ Erro de conexão. Tente novamente mais tarde.");
  });
});


function mostrarPopup(mensagem) {
  document.getElementById('popupTexto').innerText = mensagem;
  document.getElementById('popupMensagem').style.display = 'flex';
}

function fecharPopup() {
  document.getElementById('popupMensagem').style.display = 'none';
}


document.getElementById('cep').addEventListener('blur', function () {
  const cep = this.value.replace(/\D/g, '');

  if (cep.length !== 8) {
    mostrarPopup("⚠️ CEP inválido. Digite os 8 números sem traço.");
    return;
  }

  fetch(`https://viacep.com.br/ws/${cep}/json/`)
    .then(res => res.json())
    .then(data => {
      if (data.erro) {
        mostrarPopup("❌ CEP não encontrado.");
        return;
      }

      // Preenche os campos
      document.getElementById('endereco').value = data.logradouro || '';
      document.getElementById('cidade').value = data.localidade || '';
      document.getElementById('estado').value = data.uf || '';
    })
    .catch(() => {
      mostrarPopup("❌ Erro ao buscar o endereço. Tente novamente.");
    });
});


// Máscara de CEP: 00000-000
document.getElementById('cep').addEventListener('input', function (e) {
  let value = e.target.value.replace(/\D/g, '').slice(0, 8);
  if (value.length > 5) {
    value = value.slice(0, 5) + '-' + value.slice(5);
  }
  e.target.value = value;
});


window.addEventListener("DOMContentLoaded", function () {
  const urlParams = new URLSearchParams(window.location.search);
  const msg = urlParams.get("mensagem");

  if (msg === "veiculo_atualizado") {
    mostrarPopup("✅ Veículo atualizado com sucesso!");
    history.replaceState(null, "", window.location.pathname);
  }

  // ✅ Reabre a última seção ativa se existir
  const secaoAtiva = localStorage.getItem("secao_ativa");
  if (secaoAtiva && document.getElementById(secaoAtiva)) {
    showSection(secaoAtiva);
    localStorage.removeItem("secao_ativa"); // limpa após usar
  } else {
    showSection("dados"); // fallback se nada estiver salvo
  }

  // ✅ Remove hashes da URL como #propostas
  history.replaceState(null, "", window.location.pathname);

  // ✅ Máscara de milhar
  const campoKmPainel = document.getElementById('kmPainel');
  if (campoKmPainel) {
    campoKmPainel.addEventListener('input', function () {
      let valor = this.value.replace(/\D/g, '');
      this.value = valor.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    });
  }

  // ✅ Letras da placa sempre maiúsculas
  const campoPlaca = document.getElementById('placaPainel');
  if (campoPlaca) {
    campoPlaca.addEventListener('input', function () {
      this.value = this.value.toUpperCase();
    });
  }
});





function abrirEdicaoVeiculo(id) {
  fetch("carregar_veiculo.php?id=" + id)
    .then(res => res.json())
    .then(data => {
      if (!data.success) {
        mostrarPopup("❌ Veículo não encontrado.");
        return;
      }

      const v = data.veiculo;

      document.getElementById('editar_veiculo_id').value = v.id;
      document.getElementById('editar_placa').value = v.placa;
      document.getElementById('editar_marca').value = v.marca;
      document.getElementById('editar_modelo').value = v.modelo;
      document.getElementById('editar_ano_fabrica').value = v.ano_fabrica;

      // ✅ Corrigido: remove casas decimais com Math.floor e formata com separadores
      const kmNumerico = Math.floor(Number(v.quilometragem));
      const campoKm = document.getElementById('editar_km');
      campoKm.value = kmNumerico.toLocaleString('pt-BR');

      // ✅ Máscara dinâmica de milhar
      campoKm.addEventListener('input', function () {
        let valor = this.value.replace(/\D/g, '');
        this.value = valor.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      });

      // ✅ Carregamento das fotos
      const fotosGrid = document.getElementById('editar_fotos_grid');
      fotosGrid.innerHTML = '';
      for (let i = 0; i < 6; i++) {
  const img = v.fotos[i] || 'imagens/default_car.png';

  const label = document.createElement('label');
  label.classList.add('foto-box');

  const preview = document.createElement('img');
  preview.classList.add('foto-preview');
  preview.id = 'editar_preview' + i;
  preview.src = img;

  const input = document.createElement('input');
  input.type = 'file';
  input.name = 'foto' + i;
  input.accept = 'image/*';
  input.style.display = 'none';

  // ✅ CORRETO: só esse listener, sem click duplicado
  input.addEventListener('change', function () {
    previewFotoEditar(this, i);
  });

  // ✅ SEM o label.click() duplicado
  label.appendChild(preview);
  label.appendChild(input);

  fotosGrid.appendChild(label);
}



      showSection('editarVeiculo');
    })
    .catch(() => {
      mostrarPopup("❌ Erro ao carregar veículo.");
    });
}






document.getElementById("formEditarVeiculo").addEventListener("submit", function(e) {
  e.preventDefault();

  const form = e.target;
  const veiculoId = document.getElementById('editar_veiculo_id').value;
  const formData = new FormData();

  // Campos de texto
  formData.append("veiculo_id", veiculoId);
  formData.append("quilometragem", document.getElementById('editar_km').value.replace(/\D/g, ''));
  formData.append("usuario_id", <?= $_SESSION['usuario_id'] ?>);

  // Imagens modificadas
  for (let i = 0; i < 6; i++) {
    const input = form.querySelector(`input[name="foto${i}"]`);
    if (input && input.getAttribute('data-modificado') === '1' && input.compressedFile) {
      formData.append(`foto${i}`, input.compressedFile);
    }
  }

  fetch("salvar_edicao_veiculo.php", {
    method: "POST",
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      mostrarPopup("✅ Veículo atualizado com sucesso!");

      fetch("carregar_veiculo.php?id=" + veiculoId)
        .then(res => res.json())
        .then(info => {
          if (info.success) {
            abrirEdicaoVeiculo(veiculoId);
            atualizarCardVeiculo(info.veiculo);
          }
        });
    } else {
      mostrarPopup("❌ " + (data.message || "Erro ao atualizar veículo."));
    }
  })
  .catch(() => {
    mostrarPopup("❌ Erro de conexão ao salvar veículo.");
  });
});


function previewFotoEditar(input, index) {
  const file = input.files[0];
  if (!file) return;

  new Compressor(file, {
    quality: 0.6,
    success(compressed) {
      // Armazena o arquivo comprimido numa propriedade customizada
      input.compressedFile = new File([compressed], file.name, { type: file.type });
      input.setAttribute('data-modificado', '1');

      const reader = new FileReader();
      reader.onload = function (e) {
        const previewImg = document.getElementById("editar_preview" + index);
        previewImg.src = e.target.result;
      };
      reader.readAsDataURL(compressed);
    },
    error(err) {
      mostrarPopup("❌ Erro ao processar imagem: " + err.message);
    }
  });
}





function atualizarCardVeiculo(veiculo) {
  const card = document.querySelector(`.vehicle-card[data-id='${veiculo.id}']`);
  if (!card) return;

  // Atualiza dados do card
  const img = card.querySelector("img");
  img.src = (veiculo.caminho_foto || 'imagens/default_car.png') + '?t=' + new Date().getTime();


  card.querySelector("h3").innerText = `${veiculo.modelo} (${veiculo.ano_fabrica})`;
  card.querySelector("p:nth-of-type(1)").innerHTML = `<strong>Placa:</strong> ${veiculo.placa}`;
  card.querySelector("p:nth-of-type(2)").innerHTML = `<strong>Marca:</strong> ${veiculo.marca}`;
  card.querySelector("p:nth-of-type(3)").innerHTML = `<strong>KM:</strong> ${Number(veiculo.quilometragem).toLocaleString('pt-BR')} km`;
}





</script>


<div id="popupMensagem" class="popup-mensagem" style="display: none;">
  <div class="popup-conteudo">
    <span id="popupTexto"></span>
    <button onclick="fecharPopup()" class="btn-vermelho btn-fechar">Fechar</button>
  </div>
</div>

<!-- LOADER -->
<div id="loader" class="loader-overlay">
  <div class="loader-content">
    <div class="spinner"></div>
    <p class="loader-text">Aguarde, enviando informações...</p>
  </div>
</div>





</body>
</html>
