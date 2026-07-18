const SITE = {
  name: 'Hour of Grace Family Chapel International',
  email: 'info@hourofgraceministries.org',
  phone: '07482673887',
  phoneDisplay: '07482 673887',
  address: '403a York Road, Leeds, LS9 6TD',
  mapsUrl: 'https://www.google.com/maps/search/?api=1&query=403a+York+Road,+Leeds,+LS9+6TD',
  url: 'https://hourofgraceministries.org',
  facebook: 'https://www.facebook.com/people/Hour-Of-Grace-Family-Chapel-International/61557072391077/',
};

const SOCIAL_LINKS = [
  {
    name: 'Facebook',
    href: 'https://www.facebook.com/people/Hour-Of-Grace-Family-Chapel-International/61557072391077/',
    label: 'Hour Of Grace Family Chapel International on Facebook',
    icon: 'facebook',
  },
];

const NAV_LINKS = [
  { href: '/', label: 'Home', slug: 'home' },
  { href: '/about/', label: 'About Us', slug: 'about' },
  { href: '/gallery/', label: 'Gallery', slug: 'gallery' },
  { href: '/school/', label: 'Bible School', slug: 'school' },
  { href: '/prayer/', label: 'Prayer Request', slug: 'prayer' },
  { href: '/give/', label: 'Give', slug: 'give' },
  { href: '/contact/', label: 'Contact Us', slug: 'contact' },
];

const PROGRAMS = [
  { day: 'Sunday', title: 'Worship Service', time: '12:00 PM – 3:00 PM' },
  { day: 'Wednesday', title: 'Prayer & Discipleship', time: '6:00 PM – 8:00 PM' },
  { day: 'Saturday', title: 'Maths, Science & Music Class', time: '12:00 PM – 2:00 PM' },
  { day: 'Saturday', title: 'Choir Practice', time: '2:00 PM – 4:00 PM' },
  { day: 'Saturday', title: 'Prayer Time', time: '2:00 PM – 4:00 PM' },
  { day: 'Last Friday Monthly', title: 'Night Vigil', time: '8:30 PM – 12:00 AM' },
];

let galleryImages = [];

function resolveMediaUrl(url) {
  if (!url) return url;
  if (url.startsWith('http://') || url.startsWith('https://')) return url;
  return url.startsWith('/') ? url : `/${url.replace(/^\/+/, '')}`;
}

function currentPage() {
  const path = window.location.pathname.replace(/\/$/, '') || '/';
  if (path === '/' || path === '/index' || path.endsWith('/index.html')) {
    return 'home';
  }
  const segment = path.split('/').filter(Boolean).pop() || 'home';
  return segment.replace(/\.html$/, '');
}

function socialIcon(name) {
  if (name === 'facebook') {
    return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>';
  }

  return '';
}

function renderSocialLinks() {
  if (!SOCIAL_LINKS.length) return '';

  return `
    <div class="footer-social">
      <p class="footer-social-label">Follow us</p>
      <ul class="footer-social-list">
        ${SOCIAL_LINKS.map(({ name, href, label, icon }) => `
          <li>
            <a href="${href}" target="_blank" rel="noopener noreferrer" class="footer-social-link" aria-label="${label}">
              <span class="footer-social-icon">${socialIcon(icon)}</span>
              ${name}
            </a>
          </li>
        `).join('')}
      </ul>
    </div>
  `;
}

