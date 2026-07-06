function initSchoolCampusTabs() {
  const tabs = document.querySelectorAll('.school-campus-tab');
  const panels = document.querySelectorAll('.school-campus-panel');
  if (!tabs.length || !panels.length) return;

  function activate(campus) {
    tabs.forEach((tab) => {
      const isActive = tab.dataset.campus === campus;
      tab.classList.toggle('is-active', isActive);
      tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
      tab.tabIndex = isActive ? 0 : -1;
    });

    panels.forEach((panel) => {
      const isActive = panel.id === `panel-${campus}`;
      panel.classList.toggle('is-active', isActive);
      panel.hidden = !isActive;
    });

    if (history.replaceState) {
      history.replaceState(null, '', `#${campus}`);
    }
  }

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => activate(tab.dataset.campus));
    tab.addEventListener('keydown', (e) => {
      const index = Array.from(tabs).indexOf(tab);
      if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
        e.preventDefault();
        const next = e.key === 'ArrowRight' ? index + 1 : index - 1;
        const target = tabs[(next + tabs.length) % tabs.length];
        target.focus();
        activate(target.dataset.campus);
      }
    });
  });

  const hash = location.hash.replace('#', '');
  if (hash === 'london' || hash === 'leeds') {
    activate(hash);
  }
}

function initSchoolFlyerLightbox() {
  const overlay = document.getElementById('school-flyer-lightbox');
  const img = overlay?.querySelector('.school-flyer-lightbox-img');
  const closeBtn = overlay?.querySelector('.school-flyer-lightbox-close');
  if (!overlay || !img) return;

  function open(src, alt) {
    img.src = src;
    img.alt = alt;
    overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    closeBtn?.focus();
  }

  function close() {
    overlay.classList.add('hidden');
    img.src = '';
    document.body.style.overflow = '';
  }

  document.querySelectorAll('.school-flyer-trigger').forEach((trigger) => {
    trigger.addEventListener('click', () => {
      open(trigger.dataset.flyerSrc, trigger.dataset.flyerAlt || 'Admission flyer');
    });
  });

  closeBtn?.addEventListener('click', close);
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) close();
  });

  document.addEventListener('keydown', (e) => {
    if (overlay.classList.contains('hidden')) return;
    if (e.key === 'Escape') close();
  });
}

document.addEventListener('DOMContentLoaded', () => {
  initSchoolCampusTabs();
  initSchoolFlyerLightbox();
});
