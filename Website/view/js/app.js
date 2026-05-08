/**
 * App shell: navigation, trip selector, section routing.
 * Authentication is handled server-side; this file never redirects or
 * manages login state — it simply reads the session injected by index.php.
 */
(function (global) {
  const SECTIONS = {
    dashboard: { title: "Trips Dashboard", eyebrow: "Workspace", render: (c, s) => global.Trips.renderDashboard(c, s) },
    members:   { title: "Members",         eyebrow: "Team",      render: (c, s) => global.Members.render(c, s) },
    itinerary: { title: "Itinerary",       eyebrow: "Planning",  render: (c, s) => global.Itinerary.render(c, s) },
    voting:    { title: "Voting",          eyebrow: "Decisions", render: (c, s) => global.Voting.render(c, s) },
    rsvp:      { title: "RSVP",           eyebrow: "Attendance",render: (c, s) => global.RSVP.render(c, s) },
    expenses:  { title: "Expenses",        eyebrow: "Budget",    render: (c, s) => global.Expenses.render(c, s) },
    chat:      { title: "Chat",            eyebrow: "Collaboration", render: (c, s) => global.Chat.render(c, s) },
    documents: { title: "Documents",       eyebrow: "Files",     render: (c, s) => global.Documents.render(c, s) },
    checklist: { title: "Checklist",       eyebrow: "Execution", render: (c, s) => global.Checklist.render(c, s) },
  };

  let state = global.Storage.load();
  let listenersAttached = false;

  // Sync the frontend session with the PHP-authenticated user injected by index.php.
  function syncServerSession() {
    const srv = window.__serverSession;
    if (!srv || !srv.userId) return;

    // Ensure the user record exists in the client-side store.
    state.users = state.users || [];
    if (!state.users.find(function (u) { return u.id === String(srv.userId); })) {
      state.users.push({
        id: String(srv.userId),
        name: srv.userName || "User",
        email: "",
        password: "",
      });
    }
    state.session = { userId: String(srv.userId) };
    global.Storage.save(state);
  }

  function setActiveNav(section) {
    document.querySelectorAll(".nav-item").forEach(function (btn) {
      btn.classList.toggle("is-active", btn.getAttribute("data-section") === section);
    });
  }

  function updateUserChip() {
    state = global.Storage.load();
    const trip = state.activeTripId ? global.Storage.getTrip(state, state.activeTripId) : null;
    const mem  = trip ? global.Permissions.findActiveMember(trip, state) : null;
    const roleEl = document.getElementById("user-trip-role");
    if (roleEl) {
      if (!trip || !mem) roleEl.textContent = "Select a trip";
      else roleEl.textContent = mem.role === "organizer" ? "Organizer on this trip" : "Member on this trip";
    }
  }

  function navigate(section) {
    if (!SECTIONS[section]) section = "dashboard";
    state.activeSection = section;
    global.Storage.save(state);
    setActiveNav(section);
    const meta = SECTIONS[section];
    const eyebrow = document.getElementById("topbar-section");
    const title   = document.getElementById("topbar-title");
    if (eyebrow) eyebrow.textContent = meta.eyebrow;
    if (title)   title.textContent   = meta.title;
    const root = document.getElementById("view-root");
    if (!root) return;
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

    document.querySelectorAll(".nav-item").forEach(function (btn) {
      btn.addEventListener("click", function () { navigate(btn.getAttribute("data-section")); });
    });

    document.getElementById("trip-select")?.addEventListener("change", function (e) {
      state.activeTripId = e.target.value || null;
      global.Storage.save(state);
      global.UI.toast("Switched trip");
      refresh();
    });

    document.getElementById("btn-new-trip-quick")?.addEventListener("click", function () {
      global.Trips.openTripForm(state, null);
    });

    const sidebar  = document.getElementById("sidebar");
    const backdrop = document.getElementById("sidebar-backdrop");
    document.getElementById("btn-menu")?.addEventListener("click", function () {
      sidebar?.classList.add("is-open");
      backdrop?.classList.add("is-open");
    });
    backdrop?.addEventListener("click", function () {
      sidebar?.classList.remove("is-open");
      backdrop?.classList.remove("is-open");
    });
    document.querySelectorAll(".nav-item").forEach(function (btn) {
      btn.addEventListener("click", function () {
        sidebar?.classList.remove("is-open");
        backdrop?.classList.remove("is-open");
      });
    });
  }

  function boot() {
    syncServerSession();
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
    bootstrapAfterAuth: boot, // kept for compatibility — just calls boot()
    getState: function () { return state; },
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})(typeof window !== "undefined" ? window : globalThis);