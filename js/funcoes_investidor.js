const isInvestidor = typeof USUARIO_TIPO !== "undefined" && USUARIO_TIPO === "investidor";




function toggleSidebar(forceClose = false) {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('overlay');

  if (forceClose || sidebar.classList.contains('active')) {
    sidebar.classList.remove('active');
    overlay.style.display = 'none';
  } else {
    sidebar.classList.add('active');
    overlay.style.display = 'block';
  }
}

function fecharSidebar() {
  toggleSidebar(true);
}

function showSection(sectionId) {
  document.querySelectorAll('.section').forEach(sec => sec.classList.remove('active'));
  const section = document.getElementById(sectionId);
  if (section) section.classList.add('active');

  if (window.innerWidth <= 768) {
    document.querySelector('.sidebar').classList.remove('active');
    document.getElementById('overlay').style.display = 'none';
  }

  // 🔄 Gatilhos dinâmicos de carregamento
  if (sectionId === 'propostasEnviadas') {
    if (typeof carregarPropostasEnviadas === "function") {
      carregarPropostasEnviadas();
    }
  }

  if (sectionId === 'propostasRecebidas') {
    if (typeof carregarPropostasRecebidas === "function") {
      carregarPropostasRecebidas();
    }
  }

  if (sectionId === 'ofertaVeiculos') {
    if (typeof carregarOfertaVeiculos === "function") {
      carregarOfertaVeiculos();
    }
  }

  if (sectionId === 'painel') {
    if (typeof renderizarGraficoPropostas === "function") {
      renderizarGraficoPropostas();
    }
  }

  if (sectionId === 'edicaoVeiculo') {
    if (typeof aplicarSubmitEditarVeiculo === "function") {
      aplicarSubmitEditarVeiculo();
    }
  }
}

function renderizarGraficoPropostas() {
  const canvas = document.getElementById('graficoPropostas');
  if (!canvas) return;

  // 🔥 Destrói o gráfico anterior se já existir
  if (canvas.graficoInstancia instanceof Chart) {
    canvas.graficoInstancia.destroy();
  }

  const enviadas = parseInt(canvas.dataset.enviadas || 0, 10);
  const contrapropostas = parseInt(canvas.dataset.contrapropostas || 0, 10);
  const aceitas = parseInt(canvas.dataset.aceitas || 0, 10);
  const recusadas = parseInt(canvas.dataset.recusadas || 0, 10);

  const primeiroLabel = (typeof USUARIO_TIPO !== "undefined" && USUARIO_TIPO === "vendedor") 
    ? "Propostas Recebidas" 
    : "Propostas Enviadas";

  canvas.graficoInstancia = new Chart(canvas.getContext('2d'), {
    type: 'doughnut',
    data: {
      labels: [primeiroLabel, 'Contrapropostas', 'Aceitas', 'Recusadas'],
      datasets: [{
        data: [enviadas, contrapropostas, aceitas, recusadas],
        backgroundColor: ['#ecdc0c', '#fb8c00', '#2e7d32', '#c62828'],
        borderWidth: 1
      }]
    },
    options: {
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            font: { size: 13 },
            color: '#333'
          }
        }
      }
    }
  });
}


document.addEventListener("DOMContentLoaded", () => {
  const overlay = document.getElementById('overlay');
  if (overlay) {
    overlay.addEventListener('click', fecharSidebar);
  }

  document.querySelectorAll(".menu-toggle-sub").forEach(toggle => {
    toggle.addEventListener("click", () => {
      const parent = toggle.closest(".menu-item");
      parent.classList.toggle("open");
    });
  });

  const filtroStatus = document.getElementById("filtroStatusEnviadas");
  const filtroOrdenacao = document.getElementById("filtroOrdenacaoEnviadas");
  if (filtroStatus) filtroStatus.addEventListener("change", aplicarFiltrosPropostas);
  if (filtroOrdenacao) filtroOrdenacao.addEventListener("change", aplicarFiltrosPropostas);

  setTimeout(() => {
    const painel = document.getElementById('painel');
    const canvas = document.getElementById('graficoPropostas');
    if (painel && painel.classList.contains('active') && canvas) {
      renderizarGraficoPropostas();
    }
  }, 100);
});

