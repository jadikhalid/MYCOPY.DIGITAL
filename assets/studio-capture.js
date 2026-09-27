(function () {
  const form = document.getElementById("studio-capture-form");
  const continueBtn = document.getElementById("studio-capture-continue");
  const backBtn = document.getElementById("studio-capture-back");
  const resetBtn = document.getElementById("studio-capture-reset");
  const completeBtn = document.getElementById("studio-capture-complete");
  const confirmLock = document.getElementById("capture-confirm-lock");
  const MAX_STEP = 5;

  if (!form) return;

  let currentStep = Number(form.getAttribute("data-step") || "1");
  let transitioning = false;
  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function toast(type, title, message) {
    if (typeof window.mycopyToast === "function") {
      window.mycopyToast(type, title, message);
    }
  }

  function syncChrome(step) {
    form.setAttribute("data-step", String(step));
    document.getElementById("studio-capture-actions")?.setAttribute("data-step", String(step));
    if (backBtn instanceof HTMLButtonElement) {
      backBtn.hidden = false;
      backBtn.disabled = step <= 1;
    }
    if (continueBtn instanceof HTMLButtonElement) {
      continueBtn.hidden = step >= MAX_STEP;
    }
    if (resetBtn instanceof HTMLButtonElement) {
      resetBtn.hidden = step >= MAX_STEP;
    }
    if (completeBtn instanceof HTMLButtonElement) {
      completeBtn.hidden = step < MAX_STEP;
      completeBtn.disabled = !(confirmLock instanceof HTMLInputElement && confirmLock.checked);
    }
    updateRecapFromForm();
  }

  function panelFor(step) {
    return form.querySelector(`[data-step-panel="${step}"]`);
  }

  function waitAnimation(el) {
    return new Promise((resolve) => {
      if (!(el instanceof HTMLElement)) {
        resolve();
        return;
      }
      let done = false;
      const finish = () => {
        if (done) return;
        done = true;
        el.removeEventListener("animationend", onEnd);
        resolve();
      };
      const onEnd = (event) => {
        if (event.target !== el) return;
        finish();
      };
      el.addEventListener("animationend", onEnd);
      window.setTimeout(finish, 520);
    });
  }

  async function showStep(step, options = {}) {
    const next = Number(step);
    const animate = options.animate !== false;
    const direction = options.direction || (next >= currentStep ? "forward" : "back");
    const fromStep = currentStep;
    const fromPanel = panelFor(fromStep);
    const toPanel = panelFor(next);

    if (!toPanel) return;
    if (next === fromStep && fromPanel?.classList.contains("is-active")) {
      syncChrome(next);
      return;
    }

    if (transitioning) return;
    transitioning = true;

    syncChrome(next);
    currentStep = next;

    if (!animate || reduceMotion || !fromPanel || fromPanel === toPanel) {
      form.querySelectorAll("[data-step-panel]").forEach((panel) => {
        panel.classList.remove("is-active", "is-leaving", "is-from-back", "is-to-back", "is-instant");
        if (Number(panel.getAttribute("data-step-panel")) === next) {
          panel.classList.add("is-active", "is-instant");
        }
      });
      transitioning = false;
      return;
    }

    const stage = document.getElementById("studio-capture-stage");
    if (stage instanceof HTMLElement) {
      stage.style.height = `${stage.offsetHeight}px`;
    }

    fromPanel.classList.remove("is-active", "is-from-back");
    fromPanel.classList.add("is-leaving");
    if (direction === "back") {
      fromPanel.classList.add("is-to-back");
    } else {
      fromPanel.classList.remove("is-to-back");
    }

    toPanel.classList.remove("is-leaving", "is-to-back");
    toPanel.classList.add("is-active");
    if (direction === "back") {
      toPanel.classList.add("is-from-back");
    } else {
      toPanel.classList.remove("is-from-back");
    }

    if (stage instanceof HTMLElement) {
      const target = Math.max(toPanel.offsetHeight, 0);
      void stage.offsetHeight;
      stage.classList.add("is-resizing");
      stage.style.height = `${target}px`;
    }

    await Promise.all([waitAnimation(fromPanel), waitAnimation(toPanel)]);

    fromPanel.classList.remove("is-leaving", "is-to-back");
    form.querySelectorAll("[data-step-panel]").forEach((panel) => {
      if (panel !== toPanel) {
        panel.classList.remove("is-active", "is-from-back", "is-leaving", "is-to-back");
      }
    });

    if (stage instanceof HTMLElement) {
      stage.classList.remove("is-resizing");
      stage.style.height = "";
    }
    transitioning = false;
  }

  function updatePreviews(fields) {
    ["photo_face", "photo_profile_left", "photo_profile_right"].forEach((slot) => {
      const img = document.getElementById("preview-" + slot);
      const url = fields[slot + "_url"];
      if (!(img instanceof HTMLImageElement)) return;
      const frame = img.closest(".studio-capture__photo-frame");
      const placeholder = frame?.querySelector(".studio-capture__photo-placeholder");
      if (url) {
        img.src = url;
        img.hidden = false;
        if (placeholder instanceof HTMLElement) placeholder.hidden = true;
      } else {
        img.removeAttribute("src");
        img.hidden = true;
        if (placeholder instanceof HTMLElement) placeholder.hidden = false;
      }
    });
  }

  function fillFields(fields) {
    Object.entries(fields).forEach(([key, value]) => {
      if (key.endsWith("_url") || key === "completed" || key === "completed_at" || key === "capture_step") {
        return;
      }
      const input = form.elements.namedItem(key);
      if (input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement) {
        input.value = value;
      }
    });
    updatePreviews(fields);
    updateRecapFromForm(fields);
  }

  function updateRecapFromForm(fields) {
    const first = fields?.first_name ?? form.elements.namedItem("first_name")?.value ?? "";
    const last = fields?.last_name ?? form.elements.namedItem("last_name")?.value ?? "";
    const birth = fields?.birth_date ?? form.elements.namedItem("birth_date")?.value ?? "";
    const character = fields?.character_text ?? form.elements.namedItem("character_text")?.value ?? "";

    const nameEl = document.querySelector('[data-recap="name"]');
    const birthEl = document.querySelector('[data-recap="birth_date"]');
    const charEl = document.querySelector('[data-recap="character_text"]');
    if (nameEl) nameEl.textContent = `${first} ${last}`.trim();
    if (birthEl) birthEl.textContent = birth;
    if (charEl) charEl.textContent = character;

    ["photo_face", "photo_profile_left", "photo_profile_right"].forEach((slot) => {
      const img = document.querySelector(`[data-recap-photo="${slot}"]`);
      const url = fields?.[slot + "_url"];
      if (!(img instanceof HTMLImageElement)) return;
      const frame = img.closest(".studio-capture__photo-frame");
      const placeholder = frame?.querySelector(".studio-capture__photo-placeholder");
      if (url) {
        img.src = url;
        img.hidden = false;
        if (placeholder instanceof HTMLElement) placeholder.hidden = true;
      } else if (fields) {
        img.removeAttribute("src");
        img.hidden = true;
        if (placeholder instanceof HTMLElement) placeholder.hidden = false;
      }
    });
  }

  async function postAction(action) {
    const body = new FormData(form);
    body.set("action", action);
    if (action === "complete") {
      body.set("confirm_lock", confirmLock instanceof HTMLInputElement && confirmLock.checked ? "1" : "0");
    }
    const response = await fetch("/api/studio-capture.php", {
      method: "POST",
      credentials: "same-origin",
      body,
    });
    const data = await response.json();
    if (!data.ok) {
      throw new Error(data.error || "Request failed.");
    }
    return data;
  }

  async function loadCapture() {
    try {
      const response = await fetch("/api/studio-capture.php", {
        headers: { Accept: "application/json" },
        credentials: "same-origin",
      });
      const data = await response.json();
      if (!data.ok) return;
      if (data.complete) {
        window.location.reload();
        return;
      }
      if (data.fields) fillFields(data.fields);
      await showStep(Number(data.step || currentStep), { animate: false });
    } catch (_) {
      await showStep(currentStep, { animate: false });
    }
  }

  if (continueBtn instanceof HTMLButtonElement) {
    continueBtn.addEventListener("click", async () => {
      continueBtn.disabled = true;
      try {
        const data = await postAction("continue");
        if (data.fields) fillFields(data.fields);
        toast("success", "Saved", "Your answers have been saved.");
        await showStep(Number(data.step || currentStep + 1), { direction: "forward" });
      } catch (err) {
        toast("error", "Could not continue", err && err.message ? err.message : "Please try again.");
      } finally {
        continueBtn.disabled = false;
      }
    });
  }

  if (resetBtn instanceof HTMLButtonElement) {
    resetBtn.addEventListener("click", () => {
      const panel = panelFor(currentStep);
      if (!panel) return;
      panel.querySelectorAll("input, textarea").forEach((input) => {
        input.value = "";
      });
      panel.querySelectorAll(".studio-capture__photo-img").forEach((img) => {
        img.removeAttribute("src");
        img.hidden = true;
        const placeholder = img.closest(".studio-capture__photo-frame")?.querySelector(".studio-capture__photo-placeholder");
        if (placeholder instanceof HTMLElement) placeholder.hidden = false;
      });
    });
  }

  if (backBtn instanceof HTMLButtonElement) {
    backBtn.addEventListener("click", async () => {
      backBtn.disabled = true;
      try {
        const data = await postAction("back");
        if (data.fields) fillFields(data.fields);
        await showStep(Number(data.step || Math.max(1, currentStep - 1)), { direction: "back" });
      } catch (err) {
        toast("error", "Could not go back", err && err.message ? err.message : "Please try again.");
      } finally {
        backBtn.disabled = currentStep <= 1;
      }
    });
  }

  if (confirmLock instanceof HTMLInputElement && completeBtn instanceof HTMLButtonElement) {
    confirmLock.addEventListener("change", () => {
      completeBtn.disabled = !confirmLock.checked;
    });
  }

  if (completeBtn instanceof HTMLButtonElement) {
    completeBtn.addEventListener("click", async () => {
      completeBtn.disabled = true;
      try {
        const data = await postAction("complete");
        try {
          sessionStorage.setItem(
            "mycopy_toast",
            JSON.stringify({ type: "success", title: "Capture complete", message: data.message || "Your base is locked." })
          );
        } catch (_) {}
        window.location.reload();
      } catch (err) {
        toast("error", "Could not confirm", err && err.message ? err.message : "Please try again.");
        completeBtn.disabled = !(confirmLock instanceof HTMLInputElement && confirmLock.checked);
      }
    });
  }

  window.mycopyCaptureSave = () => postAction("save");

  loadCapture();
})();