function renderHeader() {
  const page = currentPage();
  const navItems = NAV_LINKS.map(({ href, label, slug }) => {
    const active = page === slug ? 'text-brand-purple font-medium' : 'text-slate-600 hover:text-brand-purple';
    return `<a href="${href}" class="${active} transition-colors duration-200">${label}</a>`;
  }).join('');

  return `
    <header class="fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-md border-b border-slate-100">
      <div class="max-w-6xl mx-auto px-5 lg:px-8">
        <div class="flex items-center justify-between h-[72px]">
          <a href="/" class="flex items-center shrink-0">
            <img src="/assets/logo.png" alt="${SITE.name}" class="h-12 w-auto md:h-14" />
          </a>

          <nav class="hidden lg:flex items-center gap-6 xl:gap-8 text-[15px]">
            ${navItems}
            <a href="/register/" class="ml-1 inline-flex items-center px-5 py-2.5 rounded-full bg-brand-purple text-white text-sm font-medium hover:bg-brand-purple-dark transition-colors duration-200">
              Register
            </a>
          </nav>

          <button id="menu-toggle" type="button" class="lg:hidden p-2 -mr-2 text-brand-purple" aria-label="Open menu" aria-expanded="false">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
          </button>
        </div>
      </div>

      <div id="mobile-menu" class="hidden lg:hidden border-t border-slate-100 bg-white">
        <nav class="max-w-6xl mx-auto px-5 py-5 flex flex-col gap-4 text-[15px]">
          ${NAV_LINKS.map(({ href, label }) => `<a href="${href}" class="py-1 text-slate-700 hover:text-brand-purple">${label}</a>`).join('')}
          <a href="/register/" class="mt-2 inline-flex justify-center items-center px-5 py-3 rounded-full bg-brand-purple text-white font-medium">Register</a>
        </nav>
      </div>
    </header>
  `;
}

function renderFooter() {
  const year = new Date().getFullYear();
  const pages = [...NAV_LINKS, { href: '/register/', label: 'Register' }];

  return `
    <footer class="site-footer-dark">
      <div class="max-w-6xl mx-auto px-5 lg:px-8 py-12 lg:py-14">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10 text-sm text-slate-400">
          <div>
            <p class="text-white font-bold text-[15px] leading-snug mb-3">${SITE.name}</p>
            <p class="leading-relaxed">Sunday worship 12:00 PM – 3:00 PM</p>
            <p class="mt-3 leading-relaxed">
              <a href="${SITE.mapsUrl}" target="_blank" rel="noopener noreferrer" class="hover:text-white transition-colors">${SITE.address}</a>
            </p>
            ${renderSocialLinks()}
          </div>

          <div>
            <p class="text-white font-medium mb-3">Menu</p>
            <ul class="space-y-2">
              ${pages.map(({ href, label }) => `<li><a href="${href}" class="hover:text-white transition-colors">${label}</a></li>`).join('')}
            </ul>
          </div>

          <div>
            <p class="text-white font-medium mb-3">Contact</p>
            <ul class="footer-contact-list">
              <li>
                <a href="tel:${SITE.phone}" class="footer-contact-link">
                  <span class="footer-contact-icon" aria-hidden="true">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                  </span>
                  ${SITE.phoneDisplay}
                </a>
              </li>
              <li>
                <a href="mailto:${SITE.email}" class="footer-contact-link">
                  <span class="footer-contact-icon" aria-hidden="true">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                  </span>
                  ${SITE.email}
                </a>
              </li>
              <li>
                <a href="${SITE.mapsUrl}" target="_blank" rel="noopener noreferrer" class="footer-contact-link">
                  <span class="footer-contact-icon" aria-hidden="true">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                  </span>
                  ${SITE.address}
                </a>
              </li>
              <li>
                <a href="/contact/" class="footer-contact-link">
                  <span class="footer-contact-icon" aria-hidden="true">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                  </span>
                  Send a message
                </a>
              </li>
              <li>
                <a href="/prayer/" class="footer-contact-link">
                  <span class="footer-contact-icon" aria-hidden="true">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                  </span>
                  Prayer request
                </a>
              </li>
            </ul>
          </div>

          <div>
            <p class="text-white font-medium mb-3">Join our mailing list</p>
            <p class="mb-3 leading-relaxed">Event updates and church news by email.</p>
            <form data-form data-endpoint="/api/subscribe.php" class="footer-subscribe-form">
              <input type="text" name="website" tabindex="-1" autocomplete="off" class="hp-field" aria-hidden="true" />
              <label class="sr-only" for="footer-subscribe-email">Email</label>
              <div class="footer-subscribe-row">
                <input
                  id="footer-subscribe-email"
                  type="email"
                  name="email"
                  required
                  placeholder="you@email.com"
                  class="footer-input"
                  autocomplete="email"
                />
                <button type="submit" class="footer-subscribe-send" aria-label="Subscribe">
                  <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                </button>
              </div>
            </form>
          </div>
        </div>

        <div class="site-footer-dark-bar">
          <p>&copy; ${year} Hour of Grace Ministry International</p>
          <p>Apostle Vida Owusu, Founder</p>
        </div>
      </div>
    </footer>
  `;
}

