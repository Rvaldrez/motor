

// Interceptar quando a seção de ofertas for carregada
const showSectionOriginal = window.showSection;
window.showSection = function(section) {
    if (showSectionOriginal) {
        showSectionOriginal(section);
    }
    
    // Se for a seção de ofertas, carregar as ofertas
    if (section === 'ofertaVeiculos') {
        setTimeout(function() {
            if (typeof carregarOfertaVeiculos === 'function') {
                carregarOfertaVeiculos();
            }
        }, 100);
    }
};


// ✅ Quando o DOM estiver pronto, inicializa tudo
document.addEventListener("DOMContentLoaded", () => {
  // Sidebar e seção ativa
  const propostasTab = document.querySelector('li[onclick="showSection(\'propostas\')"]');
  if (propostasTab) {
    propostasTab.addEventListener("click", carregarPropostasRecebidas);
  }

  const urlParams = new URLSearchParams(window.location.search);
  const msg = urlParams.get("mensagem");
  if (msg === "veiculo_atualizado") {
    mostrarPopup("✅ Veículo atualizado com sucesso!");
    history.replaceState(null, "", window.location.pathname);
  }

  const secaoAtiva = localStorage.getItem("secao_ativa");
  if (secaoAtiva && document.getElementById(secaoAtiva)) {
    showSection(secaoAtiva);
    localStorage.removeItem("secao_ativa");
  } else {
    showSection("painel");
  }
  

  // Máscara para KM
  const campoKmPainel = document.getElementById("kmPainel");
  if (campoKmPainel) {
    campoKmPainel.addEventListener("input", function () {
      let valor = this.value.replace(/\D/g, "");
      this.value = valor.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    });
  }

  // Máscara para Placa
  const campoPlaca = document.getElementById("placaPainel");
  if (campoPlaca) {
    campoPlaca.addEventListener("input", function () {
      this.value = this.value.toUpperCase();
    });
  }

  // CEP automático
  const cepInput = document.getElementById("cep");
  if (cepInput) {
    cepInput.addEventListener("blur", function () {
      const cep = this.value.replace(/\D/g, "");
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
          document.getElementById("endereco").value = data.logradouro || "";
          document.getElementById("cidade").value = data.localidade || "";
          document.getElementById("estado").value = data.uf || "";
        })
        .catch(() => {
          mostrarPopup("❌ Erro ao buscar o endereço. Tente novamente.");
        });
    });

    cepInput.addEventListener("input", function (e) {
      let value = e.target.value.replace(/\D/g, "").slice(0, 8);
      if (value.length > 5) {
        value = value.slice(0, 5) + "-" + value.slice(5);
      }
      e.target.value = value;
    });
  }

  // Inicializa o form de edição de veículo
  aplicarSubmitEditarVeiculo();

  // ✅ Listener GLOBAL para clique no botão .btn-editar
  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".btn-editar");
    if (btn && btn.dataset.id) {
      console.log("🛠️ Botão .btn-editar clicado com ID:", btn.dataset.id);
      window.editarVeiculo(btn.dataset.id);
    }
  });


    // ✅ Listener GLOBAL para clique no botão .btn-editar
    document.addEventListener("click", (e) => {
      const btn = e.target.closest(".card-veiculo-btn-editar");
      if (btn && btn.dataset.id) {
        console.log("🛠️ Botão .btn-editar clicado com ID:", btn.dataset.id);
        window.editarVeiculo(btn.dataset.id);
      }
    });


  // ✅ Intercepta envio do formulário de Meus Dados
  const formDados = document.getElementById("formDados");
  if (formDados) {
    formDados.addEventListener("submit", function (e) {
      e.preventDefault();

      const formData = new FormData(formDados);
      const loader = document.getElementById("loader");
      if (loader) loader.style.display = "flex";

      fetch("atualizar_dados.php", {
        method: "POST",
        body: formData,
      })
        .then(res => res.json())
        .then(data => {
          if (loader) loader.style.display = "none";
          mostrarPopup(data.message || "✅ Dados atualizados com sucesso!");
        })
        .catch(err => {
          if (loader) loader.style.display = "none";
          console.error("❌ Erro ao atualizar:", err);
          mostrarPopup("❌ Erro ao atualizar os dados.");
        });
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

// ✅ Função para aplicar submit no form de edição de veículo
function aplicarSubmitEditarVeiculo() {
  const formEditar = document.getElementById("formEditarVeiculo");
  if (!formEditar) return;

  formEditar.addEventListener("submit", function (e) {
    e.preventDefault();

    const formData = new FormData(formEditar);
    const fileInputs = formEditar.querySelectorAll('input[type="file"]');

    fileInputs.forEach((input) => {
      if (input.getAttribute("data-modificado") === "1" && input.compressedFile) {
        formData.set(input.name, input.compressedFile);
      }
    });

    const loader = document.getElementById("loader");
    if (loader) loader.style.display = "flex";

    fetch("salvar_edicao_veiculo.php", {
      method: "POST",
      body: formData,
    })
      .then(res => res.json())
      .then(data => {
        if (loader) loader.style.display = "none";

        console.log("📦 RESPOSTA DO PHP:", data);

        if (data.success) {
          mostrarPopup(data.message || "✅ Veículo atualizado com sucesso.");

          if (typeof window.carregarMeusVeiculos === "function") {
            console.log("🔁 Recarregando lista de veículos...");
            window.carregarMeusVeiculos();
          } else {
            console.warn("⚠️ Função carregarMeusVeiculos não encontrada no escopo global.");
          }
          localStorage.setItem("secao_ativa", "meusVeiculos");
          showSection("meusVeiculos");
        } else {
          const erroFotos = (data.errors || []).join("\n");
          mostrarPopup(data.message + "\n" + erroFotos);
        }
      })
      .catch((err) => {
        if (loader) loader.style.display = "none";
        console.error("❌ Erro no envio:", err);
        mostrarPopup("❌ Erro ao salvar alterações.");
      });
  });
}

document.addEventListener('click', function(e) {
  const btnEditar = e.target.closest('.btn-editar');
  if (btnEditar) {
    e.preventDefault();
    e.stopPropagation();
    const veiculoId = btnEditar.dataset.id;
    if (veiculoId && typeof editarVeiculo === "function") {
      localStorage.setItem("secao_ativa", "edicaoVeiculo"); // ⬅️ Adicionado aqui
      editarVeiculo(veiculoId);
    }
  }
});





// ✅ Carrega propostas recebidas
function carregarPropostasRecebidas() {
  const container = document.getElementById("listaPropostasRecebidas");
  container.innerHTML = "<p>Carregando propostas...</p>";

  if (!USUARIO_TIPO) {
    container.innerHTML = "<p style='color:red;'>Erro: tipo de usuário indefinido.</p>";
    return;
  }

  const url = USUARIO_TIPO === 'investidor'
    ? 'listar_propostas_recebidas_investidor.php'
    : 'listar_propostas_recebidas.php';

  fetch(url)
    .then(res => res.text())
    .then(html => {
      container.innerHTML = html;
    })
    .catch(() => {
      container.innerHTML = "<p style='color:red;'>Erro ao carregar propostas.</p>";
    });
}



// ✅ Executa ao abrir a aba "Propostas Recebidas"
document.querySelector('li[onclick="showSection(\'propostasRecebidas\')"]')
  ?.addEventListener("click", carregarPropostasRecebidas);
