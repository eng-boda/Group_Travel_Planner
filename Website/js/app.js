/**
 * App shell: navigation, trip selector, section routing
 */
(function (global) {
  const SECTIONS = {
    dashboard: { title: "Trips Dashboard", eyebrow: "Workspace", render: (c, s) => global.Trips.renderDashboard(c, s) },
    members: { title: "Members", eyebrow: "Team", render: (c, s) => global.Members.render(c, s) },
    itinerary: { title: "Itinerary", eyebrow: "Planning", render: (c, s) => global.Itinerary.render(c, s) },
    voting: { title: "Voting", eyebrow: "Decisions", render: (c, s) => global.Voting.render(c, s) },
    rsvp: { title: "RSVP", eyebrow: "Attendance", render: (c, s) => global.RSVP.render(c, s) },
    expenses: { title: "Expenses", eyebrow: "Budget", render: (c, s) => global.Expenses.render(c, s) },
    chat: { title: "Chat", eyebrow: "Collaboration", render: (c, s) => global.Chat.render(c, s) },
    documents: { title: "Documents", eyebrow: "Files", render: (c, s) => global.Documents.render(c, s) },
    checklist: { title: "Checklist", eyebrow: "Execution", render: (c, s) => global.Checklist.render(c, s) },
  };

  let state = global.Storage.load();
  let listenersAttached = false;

  function setActiveNav(section) {
    document.querySelectorAll(".nav-item").forEach((btn) => {
      btn.classList.toggle("is-active", btn.getAttribute("data-section") === section);
    });
  }

  function updateUserChip() {
    state = global.Storage.load();
    const u = global.Permissions.getCurrentUser(state);
    const av = document.getElementById("user-avatar");
    const nm = document.getElementById("user-name");
    const em = document.getElementById("user-email");
    const roleEl = document.getElementById("user-trip-role");
    if (nm) nm.textContent = u ? u.name : "Guest";
    if (av) av.textContent = (u && u.name ? u.name : "Y").slice(0, 1).toUpperCase();
    if (em) em.textContent = u && u.email ? u.email : "";

    const trip = state.activeTripId ? global.Storage.getTrip(state, state.activeTripId) : null;
    const mem = trip && u ? global.Permissions.findActiveMember(trip, state) : null;
    if (roleEl) {
      if (!trip || !mem) roleEl.textContent = u ? "Select a trip" : "";
      else roleEl.textContent = mem.role === "organizer" ? "Organizer on this trip" : "Member on this trip";
    }

    const topSess = document.getElementById("topbar-session");
    if (topSess) {
      topSess.textContent = u ? "Signed in as " + u.name + " · " + u.email : "";
    }
  }

  function navigate(section) {
    if (!SECTIONS[section]) section = "dashboard";
    state.activeSection = section;
    global.Storage.save(state);
    setActiveNav(section);
    const meta = SECTIONS[section];
    const eyebrow = document.getElementById("topbar-section");
    const title = document.getElementById("topbar-title");
    if (eyebrow) eyebrow.textContent = meta.eyebrow;
    if (title) title.textContent = meta.title;
    const root = document.getElementById("view-root");
    if (!root) {
      console.warn("TripSync: #view-root not found (is the app shell visible?)");
      return;
    }
    root.innerHTML = "";
    meta.render(root, state);
    updateUserChip();
  }

  function refresh() {
    state = global.Storage.load();
    global.Permissions.ensureActiveTripAccessible(state);
    global.Storage.save(state);
    global.Trips.syncTripSelect(state);
    updateUserChip();
    navigate(state.activeSection || "dashboard");
  }

  function wireOnce() {
    if (listenersAttached) return;
    listenersAttached = true;

    document.querySelectorAll(".nav-item").forEach((btn) => {
      btn.addEventListener("click", () => navigate(btn.getAttribute("data-section")));
    });

    document.getElementById("trip-select")?.addEventListener("change", (e) => {
      state.activeTripId = e.target.value || null;
      global.Storage.save(state);
      global.UI.toast("Switched trip");
      refresh();
    });

    document.getElementById("btn-new-trip-quick")?.addEventListener("click", () => {
      global.Trips.openTripForm(state, null);
    });

    document.getElementById("btn-logout")?.addEventListener("click", () => {
      global.Auth.logout();
    });

    const sidebar = document.getElementById("sidebar");
    const backdrop = document.getElementById("sidebar-backdrop");
    document.getElementById("btn-menu")?.addEventListener("click", () => {
      sidebar?.classList.add("is-open");
      backdrop?.classList.add("is-open");
    });
    backdrop?.addEventListener("click", () => {
      sidebar?.classList.remove("is-open");
      backdrop?.classList.remove("is-open");
    });
    document.querySelectorAll(".nav-item").forEach((btn) => {
      btn.addEventListener("click", () => {
        sidebar?.classList.remove("is-open");
        backdrop?.classList.remove("is-open");
      });
    });
  }

  function bootstrapAfterAuth() {
    state = global.Storage.load();
    global.Permissions.ensureActiveTripAccessible(state);
    global.Storage.save(state);
    wireOnce();
    global.Trips.syncTripSelect(state);
    updateUserChip();
    navigate(state.activeSection || "dashboard");
  }

  global.App = {
    navigate,
    refresh,
    bootstrapAfterAuth,
    getState: () => state,
  };
})(typeof window !== "undefined" ? window : globalThis);
