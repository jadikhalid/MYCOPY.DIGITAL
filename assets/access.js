(function () {
  const form = document.querySelector(".access.access--ajax");
  if (!form) return;

  const submitBtn = form.querySelector(".access__btn");
  const errorEl = form.querySelector(".access__error");

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    if (!(submitBtn instanceof HTMLButtonElement)) return;

    submitBtn.classList.add("is-loading");
    submitBtn.disabled = true;

    if (errorEl instanceof HTMLElement) {
      errorEl.hidden = true;
      errorEl.textContent = "";
    }

    try {
      const response = await fetch("/api/access.php", {
        method: "POST",
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: new FormData(form),
      });

      const data = await response.json();

      if (data.ok && data.redirect) {
        window.location.assign(data.redirect);
        return;
      }

      if (errorEl instanceof HTMLElement) {
        errorEl.textContent = data.error || "Invalid code. Access denied.";
        errorEl.hidden = false;
      }
    } catch {
      if (errorEl instanceof HTMLElement) {
        errorEl.textContent = "Connection error. Please try again.";
        errorEl.hidden = false;
      }
    } finally {
      submitBtn.classList.remove("is-loading");
      submitBtn.disabled = false;
    }
  });
})();
