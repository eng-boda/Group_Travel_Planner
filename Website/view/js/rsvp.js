/**
 * RSVP: going / maybe / not going per active trip member (trip-level summary)
 * Per-activity RSVP is handled inline in itinerary.js via RSVPActivityWidget.
 */
(function (global) {
  const { escapeHtml, toast } = global.UI;
  const { getTrip } = global.Storage;
  const P = global.Permissions;

  /**
   * Post an RSVP response for a specific activity.
   * response must be "yes" | "maybe" | "no"
   * Returns a Promise<{success, counts, mine}>.
   */
  function postActivityRSVP(activityId, response) {
    const body =
      "activity_id=" + encodeURIComponent(activityId) +
      "&response=" + encodeURIComponent(response);

    return fetch("../controller/RSVPController.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body,
    }).then((res) => {
      if (!res.ok) throw new Error("HTTP " + res.status);
      return res.json();
    });
  }

  /**
   * Fetch counts + current user's response for one activity.
   * Returns a Promise<{success, counts:{yes,maybe,no}, mine:string|null}>.
   */
  function fetchActivityRSVP(activityId) {
    return fetch(
      "../controller/RSVPController.php?activity_id=" + encodeURIComponent(activityId)
    ).then((res) => {
      if (!res.ok) throw new Error("HTTP " + res.status);
      return res.json();
    });
  }

  // ── Trip-level summary view (the existing RSVP tab) ──────────────────────
  function render(container, state) {
    const trip = state.activeTripId ? getTrip(state, state.activeTripId) : null;
    const actions = document.getElementById("topbar-actions");
    if (actions) actions.innerHTML = "";

    if (!trip) {
        container.innerHTML = '<div class="empty"><div class="empty__icon">✓</div><p>Select a trip to manage RSVP.</p></div>';
        return;
    }

    // تجهيز قائمة النشاطات من الـ Itinerary
    const activities = (trip.itineraryDays || []).flatMap((day) =>
        (day.activities || []).map((a) => ({
            dayLabel: day.label || ("Day " + (day.dayNumber || "")),
            id: a.id,
            title: a.title || "Untitled activity",
            time: a.time || "",
        }))
    );

    function activityOptionLabel(x) {
        const bits = [x.dayLabel];
        if (x.time) bits.push(x.time);
        bits.push(x.title);
        return bits.filter(Boolean).join(" · ");
    }

    function isValidDbActivityId(id) {
        const n = Number(id);
        return Number.isFinite(n) && n > 0 && String(Math.trunc(n)) === String(id).trim();
    }

    // الواجهة الجديدة بدون الجزء العلوي المكرر
    container.innerHTML = `
        <div class="card card--gradient">
            <div class="card__header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                <div>
                    <h2 class="section-title" style="margin:0;">Activity Attendance</h2>
                    <p class="muted" id="rsvp-activity-hint" style="margin:4px 0 0;">Select an activity to see who is attending.</p>
                </div>
                <div style="min-width:260px; max-width:400px; width:100%;">
                    <select id="rsvp-activity-select" class="input" ${activities.length ? "" : "disabled"}>
                        ${activities.length 
                            ? activities.map(a => `<option value="${escapeHtml(String(a.id))}">${escapeHtml(activityOptionLabel(a))}</option>`).join("")
                            : '<option value="">No activities available</option>'}
                    </select>
                </div>
            </div>

            <div class="grid grid--3" id="rsvp-activity-grid" style="margin-top:1.5rem;">
                <div class="card" style="border-top: 4px solid var(--success);">
                    <h3 class="card__title">Going</h3>
                    <div id="rsvp-act-yes" style="font-size:2.5rem; font-weight:800; color:var(--success); margin:0.5rem 0;">0</div>
                    <ul class="list-plain" id="rsvp-act-yes-list" style="border-top:1px solid #eee; padding-top:0.5rem;"></ul>
                </div>
                <div class="card" style="border-top: 4px solid var(--warning);">
                    <h3 class="card__title">Maybe</h3>
                    <div id="rsvp-act-maybe" style="font-size:2.5rem; font-weight:800; color:var(--warning); margin:0.5rem 0;">0</div>
                    <ul class="list-plain" id="rsvp-act-maybe-list" style="border-top:1px solid #eee; padding-top:0.5rem;"></ul>
                </div>
                <div class="card" style="border-top: 4px solid var(--danger);">
                    <h3 class="card__title">Not going</h3>
                    <div id="rsvp-act-no" style="font-size:2.5rem; font-weight:800; color:var(--danger); margin:0.5rem 0;">0</div>
                    <ul class="list-plain" id="rsvp-act-no-list" style="border-top:1px solid #eee; padding-top:0.5rem;"></ul>
                </div>
            </div>
        </div>`;

    // ربط العناصر بـ JavaScript للتحكم بها
    const sel = container.querySelector("#rsvp-activity-select");
    const yesEl = container.querySelector("#rsvp-act-yes"), maybeEl = container.querySelector("#rsvp-act-maybe"), noEl = container.querySelector("#rsvp-act-no");
    const yesList = container.querySelector("#rsvp-act-yes-list"), maybeList = container.querySelector("#rsvp-act-maybe-list"), noList = container.querySelector("#rsvp-act-no-list");

    async function updateActivityDetails(activityId) {
        if (!activityId || !isValidDbActivityId(activityId)) return;
        
        // حالة التحميل
        [yesEl, maybeEl, noEl].forEach(el => el.textContent = "...");

        try {
            const data = await global.RSVP.fetchActivityRSVP(activityId);
            if (data) {
                yesEl.textContent = data.counts.yes || 0;
                maybeEl.textContent = data.counts.maybe || 0;
                noEl.textContent = data.counts.no || 0;

                yesList.innerHTML = (data.names.yes || []).map(name => `<li>${escapeHtml(name)}</li>`).join("");
                maybeList.innerHTML = (data.names.maybe || []).map(name => `<li>${escapeHtml(name)}</li>`).join("");
                noList.innerHTML = (data.names.no || []).map(name => `<li>${escapeHtml(name)}</li>`).join("");
            }
        } catch (e) {
            [yesEl, maybeEl, noEl].forEach(el => el.textContent = "0");
            console.error("RSVP Fetch Error:", e);
        }
    }

    if (sel && activities.length) {
        sel.addEventListener("change", () => updateActivityDetails(sel.value));
        updateActivityDetails(sel.value);
    }
}

  // Export for use in itinerary.js
  global.RSVP = { render, postActivityRSVP, fetchActivityRSVP };
})(typeof window !== "undefined" ? window : globalThis);