function enviarProposta(veiculoId) {
  const form = document.getElementById('formProposta' + veiculoId);
  if (form) {
    form.classList.remove("escondido");
    form.classList.add("ativo");
  }
}

function cancelarProposta(veiculoId) {
  const form = document.getElementById('formProposta' + veiculoId);
  if (form) {
    form.classList.remove("ativo");
    form.classList.add("escondido");
  }
}

function confirmarProposta(veiculoId) {
  const campo = document.getElementById('valorProposta' + veiculoId);
  let valor = campo.value.replace(/\s/g, '').replace('R$', '').replace(/\./g, '').replace(',', '.');
  const valorFloat = parseFloat(valor);

  if (!valorFloat || valorFloat <= 0) {
    mostrarPopup("⚠️ Informe um valor válido.");
    return;
  }

  const loader = document.getElementById("loader");
  if (loader) loader.style.display = "flex"; // ✅ Mostra o loader

  fetch("enviar_proposta.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({ veiculo_id: veiculoId, valor: valorFloat })
  })
    .then(res => res.json())
    .then(data => {
      if (loader) loader.style.display = "none"; // ✅ Oculta o loader

      if (data.success) {
        mostrarPopup("✅ Proposta enviada com sucesso!");

        setTimeout(() => {
          cancelarProposta(veiculoId);
          if (typeof carregarPropostasEnviadas === "function") {
            carregarPropostasEnviadas();
          }
          if (typeof carregarOfertaVeiculos === "function") {
            carregarOfertaVeiculos();
          }
        }, 1500);
      } else {
        mostrarPopup("❌ " + (data.message || "Erro ao enviar proposta."));
      }
    })
    .catch(() => {
      if (loader) loader.style.display = "none"; // ✅ Garante que o loader desapareça mesmo em erro
      mostrarPopup("❌ Erro ao conectar com o servidor.");
    });
}


function carregarOfertaVeiculos() {
  const container = document.getElementById("listaOfertaVeiculos");
  if (!container) return;

  container.innerHTML = "<p>Carregando veículos...</p>";

  fetch("secao_oferta_veiculos.php")
    .then(res => res.text())
    .then(html => {
      const conteudo = html.trim();

      if (!conteudo || conteudo.length < 50) {
        container.innerHTML = "<p style='color: #666;'>Nenhuma oferta disponível no momento.</p>";
        return;
      }

      container.innerHTML = conteudo;

      // ✅ Miniaturas trocando imagem principal
      document.querySelectorAll('.oferta-thumb').forEach(miniatura => {
        miniatura.addEventListener('click', function () {
          const novaSrc = this.src;
          const card = this.closest('.oferta-card');
          const fotoPrincipal = card?.querySelector('.oferta-main-image');
          if (fotoPrincipal) {
            fotoPrincipal.src = novaSrc;
          }
        });
      });
    })
    .catch(() => {
      container.innerHTML = "<p style='color:red;'>Erro ao carregar veículos.</p>";
    });
}




function carregarPropostasEnviadas() {
  const container = document.getElementById("listaPropostasEnviadas");
  if (!container) return;

  container.innerHTML = "<p>Carregando propostas...</p>";

  fetch("listar_propostas_enviadas.php")
    .then(res => res.text())
    .then(html => {
      container.innerHTML = html;
    })
    .catch(() => {
      container.innerHTML = "<p style='color:red;'>Erro ao carregar propostas.</p>";
    });
}

// 🔄 Oculta proposta recusada e atualiza status para historico_recusada

