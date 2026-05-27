<?php
session_start();
require_once "conexao_bd.php";

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit;
}

// 🔐 NOVA VERIFICAÇÃO: Se tem confirmação pendente
// Verifica primeiro no banco de dados para ter certeza
if (isset($_SESSION['status_confirmacao']) && 
    $_SESSION['status_confirmacao'] === 'pendente' &&
    $_SESSION['status_cadastro'] === 'completo') {
    
    // Confirma no banco se realmente está pendente
    $stmt = $mysqli->prepare("SELECT status_confirmacao FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['usuario_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario_temp = $result->fetch_assoc();
    
    if ($usuario_temp && $usuario_temp['status_confirmacao'] === 'pendente') {
        header("Location: confirmar_cadastro.php");
        exit;
    } else {
        // Se o banco já está confirmado, atualiza a sessão
        $_SESSION['status_confirmacao'] = 'confirmado';
    }
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_tipo = $_SESSION['usuario_tipo'];

// Busca dados do usuário
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
  <title>Painel MotorGo</title>

  <link rel="stylesheet" href="css/style_painel_investidor.css" />
  <link rel="stylesheet" href="css/propostas_recebidas.css"/>
  <link rel="stylesheet" href="css/style_dashboard.css"/>
  <link rel="stylesheet" href="style_veiculos.css"/>
  <link rel="stylesheet" href="css/popup.css"/>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">

  <script src="https://unpkg.com/compressorjs@1.2.1/dist/compressor.min.js"></script>
  <script src="js/chart.min.js"></script>



  <!-- Definindo dados de sessão para JS -->
  <script>
    const USUARIO_ID = <?= json_encode($usuario_id); ?>;
    const USUARIO_TIPO = <?= json_encode($usuario_tipo); ?>;
  </script>


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

<!-- OVERLAY -->
<div id="overlay" onclick="fecharSidebar()" style="display: none;"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <img src="imagens/logo_motorgo_blk.png" alt="Logo MotorGo" class="logo" />
  <ul class="menu">
    <li onclick="showSection('painel')">Painel</li>

    <li class="menu-item">
      <span class="menu-toggle-sub <?= $usuario_tipo === 'vendedor' ? 'disabled' : '' ?>"
            style="<?= $usuario_tipo === 'vendedor' ? 'color: gray; cursor: not-allowed;' : '' ?>"
            onclick="<?= $usuario_tipo === 'vendedor' ? 'window.location.href=\'cadastro_investidor.php\'' : '' ?>">
        <i class="fa-solid fa-chevron-right seta-submenu"></i>
        Comprar Veículos
      </span>
      <?php if ($usuario_tipo !== 'vendedor'): ?>
      <ul class="submenu">
        <li onclick="showSection('ofertaVeiculos')">Oferta de Veículos</li>
        <li onclick="showSection('propostasEnviadas')">Propostas Enviadas</li>
      </ul>
      <?php endif; ?>
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
    <li onclick="showSection('meusDados')">Meus Dados</li>
    <li onclick="showSection('ajuda')">Ajuda</li>
    <li id="logoutLink">Sair</li>
  </ul>
</aside>

<!-- CONTEÚDO PRINCIPAL -->
<main class="main-content">

<!-- PAINEL -->
<section id="painel" class="section active">
  <h2>Resumo do <?= $usuario_tipo === 'investidor' ? 'Investidor' : 'Vendedor' ?></h2>

  <?php
$totalEnviadasOuRecebidas = 0; // X
$totalContrapropostas = 0;      // Y
$propostasAceitas = 0;          // Z1
$propostasRecusadas = 0;        // Z2

if ($usuario_tipo === 'investidor') {
    // X - Propostas enviadas
    $sqlX = "
        SELECT COUNT(DISTINCT veiculo_id) 
        FROM propostas 
        WHERE usuario_id = ?
    ";
    $stmt = $mysqli->prepare($sqlX);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $stmt->bind_result($totalEnviadasOuRecebidas);
    $stmt->fetch();
    $stmt->close();

    // Y - Contrapropostas enviadas (não primeira proposta)
    $sqlY = "
        SELECT COUNT(*) 
        FROM propostas 
        WHERE usuario_id = ?
          AND id NOT IN (
              SELECT MIN(id)
              FROM propostas
              WHERE usuario_id = ?
              GROUP BY veiculo_id
          )
    ";
    $stmt = $mysqli->prepare($sqlY);
    $stmt->bind_param("ii", $usuario_id, $usuario_id);
    $stmt->execute();
    $stmt->bind_result($totalContrapropostas);
    $stmt->fetch();
    $stmt->close();

    // Z1 - Aceitas (INVESTIDOR recebe 1 ponto por aceite)
    $sqlZ1 = "
        SELECT COUNT(DISTINCT veiculo_id)
        FROM propostas
        WHERE usuario_id = ?
          AND status = 'aceita'
    ";
    $stmt = $mysqli->prepare($sqlZ1);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $stmt->bind_result($propostasAceitas);
    $stmt->fetch();
    $stmt->close();

    // Z2 - Recusas sofridas (historico_recusada_vendedor)
    $sqlZ2 = "
        SELECT COUNT(DISTINCT veiculo_id)
        FROM propostas
        WHERE usuario_id = ?
           AND status IN ('recusada_investidor', 'recusada_vendedor', 'historico_recusada_vendedor', 'historico_recusada_investidor')
    ";
    $stmt = $mysqli->prepare($sqlZ2);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $stmt->bind_result($propostasRecusadas);
    $stmt->fetch();
    $stmt->close();
} else {
    // VENDEDOR

    // X - Propostas recebidas
    $sqlX = "
        SELECT COUNT(DISTINCT p.veiculo_id)
        FROM propostas p
        JOIN veiculos v ON v.id = p.veiculo_id
        WHERE v.usuario_id = ?
    ";
    $stmt = $mysqli->prepare($sqlX);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $stmt->bind_result($totalEnviadasOuRecebidas);
    $stmt->fetch();
    $stmt->close();

    // Y - Contrapropostas enviadas pelo vendedor
    $sqlY = "
        SELECT COUNT(*) 
        FROM propostas p
        JOIN veiculos v ON v.id = p.veiculo_id
        WHERE v.usuario_id = ?
          AND p.id NOT IN (
              SELECT MIN(p2.id)
              FROM propostas p2
              JOIN veiculos v2 ON v2.id = p2.veiculo_id
              WHERE v2.usuario_id = ?
              GROUP BY p2.veiculo_id
          )
    ";
    $stmt = $mysqli->prepare($sqlY);
    $stmt->bind_param("ii", $usuario_id, $usuario_id);
    $stmt->execute();
    $stmt->bind_result($totalContrapropostas);
    $stmt->fetch();
    $stmt->close();

    // Z1 - Aceitas (VENDEDOR recebe 1 ponto por aceite)
    $sqlZ1 = "
        SELECT COUNT(DISTINCT p.veiculo_id)
        FROM propostas p
        JOIN veiculos v ON v.id = p.veiculo_id
        WHERE v.usuario_id = ?
          AND p.status = 'aceita'
    ";
    $stmt = $mysqli->prepare($sqlZ1);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $stmt->bind_result($propostasAceitas);
    $stmt->fetch();
    $stmt->close();

    // Z2 - Recusas sofridas (historico_recusada_investidor)
    $sqlZ2 = "
        SELECT COUNT(DISTINCT p.veiculo_id)
        FROM propostas p
        JOIN veiculos v ON v.id = p.veiculo_id
        WHERE v.usuario_id = ?
          AND p.status IN ('recusada_investidor', 'recusada_vendedor', 'historico_recusada_vendedor', 'historico_recusada_investidor')
    ";
    $stmt = $mysqli->prepare($sqlZ2);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $stmt->bind_result($propostasRecusadas);
    $stmt->fetch();
    $stmt->close();
}


// Taxa de Aceitação
$totalPropostas = $totalEnviadasOuRecebidas + $totalContrapropostas;
$aceitacao = $totalPropostas > 0 ? round(($propostasAceitas / $totalPropostas) * 100) : 0;
?>


<div class="dashboard-grid">
  <div class="dashboard-card">
    <i class="fa-solid fa-paper-plane icone-card"></i>
    <h3><?= $usuario_tipo === 'investidor' ? 'Propostas Enviadas' : 'Propostas Recebidas' ?></h3>
    <p id="cardEnviadasOuRecebidas"><?= $totalEnviadasOuRecebidas ?></p> 
  </div>

  <div class="dashboard-card">
    <i class="fa-solid fa-arrows-rotate icone-card"></i>
    <h3>Contrapropostas</h3>
    <p id="cardContrapropostas"><?= $totalContrapropostas ?></p> 
  </div>

  <div class="dashboard-card">
    <i class="fa-solid fa-circle-check icone-card"></i>
    <h3>Aceitas</h3>
    <p id="cardAceitas"><?= $propostasAceitas ?></p> 
  </div>

  <div class="dashboard-card">
    <i class="fa-solid fa-circle-xmark icone-card"></i>
    <h3>Recusadas</h3>
    <p id="cardRecusadas"><?= $propostasRecusadas ?></p> 
  </div>
</div>


  <div class="progresso-box">
    <span>Taxa de Aceitação: <strong><?= $aceitacao ?>%</strong></span>
    <div class="barra-progresso"><div class="preenchido" style="width: <?= $aceitacao ?>%;"></div></div>
  </div>

  <!-- Gráfico de pizza -->
  <div class="grafico-container">
  <canvas
    id="graficoPropostas"
    data-enviadas="<?= $totalEnviadasOuRecebidas ?>"
    data-contrapropostas="<?= $totalContrapropostas ?>"
    data-aceitas="<?= $propostasAceitas ?>"
    data-recusadas="<?= $propostasRecusadas ?>"
    width="400"
    height="220"
  ></canvas>
</div>
</section>

<!-- SEÇÕES CONDICIONAIS (somente se não for vendedor) -->
<?php if ($usuario_tipo !== 'vendedor'): ?>
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

      <div class="form-group">
  <label for="filtroEstado">Estado:</label>
  <select name="estado" id="filtroEstado">
    <option value="">Todos</option>
    <option value="AC">AC</option>
    <option value="AL">AL</option>
    <option value="AP">AP</option>
    <option value="AM">AM</option>
    <option value="BA">BA</option>
    <option value="CE">CE</option>
    <option value="DF">DF</option>
    <option value="ES">ES</option>
    <option value="GO">GO</option>
    <option value="MA">MA</option>
    <option value="MT">MT</option>
    <option value="MS">MS</option>
    <option value="MG">MG</option>
    <option value="PA">PA</option>
    <option value="PB">PB</option>
    <option value="PR">PR</option>
    <option value="PE">PE</option>
    <option value="PI">PI</option>
    <option value="RJ">RJ</option>
    <option value="RN">RN</option>
    <option value="RS">RS</option>
    <option value="RO">RO</option>
    <option value="RR">RR</option>
    <option value="SC">SC</option>
    <option value="SP">SP</option>
    <option value="SE">SE</option>
    <option value="TO">TO</option>
  </select>
</div>



      <!-- Botão SEM .btn-vermelho -->
      <div class="botao-filtrar">
        <button type="button" onclick="carregarOfertaVeiculos()">Filtrar</button>
      </div>
    </div>
  </form>
    
    <div id="listaOfertaVeiculos" class="oferta-list"></div>
  </section>

  <section id="propostasEnviadas" class="section">
    <h2>Propostas Enviadas</h2>
    <div id="listaPropostasEnviadas"></div>
  </section>
<?php endif; ?>

<!-- Seções para ambos os tipos -->
<section id="propostasRecebidas" class="section">
  <h2>Propostas Recebidas</h2>
  <div id="listaPropostasRecebidas"></div>
</section>

<?php include 'secao_cadastrar_veiculo.php'; ?>
<?php include 'secao_meus_veiculos.php'; ?>
<?php include 'secao_editar_veiculo.php'; ?>




<section id="meusDados" class="section">
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




<section id="ajuda" class="section">
  <h2>Fale com o Suporte</h2>
  <form id="formAjuda" action="enviar_ajuda.php" method="POST">
    <div class="form-group"><label>Nome:</label><input type="text" name="nome" value="<?= $usuario['nome'] ?>" required></div>
    <div class="form-group"><label>Email:</label><input type="email" name="email" value="<?= $usuario['email'] ?>" required></div>
    <div class="form-group"><label>Mensagem:</label><textarea name="mensagem" rows="5" required></textarea></div>
    <button type="submit" class="btn-vermelho">Enviar</button>
  </form>
</section>



</main>

<!-- POPUP DE MENSAGEM -->
<div id="popupMensagem" class="popup-mensagem" style="display: none;">
  <div class="popup-conteudo">
    <span id="popupTexto"></span>
    <button onclick="fecharPopup()" class="btn-fechar-popup">Fechar</button>
  </div>
</div>

<!-- LOADER -->
<div class="loader-overlay" id="loader">
  <div class="loader-content">
    <div class="spinner"></div>
    <p class="loader-text">Processando...</p>
  </div>
</div>


<!-- SCRIPTS FINAIS -->
<script>
  function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('overlay');
    const isOpen = sidebar.classList.toggle('active');
    if (window.innerWidth <= 768) {
      overlay.style.display = isOpen ? 'block' : 'none';
    }
  }

  function fecharSidebar() {
    document.querySelector('.sidebar').classList.remove('active');
    document.getElementById('overlay').style.display = 'none';
  }


  function fecharPopup() {
    document.getElementById('popupMensagem').style.display = 'none';
  }

  // Redireciona para cadastro de investidor se usuário for vendedor e clicar em "Comprar"
  document.addEventListener("DOMContentLoaded", () => {
    const tipoUsuario = <?= json_encode($_SESSION['usuario_tipo']) ?>;
    if (tipoUsuario === "vendedor") {
      document.querySelectorAll('.menu-item span').forEach(el => {
        if (el.textContent.includes("Comprar Veículos")) {
          el.style.color = "#aaa";
          el.style.pointerEvents = "auto";
          el.style.cursor = "pointer";
          el.addEventListener("click", () => {
            window.location.href = "cadastro_investidor.php";
          });
        }
      });
    }

    // Botão de logout
    const logoutBtn = document.getElementById("logoutLink");
    if (logoutBtn) {
      logoutBtn.addEventListener("click", () => {
        window.location.href = "logout.php";
      });
    }
  });

  // Máscara monetária
  document.addEventListener("input", function (e) {
    if (e.target.classList.contains("mascara-valor")) {
      let valor = e.target.value.replace(/\D/g, "");
      valor = (valor / 100).toFixed(2) + "";
      valor = valor.replace(".", ",").replace(/\B(?=(\d{3})+(?!\d))/g, ".");
      e.target.value = "R$ " + valor;
    }
  });

  // Ampliar imagem
  function ampliarImagem(src) {
    const popup = document.createElement("div");
    popup.style.cssText = `
      position:fixed; top:0; left:0; width:100%; height:100%;
      background: rgba(0,0,0,0.8); display:flex; align-items:center; justify-content:center; z-index:9999;
    `;
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
</script>

<!-- JS -->
<script src="js/utils_popup.js"></script>
<script src="js/funcoes_propostas_vendedor.js"></script>
<script src="js/funcoes_investidor.js"></script>
<script src="js/secao_cadastrar_veiculo.js"></script>
<script src="js/secao_meus_veiculos.js"></script>
<script src="js/secao_editar_veiculos.js"></script>
<script src="js/secoes_investidor.js"></script>

<!-- CÓDIGO DE PAGINAÇÃO INTEGRADO -->
<script>
// Variável para controlar o tipo de visualização e paginação
window.visualizacaoAtual = 'cards';
window.paginaAtual = 1;

// Interceptar a função carregarOfertaVeiculos para adicionar o parâmetro de visualização e paginação
(function() {
    // Aguardar a função original estar disponível
    const interceptarCarregarOfertaVeiculos = setInterval(function() {
        if (typeof window.carregarOfertaVeiculos === 'function') {
            clearInterval(interceptarCarregarOfertaVeiculos);
            
            // Salvar referência da função original
            const carregarOfertaVeiculosOriginal = window.carregarOfertaVeiculos;
            
            // Sobrescrever com nova função que adiciona os parâmetros
            window.carregarOfertaVeiculos = function(pagina = 1) {
                window.paginaAtual = pagina;
                
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
                    visualizacao: window.visualizacaoAtual,
                    pagina: window.paginaAtual
                });
                
                // Mostrar loader se existir
                const loader = document.getElementById('loader');
                if (loader) loader.style.display = 'flex';
                
                fetch(`secao_oferta_veiculos.php?${params}`)
                    .then(response => response.text())
                    .then(html => {
                        document.getElementById('listaOfertaVeiculos').innerHTML = html;
                        
                        // Manter o estado da visualização
                        const botoesView = document.querySelectorAll('.btn-view');
                        botoesView.forEach(btn => {
                            if ((window.visualizacaoAtual === 'cards' && btn.textContent.includes('Cards')) ||
                                (window.visualizacaoAtual === 'lista' && btn.textContent.includes('Lista'))) {
                                btn.classList.add('active');
                            } else {
                                btn.classList.remove('active');
                            }
                        });
                        
                        // Scroll suave para o topo da lista
                        const ofertaSection = document.getElementById('ofertaVeiculos');
                        if (ofertaSection) {
                            ofertaSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    })
                    .catch(error => console.error('Erro ao carregar ofertas:', error))
                    .finally(() => {
                        // Esconder loader
                        if (loader) loader.style.display = 'none';
                    });
            };
        }
    }, 100);
})();

// ========== FUNÇÕES GLOBAIS PARA VISUALIZAÇÃO ==========
window.alterarVisualizacao = function(tipo) {
    console.log('Alterando visualização para:', tipo);
    window.visualizacaoAtual = tipo;
    window.paginaAtual = 1; // Resetar para primeira página ao mudar visualização
    
    if (typeof window.carregarOfertaVeiculos === 'function') {
        window.carregarOfertaVeiculos(1);
    }
}

// ========== FUNÇÕES DE PAGINAÇÃO ==========
window.irParaPagina = function(pagina) {
    window.paginaAtual = pagina;
    
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
    
    // Mostrar loader se existir
    const loader = document.getElementById('loader');
    if (loader) loader.style.display = 'flex';
    
    // Fazer scroll suave para o topo da seção
    const ofertaSection = document.getElementById('ofertaVeiculos');
    if (ofertaSection) {
        ofertaSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    
    // Carregar a nova página via AJAX
    fetch(`secao_oferta_veiculos.php?${params}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('listaOfertaVeiculos').innerHTML = html;
        })
        .catch(error => console.error('Erro ao carregar página:', error))
        .finally(() => {
            // Esconder loader
            if (loader) loader.style.display = 'none';
        });
}

window.irParaPaginaInput = function() {
    const input = document.getElementById('inputPagina');
    const pagina = parseInt(input.value);
    
    if (pagina && pagina > 0) {
        irParaPagina(pagina);
    }
}

window.irParaPaginaEnter = function(event) {
    if (event.key === 'Enter') {
        irParaPaginaInput();
    }
}

// ========== RESETAR PAGINAÇÃO AO FILTRAR ==========
document.addEventListener('DOMContentLoaded', function() {
    const btnFiltrar = document.querySelector('.botao-filtrar button');
    if (btnFiltrar) {
        btnFiltrar.addEventListener('click', function() {
            window.paginaAtual = 1; // Resetar para primeira página ao filtrar
        });
    }
});

// ========== FUNÇÕES PARA LISTA DESKTOP (EXPANSÍVEL) ==========
window.abrirPropostaDesktop = function(veiculoId) {
    // Fechar outras propostas abertas
    document.querySelectorAll('.form-proposta-expansivel').forEach(form => {
        form.style.display = 'none';
        // Resetar borda do card correspondente
        const cardId = form.id.replace('formPropostaDesktop', 'cardDesktop');
        const card = document.getElementById(cardId);
        if (card) {
            card.style.borderRadius = '8px';
        }
    });
    
    // Abrir a proposta selecionada
    const form = document.getElementById(`formPropostaDesktop${veiculoId}`);
    const card = document.getElementById(`cardDesktop${veiculoId}`);
    
    if (form && card) {
        form.style.display = 'block';
        // Ajustar borda do card para conectar visualmente
        card.style.borderRadius = '8px 8px 0 0';
        
        const input = document.getElementById(`valorPropostaDesktop${veiculoId}`);
        if (input) {
            setTimeout(() => input.focus(), 100);
            // Aplicar máscara monetária
            if (!input.hasAttribute('data-mascara-aplicada')) {
                input.setAttribute('data-mascara-aplicada', 'true');
                input.addEventListener('input', function(e) {
                    let valor = e.target.value.replace(/\D/g, "");
                    valor = (valor / 100).toFixed(2) + "";
                    valor = valor.replace(".", ",").replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                    e.target.value = "R$ " + valor;
                });
            }
        }
    }
}

window.cancelarPropostaDesktop = function(veiculoId) {
    const form = document.getElementById(`formPropostaDesktop${veiculoId}`);
    const card = document.getElementById(`cardDesktop${veiculoId}`);
    
    if (form) form.style.display = 'none';
    if (card) card.style.borderRadius = '8px'; // Resetar borda
    
    const input = document.getElementById(`valorPropostaDesktop${veiculoId}`);
    if (input) input.value = '';
}

window.confirmarPropostaDesktop = function(veiculoId) {
    const valorInput = document.getElementById(`valorPropostaDesktop${veiculoId}`);
    if (!valorInput) return;
    
    const valor = valorInput.value.replace(/\D/g, '') / 100;
    
    if (valor <= 0) {
        alert('Por favor, informe um valor válido.');
        return;
    }
    
    // Criar input temporário para reutilizar a função confirmarProposta existente
    const tempInput = document.createElement('input');
    tempInput.id = `valorProposta${veiculoId}`;
    tempInput.value = valorInput.value;
    document.body.appendChild(tempInput);
    
    // Chamar a função existente
    if (typeof confirmarProposta === 'function') {
        confirmarProposta(veiculoId);
    }
    
    // Remover input temporário e fechar formulário
    setTimeout(() => {
        tempInput.remove();
        // Fechar formulário após confirmação
        const form = document.getElementById(`formPropostaDesktop${veiculoId}`);
        const card = document.getElementById(`cardDesktop${veiculoId}`);
        if (form) form.style.display = 'none';
        if (card) card.style.borderRadius = '8px'; // Resetar borda
        
        // Recarregar a página atual após enviar proposta
        irParaPagina(window.paginaAtual);
    }, 100);
}

// ========== FUNÇÕES PARA LISTA MOBILE (EXPANSÍVEL) ==========
window.abrirPropostaListaMobile = function(veiculoId) {
    // Fechar outras propostas abertas
    document.querySelectorAll('.form-proposta-expansivel-mobile').forEach(form => {
        form.style.display = 'none';
        // Resetar borda do card correspondente
        const cardId = form.id.replace('formPropostaListaMobile', 'cardMobile');
        const card = document.getElementById(cardId);
        if (card) {
            card.style.borderRadius = '8px';
        }
    });
    
    // Abrir a proposta selecionada
    const form = document.getElementById(`formPropostaListaMobile${veiculoId}`);
    const card = document.getElementById(`cardMobile${veiculoId}`);
    
    if (form && card) {
        form.style.display = 'block';
        // Ajustar borda do card para conectar visualmente
        card.style.borderRadius = '8px 8px 0 0';
        
        const input = document.getElementById(`valorPropostaListaMobile${veiculoId}`);
        if (input) {
            setTimeout(() => input.focus(), 100);
            // Aplicar máscara monetária
            if (!input.hasAttribute('data-mascara-aplicada')) {
                input.setAttribute('data-mascara-aplicada', 'true');
                input.addEventListener('input', function(e) {
                    let valor = e.target.value.replace(/\D/g, "");
                    valor = (valor / 100).toFixed(2) + "";
                    valor = valor.replace(".", ",").replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                    e.target.value = "R$ " + valor;
                });
            }
        }
    }
}

window.cancelarPropostaListaMobile = function(veiculoId) {
    const form = document.getElementById(`formPropostaListaMobile${veiculoId}`);
    const card = document.getElementById(`cardMobile${veiculoId}`);
    
    if (form) form.style.display = 'none';
    if (card) card.style.borderRadius = '8px'; // Resetar borda
    
    const input = document.getElementById(`valorPropostaListaMobile${veiculoId}`);
    if (input) input.value = '';
}

window.confirmarPropostaListaMobile = function(veiculoId) {
    const valorInput = document.getElementById(`valorPropostaListaMobile${veiculoId}`);
    if (!valorInput) return;
    
    const valor = valorInput.value.replace(/\D/g, '') / 100;
    
    if (valor <= 0) {
        alert('Por favor, informe um valor válido.');
        return;
    }
    
    // Criar input temporário para reutilizar a função confirmarProposta existente
    const tempInput = document.createElement('input');
    tempInput.id = `valorProposta${veiculoId}`;
    tempInput.value = valorInput.value;
    document.body.appendChild(tempInput);
    
    // Chamar a função existente
    if (typeof confirmarProposta === 'function') {
        confirmarProposta(veiculoId);
    }
    
    // Remover input temporário e fechar formulário
    setTimeout(() => {
        tempInput.remove();
        // Fechar formulário após confirmação
        const form = document.getElementById(`formPropostaListaMobile${veiculoId}`);
        const card = document.getElementById(`cardMobile${veiculoId}`);
        if (form) form.style.display = 'none';
        if (card) card.style.borderRadius = '8px'; // Resetar borda
        
        // Recarregar a página atual após enviar proposta
        irParaPagina(window.paginaAtual);
    }, 100);
}

// ========== FUNÇÃO GLOBAL PARA FECHAR AVISO FIPE ==========
function fecharAvisoFipe() {
    console.log('🎯 fecharAvisoFipe() chamada');
    
    const aviso = document.getElementById('avisoFipe');
    console.log('🔍 Elemento avisoFipe encontrado:', aviso);
    
    if (aviso) {
        console.log('✅ Fechando aviso...');
        aviso.style.display = 'none';
        aviso.style.visibility = 'hidden';
        console.log('✅ Aviso fechado com sucesso!');
    } else {
        console.log('❌ Elemento avisoFipe não encontrado no DOM');
    }
}

// ========== GARANTIR QUE A FUNÇÃO ESTEJA NO ESCOPO GLOBAL ==========
window.fecharAvisoFipe = fecharAvisoFipe;

// ========== LISTENER PARA ELEMENTOS CARREGADOS VIA AJAX ==========
document.addEventListener('click', function(e) {
    if (e.target.closest('.btn-fechar-aviso')) {
        console.log('🔄 Click detectado no botão X via delegação');
        e.preventDefault();
        e.stopPropagation();
        
        // Verificar qual aviso está sendo fechado
        const avisoContainer = e.target.closest('.aviso-fipe-unico');
        if (avisoContainer) {
            if (avisoContainer.id === 'avisoFipe') {
                fecharAvisoFipe(); // Lista
            } else if (avisoContainer.id === 'avisoFipeCards') {
                fecharAvisoFipeCards(); // Cards
            }
        }
    }
});

console.log('✅ Função fecharAvisoFipe carregada globalmente');

// ========== FUNÇÃO PARA FECHAR AVISO FIPE - VERSÃO CARDS ==========
function fecharAvisoFipeCards() {
    console.log('🎯 fecharAvisoFipeCards() chamada');
    
    const aviso = document.getElementById('avisoFipeCards');
    console.log('🔍 Elemento avisoFipeCards encontrado:', aviso);
    
    if (aviso) {
        console.log('✅ Fechando aviso Cards...');
        aviso.style.display = 'none';
        aviso.style.visibility = 'hidden';
        console.log('✅ Aviso Cards fechado com sucesso!');
    } else {
        console.log('❌ Elemento avisoFipeCards não encontrado no DOM');
    }
}

// ========== GARANTIR QUE A FUNÇÃO ESTEJA NO ESCOPO GLOBAL ==========
window.fecharAvisoFipeCards = fecharAvisoFipeCards;

// ========== MANTER ESTADO DA PÁGINA AO RECARREGAR ==========
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se existe uma página salva no sessionStorage
    const savedPage = sessionStorage.getItem('ofertaVeiculosPagina');
    if (savedPage) {
        window.paginaAtual = parseInt(savedPage);
    }
});

// Salvar página atual no sessionStorage sempre que mudar
window.addEventListener('beforeunload', function() {
    if (window.paginaAtual) {
        sessionStorage.setItem('ofertaVeiculosPagina', window.paginaAtual);
    }
});
</script>

</body>
</html>
