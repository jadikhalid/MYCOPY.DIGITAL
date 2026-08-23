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
  let sessionSecret = null;
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

  function waitForDrawerReady() {
    return new Promise((resolve) => {
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          const styles = window.getComputedStyle(drawer);
          const durationMs = parseFloat(styles.transitionDuration) * 1000;
          if (
            !Number.isFinite(durationMs) ||
            durationMs <= 0 ||
            window.matchMedia("(prefers-reduced-motion: reduce)").matches
          ) {
            resolve();
            return;
          }

          let settled = false;
          const finish = () => {
            if (settled) return;
            settled = true;
            drawer.removeEventListener("transitionend", onTransitionEnd);
            resolve();
          };

          const onTransitionEnd = (event) => {
            if (event.target === drawer && event.propertyName === "transform") {
              finish();
            }
          };

          drawer.addEventListener("transitionend", onTransitionEnd);
          window.setTimeout(finish, durationMs + 60);
        });
      });
    });
  }

  function destroyCheckout() {
    if (!checkout) {
      mountEl.innerHTML = "";
      return;
    }

    try {
      checkout.destroy();
    } catch (_) {
      try {
        checkout.unmount();
      } catch (__) {
        /* ignore */
      }
    }

    checkout = null;
    mountEl.innerHTML = "";
  }

  async function closeCart() {
    drawer.classList.remove("is-open");
    drawer.setAttribute("aria-hidden", "true");
    overlay.hidden = true;
    document.body.classList.remove("cart-open");

    destroyCheckout();
    sessionSecret = null;
    setStatus("");
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

  async function fetchCheckoutSecret() {
    if (sessionSecret) {
      return sessionSecret;
    }

    const response = await fetch("reserve.php", {
      method: "POST",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: new FormData(form),
    });

    const data = await response.json();
    if (!data.ok || !data.client_secret) {
      throw new Error(data.error || "Could not start checkout.");
    }

    sessionSecret = data.client_secret;
    return sessionSecret;
  }

  async function createCheckoutInstance() {
    destroyCheckout();
    sessionSecret = null;

    const instance = await getStripe().createEmbeddedCheckoutPage({
      fetchClientSecret: fetchCheckoutSecret,
    });

    checkout = instance;
    instance.mount("#checkout-mount");
    return instance;
  }

  async function mountCheckout() {
    await waitForStripe();

    try {
      await createCheckoutInstance();
    } catch (err) {
      const message = err && err.message ? String(err.message) : "";
      // Leftover Stripe instance (close/retry race) — wipe and retry once.
      if (/multiple Embedded Checkout/i.test(message)) {
        destroyCheckout();
        sessionSecret = null;
        await new Promise((r) => window.setTimeout(r, 50));
        await createCheckoutInstance();
        return;
      }
      throw err;
    }
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (busy) return;

    busy = true;
    clearError();
    setLoading(true);

    try {
      openCart();
      setStatus("Loading secure checkout…");
      await waitForDrawerReady();
      await mountCheckout();
      setStatus("");
    } catch (err) {
      showError(err && err.message ? err.message : "Connection error. Please try again.");
      await closeCart();
    } finally {
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
