// =============================
// main.js - Maveren Capital
// =============================

document.addEventListener('DOMContentLoaded', () => {

  /**
   * ======================
   * 1. Navbar Toggle (Mobile)
   * ======================
   */
  const navToggler = document.querySelector('[data-nav-toggler]');
  const navbar = document.querySelector('[data-navbar]');
  const body = document.body;

  // Must match the @media (max-width: 768px) breakpoint in mvc-design.css.
  const NAV_BREAKPOINT = 768;

  if (navToggler && navbar) {
    const setNavOpen = (open) => {
      navbar.classList.toggle('navbar--open', open);
      body.classList.toggle('has-nav-open', open);
      navToggler.setAttribute('aria-expanded', String(open));
      navToggler.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    };

    const isNavOpen = () => navbar.classList.contains('navbar--open');

    navToggler.addEventListener('click', () => setNavOpen(!isNavOpen()));

    // Follow a link, then let the new page render with the menu closed.
    navbar.querySelectorAll('.navbar__links a').forEach((link) => {
      link.addEventListener('click', () => setNavOpen(false));
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && isNavOpen()) {
        setNavOpen(false);
        navToggler.focus();
      }
    });

    document.addEventListener('click', (e) => {
      if (isNavOpen() && !navbar.contains(e.target)) setNavOpen(false);
    });

    // The overlay only exists below the breakpoint; leaving it "open" past
    // that point would strand the scroll lock.
    window.addEventListener('resize', () => {
      if (window.innerWidth > NAV_BREAKPOINT && isNavOpen()) setNavOpen(false);
    });
  }


  /**
   * ======================
   * 2. Header Scroll Effect
   * ======================
   */

  // Legacy .header markup, still used by non-redesign pages.
  const header = document.querySelector('.header');
  if (header) {
    window.addEventListener('scroll', () => {
      header.classList.toggle('scrolled', window.scrollY > 10);
    });
  }

  // Redesign nav: swap the hero scrim for a solid bar once past the fold.
  // Previously duplicated inline in footer.php and index.php.
  if (navbar && !navbar.classList.contains('navbar--solid')) {
    const onScroll = () => {
      navbar.classList.toggle('is-scrolled', window.scrollY > 80);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }


  /**
   * ======================
   * 3. Interactive Cards
   * ======================
   */
  const interactiveCards = document.querySelectorAll('.platform-card, .testimonial-card');
  interactiveCards.forEach(card => {
    card.addEventListener('mousemove', e => {
      const rect = card.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      card.style.setProperty('--x', `${x}px`);
      card.style.setProperty('--y', `${y}px`);
    });

    card.addEventListener('mouseleave', () => {
      card.style.setProperty('--x', '50%');
      card.style.setProperty('--y', '50%');
    });
  });


  /**
   * ======================
   * 4. FAQ Accordion
   * ======================
   */
  const faqItems = document.querySelectorAll('.faq-item');
  faqItems.forEach(item => {
    const button = item.querySelector('.faq-question');
    const answer = item.querySelector('.faq-answer');
    const icon = button.querySelector('i');

    button.addEventListener('click', () => {
      const isOpen = item.classList.contains('active');

      if (isOpen) {
        item.classList.remove('active');
        answer.style.maxHeight = null;
        button.setAttribute('aria-expanded', 'false');
        icon.classList.replace('uil-minus', 'uil-plus');
      } else {
        item.classList.add('active');
        answer.style.maxHeight = '0px';
        requestAnimationFrame(() => {
          requestAnimationFrame(() => {
            answer.style.maxHeight = answer.scrollHeight + 'px';
          });
        });
        button.setAttribute('aria-expanded', 'true');
        icon.classList.replace('uil-plus', 'uil-minus');
      }
    });
  });


  /**
   * ======================
   * 5. Scroll Animations + CountUp Stats
   * ======================
   */
  const animatedElements = document.querySelectorAll('[data-appear], [data-appear-left], [data-appear-right], [data-appear-stagger]');
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          if (entry.target.hasAttribute('data-appear-stagger')) {
            const parent = entry.target.parentElement;
            const siblings = parent.querySelectorAll('[data-appear-stagger]');
            siblings.forEach((el, i) => {
              setTimeout(() => el.classList.add('appear'), i * 300);
              observer.unobserve(el);
            });
          } else {
            entry.target.classList.add('appear');
            observer.unobserve(entry.target);
          }
        }
      });
    }, { threshold: 0.7 });

    animatedElements.forEach(el => observer.observe(el));
  } else {
    animatedElements.forEach(el => el.classList.add('appear'));
  }

  // Target any element with a data-count (the "Our Numbers" spans use data-count
  // without the .stat-number class, so the old selector matched nothing).
  const statNumbers = document.querySelectorAll('[data-count]');
  function animateCountUp(el, target, duration = 2000) {
    let startTime = null;
    const step = (timestamp) => {
      if (!startTime) startTime = timestamp;
      const progress = Math.min((timestamp - startTime) / duration, 1);
      const value = Math.floor(progress * target);
      el.textContent = value.toLocaleString();
      if (progress < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  }

  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const el = entry.target;
          const target = parseInt(el.getAttribute('data-count'), 10);
          if (!el.classList.contains('counted')) {
            el.classList.add('counted');
            animateCountUp(el, target);
          }
          observer.unobserve(el);
        }
      });
    }, { threshold: 0.6 });
    statNumbers.forEach(el => observer.observe(el));
  }

}); // end DOMContentLoaded block 1



/**
 * ======================
 * 6. Smartsupp Live Chat
 * ======================
 * Moved to assets/js/smartsupp.js, which is loaded by the public and member
 * head partials so the widget appears on the dashboard too - main.js is a
 * marketing-site file and is never loaded there.
 *
 * The copy that lived here also used a stale key (acee1c8f...) and omitted
 * the vendor's command-queue stub. Both fixed in the new file.
 */
