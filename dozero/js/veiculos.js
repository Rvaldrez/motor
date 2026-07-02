/**
 * MotorGo – veiculos.js
 * Vehicle section: FIPE API integration, vehicle cards, listing,
 * add/edit/delete, proposals and filters.
 *
 * Depends on: main.js (MotorGo namespace must be loaded first).
 */

'use strict';

// Convenience aliases to main.js utilities.
const {
  showToast, openModal, closeModal, fetchJson, postForm,
  confirmDelete, debounce, formatBRL, formatDate,
  moneyToFloat, cleanMask, maskMoney,
} = window.MotorGo;

// ============================================================
// ===== CONSTANTS ============================================
// ============================================================

// Allow overriding via <meta name="fipe-api-base" content="https://…"> or
// a global MotorGoConfig object set before this script is loaded.
const FIPE_BASE = (
  window.MotorGoConfig?.fipeApiBase ??
  document.querySelector('meta[name="fipe-api-base"]')?.content ??
  'https://parallelum.com.br/fipe/api/v1'
);

const ACTIONS_DIR = (
  window.MotorGoConfig?.actionsDir ??
  document.querySelector('meta[name="actions-dir"]')?.content ??
  'actions/'
);

// ============================================================
// ===== FIPE TABLE API =======================================
// ============================================================

/**
 * Populate a <select> with an array of items.
 * @param {HTMLSelectElement} selectEl
 * @param {Array<{codigo:string, nome:string}>} items
 * @param {string} placeholder
 */
function _fillSelect(selectEl, items, placeholder) {
  selectEl.innerHTML = `<option value="">${placeholder}</option>`;
  items.forEach(item => {
    const opt = document.createElement('option');
    opt.value       = item.codigo;
    opt.textContent = item.nome;
    selectEl.appendChild(opt);
  });
  selectEl.disabled = false;
}

/** Show a loading state on a select. */
function _selectLoading(selectEl, msg = 'Carregando…') {
  selectEl.innerHTML = `<option value="">${msg}</option>`;
  selectEl.disabled  = true;
}

/**
 * Load vehicle brands from FIPE into select#marca (or a provided element).
 * @param {'carros'|'motos'|'caminhoes'} vehicleType
 * @param {HTMLSelectElement} [selectEl]
 */
async function loadBrands(vehicleType = 'carros', selectEl) {
  const el = selectEl ?? document.getElementById('marca');
  if (!el) return;

  _selectLoading(el, 'Carregando marcas…');

  // Cascade: disable downstream selects.
  ['modelo', 'ano_fab'].forEach(id => {
    const s = document.getElementById(id);
    if (s) { s.innerHTML = '<option value="">— selecione —</option>'; s.disabled = true; }
  });

  const res = await fetch(`${FIPE_BASE}/${vehicleType}/marcas`);
  if (!res.ok) {
    showToast('Não foi possível carregar as marcas FIPE.', 'error');
    _fillSelect(el, [], '— erro —');
    return;
  }

  const brands = await res.json();
  _fillSelect(el, brands, '— Selecione a marca —');
}

/**
 * Load models for a given brand code into select#modelo.
 * @param {string} brandCode
 * @param {'carros'|'motos'|'caminhoes'} vehicleType
 * @param {HTMLSelectElement} [selectEl]
 */
async function loadModels(brandCode, vehicleType = 'carros', selectEl) {
  const el = selectEl ?? document.getElementById('modelo');
  if (!el || !brandCode) return;

  _selectLoading(el, 'Carregando modelos…');

  const anoEl = document.getElementById('ano_fab');
  if (anoEl) { anoEl.innerHTML = '<option value="">— selecione —</option>'; anoEl.disabled = true; }

  const res = await fetch(`${FIPE_BASE}/${vehicleType}/marcas/${brandCode}/modelos`);
  if (!res.ok) {
    showToast('Não foi possível carregar os modelos.', 'error');
    _fillSelect(el, [], '— erro —');
    return;
  }

  const { modelos } = await res.json();
  _fillSelect(el, modelos, '— Selecione o modelo —');
}

/**
 * Load available years for a brand+model combination into select#ano_fab.
 * @param {string} brandCode
 * @param {string} modelCode
 * @param {'carros'|'motos'|'caminhoes'} vehicleType
 * @param {HTMLSelectElement} [selectEl]
 */
