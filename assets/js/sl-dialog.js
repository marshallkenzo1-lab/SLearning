/*!
 * SLearning — Dialog kustom (pengganti popup "localhost says…" bawaan browser)
 *
 * Cara kerja:
 *  - window.confirm() dan window.alert() diganti dengan dialog bertema kuning/hitam.
 *  - Kode lama seperti onclick="return confirm('...')" / onsubmit="return confirm('...')"
 *    TIDAK perlu diubah. Dialog tidak bisa "menunggu" seperti popup bawaan, jadi:
 *      1) confirm() menahan aksi (return false) dan menampilkan dialog,
 *      2) kalau user menekan "Ya", aksi asli dijalankan ulang (klik / submit),
 *         dan pemanggilan confirm() berikutnya langsung dijawab true.
 *
 * API manual:
 *   SLDialog.confirm('Pesan', {title, confirmText, cancelText, type}) -> Promise<boolean>
 *   SLDialog.alert('Pesan', {title, icon, auto})                      -> Promise<void>
 *   SLDialog.toast('Pesan')
 */
(function () {
  'use strict';
  if (window.SLDialog) return;

  var nativeConfirm = window.confirm ? window.confirm.bind(window) : null;
  var bypass = false;   // true sesaat ketika aksi dijalankan ulang setelah user menekan "Ya"
  var active = null;    // dialog yang sedang tampil

  /* ---------- CSS ---------- */
  var css = [
    '.sld-overlay{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;padding:20px;',
    'background:rgba(10,10,11,.48);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);opacity:0;',
    'transition:opacity .24s ease;font-family:"Plus Jakarta Sans","Poppins","Open Sans",Arial,sans-serif}',
    '.sld-overlay.sld-open{opacity:1}',
    '.sld-box{width:100%;max-width:400px;background:#fff;color:#0a0a0b;border-radius:22px;overflow:hidden;',
    'box-shadow:0 28px 70px rgba(0,0,0,.28),0 0 0 1px rgba(0,0,0,.04);opacity:0;transform:translateY(18px) scale(.93);',
    'transition:transform .34s cubic-bezier(.2,.9,.3,1.25),opacity .24s ease}',
    '.sld-open .sld-box{opacity:1;transform:none}',
    '.sld-closing{opacity:0}',
    '.sld-closing .sld-box{opacity:0;transform:translateY(8px) scale(.97);transition-duration:.16s}',
    '.sld-head{background:linear-gradient(180deg,#FFF8D6 0%,#FFFDF6 100%);border-bottom:1px solid #F2E7B5;padding:26px 24px 20px;text-align:center}',
    '.sld-icon{width:58px;height:58px;margin:0 auto 14px;border-radius:50%;display:flex;align-items:center;justify-content:center;',
    'background:#ffc107;color:#0a0a0b;box-shadow:0 8px 20px rgba(255,193,7,.38);animation:sld-pop .5s cubic-bezier(.2,.9,.3,1.4) .08s both}',
    '.sld-icon svg{width:28px;height:28px}',
    '.sld-danger .sld-icon{background:#FDEBE6;color:#C94F36;box-shadow:0 8px 20px rgba(201,79,54,.22)}',
    '.sld-title{margin:0;font-size:18px;font-weight:700;line-height:1.35}',
    '.sld-msg{margin:0;padding:18px 26px 4px;font-size:14px;line-height:1.65;color:#4b5563;text-align:center;white-space:pre-line;word-break:break-word}',
    '.sld-actions{display:flex;gap:10px;padding:20px 24px 24px}',
    '.sld-btn{flex:1;height:46px;border:0;border-radius:13px;font:inherit;font-size:14px;font-weight:700;cursor:pointer;',
    'transition:background .18s ease,transform .12s ease,box-shadow .18s ease}',
    '.sld-btn:active{transform:scale(.96)}',
    '.sld-btn:focus-visible{outline:3px solid rgba(255,193,7,.55);outline-offset:2px}',
    '.sld-cancel{background:#f4f4f5;color:#0a0a0b}',
    '.sld-cancel:hover{background:#e7e7ea}',
    '.sld-ok{background:#ffc107;color:#0a0a0b;box-shadow:0 6px 16px rgba(255,193,7,.35)}',
    '.sld-ok:hover{background:#ffb300}',
    '.sld-danger .sld-ok{background:#C94F36;color:#fff;box-shadow:0 6px 16px rgba(201,79,54,.32)}',
    '.sld-danger .sld-ok:hover{background:#b5432c}',
    '@keyframes sld-pop{0%{transform:scale(.4);opacity:0}100%{transform:scale(1);opacity:1}}',
    '.sld-toast{position:fixed;left:50%;bottom:28px;z-index:100001;display:flex;align-items:center;gap:10px;max-width:calc(100% - 32px);',
    'padding:13px 20px;border-radius:14px;background:#0a0a0b;color:#fff;font:600 14px/1.4 "Plus Jakarta Sans","Poppins",Arial,sans-serif;',
    'box-shadow:0 14px 36px rgba(0,0,0,.3);opacity:0;transform:translate(-50%,16px);transition:opacity .25s ease,transform .3s cubic-bezier(.2,.9,.3,1.2);pointer-events:none}',
    '.sld-toast.sld-open{opacity:1;transform:translate(-50%,0)}',
    '.sld-toast svg{width:18px;height:18px;flex:none;color:#ffc107}',
    '@media (max-width:420px){.sld-actions{flex-direction:column-reverse}.sld-btn{height:48px}}',
    '@media (prefers-reduced-motion:reduce){.sld-overlay,.sld-box,.sld-toast,.sld-btn{transition:none!important}.sld-icon{animation:none!important}}'
  ].join('');
  var style = document.createElement('style');
  style.setAttribute('data-sl-dialog', '');
  style.textContent = css;
  document.head.appendChild(style);

  /* ---------- Ikon (SVG stroke) ---------- */
  var ICONS = {
    question: '<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/>',
    danger:   '<path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 11v6M14 11v6"/>',
    logout:   '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    info:     '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
    clock:    '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    check:    '<path d="M20 6 9 17l-5-5"/>'
  };
  function icon(name) {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      (ICONS[name] || ICONS.question) + '</svg>';
  }

  /* ---------- Deteksi jenis pesan (judul, ikon, label tombol) ---------- */
  function classify(msg) {
    var s = String(msg || '').toLowerCase(), o;
    if (/yakin ingin keluar/.test(s)) {
      o = { type: 'normal', icon: 'logout', title: 'Keluar dari akun?', ok: 'Ya, Keluar' };
    } else if (/hapus|tolak|tarik|keluarkan/.test(s)) {
      o = { type: 'danger', icon: 'danger', title: 'Apakah Anda yakin?', ok: 'Ya, Lanjutkan' };
      if (/tandai/.test(s)) o.ok = 'Ya, Tandai Selesai';
      else if (/hapus/.test(s)) o.ok = 'Ya, Hapus';
      else if (/tarik/.test(s)) o.ok = 'Ya, Tarik';
      else if (/tolak/.test(s)) o.ok = 'Ya, Tolak';
      else if (/keluarkan/.test(s)) o.ok = 'Ya, Keluarkan';
    } else {
      o = { type: 'normal', icon: 'question', title: 'Konfirmasi', ok: 'Ya, Lanjutkan' };
      if (/kerjakan ulang/.test(s)) o.ok = 'Ya, Kerjakan Ulang';
      else if (/kumpulkan/.test(s)) { o.ok = 'Ya, Kumpulkan'; o.title = 'Kumpulkan jawaban?'; }
      else if (/kirim|lapor/.test(s)) o.ok = 'Ya, Kirim';
    }
    return o;
  }

  /* ---------- Membangun dialog ---------- */
  function build(opts) {
    var overlay = document.createElement('div');
    overlay.className = 'sld-overlay' + (opts.type === 'danger' ? ' sld-danger' : '');
    overlay.setAttribute('role', 'alertdialog');
    overlay.setAttribute('aria-modal', 'true');

    var box = document.createElement('div');
    box.className = 'sld-box';

    var head = document.createElement('div');
    head.className = 'sld-head';
    var ic = document.createElement('div');
    ic.className = 'sld-icon';
    ic.innerHTML = icon(opts.icon);
    var title = document.createElement('h3');
    title.className = 'sld-title';
    title.textContent = opts.title;
    title.id = 'sld-title-' + Date.now();
    head.appendChild(ic);
    head.appendChild(title);

    var msg = document.createElement('p');
    msg.className = 'sld-msg';
    msg.textContent = opts.message;
    msg.id = title.id + '-msg';
    overlay.setAttribute('aria-labelledby', title.id);
    overlay.setAttribute('aria-describedby', msg.id);

    var actions = document.createElement('div');
    actions.className = 'sld-actions';
    var cancelBtn = null;
    if (opts.cancelText) {
      cancelBtn = document.createElement('button');
      cancelBtn.type = 'button';
      cancelBtn.className = 'sld-btn sld-cancel';
      cancelBtn.textContent = opts.cancelText;
      actions.appendChild(cancelBtn);
    }
    var okBtn = document.createElement('button');
    okBtn.type = 'button';
    okBtn.className = 'sld-btn sld-ok';
    okBtn.textContent = opts.okText;
    actions.appendChild(okBtn);

    box.appendChild(head);
    box.appendChild(msg);
    box.appendChild(actions);
    overlay.appendChild(box);
    return { overlay: overlay, ok: okBtn, cancel: cancelBtn };
  }

  function show(opts) {
    return new Promise(function (resolve) {
      if (active) active.close(false); // pastikan hanya satu dialog

      var els = build(opts);
      var prevFocus = document.activeElement;
      var prevOverflow = document.body.style.overflow;
      var done = false, timer = null;

      function close(result) {
        if (done) return;
        done = true;
        if (timer) clearTimeout(timer);
        document.removeEventListener('keydown', onKey, true);
        els.overlay.classList.remove('sld-open');
        els.overlay.classList.add('sld-closing');
        setTimeout(function () {
          if (els.overlay.parentNode) els.overlay.parentNode.removeChild(els.overlay);
          document.body.style.overflow = prevOverflow;
          if (active && active.overlay === els.overlay) active = null;
          if (prevFocus && prevFocus.focus) { try { prevFocus.focus({ preventScroll: true }); } catch (e) {} }
          resolve(result);
        }, 170);
      }

      function onKey(e) {
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(els.cancel ? false : true); }
        else if (e.key === 'Tab') {
          var f = els.cancel ? [els.cancel, els.ok] : [els.ok];
          var i = f.indexOf(document.activeElement);
          e.preventDefault();
          i = e.shiftKey ? (i <= 0 ? f.length - 1 : i - 1) : (i + 1) % f.length;
          f[i].focus();
        }
      }

      els.ok.addEventListener('click', function () { close(true); });
      if (els.cancel) els.cancel.addEventListener('click', function () { close(false); });
      els.overlay.addEventListener('mousedown', function (e) {
        if (e.target === els.overlay) close(els.cancel ? false : true);
      });
      document.addEventListener('keydown', onKey, true);

      document.body.appendChild(els.overlay);
      document.body.style.overflow = 'hidden';
      active = { overlay: els.overlay, close: close };

      // paksa reflow agar transisi masuk berjalan halus
      void els.overlay.offsetWidth;
      els.overlay.classList.add('sld-open');
      // aksi berbahaya: fokus default ke "Batal" supaya tidak terhapus karena tekan Enter
      (opts.type === 'danger' && els.cancel ? els.cancel : els.ok).focus({ preventScroll: true });

      if (opts.auto) timer = setTimeout(function () { close(true); }, opts.auto);
    });
  }

  /* ---------- API publik ---------- */
  function confirmDialog(message, o) {
    o = o || {};
    var c = classify(message);
    return show({
      message: String(message == null ? '' : message),
      title: o.title || c.title,
      icon: o.icon || c.icon,
      type: o.type || c.type,
      okText: o.confirmText || c.ok,
      cancelText: o.cancelText || 'Batal'
    });
  }

  function alertDialog(message, o) {
    o = o || {};
    return show({
      message: String(message == null ? '' : message),
      title: o.title || 'Informasi',
      icon: o.icon || 'info',
      type: 'normal',
      okText: o.okText || 'Mengerti',
      cancelText: null,
      auto: o.auto || 0
    }).then(function () {});
  }

  var toastEl = null, toastTimer = null;
  function toast(message, ms) {
    if (toastEl && toastEl.parentNode) toastEl.parentNode.removeChild(toastEl);
    clearTimeout(toastTimer);
    var t = document.createElement('div');
    t.className = 'sld-toast';
    t.setAttribute('role', 'status');
    t.innerHTML = icon('check');
    var span = document.createElement('span');
    span.textContent = String(message == null ? '' : message);
    t.appendChild(span);
    document.body.appendChild(t);
    toastEl = t;
    void t.offsetWidth;
    t.classList.add('sld-open');
    toastTimer = setTimeout(function () {
      t.classList.remove('sld-open');
      setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 320);
    }, ms || 2200);
  }

  /* ---------- Pengganti window.confirm / window.alert ---------- */
  window.confirm = function (message) {
    if (bypass) { bypass = false; return true; }          // aksi dijalankan ulang setelah user setuju
    var ev = window.event;
    var el = ev && (ev.currentTarget || ev.target);
    if (!el || !el.nodeType) return nativeConfirm ? nativeConfirm(message) : true; // dipanggil dari script biasa

    var type = ev.type, submitter = ev.submitter || null;
    confirmDialog(message).then(function (yes) {
      if (!yes) return;
      bypass = true;
      try {
        if (type === 'submit' && el.tagName === 'FORM') {
          if (el.requestSubmit) el.requestSubmit(submitter || undefined); else el.submit();
        } else if (typeof el.click === 'function') {
          el.click();
        }
      } finally { bypass = false; }
    });
    return false; // tahan aksi sampai user menjawab
  };

  window.alert = function (message) { alertDialog(message); };

  window.SLDialog = { confirm: confirmDialog, alert: alertDialog, toast: toast };
})();
