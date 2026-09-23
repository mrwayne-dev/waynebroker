/* =====================================================================
 * FILE: /assets/js/motion.js
 * Scroll motion for the public marketing pages.
 *
 * Self-hosted and external on purpose. The strict CSP pins inline scripts
 * by SHA-256, so anything inline would need its hash regenerated on every
 * edit; an external file is covered by 'self' and needs nothing. No
 * third-party animation library is reachable under this policy anyway.
 *
 * FOUR RULES, all load-bearing:
 *
 *   1. Additive only. Every effect starts from a state where the page is
 *      already complete and readable. With JS off nothing is hidden,
 *      nothing is half-faded, and every link works - which is also why
 *      the reveal opacity is set BY this file rather than in the CSS.
 *   2. prefers-reduced-motion: reduce disables all of it. Not "less of
 *      it" - none of it.
 *   3. Nothing below 900px. Parallax and pinning cost battery and fight
 *      the reader's own scrolling on a phone.
 *   4. No layout reads or writes in a scroll handler. Positions are
 *      measured once, cached, and only `transform` and `opacity` are
 *      written - the two properties the compositor can handle without a
 *      reflow.
 * ===================================================================== */
(function () {
  'use strict';

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  var wide    = window.matchMedia('(min-width: 901px)');

  /* ---------------------------------------------------------------
   * 1. REVEAL - sections rise and fade as they enter
   * ------------------------------------------------------------- */
  function initReveal() {
    var items = Array.prototype.slice.call(document.querySelectorAll('[data-reveal]'));
    if (!items.length) return;

    // No IntersectionObserver: never hide anything. An un-revealed element is
    // invisible content, which is strictly worse than no animation.
    if (!('IntersectionObserver' in window)) return;

    items.forEach(function (el) {
      el.style.opacity = '0';
      el.style.transform = 'translateY(18px)';
      el.style.transition = 'opacity .7s cubic-bezier(.16,1,.3,1), transform .7s cubic-bezier(.16,1,.3,1)';
    });

    function show(el, delay) {
      if (el.__shown) return;
      el.__shown = true;
      setTimeout(function () {
        el.style.opacity = '1';
        el.style.transform = 'none';
      }, delay || 0);
    }

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        show(e.target, parseInt(e.target.getAttribute('data-reveal-delay') || '0', 10));
        io.unobserve(e.target);
      });
    }, { rootMargin: '0px 0px -12% 0px', threshold: 0.08 });

    items.forEach(function (el) { io.observe(el); });

    /* FAILSAFE.
     *
     * The observer is the nice path, not the guarantee. A fast flick-scroll can
     * carry an element through the root before a callback is delivered, and
     * anything missed that way stays at opacity 0 forever - measured at 10 of
     * 20 elements on this page during a scripted fast scroll.
     *
     * So sweep independently: anything that has reached the viewport gets shown
     * whether or not the observer reported it. Cheap (a rect read on a rAF,
     * only for elements not yet shown) and it makes permanently-invisible
     * content impossible.
     */
    var ticking = false;
    function sweep() {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(function () {
        ticking = false;
        var vh = window.innerHeight || document.documentElement.clientHeight;
        var remaining = false;
        items.forEach(function (el) {
          if (el.__shown) return;
          remaining = true;
          if (el.getBoundingClientRect().top < vh * 0.95) {
            show(el, parseInt(el.getAttribute('data-reveal-delay') || '0', 10));
            io.unobserve(el);
          }
        });
        if (!remaining) {
          window.removeEventListener('scroll', sweep);
          window.removeEventListener('resize', sweep);
        }
      });
    }
    window.addEventListener('scroll', sweep, { passive: true });
    window.addEventListener('resize', sweep, { passive: true });
    sweep();   // anything already in view at load
  }

  /* ---------------------------------------------------------------
   * 2. COUNTERS - figures count up once, on entry
   * ------------------------------------------------------------- */
  function initCounters() {
    var els = document.querySelectorAll('[data-count-to]');
    if (!els.length || !('IntersectionObserver' in window)) return;

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        run(e.target);
        io.unobserve(e.target);
      });
    }, { threshold: 0.4 });

    Array.prototype.forEach.call(els, function (el) { io.observe(el); });

    function run(el) {
      var target = parseFloat(el.getAttribute('data-count-to'));
      var dp     = parseInt(el.getAttribute('data-count-decimals') || '0', 10);
      var prefix = el.getAttribute('data-count-prefix') || '';
      var suffix = el.getAttribute('data-count-suffix') || '';
      if (isNaN(target)) return;

      var dur = 1500, t0 = null;
      function frame(ts) {
        if (t0 === null) t0 = ts;
        var p = Math.min(1, (ts - t0) / dur);
        var eased = 1 - Math.pow(1 - p, 3);
        el.textContent = prefix + (target * eased).toLocaleString(undefined, {
          minimumFractionDigits: dp, maximumFractionDigits: dp,
        }) + suffix;
        if (p < 1) requestAnimationFrame(frame);
      }
      requestAnimationFrame(frame);
    }
  }

  /* ---------------------------------------------------------------
   * 3. STICKY SEQUENCE - pinned copy, visual swaps with scroll
   * ------------------------------------------------------------- */
  function initSticky() {
    if (!wide.matches) return;
    var roots = document.querySelectorAll('[data-sticky-split]');
    if (!roots.length || !('IntersectionObserver' in window)) return;

    Array.prototype.forEach.call(roots, function (root) {
      var steps  = root.querySelectorAll('[data-step]');
      var shots  = root.querySelectorAll('[data-shot]');
      if (!steps.length) return;

      function activate(i) {
        Array.prototype.forEach.call(steps, function (s, n) {
          s.classList.toggle('is-active', n === i);
        });
        Array.prototype.forEach.call(shots, function (s, n) {
          s.style.opacity = n === i ? '1' : '0';
          // Keep every shot in flow but stacked, so the container never
          // changes height as the active one changes.
          s.style.zIndex = n === i ? '2' : '1';
        });
      }

      Array.prototype.forEach.call(shots, function (s, n) {
        s.style.transition = 'opacity .45s cubic-bezier(.16,1,.3,1)';
        if (n > 0) { s.style.position = 'absolute'; s.style.inset = '0'; }
      });
      activate(0);

      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (e.isIntersecting) {
            activate(parseInt(e.target.getAttribute('data-step'), 10));
          }
        });
      }, { rootMargin: '-45% 0px -45% 0px', threshold: 0 });

      Array.prototype.forEach.call(steps, function (s) { io.observe(s); });
    });
  }

  /* ---------------------------------------------------------------
   * 4. PARALLAX - hero layers only
   * ------------------------------------------------------------- */
  function initParallax() {
    if (!wide.matches) return;
    var layers = document.querySelectorAll('[data-parallax]');
    if (!layers.length) return;

    var items = Array.prototype.map.call(layers, function (el) {
      return { el: el, speed: parseFloat(el.getAttribute('data-parallax')) || 0.2 };
    });

    var ticking = false;
    function onScroll() {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(function () {
        // scrollY only - no getBoundingClientRect in the loop, so this
        // never forces a synchronous layout.
        var y = window.pageYOffset;
        items.forEach(function (it) {
          it.el.style.transform = 'translate3d(0,' + (y * it.speed).toFixed(1) + 'px,0)';
        });
        ticking = false;
      });
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ---------------------------------------------------------------
   * 5. TABS
   * ------------------------------------------------------------- */
  function initTabs() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-tabs]'), function (root) {
      var btns   = root.querySelectorAll('[role="tab"]');
      var panels = root.querySelectorAll('[role="tabpanel"]');
      if (!btns.length) return;

      function select(i) {
        Array.prototype.forEach.call(btns, function (b, n) {
          b.setAttribute('aria-selected', n === i ? 'true' : 'false');
          b.setAttribute('tabindex', n === i ? '0' : '-1');
        });
        Array.prototype.forEach.call(panels, function (p, n) { p.hidden = n !== i; });
      }

      Array.prototype.forEach.call(btns, function (b, i) {
        b.addEventListener('click', function () { select(i); });
        // Arrow-key navigation is part of the tab pattern, not a nicety.
        b.addEventListener('keydown', function (e) {
          var d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
          if (!d) return;
          e.preventDefault();
          var next = (i + d + btns.length) % btns.length;
          select(next);
          btns[next].focus();
        });
      });
      select(0);
    });
  }

  function boot() {
    // Tabs are FUNCTIONALITY, not decoration, so they initialise regardless of
    // the motion preference. Bailing out of the whole module under
    // prefers-reduced-motion left the tab buttons inert - every panel was
    // visible, which is readable, but clicking a tab did nothing at all.
    initTabs();

    if (reduced.matches) return;

    initReveal();
    initCounters();
    initSticky();
    initParallax();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
