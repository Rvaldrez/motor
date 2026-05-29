console.log("✅ JS carregado!");

document.addEventListener("DOMContentLoaded", () => {
  if (typeof USUARIO_TIPO !== "undefined" && USUARIO_TIPO === "vendedor") {
    document.addEventListener("click", function (e) {
      const target = e.target;

      if (target.classList.contains("btn-negociar")) {
        const id = target.dataset.id;
        const form = document.getElementById("negociacao" + id);
        if (form) form.classList.add("ativo");
      }

      if (target.classList.contains("btn-cancelar")) {
        const id = target.dataset.id;
        const form = document.getElementById("negociacao" + id);
        if (form) form.classList.remove("ativo");
      }

      if (target.classList.contains("btn-aceitar")) {
        const id = target.dataset.id;
        aceitarProposta(id);
      }

      if (target.classList.contains("btn-recusar")) {
        const id = target.dataset.id;
        recusarProposta(id);
      }

      const btnEnviar = target.closest(".btn-enviar-contraproposta");
      if (btnEnviar) {
        const id = btnEnviar.dataset.id;
        confirmarNegociacao(id);
        return;
      }

      if (target.classList.contains("btn-ok-recusa")) {
        const id = target.dataset.id;
        const card = document.querySelector(`.card-proposta-recebida[data-id='${id}']`);
        if (!card) return;

        const statusFinal = USUARIO_TIPO === "investidor"
          ? "historico_recusada_vendedor"
          : "historico_recusada_investidor";

        fetch("ocultar_proposta.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: new URLSearchParams({
            proposta_id: id,
            novo_status: statusFinal
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
            mostrarPopup("❌ Erro de conexão.");
          });
      }
    });
  } // ← ESSA era a chave de fechamento que faltava!
});

    

function aceitarProposta(id) {
  mostrarLoader(); // 🌀 Exibe o loader antes de tudo

  fetch("aceitar_proposta.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({ proposta_id: id })
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        mostrarPopup("✅ Proposta aceita com sucesso!");

        const card = document.querySelector(`[data-id='${id}']`);
        if (card) {
          const acoes = card.querySelector(".acoes-proposta");
          if (acoes) acoes.remove();

          const status = card.querySelector(".status-aguardando");
          if (status) status.innerHTML = '✅ Proposta aceita';

          const info = document.createElement("div");
          info.innerHTML = `
            <div class="mensagem-aceita-vendedor">
              <img src="imagens/check.png" alt="Ícone de check" />
              <div class="mensagem-texto">
                <p><strong>Parabéns!</strong> Abaixo, seguem os dados do comprador de seu veículo. Entre em contato com ele para concluir a venda. Lhe enviamos um email com nossas sugestões.</p>
              </div>
            </div>
          `;
          card.appendChild(info);
        }

        // 🔁 Após delay, recarrega a página
        setTimeout(() => {
          window.location.reload();
        }, 2000); // ⏱️ Dá tempo do usuário ver o popup e alterações

      } else {
        esconderLoader(); // Esconde o loader em caso de falha
        mostrarPopup("❌ Erro ao aceitar a proposta.");
      }
    })
    .catch(() => {
      esconderLoader();
      mostrarPopup("❌ Erro de conexão.");
    });
}



function recusarProposta(id) {
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

        const card = document.querySelector(`[data-id='${id}']`);
        if (data.success) {
          mostrarPopup("🚫 Proposta recusada.");

          if (card) {
            if (typeof USUARIO_TIPO !== "undefined" && USUARIO_TIPO === "vendedor") {
              card.remove(); // 🔄 remove imediatamente do DOM
            } else {
              const acoes = card.querySelector(".acoes-proposta");
              const form = card.querySelector(".form-negociacao");
              if (acoes) acoes.remove();
              if (form) form.remove();

              const mensagem = document.createElement("div");
              mensagem.className = "mensagem-recusa-investidor";
              mensagem.innerHTML = `
                <img src="imagens/cancela.png" alt="Ícone de recusa" />
                <div class="mensagem-texto">
                  <p><strong>Sua proposta foi recusada.</strong></p>
                  <p>Mas, se desejar, você pode fazer uma nova proposta para esse veículo acessando o painel <strong>“Oferta de Veículos”</strong>, caso ele ainda esteja disponível.</p>
                </div>
                <div style="margin-top: 10px; text-align: center;">
                  <button class="btn-ok-recusa" data-id="${id}">Ok</button>
                </div>
              `;
              card.appendChild(mensagem);
            }
          }
        } else {
          mostrarPopup("❌ Erro ao recusar a proposta.");
        }
      })
      .catch(() => {
        esconderLoader(); // 👈 Mesmo no erro, esconde
        mostrarPopup("❌ Erro de conexão.");
      });
  });
}