function showToast(message, type = 'success') {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `
    <span>${message}</span>
    <button type="button" aria-label="Dismiss">&times;</button>
  `;

  toast.querySelector('button').addEventListener('click', () => toast.remove());
  container.appendChild(toast);

  setTimeout(() => {
    toast.classList.add('toast-hide');
    setTimeout(() => toast.remove(), 300);
  }, 5000);
}

function initLayout() {
  const headerEl = document.getElementById('site-header');
  const footerEl = document.getElementById('site-footer');
  if (headerEl) headerEl.innerHTML = renderHeader();
  if (footerEl) footerEl.innerHTML = renderFooter();

  const toggle = document.getElementById('menu-toggle');
  const menu = document.getElementById('mobile-menu');
  if (toggle && menu) {
    toggle.addEventListener('click', () => {
      const isHidden = menu.classList.toggle('hidden');
      toggle.setAttribute('aria-expanded', String(!isHidden));
    });

    menu.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => {
        menu.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  }
}

async function loadGalleryImages() {
  try {
    const res = await fetch('/api/gallery.php');
    const data = await res.json();
    if (data.success && Array.isArray(data.images) && data.images.length) {
      galleryImages = data.images.map((img) => resolveMediaUrl(img.url));
      return galleryImages;
    }
  } catch (err) {
    console.warn('Gallery API unavailable, trying static gallery data.');
  }

  try {
    const res = await fetch('/data/gallery.json');
    const urls = await res.json();
    if (Array.isArray(urls) && urls.length) {
      galleryImages = urls.map(resolveMediaUrl);
      return galleryImages;
    }
  } catch (err) {
    console.warn('Static gallery JSON unavailable.');
  }

  galleryImages = [];
  return galleryImages;
}

async function loadHeroSlides() {
  try {
    const res = await fetch('/api/hero.php');
    const data = await res.json();
    if (data.success && Array.isArray(data.images) && data.images.length) {
      return data.images.map((img) => resolveMediaUrl(img.url));
    }
  } catch (err) {
    console.warn('Hero API unavailable, falling back to gallery.');
  }

  const gallery = await loadGalleryImages();
  return gallery.slice(0, 5);
}

function initHeroSlider(images) {
  const slider = document.getElementById('hero-slider');
  const dotsContainer = document.getElementById('hero-dots');
  const section = document.getElementById('hero-section');
  if (!slider || !dotsContainer) return;

  const slides = images.slice(0, 5);
  if (!slides.length) return;

  slider.innerHTML = slides
    .map(
      (src, i) => `
      <div class="hero-slide${i === 0 ? ' is-active' : ''}" data-index="${i}">
        <img src="${src}" alt="Hour of Grace ministry photo ${i + 1}" ${i === 0 ? '' : 'loading="lazy"'} />
      </div>
    `
    )
    .join('');

  dotsContainer.innerHTML = slides
    .map(
      (_, i) => `
      <button
        type="button"
        class="hero-dot${i === 0 ? ' is-active' : ''}"
        data-index="${i}"
        role="tab"
        aria-label="Show slide ${i + 1}"
        aria-selected="${i === 0 ? 'true' : 'false'}"
      ></button>
    `
    )
    .join('');

  let current = 0;
  let timer = null;
  const slideEls = slider.querySelectorAll('.hero-slide');
  const dotEls = dotsContainer.querySelectorAll('.hero-dot');

  function goTo(index) {
    current = (index + slides.length) % slides.length;
    slideEls.forEach((el, i) => el.classList.toggle('is-active', i === current));
    dotEls.forEach((el, i) => {
      el.classList.toggle('is-active', i === current);
      el.setAttribute('aria-selected', i === current ? 'true' : 'false');
    });
  }

  function next() {
    goTo(current + 1);
  }

  function startAutoplay() {
    stopAutoplay();
    timer = window.setInterval(next, 6000);
  }

  function stopAutoplay() {
    if (timer) {
      window.clearInterval(timer);
      timer = null;
    }
  }

  dotsContainer.addEventListener('click', (e) => {
    const btn = e.target.closest('.hero-dot');
    if (!btn) return;
    goTo(Number(btn.dataset.index));
    startAutoplay();
  });

  section?.addEventListener('mouseenter', stopAutoplay);
  section?.addEventListener('mouseleave', startAutoplay);
  section?.addEventListener('focusin', stopAutoplay);
  section?.addEventListener('focusout', startAutoplay);

  startAutoplay();
}

function initGalleryGrid(containerId, limit, images) {
  const container = document.getElementById(containerId);
  if (!container) return;

  const list = limit ? images.slice(0, limit) : images;

  if (!list.length) {
    container.innerHTML = '<p class="text-slate-500 col-span-full text-center py-12">Gallery photos will appear here soon.</p>';
    return;
  }

  container.innerHTML = list
    .map(
      (src, i) => `
      <button type="button" class="gallery-item group relative aspect-[4/3] overflow-hidden rounded-xl bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-purple" data-index="${i}" aria-label="View photo ${i + 1}">
        <img src="${src}" alt="Hour of Grace ministry photo ${i + 1}" loading="lazy" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" />
        <div class="absolute inset-0 bg-brand-purple/0 group-hover:bg-brand-purple/20 transition-colors duration-300"></div>
      </button>
    `
    )
    .join('');

  initLightbox(list);
}

function initLightbox(images) {
  let current = 0;
  const overlay = document.getElementById('lightbox');
  const img = document.getElementById('lightbox-img');
  if (!overlay || !img) return;

  function show(index) {
    current = (index + images.length) % images.length;
    img.src = images[current];
    overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }

  function hide() {
    overlay.classList.add('hidden');
    document.body.style.overflow = '';
  }

  document.querySelectorAll('.gallery-item').forEach((btn) => {
    btn.addEventListener('click', () => show(Number(btn.dataset.index)));
  });

  document.getElementById('lightbox-close')?.addEventListener('click', hide);
  document.getElementById('lightbox-prev')?.addEventListener('click', () => show(current - 1));
  document.getElementById('lightbox-next')?.addEventListener('click', () => show(current + 1));

  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) hide();
  });

  document.addEventListener('keydown', (e) => {
    if (overlay.classList.contains('hidden')) return;
    if (e.key === 'Escape') hide();
    if (e.key === 'ArrowLeft') show(current - 1);
    if (e.key === 'ArrowRight') show(current + 1);
  });
}

