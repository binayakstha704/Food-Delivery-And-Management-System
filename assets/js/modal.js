/**
 * Herald Canteen — Custom Modal System
 * Replaces native browser alert() and confirm() with styled modals.
 */

(function () {
  // ── Inject CSS once ──────────────────────────────────────────────────────
  const CSS = `
    #hc-modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 99999;
      background: rgba(0,0,0,0.55);
      backdrop-filter: blur(3px);
      align-items: center;
      justify-content: center;
      animation: hcFadeIn .15s ease;
    }
    #hc-modal-overlay.active { display: flex; }
    @keyframes hcFadeIn { from { opacity:0 } to { opacity:1 } }
    @keyframes hcSlideUp { from { opacity:0; transform:translateY(18px) scale(.97) } to { opacity:1; transform:none } }

    #hc-modal-box {
      background: #1e2030;
      border: 1px solid rgba(255,255,255,.10);
      border-radius: 16px;
      padding: 32px 28px 24px;
      min-width: 320px;
      max-width: 440px;
      width: 90vw;
      box-shadow: 0 24px 60px rgba(0,0,0,.55);
      animation: hcSlideUp .18s ease;
      font-family: inherit;
      color: #e8eaf6;
    }

    #hc-modal-icon {
      font-size: 2rem;
      margin-bottom: 10px;
      display: block;
      text-align: center;
    }

    #hc-modal-title {
      font-size: 1.05rem;
      font-weight: 700;
      color: #fff;
      margin: 0 0 8px;
      text-align: center;
    }

    #hc-modal-message {
      font-size: .9rem;
      color: #b0b8d1;
      line-height: 1.55;
      margin: 0 0 24px;
      text-align: center;
    }

    #hc-modal-buttons {
      display: flex;
      gap: 10px;
      justify-content: center;
    }

    .hc-modal-btn {
      flex: 1;
      max-width: 160px;
      padding: 10px 18px;
      border-radius: 999px;
      border: none;
      font-size: .88rem;
      font-weight: 600;
      cursor: pointer;
      transition: opacity .15s, transform .1s;
    }
    .hc-modal-btn:hover { opacity: .88; transform: translateY(-1px); }
    .hc-modal-btn:active { transform: scale(.97); }

    .hc-modal-btn-cancel {
      background: rgba(255,255,255,.08);
      color: #c0c8e0;
      border: 1px solid rgba(255,255,255,.12);
    }
    .hc-modal-btn-ok {
      background: #5c6bc0;
      color: #fff;
    }
    .hc-modal-btn-ok.danger { background: #e53935; }
    .hc-modal-btn-ok.warning { background: #f59e0b; color: #1a1a1a; }
    .hc-modal-btn-ok.success { background: #22c55e; color: #fff; }
  `;

  const style = document.createElement("style");
  style.textContent = CSS;
  document.head.appendChild(style);

  // ── Build DOM once ────────────────────────────────────────────────────────
  const overlay = document.createElement("div");
  overlay.id = "hc-modal-overlay";
  overlay.innerHTML = `
    <div id="hc-modal-box">
      <span id="hc-modal-icon"></span>
      <p id="hc-modal-title"></p>
      <p id="hc-modal-message"></p>
      <div id="hc-modal-buttons"></div>
    </div>
  `;
  document.addEventListener("DOMContentLoaded", () =>
    document.body.appendChild(overlay),
  );

  let resolveModal = null;

  overlay.addEventListener("click", function (e) {
    if (e.target === overlay) dismiss(false);
  });

  document.addEventListener("keydown", function (e) {
    if (!overlay.classList.contains("active")) return;
    if (e.key === "Escape") dismiss(false);
    if (e.key === "Enter") {
      const ok = document.querySelector(".hc-modal-btn-ok");
      if (ok) {
        e.preventDefault();
        ok.click();
      }
    }
  });

  function dismiss(result) {
    overlay.classList.remove("active");
    if (resolveModal) {
      resolveModal(result);
      resolveModal = null;
    }
  }

  // ── Core show function ────────────────────────────────────────────────────
  /**
   * @param {object} opts
   *   message  {string}
   *   title    {string}   optional
   *   icon     {string}   emoji/text, optional
   *   type     'info'|'danger'|'warning'|'success'
   *   confirm  {boolean}  show Cancel button?
   *   okText   {string}
   *   cancelText {string}
   */
  function showModal(opts) {
    const {
      message = "",
      title = "",
      icon = "",
      type = "info",
      confirm = false,
      okText = "OK",
      cancelText = "Cancel",
    } = opts;

    document.getElementById("hc-modal-icon").textContent = icon;
    document.getElementById("hc-modal-icon").style.display = icon
      ? "block"
      : "none";
    document.getElementById("hc-modal-title").textContent = title;
    document.getElementById("hc-modal-title").style.display = title
      ? "block"
      : "none";
    document.getElementById("hc-modal-message").textContent = message;

    const btnWrap = document.getElementById("hc-modal-buttons");
    btnWrap.innerHTML = "";

    if (confirm) {
      const cancel = document.createElement("button");
      cancel.className = "hc-modal-btn hc-modal-btn-cancel";
      cancel.textContent = cancelText;
      cancel.onclick = () => dismiss(false);
      btnWrap.appendChild(cancel);
    }

    const ok = document.createElement("button");
    ok.className = `hc-modal-btn hc-modal-btn-ok ${type !== "info" ? type : ""}`;
    ok.textContent = okText;
    ok.onclick = () => dismiss(true);
    btnWrap.appendChild(ok);

    overlay.classList.add("active");
    setTimeout(() => ok.focus(), 50);

    return new Promise((res) => {
      resolveModal = res;
    });
  }

  // ── Public API ────────────────────────────────────────────────────────────

  /** Drop-in async replacement for alert() */
  window.hcAlert = function (
    message,
    { title = "", icon = "ℹ️", type = "info" } = {},
  ) {
    return showModal({
      message,
      title,
      icon,
      type,
      confirm: false,
      okText: "OK",
    });
  };

  /** Drop-in async replacement for confirm() → resolves true/false */
  window.hcConfirm = function (
    message,
    {
      title = "",
      icon = "❓",
      type = "danger",
      okText = "Confirm",
      cancelText = "Cancel",
    } = {},
  ) {
    return showModal({
      message,
      title,
      icon,
      type,
      confirm: true,
      okText,
      cancelText,
    });
  };

  // ── Helper: submit a form preserving the submit button's name/value ──────
  function submitFormWithButton(form) {
    // Find the submit button(s) inside this form and inject hidden inputs
    // so that form.submit() (which skips buttons) still sends the right POST key.
    var buttons = form.querySelectorAll(
      'button[type="submit"][name], input[type="submit"][name]',
    );
    buttons.forEach(function (btn) {
      var hidden = document.createElement("input");
      hidden.type = "hidden";
      hidden.name = btn.name;
      hidden.value = btn.value || "";
      form.appendChild(hidden);
    });
    form.submit();
  }
  // For forms that still use onsubmit="return confirm('...')" we need a sync
  // workaround: we DON'T replace window.confirm globally (that breaks things),
  // instead we rewrite those forms at page load via data-hc-confirm attribute,
  // OR intercept via event delegation below.
  document.addEventListener("DOMContentLoaded", function () {
    // 1. Forms with onsubmit containing confirm(
    document.querySelectorAll("form[onsubmit]").forEach(function (form) {
      const attr = form.getAttribute("onsubmit") || "";
      const match = attr.match(/confirm\(['"](.+?)['"]\)/);
      if (!match) return;
      const msg = match[1];
      form.removeAttribute("onsubmit");
      form.addEventListener("submit", async function (e) {
        e.preventDefault();
        const ok = await window.hcConfirm(msg);
        if (ok) submitFormWithButton(form);
      });
    });

    // 2. Forms with data-hc-confirm attribute (our explicit markup)
    document.querySelectorAll("form[data-hc-confirm]").forEach(function (form) {
      form.addEventListener("submit", async function (e) {
        e.preventDefault();
        const msg = form.dataset.hcConfirm;
        const icon = form.dataset.hcConfirmIcon || "❓";
        const type = form.dataset.hcConfirmType || "danger";
        const okText = form.dataset.hcConfirmOk || "Confirm";
        const ok = await window.hcConfirm(msg, { icon, type, okText });
        if (ok) submitFormWithButton(form);
      });
    });
  });
})();
