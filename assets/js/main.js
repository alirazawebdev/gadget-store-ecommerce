/* Gadget Store frontend interactions */
(function () {
  'use strict';

  /* ---------- hero slider ---------- */
  var slides = document.querySelectorAll('.hero-slider .slide');
  var dotsWrap = document.getElementById('sliderDots');
  var current = 0, timer = null;

  function show(i) {
    if (!slides.length) return;
    slides[current].classList.remove('active');
    if (dotsWrap) dotsWrap.children[current].classList.remove('active');
    current = (i + slides.length) % slides.length;
    slides[current].classList.add('active');
    if (dotsWrap) dotsWrap.children[current].classList.add('active');
  }
  if (slides.length && dotsWrap) {
    slides.forEach(function (_, i) {
      var b = document.createElement('button');
      b.setAttribute('aria-label', 'Slide ' + (i + 1));
      b.addEventListener('click', function () { show(i); restart(); });
      dotsWrap.appendChild(b);
    });
    show(0);
    timer = setInterval(function () { show(current + 1); }, 5000);
    function restart() { clearInterval(timer); timer = setInterval(function () { show(current + 1); }, 5000); }
  }

  /* ---------- quantity steppers ---------- */
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('.qty-plus, .qty-minus');
    if (!btn) return;
    var input = btn.parentElement.querySelector('input[name="qty"]');
    if (!input) return;
    var v = parseInt(input.value || '1', 10);
    if (btn.classList.contains('qty-plus')) v++;
    else v = Math.max(1, v - 1);
    input.value = v;
  });

  /* ---------- ajax add to cart ---------- */
  document.addEventListener('submit', function (ev) {
    var form = ev.target.closest('.add-cart-form');
    if (!form) return;
    ev.preventDefault();
    var data = new FormData(form);
    var submitter = ev.submitter || document.activeElement;
    var isBuyNow = !!(submitter && submitter.name === 'buy_now');
    fetch(form.action, {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { if (!r.ok) throw new Error('bad response'); return r.json(); })
      .then(function (res) {
        document.querySelectorAll('.cart-count').forEach(function (el) { el.textContent = res.count; });
        if (isBuyNow) {
          window.location.href = form.getAttribute('data-checkout') || 'checkout.php';
          return;
        }
        var btn = form.querySelector('.btn-add');
        if (btn) {
          var old = btn.textContent;
          btn.textContent = 'Added ✓';
          setTimeout(function () { btn.textContent = old; }, 1200);
        }
      })
      .catch(function () {
        /* last resort: normal submit — server redirects back, never raw JSON */
        if (isBuyNow && !form.querySelector('input[name="buy_now"]')) {
          var h = document.createElement('input');
          h.type = 'hidden'; h.name = 'buy_now'; h.value = '1';
          form.appendChild(h);
        }
        form.submit();
      });
  });

  /* ---------- flash sale countdown ---------- */
  document.querySelectorAll('[data-countdown]').forEach(function (el) {
    var end = new Date(el.getAttribute('data-countdown')).getTime();
    function tick() {
      var d = end - Date.now();
      if (d < 0) { el.innerHTML = '<div><b>00</b><small>Expired</small></div>'; return; }
      var days = Math.floor(d / 864e5), hrs = Math.floor(d % 864e5 / 36e5),
          min = Math.floor(d % 36e5 / 6e4), sec = Math.floor(d % 6e4 / 1e3);
      function p(n) { return (n < 10 ? '0' : '') + n; }
      el.innerHTML =
        '<div><b>' + p(days) + '</b><small>Days</small></div>' +
        '<div><b>' + p(hrs) + '</b><small>Hours</small></div>' +
        '<div><b>' + p(min) + '</b><small>Mins</small></div>' +
        '<div><b>' + p(sec) + '</b><small>Secs</small></div>';
    }
    tick(); setInterval(tick, 1000);
  });

  /* ---------- sale popup (once per session) ---------- */
  var popup = document.getElementById('salePopup');
  if (popup && !sessionStorage.getItem('popup_seen')) {
    setTimeout(function () { popup.style.display = 'flex'; }, 1500);
    sessionStorage.setItem('popup_seen', '1');
  }
  var closeBtn = document.getElementById('popupClose');
  if (closeBtn) closeBtn.addEventListener('click', function () { popup.style.display = 'none'; });
  if (popup) popup.addEventListener('click', function (ev) { if (ev.target === popup) popup.style.display = 'none'; });

  /* ---------- mobile nav ---------- */
  var toggle = document.getElementById('menuToggle');
  var nav = document.getElementById('hdNav');
  if (toggle && nav) toggle.addEventListener('click', function () { nav.classList.toggle('open'); });

  /* ---------- header search toggle ---------- */
  var sToggle = document.getElementById('searchToggle');
  var sBar = document.getElementById('hdSearch');
  if (sToggle && sBar) sToggle.addEventListener('click', function () {
    var open = sBar.style.display !== 'none';
    sBar.style.display = open ? 'none' : 'block';
    if (!open) { var inp = sBar.querySelector('input[name="q"]'); if (inp) inp.focus(); }
  });

  /* ---------- product gallery (thumbs + arrows) ---------- */
  var mainImg = document.getElementById('pdMainImg');
  var gThumbs = Array.prototype.slice.call(document.querySelectorAll('.g-thumbs img'));
  var gIdx = 0;
  function galShow(i) {
    if (!gThumbs.length || !mainImg) return;
    gIdx = (i + gThumbs.length) % gThumbs.length;
    mainImg.src = gThumbs[gIdx].src;
    gThumbs.forEach(function (x) { x.classList.remove('active'); });
    gThumbs[gIdx].classList.add('active');
  }
  gThumbs.forEach(function (th, i) {
    th.addEventListener('click', function () { galShow(i); });
  });
  var gPrev = document.getElementById('galPrev');
  var gNext = document.getElementById('galNext');
  if (gPrev) gPrev.addEventListener('click', function () { galShow(gIdx - 1); });
  if (gNext) gNext.addEventListener('click', function () { galShow(gIdx + 1); });

  /* ---------- product tabs ---------- */
  document.querySelectorAll('.shop-tabs .tab-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var wrap = btn.closest('.shop-tabs');
      if (!wrap) return;
      wrap.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
      wrap.querySelectorAll('.tab-panel').forEach(function (p) { p.classList.remove('active'); });
      btn.classList.add('active');
      var panel = wrap.querySelector('#' + btn.getAttribute('data-tab'));
      if (panel) panel.classList.add('active');
    });
  });

  /* ---------- sticky buy bar (product page) ---------- */
  var sbb = document.getElementById('stickyBuybar');
  var buyBox = document.querySelector('.shop-buybox');
  if (sbb && buyBox) {
    document.body.classList.add('has-stickybar');
    function sbbOnScroll() {
      var r = buyBox.getBoundingClientRect();
      sbb.classList.toggle('show', r.bottom < 0);
    }
    sbbOnScroll();
    window.addEventListener('scroll', sbbOnScroll, { passive: true });
  }

  /* ---------- sales pop notifications (real recent orders) ---------- */
  function spEsc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  var spBox = document.getElementById('salesPop');
  var spDisabled = false;
  try { spDisabled = sessionStorage.getItem('spop_off') === '1'; } catch (e) {}
  if (spBox && !spDisabled) {
    var spInterval = parseInt(spBox.getAttribute('data-interval'), 10) || 15;
    var spDuration = parseInt(spBox.getAttribute('data-duration'), 10) || 6;
    var spData = [], spIdx = 0, spTimer = null;
    function spShow() {
      if (!spData.length || spDisabled) return;
      var s = spData[spIdx % spData.length]; spIdx++;
      spBox.querySelector('.sp-img').innerHTML = s.img ? '<img src="' + spEsc(s.img) + '" alt="">' : '';
      spBox.querySelector('.sp-txt').innerHTML =
        '<b>' + spEsc(s.name) + '</b> from ' + spEsc(s.city) +
        ' purchased <b>' + spEsc(s.product) + '</b>' +
        '<div class="sp-time">' + spEsc(s.when) + ' &middot; Verified order &#10003;</div>';
      spBox.classList.add('show');
      setTimeout(function () { spBox.classList.remove('show'); }, spDuration * 1000);
    }
    var spClose = spBox.querySelector('.sp-close');
    if (spClose) spClose.addEventListener('click', function () {
      spDisabled = true;
      spBox.classList.remove('show');
      if (spTimer) clearInterval(spTimer);
      try { sessionStorage.setItem('spop_off', '1'); } catch (e) {}
    });
    fetch(spBox.getAttribute('data-src'), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.length) {
          spData = d;
          setTimeout(spShow, 4000);
          spTimer = setInterval(spShow, spInterval * 1000);
        }
      })
      .catch(function () { /* silent */ });
  }

  /* ---------- copy buttons (order numbers etc.) ---------- */
  document.querySelectorAll('.copy-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var txt = btn.getAttribute('data-copy') || '';
      function done() {
        if (btn.classList.contains('copied')) return;
        btn.classList.add('copied');
        var old = btn.innerHTML;
        btn.innerHTML = '\u2713 Copied';
        setTimeout(function () { btn.classList.remove('copied'); btn.innerHTML = old; }, 1800);
      }
      function fallback() {
        var ta = document.createElement('textarea');
        ta.value = txt;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
        document.body.removeChild(ta);
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(txt).then(done).catch(fallback);
      } else { fallback(); }
    });
  });
})();
