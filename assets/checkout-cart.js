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

  function openCart() {
    overlay.hidden = false;
    drawer.classList.add("is-open");
    drawer.setAttribute("aria-hidden", "false");
    document.body.classList.add("cart-open");
  }

  async function closeCart() {
    drawer.classList.remove("is-open");
    drawer.setAttribute("aria-hidden", "true");
    overlay.hidden = true;
    document.body.classList.remove("cart-open");

    if (checkout) {
      try {
        checkout.destroy();
      } catch (_) {
        /* ignore */
      }
      checkout = null;
    }

    mountEl.innerHTML = "";
    if (statusEl) {
      statusEl.hidden = true;
      statusEl.textContent = "";
    }
  }

  async function mountCheckout(clientSecret, publishableKey) {
    if (!window.Stripe) {
      throw new Error("Stripe.js failed to load.");
    }

    const pk = publishableKey || window.MYCOPY_STRIPE_PK || "";
    if (!pk || pk.indexOf("pk_") !== 0) {
      throw new Error("Stripe publishable key is missing. Add publishable_key to config.stripe.php.");
    }

    const stripe = window.Stripe(pk);
    checkout = await stripe.createEmbeddedCheckoutPage({
      fetchClientSecret: async () => clientSecret,
    });
    checkout.mount("#checkout-mount");
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    clearError();
    setLoading(true);

    try {
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
        showError(data.error || "Could not start checkout.");
        return;
      }

      openCart();
      if (statusEl) {
        statusEl.hidden = false;
        statusEl.textContent = "Loading secure checkout…";
      }

      await mountCheckout(data.client_secret, data.publishable_key);

      if (statusEl) {
        statusEl.hidden = true;
        statusEl.textContent = "";
      }
    } catch (err) {
      showError(err && err.message ? err.message : "Connection error. Please try again.");
      await closeCart();
    } finally {
      setLoading(false);
    }
  });

  if (closeBtn) {
    closeBtn.addEventListener("click", () => {
      closeCart();
    });
  }

  if (overlay) {
    overlay.addEventListener("click", () => {
      closeCart();
    });
  }

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && drawer.classList.contains("is-open")) {
      closeCart();
    }
  });
})();
