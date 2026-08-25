(function () {
  const form = document.getElementById("reserve-form");
  const submitBtn = document.getElementById("reserve-submit");
  const errorEl = document.getElementById("reserve-error");
  const overlay = document.getElementById("cart-overlay");
  const drawer = document.getElementById("cart-drawer");
  const closeBtn = document.getElementById("cart-close");
  const mountEl = document.getElementById("checkout-mount");
  const statusEl = document.getElementById("cart-status");
  const confirmOverlay = document.getElementById("confirm-overlay");
  const confirmModal = document.getElementById("confirm-modal");
  const confirmName = document.getElementById("confirm-name");
  const confirmEmail = document.getElementById("confirm-email");
  const confirmEdit = document.getElementById("confirm-edit");
  const confirmPay = document.getElementById("confirm-pay");
  const nameInput = document.getElementById("name");
  const emailInput = document.getElementById("email");

  if (!form || !submitBtn || !drawer || !mountEl || !confirmModal || !confirmPay) return;

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
    if (confirmPay) {
      confirmPay.disabled = loading;
      confirmPay.classList.toggle("is-loading", loading);
    }
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

  function openConfirm() {
    const name = (nameInput && nameInput.value ? nameInput.value : "").trim();
    const email = (emailInput && emailInput.value ? emailInput.value : "").trim();

    if (confirmName) confirmName.textContent = name;
    if (confirmEmail) confirmEmail.textContent = email;

    if (confirmOverlay) confirmOverlay.hidden = false;
    confirmModal.hidden = false;
    confirmModal.setAttribute("aria-hidden", "false");
    document.body.classList.add("confirm-open");

    confirmPay.focus();
  }

  function closeConfirm() {
    if (confirmOverlay) confirmOverlay.hidden = true;
    confirmModal.hidden = true;
    confirmModal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("confirm-open");
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

  async function startCheckout() {
    if (busy) return;

    busy = true;
    clearError();
    setLoading(true);
    closeConfirm();

    const gen = ++generation;

    try {
      openCart();
      setStatus("Loading secure checkout…");
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
  }

  function showToast(type, title, message) {
    document.querySelectorAll(".toast--live").forEach((el) => el.remove());

    const toast = document.createElement("div");
    toast.className = "toast toast--live toast--" + (type === "success" ? "success" : "error");
    toast.setAttribute("role", "status");
    toast.setAttribute("aria-live", "polite");
    toast.innerHTML =
      '<span class="toast__bar" aria-hidden="true"></span>' +
      '<div class="toast__body">' +
      '<p class="toast__title"></p>' +
      '<p class="toast__msg"></p>' +
      "</div>";

    const titleEl = toast.querySelector(".toast__title");
    const msgEl = toast.querySelector(".toast__msg");
    if (titleEl) titleEl.textContent = title || "";
    if (msgEl) msgEl.textContent = message || "";

    document.body.appendChild(toast);
    window.setTimeout(() => {
      if (toast.parentNode) toast.remove();
    }, 5600);
  }

  async function checkEmailAvailable() {
    const body = new FormData();
    body.set("email", emailInput ? emailInput.value.trim() : "");

    const response = await fetch("/api/check-email.php", {
      method: "POST",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body,
    });

    let data;
    try {
      data = await response.json();
    } catch (_) {
      throw new Error("Invalid check response.");
    }

    return data;
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (busy) return;
    clearError();

    if (typeof form.reportValidity === "function" && !form.reportValidity()) {
      return;
    }

    busy = true;
    setLoading(true);

    try {
      const data = await checkEmailAvailable();

      // Fail closed: only open the modal when the API explicitly says available.
      if (data && data.ok === true && data.available === true) {
        openConfirm();
        return;
      }

      const toast = (data && data.toast) || {};
      showToast(
        toast.type || "error",
        toast.title || "Email already used",
        toast.message || (data && data.error) || "This email cannot be used."
      );
    } catch (_) {
      showToast("error", "Check failed", "Could not verify email. Please try again.");
    } finally {
      busy = false;
      setLoading(false);
    }
  });

  confirmPay.addEventListener("click", () => {
    startCheckout();
  });

  if (confirmEdit) {
    confirmEdit.addEventListener("click", () => {
      closeConfirm();
      if (emailInput) emailInput.focus();
    });
  }

  if (confirmOverlay) {
    confirmOverlay.addEventListener("click", () => {
      if (busy) return;
      closeConfirm();
    });
  }

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
    if (event.key !== "Escape") return;

    if (drawer.classList.contains("is-open")) {
      closeCart();
      busy = false;
      setLoading(false);
      return;
    }

    if (!confirmModal.hidden && !busy) {
      closeConfirm();
    }
  });
})();
