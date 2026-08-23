(function () {
  const form = document.getElementById("reserve-form");
  const submitBtn = document.getElementById("reserve-submit");
  const errorEl = document.getElementById("reserve-error");
  const overlay = document.getElementById("cart-overlay");
  const drawer = document.getElementById("cart-drawer");
  const closeBtn = document.getElementById("cart-close");
  const mountEl = document.getElementById("checkout-mount");
  const statusEl = document.getElementById("cart-status");

  if (!form || !submitBtn || !drawer) return;

  let busy = false;

  function showError(message) {
    if (!(errorEl instanceof HTMLElement)) return;
    errorEl.textContent = message;
    errorEl.hidden = false;
  }

  function clearError() {
    if (!(errorEl instanceof HTMLElement)) return;
    errorEl.textContent = "";
    errorEl.hidden = true;
  }

  function setLoading(loading) {
    submitBtn.classList.toggle("is-loading", loading);
    submitBtn.disabled = loading;
  }

  function setStatus(message) {
    if (!(statusEl instanceof HTMLElement)) return;
    if (!message) {
      statusEl.hidden = true;
      statusEl.textContent = "";
      return;
    }
    statusEl.hidden = false;
    statusEl.textContent = message;
  }

  function openCart() {
    overlay.hidden = false;
    drawer.classList.add("is-open");
    drawer.setAttribute("aria-hidden", "false");
    document.body.classList.add("cart-open");
  }

  function closeCart() {
    drawer.classList.remove("is-open");
    drawer.setAttribute("aria-hidden", "true");
    overlay.hidden = true;
    document.body.classList.remove("cart-open");
    setStatus("");
    if (mountEl) {
      mountEl.innerHTML = "";
    }
  }

  function renderRedirectPanel() {
    if (!(mountEl instanceof HTMLElement)) return;
    mountEl.innerHTML =
      '<div class="cart-drawer__redirect">' +
      "<p>Opening Stripe secure checkout…</p>" +
      "<p class=\"cart-drawer__redirect-note\">You will complete payment on Stripe, then return here.</p>" +
      "</div>";
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (busy) return;

    busy = true;
    clearError();
    setLoading(true);

    try {
      openCart();
      renderRedirectPanel();
      setStatus("Preparing secure payment…");

      const body = new FormData(form);
      body.set("ui_mode", "hosted_page");

      const response = await fetch("reserve.php", {
        method: "POST",
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        body,
      });

      const data = await response.json();
      if (!data.ok || !data.url) {
        throw new Error(data.error || "Could not start checkout.");
      }

      setStatus("Redirecting to Stripe…");
      window.location.href = data.url;
    } catch (err) {
      showError(err && err.message ? err.message : "Connection error. Please try again.");
      closeCart();
      busy = false;
      setLoading(false);
    }
  });

  if (closeBtn) {
    closeBtn.addEventListener("click", () => {
      if (busy) return;
      closeCart();
    });
  }

  if (overlay) {
    overlay.addEventListener("click", () => {
      if (busy) return;
      closeCart();
    });
  }

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && drawer.classList.contains("is-open") && !busy) {
      closeCart();
    }
  });
})();
