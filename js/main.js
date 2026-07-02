/**
 * MotorGo – main.js
 * Core UI utilities: toasts, modals, sidebar, navigation, validation,
 * masks, CEP lookup, AJAX helpers, image preview, confirm dialogs,
 * password strength and multi-step forms.
 */

'use strict';

// ============================================================
// ===== TOAST NOTIFICATIONS ==================================
// ============================================================

/**
 * Display a toast notification.
 * @param {string} message  - Text to display.
 * @param {'success'|'error'|'warning'|'info'} type - Visual style.
 * @param {number} duration - Auto-dismiss time in ms (default 3500).
 */
function showToast(message, type = 'success', duration = 3500) {
  let container = document.querySelector('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const icons = {
    success: '✓',
    error:   '✕',
    warning: '⚠',
    info:    'ℹ',
  };

  const toast = document.createElement('div');
  toast.className = `toast toast--${type}`;
  toast.setAttribute('role', 'alert');
  toast.setAttribute('aria-live', 'assertive');
  toast.innerHTML = `
    <span class="toast__icon">${icons[type] ?? icons.info}</span>
    <span class="toast__message">${message}</span>
    <button class="toast__close" aria-label="Fechar notificação">×</button>
  `;

  container.appendChild(toast);

  // Trigger entrance animation on next frame.
  requestAnimationFrame(() => toast.classList.add('toast--visible'));

  const dismiss = () => {
    toast.classList.remove('toast--visible');
    toast.classList.add('toast--hiding');
    toast.addEventListener('transitionend', () => toast.remove(), { once: true });
  };

  toast.querySelector('.toast__close').addEventListener('click', dismiss);

  if (duration > 0) {
    setTimeout(dismiss, duration);
  }
}

// ============================================================
// ===== MODAL SYSTEM =========================================
// ============================================================

/**
 * Open a modal by its ID.
 * Expects a structure: .modal-overlay#<modalId> > .modal
 * @param {string} modalId - The id attribute of the .modal-overlay element.
 */
function openModal(modalId) {
  const overlay = document.getElementById(modalId);
  if (!overlay) return;

  overlay.classList.add('active');
  document.body.classList.add('modal-open');
  document.body.style.overflow = 'hidden';

  // Focus the first focusable element inside modal for accessibility.
  const focusable = overlay.querySelector(
    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
  );
  if (focusable) setTimeout(() => focusable.focus(), 50);
}

/**
 * Close a modal by its ID.
 * @param {string} modalId
 */
function closeModal(modalId) {
  const overlay = document.getElementById(modalId);
  if (!overlay) return;

  overlay.classList.remove('active');

  // Only remove body lock if no other modals are open.
  if (!document.querySelector('.modal-overlay.active')) {
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
  }
}

/** Close all open modals at once. */
function closeAllModals() {
  document.querySelectorAll('.modal-overlay.active').forEach(overlay => {
    overlay.classList.remove('active');
  });
  document.body.classList.remove('modal-open');
  document.body.style.overflow = '';
}

/** Initialise global modal close handlers (overlay click + Escape key). */
function initModalHandlers() {
  // Close on overlay click (not on the modal panel itself).
  document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
      closeModal(e.target.id);
    }
  });

  // Close on [data-modal-close] button click.
  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-modal-close]');
    if (btn) {
      const modalId = btn.dataset.modalClose || btn.closest('.modal-overlay')?.id;
      if (modalId) closeModal(modalId);
    }
  });

  // Open on [data-modal-open] button click.
  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-modal-open]');
    if (btn) openModal(btn.dataset.modalOpen);
  });

  // Escape key.
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeAllModals();
  });
}

// ============================================================
// ===== SIDEBAR TOGGLE =======================================
// ============================================================

