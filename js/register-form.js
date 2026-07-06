const REGISTER_LABELS = {
  job_category: {
    minister: 'Minister of Religion',
    worker: 'Religious Worker',
  },
  job_interest: {
    singer: 'Singer',
    organist: 'Organist',
    guitarist: 'Guitarist',
    drummer: 'Drummer',
    trumpeter: 'Trumpeter',
    'bible-teacher': 'Bible Teacher',
    'sound-engineer': 'Sound Engineer',
    'children-bible-teacher': "Children's Bible Teacher",
    pastor: 'Pastor',
    'evangelism-leader': 'Leader of Evangelism',
    'youth-leader': 'Leader of Youth Ministry',
    procurement: 'Procurement Officers',
    'women-fellowship': "Women's Fellowship Leaders",
    'men-fellowship': "Men's Fellowship Leaders",
  },
  yes_no: {
    yes: 'Yes',
    no: 'No',
  },
};

const REGISTER_STEPS = [
  { id: 1, title: 'Personal', desc: 'Your details' },
  { id: 2, title: 'Role', desc: 'Education & ministry' },
  { id: 3, title: 'Faith', desc: 'Background' },
  { id: 4, title: 'Documents', desc: 'Upload files' },
  { id: 5, title: 'Review', desc: 'Confirm & submit' },
];

const PREVIEW_FIELDS = [
  { step: 1, label: 'Full name', name: 'name' },
  { step: 1, label: 'Email', name: 'email' },
  { step: 1, label: 'Home address', name: 'address' },
  { step: 1, label: 'Date of birth', name: 'dob', type: 'date' },
  { step: 1, label: 'Country of birth', name: 'country_birth' },
  { step: 1, label: 'Country of residence', name: 'country_resident' },
  { step: 2, label: 'Highest education', name: 'education' },
  { step: 2, label: 'Job category', name: 'job_category', map: 'job_category' },
  { step: 2, label: 'Area of interest', name: 'job_interest', map: 'job_interest' },
  { step: 2, label: 'Religion', name: 'religion' },
  { step: 3, label: 'Born again', name: 'born_again', map: 'yes_no' },
  { step: 3, label: 'Baptised', name: 'baptized', map: 'yes_no' },
  { step: 3, label: 'Baptism year', name: 'baptism_year', optional: true },
  { step: 3, label: 'Baptism church', name: 'baptism_church', optional: true },
  { step: 3, label: 'Name of baptizer', name: 'baptizer', optional: true },
];

let registerStep = 1;
let registerFiles = [];
let registerPreviewUrls = [];

function formatRegisterValue(form, field) {
  const el = form.elements[field.name];
  if (!el) return '—';
  const raw = el.value?.trim() || '';
  if (!raw) return field.optional ? '—' : '—';
  if (field.type === 'date' && raw) {
    return new Date(`${raw}T12:00:00`).toLocaleDateString('en-GB', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    });
  }
  if (field.map === 'job_category') return REGISTER_LABELS.job_category[raw] || raw;
  if (field.map === 'job_interest') return REGISTER_LABELS.job_interest[raw] || raw;
  if (field.map === 'yes_no') return REGISTER_LABELS.yes_no[raw] || raw;
  return raw;
}

function isImageFile(file) {
  return /^image\//.test(file.type) || /\.(jpe?g|png|gif|webp)$/i.test(file.name);
}

function revokePreviewUrls() {
  registerPreviewUrls.forEach((url) => URL.revokeObjectURL(url));
  registerPreviewUrls = [];
}

function renderRegisterSteps() {
  const nav = document.getElementById('register-step-nav');
  if (!nav) return;

  nav.innerHTML = REGISTER_STEPS.map(({ id, title, desc }) => {
    let state = '';
    if (id === registerStep) state = 'is-active';
    else if (id < registerStep) state = 'is-complete';
    return `
      <li class="register-step ${state}" data-step-indicator="${id}">
        <span class="register-step-num">${id < registerStep ? '✓' : id}</span>
        <span class="register-step-text">
          <strong>${title}</strong>
          <small>${desc}</small>
        </span>
      </li>
    `;
  }).join('');
}

