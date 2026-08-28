(function () {
  const form = document.getElementById("protocol-capture-form");
  const confirmBtn = document.getElementById("protocol-capture-confirm");
  const fileInput = document.getElementById("protocol-capture-file");
  const preview = document.getElementById("protocol-capture-preview");
  const statusEl = document.getElementById("protocol-capture-status");
  const phaseEl = document.getElementById("protocol-current-phase");

  function setStatus(message, isError) {
    if (!(statusEl instanceof HTMLElement)) return;
    statusEl.textContent = message || "";
    statusEl.hidden = !message;
    statusEl.classList.toggle("protocol-capture__status--error", !!isError);
  }

  async function postCapture(body) {
    const response = await fetch("/api/protocol-capture.php", {
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
      throw new Error("Invalid response.");
    }
    if (!data.ok) {
      throw new Error(data.error || "Request failed.");
    }
    return data;
  }

  if (form instanceof HTMLFormElement && fileInput instanceof HTMLInputElement) {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (!fileInput.files || !fileInput.files[0]) {
        setStatus("Choose a photo first.", true);
        return;
      }

      const body = new FormData();
      body.set("action", "upload");
      body.set("photo", fileInput.files[0]);
      setStatus("Uploading…", false);

      try {
        const data = await postCapture(body);
        if (preview instanceof HTMLImageElement && data.photo_url) {
          preview.src = data.photo_url;
        }
        setStatus(data.message || "Photo saved.", false);
        if (confirmBtn instanceof HTMLButtonElement) {
          confirmBtn.disabled = false;
        }
      } catch (err) {
        setStatus(err && err.message ? err.message : "Upload failed.", true);
      }
    });
  }

  if (confirmBtn instanceof HTMLButtonElement) {
    confirmBtn.addEventListener("click", async () => {
      const body = new FormData();
      body.set("action", "confirm");
      setStatus("Confirming capture…", false);
      confirmBtn.disabled = true;

      try {
        const data = await postCapture(body);
        setStatus(data.message || "Capture confirmed.", false);
        if (phaseEl instanceof HTMLElement && data.phase) {
          phaseEl.textContent = data.phase;
        }
        document.querySelectorAll(".protocol-phase").forEach((el) => {
          el.classList.toggle(
            "protocol-phase--active",
            el.getAttribute("data-phase") === data.phase
          );
          el.classList.toggle(
            "protocol-phase--done",
            el.getAttribute("data-phase") === "capture"
          );
        });
      } catch (err) {
        setStatus(err && err.message ? err.message : "Could not confirm.", true);
        confirmBtn.disabled = false;
      }
    });
  }
})();