/** Initialise the sidebar toggle for mobile and desktop. */
function initSidebar() {
  const toggleBtn    = document.querySelector('.sidebar-toggle');
  const sidebar      = document.querySelector('.layout-sidebar');
  const layoutMain   = document.querySelector('.layout-main');
  const overlay      = document.querySelector('.sidebar-overlay');

  if (!sidebar) return;

  const MOBILE_BP = 768; // px

  function isMobile() {
    return window.innerWidth <= MOBILE_BP;
  }

  function openSidebar() {
    sidebar.classList.add('sidebar-open');
    if (overlay) overlay.classList.add('active');
    document.body.classList.add('sidebar-is-open');
  }

  function closeSidebar() {
    sidebar.classList.remove('sidebar-open');
    if (overlay) overlay.classList.remove('active');
    document.body.classList.remove('sidebar-is-open');
  }

  function toggleSidebar() {
    if (isMobile()) {
      sidebar.classList.contains('sidebar-open') ? closeSidebar() : openSidebar();
    } else {
      // Desktop: collapse/expand using a class on .layout-main.
      layoutMain?.classList.toggle('sidebar-collapsed');
      const collapsed = layoutMain?.classList.contains('sidebar-collapsed');
      localStorage.setItem('motorgo_sidebar_collapsed', collapsed ? '1' : '0');
    }
  }

  // Restore desktop state from localStorage.
  if (!isMobile()) {
    const saved = localStorage.getItem('motorgo_sidebar_collapsed');
    if (saved === '1') layoutMain?.classList.add('sidebar-collapsed');
  }

  if (toggleBtn) toggleBtn.addEventListener('click', toggleSidebar);
  if (overlay)   overlay.addEventListener('click', closeSidebar);

  // Reset on window resize.
  let resizeTimer;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
      if (!isMobile()) {
        closeSidebar(); // ensure mobile overlay is gone
      }
    }, 150);
  });
}

// ============================================================
// ===== SECTION NAVIGATION ===================================
// ============================================================

/** Hide all sections and show the requested one. Updates hash + active nav link.
 * @param {string} sectionName - Value matching data-section attribute.
 */
function showSection(sectionName) {
  const sections = document.querySelectorAll('.section-content');
  const navLinks = document.querySelectorAll('.sidebar-nav-link[data-section]');

  sections.forEach(sec => {
    const isTarget = sec.dataset.section === sectionName;
    sec.hidden = !isTarget;
    sec.classList.toggle('section--active', isTarget);
    if (isTarget) {
      // Trigger entrance animation.
      sec.classList.remove('section--animated');
      requestAnimationFrame(() =>
        requestAnimationFrame(() => sec.classList.add('section--animated'))
      );
    }
  });

  navLinks.forEach(link => {
    link.classList.toggle('active', link.dataset.section === sectionName);
    link.setAttribute('aria-current', link.dataset.section === sectionName ? 'page' : '');
  });

  // Update URL hash without scrolling.
  history.replaceState(null, '', `#${sectionName}`);

  // Persist last visited section.
  sessionStorage.setItem('motorgo_section', sectionName);
}

/** Read hash or session storage and activate correct section on load. */
function initSectionNavigation() {
  const navLinks = document.querySelectorAll('.sidebar-nav-link[data-section]');

  navLinks.forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      showSection(link.dataset.section);
    });
  });

  // Determine initial section.
  const hash    = window.location.hash.replace('#', '');
  const saved   = sessionStorage.getItem('motorgo_section');
  const first   = navLinks[0]?.dataset.section;
  const target  = hash || saved || first;

  if (target) showSection(target);
}

// ============================================================
// ===== FORM VALIDATION ======================================
// ============================================================

/**
 * Validate all required fields in a form.
 * @param {HTMLFormElement} formElement
 * @returns {boolean} true when all fields are valid.
 */