function confirmarNegociacao(id) {
  const campo = document.getElementById("valorNegociado" + id);
  const btnEnviar = document.querySelector(`.btn-enviar-contraproposta[data-id='${id}']`);


  if (!campo || !btnEnviar) return mostrarPopup("❌ Erro interno.");

  const valorFloat = parseValorMonetario(campo.value);

  if (!valorFloat || valorFloat <= 0) {
    mostrarPopup("⚠️ Informe um valor válido.");
    return;
  }

  function parseValorMonetario(valorBruto) {
    if (typeof valorBruto === "number") return valorBruto;

    let valor = String(valorBruto || "")
      .replace(/\s/g, "")
      .replace("R$", "")
      .replace(/[^\d,.-]/g, "");

    if (!valor) return 0;

    if (valor.includes(",")) {
      valor = valor.replace(/\./g, "").replace(",", ".");
    } else if (/^\d{1,3}(\.\d{3})+$/.test(valor)) {
      valor = valor.replace(/\./g, "");
    }

    const numero = parseFloat(valor);
    return Number.isFinite(numero) ? numero : 0;
  }

  mostrarLoader();
  btnEnviar.disabled = true;
  btnEnviar.innerHTML = `<span class="spinner-loader"></span> Enviando...`;

  fetch("processar_contraproposta.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({ proposta_id: id, novo_valor: valorFloat })
  })
    .then(res => res.json())
    .then(data => {
      esconderLoader();
      if (data.success) {
        mostrarPopup("✅ Contraproposta enviada.");
        setTimeout(() => location.reload(), 2000);
      } else {
        mostrarPopup("❌ " + (data.message || "Erro ao enviar contraproposta."));
        btnEnviar.disabled = false;
        btnEnviar.innerHTML = "Enviar";
      }
    })
    .catch(() => {
      esconderLoader();
      mostrarPopup("❌ Erro ao conectar com o servidor.");
      btnEnviar.disabled = false;
      btnEnviar.innerHTML = "Enviar";
    });
}

// ✅ Máscara monetária
document.addEventListener("input", function (e) {
  if (e.target.classList.contains("mascara-valor")) {
    let valor = e.target.value.replace(/\D/g, "");
    valor = (valor / 100).toFixed(2) + "";
    valor = valor.replace(".", ",").replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    e.target.value = "R$ " + valor;
  }
});

// ✅ Copiar dados do comprador
function copiarDadosComprador(nome, telefone, email) {
  const texto = `Nome: ${nome}\nTelefone: ${telefone}\nEmail: ${email}`;
  
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(texto)
      .then(() => {
        mostrarPopup("📋 Dados copiados com sucesso!");
      })
      .catch((err) => {
        console.error("Erro ao copiar:", err);
        mostrarPopup("❌ Erro ao copiar os dados.");
      });
  } else {
    const textarea = document.createElement("textarea");
    textarea.value = texto;
    document.body.appendChild(textarea);
    textarea.select();
    try {
      document.execCommand("copy");
      mostrarPopup("📋 Dados copiados com sucesso!");
    } catch (err) {
      console.error("Fallback: Erro ao copiar:", err);
      mostrarPopup("❌ Erro ao copiar os dados.");
    }
    document.body.removeChild(textarea);
  }
}

// ✅ Loader popup

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