function ocultarPropostaRecusada(id, card) {
  if (!id || !card) {
    mostrarPopup("❌ Erro interno ao identificar proposta.");
    return;
  }

  // Define o novo status com base no tipo de usuário
  let novoStatus = "";
  if (typeof USUARIO_TIPO !== "undefined") {
    novoStatus = USUARIO_TIPO === "investidor"
      ? "historico_recusada_vendedor"
      : "historico_recusada_investidor";
  }

  fetch("ocultar_proposta.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({
      proposta_id: id,
      novo_status: novoStatus
    })
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        card.remove();
        mostrarPopup("✅ Proposta cancelada.");
      } else {
        mostrarPopup("❌ " + (data.message || "Erro ao ocultar proposta."));
      }
    })
    .catch(() => {
      mostrarPopup("❌ Erro ao conectar com o servidor.");
    });
}




// 📌 Eventos de clique gerais
document.addEventListener("click", function (e) {
  if (!isInvestidor) return;
  const id = e.target.dataset.id;

  if (e.target.classList.contains("btn-aceitar")) {
    aceitarProposta(id); // 👈 aqui é o novo bloco
  }

  if (e.target.classList.contains("btn-negociar")) {
    const form = document.getElementById("negociacao" + id);
    if (form) form.classList.add("ativo");
  }

  if (e.target.classList.contains("btn-cancelar")) {
    const form = document.getElementById("negociacao" + id);
    if (form) form.classList.remove("ativo");
  }



  // 📤 Envio de contraproposta (investidor ou vendedor)
  if (e.target.classList.contains("btn-enviar-contraproposta")) {
    const campo = document.getElementById("valorNegociado" + id);
    if (!campo) return;
  
    let valor = campo.value.replace(/\s/g, '').replace('R$', '').replace(/\./g, '').replace(',', '.');
    const valorFloat = parseFloat(valor);
  
    if (!valorFloat || valorFloat <= 0) {
      mostrarPopup("⚠️ Informe um valor válido.");
      return;
    }
  
    const loader = document.getElementById("loader");
    if (loader) loader.style.display = "flex"; // ✅ Mostra o loader
  
    let endpoint = "";
    let params = null;
  
    if (USUARIO_TIPO === "investidor") {
      endpoint = "responder_contraproposta.php";
      params = new URLSearchParams({
        proposta_id: id,
        acao: "negociar",
        valor: valorFloat
      });
    } else if (USUARIO_TIPO === "vendedor") {
      endpoint = "processar_contraproposta.php";
      params = new URLSearchParams({
        proposta_id: id,
        novo_valor: valorFloat
      });
    } else {
      if (loader) loader.style.display = "none"; // Oculta loader se erro
      mostrarPopup("❌ Tipo de usuário inválido.");
      return;
    }
  
    fetch(endpoint, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: params
    })
      .then(res => res.json())
      .then(data => {
        if (loader) loader.style.display = "none";
  
        if (data.success) {
          mostrarPopup("✅ Contraproposta enviada com sucesso!");
          setTimeout(() => location.reload(), 1500);
        } else {
          mostrarPopup("❌ " + (data.message || "Erro ao enviar contraproposta."));
        }
      })
      .catch(() => {
        if (loader) loader.style.display = "none";
        mostrarPopup("❌ Erro ao conectar com o servidor.");
      });
  }
  
  


  // ✅ NOVO BLOCO: Recusa da proposta pelo investidor
  if (e.target.classList.contains("btn-recusar")) {
    mostrarPopupConfirmacao("Tem certeza que deseja recusar esta proposta?", () => {
      mostrarLoader();
  
      fetch("recusar_proposta.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({ proposta_id: id })
      })
        .then(res => res.json())
        .then(data => {
          esconderLoader();
          const card = e.target.closest(".card-proposta-recebida");
  
          if (data.success) {
            mostrarPopup("🚫 Proposta recusada.");
            if (card) card.remove();
            atualizarPainel(); // ✅ ATUALIZA depois da recusa
          } else {
            mostrarPopup("❌ Erro ao recusar proposta.");
          }
        })
        .catch(() => {
          esconderLoader();
          mostrarPopup("❌ Erro ao conectar com o servidor.");
        });
    });
  }
  
  

  if (e.target.classList.contains("btn-ok-recusa")) {
    const card = e.target.closest(".card-proposta-recebida");
    const propostaId = card?.dataset.id;
    ocultarPropostaRecusada(propostaId, card);
  }
});

