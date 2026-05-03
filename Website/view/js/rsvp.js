/**
 * RSVP: going / maybe / not going per active trip member
 */
(function (global) {
  const { escapeHtml, toast } = global.UI;
  const { getTrip } = global.Storage;
  const P = global.Permissions;

  function render(container, state) {
    const trip = state.activeTripId ? getTrip(state, state.activeTripId) : null;
    const actions = document.getElementById("topbar-actions");
    if (actions) actions.innerHTML = "";

    if (!trip) {
      container.innerHTML =
        '<div class="empty"><div class="empty__icon">✓</div><p>Select a trip to manage RSVP.</p></div>';
      return;
    }

    const mem = P.findActiveMember(trip, state);
    if (!mem) {
      container.innerHTML =
        '<div class="empty"><div class="empty__icon">🔒</div><p>You are not a member of this trip.</p></div>';
      return;
    }

    const activeMembers = trip.members.filter((m) => m.status === "active");

    const statuses = ["going", "maybe", "not_going"];
    const labels = { going: "Going", maybe: "Maybe", not_going: "Not going" };

    function counts() {
      const c = { going: 0, maybe: 0, not_going: 0 };
      activeMembers.forEach((m) => {
        const s = trip.rsvp[m.id] || "maybe";
        if (c[s] !== undefined) c[s]++;
        else c.maybe++;
      });
      return c;
    }

    function lists() {
      const out = { going: [], maybe: [], not_going: [] };
      activeMembers.forEach((m) => {
        const s = trip.rsvp[m.id] || "maybe";
        const key = s === "going" || s === "maybe" || s === "not_going" ? s : "maybe";
        out[key].push(m);
      });
      return out;
    }

    function setMyStatus(s) {
      trip.rsvp[mem.id] = s;
      global.Storage.save(state);
      toast("RSVP updated");
      render(container, state);
    }

    const c = counts();
    const L = lists();
    const mine = trip.rsvp[mem.id] || "maybe";

    container.innerHTML =
      '<div class="card card--gradient" style="margin-bottom:1rem;">' +
      '<div class="card__header"><h2 class="section-title" style="margin:0;">Your RSVP</h2></div>' +
      '<p class="muted" style="margin-bottom:0.75rem;">Your status is saved for this trip workspace.</p>' +
      '<div style="display:flex;flex-wrap:wrap;gap:0.5rem;">' +
      statuses
        .map(
          (s) =>
            '<button type="button" class="btn ' +
            (mine === s ? "btn--primary" : "btn--secondary") +
            '" data-rsvp="' +
            s +
            '">' +
            escapeHtml(labels[s]) +
            "</button>"
        )
        .join("") +
      "</div></div>" +
      '<div class="grid grid--3">' +
      '<div class="card"><h3 class="card__title">Going</h3>' +
      '<div style="font-size:2rem;font-weight:700;color:var(--success);margin:0.25rem 0;">' +
      c.going +
      "</div>" +
      "<ul class=\"list-plain\">" +
      L.going.map((m) => "<li>" + escapeHtml(m.name) + "</li>").join("") +
      "</ul></div>" +
      '<div class="card"><h3 class="card__title">Maybe</h3>' +
      '<div style="font-size:2rem;font-weight:700;color:var(--warning);margin:0.25rem 0;">' +
      c.maybe +
      "</div>" +
      "<ul class=\"list-plain\">" +
      L.maybe.map((m) => "<li>" + escapeHtml(m.name) + "</li>").join("") +
      "</ul></div>" +
      '<div class="card"><h3 class="card__title">Not going</h3>' +
      '<div style="font-size:2rem;font-weight:700;color:var(--danger);margin:0.25rem 0;">' +
      c.not_going +
      "</div>" +
      "<ul class=\"list-plain\">" +
      L.not_going.map((m) => "<li>" + escapeHtml(m.name) + "</li>").join("") +
      "</ul></div>" +
      "</div>";

    container.querySelectorAll("[data-rsvp]").forEach((btn) => {
      btn.addEventListener("click", () => setMyStatus(btn.getAttribute("data-rsvp")));
    });
  }

  global.RSVP = { render };
})(typeof window !== "undefined" ? window : globalThis);