async function loadYears(brandCode, modelCode, vehicleType = 'carros', selectEl) {
  const el = selectEl ?? document.getElementById('ano_fab');
  if (!el || !brandCode || !modelCode) return;

  _selectLoading(el, 'Carregando anos…');

  const res = await fetch(`${FIPE_BASE}/${vehicleType}/marcas/${brandCode}/modelos/${modelCode}/anos`);
  if (!res.ok) {
    showToast('Não foi possível carregar os anos.', 'error');
    _fillSelect(el, [], '— erro —');
    return;
  }

  const years = await res.json();
  _fillSelect(el, years, '— Selecione o ano —');
}

/**
 * Fetch FIPE price data and return the payload.
 * @param {string} brandCode
 * @param {string} modelCode
 * @param {string} yearCode   - e.g. "2020-1"
 * @param {'carros'|'motos'|'caminhoes'} vehicleType
 * @returns {Promise<object|null>}
 */
async function loadFipeData(brandCode, modelCode, yearCode, vehicleType = 'carros') {
  if (!brandCode || !modelCode || !yearCode) return null;

  const url = `${FIPE_BASE}/${vehicleType}/marcas/${brandCode}/modelos/${modelCode}/anos/${yearCode}`;
  const res = await fetch(url);
  if (!res.ok) return null;

  const data = await res.json();
  return data;
}

/**
 * Wire up cascading FIPE selects inside a container.
 * Expects: select#tipo_veiculo (optional), select#marca, select#modelo, select#ano_fab.
 * @param {HTMLElement} [container=document]
 */
function initFipeCascade(container = document) {
  const tipoEl   = container.querySelector('#tipo_veiculo');
  const marcaEl  = container.querySelector('#marca');
  const modeloEl = container.querySelector('#modelo');
  const anoEl    = container.querySelector('#ano_fab');
  const precoEl  = container.querySelector('#preco_fipe');

  const tipo = () => tipoEl?.value || 'carros';

  if (marcaEl) {
    loadBrands(tipo(), marcaEl);

    marcaEl.addEventListener('change', () => loadModels(marcaEl.value, tipo(), modeloEl));

    if (modeloEl) {
      modeloEl.addEventListener('change', () => loadYears(marcaEl.value, modeloEl.value, tipo(), anoEl));
    }

    if (anoEl && precoEl) {
      anoEl.addEventListener('change', async () => {
        const data = await loadFipeData(marcaEl.value, modeloEl?.value, anoEl.value, tipo());
        if (data?.Valor) {
          precoEl.value = data.Valor;
          showToast(`Tabela FIPE: ${data.Valor}`, 'info');
        }
      });
    }
  }

  if (tipoEl) {
    tipoEl.addEventListener('change', () => loadBrands(tipo(), marcaEl));
  }
}

// ============================================================
// ===== VEHICLE CARD RENDERING ===============================
// ============================================================

const STATUS_LABELS = {
  ativo:        { label: 'Ativo',         class: 'badge--success'  },
  pausado:      { label: 'Pausado',       class: 'badge--warning'  },
  vendido:      { label: 'Vendido',       class: 'badge--neutral'  },
  em_analise:   { label: 'Em análise',    class: 'badge--info'     },
  inativo:      { label: 'Inativo',       class: 'badge--danger'   },
};

/**
 * Build the HTML string for a single vehicle card.
 * @param {{
 *   id: number,
 *   marca: string,
 *   modelo: string,
 *   ano_fabrica: number|string,
 *   preco: number|string,
 *   quilometragem: number|string,
 *   foto_principal: string|null,
 *   status: string,
 *   em_negociacao: boolean|number
 * }} vehicle
 * @returns {string} HTML string.
 */