function validateForm(formElement) {
  let valid = true;

  formElement.querySelectorAll('[required]').forEach(field => {
    clearFieldError(field);

    const value = field.value.trim();

    if (!value) {
      showFieldError(field, 'Este campo é obrigatório.');
      valid = false;
      return;
    }

    if (field.type === 'email' && !validateEmail(value)) {
      showFieldError(field, 'Informe um e-mail válido.');
      valid = false;
      return;
    }

    if (field.dataset.mask === 'cpf' && !validateCpf(value)) {
      showFieldError(field, 'CPF inválido.');
      valid = false;
      return;
    }

    if (field.dataset.minlength && value.length < parseInt(field.dataset.minlength, 10)) {
      showFieldError(field, `Mínimo de ${field.dataset.minlength} caracteres.`);
      valid = false;
    }
  });

  return valid;
}

/**
 * Display an error message below a field.
 * @param {HTMLElement} fieldElement
 * @param {string} message
 */
function showFieldError(fieldElement, message) {
  fieldElement.classList.add('field--error');

  let errEl = fieldElement.parentElement.querySelector('.field-error-msg');
  if (!errEl) {
    errEl = document.createElement('span');
    errEl.className = 'field-error-msg';
    errEl.setAttribute('role', 'alert');
    fieldElement.parentElement.appendChild(errEl);
  }
  errEl.textContent = message;
}

/**
 * Remove the error state from a field.
 * @param {HTMLElement} fieldElement
 */
function clearFieldError(fieldElement) {
  fieldElement.classList.remove('field--error');
  const errEl = fieldElement.parentElement?.querySelector('.field-error-msg');
  if (errEl) errEl.remove();
}

/**
 * Validate an e-mail address with a regex.
 * @param {string} email
 * @returns {boolean}
 */
function validateEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email.trim());
}

/** Attach real-time blur validation to all forms marked with data-validate. */
function initFormValidation() {
  document.querySelectorAll('form[data-validate]').forEach(form => {
    form.querySelectorAll('[required]').forEach(field => {
      field.addEventListener('blur', () => {
        if (field.value.trim()) {
          clearFieldError(field);
          // Re-run type-specific checks on blur.
          if (field.type === 'email' && !validateEmail(field.value)) {
            showFieldError(field, 'Informe um e-mail válido.');
          }
          if (field.dataset.mask === 'cpf' && !validateCpf(field.value)) {
            showFieldError(field, 'CPF inválido.');
          }
        }
      });
    });

    form.addEventListener('submit', e => {
      if (!validateForm(form)) {
        e.preventDefault();
        const firstError = form.querySelector('.field--error');
        if (firstError) firstError.focus();
      }
    });
  });
}

// ============================================================
// ===== MASKS / FORMATTERS ===================================
// ============================================================

/**
 * Remove all non-digit characters from a string.
 * @param {string} value
 * @returns {string}
 */
function cleanMask(value) {
  return value.replace(/\D/g, '');
}

/**
 * Convert a Brazilian formatted monetary string to a float.
 * e.g. "R$ 1.234,56" → 1234.56
 * @param {string} value
 * @returns {number}
 */
function moneyToFloat(value) {
  const cleaned = value
    .replace(/R\$\s?/, '')
    .replace(/\./g, '')
    .replace(',', '.');
  return parseFloat(cleaned) || 0;
}

/**
 * Attach real-time BRL money formatting to an input.
 * @param {HTMLInputElement} inputElement
 */
function maskMoney(inputElement) {
  inputElement.addEventListener('input', () => {
    let digits = cleanMask(inputElement.value);
    if (!digits) {
      inputElement.value = '';
      return;
    }
    // Treat last two digits as cents.
    digits = digits.padStart(3, '0');
    const cents  = digits.slice(-2);
    const reais  = digits.slice(0, -2).replace(/^0+/, '') || '0';
    const formatted = parseInt(reais, 10).toLocaleString('pt-BR');
    inputElement.value = `R$ ${formatted},${cents}`;
  });

  inputElement.addEventListener('keydown', e => {
    if (e.key === 'Backspace') {
      e.preventDefault();
      let digits = cleanMask(inputElement.value);
      digits = digits.slice(0, -1);
      if (!digits) {
        inputElement.value = '';
        return;
      }
      digits = digits.padStart(3, '0');
      const cents  = digits.slice(-2);
      const reais  = digits.slice(0, -2).replace(/^0+/, '') || '0';
      inputElement.value = `R$ ${parseInt(reais, 10).toLocaleString('pt-BR')},${cents}`;
    }
  });
}