function aceitarProposta(id) {
  mostrarLoader();

  fetch("aceitar_proposta.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({ proposta_id: id })
  })
    .then(res => res.json())
    .then(data => {
      esconderLoader();

      if (data.success) {
        mostrarPopup("✅ Proposta aceita com sucesso!");

        setTimeout(() => {
          atualizarPainel(); // Atualiza os cards do Painel

          // 🔥 Verifica o tipo de usuário
          if (typeof USUARIO_TIPO !== "undefined") {
            if (USUARIO_TIPO === "vendedor") {
              if (typeof carregarPropostasRecebidas === "function") {
                carregarPropostasRecebidas(); // Vendedor -> propostas recebidas
                showSection('propostasRecebidas');
              }
            } else if (USUARIO_TIPO === "investidor") {
              if (typeof carregarPropostasEnviadas === "function") {
                carregarPropostasEnviadas(); // Investidor -> propostas enviadas
              }
              if (typeof carregarPropostasRecebidas === "function") {
                carregarPropostasRecebidas(); // Investidor -> propostas recebidas de vendas
              }
              showSection('propostasEnviadas'); // Deixa aberto inicialmente na aba "Enviadas"
            } else {
              location.reload(); // fallback de segurança
            }
          } else {
            location.reload(); // fallback de segurança
          }

        }, 1500);

      } else {
        mostrarPopup("❌ Erro ao aceitar a proposta.");
      }
    })
    .catch(() => {
      esconderLoader();
      mostrarPopup("❌ Erro de conexão.");
    });
}





function mostrarLoader() {
  const el = document.getElementById("loader");
  if (el) el.style.display = "flex";
}

function esconderLoader() {
  const el = document.getElementById("loader");
  if (el) el.style.display = "none";
}

function mostrarPopupConfirmacao(mensagem, onConfirmar) {
  const popup = document.createElement("div");
  popup.className = "popup-mensagem";
  popup.style.display = "flex";
  popup.innerHTML = `
    <div class="popup-conteudo">
      <span>${mensagem}</span>
      <div style="margin-top: 15px; display: flex; justify-content: center; gap: 10px;">
        <button class="btn-vermelho" id="btnConfirmarSim">Sim</button>
        <button class="btn-cancelar" id="btnConfirmarNao">Cancelar</button>
      </div>
    </div>
  `;
  document.body.appendChild(popup);

  document.getElementById("btnConfirmarSim").onclick = () => {
    popup.remove();
    if (typeof onConfirmar === "function") onConfirmar();
  };

  document.getElementById("btnConfirmarNao").onclick = () => {
    popup.remove();
  };
}

document.addEventListener("DOMContentLoaded", () => {
  const seletor = document.getElementById("modoVisualizacao");
  const container = document.getElementById("listaOfertaVeiculos");

  if (seletor && container) {
    seletor.addEventListener("change", () => {
      if (seletor.value === "lista") {
        container.classList.add("lista");
      } else {
        container.classList.remove("lista");
      }
    });
  }
});


// Adiciona listener para trocar imagem ao clicar na miniatura
document.addEventListener("DOMContentLoaded", () => {
  const listaContainer = document.getElementById("listaOfertaVeiculos");
  if (listaContainer) {
    listaContainer.addEventListener("click", function (e) {
      const miniatura = e.target.closest(".oferta-thumb");
      if (!miniatura) return;

      const card = miniatura.closest(".oferta-card");
      const fotoPrincipal = card?.querySelector(".oferta-main-image");

      if (fotoPrincipal) {
        fotoPrincipal.src = miniatura.src;
      }
    });
  }
});





