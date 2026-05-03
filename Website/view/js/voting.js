/**
 * Voting: polls, one vote per trip member, organizer manages polls
 */
(function (global) {
  const { escapeHtml, openModal, toast } = global.UI;
  const { generateId, getTrip } = global.Storage;
  const P = global.Permissions;

  function totalVotes(options) {
    return options.reduce((s, o) => s + (o.votes ? o.votes.length : 0), 0);
  }

  function renderPoll(poll, state, trip, container, mid, isOrg) {
    const tv = totalVotes(poll.options);
    let votedOpt = null;
    poll.options.forEach((o) => {
      if (mid && o.votes && o.votes.indexOf(mid) >= 0) votedOpt = o.id;
    });

    const el = document.createElement("div");
    el.className = "card";
    let bars = "";
    poll.options.forEach((o) => {
      const c = o.votes ? o.votes.length : 0;
      const pct = tv ? Math.round((c / tv) * 100) : 0;
      const canVote = !!mid;
      bars +=
        '<div class="poll-option">' +
        '<div class="poll-option__row"><span>' +
        escapeHtml(o.text) +
        "</span><span>" +
        pct +
        "% · " +
        c +
        " votes</span></div>" +
        '<div class="poll-option__bar-wrap"><div class="poll-option__bar" style="width:' +
        pct +
        '%"></div></div>' +
        '<button type="button" class="btn btn--sm ' +
        (votedOpt === o.id ? "btn--primary" : "btn--secondary") +
        '" style="margin-top:0.35rem;" data-vote-poll="' +
        escapeHtml(poll.id) +
        '" data-vote-opt="' +
        escapeHtml(o.id) +
        '"' +
        (canVote ? "" : " disabled") +
        ">" +
        (votedOpt === o.id ? "Your vote" : "Vote") +
        "</button></div>";
    });

    el.innerHTML =
      '<div class="card__header">' +
      '<h3 class="card__title">' +
      escapeHtml(poll.question) +
      "</h3>" +
      (isOrg
        ? '<button type="button" class="btn btn--sm btn--danger" data-del-poll="' +
          escapeHtml(poll.id) +
          '">Delete</button>'
        : "") +
      "</div>" +
      bars +
      (!mid ? '<p class="muted" style="margin-top:0.5rem;font-size:0.85rem;">Join this trip to vote.</p>' : "");

    el.querySelectorAll("[data-vote-poll]").forEach((btn) => {
      btn.addEventListener("click", () => {
        if (!mid) return;
        const pid = btn.getAttribute("data-vote-poll");
        const oid = btn.getAttribute("data-vote-opt");
        const po = trip.polls.find((x) => x.id === pid);
        if (!po) return;
        po.options.forEach((o) => {
          o.votes = (o.votes || []).filter((v) => v !== mid);
        });
        const opt = po.options.find((o) => o.id === oid);
        if (opt) {
          opt.votes = opt.votes || [];
          opt.votes.push(mid);
        }
        global.Storage.save(state);
        toast("Vote recorded");
        global.App.refresh();
      });
    });

    el.querySelector("[data-del-poll]")?.addEventListener("click", () => {
      if (!isOrg) return;
      if (!confirm("Delete this poll?")) return;
      trip.polls = trip.polls.filter((p) => p.id !== poll.id);
      global.Storage.save(state);
      toast("Poll deleted");
      global.App.refresh();
    });

    container.appendChild(el);
  }

  function openNewPoll(state, trip) {
    if (!P.canManageTripSettings(trip, state)) {
      toast("Only organizers can create polls");
      return;
    }
    const body = document.createElement("div");
    body.className = "form-grid";
    body.innerHTML =
      '<div class="form-row"><label>Question</label><input id="p-q" class="input" placeholder="Where should we eat?" /></div>' +
      '<div class="form-row"><label>Options (one per line)</label><textarea id="p-opts" class="textarea" placeholder="Option A\nOption B\nOption C"></textarea></div>';

    const footer = document.createElement("div");
    footer.style.display = "flex";
    footer.style.gap = "0.5rem";
    footer.style.justifyContent = "flex-end";
    const cancel = document.createElement("button");
    cancel.type = "button";
    cancel.className = "btn btn--secondary";
    cancel.textContent = "Cancel";
    const create = document.createElement("button");
    create.type = "button";
    create.className = "btn btn--primary";
    create.textContent = "Create poll";
    footer.appendChild(cancel);
    footer.appendChild(create);

    const dlg = openModal({ title: "New poll", body, footer });
    cancel.addEventListener("click", () => dlg.close());
    create.addEventListener("click", () => {
      const q = body.querySelector("#p-q").value.trim();
      const lines = body
        .querySelector("#p-opts")
        .value.split("\n")
        .map((l) => l.trim())
        .filter(Boolean);
      if (!q || lines.length < 2) {
        toast("Need a question and at least 2 options");
        return;
      }
      trip.polls.push({
        id: generateId("poll"),
        question: q,
        options: lines.map((text) => ({ id: generateId("opt"), text, votes: [] })),
      });
      global.Storage.save(state);
      toast("Poll created");
      dlg.close();
      global.App.refresh();
    });
  }

  function render(container, state) {
    const trip = state.activeTripId ? getTrip(state, state.activeTripId) : null;
    const participant = trip && P.isTripParticipant(trip, state);
    const isOrg = trip && P.isOrganizer(trip, state);
    const mid = trip ? P.getMemberIdForSession(trip, state) : null;

    const actions = document.getElementById("topbar-actions");
    if (actions) {
      const showNew = isOrg;
      actions.innerHTML = showNew
        ? '<button type="button" class="btn btn--primary" id="btn-new-poll">+ New poll</button>'
        : '<span class="muted" style="font-size:0.85rem;">Organizers create polls</span>';
    }

    if (!trip) {
      container.innerHTML =
        '<div class="empty"><div class="empty__icon">◇</div><p>Select a trip to vote.</p></div>';
      return;
    }

    if (!participant) {
      container.innerHTML =
        '<div class="empty"><div class="empty__icon">🔒</div><p>You are not a member of this trip.</p></div>';
      return;
    }

    container.innerHTML = '<div id="polls-wrap"></div>';
    const wrap = container.querySelector("#polls-wrap");
    if (!trip.polls.length) {
      wrap.innerHTML =
        '<div class="card"><p class="muted">No polls yet. ' +
        (isOrg ? "Create one to decide as a group." : "An organizer can add a poll.") +
        "</p></div>";
    } else {
      trip.polls.forEach((p) => renderPoll(p, state, trip, wrap, mid, isOrg));
    }

    document.getElementById("btn-new-poll")?.addEventListener("click", () => openNewPoll(state, trip));
  }

  global.Voting = { render };
})(typeof window !== "undefined" ? window : globalThis);
