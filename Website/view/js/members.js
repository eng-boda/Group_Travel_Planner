/**
 * Members panel: invites, roles, promote/demote/remove
 */
(function (global) {
  const { escapeHtml, toast, avatarInitial } = global.UI;
  const { generateId, getTrip, save, pickColor } = global.Storage;
  const P = global.Permissions;

  function normalizeInviteInput(raw) {
    return String(raw || "").trim();
  }

  function findUserByInvite(state, inv) {
    const users = state.users || [];
    const lower = inv.toLowerCase();
    return (
      users.find((u) => u.email && u.email.toLowerCase() === lower) ||
      users.find((u) => u.name && u.name.toLowerCase() === lower) ||
      null
    );
  }

  function cancelInvite(trip, inviteId) {
    trip.pendingInvites = trip.pendingInvites.filter((x) => x.id !== inviteId);
  }

  function inviteToTrip(state, trip, rawInput) {
    state.users = state.users || [];
    const inv = normalizeInviteInput(rawInput);
    if (!inv) {
      toast("Enter an email or name");
      return;
    }
    const uid = P.getSessionUserId(state);
    const existingMember = trip.members.find(
      (m) =>
        (m.email && m.email.toLowerCase() === inv.toLowerCase()) ||
        (m.userId &&
          state.users.find((u) => u.id === m.userId && u.email && u.email.toLowerCase() === inv.toLowerCase()))
    );
    if (existingMember && existingMember.status === "active") {
      toast("That person is already on this trip");
      return;
    }

    const user = findUserByInvite(state, inv);
    if (user) {
      const already = trip.members.some((m) => m.userId === user.id && m.status === "active");
      if (already) {
        toast("User is already a member");
        return;
      }
      trip.members.push({
        id: generateId("m"),
        userId: user.id,
        name: user.name,
        email: user.email,
        role: "member",
        status: "active",
        color: pickColor(user.id),
      });
      save(state);
      toast(user.name + " added to the trip");
      return;
    }

    const dup = trip.pendingInvites.some(
      (p) => p.email.toLowerCase() === inv.toLowerCase() && p.status === "pending"
    );
    if (dup) {
      toast("Invitation already pending for that address");
      return;
    }

    trip.pendingInvites.push({
      id: generateId("inv"),
      email: inv,
      status: "pending",
      invitedAt: Date.now(),
      invitedByUserId: uid,
    });
    save(state);
    toast("Invitation pending — no account match for \"" + inv + "\"");
  }

  function promote(state, trip, memberId) {
    const m = trip.members.find((x) => x.id === memberId);
    if (!m || m.status !== "active") return;
    m.role = "organizer";
    save(state);
    toast(m.name + " is now an Organizer");
    global.App.refresh();
  }

  function demote(state, trip, memberId) {
    const m = trip.members.find((x) => x.id === memberId);
    if (!m || m.status !== "active") return;
    if (m.role !== "organizer") return;
    if (P.countOrganizers(trip) <= 1) {
      toast("Keep at least one Organizer on the trip");
      return;
    }
    m.role = "member";
    save(state);
    toast(m.name + " is now a Member");
    global.App.refresh();
  }

  function stripMemberFromTripData(trip, memberId) {
    if (trip.rsvp && typeof trip.rsvp === "object") delete trip.rsvp[memberId];
    (trip.polls || []).forEach((poll) => {
      (poll.options || []).forEach((o) => {
        o.votes = (o.votes || []).filter((v) => v !== memberId);
      });
    });
    (trip.expenses || []).forEach((e) => {
      if (e.splits) delete e.splits[memberId];
      if (e.paidBy === memberId && trip.members.some((x) => x.id !== memberId && x.status === "active")) {
        const alt = trip.members.find((x) => x.id !== memberId && x.status === "active");
        if (alt) e.paidBy = alt.id;
      }
    });
    (trip.checklist || []).forEach((c) => {
      if (c.assigneeId === memberId) c.assigneeId = null;
    });
  }

  function removeMember(state, trip, memberId) {
    const m = trip.members.find((x) => x.id === memberId);
    if (!m) return;
    if (m.role === "organizer" && P.countOrganizers(trip) <= 1) {
      toast("Assign another Organizer before removing this person");
      return;
    }
    trip.members = trip.members.filter((x) => x.id !== memberId);
    stripMemberFromTripData(trip, memberId);
    save(state);
    toast(m.name + " removed from trip");
    global.App.refresh();
  }

  function render(container, state) {
    const trip = state.activeTripId ? getTrip(state, state.activeTripId) : null;
    const actions = document.getElementById("topbar-actions");
    const canManage = trip && P.canManageMembers(trip, state);
    const participant = trip && P.isTripParticipant(trip, state);

    if (actions) {
      actions.innerHTML = "";
    }

    if (!trip) {
      container.innerHTML =
        '<div class="empty"><div class="empty__icon">👥</div><p>Select a trip to manage members.</p></div>';
      return;
    }

    if (!participant) {
      container.innerHTML =
        '<div class="empty"><div class="empty__icon">🔒</div><p>You are not a member of this trip.</p></div>';
      return;
    }

    const sessionMem = P.findActiveMember(trip, state);

    container.innerHTML =
      '<div class="members-hero card card--gradient">' +
      '<div class="members-hero__text">' +
      "<h2 class=\"section-title\" style=\"margin:0 0 0.35rem;\">Team &amp; access</h2>" +
      '<p class="muted" style="margin:0;">Organizers manage invites, roles, and trip settings. Members collaborate on planning.</p>' +
      "</div>" +
      (canManage
        ? '<div class="members-invite">' +
          '<input type="text" id="invite-input" class="input" placeholder="Email or exact account name" />' +
          '<button type="button" class="btn btn--primary" id="btn-send-invite">Invite</button>' +
          "</div>"
        : '<p class="muted" style="margin:0;font-size:0.88rem;">Only organizers can invite people.</p>') +
      "</div>" +
      '<div class="grid grid--2" style="margin-top:1rem;" id="member-cards"></div>' +
      '<div class="card" style="margin-top:1rem;" id="pending-card">' +
      '<h3 class="card__title">Pending invitations</h3>' +
      '<ul class="list-plain" id="pending-list"></ul></div>';

    const grid = container.querySelector("#member-cards");
    const activeMembers = trip.members.filter((m) => m.status === "active");

    activeMembers.forEach((m) => {
      const isOrg = m.role === "organizer";
      const isSelf = sessionMem && sessionMem.id === m.id;
      const orgCount = P.countOrganizers(trip);
      const canPromote = canManage && !isOrg;
      const canDemote = canManage && isOrg && orgCount > 1;
      const canRemoveMember = canManage && !(isOrg && orgCount <= 1);

      const card = document.createElement("div");
      card.className = "member-card card" + (isOrg ? " member-card--organizer" : "");
      card.innerHTML =
        '<div class="member-card__top">' +
        '<span class="avatar" style="background:' +
        escapeHtml(m.color) +
        '">' +
        escapeHtml(avatarInitial(m.name)) +
        "</span>" +
        '<div class="member-card__info">' +
        '<div class="member-card__name">' +
        escapeHtml(m.name) +
        (isSelf ? ' <span class="badge badge--info">You</span>' : "") +
        "</div>" +
        '<div class="muted" style="font-size:0.82rem;">' +
        escapeHtml(m.email || "No email on file") +
        "</div>" +
        '<div style="margin-top:0.35rem;display:flex;gap:0.35rem;flex-wrap:wrap;">' +
        '<span class="badge ' +
        (isOrg ? "badge--confirmed" : "badge--info") +
        '">' +
        (isOrg ? "Organizer" : "Member") +
        "</span>" +
        '<span class="badge badge--draft">Active</span>' +
        "</div></div></div>" +
        '<div class="member-card__actions">' +
        '<button type="button" class="btn btn--sm btn--secondary" data-promote="' +
        escapeHtml(m.id) +
        '"' +
        (canPromote ? "" : " disabled") +
        ">Promote</button>" +
        '<button type="button" class="btn btn--sm btn--secondary" data-demote="' +
        escapeHtml(m.id) +
        '"' +
        (canDemote ? "" : " disabled") +
        ">Demote</button>" +
        '<button type="button" class="btn btn--sm btn--danger" data-remove="' +
        escapeHtml(m.id) +
        '"' +
        (canRemoveMember ? "" : " disabled") +
        ">Remove</button>" +
        "</div>" +
        (!canManage ? '<p class="muted" style="margin:0.5rem 0 0;font-size:0.78rem;">Only organizers can change roles.</p>' : "");

      grid.appendChild(card);

      card.querySelector("[data-promote]")?.addEventListener("click", () => {
        if (!canPromote) return;
        promote(state, trip, m.id);
      });
      card.querySelector("[data-demote]")?.addEventListener("click", () => {
        if (!canDemote) return;
        demote(state, trip, m.id);
      });
      card.querySelector("[data-remove]")?.addEventListener("click", () => {
        if (!canRemoveMember) return;
        if (!confirm("Remove " + m.name + " from this trip?")) return;
        removeMember(state, trip, m.id);
      });
    });

    const pendList = container.querySelector("#pending-list");
    const pending = trip.pendingInvites.filter((p) => p.status === "pending");
    if (!pending.length) {
      pendList.innerHTML = '<li class="muted">No pending invitations.</li>';
    } else {
      pendList.innerHTML = "";
      pending.forEach((p) => {
        const li = document.createElement("li");
        li.style.display = "flex";
        li.style.justifyContent = "space-between";
        li.style.alignItems = "center";
        li.style.gap = "0.5rem";
        li.style.flexWrap = "wrap";
        li.innerHTML =
          "<div><strong>" +
          escapeHtml(p.email) +
          '</strong><br/><span class="muted" style="font-size:0.78rem;">Pending · sent ' +
          new Date(p.invitedAt).toLocaleString() +
          "</span></div>" +
          (canManage
            ? '<button type="button" class="btn btn--sm btn--secondary" data-cancel-inv="' +
              escapeHtml(p.id) +
              '">Cancel</button>'
            : "");
        pendList.appendChild(li);
      });
      pendList.querySelectorAll("[data-cancel-inv]").forEach((btn) => {
        btn.addEventListener("click", () => {
          cancelInvite(trip, btn.getAttribute("data-cancel-inv"));
          save(state);
          toast("Invitation cancelled");
          global.App.refresh();
        });
      });
    }

    container.querySelector("#btn-send-invite")?.addEventListener("click", () => {
      const v = container.querySelector("#invite-input").value;
      inviteToTrip(state, trip, v);
      global.App.refresh();
    });
  }

  global.Members = { render, inviteToTrip };
})(typeof window !== "undefined" ? window : globalThis);
