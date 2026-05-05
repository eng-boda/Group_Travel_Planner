/**
 * sessionStorage persistence for Collaborative Trip Planner (v2: auth + members)
 * Data persists only for the current browser tab session.
 */
(function (global) {
  const STORAGE_KEY = "collabTripPlanner_v1";

  function generateId(prefix) {
    return (prefix || "id") + "_" + Math.random().toString(36).slice(2, 11) + Date.now().toString(36);
  }

  const COLORS = ["#6366f1", "#8b5cf6", "#06b6d4", "#f43f5e", "#14b8a6", "#f97316", "#84cc16", "#a855f7"];

  function pickColor(i) {
    return COLORS[Math.abs(String(i).length) % COLORS.length];
  }

  function normalizeMemberRecord(m, idx) {
    const raw = String(m.role || "member").toLowerCase();
    const role =
      raw === "organizer" || raw === "co-organizer" || raw.includes("organ") ? "organizer" : "member";
    return {
      id: m.id || generateId("m"),
      userId: m.userId != null ? m.userId : null,
      name: m.name || "Member",
      email: m.email != null ? m.email : null,
      role,
      status: m.status || "active",
      color: m.color || pickColor((m.id || "") + idx),
    };
  }

  function emptyState() {
    return {
      version: 2,
      users: [],
      session: { userId: null },
      activeSection: "dashboard",
      trips: [],
      activeTripId: null,
    };
  }

  function load() {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY);
      if (!raw) {
        return emptyState();
      }
      const data = JSON.parse(raw);
      if (!data.users) data.users = [];
      if (!data.session) data.session = { userId: null };
      if (!data.trips) data.trips = [];
      data.trips.forEach((trip) => {
        if (!trip.pendingInvites) trip.pendingInvites = [];
        if (!trip.rsvp || typeof trip.rsvp !== "object") trip.rsvp = {};
        trip.members = (trip.members || []).map((m, i) => normalizeMemberRecord(m, i));
      });
      return data;
    } catch (e) {
      console.warn("Storage load failed", e);
      return emptyState();
    }
  }

  function save(state) {
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch (e) {
      console.warn("Storage save failed", e);
    }
  }

  function getTrip(state, tripId) {
    return state.trips.find((t) => t.id === tripId) || null;
  }

  function updateTrip(state, tripId, fn) {
    const trip = getTrip(state, tripId);
    if (!trip) return false;
    fn(trip);
    save(state);
    return true;
  }

  global.Storage = {
    STORAGE_KEY,
    generateId,
    load,
    save,
    getTrip,
    updateTrip,
    pickColor,
    normalizeMemberRecord,
  };
})(typeof window !== "undefined" ? window : globalThis);