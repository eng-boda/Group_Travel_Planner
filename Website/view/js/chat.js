/**
 * Trip chat: messages, simple threads, simulated activity
 */
(function (global) {
  const { escapeHtml, toast, avatarInitial } = global.UI;
  const { generateId, getTrip } = global.Storage;

  let typingTimer = null;

  function formatTime(ts) {
    const d = new Date(ts);
    return d.toLocaleString(undefined, { month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" });
  }

  function authorName(trip, authorId) {
    const m = trip.members.find((x) => x.id === authorId);
    return m ? m.name : authorId;
  }

  function renderMessages(trip, container) {
    const roots = trip.messages.filter((m) => !m.parentId).sort((a, b) => a.ts - b.ts);
    container.innerHTML = "";

    if (!roots.length) {
      container.innerHTML = '<p class="muted">No messages yet. Say hello to the team.</p>';
      return;
    }

    container.onclick = function (e) {
      const btn = e.target.closest("[data-reply]");
      if (!btn) return;
      const id = btn.getAttribute("data-reply");
      const inp = document.getElementById("chat-input");
      const hid = document.getElementById("chat-reply-to");
      if (hid) hid.value = id;
      if (inp) {
        inp.focus();
        inp.placeholder = "Reply to thread…";
      }
      toast("Replying in thread");
    };

    function bubble(m, isReply) {
      const row = document.createElement("div");
      row.className = "msg" + (isReply ? " msg--reply" : "");
      const av = trip.members.find((x) => x.id === m.authorId);
      const col = av ? av.color : "#6366f1";
      const an = authorName(trip, m.authorId);
      row.innerHTML =
        '<span class="avatar avatar--sm" style="background:' +
        escapeHtml(col) +
        '">' +
        escapeHtml(avatarInitial(an)) +
        '</span><div class="msg__bubble">' +
        '<div class="msg__author">' +
        escapeHtml(an) +
        "</div>" +
        '<p class="msg__text">' +
        escapeHtml(m.text) +
        "</p>" +
        '<div class="msg__time">' +
        escapeHtml(formatTime(m.ts)) +
        '</div><button type="button" class="btn btn--sm btn--secondary" style="margin-top:0.35rem;" data-reply="' +
        escapeHtml(m.id) +
        '">Reply</button></div>';
      return row;
    }

    roots.forEach((msg) => {
      container.appendChild(bubble(msg, false));
      trip.messages
        .filter((c) => c.parentId === msg.id)
        .sort((a, b) => a.ts - b.ts)
        .forEach((ch) => container.appendChild(bubble(ch, true)));
    });
  }

  function simulatePeerActivity(trip, state, container) {
    const indicator = document.getElementById("chat-typing");
    if (!indicator) return;
    const myMid = global.Permissions.getMemberIdForSession(trip, state);
    const others = trip.members.filter((m) => m.status === "active" && m.id !== myMid);
    if (!others.length) return;
    const pick = others[Math.floor(Math.random() * others.length)];
    indicator.classList.add("is-visible");
    indicator.textContent = pick.name.split(" ")[0] + " is typing…";
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
      indicator.classList.remove("is-visible");
    }, 1800);
  }

  function render(container, state) {
    const trip = state.activeTripId ? getTrip(state, state.activeTripId) : null;
    const participant = trip && global.Permissions.isTripParticipant(trip, state);
    const mem = trip && global.Permissions.findActiveMember(trip, state);

    const actions = document.getElementById("topbar-actions");
    if (actions) {
      actions.innerHTML =
        '<button type="button" class="btn btn--secondary" id="btn-chat-sim">Simulate activity</button>';
    }

    if (!trip) {
      container.innerHTML =
        '<div class="empty"><div class="empty__icon">💬</div><p>Select a trip to open chat.</p></div>';
      return;
    }

    if (!participant || !mem) {
      container.innerHTML =
        '<div class="empty"><div class="empty__icon">🔒</div><p>You are not a member of this trip.</p></div>';
      return;
    }

    container.innerHTML =
      '<div class="chat-layout">' +
      '<div class="card">' +
      '<div class="card__header"><h3 class="card__title">Trip conversation</h3><span class="badge badge--info">Workspace</span></div>' +
      '<div class="thread-list" id="chat-threads"></div>' +
      '<div class="typing-indicator" id="chat-typing"></div>' +
      '<div class="chat-composer">' +
      '<input type="hidden" id="chat-reply-to" value="" />' +
      '<textarea id="chat-input" class="textarea" placeholder="Share an update…" rows="3"></textarea>' +
      '<div style="display:flex;gap:0.5rem;flex-wrap:wrap;">' +
      '<button type="button" class="btn btn--primary" id="chat-send">Send message</button>' +
      '<button type="button" class="btn btn--secondary" id="chat-clear-reply">Clear reply target</button>' +
      "</div></div></div>" +
      '<div class="card">' +
      "<h3 class=\"card__title\">Tips</h3>" +
      '<p class="muted" style="font-size:0.88rem;">Use <strong>Reply</strong> to keep threads tidy. Everything is stored in your browser session.</p>' +
      '<button type="button" class="btn btn--secondary" style="margin-top:0.75rem;width:100%;" id="btn-seed-msg">Add demo thread</button>' +
      "</div></div>";

    const threadEl = container.querySelector("#chat-threads");
    renderMessages(trip, threadEl);

    function send() {
      const input = container.querySelector("#chat-input");
      const reply = container.querySelector("#chat-reply-to");
      const text = input.value.trim();
      if (!text) {
        toast("Type a message first");
        return;
      }
      trip.messages.push({
        id: generateId("msg"),
        parentId: reply.value || null,
        authorId: mem.id,
        text,
        ts: Date.now(),
      });
      global.Storage.save(state);
      input.value = "";
      reply.value = "";
      input.placeholder = "Share an update…";
      toast("Message sent");
      global.App.refresh();
    }

    container.querySelector("#chat-send")?.addEventListener("click", send);
    container.querySelector("#chat-input")?.addEventListener("keydown", (e) => {
      if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) send();
    });
    container.querySelector("#chat-clear-reply")?.addEventListener("click", () => {
      container.querySelector("#chat-reply-to").value = "";
      container.querySelector("#chat-input").placeholder = "Share an update…";
      toast("Reply target cleared");
    });

    document.getElementById("btn-chat-sim")?.addEventListener("click", () => {
      simulatePeerActivity(trip, state, threadEl);
      setTimeout(() => {
        const myMid = global.Permissions.getMemberIdForSession(trip, state);
        const others = trip.members.filter((m) => m.status === "active" && m.id !== myMid);
        const pick = others[Math.floor(Math.random() * others.length)];
        if (!pick) return;
        const lines = [
          "Just shared notes in Documents 📎",
          "Updated my RSVP — check the tab",
          "Can we lock Day 2 dinner time?",
        ];
        trip.messages.push({
          id: generateId("msg"),
          parentId: null,
          authorId: pick.id,
          text: lines[Math.floor(Math.random() * lines.length)],
          ts: Date.now(),
        });
        global.Storage.save(state);
        global.App.refresh();
      }, 900);
    });

    container.querySelector("#btn-seed-msg")?.addEventListener("click", () => {
      trip.messages.push({
        id: generateId("msg"),
        parentId: null,
        authorId: mem.id,
        text: "Demo: Parking is P2 under the hotel — code 4821.",
        ts: Date.now(),
      });
      global.Storage.save(state);
      toast("Demo message added");
      global.App.refresh();
    });
  }

  global.Chat = { render };
})(typeof window !== "undefined" ? window : globalThis);