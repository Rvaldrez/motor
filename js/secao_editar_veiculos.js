console.log("✅ secao_editar_veiculos.js carregado");


function mostrarMiniaturaEditar(event, inputId) {
    const input = document.getElementById(inputId);
    const file = input.files[0];
    const miniatura = document.getElementById(`miniatura-${inputId}`);
    const container = input.closest(".camera-upload");
    const cameraIcon = container.querySelector('.camera-icon');
  
    if (!file) return;
  
    new Compressor(file, {
      quality: 0.6,
      success(compressed) {
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

  

// ✅ Função global de edição
window.editarVeiculo = function (veiculoId) {
    console.log("✅ Chamando editarVeiculo", veiculoId);
  
    fetch(`carregar_veiculo.php?id=${veiculoId}`)
      .then(res => res.json())
      .then(data => {
        if (!data.success || !data.veiculo) {
          throw new Error("Erro ao carregar dados");
        }
  
        const v = data.veiculo;
  
        document.getElementById("editar_veiculo_id").value = v.id || "";
        document.getElementById("editar_placa").value = v.placa || "";
        document.getElementById("editar_marca").value = v.marca || "";
        document.getElementById("editar_modelo").value = v.modelo || "";
        document.getElementById("editar_ano_fabrica").value = v.ano_fabrica || "";
        document.getElementById("editar_km").value = Number(v.quilometragem).toLocaleString("pt-BR");
  
        const grid = document.getElementById("editar_fotos_grid");
        grid.innerHTML = "";
  
        // ✅ Gera até 6 imagens (mesmo que nem todas existam)
        for (let i = 0; i < 6; i++) {
          const inputId = `editar_foto${i}`;
          const container = document.createElement("div");
          container.classList.add("camera-upload");
          container.onclick = () => document.getElementById(inputId)?.click();
  
          const input = document.createElement("input");
          input.type = "file";
          input.name = "foto" + i;
          input.id = inputId;
          input.accept = "image/*";
          input.style.display = "none";
          input.addEventListener("change", (e) => mostrarMiniaturaEditar(e, inputId));
  
          const miniatura = document.createElement("div");
          miniatura.classList.add("miniatura");
          miniatura.id = "miniatura-" + inputId;
  
          // ✅ Se a imagem existir, insere no preview
          if (v.fotos && v.fotos[i]) {
            miniatura.innerHTML = `<img src="${v.fotos[i]}" class="foto-preview" alt="Foto ${i + 1}">`;
            container.classList.add("has-image");
          }
  
          const icone = document.createElement("img");
          icone.src = "imagens/camera.png";
          icone.classList.add("camera-icon");
  
          container.appendChild(input);
          container.appendChild(miniatura);
          container.appendChild(icone);
          grid.appendChild(container);
        }
  
        showSection("edicaoVeiculo");
      })
      .catch((err) => {
        console.error(err);
        mostrarPopup("❌ Erro ao conectar com o servidor para carregar o veículo.");
      });
  };
  