function renderVehicleCard(vehicle) {
  const statusInfo = STATUS_LABELS[vehicle.status] ?? { label: vehicle.status, class: 'badge--neutral' };
  const foto       = vehicle.foto_principal
    ? `/uploads/${vehicle.foto_principal}`
    : '/imagens/car-placeholder.png';

  const preco      = typeof vehicle.preco === 'string'
    ? parseFloat(vehicle.preco)
    : (vehicle.preco ?? 0);

  const km         = Number(vehicle.quilometragem ?? 0).toLocaleString('pt-BR');
  const emNeg      = vehicle.em_negociacao == 1 || vehicle.em_negociacao === true;

  return `
    <article class="vehicle-card" data-vehicle-id="${vehicle.id}">
      <div class="vehicle-card__image-wrap">
        <img
          src="${foto}"
          alt="${vehicle.marca} ${vehicle.modelo}"
          class="vehicle-card__image"
          loading="lazy"
          onerror="this.src='/imagens/car-placeholder.png'"
        >
        <span class="badge ${statusInfo.class} vehicle-card__status-badge">
          ${statusInfo.label}
        </span>
        ${emNeg ? '<span class="badge badge--accent vehicle-card__neg-badge">Em negociação</span>' : ''}
      </div>

      <div class="vehicle-card__body">
        <h3 class="vehicle-card__title">
          ${vehicle.marca} ${vehicle.modelo}
        </h3>
        <p class="vehicle-card__year">Ano: ${vehicle.ano_fabrica}</p>

        <div class="vehicle-card__meta">
          <span class="vehicle-card__km">
            <i class="icon icon-speedometer" aria-hidden="true"></i>
            ${km} km
          </span>
          <span class="vehicle-card__price">
            R$ ${formatBRL(preco)}
          </span>
        </div>
      </div>

      <div class="vehicle-card__actions">
        <button
          type="button"
          class="btn btn--sm btn--ghost"
          onclick="MotorGoVeiculos.editVehicle(${vehicle.id})"
          aria-label="Editar ${vehicle.marca} ${vehicle.modelo}"
        >
          Editar
        </button>
        <button
          type="button"
          class="btn btn--sm btn--primary"
          onclick="MotorGoVeiculos.sendProposal(${vehicle.id})"
          aria-label="Enviar proposta para ${vehicle.marca} ${vehicle.modelo}"
        >
          Proposta
        </button>
        <button
          type="button"
          class="btn btn--sm btn--danger-ghost"
          onclick="MotorGoVeiculos.deleteVehicle(${vehicle.id})"
          aria-label="Excluir ${vehicle.marca} ${vehicle.modelo}"
        >
          Excluir
        </button>
      </div>
    </article>
  `;
}

// ============================================================
// ===== LOADING SKELETON =====================================
// ============================================================

/** Returns HTML for N skeleton cards while data loads. */
function _skeletonCards(n = 6) {
  return Array.from({ length: n }, () => `
    <div class="vehicle-card vehicle-card--skeleton" aria-hidden="true">
      <div class="skeleton skeleton--image"></div>
      <div class="vehicle-card__body">
        <div class="skeleton skeleton--line skeleton--title"></div>
        <div class="skeleton skeleton--line skeleton--short"></div>
        <div class="skeleton skeleton--line skeleton--meta"></div>
      </div>
    </div>
  `).join('');
}

// ============================================================
// ===== VEHICLE LIST =========================================
// ============================================================

let _currentPage     = 1;
let _currentFilters  = {};
let _totalPages      = 1;

/**
 * Load vehicles from the server and render them.
 * @param {object}  [filters={}]  - Key/value filter parameters.
 * @param {number}  [page=1]      - Page number.
 */
async function loadVehicles(filters = {}, page = 1) {
  const container = document.getElementById('vehicles-container');
  if (!container) return;

  _currentPage    = page;
  _currentFilters = filters;

  // Show skeleton while loading.
  container.innerHTML = _skeletonCards(6);

  const params = new URLSearchParams({ page, ...filters });
  const result = await fetchJson(`${ACTIONS_DIR}listar_veiculos.php?${params}`);

  if (!result.success) {
    container.innerHTML = `
      <div class="empty-state">
        <p>Erro ao carregar veículos. Tente novamente.</p>
        <button type="button" class="btn btn--primary" onclick="MotorGoVeiculos.loadVehicles()">
          Recarregar
        </button>
      </div>
    `;
    return;
  }

  const { veiculos = [], total = 0, por_pagina = 12 } = result.data ?? {};
  _totalPages = Math.ceil(total / por_pagina) || 1;

  if (!veiculos.length) {
    container.innerHTML = `
      <div class="empty-state">
        <img src="/imagens/empty-vehicles.svg" alt="" aria-hidden="true" class="empty-state__img">
        <h3>Nenhum veículo encontrado</h3>
        <p>Tente ajustar os filtros ou cadastre um novo veículo.</p>
      </div>
    `;
    _renderPagination(0, 0);
    return;
  }

  container.innerHTML = veiculos.map(renderVehicleCard).join('');
  _renderPagination(page, _totalPages);
}

