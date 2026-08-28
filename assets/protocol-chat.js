(function () {
  const log = document.getElementById("profile-chat-log");
  const form = document.getElementById("profile-chat-form");
  const input = document.getElementById("profile-chat-input");
  const typing = document.getElementById("profile-chat-typing");
  const statusEl = document.getElementById("profile-chat-status");
  const pctEl = document.getElementById("profile-progress-pct");
  const fillEl = document.getElementById("profile-progress-fill");
  const sectionsEl = document.getElementById("profile-progress-sections");

  if (!log || !form || !input) return;

  let busy = false;

  function setStatus(message, isError) {
    if (!(statusEl instanceof HTMLElement)) return;
    statusEl.textContent = message || "";
    statusEl.hidden = !message;
    statusEl.classList.toggle("profile-chat__status--error", !!isError);
  }

  function setTyping(show) {
    if (!(typing instanceof HTMLElement)) return;
    typing.hidden = !show;
  }

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function renderMessages(messages) {
    if (!(log instanceof HTMLElement) || !Array.isArray(messages)) return;
    log.innerHTML = messages
      .map((msg) => {
        const role = msg.role === "assistant" ? "agent" : "user";
        return (
          '<div class="profile-chat__msg profile-chat__msg--' +
          role +
          '">' +
          '<p class="profile-chat__role">' +
          (role === "agent" ? "MYCOPY Agent" : "You") +
          "</p>" +
          '<div class="profile-chat__bubble">' +
          escapeHtml(msg.content).replace(/\n/g, "<br>") +
          "</div></div>"
        );
      })
      .join("");
    log.scrollTop = log.scrollHeight;
  }

  function updateCompletion(completion) {
    if (!completion) return;
    if (pctEl) pctEl.textContent = String(completion.percent ?? 0) + "%";
    if (fillEl instanceof HTMLElement) {
      fillEl.style.width = String(completion.percent ?? 0) + "%";
    }
    if (sectionsEl && completion.sections) {
      sectionsEl.innerHTML = Object.values(completion.sections)
        .map(
          (sec) =>
            "<li>" +
            escapeHtml(sec.label) +
            " · " +
            String(sec.percent) +
            "%</li>"
        )
        .join("");
    }
  }

  async function loadState() {
    setTyping(true);
    setStatus("");
    try {
      const response = await fetch("/api/protocol-chat.php", {
        headers: { Accept: "application/json" },
        credentials: "same-origin",
      });
      let data;
      try {
        data = await response.json();
      } catch (_) {
        throw new Error("Server returned an invalid response (" + response.status + ").");
      }
      if (!data.ok) {
        throw new Error(data.error || "Could not load chat (" + response.status + ").");
      }
      renderMessages(data.messages || []);
      updateCompletion(data.completion);
    } catch (err) {
      setStatus(err && err.message ? err.message : "Load failed.", true);
    } finally {
      setTyping(false);
    }
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (busy) return;

    const text = input.value.trim();
    if (!text) return;

    busy = true;
    setStatus("");
    input.disabled = true;
    setTyping(true);

    const priorMessages = await fetch("/api/protocol-chat.php", {
      headers: { Accept: "application/json" },
    })
      .then((r) => r.json())
      .then((d) => d.messages || []);

    renderMessages([...priorMessages, { role: "user", content: text }]);

    try {
      const response = await fetch("/api/protocol-chat.php", {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify({ message: text }),
      });
      const data = await response.json();
      if (!data.ok) {
        throw new Error(data.error || "Send failed.");
      }
      renderMessages(data.messages);
      updateCompletion(data.completion);
      input.value = "";
    } catch (err) {
      setStatus(err && err.message ? err.message : "Send failed.", true);
      await loadState();
    } finally {
      busy = false;
      input.disabled = false;
      setTyping(false);
      input.focus();
    }
  });

  loadState();
})();
