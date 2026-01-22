<?php
session_start();

// Verifica se o usuário está logado e se é investidor
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'investidor') {
 header("Location: login.php");
    exit;
}

require_once "conexao_bd.php";

// Busca dados do usuário
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
<script>
  const USUARIO_ID = <?= json_encode($usuario_id); ?>;
</script>



<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512..." crossorigin="anonymous" referrerpolicy="no-referrer" />

<!-- Chart.js -->
<script src="js/chart.min.js"></script>

<!-- Fonte moderna -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">



  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Painel do Investidor</title>
  <link rel="stylesheet" href="css/style_painel_investidor.css" />
  <link rel="stylesheet" href="css/propostas_recebidas.css"/>
  <link rel="stylesheet" href="css/style_dashboard.css"/>
  <link rel="stylesheet" href="style_veiculos.css"/>


  <script src="https://unpkg.com/compressorjs@1.2.1/dist/compressor.min.js"></script>
 


</head>
<body>

<!-- TOPO MOBILE -->
<div class="topbar-investidor">
  <div class="topbar-left">
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
  </div>
  <div class="topbar-center">
    <img src="imagens/logo_motorgo_blk.png" alt="Logo MotorGo" class="logo-mobile" />
  </div>
  <div class="topbar-right"></div>
</div>





<!-- OVERLAY PARA MOBILE -->
<div id="overlay" onclick="fecharSidebar()" style="display: none;"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <img src="imagens/logo_motorgo_blk.png" alt="Logo MotorGo" class="logo" />
  <ul class="menu">
    <li onclick="showSection('painel')">Painel</li>

    <li class="menu-item">
      <span class="menu-toggle-sub">
        <i class="fa-solid fa-chevron-right seta-submenu"></i>
        Comprar Veículos
      </span>
      <ul class="submenu">
        <li onclick="showSection('ofertaVeiculos')">Oferta de Veículos</li>
        <li onclick="showSection('propostasEnviadas')">Propostas Enviadas</li>
      </ul>
    </li>

    <li class="menu-item">
      <span class="menu-toggle-sub">
        <i class="fa-solid fa-chevron-right seta-submenu"></i>
        Vender Veículos
      </span>
      <ul class="submenu">
        <li onclick="showSection('cadastrarVeiculo')">Cadastrar Veículo</li>
        <li onclick="showSection('meusVeiculos')">Meus Veículos</li>
        <li onclick="showSection('propostasRecebidas')">Propostas Recebidas</li>
      </ul>
    </li>

    <li onclick="showSection('ajuda')">Ajuda</li>
    <li id="logoutLink">Sair</li>
  </ul>
</aside>



<!-- CONTEÚDO PRINCIPAL -->
<main class="main-content">
<!-- DASHBOARD - Painel -->
<section id="painel" class="section active">
  <h2>Resumo do Investidor</h2>

  <?php
  
    // Total de primeiras propostas (enviadas)
    $sqlEnviadas = "
    SELECT COUNT(*) FROM (
      SELECT MIN(id) 
      FROM propostas 
      WHERE usuario_id = ? AND status != 'historico'
      GROUP BY veiculo_id
    ) as primeiras
  ";
  
    $stmt = $mysqli->prepare($sqlEnviadas);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $stmt->bind_result($totalEnviadas);
    $stmt->fetch();
    $stmt->close();
    
    // Total de contrapropostas (as demais)
    $sqlContrapropostas = "
    SELECT COUNT(*) FROM propostas 
    WHERE usuario_id = ? 
    AND id NOT IN (
      SELECT MIN(id)
      FROM propostas 
      WHERE usuario_id = ? AND status != 'historico'
      GROUP BY veiculo_id
    )
    AND status != 'historico'
  ";
  
    $stmt = $mysqli->prepare($sqlContrapropostas);
    $stmt->bind_param("ii", $usuario_id, $usuario_id);
    $stmt->execute();
    $stmt->bind_result($totalContrapropostas);
    $stmt->fetch();
    $stmt->close();
    
    // Aceitas
    $sqlAceitas = "SELECT COUNT(*) FROM propostas WHERE usuario_id = ? AND status = 'aceita'";
    $stmt = $mysqli->prepare($sqlAceitas);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $stmt->bind_result($propostasAceitas);
    $stmt->fetch();
    $stmt->close();
    
    // Recusadas
    $sqlRecusadas = "SELECT COUNT(*) FROM propostas WHERE usuario_id = ? AND status = 'recusada'";
    $stmt = $mysqli->prepare($sqlRecusadas);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $stmt->bind_result($propostasRecusadas);
    $stmt->fetch();
    $stmt->close();
    
    // Média das aceitas
    $sqlMedia = "SELECT AVG(valor) FROM propostas WHERE usuario_id = ? AND status = 'aceita'";
    $stmt = $mysqli->prepare($sqlMedia);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $stmt->bind_result($mediaValor);
    $stmt->fetch();
    $stmt->close();
    ?>
    

    <div class="dashboard-grid">
  <div class="dashboard-card">
    <i class="fa-solid fa-paper-plane icone-card"></i>
    <h3>Propostas Enviadas</h3>
    <p><?= $totalEnviadas ?></p>
  </div>

  <div class="dashboard-card">
    <i class="fa-solid fa-arrows-rotate icone-card"></i>
    <h3>Contrapropostas</h3>
    <p><?= $totalContrapropostas ?></p>
  </div>

  <div class="dashboard-card">
    <i class="fa-solid fa-circle-check icone-card"></i>
    <h3>Aceitas</h3>
    <p><?= $propostasAceitas ?></p>
  </div>

  <div class="dashboard-card">
    <i class="fa-solid fa-circle-xmark icone-card"></i>
    <h3>Recusadas</h3>
    <p><?= $propostasRecusadas ?></p>
  </div>