/** Render pagination controls below the vehicle list. */
function _renderPagination(current, total) {
  const el = document.getElementById('vehicles-pagination');
  if (!el) return;

  if (total <= 1) { el.innerHTML = ''; return; }

  let html = '<nav class="pagination" aria-label="Paginação de veículos"><ul class="pagination__list">';

  html += `<li class="pagination__item">
    <button class="pagination__btn" ${current === 1 ? 'disabled' : ''}
      onclick="MotorGoVeiculos.loadVehicles(MotorGoVeiculos.currentFilters(), ${current - 1})">
      ‹ Anterior
    </button>
  </li>`;

  for (let i = 1; i <= total; i++) {
    html += `<li class="pagination__item">
      <button class="pagination__btn ${i === current ? 'pagination__btn--active' : ''}"
        onclick="MotorGoVeiculos.loadVehicles(MotorGoVeiculos.currentFilters(), ${i})"
        aria-current="${i === current ? 'page' : ''}">
        ${i}
      </button>
    </li>`;
  }

  html += `<li class="pagination__item">
    <button class="pagination__btn" ${current === total ? 'disabled' : ''}
      onclick="MotorGoVeiculos.loadVehicles(MotorGoVeiculos.currentFilters(), ${current + 1})">
      Próxima ›
    </button>
  </li>`;

  html += '</ul></nav>';
  el.innerHTML = html;
}

/** Return current active filters (used by pagination buttons). */
function currentFilters() {
  return { ..._currentFilters };
}

// ============================================================
// ===== ADD VEHICLE FORM =====================================
// ============================================================

/**
 * Initialise all interactions on the add-vehicle form:
 * - drag-and-drop photo zone
 * - photo preview grid (max 10)
 * - photo removal
 * - FIPE cascade selects
 * - form submission with progress indicator
 */
function initAddVehicleForm() {
  const form = document.getElementById('form-add-veiculo');
  if (!form) return;

  const dropzone   = form.querySelector('.photo-dropzone');
  const fileInput  = form.querySelector('input[type="file"][name="fotos[]"]');
  const previewGrid = form.querySelector('#photo-preview-grid');
  const MAX_PHOTOS  = 10;

  // ── Drag-and-drop ──────────────────────────────────────────
  if (dropzone && fileInput) {
    dropzone.addEventListener('click', () => fileInput.click());

    dropzone.addEventListener('dragover', e => {
      e.preventDefault();
      dropzone.classList.add('dropzone--over');
    });

    ['dragleave', 'dragend'].forEach(evt =>
      dropzone.addEventListener(evt, () => dropzone.classList.remove('dropzone--over'))
    );

    dropzone.addEventListener('drop', e => {
      e.preventDefault();
      dropzone.classList.remove('dropzone--over');
      _mergeFilesIntoInput(fileInput, Array.from(e.dataTransfer.files), MAX_PHOTOS);
      _renderPhotoPreviewGrid(fileInput, previewGrid, MAX_PHOTOS);
    });

    fileInput.addEventListener('change', () => {
      _renderPhotoPreviewGrid(fileInput, previewGrid, MAX_PHOTOS);
    });
  }

  // ── FIPE cascade ──────────────────────────────────────────
  initFipeCascade(form);

  // ── Form submission ────────────────────────────────────────
  form.addEventListener('submit', async e => {
    e.preventDefault();

    if (!window.MotorGo.validateForm(form)) {
      showToast('Preencha todos os campos obrigatórios.', 'warning');
      return;
    }

    const submitBtn = form.querySelector('[type="submit"]');
    const formData  = new FormData(form);

    const result = await postForm(`${ACTIONS_DIR}salvar_veiculo.php`, formData, submitBtn);

    if (result.success) {
      showToast('Veículo cadastrado com sucesso!', 'success');
      form.reset();
      if (previewGrid) previewGrid.innerHTML = '';
      loadVehicles();
      window.MotorGo.showSection('meus-veiculos');
    } else {
      showToast(result.message || 'Erro ao cadastrar veículo.', 'error');
    }
  });
}

/**
 * Add dropped/selected files into an existing file input, respecting max count.
 * @param {HTMLInputElement} inputEl
 * @param {File[]}           newFiles
 * @param {number}           max
 */