/**
 * Attach real-time phone formatting to an input.
 * Supports (11) 99999-9999 and (11) 9999-9999.
 * @param {HTMLInputElement} inputElement
 */
function maskPhone(inputElement) {
  inputElement.addEventListener('input', () => {
    let digits = cleanMask(inputElement.value).slice(0, 11);
    if (digits.length === 0) { inputElement.value = ''; return; }

    if (digits.length <= 2) {
      inputElement.value = `(${digits}`;
    } else if (digits.length <= 6) {
      inputElement.value = `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    } else if (digits.length <= 10) {
      inputElement.value = `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
    } else {
      inputElement.value = `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
    }
  });
}

/**
 * Attach real-time CPF formatting to an input. (123.456.789-00)
 * @param {HTMLInputElement} inputElement
 */
function maskCpf(inputElement) {
  inputElement.addEventListener('input', () => {
    let digits = cleanMask(inputElement.value).slice(0, 11);
    if (digits.length <= 3) {
      inputElement.value = digits;
    } else if (digits.length <= 6) {
      inputElement.value = `${digits.slice(0, 3)}.${digits.slice(3)}`;
    } else if (digits.length <= 9) {
      inputElement.value = `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;
    } else {
      inputElement.value = `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
    }
  });
}

/**
 * Attach real-time CEP formatting to an input. (12345-678)
 * @param {HTMLInputElement} inputElement
 */
function maskCep(inputElement) {
  inputElement.addEventListener('input', () => {
    let digits = cleanMask(inputElement.value).slice(0, 8);
    inputElement.value = digits.length > 5
      ? `${digits.slice(0, 5)}-${digits.slice(5)}`
      : digits;
  });
}

/** Apply masks to every input that has a data-mask attribute. */
function initMasks() {
  document.querySelectorAll('[data-mask]').forEach(input => {
    switch (input.dataset.mask) {
      case 'money': maskMoney(input); break;
      case 'phone': maskPhone(input); break;
      case 'cpf':   maskCpf(input);   break;
      case 'cep':   maskCep(input);   break;
    }
  });
}

// ============================================================
// ===== CPF VALIDATION =======================================
// ============================================================

/**
 * Full CPF validation including check-digit verification.
 * @param {string} cpf
 * @returns {boolean}
 */
function validateCpf(cpf) {
  const digits = cleanMask(cpf);

  if (digits.length !== 11) return false;
  if (/^(\d)\1{10}$/.test(digits)) return false; // All same digits.

  const calcDigit = (str, factor) => {
    let sum = 0;
    for (let i = 0; i < str.length; i++) {
      sum += parseInt(str[i], 10) * (factor - i);
    }
    const remainder = (sum * 10) % 11;
    return remainder === 10 || remainder === 11 ? 0 : remainder;
  };

  const d1 = calcDigit(digits.slice(0, 9),  10);
  const d2 = calcDigit(digits.slice(0, 10), 11);

  return d1 === parseInt(digits[9], 10) && d2 === parseInt(digits[10], 10);
}

// ============================================================
// ===== CEP AUTO-FILL ========================================
// ============================================================

/**
 * Fetch address data from ViaCEP and populate form fields.
 * Expects sibling inputs: [data-cep-field="logradouro|bairro|cidade|estado"].
 * @param {string} cep - Raw or formatted CEP string.
 * @param {HTMLElement} [context=document] - Scope for field lookup.
 * @returns {Promise<object|null>}
 */
async function buscarCep(cep, context = document) {
  const digits = cleanMask(cep);
  if (digits.length !== 8) return null;

  const fill = (field, value) => {
    const el = context.querySelector(`[data-cep-field="${field}"]`);
    if (el) el.value = value;
  };

  try {
    const res  = await fetch(`https://viacep.com.br/ws/${digits}/json/`);
    const data = await res.json();

    if (data.erro) {
      showToast('CEP não encontrado.', 'warning');
      return null;
    }

    fill('logradouro', data.logradouro || '');
    fill('bairro',     data.bairro     || '');
    fill('cidade',     data.localidade || '');
    fill('estado',     data.uf         || '');

    return data;
  } catch {
    showToast('Erro ao buscar CEP. Verifique sua conexão.', 'error');
    return null;
  }
}

/** Attach auto-fill to every input with data-cep attribute. */
function initCepAutoFill() {
  document.querySelectorAll('[data-cep]').forEach(input => {
    const form = input.closest('form') ?? document;

    input.addEventListener('blur', async () => {
      const digits = cleanMask(input.value);
      if (digits.length !== 8) return;

      input.classList.add('loading');
      input.disabled = true;

      await buscarCep(input.value, form);

      input.classList.remove('loading');
      input.disabled = false;
    });
  });
}

// ============================================================
// ===== AJAX HELPERS =========================================
// ============================================================

/**
 * Wrapper around fetch() that always resolves to a normalised object.
 * @param {string} url
 * @param {RequestInit} [options={}]
 * @returns {Promise<{success: boolean, data: any, message: string}>}
 */
async function fetchJson(url, options = {}) {
  try {
    const res = await fetch(url, {
      headers: { 'Accept': 'application/json', ...(options.headers ?? {}) },
      ...options,
    });

    const text = await res.text();
    let parsed;
    try {
      parsed = JSON.parse(text);
    } catch {
      return { success: false, data: null, message: 'Resposta inválida do servidor.' };
    }

    return {
      success: res.ok && (parsed.success ?? true),
      data:    parsed.data    ?? parsed,
      message: parsed.message ?? (res.ok ? 'OK' : 'Erro no servidor.'),
    };
  } catch (err) {
    return { success: false, data: null, message: err.message || 'Erro de conexão.' };
  }
}

/**
 * POST a FormData object and return the JSON response.
 * Automatically disables the submit button and shows a loading state.
 * @param {string} url
 * @param {FormData} formData
 * @param {HTMLButtonElement|null} [submitBtn=null]
 * @returns {Promise<{success: boolean, data: any, message: string}>}
 */
async function postForm(url, formData, submitBtn = null) {
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn._originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="spinner spinner--sm"></span> Aguarde…';
  }

  const result = await fetchJson(url, { method: 'POST', body: formData });

  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.innerHTML = submitBtn._originalText;
  }

  return result;
}