function showRegisterStep(step) {
  registerStep = step;
  document.querySelectorAll('[data-register-step]').forEach((panel) => {
    panel.hidden = Number(panel.dataset.registerStep) !== step;
  });

  renderRegisterSteps();

  const backBtn = document.getElementById('register-back');
  const nextBtn = document.getElementById('register-next');
  const submitBtn = document.getElementById('register-submit');

  if (backBtn) backBtn.hidden = step === 1;
  if (nextBtn) nextBtn.hidden = step === REGISTER_STEPS.length;
  if (submitBtn) submitBtn.hidden = step !== REGISTER_STEPS.length;

  if (step === REGISTER_STEPS.length) {
    renderRegisterReview();
  }

  if (step === 4) {
    renderFilePreviews(document.getElementById('register-doc-previews'));
  }

  window.scrollTo({ top: document.getElementById('register-wizard')?.offsetTop - 96 || 0, behavior: 'smooth' });
}

function validateRegisterStep(step) {
  const panel = document.querySelector(`[data-register-step="${step}"]`);
  const form = document.getElementById('register-wizard-form');
  if (!panel || !form) return false;

  panel.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));

  const fields = panel.querySelectorAll('input, select, textarea');
  let valid = true;

  fields.forEach((field) => {
    if (field.disabled || field.type === 'file') return;
    if (!field.required) return;
    if (!field.value.trim()) {
      field.classList.add('is-invalid');
      valid = false;
    }
  });

  if (!valid && typeof showToast === 'function') {
    showToast('Please complete all required fields before continuing.', 'error');
  }

  return valid;
}

function renderFilePreviews(container) {
  if (!container) return;

  revokePreviewUrls();

  if (!registerFiles.length) {
    container.innerHTML = '<p class="register-doc-empty">No documents selected yet.</p>';
    return;
  }

  container.innerHTML = registerFiles
    .map((file, index) => {
      const isImage = isImageFile(file);
      let preview = '';

      if (isImage) {
        const url = URL.createObjectURL(file);
        registerPreviewUrls.push(url);
        preview = `<img src="${url}" alt="${file.name}" class="register-doc-thumb" data-preview-index="${index}" />`;
      } else {
        preview = `
          <div class="register-doc-file">
            <svg class="w-8 h-8 text-brand-purple" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          </div>
        `;
      }

      return `
        <article class="register-doc-card">
          ${preview}
          <div class="register-doc-meta">
            <p class="register-doc-name" title="${file.name}">${file.name}</p>
            <p class="register-doc-size">${(file.size / 1024).toFixed(1)} KB</p>
          </div>
          <div class="register-doc-actions">
            ${isImage ? `<button type="button" class="register-doc-view" data-preview-index="${index}">View</button>` : ''}
            <button type="button" class="register-doc-remove" data-remove-index="${index}">Remove</button>
          </div>
        </article>
      `;
    })
    .join('');

  container.querySelectorAll('[data-preview-index]').forEach((el) => {
    el.addEventListener('click', () => openRegisterImagePreview(Number(el.dataset.previewIndex)));
  });

  container.querySelectorAll('[data-remove-index]').forEach((btn) => {
    btn.addEventListener('click', () => {
      registerFiles.splice(Number(btn.dataset.removeIndex), 1);
      syncRegisterFileInput();
      renderFilePreviews(container);
      const reviewDocs = document.getElementById('register-review-docs');
      if (reviewDocs) renderFilePreviews(reviewDocs);
    });
  });
}

function syncRegisterFileInput() {
  const input = document.getElementById('documents');
  if (!input) return;
  const dt = new DataTransfer();
  registerFiles.forEach((file) => dt.items.add(file));
  input.files = dt.files;
}

