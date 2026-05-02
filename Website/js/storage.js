/**
 * localStorage persistence for Collaborative Trip Planner (v2: auth + members)
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

  function defaultMembers(seedUser) {
    return [
      {
        id: "m_self",
        userId: seedUser.id,
        name: seedUser.name,
        email: seedUser.email,
        role: "organizer",
        status: "active",
        color: "#f43f5e",
      },
      {
        id: "m1",
        userId: null,
        name: "Alex Rivera",
        email: "alex@example.com",
        role: "member",
        status: "active",
        color: "#6366f1",
      },
      {
        id: "m2",
        userId: null,
        name: "Jordan Lee",
        email: "jordan@example.com",
        role: "member",
        status: "active",
        color: "#8b5cf6",
      },
      {
        id: "m3",
        userId: null,
        name: "Sam Patel",
        email: "sam@example.com",
        role: "member",
        status: "active",
        color: "#06b6d4",
      },
    ];
  }

  function seedTrip(seedUser) {
    const start = new Date();
    start.setDate(start.getDate() + 14);
    const end = new Date(start);
    end.setDate(end.getDate() + 2);
    const fmt = (d) => d.toISOString().slice(0, 10);
    const tripId = generateId("trip");

    return {
      id: tripId,
      name: "Portland team offsite",
      description: "Quarterly planning retreat with the product squad.",
      startDate: fmt(start),
      endDate: fmt(end),
      budget: 4500,
      members: defaultMembers(seedUser),
      pendingInvites: [],
      itineraryDays: [
        {
          id: generateId("day"),
          dayNumber: 1,
          label: "Day 1",
          activities: [
            {
              id: generateId("act"),
              title: "Arrival & hotel check-in",
              time: "15:00",
              location: "The Hoxton",
              notes: "Confirmation in documents.",
              status: "confirmed",
              documentIds: [],
            },
            {
              id: generateId("act"),
              title: "Welcome dinner",
              time: "19:00",
              location: "Le Pigeon",
              notes: "Reserve for 6.",
              status: "draft",
              documentIds: [],
            },
          ],
        },
        {
          id: generateId("day"),
          dayNumber: 2,
          label: "Day 2",
          activities: [
            {
              id: generateId("act"),
              title: "Strategy workshop",
              time: "09:30",
              location: "WeWork",
              notes: "Bring laptops.",
              status: "confirmed",
              documentIds: [],
            },
          ],
        },
      ],
      polls: [
        {
          id: generateId("poll"),
          question: "Team activity on Day 2 evening?",
          options: [
            { id: generateId("opt"), text: "Escape room", votes: [] },
            { id: generateId("opt"), text: "Bowling", votes: [] },
            { id: generateId("opt"), text: "Food tour", votes: [] },
          ],
        },
      ],
      rsvp: { m_self: "going", m1: "going", m2: "maybe", m3: "going" },
      expenses: [
        {
          id: generateId("exp"),
          title: "Hotel deposit",
          amount: 1200,
          paidBy: "m1",
          splitType: "equal",
          splits: { m_self: 300, m1: 300, m2: 300, m3: 300 },
          createdAt: Date.now() - 86400000 * 3,
        },
      ],
      messages: [
        {
          id: generateId("msg"),
          parentId: null,
          authorId: "m1",
          text: "Excited for this trip! Should we block calendars?",
          ts: Date.now() - 3600000 * 5,
        },
        {
          id: generateId("msg"),
          parentId: null,
          authorId: "m_self",
          text: "Yes — I sent invites. Drop ideas in voting.",
          ts: Date.now() - 3600000 * 4,
        },
      ],
      documents: [
        {
          id: generateId("doc"),
          name: "hotel-confirmation.pdf",
          sizeLabel: "240 KB",
          activityId: null,
          uploadedAt: Date.now() - 86400000,
        },
      ],
      checklist: [
        { id: generateId("chk"), title: "Share flight details", assigneeId: "m2", status: "assigned", order: 0 },
        { id: generateId("chk"), title: "Book rides from airport", assigneeId: "m1", status: "pending", order: 1 },
        { id: generateId("chk"), title: "Print name badges", assigneeId: "m3", status: "completed", order: 2 },
      ],
    };
  }

  function emptyStateV2() {
    return {
      version: 2,
      users: [],
      session: { userId: null },
      activeSection: "dashboard",
      trips: [],
      activeTripId: null,
    };
  }

  function migrateToV2(data) {
    data.users = data.users || [];
    if (!data.users.length) {
      const legacyName = data.currentUser && data.currentUser.name ? data.currentUser.name : "Demo Leader";
      data.users.push({
        id: generateId("u"),
        name: legacyName,
        email: "demo@tripsync.app",
        password: "demo",
      });
    }
    const primary = data.users[0];
    data.session = data.session || { userId: primary.id };
    if (!data.session.userId) data.session.userId = primary.id;

    data.trips = data.trips || [];
    data.trips.forEach((trip, tIdx) => {
      trip.pendingInvites = trip.pendingInvites || [];
      const mems = (trip.members || []).map((m, i) => {
        const nm = normalizeMemberRecord(m, i);
        if (m.id === "m_self" || (m.name === "You" && !nm.userId)) {
          nm.userId = primary.id;
          nm.email = primary.email;
          nm.name = primary.name;
        }
        return nm;
      });
      trip.members = mems;
    });

    delete data.currentUser;
    data.version = 2;
    return data;
  }

  function load() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) {
        return emptyStateV2();
      }
      const data = JSON.parse(raw);
      if (!data.version || data.version < 2) {
        migrateToV2(data);
        save(data);
      }
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
      return emptyStateV2();
    }
  }

  function save(state) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
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

  /** Seed demo trip when a new account has no trips yet; always persists session + users */
  function seedDemoTripForUser(state, user) {
    if (!user) return;
    if (!state.trips.length) {
      const trip = seedTrip(user);
      state.trips.push(trip);
      state.activeTripId = trip.id;
    }
    save(state);
  }

  global.Storage = {
    STORAGE_KEY,
    generateId,
    load,
    save,
    getTrip,
    updateTrip,
    seedTrip,
    emptyStateV2,
    seedDemoTripForUser,
    pickColor,
    normalizeMemberRecord,
  };
})(typeof window !== "undefined" ? window : globalThis);
