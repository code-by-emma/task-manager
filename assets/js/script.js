/**
 * Task Manager - client-side enhancements.
 *
 * Everything here is progressive enhancement only. All real validation,
 * authorization, and data handling happens server-side in PHP; this file
 * never contains business logic that the server doesn't also enforce.
 */
(function () {
  'use strict';

  // Confirm before any form marked with data-confirm submits (e.g. delete
  // task forms).
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (form.hasAttribute && form.hasAttribute('data-confirm')) {
      var message = form.getAttribute('data-confirm') || 'Are you sure?';
      if (!window.confirm(message)) {
        event.preventDefault();
      }
    }
  });

  // Mobile nav toggle.
  var navToggle = document.getElementById('navToggle');
  var appNav = document.querySelector('.app-nav');
  if (navToggle && appNav) {
    navToggle.addEventListener('click', function () {
      appNav.classList.toggle('is-open');
    });
  }

  // Auto-dismiss flash messages after a few seconds.
  var flashes = document.querySelectorAll('.flash');
  flashes.forEach(function (flash) {
    setTimeout(function () {
      flash.style.transition = 'opacity 0.4s ease';
      flash.style.opacity = '0';
      setTimeout(function () {
        flash.remove();
      }, 400);
    }, 4000);
  });

  // Live character count for textareas with maxlength (description field).
  var textareas = document.querySelectorAll('textarea[maxlength]');
  textareas.forEach(function (textarea) {
    var max = textarea.getAttribute('maxlength');
    var counter = document.createElement('small');
    counter.className = 'form-hint';
    counter.style.display = 'block';
    counter.style.marginTop = '4px';

    function updateCounter() {
      counter.textContent = textarea.value.length + ' / ' + max + ' characters';
    }

    updateCounter();
    textarea.insertAdjacentElement('afterend', counter);
    textarea.addEventListener('input', updateCounter);
  });

  // Dark mode toggle. The theme attribute itself is already set as early
  // as possible by an inline script in <head> (to avoid a flash of the
  // wrong theme); this just keeps the icon in sync and handles clicks.
  var themeToggle = document.getElementById('themeToggle');
  var themeToggleIcon = document.getElementById('themeToggleIcon');

  function currentTheme() {
    return document.documentElement.getAttribute('data-theme') || 'light';
  }

  function syncIcon() {
    if (!themeToggleIcon) return;
    // Show a sun when in dark mode (click to go light), and a moon when
    // in light mode (click to go dark).
    themeToggleIcon.textContent = currentTheme() === 'dark' ? '\u2600\uFE0F' : '\uD83C\uDF19';
  }

  function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    try {
      localStorage.setItem('theme', theme);
    } catch (e) {
      // Ignore storage errors (private browsing, disabled storage, etc.).
    }
    syncIcon();
  }

  if (themeToggle) {
    syncIcon();
    themeToggle.addEventListener('click', function () {
      setTheme(currentTheme() === 'dark' ? 'light' : 'dark');
    });
  }

  // Show/hide password toggles. Each button has data-target pointing to
  // the id of the password input it controls.
  document.querySelectorAll('.password-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-target'));
      if (!input) return;

      var isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      btn.textContent = isHidden ? '\uD83D\uDE48' : '\uD83D\uDC41\uFE0F';
      btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
    });
  });
})();