async function initForms() {
  document.querySelectorAll('[data-form]').forEach((form) => {
    if (!form.querySelector('[name="website"]')) {
      form.insertAdjacentHTML(
        'beforeend',
        '<input type="text" name="website" tabindex="-1" autocomplete="off" class="hp-field" aria-hidden="true" />'
      );
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const endpoint = form.dataset.endpoint;
      if (!endpoint) return;

      const btn = form.querySelector('[type="submit"]');
      const original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="btn-loading">Sending…</span>';

      form.querySelectorAll('.field-error').forEach((el) => el.remove());
      form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));

      try {
        const res = await fetch(endpoint, {
          method: 'POST',
          body: new FormData(form),
        });
        const data = await res.json();

        if (!res.ok || !data.success) {
          throw new Error(data.message || 'Submission failed. Please try again.');
        }

        form.reset();
        showToast(data.message || 'Submitted successfully.', 'success');
      } catch (err) {
        showToast(err.message || 'Something went wrong. Please try again.', 'error');
      } finally {
        btn.disabled = false;
        btn.innerHTML = original;
      }
    });

    form.querySelectorAll('[required]').forEach((field) => {
      field.addEventListener('invalid', (e) => {
        e.preventDefault();
        field.classList.add('is-invalid');
      });
      field.addEventListener('input', () => field.classList.remove('is-invalid'));
    });
  });
}

function renderPrograms(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;

  container.innerHTML = PROGRAMS.map(
    ({ day, title, time }) => `
    <div class="programs-item">
      <p class="programs-day">${day}</p>
      <div>
        <h3 class="programs-title">${title}</h3>
        <p class="programs-time">${time}</p>
      </div>
    </div>
  `
  ).join('');
}

document.addEventListener('DOMContentLoaded', async () => {
  initLayout();
  initForms();
  renderPrograms('programs-grid');

  const images = await loadGalleryImages();
  const heroSlides = await loadHeroSlides();
  initHeroSlider(heroSlides);
  initGalleryGrid('gallery-preview', 12, images);
  initGalleryGrid('gallery-full', null, images);
});
