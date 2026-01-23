console.log("✅ secao_meus_veiculos.js carregado");

// ✅ Recarrega a lista de veículos do usuário
console.log("✅ secao_meus_veiculos.js carregado");




      // ✅ Recarrega a lista de veículos do usuário
window.carregarMeusVeiculos = function () {
  fetch('listar_meus_veiculos.php')
    .then(res => {
      if (!res.ok) {
        if (res.status === 403) {
          throw new Error("sessao_expirada");
        } else {
          throw new Error("erro_http");
        }
      }
      return res.text();
    })
    .then(html => {
      const container = document.querySelector('#meusVeiculos .vehicle-list') ??
                        document.querySelector('#meusVeiculos .card-veiculo-lista');
      if (!container) {
        console.error("❌ Elemento .card-veiculo-lista não encontrado.");
        return;
      }

      // ✅ Simplesmente renderiza, sem checar se contém "Acesso negado"
      container.innerHTML = html.trim()
        ? html
        : `<p style="text-align: center; margin-top: 20px; color: #666;">Nenhum veículo cadastrado no momento.</p>`;
    })
    .catch((err) => {
      if (err.message === "sessao_expirada") {
        mostrarPopup("⚠️ Sessão expirada ou inválida. Recarregue a página.");
      } else {
        console.error("❌ Erro ao carregar veículos:", err);
        mostrarPopup("❌ Erro ao buscar os veículos. Tente novamente.");
      }
    });
};






// ✅ Remove um veículo via POST para remover_veiculo.php
function removerVeiculo(id) {
  if (!confirm("Deseja remover este veículo?")) return;

  fetch("remover_veiculo.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({ id })
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        mostrarPopup("✅ Veículo removido com sucesso.");
        setTimeout(() => carregarMeusVeiculos(), 1000);
      } else {
       // mostrarPopup("❌ Erro ao remover veículo.");
       mostrarPopup(data.message || "❌ Erro ao remover veículo."); // 👈 Mostra erro real
      }
    })
    .catch(() => {
      mostrarPopup("❌ Erro na conexão ao remover veículo.");
    });
}

// ✅ Abre a tela de edição de um veículo carregando seus dados
function abrirEdicaoVeiculo(id) {
  fetch(`carregar_veiculo.php?id=${id}`)
    .then(res => res.json())
    .then(data => {
      if (!data.success || !data.veiculo) {
        mostrarPopup("❌ Erro ao carregar os dados do veículo.");
        return;
      }

      const v = data.veiculo;
      document.getElementById("editar_veiculo_id").value = v.id;
      document.getElementById("editar_placa").value = v.placa;
      document.getElementById("editar_marca").value = v.marca;
      document.getElementById("editar_modelo").value = v.modelo;
      document.getElementById("editar_ano_fabrica").value = v.ano_fabrica;
      document.getElementById("editar_km").value = Number(v.quilometragem).toLocaleString("pt-BR");

      const fotosGrid = document.getElementById("editar_fotos_grid");
      fotosGrid.innerHTML = "";

      for (let i = 0; i < 6; i++) {
        const imgSrc = v.fotos?.[i] || "imagens/default_car.png";

        const label = document.createElement("label");
        label.classList.add("foto-box");

        const img = document.createElement("img");
        img.src = imgSrc;
        img.classList.add("foto-preview");
        img.id = "editar_preview" + i;

        const input = document.createElement("input");
        input.type = "file";
        input.name = "foto" + i;
        input.accept = "image/*";
        input.style.display = "none";
        input.addEventListener("change", () => previewFotoEditar(input, i));

        label.appendChild(img);
        label.appendChild(input);
        fotosGrid.appendChild(label);
      }

      showSection("editarVeiculo");
    })
    .catch(() => {
      mostrarPopup("❌ Erro ao conectar com o servidor para carregar o veículo.");
    });
}

// ✅ Exibe o preview de uma imagem editada e a comprime
function previewFotoEditar(input, index) {
  const file = input.files?.[0];
  if (!file) return;

  new Compressor(file, {
    quality: 0.6,
    success(compressed) {
      input.compressedFile = new File([compressed], file.name, { type: file.type });
      input.setAttribute("data-modificado", "1");

      const reader = new FileReader();
      reader.onload = function (e) {
        const preview = document.getElementById("editar_preview" + index);
        preview.src = e.target.result;
      };
      reader.readAsDataURL(compressed);
    },
    error(err) {
      alert("Erro ao processar imagem: " + err.message);
    }
  });
}

// ✅ Chamada após salvar a edição do veículo
window.finalizarEdicaoVeiculo = function () {
  mostrarPopup("✅ Veículo atualizado com sucesso!");
  setTimeout(() => {
    carregarMeusVeiculos();
    showSection("meusVeiculos");
  }, 1000);
}