function _mergeFilesIntoInput(inputEl, newFiles, max) {
  const dt    = new DataTransfer();
  const exist = Array.from(inputEl.files ?? []);
  const merged = [...exist, ...newFiles].slice(0, max);

  if (exist.length + newFiles.length > max) {
    showToast(`Máximo de ${max} fotos. Algumas foram ignoradas.`, 'warning');
  }

  merged.forEach(f => dt.items.add(f));
  inputEl.files = dt.files;
}

/**
 * Render the photo preview grid from the current files in a file input.
 * @param {HTMLInputElement} inputEl
 * @param {HTMLElement}      gridEl
 * @param {number}           max
 */
function _renderPhotoPreviewGrid(inputEl, gridEl, max) {
  if (!gridEl) return;
  gridEl.innerHTML = '';

  const files = Array.from(inputEl.files ?? []).slice(0, max);

  files.forEach((file, idx) => {
    if (!file.type.startsWith('image/')) return;

    const reader = new FileReader();
    reader.onload = e => {
      const thumb = document.createElement('div');
      thumb.className      = 'photo-thumb';
      thumb.draggable      = true;
      thumb.dataset.index  = idx;
      thumb.innerHTML = `
        <img src="${e.target.result}" alt="Foto ${idx + 1}" loading="lazy">
        ${idx === 0 ? '<span class="photo-thumb__badge">Principal</span>' : ''}
        <button type="button" class="photo-thumb__remove" aria-label="Remover foto ${idx + 1}">×</button>
      `;
      thumb.querySelector('.photo-thumb__remove').addEventListener('click', () => {
        _removeFileAtIndex(inputEl, idx);
        _renderPhotoPreviewGrid(inputEl, gridEl, max);
      });
      gridEl.appendChild(thumb);
    };
    reader.readAsDataURL(file);
  });
}

/** Remove file at index from a file input. */
function _removeFileAtIndex(inputEl, index) {
  const dt = new DataTransfer();
  Array.from(inputEl.files).forEach((f, i) => { if (i !== index) dt.items.add(f); });
  inputEl.files = dt.files;
}

// ============================================================
// ===== EDIT VEHICLE =========================================
// ============================================================

/**
 * Load vehicle data via AJAX and open the edit modal.
 * @param {number|string} vehicleId
 */
async function editVehicle(vehicleId) {
  const result = await fetchJson(`${ACTIONS_DIR}carregar_veiculo.php?id=${vehicleId}`);

  if (!result.success) {
    showToast(result.message || 'Não foi possível carregar o veículo.', 'error');
    return;
  }

  const v    = result.data;
  const form = document.getElementById('form-edit-veiculo');

  if (!form) return;

  // Populate fields.
  const set = (name, val) => {
    const el = form.querySelector(`[name="${name}"]`);
    if (el) el.value = val ?? '';
  };

  set('id',             v.id);
  set('marca',          v.marca);
  set('modelo',         v.modelo);
  set('ano_fabrica',    v.ano_fabrica);
  set('ano_modelo',     v.ano_modelo);
  set('preco',          v.preco);
  set('quilometragem',  v.quilometragem);
  set('cor',            v.cor);
  set('combustivel',    v.combustivel);
  set('cambio',         v.cambio);
  set('descricao',      v.descricao);
  set('status',         v.status);

  // Show existing photos.
  const photoGrid = form.querySelector('#edit-photo-grid');
  if (photoGrid && Array.isArray(v.fotos)) {
    photoGrid.innerHTML = v.fotos.map((foto, i) => `
      <div class="photo-thumb" data-foto-id="${foto.id}">
        <img src="/uploads/${foto.nome}" alt="Foto ${i + 1}" loading="lazy">
        ${i === 0 ? '<span class="photo-thumb__badge">Principal</span>' : ''}
        <button type="button" class="photo-thumb__remove"
          onclick="MotorGoVeiculos.removeFoto(${foto.id}, this)"
          aria-label="Remover foto">×</button>
      </div>
    `).join('');
  }

  openModal('modal-edit-veiculo');
}

/**
 * Remove an existing vehicle photo via AJAX.
 * @param {number} fotoId
 * @param {HTMLButtonElement} btn
 */
async function removeFoto(fotoId, btn) {
  const result = await fetchJson(`${ACTIONS_DIR}remover_foto.php?id=${fotoId}`, { method: 'POST' });
  if (result.success) {
    btn.closest('.photo-thumb')?.remove();
    showToast('Foto removida.', 'success');
  } else {
    showToast(result.message || 'Erro ao remover foto.', 'error');
  }
}

