/**
 * Documents: simulated upload, list, link to activities (UI)
 */
(function (global) {
  const { escapeHtml, openModal, toast } = global.UI;
  const { generateId, getTrip } = global.Storage;

  function openAttachModal(state, trip, doc) {
    const days = trip.itineraryDays || [];
    const body = document.createElement("div");
    body.className = "form-grid";
    let opts = '<option value="">— Not attached —</option>';
    days.forEach((d) => {
      d.activities.forEach((a) => {
        opts +=
          '<option value="' +
          escapeHtml(a.id) +
          '"' +
          (doc.activityId === a.id ? " selected" : "") +
          ">" +
          escapeHtml((d.label || "Day " + d.dayNumber) + ": " + a.title) +
          "</option>";
      });
    });
    body.innerHTML =
      '<p class="muted">Link this file to an itinerary activity (display only).</p>' +
      '<div class="form-row"><label>Activity</label><select id="lnk-act" class="input">' +
      opts +
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
    save.textContent = "Save link";
    footer.appendChild(cancel);
    footer.appendChild(save);

    const dlg = openModal({ title: escapeHtml(doc.name), body, footer });
    cancel.addEventListener("click", () => dlg.close());
    save.addEventListener("click", () => {
      const actId = body.querySelector("#lnk-act").value || null;
      days.forEach((d) => {
        d.activities.forEach((a) => {
          if (a.documentIds) a.documentIds = a.documentIds.filter((x) => x !== doc.id);
        });
      });
      doc.activityId = actId;
      if (actId) {
        days.forEach((d) => {
          d.activities.forEach((a) => {
            if (a.id === actId) {
              a.documentIds = a.documentIds || [];
              if (a.documentIds.indexOf(doc.id) < 0) a.documentIds.push(doc.id);
            }
          });
        });
      }
      global.Storage.save(state);
      toast("Attachment updated");
      dlg.close();
      global.App.refresh();
    });
  }

  function render(container, state) {
    const trip = state.activeTripId ? getTrip(state, state.activeTripId) : null;
    const actions = document.getElementById("topbar-actions");
    if (actions) {
      actions.innerHTML =
        '<label class="btn btn--primary" style="cursor:pointer;margin:0;">+ Upload<input type="file" id="doc-file" hidden multiple /></label>';
    }

    if (!trip) {
      container.innerHTML =
        '<div class="empty"><div class="empty__icon">📄</div><p>Select a trip for documents.</p></div>';
      return;
    }

    if (!global.Permissions.isTripParticipant(trip, state)) {
      if (actions) actions.innerHTML = "";
      container.innerHTML =
        '<div class="empty"><div class="empty__icon">🔒</div><p>You are not a member of this trip.</p></div>';
      return;
    }

    container.innerHTML =
      '<div class="card">' +
      '<div class="card__header"><h3 class="card__title">Library</h3><span class="badge badge--info">' +
      trip.documents.length +
      " files</span></div>" +
      '<ul class="list-plain" id="doc-list"></ul></div>' +
      '<p class="muted" style="margin-top:0.75rem;font-size:0.85rem;">Uploads are simulated — file metadata is stored locally. Attach files to activities from here or from the itinerary editor.</p>';

    const list = container.querySelector("#doc-list");
    trip.documents.forEach((d) => {
      let actLabel = "—";
      if (d.activityId) {
        for (const day of trip.itineraryDays) {
          const a = day.activities.find((x) => x.id === d.activityId);
          if (a) {
            actLabel = (day.label || "Day " + day.dayNumber) + ": " + a.title;
            break;
          }
        }
      }
      const li = document.createElement("li");
      li.style.display = "flex";
      li.style.justifyContent = "space-between";
      li.style.alignItems = "center";
      li.style.gap = "0.5rem";
      li.style.flexWrap = "wrap";
      li.innerHTML =
        "<div><strong>" +
        escapeHtml(d.name) +
        "</strong><br/><span class=\"muted\" style=\"font-size:0.8rem;\">" +
        escapeHtml(d.sizeLabel) +
        " · Linked: " +
        escapeHtml(actLabel) +
        "</span></div>" +
        '<div style="display:flex;gap:0.35rem;">' +
        '<button type="button" class="btn btn--sm btn--secondary" data-attach="' +
        escapeHtml(d.id) +
        '">Attach…</button>' +
        '<button type="button" class="btn btn--sm btn--danger" data-del-doc="' +
        escapeHtml(d.id) +
        '">Delete</button>' +
        "</div>";
      list.appendChild(li);
    });
    if (!trip.documents.length) list.innerHTML = '<li class="muted">No documents yet. Upload to add.</li>';

    list.querySelectorAll("[data-attach]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const doc = trip.documents.find((x) => x.id === btn.getAttribute("data-attach"));
        if (doc) openAttachModal(state, trip, doc);
      });
    });
    list.querySelectorAll("[data-del-doc]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const id = btn.getAttribute("data-del-doc");
        if (!confirm("Remove document reference?")) return;
        trip.documents = trip.documents.filter((x) => x.id !== id);
        trip.itineraryDays.forEach((d) => {
          d.activities.forEach((a) => {
            if (a.documentIds) a.documentIds = a.documentIds.filter((x) => x !== id);
          });
        });
        global.Storage.save(state);
        toast("Document removed");
        global.App.refresh();
      });
    });

    document.getElementById("doc-file")?.addEventListener("change", (e) => {
      const files = e.target.files;
      if (!files || !files.length) return;
      for (let i = 0; i < files.length; i++) {
        const f = files[i];
        const kb = Math.max(1, Math.round(f.size / 1024));
        trip.documents.push({
          id: generateId("doc"),
          name: f.name,
          sizeLabel: kb + " KB",
          activityId: null,
          uploadedAt: Date.now(),
        });
      }
      global.Storage.save(state);
      toast(files.length + " file(s) added");
      e.target.value = "";
      global.App.refresh();
    });
  }

  global.Documents = { render };
})(typeof window !== "undefined" ? window : globalThis);
