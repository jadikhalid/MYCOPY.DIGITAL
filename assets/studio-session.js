(function () {
  function showToast(type, title, message) {
    document.querySelectorAll(".toast--live").forEach((el) => el.remove());

    const toast = document.createElement("div");
    toast.className = "toast toast--live toast--closable toast--" + (type === "success" ? "success" : "error");
    toast.setAttribute("role", type === "success" ? "status" : "alert");
    toast.setAttribute("aria-live", "polite");
    toast.innerHTML =
      '<span class="toast__bar" aria-hidden="true"></span>' +
      '<div class="toast__body">' +
      '<p class="toast__title"></p>' +
      '<p class="toast__msg"></p>' +
      "</div>" +
      '<button class="toast__close" type="button" aria-label="Close">&times;</button>';

    const titleEl = toast.querySelector(".toast__title");
    const msgEl = toast.querySelector(".toast__msg");
    if (titleEl) titleEl.textContent = title || "";
    if (msgEl) msgEl.textContent = message || "";

    const autoClose = window.setTimeout(() => {
      if (toast.parentNode) toast.remove();
    }, 5600);

    toast.querySelector(".toast__close")?.addEventListener("click", () => {
      window.clearTimeout(autoClose);
      toast.classList.add("is-closing");
      toast.addEventListener("animationend", () => toast.remove(), { once: true });
      window.setTimeout(() => toast.remove(), 600);
    });

    document.body.appendChild(toast);
  }

  window.mycopyToast = showToast;

  try {
    const pending = sessionStorage.getItem("mycopy_toast");
    if (pending) {
      sessionStorage.removeItem("mycopy_toast");
      const data = JSON.parse(pending);
      showToast(data.type, data.title, data.message);
    }
  } catch (_) {}

  const form = document.getElementById("end-session-form");
  const modal = document.getElementById("end-session-modal");
  const confirmBtn = document.getElementById("end-session-confirm");

  if (!(form instanceof HTMLFormElement) || !(modal instanceof HTMLElement) || !(confirmBtn instanceof HTMLButtonElement)) {
    return;
  }

  const defaultLabel = confirmBtn.textContent;
  let skipSave = false;

  function open() {
    skipSave = false;
    confirmBtn.textContent = defaultLabel;
    confirmBtn.disabled = false;
    modal.hidden = false;
    requestAnimationFrame(() => modal.classList.add("is-open"));
    confirmBtn.focus();
  }

  function close() {
    modal.classList.remove("is-open");
    modal.hidden = true;
  }

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    open();
  });

  modal.querySelectorAll("[data-modal-close]").forEach((el) => {
    el.addEventListener("click", close);
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !modal.hidden) close();
  });

  confirmBtn.addEventListener("click", async () => {
    confirmBtn.disabled = true;
    if (!skipSave && typeof window.mycopyCaptureSave === "function") {
      confirmBtn.textContent = "Saving…";
      try {
        await window.mycopyCaptureSave();
      } catch (err) {
        showToast(
          "error",
          "Could not save",
          (err && err.message ? err.message : "Please try again.") + " You can still quit without saving."
        );
        confirmBtn.textContent = "Quit anyway";
        confirmBtn.disabled = false;
        skipSave = true;
        return;
      }
    }
    form.submit();
  });
})();