/** Set up the edit form submit handler. */
function initEditVehicleForm() {
  const form = document.getElementById('form-edit-veiculo');
  if (!form) return;

  form.addEventListener('submit', async e => {
    e.preventDefault();

    if (!window.MotorGo.validateForm(form)) {
      showToast('Preencha todos os campos obrigatórios.', 'warning');
      return;
    }

    const submitBtn = form.querySelector('[type="submit"]');
    const formData  = new FormData(form);
    const result    = await postForm(`${ACTIONS_DIR}salvar_edicao_veiculo.php`, formData, submitBtn);

    if (result.success) {
      showToast('Veículo atualizado com sucesso!', 'success');
      closeModal('modal-edit-veiculo');
      loadVehicles(_currentFilters, _currentPage);
    } else {
      showToast(result.message || 'Erro ao atualizar veículo.', 'error');
    }
  });
}

// ============================================================
// ===== DELETE VEHICLE =======================================
// ============================================================

/**
 * Ask for confirmation then delete a vehicle via AJAX.
 * Removes the card from the DOM on success.
 * @param {number|string} vehicleId
 */
function deleteVehicle(vehicleId) {
  confirmDelete(
    'Tem certeza que deseja excluir este veículo? Esta ação não pode ser desfeita.',
    async confirmed => {
      if (!confirmed) return;

      const result = await fetchJson(
        `${ACTIONS_DIR}remover_veiculo.php`,
        { method: 'POST', body: new URLSearchParams({ id: vehicleId }) }
      );

      if (result.success) {
        const card = document.querySelector(`.vehicle-card[data-vehicle-id="${vehicleId}"]`);
        card?.remove();
        showToast('Veículo excluído com sucesso.', 'success');

        // Reload if page is now empty.
        const container = document.getElementById('vehicles-container');
        if (container && !container.querySelector('.vehicle-card')) {
          loadVehicles(_currentFilters, Math.max(1, _currentPage - 1));
        }
      } else {
        showToast(result.message || 'Erro ao excluir veículo.', 'error');
      }
    }
  );
}

// ============================================================
// ===== PROPOSALS ============================================
// ============================================================

/**
 * Open the proposal modal pre-filled with the vehicle id.
 * @param {number|string} vehicleId
 */
function sendProposal(vehicleId) {
  const modal = document.getElementById('modal-enviar-proposta');
  if (!modal) return;

  const hiddenId = modal.querySelector('[name="veiculo_id"]');
  if (hiddenId) hiddenId.value = vehicleId;

  // Reset previous values.
  modal.querySelectorAll('input:not([type=hidden]), textarea').forEach(el => { el.value = ''; });

  openModal('modal-enviar-proposta');
}

/**
 * Submit a proposal via AJAX.
 * @param {number|string} vehicleId
 * @param {number|string} value   - Proposed value in BRL.
 * @param {string}        message - Optional message to seller.
 * @returns {Promise<boolean>}
 */
async function submitProposal(vehicleId, value, message) {
  const body = new FormData();
  body.append('veiculo_id', vehicleId);
  body.append('valor',      value);
  body.append('mensagem',   message);

  const result = await fetchJson(`${ACTIONS_DIR}enviar_proposta.php`, { method: 'POST', body });

  if (result.success) {
    showToast('Proposta enviada com sucesso!', 'success');
    closeModal('modal-enviar-proposta');
    return true;
  }

  showToast(result.message || 'Erro ao enviar proposta.', 'error');
  return false;
}

/**
 * Accept, reject or counter a proposal.
 * @param {number|string} proposalId
 * @param {'aceitar'|'recusar'|'contraproposta'} action
 * @param {object} [extra={}] - Extra fields (e.g. { valor_contra: 50000 }).
 */
async function respondToProposal(proposalId, action, extra = {}) {
  const body = new FormData();
  body.append('proposta_id', proposalId);
  body.append('acao',        action);
  Object.entries(extra).forEach(([k, v]) => body.append(k, v));

  const endpoint = action === 'contraproposta'
    ? 'processar_contraproposta.php'
    : 'negociar_proposta.php';

  const result = await fetchJson(`${ACTIONS_DIR}${endpoint}`, { method: 'POST', body });

  const labels = { aceitar: 'aceita', recusar: 'recusada', contraproposta: 'enviada' };

  if (result.success) {
    showToast(`Proposta ${labels[action] ?? 'processada'} com sucesso.`, 'success');
    // Refresh proposal list if visible.
    const listEl = document.getElementById('proposals-list');
    if (listEl) loadProposals(listEl.dataset.tipo ?? 'recebidas');
  } else {
    showToast(result.message || 'Erro ao processar proposta.', 'error');
  }
}

