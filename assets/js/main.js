/* Sygma Solar — landing page interactions (no dependencies) */
(function () {
  'use strict';

  document.documentElement.classList.add('js');
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  function store(key, val) {
    try { if (val === undefined) return localStorage.getItem(key); localStorage.setItem(key, val); } catch (e) { return null; }
  }

  /* ---------- Announcement bar ---------- */
  var announce = $('#announce');
  if (announce) {
    if (store('sygma-announce') === 'hidden') announce.classList.add('hidden');
    $('.announce-close', announce).addEventListener('click', function () {
      announce.classList.add('hidden');
      store('sygma-announce', 'hidden');
    });
  }

  /* ---------- Sticky header + mobile CTA ---------- */
  var header = $('.site-header');
  var mobileCta = $('#mobile-cta');
  var demo = $('#demo');
  function onScroll() {
    var y = window.scrollY;
    header.classList.toggle('scrolled', y > 8);
    if (mobileCta) {
      var demoTop = demo.getBoundingClientRect().top;
      mobileCta.classList.toggle('show', y > 700 && demoTop > window.innerHeight);
    }
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---------- Navigation (mobile menu + mega dropdowns) ---------- */
  var toggle = $('#nav-toggle');
  var nav = $('#main-nav');
  var mqMobile = window.matchMedia('(max-width: 980px)');
  function closeDropdowns(except) {
    $$('.nav-item.open').forEach(function (item) {
      if (item === except) return;
      item.classList.remove('open');
      $('.nav-link', item).setAttribute('aria-expanded', 'false');
    });
  }
  function setNav(open) {
    nav.classList.toggle('open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    if (!open) closeDropdowns();
  }
  toggle.addEventListener('click', function () { setNav(!nav.classList.contains('open')); });
  $$('.has-mega > .nav-link').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var item = btn.parentElement;
      var open = !item.classList.contains('open');
      closeDropdowns(item);
      item.classList.toggle('open', open);
      btn.setAttribute('aria-expanded', String(open));
    });
  });
  nav.addEventListener('click', function (e) {
    if (e.target.closest('a')) { setNav(false); if (document.activeElement) document.activeElement.blur(); }
  });
  document.addEventListener('click', function (e) { if (!e.target.closest('.nav-item')) closeDropdowns(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setNav(false); });
  mqMobile.addEventListener('change', function () { setNav(false); });

  /* ---------- Number formatting ---------- */
  function fmt(n, decimals) {
    return n.toLocaleString('en-IN', { minimumFractionDigits: decimals || 0, maximumFractionDigits: decimals || 0 });
  }
  function inr(n) {
    if (n >= 1e7) return '₹' + (n / 1e7).toFixed(2) + ' Cr';
    if (n >= 1e5) return '₹' + (n / 1e5).toFixed(2) + ' L';
    return '₹' + fmt(Math.round(n));
  }

  /* ---------- Animated counters ---------- */
  function animateCount(el) {
    var target = parseFloat(el.dataset.count);
    var decimals = parseInt(el.dataset.decimals || '0', 10);
    var suffix = el.dataset.suffix || '';
    if (reduceMotion) { el.textContent = fmt(target, decimals) + suffix; return; }
    var start = null, duration = 1600;
    function step(ts) {
      if (!start) start = ts;
      var p = Math.min((ts - start) / duration, 1);
      el.textContent = fmt(target * (1 - Math.pow(1 - p, 3)), decimals) + suffix;
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  /* ---------- Rooftop panel layout (generated, animated on view) ---------- */
  var panelGroup = $('.roof .panels');
  var NS = 'http://www.w3.org/2000/svg';
  if (panelGroup) {
    // 8 columns x 4 rows, skipping cells that overlap the water tank + its shadow.
    for (var r = 0; r < 4; r++) {
      for (var c = 0; c < 8; c++) {
        var x = 78 + c * 38, y = 58 + r * 26;
        if (x + 34 > 322 && y < 124) continue;
        var rect = document.createElementNS(NS, 'rect');
        rect.setAttribute('x', x); rect.setAttribute('y', y);
        rect.setAttribute('width', 34); rect.setAttribute('height', 22); rect.setAttribute('rx', 2);
        panelGroup.appendChild(rect);
      }
    }
  }
  function placePanels() {
    $$('rect', panelGroup).forEach(function (p, i) {
      setTimeout(function () { p.classList.add('on'); }, reduceMotion ? 0 : 300 + i * 55);
    });
  }

  /* ---------- Intersection observer: reveal, counters, panels ---------- */
  var revealEls = $$('.reveal');
  var countEls = $$('[data-count]');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        if (el.hasAttribute('data-count')) animateCount(el);
        else if (el.classList.contains('roof')) placePanels();
        else el.classList.add('in');
        io.unobserve(el);
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
    revealEls.forEach(function (el, i) { el.style.transitionDelay = (i % 4) * 70 + 'ms'; io.observe(el); });
    countEls.forEach(function (el) { io.observe(el); });
    if (panelGroup) io.observe($('.roof'));
  } else {
    revealEls.forEach(function (el) { el.classList.add('in'); });
    countEls.forEach(animateCount);
    if (panelGroup) placePanels();
  }

  /* ---------- Tabs (accessible, arrow-key navigation) ---------- */
  var tabs = $$('[role="tab"]');
  function activate(tab, focus) {
    tabs.forEach(function (t) {
      var selected = t === tab;
      t.classList.toggle('active', selected);
      t.setAttribute('aria-selected', String(selected));
      t.tabIndex = selected ? 0 : -1;
      var panel = document.getElementById(t.getAttribute('aria-controls'));
      panel.hidden = !selected;
      panel.classList.toggle('active', selected);
    });
    if (focus) tab.focus();
  }
  tabs.forEach(function (tab, i) {
    tab.addEventListener('click', function () { activate(tab); });
    tab.addEventListener('keydown', function (e) {
      var next = null;
      if (e.key === 'ArrowRight') next = tabs[(i + 1) % tabs.length];
      else if (e.key === 'ArrowLeft') next = tabs[(i - 1 + tabs.length) % tabs.length];
      else if (e.key === 'Home') next = tabs[0];
      else if (e.key === 'End') next = tabs[tabs.length - 1];
      if (next) { e.preventDefault(); activate(next, true); }
    });
  });

  /* ---------- Inverter heatmap (deterministic pseudo-random) ---------- */
  var heatmap = $('#heatmap');
  if (heatmap) {
    var seed = 7;
    var rand = function () { seed = (seed * 9301 + 49297) % 233280; return seed / 233280; };
    var frag = document.createDocumentFragment();
    for (var h = 0; h < 72; h++) {
      var v = rand();
      var cell = document.createElement('i');
      cell.style.background = v > 0.97 ? '#ef4444' : v > 0.9 ? '#f59e0b' : v > 0.45 ? '#10b981' : '#34d399';
      cell.style.opacity = (0.65 + rand() * 0.35).toFixed(2);
      frag.appendChild(cell);
    }
    heatmap.appendChild(frag);
  }

  /* ---------- Solar savings calculator ---------- */
  var calc = $('#calc');
  if (calc) {
    var inputs = {
      bill: $('#c-bill'), tariff: $('#c-tariff'), cost: $('#c-cost'), sun: $('#c-sun'), sub: $('#c-sub')
    };
    var out = function (id, text) { $('#' + id).textContent = text; };

    // Indicative central residential subsidy: ₹30k/kW for first 2 kW, ₹18k for the 3rd kW, capped at ₹78k.
    function subsidy(kw) {
      if (kw <= 2) return kw * 30000;
      return Math.min(60000 + (Math.min(kw, 3) - 2) * 18000, 78000);
    }
    function fillRange(el) {
      var p = (el.value - el.min) / (el.max - el.min) * 100;
      el.style.setProperty('--p', p + '%');
    }
    function update() {
      var bill = +inputs.bill.value, tariff = +inputs.tariff.value, costKw = +inputs.cost.value, sun = +inputs.sun.value;
      [inputs.bill, inputs.tariff, inputs.cost, inputs.sun].forEach(fillRange);

      out('o-bill', '₹' + fmt(bill));
      out('o-tariff', '₹' + tariff.toFixed(1) + ' / unit');
      out('o-cost', '₹' + fmt(costKw) + ' / kW');
      out('o-sun', sun.toFixed(1) + ' units / kW / day');

      var monthlyUnits = bill / tariff;
      var size = Math.max(1, Math.round(monthlyUnits / (sun * 30) * 10) / 10);
      var gross = size * costKw;
      var net = Math.max(0, gross - (inputs.sub.checked ? subsidy(size) : 0));
      var annualGen = size * sun * 365;
      var annualSave = Math.min(annualGen * tariff, bill * 12);
      var payback = annualSave > 0 ? net / annualSave : 0;

      // 25-year savings with 0.5%/yr panel degradation and 3%/yr tariff escalation.
      var life = 0;
      for (var yr = 0; yr < 25; yr++) life += annualSave * Math.pow(0.995, yr) * Math.pow(1.03, yr);

      var co2 = annualGen * 0.82 / 1000; // tonnes, grid emission factor ~0.82 kg/kWh
      out('r-size', fmt(size, 1) + ' kW');
      out('r-cost', inr(net));
      out('r-save', inr(annualSave));
      out('r-pay', payback.toFixed(1) + ' yrs');
      out('r-life', inr(life));
      out('r-co2', co2.toFixed(1) + ' t');
      out('r-trees', fmt(Math.round(co2 * 1000 / 22)));
    }
    calc.addEventListener('input', update);
    update();
  }

  /* ---------- Testimonial carousel ---------- */
  var car = $('#carousel');
  if (car) {
    var stepBy = function (dir) {
      var card = $('.testi', car);
      var gap = parseFloat(getComputedStyle(car).columnGap) || 24;
      var w = card.getBoundingClientRect().width + gap;
      var max = car.scrollWidth - car.clientWidth - 2;
      if (dir > 0 && car.scrollLeft >= max) car.scrollTo({ left: 0 });
      else if (dir < 0 && car.scrollLeft <= 2) car.scrollTo({ left: max });
      else car.scrollBy({ left: dir * w });
    };
    $('#car-next').addEventListener('click', function () { stepBy(1); });
    $('#car-prev').addEventListener('click', function () { stepBy(-1); });
    car.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight') { e.preventDefault(); stepBy(1); }
      if (e.key === 'ArrowLeft') { e.preventDefault(); stepBy(-1); }
    });
  }

  /* ---------- Video modal ---------- */
  var modal = $('#video-modal');
  var frame = $('#video-frame');
  if (modal && typeof modal.showModal === 'function') {
    var placeholder = frame.innerHTML;
    $('#video-open').addEventListener('click', function () {
      var src = frame.dataset.src;
      if (src) {
        var iframe = document.createElement('iframe');
        iframe.src = src + (src.indexOf('?') > -1 ? '&' : '?') + 'autoplay=1';
        iframe.allow = 'autoplay; encrypted-media; picture-in-picture';
        iframe.allowFullscreen = true;
        iframe.title = 'Sygma Solar product tour';
        frame.innerHTML = '';
        frame.appendChild(iframe);
      }
      modal.showModal();
    });
    var closeModal = function () { modal.close(); };
    $('#video-close').addEventListener('click', closeModal);
    $('#video-demo').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    modal.addEventListener('close', function () { frame.innerHTML = placeholder; var d = $('#video-demo'); if (d) d.addEventListener('click', closeModal); });
  } else if (modal) {
    $('#video-open').addEventListener('click', function () { location.hash = '#demo'; });
  }

  /* ---------- Pricing toggle ---------- */
  var sw = $('#billing-switch');
  var labels = $$('.bt-label');
  function setBilling(yearly) {
    sw.setAttribute('aria-checked', String(yearly));
    labels.forEach(function (l) { l.classList.toggle('active', (l.dataset.period === 'yearly') === yearly); });
    $$('.amt[data-monthly]').forEach(function (el) { el.textContent = yearly ? el.dataset.yearly : el.dataset.monthly; });
  }
  sw.addEventListener('click', function () { setBilling(sw.getAttribute('aria-checked') !== 'true'); });
  labels.forEach(function (l) { l.addEventListener('click', function () { setBilling(l.dataset.period === 'yearly'); }); });

  /* ---------- Form helpers ---------- */
  var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  var phoneRe = /^[+\d][\d\s-]{7,15}$/;
  function invalid(field) {
    var v = field.value.trim();
    if (field.required && !v) return true;
    if (field.type === 'email' && v && !emailRe.test(v)) return true;
    if (field.type === 'tel' && v && !phoneRe.test(v)) return true;
    return false;
  }
  function validate(form) {
    var first = null;
    $$('input[name], select[name], input[type="email"]', form).forEach(function (f) {
      var bad = invalid(f);
      f.classList.toggle('invalid', bad);
      f.setAttribute('aria-invalid', String(bad));
      if (bad && !first) first = f;
    });
    if (first) first.focus();
    return !first;
  }
  document.addEventListener('input', function (e) {
    var f = e.target;
    if (f.classList && f.classList.contains('invalid') && !invalid(f)) {
      f.classList.remove('invalid');
      f.setAttribute('aria-invalid', 'false');
    }
  });
  function say(el, cls, text) { el.className = el.className.replace(/\s?(ok|err)\b/g, '') + ' ' + cls; el.textContent = text; }

  /* Hero quick form → prefill and jump to the full demo form */
  var heroForm = $('#hero-form');
  var demoForm = $('#demo-form');
  heroForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var msg = $('#hero-form-msg');
    if (!validate(heroForm)) { say(msg, 'err', 'Please enter a valid mobile number and email.'); return; }
    say(msg, 'ok', 'Great! Just a few more details below.');
    demoForm.elements.phone.value = heroForm.elements.phone.value.trim();
    demoForm.elements.email.value = heroForm.elements.email.value.trim();
    demo.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth' });
    setTimeout(function () { demoForm.elements.name.focus({ preventScroll: true }); }, reduceMotion ? 0 : 700);
  });

  /* Demo form */
  demoForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var msg = $('#form-msg');
    if (!validate(demoForm)) { say(msg, 'err', 'Please fill in the highlighted fields.'); return; }
    // TODO: send to your backend / CRM, e.g.:
    // fetch('/demo/request', { method: 'POST', body: new FormData(demoForm) })
    say(msg, 'ok', 'Thank you! Our team will call you within one business day.');
    demoForm.reset();
    heroForm.reset();
  });

  /* Newsletter */
  var nl = $('#newsletter');
  nl.addEventListener('submit', function (e) {
    e.preventDefault();
    var msg = $('#nl-msg');
    if (!validate(nl)) { msg.textContent = 'Please enter a valid email address.'; return; }
    // TODO: connect to your email marketing tool.
    msg.textContent = 'Subscribed! Look out for our next issue.';
    nl.reset();
  });

  /* ---------- Footer year ---------- */
  var year = $('#year');
  if (year) year.textContent = new Date().getFullYear();
})();
