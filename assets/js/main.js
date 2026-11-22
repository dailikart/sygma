/* Sygma Solar — landing page interactions (no dependencies) */
(function () {
  'use strict';

  document.documentElement.classList.add('js');
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Sticky header shadow ---------- */
  var header = document.querySelector('.site-header');
  function onScroll() { header.classList.toggle('scrolled', window.scrollY > 8); }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---------- Mobile navigation ---------- */
  var toggle = document.getElementById('nav-toggle');
  var nav = document.getElementById('main-nav');
  function setNav(open) {
    nav.classList.toggle('open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  }
  toggle.addEventListener('click', function () { setNav(!nav.classList.contains('open')); });
  nav.addEventListener('click', function (e) { if (e.target.closest('a')) setNav(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setNav(false); });

  /* ---------- Animated counters ---------- */
  function formatNum(n, decimals) {
    return n.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
  }
  function animateCount(el) {
    var target = parseFloat(el.dataset.count);
    var decimals = parseInt(el.dataset.decimals || '0', 10);
    var suffix = el.dataset.suffix || '';
    if (reduceMotion) { el.textContent = formatNum(target, decimals) + suffix; return; }
    var start = null, duration = 1600;
    function step(ts) {
      if (!start) start = ts;
      var p = Math.min((ts - start) / duration, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = formatNum(target * eased, decimals) + suffix;
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  /* ---------- Reveal on scroll + counters ---------- */
  var revealEls = document.querySelectorAll('.reveal');
  var countEls = document.querySelectorAll('[data-count]');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        if (el.hasAttribute('data-count')) animateCount(el);
        else el.classList.add('in');
        io.unobserve(el);
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
    revealEls.forEach(function (el, i) {
      el.style.transitionDelay = (i % 4) * 80 + 'ms';
      io.observe(el);
    });
    countEls.forEach(function (el) { io.observe(el); });
  } else {
    revealEls.forEach(function (el) { el.classList.add('in'); });
    countEls.forEach(animateCount);
  }

  /* ---------- Module tabs (accessible, arrow-key navigation) ---------- */
  var tabs = Array.prototype.slice.call(document.querySelectorAll('[role="tab"]'));
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
  var heatmap = document.getElementById('heatmap');
  if (heatmap) {
    var seed = 7;
    var rand = function () { seed = (seed * 9301 + 49297) % 233280; return seed / 233280; };
    var frag = document.createDocumentFragment();
    for (var c = 0; c < 72; c++) {
      var v = rand();
      var cell = document.createElement('i');
      var color = v > 0.97 ? '#ef4444' : v > 0.9 ? '#f59e0b' : v > 0.45 ? '#10b981' : '#34d399';
      cell.style.background = color;
      cell.style.opacity = (0.65 + rand() * 0.35).toFixed(2);
      frag.appendChild(cell);
    }
    heatmap.appendChild(frag);
  }

  /* ---------- Pricing toggle ---------- */
  var sw = document.getElementById('billing-switch');
  var labels = document.querySelectorAll('.bt-label');
  function setBilling(yearly) {
    sw.setAttribute('aria-checked', String(yearly));
    labels.forEach(function (l) { l.classList.toggle('active', (l.dataset.period === 'yearly') === yearly); });
    document.querySelectorAll('.amt[data-monthly]').forEach(function (el) {
      el.textContent = yearly ? el.dataset.yearly : el.dataset.monthly;
    });
  }
  sw.addEventListener('click', function () { setBilling(sw.getAttribute('aria-checked') !== 'true'); });
  labels.forEach(function (l) {
    l.addEventListener('click', function () { setBilling(l.dataset.period === 'yearly'); });
  });

  /* ---------- Demo form validation ---------- */
  var form = document.getElementById('demo-form');
  var msg = document.getElementById('form-msg');
  var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var firstBad = null;
    Array.prototype.forEach.call(form.elements, function (field) {
      if (!field.name) return;
      var value = field.value.trim();
      var bad = (field.required && !value) || (field.type === 'email' && value && !emailRe.test(value));
      field.classList.toggle('invalid', bad);
      field.setAttribute('aria-invalid', String(bad));
      if (bad && !firstBad) firstBad = field;
    });
    if (firstBad) {
      msg.className = 'form-msg err';
      msg.textContent = 'Please fill in the highlighted fields.';
      firstBad.focus();
      return;
    }
    // TODO: connect to your backend / CRM endpoint here (e.g. fetch('/api/demo', {method:'POST', body:new FormData(form)})).
    msg.className = 'form-msg ok';
    msg.textContent = 'Thank you! Our team will contact you within one business day.';
    form.reset();
  });
  form.addEventListener('input', function (e) {
    if (e.target.classList.contains('invalid') && e.target.value.trim()) {
      e.target.classList.remove('invalid');
      e.target.setAttribute('aria-invalid', 'false');
    }
  });

  /* ---------- Footer year ---------- */
  var year = document.getElementById('year');
  if (year) year.textContent = new Date().getFullYear();
})();