/**
 * Load and render proposals into #proposals-list.
 * @param {'recebidas'|'enviadas'} tipo
 */
async function loadProposals(tipo = 'recebidas') {
  const listEl = document.getElementById('proposals-list');
  if (!listEl) return;

  listEl.innerHTML = '<div class="loading-spinner" aria-label="Carregando propostas…"></div>';

  const endpoint = tipo === 'enviadas'
    ? 'listar_propostas_enviadas.php'
    : 'listar_propostas_recebidas.php';

  const result = await fetchJson(`${ACTIONS_DIR}${endpoint}`);

  if (!result.success) {
    listEl.innerHTML = '<p class="text-muted">Erro ao carregar propostas.</p>';
    return;
  }

  const propostas = result.data?.propostas ?? result.data ?? [];

  if (!propostas.length) {
    listEl.innerHTML = '<p class="text-muted">Nenhuma proposta encontrada.</p>';
    return;
  }

  listEl.innerHTML = propostas.map(p => renderProposalItem(p, tipo)).join('');
}

/**
 * Build HTML for a single proposal row.
 * @param {{
 *   id: number,
 *   veiculo: string,
 *   valor: number,
 *   status: string,
 *   criado_em: string,
 *   mensagem?: string,
 *   comprador?: string,
 *   vendedor?: string,
 * }} proposal
 * @param {'recebidas'|'enviadas'} tipo
 * @returns {string}
 */
function renderProposalItem(proposal, tipo = 'recebidas') {
  const statusClasses = {
    pendente:      'badge--warning',
    aceita:        'badge--success',
    recusada:      'badge--danger',
    contraproposta:'badge--info',
    cancelada:     'badge--neutral',
  };
  const badgeCls = statusClasses[proposal.status] ?? 'badge--neutral';

  const parte = tipo === 'recebidas'
    ? `<span class="proposal-part">De: <strong>${proposal.comprador ?? '—'}</strong></span>`
    : `<span class="proposal-part">Para: <strong>${proposal.vendedor ?? '—'}</strong></span>`;

  const actions = (tipo === 'recebidas' && proposal.status === 'pendente') ? `
    <div class="proposal-actions">
      <button type="button" class="btn btn--sm btn--success"
        onclick="MotorGoVeiculos.respondToProposal(${proposal.id}, 'aceitar')">
        Aceitar
      </button>
      <button type="button" class="btn btn--sm btn--ghost"
        onclick="MotorGoVeiculos.openCounterModal(${proposal.id})">
        Contraproposta
      </button>
      <button type="button" class="btn btn--sm btn--danger-ghost"
        onclick="MotorGoVeiculos.respondToProposal(${proposal.id}, 'recusar')">
        Recusar
      </button>
    </div>
  ` : '';

  return `
    <div class="proposal-item" data-proposal-id="${proposal.id}">
      <div class="proposal-item__info">
        <strong class="proposal-item__vehicle">${proposal.veiculo}</strong>
        ${parte}
        <span class="proposal-item__date">${formatDate(proposal.criado_em)}</span>
        ${proposal.mensagem ? `<p class="proposal-item__msg">"${proposal.mensagem}"</p>` : ''}
      </div>
      <div class="proposal-item__right">
        <span class="proposal-item__value">R$ ${formatBRL(Number(proposal.valor))}</span>
        <span class="badge ${badgeCls}">${proposal.status}</span>
        ${actions}
      </div>
    </div>
  `;
}

/**
 * Open the counter-proposal modal for a given proposal.
 * @param {number} proposalId
 */
function openCounterModal(proposalId) {
  const modal = document.getElementById('modal-contraproposta');
  if (!modal) return;

  const idInput = modal.querySelector('[name="proposta_id"]');
  if (idInput) idInput.value = proposalId;

  openModal('modal-contraproposta');
}

