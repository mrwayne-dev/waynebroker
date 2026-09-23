/* =====================================================================
 * FILE: /assets/js/translate.js
 * Maveren language switcher - the MODAL only.
 *
 * The translating itself happens server-side in api/utilities/i18n.php.
 * This file picks a language and posts it to api/public/set_language.php,
 * which sets a cookie and redirects back; the next render comes back
 * translated. No third-party script runs in the browser, which is what
 * lets the strict CSP keep its inline-script hashes.
 *
 * Google's Website Translator widget was the first approach and was
 * abandoned: it is retired. It still loads and still builds its <select>,
 * and leaves it permanently empty - reproduced with Google's own
 * documented snippet on a bare page with no CSP.
 *
 * Progressive enhancement: the trigger is a <form> submit button, so with
 * JavaScript off the language list is still reachable and still works.
 * ===================================================================== */
(function () {
  'use strict';

  // The set Google's element.js supports. Deliberately NOT grouped by
  // continent: a language is not a place, and every attempt to bucket
  // Spanish, French or Arabic geographically is wrong for most of the people
  // who speak it. Suggested comes from the reader's own browser, Common is
  // ordered by number of speakers, and the rest is one A-Z list that the
  // search box makes short.
  var COMMON = ['en','zh-CN','hi','es','fr','ar','bn','pt','ru','ur','id','de','ja','sw','ko'];

  var ALL = [
    ['af','Afrikaans'],['sq','Albanian'],['am','Amharic'],['ar','Arabic'],['hy','Armenian'],
    ['as','Assamese'],['ay','Aymara'],['az','Azerbaijani'],['bm','Bambara'],['eu','Basque'],
    ['be','Belarusian'],['bn','Bengali'],['bho','Bhojpuri'],['bs','Bosnian'],['bg','Bulgarian'],
    ['my','Burmese'],['ca','Catalan'],['ceb','Cebuano'],['zh-CN','Chinese (Simplified)'],
    ['zh-TW','Chinese (Traditional)'],['co','Corsican'],['hr','Croatian'],['cs','Czech'],
    ['da','Danish'],['dv','Dhivehi'],['doi','Dogri'],['nl','Dutch'],['en','English'],
    ['eo','Esperanto'],['et','Estonian'],['ee','Ewe'],['fil','Filipino'],['fi','Finnish'],
    ['fr','French'],['fy','Frisian'],['gl','Galician'],['ka','Georgian'],['de','German'],
    ['el','Greek'],['gn','Guarani'],['gu','Gujarati'],['ht','Haitian Creole'],['ha','Hausa'],
    ['haw','Hawaiian'],['he','Hebrew'],['hi','Hindi'],['hmn','Hmong'],['hu','Hungarian'],
    ['is','Icelandic'],['ig','Igbo'],['ilo','Ilocano'],['id','Indonesian'],['ga','Irish'],
    ['it','Italian'],['ja','Japanese'],['jw','Javanese'],['kn','Kannada'],['kk','Kazakh'],
    ['km','Khmer'],['rw','Kinyarwanda'],['gom','Konkani'],['ko','Korean'],
    ['kri','Krio'],['ku','Kurdish (Kurmanji)'],['ckb','Kurdish (Sorani)'],['ky','Kyrgyz'],
    ['lo','Lao'],['la','Latin'],['lv','Latvian'],['ln','Lingala'],['lt','Lithuanian'],
    ['lg','Luganda'],['lb','Luxembourgish'],['mk','Macedonian'],['mai','Maithili'],
    ['mg','Malagasy'],['ms','Malay'],['ml','Malayalam'],['mt','Maltese'],['mi','Maori'],
    ['mr','Marathi'],['mni-Mtei','Meiteilon (Manipuri)'],['lus','Mizo'],['mn','Mongolian'],
    ['ne','Nepali'],['no','Norwegian'],['ny','Nyanja (Chichewa)'],['or','Odia (Oriya)'],
    ['om','Oromo'],['ps','Pashto'],['fa','Persian'],['pl','Polish'],['pt','Portuguese'],
    ['pa','Punjabi'],['qu','Quechua'],['ro','Romanian'],['ru','Russian'],['sm','Samoan'],
    ['sa','Sanskrit'],['gd','Scots Gaelic'],['nso','Sepedi'],['sr','Serbian'],['st','Sesotho'],
    ['sn','Shona'],['sd','Sindhi'],['si','Sinhala'],['sk','Slovak'],['sl','Slovenian'],
    ['so','Somali'],['es','Spanish'],['su','Sundanese'],['sw','Swahili'],['sv','Swedish'],
    ['tl','Tagalog'],['tg','Tajik'],['ta','Tamil'],['tt','Tatar'],['te','Telugu'],
    ['th','Thai'],['ti','Tigrinya'],['ts','Tsonga'],['tr','Turkish'],['tk','Turkmen'],
    ['ak','Twi'],['uk','Ukrainian'],['ur','Urdu'],['ug','Uyghur'],['uz','Uzbek'],
    ['vi','Vietnamese'],['cy','Welsh'],['xh','Xhosa'],['yi','Yiddish'],['yo','Yoruba'],
    ['zu','Zulu'],
  ];

  var FLAT = {};
  ALL.forEach(function (l) { FLAT[l[0]] = l[1]; });
  var COUNT = ALL.length;

  function readCookie(name) {
    var m = document.cookie.match(new RegExp('(?:^|;\\s*)' + name + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : '';
  }

  var current = readCookie('mvc_lang') || 'en';

  /**
   * Hand the choice to the server. A real form POST rather than fetch(), so
   * the CSRF token travels the same way every other write on the site does
   * and the browser performs the redirect itself.
   */
  function applyLanguage(lang) {
    if (lang === current) { close(); return; }
    var f = document.createElement('form');
    f.method = 'POST';
    f.action = '/api/public/set_language.php';
    f.style.display = 'none';

    function hidden(n, v) {
      var i = document.createElement('input');
      i.type = 'hidden'; i.name = n; i.value = v;
      f.appendChild(i);
    }
    hidden('lang', lang);
    hidden('return', location.pathname + location.search);

    var tok = document.querySelector('meta[name="csrf-token"]');
    if (tok) hidden('csrf_token', tok.getAttribute('content'));

    document.body.appendChild(f);
    f.submit();
  }

  /* --- Modal ------------------------------------------------------- */
  var modal, listEl, searchEl, lastFocus;

  function suggested() {
    var out = [], seen = {};
    (navigator.languages || [navigator.language || 'en']).forEach(function (tag) {
      if (!tag) return;
      var exact = Object.keys(FLAT).filter(function (c) { return c.toLowerCase() === tag.toLowerCase(); })[0];
      var base  = tag.split('-')[0].toLowerCase();
      var code  = exact || (FLAT[base] ? base : null);
      if (code && !seen[code]) { seen[code] = 1; out.push([code, FLAT[code]]); }
    });
    if (!seen.en) out.push(['en', 'English']);
    return out;
  }

  function build() {
    if (modal) return modal;

    modal = document.createElement('div');
    modal.className = 'mvc-lang';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-label', 'Choose a language');
    modal.hidden = true;
    modal.innerHTML =
      '<div class="mvc-lang__overlay" data-lang-close></div>' +
      '<div class="mvc-lang__panel">' +
        '<div class="mvc-lang__head">' +
          '<div>' +
            '<div class="mvc-lang__title">Choose a language</div>' +
            '<div class="mvc-lang__sub">Translations are machine generated.</div>' +
          '</div>' +
          '<button type="button" class="mvc-lang__close" data-lang-close aria-label="Close">' +
            '<i class="ph ph-x" aria-hidden="true"></i></button>' +
        '</div>' +
        '<div class="mvc-lang__searchwrap">' +
          '<i class="ph ph-magnifying-glass" aria-hidden="true"></i>' +
          '<input type="search" class="mvc-lang__search" placeholder="Search ' + COUNT + ' languages" ' +
            'aria-label="Search languages" autocomplete="off">' +
        '</div>' +
        '<div class="mvc-lang__list" role="listbox" tabindex="-1"></div>' +
      '</div>';
    document.body.appendChild(modal);

    listEl = modal.querySelector('.mvc-lang__list');
    searchEl = modal.querySelector('.mvc-lang__search');

    modal.addEventListener('click', function (e) {
      if (e.target.closest('[data-lang-close]')) { close(); return; }
      var opt = e.target.closest('[data-lang]');
      if (opt) { applyLanguage(opt.getAttribute('data-lang')); close(); }
    });
    searchEl.addEventListener('input', function () { render(searchEl.value); });
    modal.addEventListener('keydown', onKeydown);
    return modal;
  }

  function render(q) {
    q = (q || '').trim().toLowerCase();
    var groups = [
      ['Suggested', suggested()],
      ['Common', COMMON.map(function (c) { return [c, FLAT[c]]; }).filter(function (l) { return l[1]; })],
      ['All languages', ALL],
    ];

    var html = '';
    groups.forEach(function (g) {
      var items = g[1].filter(function (l) {
        return !q || l[1].toLowerCase().indexOf(q) > -1 || l[0].toLowerCase().indexOf(q) === 0;
      });
      if (!items.length) return;
      html += '<div class="mvc-lang__group">' + g[0] + '</div><div class="mvc-lang__grid">';
      items.forEach(function (l) {
        var on = l[0] === current;
        html += '<button type="button" class="mvc-lang__opt' + (on ? ' is-current' : '') + '" ' +
          'data-lang="' + l[0] + '" role="option" aria-selected="' + on + '">' +
          '<span class="mvc-lang__name">' + l[1] + '</span>' +
          '<span class="mvc-lang__code">' + l[0] + '</span>' +
          (on ? '<i class="ph ph-check" aria-hidden="true"></i>' : '') +
          '</button>';
      });
      html += '</div>';
    });

    listEl.innerHTML = html || '<div class="mvc-lang__none">No language matches that.</div>';
  }

  function onKeydown(e) {
    if (e.key === 'Escape') { close(); return; }
    if (e.key !== 'Tab') return;
    // Focus trap: the dialog is modal, so Tab must not escape it.
    var f = modal.querySelectorAll('button, input, [tabindex]:not([tabindex="-1"])');
    if (!f.length) return;
    var first = f[0], last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  }

  function open() {
    build();
    lastFocus = document.activeElement;
    render('');
    modal.hidden = false;
    document.body.classList.add('mvc-lang-open');
    searchEl.value = '';
    setTimeout(function () { searchEl.focus(); }, 30);
  }

  function close() {
    if (!modal) return;
    modal.hidden = true;
    document.body.classList.remove('mvc-lang-open');
    lastFocus && lastFocus.focus && lastFocus.focus();
  }

  /* --- Triggers ---------------------------------------------------- */
  function syncTriggers() {
    var label = FLAT[current] || 'English';
    Array.prototype.forEach.call(document.querySelectorAll('[data-lang-open]'), function (b) {
      var chip = b.querySelector('.mvc-lang-trigger__code');
      if (chip) chip.textContent = current.split('-')[0].toUpperCase();
      b.setAttribute('aria-label', 'Change language, currently ' + label);
      b.setAttribute('title', 'Language: ' + label);
    });
  }

  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-lang-open]')) { e.preventDefault(); open(); }
  });

  // The server already set <html lang> and dir on a translated response, so
  // there is nothing to fix up here - only the trigger label.
  document.addEventListener('DOMContentLoaded', syncTriggers);
  syncTriggers();

  window.mvcTranslate = { open: open, close: close, apply: applyLanguage, current: function () { return current; } };
})();
