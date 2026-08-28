(function () {
  const form = document.getElementById("protocol-intake-form");
  const completeBtn = document.getElementById("protocol-intake-complete");
  const statusEl = document.getElementById("protocol-intake-status");
  const phaseEl = document.getElementById("protocol-current-phase");

  if (!form) return;

  function setStatus(message, isError) {
    if (!(statusEl instanceof HTMLElement)) return;
    statusEl.textContent = message || "";
    statusEl.hidden = !message;
    statusEl.classList.toggle("protocol-intake__status--error", !!isError);
  }

  function updatePreviews(fields) {
    ["photo_face", "photo_profile_left", "photo_profile_right"].forEach((slot) => {
      const img = document.getElementById("preview-" + slot);
      const url = fields[slot + "_url"];
      if (!(img instanceof HTMLImageElement)) return;
      const frame = img.closest(".protocol-intake__photo-frame");
      const placeholder = frame?.querySelector(".protocol-intake__photo-placeholder");
      if (url) {
        img.src = url;
        img.hidden = false;
        if (placeholder instanceof HTMLElement) placeholder.hidden = true;
      }
    });
  }

  async function loadIntake() {
    try {
      const response = await fetch("/api/protocol-intake.php", {
        headers: { Accept: "application/json" },
        credentials: "same-origin",
      });
      const data = await response.json();
      if (!data.ok || !data.fields) return;

      Object.entries(data.fields).forEach(([key, value]) => {
        if (!key.endsWith("_url")) {
          const input = form.elements.namedItem(key);
          if (input instanceof HTMLInputElement) {
            input.value = value;
          }
        }
      });
      updatePreviews(data.fields);
    } catch (_) {
      /* ignore */
    }
  }

  async function submitIntake(action) {
    const body = new FormData(form);
    body.set("action", action);

    const response = await fetch("/api/protocol-intake.php", {
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

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    setStatus("Saving…", false);
    try {
      const data = await submitIntake("save");
      if (data.fields) updatePreviews(data.fields);
      setStatus(data.message || "Saved.", false);
    } catch (err) {
      setStatus(err && err.message ? err.message : "Save failed.", true);
    }
  });

  if (completeBtn instanceof HTMLButtonElement) {
    completeBtn.addEventListener("click", async () => {
      setStatus("Validating…", false);
      completeBtn.disabled = true;
      try {
        const data = await submitIntake("complete");
        setStatus(data.message || "Intake complete.", false);
        if (phaseEl) phaseEl.textContent = data.phase || "train";
        window.location.reload();
      } catch (err) {
        setStatus(err && err.message ? err.message : "Could not complete.", true);
        completeBtn.disabled = false;
      }
    });
  }

  loadIntake();
})();
