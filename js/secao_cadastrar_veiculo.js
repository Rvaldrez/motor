document.addEventListener("DOMContentLoaded", () => {
    const campoPlaca = document.getElementById("placaPainel");
    const campoKm = document.getElementById("kmPainel");
  
    if (campoPlaca) {
      campoPlaca.addEventListener("input", () => {
        campoPlaca.value = campoPlaca.value.toUpperCase();
      });
    }
  
    if (campoKm) {
      campoKm.addEventListener("input", () => {
        let valor = campoKm.value.replace(/\D/g, "");
        campoKm.value = valor.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
      });
    }
  
    // Carregar marcas ao abrir a aba
    const botaoMenu = document.querySelector('li[onclick="showSection(\'cadastrarVeiculo\')"]');
    if (botaoMenu) {
      botaoMenu.addEventListener("click", () => {
        fetch("carregar_marcas.php")
          .then(res => res.json())
          .then(marcas => {
            const select = document.getElementById("marcaPainel");
            select.innerHTML = '<option value="">Selecione uma Marca</option>';
            marcas.forEach(m => {
              select.innerHTML += `<option value="${m.id}">${m.nome}</option>`;
            });
          });
      });
    }
  });
  
  function carregarModelosPainel() {
    const marca = document.getElementById("marcaPainel").value;
    fetch(`carregar_modelos.php?marca_id=${marca}`)
      .then(res => res.json())
      .then(modelos => {
        const select = document.getElementById("modeloPainel");
        select.innerHTML = '<option value="">Selecione um Modelo</option>';
        modelos.forEach(m => {
          select.innerHTML += `<option value="${m.id}">${m.nome}</option>`;
        });
      });
  }
  
  function carregarAnoPainel() {
    const marca = document.getElementById("marcaPainel").value;
    const modelo = document.getElementById("modeloPainel").value;
    fetch(`carregar_ano.php?marca_id=${marca}&modelo_id=${modelo}`)
      .then(res => res.json())
      .then(anos => {
        const select = document.getElementById("anoPainel");
        select.innerHTML = '<option value="">Selecione o Ano do Modelo</option>';
        anos.forEach(a => {
          select.innerHTML += `<option value="${a.ano}">${a.ano}</option>`;
        });
      });
  }
  
  function carregarPrecoPainel() {
    const modelo = document.getElementById("modeloPainel").value;
    const ano = document.getElementById("anoPainel").value;
    fetch(`carregar_preco.php?modelo_id=${modelo}&ano=${ano}`)
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          document.getElementById("precoPainel").value = data.preco;
        }
      });
  }
  
  function mostrarMiniatura(event, inputId) {
    const input = document.getElementById(inputId);
    const file = input.files[0];
    const miniatura = document.getElementById(`miniatura-${inputId}`);
    const container = input.closest(".camera-upload");
    const cameraIcon = container.querySelector('.camera-icon');
  
    if (!file) return;
  
    new Compressor(file, {
      quality: 0.6,
      success(compressed) {
        // Armazena o arquivo comprimido para enviar depois
        input.compressedFile = compressed;
  
        const reader = new FileReader();
        reader.onload = function (e) {
          miniatura.innerHTML = `<img src="${e.target.result}" class="foto-preview" alt="Miniatura">`;
          miniatura.style.display = 'flex';
          if (cameraIcon) cameraIcon.style.display = 'none';
        };
        reader.readAsDataURL(compressed);
      },
      error(err) {
        alert("Erro ao processar imagem: " + err.message);
      }
    });
  }

  function enviarVeiculoDoPainel() {
    const form = document.getElementById("formVeiculoPainel");
    const formData = new FormData();
  
    // Verifica os campos obrigatórios
    const campos = ["placa", "marca", "modelo", "ano_fabrica", "quilometragem"];
    for (let campo of campos) {
      const input = form.querySelector(`[name="${campo}"]`);
      if (!input || !input.value.trim()) {
        mostrarPopup(`⚠️ O campo ${campo} é obrigatório.`);
        return;
      }
      formData.append(campo, input.value.trim());
    }
  
    // Adiciona o preço oculto
    const preco = document.getElementById("precoPainel").value || "0";
    formData.append("preco", preco);
  
    // Verifica se as 6 fotos estão preenchidas
    for (let i = 1; i <= 6; i++) {
      const input = document.getElementById(`foto${i}Painel`);
      const file = input?.compressedFile || input?.files?.[0];
      if (!file) {
        mostrarPopup("⚠️ Por favor, envie todas as 6 fotos do veículo.");
        return;
      }
      formData.append(`foto${i}`, file);
    }
  
    // Mostra o loader
    document.getElementById("loader").style.display = "flex";
  
    // Envia para o backend
    fetch("processa_cadastro_veiculo.php", {
      method: "POST",
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      document.getElementById("loader").style.display = "none";
  
      if (data.success) {
        mostrarPopup("✅ Veículo cadastrado com sucesso!");
        setTimeout(() => {
          localStorage.setItem("secao_ativa", "veiculos");
          location.reload();
        }, 1500);
      } else {
        mostrarPopup("❌ Erro: " + (data.message || "não foi possível cadastrar."));
      }
    })
    .catch(() => {
      document.getElementById("loader").style.display = "none";
      mostrarPopup("❌ Erro de conexão ao enviar dados.");
    });
  }
  
  