// INICIO FILTRO OFERTA VEICULOS


function carregarOfertaVeiculos() {
  const marca = document.getElementById('filtroMarca').value;
  const anoDe = document.getElementById('filtroAnoDe').value;
  const anoAte = document.getElementById('filtroAnoAte').value;
  const preco = document.getElementById('filtroPreco').value;
  const estado = document.getElementById('filtroEstado').value;

  const lista = document.getElementById('listaOfertaVeiculos');
  
  // Mostra loading antes de buscar
  lista.innerHTML = `
    <div style="text-align: center; padding: 20px;">
      <div class="spinner" style="margin-bottom: 10px;"></div>
      <p>Carregando veículos...</p>
    </div>
  `;

  const params = new URLSearchParams({
    

    marca: marca,
    ano_de: anoDe,
    ano_ate: anoAte,
    preco: preco,
    estado: estado,
  });

  fetch('secao_oferta_veiculos.php?' + params.toString())
    .then(response => response.text())
    .then(data => {
      // Se o PHP não retornar nada, mostra mensagem
      if (data.trim() === '') {
        lista.innerHTML = `
          <div style="text-align: center; padding: 20px;">
            <p style="font-size: 18px; color: #555;">Nenhum veículo encontrado.</p>
          </div>
        `;
      } else {
        lista.innerHTML = data;

        // Fade-in bonito
        lista.style.opacity = 0;
        setTimeout(() => {
          lista.style.transition = 'opacity 0.5s';
          lista.style.opacity = 1;
        }, 50);
      }
    })
    .catch(error => {
      console.error('Erro ao buscar veículos:', error);
      lista.innerHTML = `
        <div style="text-align: center; padding: 20px;">
          <p style="color: red;">Erro ao carregar veículos. Tente novamente. ❌</p>
        </div>
      `;
    });
}



function carregarMarcas() {
  const selectMarca = document.getElementById('filtroMarca');
  if (!selectMarca) {
    console.warn("⚠️ Elemento #filtroMarca não encontrado. Função carregarMarcas() ignorada.");
    return;
  }

  fetch('carregar_marcas.php')
    .then(response => response.json())
    .then(marcas => {
      // Primeiro, limpa o select
      selectMarca.innerHTML = '<option value="">Todas</option>';

      // Depois, adiciona as opções
      marcas.forEach(marca => {
        const option = document.createElement('option');
        option.value = marca.id;
        option.textContent = marca.nome;
        selectMarca.appendChild(option);
      });
    })
    .catch(error => {
      console.error('Erro ao carregar marcas:', error);
    });
}




// Carregar marcas automaticamente quando a página for carregada
document.addEventListener('DOMContentLoaded', () => {
  carregarMarcas();
});

// FIM FILTRO OFERTA VEICULOS




// Inicio Atualizar Painel e Grafico automaticamente 

function atualizarPainel() {
  fetch('atualizar_painel.php')
    .then(response => response.json())
    .then(dados => {
      if (dados.sucesso) {
        // Atualiza os números nos cards
        document.getElementById('cardEnviadasOuRecebidas').innerText = dados.enviadasOuRecebidas;
        document.getElementById('cardContrapropostas').innerText = dados.contrapropostas;
        document.getElementById('cardAceitas').innerText = dados.aceitas;
        document.getElementById('cardRecusadas').innerText = dados.recusadas;

        // Atualiza o gráfico
        atualizarGrafico(dados);
      }
    })
    .catch(error => {
      console.error('Erro ao atualizar painel:', error);
    });
}

// Atualizar o gráfico
function atualizarGrafico(dados) {
  const canvas = document.getElementById('graficoPropostas');
  if (!canvas) return;

  if (canvas.graficoInstancia) {
    canvas.graficoInstancia.data.datasets[0].data = [
      dados.enviadasOuRecebidas,
      dados.contrapropostas,
      dados.aceitas,
      dados.recusadas
    ];

    const primeiroLabel = (typeof USUARIO_TIPO !== "undefined" && USUARIO_TIPO === "vendedor") ? "Propostas Recebidas" : "Propostas Enviadas";
    canvas.graficoInstancia.data.labels = [primeiroLabel, 'Contrapropostas', 'Aceitas', 'Recusadas'];

    canvas.graficoInstancia.update();
  }
}

