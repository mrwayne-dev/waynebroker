/* =================================================================
 * FILE: /assets/js/dock.js
 * Mobile dock "More" bottom sheet (admin only for now).
 *
 * Plain DOM, no jQuery: this loads on every dashboard page and the
 * dock must work even if a page-specific bundle fails.
 *
 * Modelled on the public site's mobile overlay (assets/js/main.js):
 * Escape closes, clicking outside closes, body scroll is locked while
 * open, and aria-expanded tracks state.
 * ================================================================= */
(function () {
  'use strict';

  function init() {
    var toggle = document.querySelector('[data-dock-more]');
    var sheet = document.getElementById('mvc-dock-sheet');
    if (!toggle || !sheet) return;

    var panel = sheet.querySelector('.mvc-sheet__panel');

    function isOpen() {
      return sheet.classList.contains('is-open');
    }

    function setOpen(open) {
      sheet.classList.toggle('is-open', open);
      sheet.setAttribute('aria-hidden', open ? 'false' : 'true');
      toggle.setAttribute('aria-expanded', String(open));
      document.body.style.overflow = open ? 'hidden' : '';
      if (open && panel) panel.focus();
    }

    toggle.addEventListener('click', function (e) {
      e.preventDefault();
      setOpen(!isOpen());
    });

    sheet.addEventListener('click', function (e) {
      // The overlay carries data-dock-close; links inside the panel should
      // navigate normally rather than being swallowed here.
      if (e.target.closest('[data-dock-close]')) setOpen(false);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && isOpen()) {
        setOpen(false);
        toggle.focus();
      }
    });

    // The sheet only exists below the dock breakpoint; leaving it "open"
    // past that point would strand the scroll lock.
    window.addEventListener('resize', function () {
      if (window.innerWidth > 1200 && isOpen()) setOpen(false);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
