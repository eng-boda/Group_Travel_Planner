/**
 * Itinerary: days, activities, drag/drop reorder, conflict hints
 */
(function (global) {
  const { escapeHtml, openModal, toast } = global.UI;
  const { generateId, getTrip } = global.Storage;

  // --- Weather (activity-card only; async; cached) ---
  const __weatherCache = new Map(); // locationKey -> Promise<{ tempC:number, condition:string, emoji:string }|null>
  const __geoCache = new Map(); // locationKey -> Promise<{ lat:number, lon:number }|null>
  const __DEFAULT_COORDS = { lat: 30.0444, lon: 31.2357 }; // Cairo (safe fallback)

  function __weatherKey(location) {
    return String(location || "").trim().toLowerCase();
  }

  function __weatherFromCode(code) {
    // Open-Meteo weathercode mapping (subset grouped)
    if (code === 0) return { condition: "Clear", emoji: "☀️" };
    if (code === 1 || code === 2) return { condition: "Partly cloudy", emoji: "⛅" };
    if (code === 3) return { condition: "Cloudy", emoji: "☁️" };
    if (code === 45 || code === 48) return { condition: "Fog", emoji: "🌫️" };
    if ((code >= 51 && code <= 57) || (code >= 61 && code <= 67) || (code >= 80 && code <= 82))
      return { condition: "Rain", emoji: "🌧️" };
    if ((code >= 71 && code <= 77) || (code >= 85 && code <= 86))
      return { condition: "Snow", emoji: "🌨️" };
    if (code >= 95 && code <= 99) return { condition: "Thunderstorm", emoji: "⛈️" };
    return { condition: "Weather", emoji: "☁️" };
  }

  async function __geocode(location) {
    const key = __weatherKey(location);
    if (!key) return null;
    if (__geoCache.has(key)) return __geoCache.get(key);

    const p = (async () => {
      const parseLatLon = (latRaw, lonRaw) => {
        const lat = parseFloat(latRaw);
        const lon = parseFloat(lonRaw);
        if (!isFinite(lat) || !isFinite(lon)) return null;
        return { lat, lon };
      };

      // Primary: OpenStreetMap Nominatim
      try {
        const url =
          "https://nominatim.openstreetmap.org/search?format=json&limit=1&q=" +
          encodeURIComponent(location);
        const res = await fetch(url, {
          headers: { Accept: "application/json", "Accept-Language": "en" },
        });
        if (res.ok) {
          const data = await res.json();
          const first = Array.isArray(data) ? data[0] : null;
          const coords = first ? parseLatLon(first.lat, first.lon) : null;
          if (coords) return coords;
        }
      } catch {
        // continue to fallback
      }

      // Fallback: Open-Meteo geocoding (often more reliable with CORS)
      try {
        const url =
          "https://geocoding-api.open-meteo.com/v1/search?count=1&language=en&format=json&name=" +
          encodeURIComponent(location);
        const res = await fetch(url, { headers: { Accept: "application/json" } });
        if (res.ok) {
          const data = await res.json();
          const first = data && Array.isArray(data.results) ? data.results[0] : null;
          const coords = first ? parseLatLon(first.latitude, first.longitude) : null;
          if (coords) return coords;
        }
      } catch {
        // continue to default
      }

      // Safe last resort: default coordinates
      return { lat: __DEFAULT_COORDS.lat, lon: __DEFAULT_COORDS.lon };
    })();

    __geoCache.set(key, p);
    return p;
  }

  async function getWeather(location) {
    const key = __weatherKey(location);
    if (!key) return null;
    if (__weatherCache.has(key)) return __weatherCache.get(key);

    const p = (async () => {
      const geo =
        (await __geocode(location)) || { lat: __DEFAULT_COORDS.lat, lon: __DEFAULT_COORDS.lon };

      try {
        const url =
          "https://api.open-meteo.com/v1/forecast?latitude=" +
          encodeURIComponent(String(geo.lat)) +
          "&longitude=" +
          encodeURIComponent(String(geo.lon)) +
          "&current_weather=true";
        const res = await fetch(url, { headers: { Accept: "application/json" } });
        if (!res.ok) return { tempC: null, condition: "Unavailable", emoji: "☁️", unavailable: true };
        const data = await res.json();
        const cw = data && data.current_weather ? data.current_weather : null;
        const tempC = cw && typeof cw.temperature === "number" ? cw.temperature : null;
        const code = cw && typeof cw.weathercode === "number" ? cw.weathercode : null;
        if (tempC == null || code == null)
          return { tempC: null, condition: "Unavailable", emoji: "☁️", unavailable: true };
        const mapped = __weatherFromCode(code);
        return { tempC, condition: mapped.condition, emoji: mapped.emoji };
      } catch {
        return { tempC: null, condition: "Unavailable", emoji: "☁️", unavailable: true };
      }
    })();

    __weatherCache.set(key, p);
    return p;
  }

  function __attachWeatherToCard(cardEl, location) {
    const loc = String(location || "").trim();
    if (!loc) return;
    if (cardEl.dataset.weatherAttached === "1") return;
    cardEl.dataset.weatherAttached = "1";

    const meta = cardEl.querySelector(".activity-card__meta");
    const line = document.createElement("div");
    line.className = "activity-card__meta";
    line.dataset.weatherLine = "1";
    line.textContent = "Loading weather...";

    if (meta && meta.parentNode) meta.insertAdjacentElement("afterend", line);
    else cardEl.appendChild(line);

    getWeather(loc)
      .then((w) => {
        if (!line.isConnected) return;
        if (!w || w.unavailable || typeof w.tempC !== "number" || !isFinite(w.tempC)) {
          line.textContent = "Weather unavailable";
          return;
        }
        line.textContent = w.emoji + " " + Math.round(w.tempC) + "°C · " + w.condition;

        const isOutdoor = String(cardEl.dataset.activityType || "").toLowerCase() === "outdoor";
        if (!isOutdoor) return;

        const cond = String(w.condition || "").toLowerCase();
        const isRain = cond.includes("rain");
        const isSnow = cond.includes("snow");
        const isThunder = cond.includes("thunder");
        const isCold = w.tempC < 10;
        const isHot = w.tempC > 38;
        const isBad = isRain || isSnow || isThunder || isCold || isHot;
        if (!isBad) return;

        const existing = cardEl.querySelector(".activity-card__warning");
        const warn = existing || document.createElement("div");
        warn.className = "activity-card__warning";

        let msg = "⚠️ Bad weather for outdoor activity";
        if (isRain) msg = "⚠️ Rain expected";
        else if (isThunder) msg = "⚠️ Thunderstorm risk";
        else if (isSnow) msg = "⚠️ Snow expected";
        else if (isCold || isHot) msg = "⚠️ Extreme temperature";
        warn.textContent = msg;

        if (!existing) line.insertAdjacentElement("afterend", warn);
      })
      .catch(() => {
        if (!line.isConnected) return;
        line.textContent = "Weather unavailable";
      });
  }

  function parseMinutes(t) {
    if (!t || typeof t !== "string") return null;
    const p = t.trim().split(":");
    const h = parseInt(p[0], 10);
    const m = parseInt(p[1] || "0", 10);
    if (isNaN(h)) return null;
    return h * 60 + (isNaN(m) ? 0 : m);
  }

  function hasConflict(day) {
    const acts = day.activities.filter((a) => a.time);
    for (let i = 0; i < acts.length; i++) {
      const a = parseMinutes(acts[i].time);
      if (a == null) continue;
      for (let j = i + 1; j < acts.length; j++) {
        const b = parseMinutes(acts[j].time);
        if (b == null) continue;
        const dur = 90;
        if (a < b + dur && b < a + dur) return true;
      }
    }
    return false;
  }

  function tripConflicts(trip) {
    return trip.itineraryDays.filter(hasConflict).map((d) => d.label || "Day " + d.dayNumber);
  }

  function openActivityModal(state, trip, day, activity) {
    const isEdit = !!activity;
    const docs = trip.documents || [];
    const body = document.createElement("div");
    body.className = "form-grid";
    body.innerHTML =
      '<div class="form-row"><label>Title</label><input id="a-title" class="input" required value="' +
      escapeHtml(activity ? activity.title : "") +
      '" /></div>' +
      '<div class="form-row"><label>Time</label><input id="a-time" type="time" class="input" value="' +
      escapeHtml(activity ? activity.time : "") +
      '" /></div>' +
      '<div class="form-row"><label>Location</label><input id="a-loc" class="input" value="' +
      escapeHtml(activity ? activity.location : "") +
      '" /></div>' +
      '<div class="form-row"><label>Notes</label><textarea id="a-notes" class="textarea">' +
      escapeHtml(activity ? activity.notes : "") +
      "</textarea></div>" +
      '<div class="form-row"><label>Activity type</label><select id="a-type" class="input">' +
      '<option value="indoor"' +
      (!activity || (activity && String(activity.type || activity.activityType || "").toLowerCase() !== "outdoor")
        ? " selected"
        : "") +
      ">Indoor</option>" +
      '<option value="outdoor"' +
      (activity && String(activity.type || activity.activityType || "").toLowerCase() === "outdoor"
        ? " selected"
        : "") +
      ">Outdoor</option>" +
      "</select></div>" +
      '<div class="form-row"><label>Status</label><select id="a-status" class="input">' +
      '<option value="draft"' +
      (activity && activity.status === "draft" ? " selected" : "") +
      ">Draft</option>" +
      '<option value="confirmed"' +
      (!activity || activity.status === "confirmed" ? " selected" : "") +
      ">Confirmed</option>" +
      "</select></div>" +
      '<div class="form-row"><label>Attach document</label><select id="a-doc" class="input">' +
      '<option value="">— None —</option>' +
      docs
        .map(
          (d) =>
            '<option value="' +
            escapeHtml(d.id) +
            '"' +
            (activity && activity.documentIds && activity.documentIds[0] === d.id ? " selected" : "") +
            ">" +
            escapeHtml(d.name) +
            "</option>"
        )
        .join("") +
      "</select></div>";

    const footer = document.createElement("div");
    footer.style.display = "flex";
    footer.style.gap = "0.5rem";
    footer.style.justifyContent = "flex-end";
    const cancel = document.createElement("button");
    cancel.type = "button";
    cancel.className = "btn btn--secondary";
    cancel.textContent = "Cancel";
    const save = document.createElement("button");
    save.type = "button";
    save.className = "btn btn--primary";
    save.textContent = isEdit ? "Save activity" : "Add activity";
    footer.appendChild(cancel);
    footer.appendChild(save);

    const dlg = openModal({
      title: isEdit ? "Edit activity" : "New activity · " + (day.label || "Day " + day.dayNumber),
      body,
      footer,
    });

    cancel.addEventListener("click", () => dlg.close());
    save.addEventListener("click", () => {
      const title = body.querySelector("#a-title").value.trim();
      if (!title) {
        toast("Title required");
        return;
      }
      const time = body.querySelector("#a-time").value;
      const location = body.querySelector("#a-loc").value.trim();
      const notes = body.querySelector("#a-notes").value.trim();
      const type = body.querySelector("#a-type").value;
      const status = body.querySelector("#a-status").value;
      const docId = body.querySelector("#a-doc").value;
      const documentIds = docId ? [docId] : [];

      if (isEdit) {
        activity.title = title;
        activity.time = time;
        activity.location = location;
        activity.notes = notes;
        activity.type = type;
        activity.status = status;
        activity.documentIds = documentIds;
      } else {
        day.activities.push({
          id: generateId("act"),
          title,
          time,
          location,
          notes,
          type,
          status,
          documentIds,
        });
      }
      global.Storage.save(state);
      toast(isEdit ? "Activity updated" : "Activity added");
      dlg.close();
      global.App.refresh();
    });
  }

  function moveActivity(day, index, dir) {
    const j = index + dir;
    if (j < 0 || j >= day.activities.length) return;
    const arr = day.activities;
    const t = arr[index];
    arr[index] = arr[j];
    arr[j] = t;
  }

  function render(container, state) {
    const trip = state.activeTripId ? getTrip(state, state.activeTripId) : null;
    const actions = document.getElementById("topbar-actions");
    if (actions) {
      actions.innerHTML =
        '<button type="button" class="btn btn--secondary" id="it-add-day">+ Add day</button>' +
        '<button type="button" class="btn btn--primary" id="it-add-act">+ Add activity</button>';
    }

    if (!trip) {
      container.innerHTML =
        '<div class="empty"><div class="empty__icon">◎</div><p>Select a trip for the itinerary.</p></div>';
      return;
    }

    if (!global.Permissions.isTripParticipant(trip, state)) {
      if (actions) actions.innerHTML = "";
      container.innerHTML =
        '<div class="empty"><div class="empty__icon">🔒</div><p>You are not a member of this trip.</p></div>';
      return;
    }

    if (!trip.itineraryDays.length) {
      const sd = trip.startDate ? new Date(trip.startDate) : new Date();
      for (let d = 0; d < 2; d++) {
        const day = new Date(sd);
        day.setDate(day.getDate() + d);
        trip.itineraryDays.push({
          id: generateId("day"),
          dayNumber: d + 1,
          label: "Day " + (d + 1),
          activities: [],
        });
      }
      global.Storage.save(state);
    }

    const conflictDays = tripConflicts(trip);
    const warn =
      conflictDays.length > 0
        ? '<div class="conflict-banner"><span>⚠</span><div><strong>Possible schedule overlap</strong> on ' +
          escapeHtml(conflictDays.join(", ")) +
          ". Adjust times or reorder activities — this is a simple visual hint only.</div></div>"
        : "";

    container.innerHTML =
      warn +
      '<div class="tabs" id="it-tabs">' +
      '<button type="button" class="tab is-active" data-tab="board">Board</button>' +
      '<button type="button" class="tab" data-tab="compact">Compact list</button>' +
      "</div>" +
      '<div class="tab-panel" data-tab-panel="board"><div class="day-board" id="day-board"></div></div>' +
      '<div class="tab-panel" data-tab-panel="compact" hidden><div id="compact-list"></div></div>';

    global.UI.initTabs(container.querySelector("#it-tabs"));

    const board = container.querySelector("#day-board");
    trip.itineraryDays.forEach((day) => {
      const col = document.createElement("div");
      col.className = "day-column";
      col.dataset.dayId = day.id;
      col.innerHTML =
        '<div class="day-column__head">' +
        '<span class="day-column__label">' +
        escapeHtml(day.label || "Day " + day.dayNumber) +
        "</span>" +
        '<button type="button" class="btn btn--sm btn--secondary" data-add-day="' +
        escapeHtml(day.id) +
        '">Add here</button>' +
        "</div>" +
        '<div class="day-activities" data-day-drop="' +
        escapeHtml(day.id) +
        '"></div>';
      board.appendChild(col);
      const list = col.querySelector(".day-activities");

      day.activities.forEach((act, idx) => {
        const card = document.createElement("div");
        card.className = "activity-card";
        card.draggable = true;
        card.dataset.actId = act.id;
        card.dataset.dayId = day.id;
        card.innerHTML =
          '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.5rem;">' +
          '<div style="flex:1;min-width:0;"><div style="font-weight:700;">' +
          escapeHtml(act.title) +
          "</div>" +
          '<span class="badge ' +
          (act.status === "confirmed" ? "badge--confirmed" : "badge--draft") +
          '">' +
          escapeHtml(act.status) +
          "</span></div>" +
          '<div style="display:flex;flex-direction:column;gap:0.25rem;">' +
          '<button type="button" class="btn btn--sm btn--secondary" data-up="' +
          escapeHtml(act.id) +
          '">↑</button>' +
          '<button type="button" class="btn btn--sm btn--secondary" data-down="' +
          escapeHtml(act.id) +
          '">↓</button>' +
          "</div></div>" +
          '<div class="activity-card__meta">' +
          (act.time ? "🕐 " + escapeHtml(act.time) + " · " : "") +
          escapeHtml(act.location || "No location") +
          "</div>" +
          (act.notes ? '<div class="activity-card__meta">' + escapeHtml(act.notes) + "</div>" : "") +
          '<div style="margin-top:0.5rem;display:flex;gap:0.35rem;flex-wrap:wrap;">' +
          '<button type="button" class="btn btn--sm btn--secondary" data-edit="' +
          escapeHtml(act.id) +
          '">Edit</button>' +
          '<button type="button" class="btn btn--sm btn--danger" data-del="' +
          escapeHtml(act.id) +
          '">Delete</button>' +
          "</div>";

        card.addEventListener("dragstart", (e) => {
          e.dataTransfer.setData(
            "application/x-trip-act",
            JSON.stringify({ dayId: day.id, actId: act.id })
          );
          card.classList.add("dragging");
        });
        card.addEventListener("dragend", () => card.classList.remove("dragging"));

        list.appendChild(card);
        __attachWeatherToCard(card, act.location);

        card.querySelector("[data-edit]")?.addEventListener("click", () =>
          openActivityModal(state, trip, day, act)
        );
        card.querySelector("[data-del]")?.addEventListener("click", () => {
          if (!confirm("Delete this activity?")) return;
          day.activities = day.activities.filter((a) => a.id !== act.id);
          global.Storage.save(state);
          toast("Activity removed");
          global.App.refresh();
        });
        card.querySelector("[data-up]")?.addEventListener("click", () => {
          moveActivity(day, idx, -1);
          global.Storage.save(state);
          global.App.refresh();
        });
        card.querySelector("[data-down]")?.addEventListener("click", () => {
          moveActivity(day, idx, 1);
          global.Storage.save(state);
          global.App.refresh();
        });
      });

      list.addEventListener("dragover", (e) => {
        e.preventDefault();
        list.classList.add("is-drag-over");
      });
      list.addEventListener("dragleave", () => list.classList.remove("is-drag-over"));
      list.addEventListener("drop", (e) => {
        e.preventDefault();
        list.classList.remove("is-drag-over");
        let raw = e.dataTransfer.getData("application/x-trip-act");
        if (!raw) return;
        let payload;
        try {
          payload = JSON.parse(raw);
        } catch {
          return;
        }
        const fromDay = trip.itineraryDays.find((d) => d.id === payload.dayId);
        if (!fromDay) return;
        const actIndex = fromDay.activities.findIndex((a) => a.id === payload.actId);
        if (actIndex < 0) return;
        const [moved] = fromDay.activities.splice(actIndex, 1);
        day.activities.push(moved);
        global.Storage.save(state);
        toast("Activity moved");
        global.App.refresh();
      });
    });

    container.querySelectorAll("[data-add-day]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const id = btn.getAttribute("data-add-day");
        const day = trip.itineraryDays.find((d) => d.id === id);
        if (day) openActivityModal(state, trip, day, null);
      });
    });

    document.getElementById("it-add-act")?.addEventListener("click", () => {
      const first = trip.itineraryDays[0];
      if (first) openActivityModal(state, trip, first, null);
    });

    document.getElementById("it-add-day")?.addEventListener("click", () => {
      const n = trip.itineraryDays.length + 1;
      trip.itineraryDays.push({
        id: generateId("day"),
        dayNumber: n,
        label: "Day " + n,
        activities: [],
      });
      
      global.Storage.save(state);
      toast("Day added");
      global.App.refresh();
    });

    const compact = container.querySelector("#compact-list");
    let html = "";
    trip.itineraryDays.forEach((day) => {
      html +=
        '<div class="card" style="margin-bottom:0.75rem;"><h3 class="card__title">' +
        escapeHtml(day.label || "Day " + day.dayNumber) +
        "</h3><ul class=\"list-plain\">";
      day.activities.forEach((a) => {
        html +=
          "<li><strong>" +
          escapeHtml(a.title) +
          "</strong> — " +
          escapeHtml(a.time || "—") +
          " · " +
          escapeHtml(a.location || "") +
          "</li>";
      });
      html += "</ul></div>";
    });
    compact.innerHTML = html || '<p class="muted">No activities yet.</p>';
  }

  global.Itinerary = { render };
})(typeof window !== "undefined" ? window : globalThis);
