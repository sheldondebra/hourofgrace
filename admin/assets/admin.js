(function () {
  'use strict';

  var body = document.body;
  var sidebar = document.getElementById('adminSidebar');
  var toggle = document.getElementById('menuToggle');

  function openSidebar() {
    body.classList.add('sidebar-open');
    if (toggle) toggle.setAttribute('aria-expanded', 'true');
  }

  function closeSidebar() {
    body.classList.remove('sidebar-open');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
  }

  if (toggle) {
    toggle.addEventListener('click', function () {
      if (body.classList.contains('sidebar-open')) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });
  }

  document.querySelectorAll('[data-close-sidebar]').forEach(function (el) {
    el.addEventListener('click', closeSidebar);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSidebar();
  });

  // Close the drawer after tapping a link on small screens.
  if (sidebar) {
    sidebar.querySelectorAll('.nav-link').forEach(function (link) {
      link.addEventListener('click', function () {
        if (window.innerWidth <= 1024) closeSidebar();
      });
    });
  }

  // Auto-dismiss success alerts after a short delay, with a manual close button.
  document.querySelectorAll('.alert').forEach(function (alert) {
    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'alert-close';
    close.setAttribute('aria-label', 'Dismiss');
    close.innerHTML = '&times;';
    close.addEventListener('click', function () {
      alert.style.display = 'none';
    });
    alert.appendChild(close);

    if (alert.classList.contains('alert-success')) {
      setTimeout(function () {
        alert.classList.add('is-fading');
        setTimeout(function () {
          alert.style.display = 'none';
        }, 400);
      }, 5000);
    }
  });
})();
