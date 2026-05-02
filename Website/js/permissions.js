/**
 * Trip-scoped roles & session helpers (frontend simulation)
 */
(function (global) {
  function getSessionUserId(state) {
    if (!state.session || !state.session.userId) return null;
    return state.session.userId;
  }

  function getUser(state, userId) {
    if (!userId || !state.users) return null;
    return state.users.find((u) => u.id === userId) || null;
  }

  function getCurrentUser(state) {
    const id = getSessionUserId(state);
    return id ? getUser(state, id) : null;
  }

  function findActiveMember(trip, state) {
    const uid = getSessionUserId(state);
    if (!trip || !uid) return null;
    const members = trip.members || [];
    return members.find((m) => m.userId === uid && m.status === "active") || null;
  }

  function isTripParticipant(trip, state) {
    return !!findActiveMember(trip, state);
  }

  function isOrganizer(trip, state) {
    const m = findActiveMember(trip, state);
    return !!(m && m.role === "organizer");
  }

  function canManageTripSettings(trip, state) {
    return isOrganizer(trip, state);
  }

  function canManageMembers(trip, state) {
    return isOrganizer(trip, state);
  }

  function getAccessibleTrips(state) {
    if (!state.trips) return [];
    return state.trips.filter((t) => isTripParticipant(t, state));
  }

  function countOrganizers(trip) {
    return (trip.members || []).filter((m) => m.status === "active" && m.role === "organizer").length;
  }

  function getMemberIdForSession(trip, state) {
    const m = findActiveMember(trip, state);
    return m ? m.id : null;
  }

  function ensureActiveTripAccessible(state) {
    const acc = getAccessibleTrips(state);
    if (!state.activeTripId || !acc.some((t) => t.id === state.activeTripId)) {
      state.activeTripId = acc[0] ? acc[0].id : null;
    }
  }

  global.Permissions = {
    getSessionUserId,
    getUser,
    getCurrentUser,
    findActiveMember,
    isTripParticipant,
    isOrganizer,
    canManageTripSettings,
    canManageMembers,
    getAccessibleTrips,
    countOrganizers,
    getMemberIdForSession,
    ensureActiveTripAccessible,
  };
})(typeof window !== "undefined" ? window : globalThis);
