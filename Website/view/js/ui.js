/**
 * Shared UI: modals, toasts, tabs, dropdowns
 */
(function (global) {
  function escapeHtml(str) {
    if (str == null) return "";
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function toast(message, duration) {
    const root = document.getElementById("toast-root");
    if (!root) return;
    const el = document.createElement("div");
    el.className = "toast";
    el.textContent = message;
    root.appendChild(el);
    setTimeout(() => {
      el.style.opacity = "0";
      el.style.transform = "translateY(6px)";
      setTimeout(() => el.remove(), 200);
    }, duration || 2600);
  }

  function openModal(opts) {
    const root = document.getElementById("modal-root");
    if (!root) return;

    const backdrop = document.createElement("div");
    backdrop.className = "modal-backdrop";
    backdrop.setAttribute("role", "presentation");

    const modal = document.createElement("div");
    modal.className = "modal" + (opts.wide ? " modal--wide" : "");
    modal.setAttribute("role", "dialog");
    modal.setAttribute("aria-modal", "true");
    modal.setAttribute("aria-labelledby", "modal-title-el");

    const header = document.createElement("div");
    header.className = "modal__header";
    header.innerHTML =
      '<h2 class="modal__title" id="modal-title-el">' +
      escapeHtml(opts.title || "Dialog") +
      "</h2>" +
      '<button type="button" class="modal__close" aria-label="Close">×</button>';

    const body = document.createElement("div");
    body.className = "modal__body";
    if (typeof opts.body === "string") body.innerHTML = opts.body;
    else if (opts.body instanceof HTMLElement) body.appendChild(opts.body);

    modal.appendChild(header);
    modal.appendChild(body);

    let footer = null;
    if (opts.footer) {
      footer = document.createElement("div");
      footer.className = "modal__footer";
      if (typeof opts.footer === "string") footer.innerHTML = opts.footer;
      else if (opts.footer instanceof HTMLElement) footer.appendChild(opts.footer);
      modal.appendChild(footer);
    }

    function close() {
      backdrop.remove();
      modal.remove();
      document.removeEventListener("keydown", onKey);
      if (opts.onClose) opts.onClose();
    }

    function onKey(e) {
      if (e.key === "Escape") close();
    }

    header.querySelector(".modal__close").addEventListener("click", close);
    backdrop.addEventListener("click", close);
    document.addEventListener("keydown", onKey);

    root.appendChild(backdrop);
    root.appendChild(modal);

    return { close, modal, body, footer: footer || null };
  }

  function initTabs(container) {
    const tabs = container.querySelectorAll(".tab");
    const panels = container.querySelectorAll(".tab-panel");
    tabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        const id = tab.getAttribute("data-tab");
        tabs.forEach((t) => t.classList.toggle("is-active", t === tab));
        panels.forEach((p) => {
          p.hidden = p.getAttribute("data-tab-panel") !== id;
        });
      });
    });
  }

  function memberSelectOptions(members, selectedId) {
    return (
      '<option value="">— Select —</option>' +
      members
        .map(
          (m) =>
            '<option value="' +
            escapeHtml(m.id) +
            '"' +
            (m.id === selectedId ? " selected" : "") +
            ">" +
            escapeHtml(m.name) +
            "</option>"
        )
        .join("")
    );
  }

  function avatarInitial(name) {
    if (!name) return "?";
    const parts = name.trim().split(/\s+/);
    return (parts[0][0] + (parts[1] ? parts[1][0] : "")).toUpperCase();
  }

  global.UI = {
    escapeHtml,
    toast,
    openModal,
    initTabs,
    memberSelectOptions,
    avatarInitial,
  };
})(typeof window !== "undefined" ? window : globalThis);