</div>


  <!-- Barra de progresso -->

  <?php
  $totalPropostas = $totalEnviadas + $totalContrapropostas;
  $aceitacao = $totalPropostas > 0
      ? round(($propostasAceitas / $totalPropostas) * 100)
      : 0;
?>

  <div class="progresso-box">
    <span>Taxa de Aceitação: <strong><?= $aceitacao ?>%</strong></span>
    <div class="barra-progresso">
      <div class="preenchido" style="width: <?= $aceitacao ?>%;"></div>
    </div>
  </div>

  <!-- Gráfico de pizza -->
  <div class="grafico-container">
  <canvas
    id="graficoPropostas"
    data-enviadas="<?= $totalEnviadas ?>"
    data-contrapropostas="<?= $totalContrapropostas ?>"
    data-aceitas="<?= $propostasAceitas ?>"
    data-recusadas="<?= $propostasRecusadas ?>"
    width="400"
    height="220"
  ></canvas>
</div>


</section>



<!-- OFERTA DE VEÍCULOS -->
<section id="ofertaVeiculos" class="section">
  <h2>Oferta de Veículos</h2>

  <!-- FILTROS -->
  <form id="filterForm">
    <div class="filtro-linha">
      <div class="form-group">
        <label for="filtroMarca">Marca:</label>
        <select name="marca" id="filtroMarca"></select>
      </div>

      <div class="form-group">
        <label for="filtroAnoDe">Ano de:</label>
        <input type="number" name="filtro_ano_de" id="filtroAnoDe" placeholder="Ex: 2015" />
      </div>

      <div class="form-group">
        <label for="filtroAnoAte">Ano até:</label>
        <input type="number" name="filtro_ano_ate" id="filtroAnoAte" placeholder="Ex: 2025" />
      </div>

      <div class="form-group">
        <label for="filtroPreco">Faixa de Preço:</label>
        <select name="preco" id="filtroPreco">
          <option value="">Todas</option>
          <option value="1">Até R$ 20.000</option>
          <option value="2">R$ 20.000 a R$ 50.000</option>
          <option value="3">R$ 50.000 a R$ 100.000</option>
          <option value="4">Acima de R$ 100.000</option>
        </select>
      </div>

      <!-- Botão SEM .btn-vermelho -->
      <div class="botao-filtrar">
        <button type="button" onclick="carregarOfertaVeiculos()">Filtrar</button>
      </div>
    </div>
  </form>





  <div class="oferta-list" id="listaOfertaVeiculos">
    <!-- Os cards serão carregados aqui via JS/AJAX -->
  </div>
</section>





<!-- PROPOSTAS ENVIADAS -->
<section id="propostasEnviadas" class="section">
  <h2>Propostas Enviadas</h2>

  <!-- ✅ Container vazio para carregar o conteúdo via JS -->
  <div id="listaPropostasEnviadas">
    <!-- Conteúdo será carregado com fetch('listar_propostas_enviadas.php') -->
  </div>
</section>


<!-- PROPOSTAS RECEBIDAS -->
<section id="propostasRecebidas" class="section">
  <h2>Propostas Recebidas</h2>
  <div class="proposal-list" id="listaPropostasRecebidas">
    <!-- Propostas recebidas serão carregadas via JS -->
  </div>
</section>

<!-- ✅ INCLUDE das seções de "Vender Veículo" -->
<?php include 'secao_cadastrar_veiculo.php'; ?>
<?php include 'secao_meus_veiculos.php'; ?>
<?php include 'secao_editar_veiculo.php'; ?>