document.addEventListener("DOMContentLoaded", () => {
  const canvas = document.getElementById('graficoPropostas');
  if (canvas && !canvas.graficoInstancia) {
    const primeiroLabel = (typeof USUARIO_TIPO !== "undefined" && USUARIO_TIPO === "vendedor")
      ? "Propostas Recebidas"
      : "Propostas Enviadas";

    // Renderiza o gráfico inicial
    canvas.graficoInstancia = new Chart(canvas.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: [primeiroLabel, 'Contrapropostas', 'Aceitas', 'Recusadas'],
        datasets: [{
          data: [
            parseInt(canvas.dataset.enviadas || 0, 10),
            parseInt(canvas.dataset.contrapropostas || 0, 10),
            parseInt(canvas.dataset.aceitas || 0, 10),
            parseInt(canvas.dataset.recusadas || 0, 10)
          ],
          backgroundColor: ['#ecdc0c', '#fb8c00', '#2e7d32', '#c62828'],
          borderWidth: 1
        }]
      },
      options: {
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              font: { size: 13 },
              color: '#333'
            }
          }
        }
      }
    });
  }
});



// Fim Atualizar Painel e Grafico automaticamente 

// Cadastro Investidor - barra de progresso
function avancar(parte) {
  if (parte === 1) {
    const cpf = document.getElementById('cpf').value;
    const email = document.getElementById('email').value;

    if (!validarCPFJavaScript(cpf)) {
      mostrarPopup("CPF inválido! Por favor, insira um CPF válido.");
      return;
    }

    if (!validateEmail(email)) {
      mostrarPopup("Email inválido! Por favor, insira um e-mail válido.");
      return;
    }

    document.getElementById('parte1').style.display = 'none';
    document.getElementById('parte2').style.display = 'block';
    atualizarBarra(2);
  }

  else if (parte === 2) {
    const nome = document.querySelector('input[name="nome"]').value.trim();
    const celular = document.getElementById('celular').value.trim();
    const residencia = document.getElementById('residencia').value;
    const renda = document.getElementById('renda').value;
    const comprou = document.getElementById('comprou_particulares').value;
    const investe = document.getElementById('investe_frequente').value;

    if (!nome || !celular) {
      mostrarPopup("Por favor, preencha o nome completo e o celular.");
      return;
    }

    if (!residencia) {
      mostrarPopup("Por favor, selecione o tipo de residência.");
      return;
    }

    if (!renda) {
      mostrarPopup("Por favor, selecione a faixa de renda.");
      return;
    }

    if (!comprou) {
      mostrarPopup("Por favor, informe se já comprou veículos de particulares.");
      return;
    }

    if (!investe) {
      mostrarPopup("Por favor, informe se faz investimentos com frequência.");
      return;
    }

    document.getElementById('parte2').style.display = 'none';
    document.getElementById('parte3').style.display = 'block';
    atualizarBarra(3);
  }
}

// Atualiza a barra de progresso visual
function atualizarBarra(etapa) {
  const etapa1 = document.getElementById('etapa1');
  const etapa2 = document.getElementById('etapa2');
  const etapa3 = document.getElementById('etapa3');
  const barra = document.getElementById('barra');

  etapa1.classList.remove('ativo');
  etapa2.classList.remove('ativo');
  etapa3.classList.remove('ativo');

  if (etapa === 1) etapa1.classList.add('ativo');
  if (etapa === 2) etapa2.classList.add('ativo');
  if (etapa === 3) etapa3.classList.add('ativo');

  if (barra) {
    barra.style.width = `${etapa * 33}%`;
  }
}







