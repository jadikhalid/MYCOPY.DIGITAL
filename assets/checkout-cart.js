(function () {
  const form = document.getElementById("reserve-form");
  const submitBtn = document.getElementById("reserve-submit");
  const errorEl = document.getElementById("reserve-error");
  const overlay = document.getElementById("cart-overlay");
  const drawer = document.getElementById("cart-drawer");
  const closeBtn = document.getElementById("cart-close");
  const mountEl = document.getElementById("checkout-mount");
  const statusEl = document.getElementById("cart-status");

  if (!form || !submitBtn || !drawer || !mountEl) return;

  let checkout = null;
  let stripe = null;
  let busy = false;
  let generation = 0;

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

  function sleep(ms) {
    return new Promise((resolve) => window.setTimeout(resolve, ms));
  }

  async function teardownCheckout() {
    const instance = checkout;
    checkout = null;
    mountEl.innerHTML = "";

    if (!instance) return;

    try {
      instance.destroy();
    } catch (_) {
      /* ignore */
    }

    // Stripe only allows one Embedded Checkout object; give it a moment to release.
    await sleep(150);
  }

  async function closeCart() {
    generation += 1;
    drawer.classList.remove("is-open");
    drawer.setAttribute("aria-hidden", "true");
    overlay.hidden = true;
    document.body.classList.remove("cart-open");
    setStatus("");
    await teardownCheckout();
  }

  async function waitForStripe() {
    if (window.Stripe) return;
    await new Promise((resolve, reject) => {
      const started = Date.now();
      const timer = window.setInterval(() => {
        if (window.Stripe) {
          window.clearInterval(timer);
          resolve();
          return;
        }
        if (Date.now() - started > 10000) {
          window.clearInterval(timer);
          reject(new Error("Stripe.js failed to load."));
        }
      }, 50);
    });
  }

  function getStripe() {
    const pk = window.MYCOPY_STRIPE_PK || "";
    if (!pk || pk.indexOf("pk_") !== 0) {
      throw new Error("Stripe publishable key is missing. Add publishable_key to config.stripe.php.");
    }
    if (!stripe) {
      stripe = window.Stripe(pk);
    }
    return stripe;
  }

  async function fetchClientSecret() {
    const body = new FormData(form);
    body.set("ui_mode", "embedded_page");

    const response = await fetch("reserve.php", {
      method: "POST",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body,
    });

    const data = await response.json();
    if (!data.ok || !data.client_secret) {
      throw new Error(data.error || "Could not start checkout.");
    }

    return data.client_secret;
  }

  async function mountEmbeddedCheckout(gen) {
    await waitForStripe();
    await teardownCheckout();

    if (gen !== generation) return;

    const frame = document.createElement("div");
    frame.id = "checkout-frame";
    mountEl.appendChild(frame);

    const instance = await getStripe().createEmbeddedCheckoutPage({
      fetchClientSecret,
    });

    if (gen !== generation) {
      try {
        instance.destroy();
      } catch (_) {
        /* ignore */
      }
      return;
    }

    checkout = instance;
    instance.mount(frame);
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (busy) return;

    busy = true;
    clearError();
    setLoading(true);

    const gen = ++generation;

    try {
      openCart();
      setStatus("Loading secure checkout…");
      // Paint the open drawer before Stripe injects its iframe.
      await sleep(40);

      if (gen !== generation) return;

      await mountEmbeddedCheckout(gen);

      if (gen === generation) {
        setStatus("");
      }
    } catch (err) {
      if (gen === generation) {
        showError(err && err.message ? err.message : "Connection error. Please try again.");
        await closeCart();
      }
    } finally {
      if (gen === generation) {
        busy = false;
        setLoading(false);
      }
    }
  });

  if (closeBtn) {
    closeBtn.addEventListener("click", () => {
      closeCart();
      busy = false;
      setLoading(false);
    });
  }

  if (overlay) {
    overlay.addEventListener("click", () => {
      closeCart();
      busy = false;
      setLoading(false);
    });
  }

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && drawer.classList.contains("is-open")) {
      closeCart();
      busy = false;
      setLoading(false);
    }
  });
})();