<!-- AJUDA -->
<section id="ajuda" class="section">
  <h2>Fale com o Suporte</h2>
  <form action="enviar_ajuda.php" method="POST">
    <div class="form-group"><label>Nome:</label><input type="text" name="nome" value="<?= $usuario['nome'] ?>" required></div>
    <div class="form-group"><label>Email:</label><input type="email" name="email" value="<?= $usuario['email'] ?>" required></div>
    <div class="form-group"><label>Mensagem:</label><textarea name="mensagem" rows="5" required></textarea></div>
    <button type="submit" class="btn-vermelho">Enviar</button>
  </form>
</section>

<!-- 🔚 FIM DO MAIN -->
</main>

<!-- POPUP DE MENSAGEM -->
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
    <p class="loader-text">Aguarde, carregando informações...</p>
  </div>
</div>

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

function mostrarPopup(mensagem) {
  document.getElementById('popupTexto').innerText = mensagem;
  document.getElementById('popupMensagem').style.display = 'flex';
}

function fecharPopup() {
  document.getElementById('popupMensagem').style.display = 'none';
}

// Máscara de CEP
document.addEventListener('DOMContentLoaded', () => {
  const cepInput = document.getElementById('cep');
  if (cepInput) {
    cepInput.addEventListener('input', function (e) {
      let value = e.target.value.replace(/\D/g, '').slice(0, 8);
      if (value.length > 5) value = value.slice(0, 5) + '-' + value.slice(5);
      e.target.value = value;
    });
  }

  // KM - milhar
  const kmInput = document.getElementById('kmPainel');
  if (kmInput) {
    kmInput.addEventListener('input', function () {
      let valor = this.value.replace(/\D/g, '');
      this.value = valor.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    });
  }

  // Letras da placa maiúsculas
  const placa = document.getElementById('placaPainel');
  if (placa) {
    placa.addEventListener('input', function () {
      this.value = this.value.toUpperCase();
    });
  }

  // Logout
  const logoutBtn = document.getElementById("logoutLink");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", () => {
      window.location.href = "logout.php";
    });
  }

// Carregar marcas no filtro
fetch('carregar_marcas_disponiveis.php')
  .then(res => res.json())
  .then(marcas => {
    const selectMarca = document.getElementById('filtroMarca');
    if (!selectMarca) return;
    selectMarca.innerHTML = '<option value="">Todas</option>';
    marcas.forEach(marca => {
      selectMarca.innerHTML += `<option value="${marca}">${marca}</option>`;
    });
  });




  // Carrega lista inicial de veículos
  carregarOfertaVeiculos();
});

function carregarOfertaVeiculos() {
  const marca = document.getElementById('filtroMarca')?.value || '';
  const ano_de = document.getElementById('filtroAnoDe')?.value || '';
  const ano_ate = document.getElementById('filtroAnoAte')?.value || '';
  const preco = document.getElementById('filtroPreco')?.value || '';

  const lista = document.getElementById('listaOfertaVeiculos');
  lista.innerHTML = '<p>Carregando veículos...</p>';

  fetch('listar_veiculos_disponiveis.php', {
    method: 'POST',
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({ marca, ano_de, ano_ate, preco })
  })
    .then(res => res.text())
    .then(html => {
      lista.innerHTML = html;
    })
    .catch(() => {
      lista.innerHTML = '<p style="color:red;">Erro ao carregar veículos.</p>';
    });
}



// Trocar imagem principal
function trocarImagem(id, novaImagem) {
  const img = document.getElementById("mainImage" + id);
  if (img) img.src = novaImagem;
}

// Ampliar imagem em modal
function ampliarImagem(src) {
  const popup = document.createElement("div");
  popup.style.cssText = 
    position:fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.8); display:flex; align-items:center; justify-content:center; z-index:9999;
  ;
  const img = document.createElement("img");
  img.src = src;
  img.style.maxWidth = "90%";
  img.style.maxHeight = "90%";
  img.style.border = "4px solid #fff";
  img.style.borderRadius = "8px";
  popup.appendChild(img);
  popup.onclick = () => popup.remove();
  document.body.appendChild(popup);
}


document.addEventListener("input", function (e) {
  if (e.target.classList.contains("mascara-valor")) {
    let valor = e.target.value.replace(/\D/g, "");
    valor = (valor / 100).toFixed(2) + "";
    valor = valor.replace(".", ",").replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    e.target.value = "R$ " + valor;
  }
});





</script>
<script src="js/funcoes_propostas_vendedor.js"></script>
<script src="js/funcoes_investidor.js"></script>
<script src="js/secao_cadastrar_veiculo.js"></script>
<script src="js/secao_meus_veiculos.js"></script> 
<script src="js/secao_editar_veiculos.js"></script>
<script src="js/secoes_investidor.js"></script> 










</body>
</html>