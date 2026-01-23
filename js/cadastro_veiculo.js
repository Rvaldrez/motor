/**
 * MotorGo - Sistema de Cadastro de Veículos
 * Versão Otimizada e Limpa - SEM CONFLITOS
 * 
 * Este arquivo substitui o cadastro_veiculo.js original
 * Elimina duplicações e conflitos entre arquivos
 */

// 🔧 ESTADO GLOBAL DA APLICAÇÃO
const MotorGo = {
  state: {
    usuarioId: null,
    veiculoId: null,
    etapaAtual: 1,
    validando: {
      cpf: false,
      email: false,
      cep: false
    },
    initialized: false
  },
  
  // 🔹 INICIALIZAÇÃO
  init() {
    if (this.state.initialized) {
      console.log('⚠️ MotorGo já foi inicializado');
      return;
    }
    
    console.log('🚀 Inicializando MotorGo...');
    
    this.verificarContinuacao();
    this.configurarValidacoes();
    this.configurarEventos();
    
    this.state.initialized = true;
    console.log('✅ MotorGo inicializado com sucesso');
  },
  
  // 🔍 VERIFICA SE DEVE CONTINUAR DE UMA ETAPA ESPECÍFICA
  verificarContinuacao() {
    const etapaSession = sessionStorage.getItem('etapa');
    const usuarioIdSession = sessionStorage.getItem('usuario_id');
    const pularEtapa1 = sessionStorage.getItem('pular_etapa1');
    
    if (pularEtapa1 === 'true' && usuarioIdSession) {
      console.log('🔄 Continuando cadastro via email...');
      this.state.usuarioId = usuarioIdSession;
      
      // Determina etapa
      if (etapaSession === 'etapa3') {
        this.mostrarEtapa(3);
        this.state.veiculoId = sessionStorage.getItem('veiculo_id');
      } else {
        this.mostrarEtapa(2);
        this.carregarMarcas();
      }
      
      // Limpa flags
      sessionStorage.removeItem('pular_etapa1');
      sessionStorage.removeItem('etapa');
    } else {
      // Fluxo normal - inicia na Etapa 1
      this.mostrarEtapa(1);
    }
  },
  
  // 🎯 MOSTRAR ETAPA ESPECÍFICA
  mostrarEtapa(numeroEtapa) {
    // Esconde todas as etapas
    document.querySelectorAll('.etapa').forEach(etapa => {
      etapa.classList.add('hidden');
    });
    
    // Mostra a etapa desejada
    const etapaDesejada = document.getElementById(`parte${numeroEtapa}`);
    if (etapaDesejada) {
      etapaDesejada.classList.remove('hidden');
    }
    
    this.atualizarProgresso(numeroEtapa);
  },
  
  // 📊 ATUALIZAR BARRA DE PROGRESSO
  atualizarProgresso(etapa) {
    const barra = document.getElementById('barra');
    const etapas = document.querySelectorAll('#etapas span');
    
    if (!barra) return;
    
    // Remove classes de todas as etapas
    etapas.forEach(e => e.classList.remove('ativo', 'concluido'));
    
    this.state.etapaAtual = etapa;
    
    switch(etapa) {
      case 1:
        barra.style.width = '33%';
        document.getElementById('etapa1')?.classList.add('ativo');
        break;
      case 2:
        barra.style.width = '66%';
        document.getElementById('etapa1')?.classList.add('concluido');
        document.getElementById('etapa2')?.classList.add('ativo');
        break;
      case 3:
        barra.style.width = '100%';
        document.getElementById('etapa1')?.classList.add('concluido');
        document.getElementById('etapa2')?.classList.add('concluido');
        document.getElementById('etapa3')?.classList.add('ativo');
        break;
    }
  },
  
  // 🔧 CONFIGURAR VALIDAÇÕES
  configurarValidacoes() {
    this.aplicarMascaras();
    this.configurarValidacoesAssincrona();
  },
  
  // 🎭 APLICAR MÁSCARAS
  aplicarMascaras() {
    // CPF
    const campoCPF = document.getElementById("cpf");
    if (campoCPF && !campoCPF.hasAttribute('data-mask-applied')) {
      campoCPF.setAttribute('data-mask-applied', 'true');
      
      campoCPF.addEventListener("input", function () {
        let cpf = this.value.replace(/\D/g, "").slice(0, 11);
        cpf = cpf.replace(/(\d{3})(\d)/, "$1.$2");
        cpf = cpf.replace(/(\d{3})(\d)/, "$1.$2");
        cpf = cpf.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
        this.value = cpf;
        this.classList.remove("campo-erro", "campo-sucesso");
      });
    }

    // CEP
    const campoCEP = document.getElementById("cep");
    if (campoCEP && !campoCEP.hasAttribute('data-mask-applied')) {
      campoCEP.setAttribute('data-mask-applied', 'true');
      
      campoCEP.addEventListener("input", function () {
        let cep = this.value.replace(/\D/g, "").slice(0, 8);
        this.value = cep.replace(/^(\d{5})(\d)/, "$1-$2");
        this.classList.remove("campo-erro", "campo-sucesso");
      });
    }

    // Celular
    const campoCelular = document.getElementById("celular");
    if (campoCelular && !campoCelular.hasAttribute('data-mask-applied')) {
      campoCelular.setAttribute('data-mask-applied', 'true');
      
      campoCelular.addEventListener("input", function () {
        let celular = this.value.replace(/\D/g, "").slice(0, 11);
        celular = celular.replace(/^(\d{2})(\d)/, "($1) $2");
        celular = celular.replace(/(\d{5})(\d{4})$/, "$1-$2");
        this.value = celular;
      });
    }

    // Quilometragem
    const campoKM = document.getElementById("quilometragem");
    if (campoKM && !campoKM.hasAttribute('data-mask-applied')) {
      campoKM.setAttribute('data-mask-applied', 'true');
      
      campoKM.addEventListener("input", function () {
        let valor = this.value.replace(/\D/g, "");
        valor = valor.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        this.value = valor;
      });
    }
    
    // Placa em maiúscula
    const placaInput = document.getElementById("placa");
    if (placaInput && !placaInput.hasAttribute('data-mask-applied')) {
      placaInput.setAttribute('data-mask-applied', 'true');
      
      placaInput.addEventListener("input", function () {
        this.value = this.value.toUpperCase();
      });
    }
  },
  
  // 🔍 CONFIGURAR VALIDAÇÕES ASSÍNCRONAS
  configurarValidacoesAssincrona() {
    // Validação CPF
    const campoCPF = document.getElementById("cpf");
    if (campoCPF && !campoCPF.hasAttribute('data-validation-applied')) {
      campoCPF.setAttribute('data-validation-applied', 'true');
      
      campoCPF.addEventListener("blur", async (e) => {
        await this.validarCPF(e.target);
      });
    }

    // Validação CEP
    const campoCEP = document.getElementById("cep");
    if (campoCEP && !campoCEP.hasAttribute('data-validation-applied')) {
      campoCEP.setAttribute('data-validation-applied', 'true');
      
      campoCEP.addEventListener("blur", async (e) => {
        await this.validarCEP(e.target);
      });
    }

    // Validação Email
    const campoEmail = document.getElementById("emailCadastro");
    if (campoEmail && !campoEmail.hasAttribute('data-validation-applied')) {
      campoEmail.setAttribute('data-validation-applied', 'true');
      
      campoEmail.addEventListener("blur", async (e) => {
        await this.validarEmail(e.target);
      });
    }
  },
  
  // ✅ VALIDAR CPF
  async validarCPF(campo) {
    if (this.state.validando.cpf) return;
    
    const cpfValue = campo.value.trim();
    if (cpfValue === "" || cpfValue.replace(/\D/g, "").length < 11) return;
    
    this.state.validando.cpf = true;
    campo.classList.add("campo-validando");
    
    try {
      const response = await fetch('verificar_cpf.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ cpf: cpfValue })
      });
      
      const resultado = await response.json();
      
      campo.classList.remove("campo-validando");
      
      if (!resultado.valido) {
        campo.classList.add("campo-erro");
        this.mostrarPopup("❌ " + resultado.erro, () => {
          campo.focus();
          campo.select();
        });
      } else {
        campo.classList.add("campo-sucesso");
      }
      
    } catch (error) {
      console.error('Erro ao validar CPF:', error);
      campo.classList.remove("campo-validando");
    } finally {
      this.state.validando.cpf = false;
    }
  },
  
  // ✅ VALIDAR CEP
  async validarCEP(campo) {
    if (this.state.validando.cep) return;
    
    const cep = campo.value.replace(/\D/g, '');
    if (cep.length !== 8) {
      if (cep.length > 0) {
        campo.classList.add("campo-erro");
        this.mostrarPopup("❌ CEP deve ter exatamente 8 dígitos.");
      }
      return;
    }
    
    this.state.validando.cep = true;
    campo.classList.add("campo-validando");
    
    try {
      const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
      const data = await response.json();
      
      campo.classList.remove("campo-validando");
      
      if (!data.erro && data.localidade && data.uf) {
        campo.classList.add("campo-sucesso");
        
        document.getElementById("endereco").value = data.logradouro || '';
        document.getElementById("cidade").value = data.localidade || '';
        document.getElementById("estado").value = data.uf || '';
        
        this.mostrarPopup(`✅ CEP confirmado: ${data.localidade}/${data.uf}`);
      } else {
        campo.classList.add("campo-erro");
        this.mostrarPopup("❌ CEP não encontrado.");
      }
    } catch (error) {
      campo.classList.remove("campo-validando");
      campo.classList.add("campo-erro");
      this.mostrarPopup("❌ Erro ao consultar CEP. Verifique sua conexão.");
    } finally {
      this.state.validando.cep = false;
    }
  },
  
  // ✅ VALIDAR EMAIL
  async validarEmail(campo) {
    if (this.state.validando.email) return;
    
    const emailValue = campo.value.trim();
    if (emailValue === "") return;
    
    this.state.validando.email = true;
    campo.classList.add("campo-validando");
    
    try {
      const response = await fetch('verificar_email.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ email: emailValue })
      });
      
      const resultado = await response.json();
      
      campo.classList.remove("campo-validando");
      
      if (!resultado.valido) {
        campo.classList.add("campo-erro");
        this.mostrarPopup("❌ " + resultado.erro, () => {
          campo.focus();
          campo.select();
        });
      } else {
        campo.classList.add("campo-sucesso");
      }
      
    } catch (error) {
      console.error('Erro ao validar email:', error);
      campo.classList.remove("campo-validando");
    } finally {
      this.state.validando.email = false;
    }
  },
  
  // 🚗 CARREGAR DADOS DO VEÍCULO
  carregarMarcas() {
    fetch('carregar_marcas.php')
      .then(response => response.json())
      .then(marcas => {
        const marcaSelect = document.getElementById('marca');
        if (marcaSelect) {
          marcaSelect.innerHTML = '<option value="">Selecione uma Marca</option>';
          marcas.forEach(marca => {
            marcaSelect.innerHTML += `<option value="${marca.id}">${marca.nome}</option>`;
          });
        }
      })
      .catch(error => console.error("Erro ao carregar marcas:", error));
  },
  
  carregarModelos() {
    const marcaId = document.getElementById('marca')?.value;
    const modeloSelect = document.getElementById('modelo');
    const anoSelect = document.getElementById('ano_fabrica');
    
    if (!modeloSelect) return;
    
    modeloSelect.innerHTML = '<option value="">Carregando...</option>';
    if (anoSelect) anoSelect.innerHTML = '<option value="">Selecione um Modelo primeiro</option>';
    
    if (marcaId) {
      fetch(`carregar_modelos.php?marca_id=${marcaId}`)
        .then(response => response.json())
        .then(modelos => {
          modeloSelect.innerHTML = '<option value="">Selecione um Modelo</option>';
          modelos.forEach(modelo => {
            modeloSelect.innerHTML += `<option value="${modelo.id}">${modelo.nome}</option>`;
          });
        })
        .catch(error => {
          console.error("Erro ao carregar modelos:", error);
          modeloSelect.innerHTML = '<option value="">Erro ao carregar</option>';
        });
    } else {
      modeloSelect.innerHTML = '<option value="">Selecione uma Marca primeiro</option>';
    }
  },
  
  carregarAno() {
    const marcaId = document.getElementById('marca')?.value?.trim();
    const modeloId = document.getElementById('modelo')?.value?.trim();
    const anoSelect = document.getElementById('ano_fabrica');

    if (!anoSelect || !marcaId || !modeloId) {
      if (anoSelect) anoSelect.innerHTML = '<option value="">Selecione um Modelo primeiro</option>';
      return;
    }

    anoSelect.innerHTML = '<option value="">Carregando...</option>';

    fetch(`carregar_ano.php?marca_id=${encodeURIComponent(marcaId)}&modelo_id=${encodeURIComponent(modeloId)}`)
      .then(response => {
        if (!response.ok) {
          throw new Error(`Erro HTTP: ${response.status}`);
        }
        return response.json();
      })
      .then(anos => {
        anoSelect.innerHTML = '<option value="">Selecione o Ano</option>';
        if (!anos || anos.length === 0) {
          anoSelect.innerHTML = '<option value="">Nenhum ano disponível</option>';
        } else {
          anos.forEach(dado => {
            anoSelect.innerHTML += `<option value="${dado.ano}">${dado.ano}</option>`;
          });
        }
      })
      .catch(error => {
        console.error("Erro ao carregar anos:", error);
        anoSelect.innerHTML = '<option value="">Erro ao carregar</option>';
      });
  },
  
  carregarPreco() {
    const modeloId = document.getElementById('modelo')?.value;
    const ano = document.getElementById('ano_fabrica')?.value;

    if (!modeloId || !ano) {
      return;
    }

    fetch(`carregar_preco.php?modelo_id=${encodeURIComponent(modeloId)}&ano=${encodeURIComponent(ano)}`)
      .then(response => {
          if (!response.ok) {
              throw new Error(`Erro HTTP: ${response.status}`);
          }
          return response.json();
      })
      .then(data => {
          if (data.success) {
              const precoInput = document.getElementById('preco');
              if (precoInput) precoInput.value = data.preco;
          } else {
              console.warn("Aviso:", data.message);
              const precoInput = document.getElementById('preco');
              if (precoInput) precoInput.value = "";
          }
      })
      .catch(error => console.error("Erro ao carregar preço:", error));
  },
  
  // 🔧 CONFIGURAR EVENTOS
  configurarEventos() {
    // Event listeners para carregamento dos dados do veículo
    const marcaSelect = document.getElementById('marca');
    const modeloSelect = document.getElementById('modelo');
    const anoSelect = document.getElementById('ano_fabrica');
    
    if (marcaSelect) marcaSelect.addEventListener('change', () => this.carregarModelos());
    if (modeloSelect) modeloSelect.addEventListener('change', () => this.carregarAno());
    if (anoSelect) anoSelect.addEventListener('change', () => this.carregarPreco());
    
    // Upload de fotos
    for (let i = 1; i <= 6; i++) {
      const fotoInput = document.getElementById(`foto${i}`);
      if (fotoInput) {
        fotoInput.addEventListener('change', (e) => this.mostrarMiniatura(e, `foto${i}`));
      }
    }
  },
  
  // 📷 MOSTRAR MINIATURA DAS FOTOS
  mostrarMiniatura(event, fotoId) {
    const input = event.target;
    const file = input.files[0];
    const uploadBox = input.closest(".custom-upload-box");
    const miniaturaDiv = uploadBox?.querySelector(".custom-upload-preview");

    if (!file || !uploadBox || !miniaturaDiv) return;

    const formatosPermitidos = ["image/jpeg", "image/png", "image/webp"];
    if (!formatosPermitidos.includes(file.type)) {
        this.mostrarPopup("Formato inválido! Selecione apenas imagens JPEG, PNG ou WEBP.");
        input.value = "";
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        this.mostrarPopup(`A imagem "${file.name}" é muito grande! Escolha uma com até 5MB.`);
        input.value = "";
        return;
    }

    // Compressão com Compressor.js (se disponível)
    if (typeof Compressor !== 'undefined') {
        new Compressor(file, {
            quality: 0.6,
            maxWidth: 1280,
            success: (compressedFile) => {
                input.compressedFile = compressedFile;
                this.exibirMiniatura(compressedFile, uploadBox, miniaturaDiv);
            },
            error: (err) => {
                console.error("Erro ao comprimir imagem:", err.message);
                this.exibirMiniatura(file, uploadBox, miniaturaDiv);
            }
        });
    } else {
        // Sem compressão
        this.exibirMiniatura(file, uploadBox, miniaturaDiv);
    }
  },
  
  // 🖼️ EXIBIR MINIATURA
  exibirMiniatura(file, uploadBox, miniaturaDiv) {
    const reader = new FileReader();
    reader.onload = function (e) {
        miniaturaDiv.innerHTML = `<img src="${e.target.result}" alt="Miniatura">`;
        uploadBox.classList.add("has-image");
    };
    reader.readAsDataURL(file);
  },
  
  // 🚨 UTILITÁRIOS DE UI
  mostrarPopup(mensagem, callback = null) {
    const popup = document.getElementById('popupMensagem');
    const popupTexto = document.getElementById('popupTexto');
    
    if (!popup || !popupTexto) {
      alert(mensagem);
      return;
    }
    
    popupTexto.innerHTML = mensagem.replace(/\n/g, "<br>");
    popup.style.display = 'flex';
    
    // Armazena callback globalmente
    window.motorGoPopupCallback = callback;
  },
  
  fecharPopup() {
    const popup = document.getElementById('popupMensagem');
    if (popup) popup.style.display = 'none';
    
    if (typeof window.motorGoPopupCallback === 'function') {
      window.motorGoPopupCallback();
      window.motorGoPopupCallback = null;
    }
  },
  
  mostrarLoader() {
    const loader = document.getElementById('loader');
    if (loader) loader.classList.remove('hidden');
  },
  
  esconderLoader() {
    const loader = document.getElementById('loader');
    if (loader) loader.classList.add('hidden');
  }
};

// 🔁 EXPOR FUNÇÕES GLOBALMENTE PARA COMPATIBILIDADE
window.mostrarPopup = (mensagem, callback) => MotorGo.mostrarPopup(mensagem, callback);
window.fecharPopup = () => MotorGo.fecharPopup();
window.carregarMarcas = () => MotorGo.carregarMarcas();
window.carregarModelos = () => MotorGo.carregarModelos();
window.carregarAno = () => MotorGo.carregarAno();
window.carregarPreco = () => MotorGo.carregarPreco();
window.mostrarMiniatura = (event, fotoId) => MotorGo.mostrarMiniatura(event, fotoId);

// 🚀 INICIALIZAÇÃO AUTOMÁTICA
document.addEventListener('DOMContentLoaded', () => {
  MotorGo.init();
});

// 🔧 EXPOR MOTORGO GLOBALMENTE PARA DEBUG
window.MotorGo = MotorGo;