// ============================================================
// ===== IMAGE PREVIEW ========================================
// ============================================================

/**
 * Show a thumbnail preview when a single file is selected.
 * @param {HTMLInputElement} inputElement
 * @param {HTMLImageElement} previewElement
 */
function initImagePreview(inputElement, previewElement) {
  inputElement.addEventListener('change', () => {
    const file = inputElement.files?.[0];
    if (!file || !file.type.startsWith('image/')) return;

    const reader = new FileReader();
    reader.onload = e => {
      previewElement.src = e.target.result;
      previewElement.hidden = false;
    };
    reader.readAsDataURL(file);
  });
}

/**
 * Render thumbnail previews for a multi-file input inside a container.
 * Each preview has a remove button that clears the file from a DataTransfer.
 * @param {HTMLInputElement} inputElement  - Multiple file input.
 * @param {HTMLElement}      containerElement - Container for preview thumbnails.
 * @param {number}           [maxFiles=10]
 */
function previewMultipleImages(inputElement, containerElement, maxFiles = 10) {
  inputElement.addEventListener('change', () => {
    const files = Array.from(inputElement.files ?? []).slice(0, maxFiles);

    if (inputElement.files.length > maxFiles) {
      showToast(`Máximo de ${maxFiles} fotos permitido.`, 'warning');
    }

    // Rebuild DataTransfer to respect the slice above.
    const dt = new DataTransfer();
    files.forEach(f => dt.items.add(f));
    inputElement.files = dt.files;

    containerElement.innerHTML = '';

    files.forEach((file, idx) => {
      if (!file.type.startsWith('image/')) return;

      const reader = new FileReader();
      reader.onload = e => {
        const wrapper = document.createElement('div');
        wrapper.className = 'img-preview-thumb';
        wrapper.dataset.index = idx;
        wrapper.innerHTML = `
          <img src="${e.target.result}" alt="Foto ${idx + 1}" loading="lazy">
          <button type="button" class="img-preview-remove" aria-label="Remover foto ${idx + 1}">×</button>
          ${idx === 0 ? '<span class="img-preview-badge">Principal</span>' : ''}
        `;
        wrapper.querySelector('.img-preview-remove').addEventListener('click', () => {
          removeFileFromInput(inputElement, idx);
          wrapper.remove();
        });
        containerElement.appendChild(wrapper);
      };
      reader.readAsDataURL(file);
    });
  });
}