/** Wire up the proposal send form inside modal-enviar-proposta. */
function initProposalForms() {
  const sendForm = document.getElementById('form-enviar-proposta');
  if (sendForm) {
    sendForm.addEventListener('submit', async e => {
      e.preventDefault();
      const fd         = new FormData(sendForm);
      const vehicleId  = fd.get('veiculo_id');
      const valor      = moneyToFloat(fd.get('valor') ?? '');
      const mensagem   = fd.get('mensagem') ?? '';

      if (!valor || valor <= 0) {
        window.MotorGo.showFieldError(sendForm.querySelector('[name="valor"]'), 'Informe um valor válido.');
        return;
      }

      const ok = await submitProposal(vehicleId, valor, mensagem);
      if (ok) sendForm.reset();
    });
  }

  const counterForm = document.getElementById('form-contraproposta');
  if (counterForm) {
    counterForm.addEventListener('submit', async e => {
      e.preventDefault();
      const fd          = new FormData(counterForm);
      const proposalId  = fd.get('proposta_id');
      const valorContra = moneyToFloat(fd.get('valor_contra') ?? '');

      if (!valorContra || valorContra <= 0) {
        window.MotorGo.showFieldError(counterForm.querySelector('[name="valor_contra"]'), 'Informe um valor válido.');
        return;
      }

      await respondToProposal(proposalId, 'contraproposta', { valor_contra: valorContra });
      closeModal('modal-contraproposta');
      counterForm.reset();
    });
  }
}

// ============================================================
// ===== FILTERS ==============================================
// ============================================================

/** Initialise the vehicle filter form with real-time debounced filtering. */
function initVehicleFilters() {
  const filterForm = document.getElementById('form-filtros-veiculos');
  if (!filterForm) return;

  const applyFilters = debounce(() => {
    const fd      = new FormData(filterForm);
    const filters = {};
    for (const [key, value] of fd.entries()) {
      if (value.trim()) filters[key] = value.trim();
    }
    // Convert money fields.
    if (filters.preco_min) filters.preco_min = moneyToFloat(filters.preco_min);
    if (filters.preco_max) filters.preco_max = moneyToFloat(filters.preco_max);

    loadVehicles(filters, 1);
  }, 400);

  // Real-time on text/select inputs.
  filterForm.querySelectorAll('input, select').forEach(el => {
    el.addEventListener('input',  applyFilters);
    el.addEventListener('change', applyFilters);
  });

  // Apply money masks on range price inputs.
  filterForm.querySelectorAll('[data-mask="money"]').forEach(el => maskMoney(el));

  // Clear button.
  const clearBtn = filterForm.querySelector('[data-clear-filters]');
  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      filterForm.reset();
      loadVehicles({}, 1);
    });
  }

  // Submit still applies filters.
  filterForm.addEventListener('submit', e => {
    e.preventDefault();
    applyFilters();
  });
}

// ============================================================
// ===== DOMContentLoaded – INITIALISATION ====================
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
  // Only initialise vehicle UI if the relevant container is present.
  const hasVehicleSection = !!(
    document.getElementById('vehicles-container') ||
    document.getElementById('form-add-veiculo')   ||
    document.getElementById('form-edit-veiculo')
  );

  if (!hasVehicleSection) return;

  // Vehicle list + filters.
  if (document.getElementById('vehicles-container')) {
    initVehicleFilters();
    loadVehicles();
  }

  // Add vehicle form.
  initAddVehicleForm();

  // Edit vehicle form.
  initEditVehicleForm();

  // Proposal forms.
  initProposalForms();

  // Load proposals tab if present.
  const propList = document.getElementById('proposals-list');
  if (propList) loadProposals(propList.dataset.tipo ?? 'recebidas');

  // Proposal tab switching.
  document.querySelectorAll('[data-proposal-tab]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-proposal-tab]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const tipo = btn.dataset.proposalTab;
      if (propList) propList.dataset.tipo = tipo;
      loadProposals(tipo);
    });
  });
});

// ============================================================
// ===== PUBLIC API ===========================================
// ============================================================

window.MotorGoVeiculos = {
  loadBrands,
  loadModels,
  loadYears,
  loadFipeData,
  initFipeCascade,
  renderVehicleCard,
  loadVehicles,
  currentFilters,
  initAddVehicleForm,
  editVehicle,
  removeFoto,
  deleteVehicle,
  sendProposal,
  submitProposal,
  respondToProposal,
  renderProposalItem,
  loadProposals,
  openCounterModal,
  initVehicleFilters,
};
