const GIFT_DESCRIPTIONS = {
  tithe: 'Returning a portion of your income in faith and obedience.',
  offertory: 'Freewill gifts during worship to support the church\'s work.',
  offering: 'A general gift to support ministry and daily operations.',
  building: 'Designated giving toward church facilities and building projects.',
  mission: 'Supporting outreach, evangelism, and mission work.',
  other: 'A gift toward the ministry in the way you choose.',
};

const GIFT_LABELS = {
  tithe: 'Tithe',
  offertory: 'Offertory',
  offering: 'Offering',
  building: 'Building Fund',
  mission: 'Missions',
  other: 'Other',
};

function formatAmount(value) {
  const num = parseFloat(value);
  if (!Number.isFinite(num) || num <= 0) return null;
  return num.toFixed(2);
}

function showGivingMain() {
  document.getElementById('giving-loading')?.classList.add('hidden');
  document.getElementById('giving-main')?.classList.remove('hidden');
}

function showGivingSuccess() {
  document.getElementById('giving-loading')?.classList.add('hidden');
  document.getElementById('giving-main')?.classList.add('hidden');
  document.getElementById('giving-success')?.classList.remove('hidden');
}

function setupGivingForm(form, options = {}) {
  const { preview = false, disabled = false, config = {} } = options;
  const stripeWrap = document.getElementById('pay-stripe-wrap');
  const paypalWrap = document.getElementById('pay-paypal-wrap');
  const submitBtn = document.getElementById('giving-submit');
  const amountInput = document.getElementById('giving-amount');
  const typeDesc = document.getElementById('giving-type-desc');
  const summary = document.getElementById('giving-summary');

  if (preview) {
    form.dataset.preview = '1';
    stripeWrap?.classList.remove('hidden');
    paypalWrap?.classList.remove('hidden');
    stripeWrap?.querySelector('input')?.setAttribute('checked', 'checked');
    document.getElementById('giving-preview')?.classList.remove('hidden');
  } else {
    delete form.dataset.preview;

    if (config.stripe?.enabled) {
      stripeWrap?.classList.remove('hidden');
    }

    if (config.paypal?.enabled) {
      paypalWrap?.classList.remove('hidden');
      if (!config.stripe?.enabled) {
        paypalWrap.querySelector('input').checked = true;
      }
    }

    if (config.stripe?.enabled) {
      stripeWrap.querySelector('input').checked = true;
    }

    const stripeInput = stripeWrap?.querySelector('input');
    const paypalInput = paypalWrap?.querySelector('input');
    if (stripeInput && !paypalInput?.checked) {
      stripeInput.setAttribute('required', 'required');
    } else if (paypalInput) {
      paypalInput.setAttribute('required', 'required');
    }
  }

  if (disabled) {
    submitBtn?.setAttribute('disabled', 'disabled');
  }

  function updateSummary() {
    const giftType = form.querySelector('[name="gift_type"]:checked')?.value;
    const amount = formatAmount(amountInput.value);
    if (!giftType || !amount) {
      summary?.classList.add('hidden');
      return;
    }
    summary.classList.remove('hidden');
    summary.querySelector('strong').textContent = `${GIFT_LABELS[giftType] || 'Gift'} — £${amount}`;
  }

  function updateTypeDesc() {
    const giftType = form.querySelector('[name="gift_type"]:checked')?.value;
    if (typeDesc && giftType) {
      typeDesc.textContent = GIFT_DESCRIPTIONS[giftType] || '';
    }
    updateSummary();
  }

  form.querySelectorAll('[name="gift_type"]').forEach((input) => {
    input.addEventListener('change', updateTypeDesc);
  });

  document.querySelectorAll('.giving-amount-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      amountInput.value = btn.dataset.amount;
      document.querySelectorAll('.giving-amount-btn').forEach((b) => b.classList.remove('is-active'));
      btn.classList.add('is-active');
      updateSummary();
    });
  });

  amountInput.addEventListener('input', () => {
    const preset = amountInput.value;
    document.querySelectorAll('.giving-amount-btn').forEach((btn) => {
      btn.classList.toggle('is-active', btn.dataset.amount === preset);
    });
    updateSummary();
  });

  updateTypeDesc();

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (form.dataset.preview === '1') {
      if (typeof showToast === 'function') {
        showToast('Payments work on the live site after Stripe or PayPal is configured in Admin → Online Giving.', 'error');
      }
      return;
    }

    const original = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Redirecting…';

    try {
      const formData = new FormData(form);
      const res = await fetch('/api/create-giving-session.php', {
        method: 'POST',
        body: formData,
      });
      const data = await res.json();

      if (!res.ok || !data.success || !data.redirectUrl) {
        throw new Error(data.message || 'Unable to start payment.');
      }

      window.location.href = data.redirectUrl;
    } catch (err) {
      if (typeof showToast === 'function') {
        showToast(err.message || 'Something went wrong. Please try again.', 'error');
      }
      submitBtn.disabled = false;
      submitBtn.innerHTML = original;
    }
  });
}

async function initGivingPage() {
  const form = document.getElementById('giving-form');
  if (!form) return;

  const intro = document.getElementById('giving-intro');
  const unavailable = document.getElementById('giving-unavailable');
  const cancelledEl = document.getElementById('giving-cancelled');
  const params = new URLSearchParams(window.location.search);

  if (params.get('cancelled') === '1') {
    showGivingMain();
    cancelledEl?.classList.remove('hidden');
  }

  if (params.get('success') === '1') {
    const provider = params.get('provider');
    const token = params.get('token');

    if (provider === 'paypal' && token) {
      try {
        const res = await fetch('/api/giving-paypal-capture.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ order_id: token, website: '' }),
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
      } catch (err) {
        if (typeof showToast === 'function') {
          showToast(err.message || 'PayPal confirmation failed.', 'error');
        }
      }
    }

    showGivingSuccess();
    window.history.replaceState({}, '', '/give/');
    return;
  }

  let config = null;

  try {
    const res = await fetch('/api/giving-config.php');
    if (!res.ok) throw new Error('Config unavailable');
    const data = await res.json();
    config = data.config;
  } catch (err) {
    showGivingMain();
    setupGivingForm(form, { preview: true });
    return;
  }

  if (intro && config.intro) {
    intro.textContent = config.intro;
  }

  showGivingMain();

  if (!config.enabled) {
    unavailable?.classList.remove('hidden');
    setupGivingForm(form, { config, disabled: true });
    return;
  }

  setupGivingForm(form, { config });
}

document.addEventListener('DOMContentLoaded', initGivingPage);