/**
 * Remove a file at a given index from a file input by rebuilding DataTransfer.
 * @param {HTMLInputElement} inputElement
 * @param {number} index
 */
function removeFileFromInput(inputElement, index) {
  const dt = new DataTransfer();
  Array.from(inputElement.files).forEach((file, i) => {
    if (i !== index) dt.items.add(file);
  });
  inputElement.files = dt.files;
}

// ============================================================
// ===== CONFIRM DELETE =======================================
// ============================================================

/**
 * Show a custom confirmation dialog (not browser default).
 * @param {string}   message  - Body text of the dialog.
 * @param {Function} callback - Called with true (confirmed) or false (cancelled).
 * @param {string}   [confirmLabel='Excluir']
 * @param {string}   [confirmClass='btn--danger']
 */
function confirmDelete(message, callback, confirmLabel = 'Excluir', confirmClass = 'btn--danger') {
  const overlayId = 'motorgo-confirm-modal';
  let overlay = document.getElementById(overlayId);

  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = overlayId;
    overlay.className = 'modal-overlay';
    overlay.innerHTML = `
      <div class="modal modal-sm" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title">
        <div class="modal-header">
          <h3 class="modal-title" id="confirm-title">Confirmar ação</h3>
        </div>
        <div class="modal-body">
          <p id="confirm-message"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn--ghost" id="confirm-cancel">Cancelar</button>
          <button type="button" class="btn" id="confirm-ok"></button>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);
  }

  overlay.querySelector('#confirm-message').textContent = message;
  const okBtn = overlay.querySelector('#confirm-ok');
  okBtn.textContent = confirmLabel;
  okBtn.className   = `btn ${confirmClass}`;

  openModal(overlayId);

  // Clone to remove previous event listeners.
  const newOk     = okBtn.cloneNode(true);
  const cancelBtn = overlay.querySelector('#confirm-cancel').cloneNode(true);
  okBtn.replaceWith(newOk);
  overlay.querySelector('#confirm-cancel').replaceWith(cancelBtn);

  newOk.addEventListener('click', () => {
    closeModal(overlayId);
    callback(true);
  }, { once: true });

  cancelBtn.addEventListener('click', () => {
    closeModal(overlayId);
    callback(false);
  }, { once: true });
}

// ============================================================
// ===== PASSWORD STRENGTH ====================================
// ============================================================

/**
 * Evaluate password strength.
 * @param {string} password
 * @returns {{ score: number, label: string, class: string }}
 */
function checkPasswordStrength(password) {
  let score = 0;
  if (password.length >= 8)                    score++;
  if (password.length >= 12)                   score++;
  if (/[A-Z]/.test(password))                  score++;
  if (/[0-9]/.test(password))                  score++;
  if (/[^A-Za-z0-9]/.test(password))           score++;

  // Clamp to 0–4.
  score = Math.min(4, score);

  const labels  = ['Muito fraca', 'Fraca', 'Razoável', 'Boa', 'Forte'];
  const classes = ['strength--very-weak', 'strength--weak', 'strength--fair', 'strength--good', 'strength--strong'];

  return { score, label: labels[score], class: classes[score] };
}

/**
 * Attach a real-time strength bar to a password input.
 * Expects a sibling [data-strength-bar] element and optional [data-strength-label].
 * @param {HTMLInputElement} inputElement
 */
function updatePasswordStrengthBar(inputElement) {
  const container  = inputElement.closest('[data-password-field]') ?? inputElement.parentElement;
  let bar          = container.querySelector('[data-strength-bar]');
  let labelEl      = container.querySelector('[data-strength-label]');

  if (!bar) {
    bar = document.createElement('div');
    bar.setAttribute('data-strength-bar', '');
    bar.className = 'strength-bar';
    bar.innerHTML = '<div class="strength-bar__fill"></div>';
    inputElement.insertAdjacentElement('afterend', bar);
  }

  if (!labelEl) {
    labelEl = document.createElement('small');
    labelEl.setAttribute('data-strength-label', '');
    bar.insertAdjacentElement('afterend', labelEl);
  }

  inputElement.addEventListener('input', () => {
    const { score, label, class: cls } = checkPasswordStrength(inputElement.value);
    const fill = bar.querySelector('.strength-bar__fill');
    fill.style.width   = `${(score / 4) * 100}%`;
    fill.className      = `strength-bar__fill ${cls}`;
    labelEl.textContent = inputElement.value ? label : '';
    labelEl.className   = cls;
  });
}

/** Attach strength bars to all inputs with data-password-strength. */
function initPasswordStrength() {
  document.querySelectorAll('input[data-password-strength]').forEach(updatePasswordStrengthBar);
}

// ============================================================
// ===== MULTI-STEP FORM ======================================
// ============================================================

const _multiStepState = new WeakMap();

/**
 * Initialise a multi-step form.
 * Steps must be .form-step elements; navigation via [data-next] and [data-prev] buttons.
 * @param {HTMLFormElement} formElement
 */
function initMultiStep(formElement) {
  const steps = Array.from(formElement.querySelectorAll('.form-step'));
  if (steps.length < 2) return;

  let current = 0;

  _multiStepState.set(formElement, {
    steps,
    get current() { return current; },
    set current(v) { current = v; },
  });

  function showStep(index) {
    steps.forEach((step, i) => {
      step.hidden = i !== index;
      step.setAttribute('aria-hidden', i !== index ? 'true' : 'false');
    });
    updateStepIndicator(formElement, index, steps.length);
    current = index;
  }

  // Wire [data-next] buttons.
  formElement.querySelectorAll('[data-next]').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      if (validateForm({ querySelectorAll: () => steps[current].querySelectorAll('[required]'), querySelector: s => steps[current].querySelector(s) })) {
        if (current < steps.length - 1) showStep(current + 1);
      }
    });
  });

  // Wire [data-prev] buttons.
  formElement.querySelectorAll('[data-prev]').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      if (current > 0) showStep(current - 1);
    });
  });

  showStep(0);
}

/**
 * Advance to the next step in a multi-step form.
 * @param {HTMLFormElement} formElement
 */
function nextStep(formElement) {
  const state = _multiStepState.get(formElement);
  if (!state) return;
  if (state.current < state.steps.length - 1) {
    state.steps[state.current + 1].hidden = false;
    state.steps[state.current].hidden     = true;
    state.current++;
    updateStepIndicator(formElement, state.current, state.steps.length);
  }
}

/**
 * Go back to the previous step.
 * @param {HTMLFormElement} formElement
 */
function prevStep(formElement) {
  const state = _multiStepState.get(formElement);
  if (!state || state.current === 0) return;
  state.steps[state.current].hidden     = true;
  state.steps[state.current - 1].hidden = false;
  state.current--;
  updateStepIndicator(formElement, state.current, state.steps.length);
}

/**
 * Refresh step indicator dots/numbers.
 * @param {HTMLFormElement} formElement
 * @param {number} currentStep  - Zero-based index.
 * @param {number} totalSteps
 */
function updateStepIndicator(formElement, currentStep, totalSteps) {
  const indicators = formElement.querySelectorAll('.step-indicator__item');
  indicators.forEach((el, i) => {
    el.classList.toggle('step-indicator__item--done',    i < currentStep);
    el.classList.toggle('step-indicator__item--active',  i === currentStep);
    el.classList.toggle('step-indicator__item--pending', i > currentStep);
    el.setAttribute('aria-current', i === currentStep ? 'step' : '');
  });

  const label = formElement.querySelector('[data-step-label]');
  if (label) label.textContent = `Passo ${currentStep + 1} de ${totalSteps}`;
}

// ============================================================
// ===== UTILITY HELPERS ======================================
// ============================================================

/**
 * Debounce a function call.
 * @param {Function} fn
 * @param {number}   delay - ms
 * @returns {Function}
 */
function debounce(fn, delay) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };
}

/**
 * Format a number as Brazilian currency string (without "R$" prefix).
 * @param {number} value
 * @returns {string}
 */
function formatBRL(value) {
  return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/**
 * Format a date string to DD/MM/YYYY.
 * Handles both ISO strings (with T) and SQL date strings (YYYY-MM-DD).
 * SQL-only dates are parsed by extracting year/month/day directly to avoid
 * timezone-offset issues that arise when Date() treats bare date strings as UTC.
 * @param {string} dateStr
 * @returns {string}
 */
function formatDate(dateStr) {
  if (!dateStr) return '';

  // If already contains a time component, let Date parse normally.
  if (dateStr.includes('T') || dateStr.includes(' ')) {
    const d = new Date(dateStr);
    return isNaN(d) ? dateStr : d.toLocaleDateString('pt-BR');
  }

  // YYYY-MM-DD — extract parts to avoid UTC midnight shifting.
  const parts = dateStr.split('-');
  if (parts.length === 3) {
    const [year, month, day] = parts.map(Number);
    const d = new Date(year, month - 1, day);
    return isNaN(d) ? dateStr : d.toLocaleDateString('pt-BR');
  }

  return dateStr;
}

// ============================================================
// ===== DOMContentLoaded – INITIALISATION ====================
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
  // Masks.
  initMasks();

  // CEP auto-fill.
  initCepAutoFill();

  // Form validation on all forms with data-validate.
  initFormValidation();

  // Sidebar.
  initSidebar();

  // Section navigation.
  if (document.querySelector('.section-content')) {
    initSectionNavigation();
  }

  // Modal global handlers.
  initModalHandlers();

  // Password strength bars.
  initPasswordStrength();

  // Multi-step forms.
  document.querySelectorAll('form[data-multistep]').forEach(initMultiStep);

  // Single image previews (input[data-preview="#targetId"]).
  document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
    const preview = document.querySelector(input.dataset.preview);
    if (preview) initImagePreview(input, preview);
  });

  // Multiple image previews (input[data-preview-container="#containerId"]).
  document.querySelectorAll('input[type="file"][data-preview-container]').forEach(input => {
    const container = document.querySelector(input.dataset.previewContainer);
    if (container) {
      const max = parseInt(input.dataset.maxFiles ?? '10', 10);
      previewMultipleImages(input, container, max);
    }
  });
});

// Expose key functions globally for use in inline HTML handlers.
window.MotorGo = window.MotorGo || {};
Object.assign(window.MotorGo, {
  showToast,
  openModal,
  closeModal,
  closeAllModals,
  showSection,
  validateForm,
  showFieldError,
  clearFieldError,
  validateEmail,
  validateCpf,
  buscarCep,
  fetchJson,
  postForm,
  initImagePreview,
  previewMultipleImages,
  confirmDelete,
  checkPasswordStrength,
  updatePasswordStrengthBar,
  initMultiStep,
  nextStep,
  prevStep,
  updateStepIndicator,
  maskMoney,
  maskPhone,
  maskCpf,
  maskCep,
  cleanMask,
  moneyToFloat,
  debounce,
  formatBRL,
  formatDate,
});
