/**
 * Trips: CRUD, dashboard (permissions-aware)
 */
(function (global) {
  const { escapeHtml, openModal, toast, avatarInitial } = global.UI;
  const { generateId, getTrip, pickColor } = global.Storage;
  const P = global.Permissions;

  function syncTripSelect(state) {
    const sel = document.getElementById("trip-select");
    if (!sel) return;
    const acc = P.getAccessibleTrips(state);
    const cur = state.activeTripId;
    sel.innerHTML = acc
      .map(
        (t) =>
          '<option value="' +
          escapeHtml(t.id) +
          '"' +
          (t.id === cur ? " selected" : "") +
          ">" +
          escapeHtml(t.name) +
          "</option>"
      )
      .join("");
    if (!acc.length) sel.innerHTML = '<option value="">No trips yet</option>';
  }

  function tripDateRange(t) {
    if (!t.startDate && !t.endDate) return "Dates TBD";
    return (t.startDate || "…") + " → " + (t.endDate || "…");
  }

  function renderMemberRow(m) {
    const roleLabel = m.role === "organizer" ? "Organizer" : "Member";
    return (
      '<div class="card" style="padding:0.85rem;display:flex;align-items:center;gap:0.75rem;">' +
      '<span class="avatar" style="background:' +
      escapeHtml(m.color) +
      '">' +
      escapeHtml(avatarInitial(m.name)) +
      "</span>" +
      '<div style="flex:1;min-width:0;">' +
      '<div style="font-weight:700;font-size:0.9rem;">' +
      escapeHtml(m.name) +
      "</div>" +
      '<div class="muted" style="font-size:0.8rem;">' +
      escapeHtml(roleLabel) +
      " · " +
      escapeHtml(m.status || "active") +
      "</div>" +
      "</div>" +
      "</div>"
    );
  }

  function openTripForm(state, trip) {
    const isEdit = !!trip;
    if (isEdit && !P.canManageTripSettings(trip, state)) {
      toast("Only organizers can edit trip settings");
      return;
    }

    const body = document.createElement("div");
    body.className = "form-grid";
    body.innerHTML =
      '<div class="form-row"><label for="f-name">Trip name</label>' +
      '<input id="f-name" class="input" required value="' +
      escapeHtml(trip ? trip.name : "") +
      '" /></div>' +
      '<div class="form-row"><label for="f-desc">Description</label>' +
      '<textarea id="f-desc" class="textarea">' +
      escapeHtml(trip ? trip.description : "") +
      "</textarea></div>" +
      '<div class="form-row"><label for="f-start">Start date</label>' +
      '<input id="f-start" type="date" class="input" value="' +
      escapeHtml(trip ? trip.startDate : "") +
      '" /></div>' +
      '<div class="form-row"><label for="f-end">End date</label>' +
      '<input id="f-end" type="date" class="input" value="' +
      escapeHtml(trip ? trip.endDate : "") +
      '" /></div>' +
      '<div class="form-row"><label for="f-budget">Trip budget (for progress)</label>' +
      '<input id="f-budget" type="number" min="0" step="1" class="input" value="' +
      escapeHtml(trip ? trip.budget || 0 : 3000) +
      '" /></div>';

    const footer = document.createElement("div");
    footer.style.display = "flex";
    footer.style.gap = "0.5rem";
    footer.style.justifyContent = "flex-end";
    const btnCancel = document.createElement("button");
    btnCancel.type = "button";
    btnCancel.className = "btn btn--secondary";
    btnCancel.textContent = "Cancel";
    const btnSave = document.createElement("button");
    btnSave.type = "button";
    btnSave.className = "btn btn--primary";
    btnSave.textContent = isEdit ? "Save trip" : "Create trip";
    footer.appendChild(btnCancel);
    footer.appendChild(btnSave);

    const dlg = openModal({
      title: isEdit ? "Edit trip" : "New trip",
      body: body,
      footer: footer,
    });

    function close() {
      dlg.close();
    }
    btnCancel.addEventListener("click", close);
    btnSave.addEventListener("click", () => {
      const name = body.querySelector("#f-name").value.trim();
      if (!name) {
        toast("Please enter a trip name");
        return;
      }
      const description = body.querySelector("#f-desc").value.trim();
      const startDate = body.querySelector("#f-start").value;
      const endDate = body.querySelector("#f-end").value;
      const budget = parseFloat(body.querySelector("#f-budget").value) || 0;

      const profile = P.getCurrentUser(state);
      if (!profile) {
        toast("Sign in required");
        return;
      }

      if (isEdit) {
        trip.name = name;
        trip.description = description;
        trip.startDate = startDate;
        trip.endDate = endDate;
        trip.budget = budget;
        global.Storage.save(state);
        toast("Trip updated");
      } else {
        const memberId = generateId("m");
        const nt = {
          id: generateId("trip"),
          name,
          description,
          startDate,
          endDate,
          budget,
          members: [
            {
              id: memberId,
              userId: profile.id,
              name: profile.name,
              email: profile.email,
              role: "organizer",
              status: "active",
              color: pickColor(profile.id),
            },
          ],
          pendingInvites: [],
          itineraryDays: [],
          polls: [],
          rsvp: {},
          expenses: [],
          messages: [],
          documents: [],
          checklist: [],
        };
        nt.rsvp[memberId] = "going";
        const sd = startDate ? new Date(startDate) : new Date();
        for (let d = 0; d < 3; d++) {
          const day = new Date(sd);
          day.setDate(day.getDate() + d);
          nt.itineraryDays.push({
            id: generateId("day"),
            dayNumber: d + 1,
            label: "Day " + (d + 1),
            activities: [],
          });
        }
        state.trips.push(nt);
        state.activeTripId = nt.id;
        global.Storage.save(state);
        toast("Trip created");
      }
      close();
      global.App.refresh();
    });
  }

  function deleteTrip(state, tripId) {
    const t = getTrip(state, tripId);
    if (!t || !P.canManageTripSettings(t, state)) {
      toast("Only an organizer can delete this trip");
      return;
    }
    if (!confirm("Delete this trip and all workspace data?")) return;
    state.trips = state.trips.filter((x) => x.id !== tripId);
    if (state.activeTripId === tripId) state.activeTripId = state.trips[0] ? state.trips[0].id : null;
    global.Permissions.ensureActiveTripAccessible(state);
    global.Storage.save(state);
    toast("Trip deleted");
    global.App.refresh();
  }

  function renderDashboard(container, state) {
    const trip = state.activeTripId ? getTrip(state, state.activeTripId) : null;
    const participant = trip && P.isTripParticipant(trip, state);
    const canManage = trip && P.canManageTripSettings(trip, state);

    const actions = document.getElementById("topbar-actions");
    if (actions) {
      let html =
        '<button type="button" class="btn btn--primary" id="dash-new-trip">+ New trip</button>';
      if (trip && canManage) {
        html +=
          '<button type="button" class="btn btn--secondary" id="dash-edit-trip">Edit trip</button>' +
          '<button type="button" class="btn btn--danger" id="dash-del-trip">Delete</button>';
      }
      actions.innerHTML = html;
      actions.querySelector("#dash-new-trip")?.addEventListener("click", () => openTripForm(state, null));
      actions.querySelector("#dash-edit-trip")?.addEventListener("click", () => trip && openTripForm(state, trip));
      actions.querySelector("#dash-del-trip")?.addEventListener("click", () => trip && deleteTrip(state, trip.id));
    }

    if (!trip || !participant) {
      container.innerHTML =
        '<div class="empty"><div class="empty__icon">🧭</div><p>' +
        (!trip
          ? "No active trip. Create one to get started."
          : "You are not a member of this trip. Ask an organizer for an invite.") +
        '</p><button type="button" class="btn btn--primary" id="dash-empty-create">Create trip</button>' +
        (!participant && trip
          ? ' <button type="button" class="btn btn--secondary" id="dash-goto-members">Members</button>'
          : "") +
        "</div>";
      container.querySelector("#dash-empty-create")?.addEventListener("click", () => openTripForm(state, null));
      container.querySelector("#dash-goto-members")?.addEventListener("click", () => global.App.navigate("members"));
      return;
    }

    const totalSpent = trip.expenses.reduce((s, e) => s + (e.amount || 0), 0);
    const budget = trip.budget || 0;
    const pct = budget > 0 ? Math.min(100, Math.round((totalSpent / budget) * 100)) : 0;
    const activeMembers = trip.members.filter((m) => m.status === "active");
    const rsvpGoing = activeMembers.filter((m) => (trip.rsvp || {})[m.id] === "going").length;
    const openPolls = trip.polls.length;

    container.innerHTML =
      '<div class="tabs" id="dash-tabs">' +
      '<button type="button" class="tab is-active" data-tab="overview">Overview</button>' +
      '<button type="button" class="tab" data-tab="trips">All trips</button>' +
      '<button type="button" class="tab" data-tab="members">Members</button>' +
      "</div>" +
      '<div class="tab-panel" data-tab-panel="overview">' +
      '<div class="grid grid--3">' +
      '<div class="card card--gradient">' +
      '<div class="card__header"><h3 class="card__title">Trip window</h3><span class="badge badge--info">Live</span></div>' +
      "<p class=\"muted\">" +
      escapeHtml(tripDateRange(trip)) +
      "</p>" +
      '<p class="muted" style="margin-top:0.5rem;">' +
      escapeHtml(trip.description || "No description") +
      "</p>" +
      "</div>" +
      '<div class="card">' +
      '<div class="card__header"><h3 class="card__title">Budget pulse</h3></div>' +
      '<p style="margin:0 0 0.5rem;font-size:0.9rem;"><strong>$' +
      totalSpent.toLocaleString() +
      "</strong> of <strong>$" +
      budget.toLocaleString() +
      "</strong></p>" +
      '<div class="progress"><div class="progress__bar" style="width:' +
      pct +
      '%"></div></div>' +
      '<p class="muted" style="margin-top:0.5rem;font-size:0.8rem;">' +
      pct +
      "% of budget used · Expenses module has details</p>" +
      "</div>" +
      '<div class="card">' +
      '<div class="card__header"><h3 class="card__title">Collaboration</h3></div>' +
      "<p class=\"muted\" style=\"margin:0 0 0.5rem;\">" +
      rsvpGoing +
      " going · " +
      openPolls +
      " active polls</p>" +
      '<div class="avatar-row">' +
      activeMembers
        .slice(0, 5)
        .map(
          (m) =>
            '<span class="avatar avatar--sm" style="background:' +
            escapeHtml(m.color) +
            '" title="' +
            escapeHtml(m.name) +
            '">' +
            escapeHtml(avatarInitial(m.name)) +
            "</span>"
        )
        .join("") +
      "</div>" +
      "</div>" +
      "</div>" +
      '<div class="card" style="margin-top:1rem;">' +
      '<div class="card__header"><h3 class="card__title">Quick actions</h3></div>' +
      '<div style="display:flex;flex-wrap:wrap;gap:0.5rem;">' +
      '<button type="button" class="btn btn--secondary" data-go="members">Team &amp; invites</button>' +
      '<button type="button" class="btn btn--secondary" data-go="itinerary">Open itinerary</button>' +
      '<button type="button" class="btn btn--secondary" data-go="voting">Open voting</button>' +
      '<button type="button" class="btn btn--secondary" data-go="expenses">Log expense</button>' +
      '<button type="button" class="btn btn--secondary" data-go="chat">Open chat</button>' +
      "</div></div>" +
      "</div>" +
      '<div class="tab-panel" data-tab-panel="trips" hidden>' +
      '<div class="grid grid--2" id="trip-card-grid"></div>' +
      "</div>" +
      '<div class="tab-panel" data-tab-panel="members" hidden>' +
      '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">' +
      '<h2 class="section-title" style="margin:0;">Team snapshot</h2>' +
      '<button type="button" class="btn btn--primary" data-go="members-full">Open members panel</button></div>' +
      '<div class="grid grid--2" id="member-grid"></div>' +
      "</div>";

    global.UI.initTabs(container.querySelector("#dash-tabs"));

    const acc = P.getAccessibleTrips(state);
    const grid = container.querySelector("#trip-card-grid");
    acc.forEach((t) => {
      const canT = P.canManageTripSettings(t, state);
      const el = document.createElement("div");
      el.className = "card";
      el.innerHTML =
        '<div class="card__header">' +
        '<h3 class="card__title">' +
        escapeHtml(t.name) +
        "</h3>" +
        (t.id === state.activeTripId ? '<span class="badge badge--confirmed">Active</span>' : "") +
        "</div>" +
        "<p class=\"muted\">" +
        escapeHtml(tripDateRange(t)) +
        "</p>" +
        '<div style="margin-top:0.75rem;display:flex;flex-wrap:wrap;gap:0.4rem;">' +
        '<button type="button" class="btn btn--sm btn--primary" data-select="' +
        escapeHtml(t.id) +
        '">Open</button>' +
        (canT
          ? '<button type="button" class="btn btn--sm btn--secondary" data-edit="' +
            escapeHtml(t.id) +
            '">Edit</button>' +
            '<button type="button" class="btn btn--sm btn--danger" data-del="' +
            escapeHtml(t.id) +
            '">Delete</button>'
          : '<span class="muted" style="font-size:0.8rem;align-self:center;">Member access</span>') +
        "</div>";
      grid.appendChild(el);
    });

    grid.querySelectorAll("[data-select]").forEach((btn) => {
      btn.addEventListener("click", () => {
        state.activeTripId = btn.getAttribute("data-select");
        global.Storage.save(state);
        syncTripSelect(state);
        global.App.refresh();
        toast("Trip selected");
      });
    });
    grid.querySelectorAll("[data-edit]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const id = btn.getAttribute("data-edit");
        const tr = getTrip(state, id);
        if (tr) openTripForm(state, tr);
      });
    });
    grid.querySelectorAll("[data-del]").forEach((btn) => {
      btn.addEventListener("click", () => deleteTrip(state, btn.getAttribute("data-del")));
    });

    const mgrid = container.querySelector("#member-grid");
    activeMembers.forEach((m) => {
      const wrap = document.createElement("div");
      wrap.innerHTML = renderMemberRow(m);
      mgrid.appendChild(wrap.firstElementChild);
    });

    container.querySelector("[data-go=\"members-full\"]")?.addEventListener("click", () =>
      global.App.navigate("members")
    );

    container.querySelectorAll("[data-go]").forEach((b) => {
      const g = b.getAttribute("data-go");
      if (g === "members-full") return;
      b.addEventListener("click", () => global.App.navigate(g));
    });
  }

  global.Trips = {
    renderDashboard,
    openTripForm,
    syncTripSelect,
    getTrip,
  };
})(typeof window !== "undefined" ? window : globalThis);