function openRegisterImagePreview(index) {
  const file = registerFiles[index];
  if (!file || !isImageFile(file)) return;

  let overlay = document.getElementById('register-doc-lightbox');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = 'register-doc-lightbox';
    overlay.className = 'register-doc-lightbox hidden';
    overlay.innerHTML = `
      <button type="button" class="register-doc-lightbox-close" aria-label="Close">&times;</button>
      <img src="" alt="Document preview" />
    `;
    document.body.appendChild(overlay);
    overlay.querySelector('.register-doc-lightbox-close').addEventListener('click', () => {
      overlay.classList.add('hidden');
      document.body.style.overflow = '';
    });
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
      }
    });
  }

  const url = URL.createObjectURL(file);
  const img = overlay.querySelector('img');
  img.src = url;
  img.onload = () => URL.revokeObjectURL(url);
  overlay.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function renderRegisterReview() {
  const form = document.getElementById('register-wizard-form');
  const summary = document.getElementById('register-review-summary');
  const reviewDocs = document.getElementById('register-review-docs');
  if (!form || !summary) return;

  const grouped = {};
  PREVIEW_FIELDS.forEach((field) => {
    if (!grouped[field.step]) grouped[field.step] = [];
    grouped[field.step].push(field);
  });

  const stepTitles = {
    1: 'Personal details',
    2: 'Role & education',
    3: 'Faith & baptism',
  };

  summary.innerHTML = Object.keys(grouped)
    .map((step) => {
      const rows = grouped[step]
        .map(
          (field) => `
          <div class="register-preview-row">
            <dt>${field.label}</dt>
            <dd>${formatRegisterValue(form, field)}</dd>
          </div>
        `
        )
        .join('');

      return `
        <section class="register-preview-section">
          <div class="register-preview-head">
            <h3>${stepTitles[step]}</h3>
            <button type="button" class="register-edit-step" data-go-step="${step}">Edit</button>
          </div>
          <dl class="register-preview-grid">${rows}</dl>
        </section>
      `;
    })
    .join('');

  summary.querySelectorAll('[data-go-step]').forEach((btn) => {
    btn.addEventListener('click', () => showRegisterStep(Number(btn.dataset.goStep)));
  });

  renderFilePreviews(reviewDocs);
}

async function submitRegisterForm() {
  const form = document.getElementById('register-wizard-form');
  const submitBtn = document.getElementById('register-submit');
  if (!form || !submitBtn) return;

  const trap = form.querySelector('[name="website"]');
  if (trap && trap.value.trim()) {
    return;
  }

  const original = submitBtn.innerHTML;
  submitBtn.disabled = true;
  submitBtn.innerHTML = 'Submitting…';

  try {
    const formData = new FormData(form);
    formData.delete('documents[]');
    registerFiles.forEach((file) => formData.append('documents[]', file));

    const res = await fetch(form.dataset.endpoint, {
      method: 'POST',
      body: formData,
    });
    const data = await res.json();

    if (!res.ok || !data.success) {
      throw new Error(data.message || 'Submission failed. Please try again.');
    }

    form.reset();
    registerFiles = [];
    syncRegisterFileInput();
    renderFilePreviews(document.getElementById('register-doc-previews'));
    showRegisterStep(1);

    if (typeof showToast === 'function') {
      showToast(data.message || 'Registration submitted successfully.', 'success');
    }
  } catch (err) {
    if (typeof showToast === 'function') {
      showToast(err.message || 'Something went wrong. Please try again.', 'error');
    }
  } finally {
    submitBtn.disabled = false;
    submitBtn.innerHTML = original;
  }
}

function initRegisterWizard() {
  const form = document.getElementById('register-wizard-form');
  if (!form) return;

  const docInput = document.getElementById('documents');
  const docPreviews = document.getElementById('register-doc-previews');
  const backBtn = document.getElementById('register-back');
  const nextBtn = document.getElementById('register-next');
  const submitBtn = document.getElementById('register-submit');

  renderRegisterSteps();
  showRegisterStep(1);
  renderFilePreviews(docPreviews);

  backBtn?.addEventListener('click', () => {
    if (registerStep > 1) showRegisterStep(registerStep - 1);
  });

  nextBtn?.addEventListener('click', () => {
    if (!validateRegisterStep(registerStep)) return;
    if (registerStep < REGISTER_STEPS.length) showRegisterStep(registerStep + 1);
  });

  docInput?.addEventListener('change', () => {
    const incoming = [...docInput.files];
    incoming.forEach((file) => {
      const exists = registerFiles.some((f) => f.name === file.name && f.size === file.size);
      if (!exists) registerFiles.push(file);
    });
    syncRegisterFileInput();
    renderFilePreviews(docPreviews);
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (registerStep !== REGISTER_STEPS.length) return;
    submitRegisterForm();
  });

  form.querySelectorAll('[required]').forEach((field) => {
    field.addEventListener('input', () => field.classList.remove('is-invalid'));
    field.addEventListener('change', () => field.classList.remove('is-invalid'));
  });
}

document.addEventListener('DOMContentLoaded', initRegisterWizard);
