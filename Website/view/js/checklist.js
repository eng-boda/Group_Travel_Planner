/**
 * Checklist: items, assignees, status workflow
 */
(function (global) {
  const { escapeHtml, openModal, toast, memberSelectOptions } = global.UI;
  const { generateId, getTrip } = global.Storage;

  const STATUSES = [
    { id: "pending", label: "Pending" },
    { id: "assigned", label: "Assigned" },
    { id: "completed", label: "Completed" },
  ];

  function openItemModal(state, trip, item) {
    const isEdit = !!item;
    const activeMembers = trip.members.filter((m) => m.status === "active");
    const body = document.createElement("div");
    body.className = "form-grid";
    body.innerHTML =
      '<div class="form-row"><label>Title</label><input id="c-title" class="input" value="' +
      escapeHtml(item ? item.title : "") +
      '" /></div>' +
      '<div class="form-row"><label>Assign to</label><select id="c-asg" class="input">' +
      memberSelectOptions(activeMembers, item ? item.assigneeId : activeMembers[0]?.id) +
      "</select></div>" +
      '<div class="form-row"><label>Status</label><select id="c-st" class="input">' +
      STATUSES.map((s) => {
        const sel = item ? item.status === s.id : s.id === "pending";
        return (
          '<option value="' +
          s.id +
          '"' +
          (sel ? " selected" : "") +
          ">" +
          escapeHtml(s.label) +
          "</option>"
        );
      }).join("") +
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
    save.textContent = isEdit ? "Save" : "Add item";
    footer.appendChild(cancel);
    footer.appendChild(save);

    const dlg = openModal({ title: isEdit ? "Edit checklist item" : "New checklist item", body, footer });
    cancel.addEventListener("click", () => dlg.close());
    save.addEventListener("click", () => {
      const title = body.querySelector("#c-title").value.trim();
      if (!title) {
        toast("Title required");
        return;
      }
      const assigneeId = body.querySelector("#c-asg").value;
      const status = body.querySelector("#c-st").value;
      if (isEdit) {
        item.title = title;
        item.assigneeId = assigneeId;
        item.status = status;
      } else {
        const maxOrder = trip.checklist.reduce((m, x) => Math.max(m, x.order || 0), -1);
        trip.checklist.push({
          id: generateId("chk"),
          title,
          assigneeId,
          status,
          order: maxOrder + 1,
        });
      }
      global.Storage.save(state);
      toast(isEdit ? "Item updated" : "Item added");
      dlg.close();
      global.App.refresh();
    });
  }

  function name(trip, id) {
    const m = trip.members.find((x) => x.id === id);
    return m ? m.name : "—";
  }

  function render(container, state) {
    const trip = state.activeTripId ? getTrip(state, state.activeTripId) : null;
    const actions = document.getElementById("topbar-actions");
    if (actions) {
      actions.innerHTML =
        '<button type="button" class="btn btn--primary" id="btn-chk-add">+ Add item</button>';
    }

    if (!trip) {
      container.innerHTML =
        '<div class="empty"><div class="empty__icon">☑</div><p>Select a trip for the checklist.</p></div>';
      return;
    }

    if (!global.Permissions.isTripParticipant(trip, state)) {
      if (actions) actions.innerHTML = "";
      container.innerHTML =
        '<div class="empty"><div class="empty__icon">🔒</div><p>You are not a member of this trip.</p></div>';
      return;
    }

    const byStatus = { pending: [], assigned: [], completed: [] };
    trip.checklist
      .slice()
      .sort((a, b) => (a.order || 0) - (b.order || 0))
      .forEach((it) => {
        const s = byStatus[it.status] ? it.status : "pending";
        byStatus[s].push(it);
      });

    container.innerHTML =
      '<div class="tabs" id="chk-tabs">' +
      '<button type="button" class="tab is-active" data-tab="all">All</button>' +
      '<button type="button" class="tab" data-tab="pending">Pending</button>' +
      '<button type="button" class="tab" data-tab="assigned">Assigned</button>' +
      '<button type="button" class="tab" data-tab="completed">Completed</button>' +
      "</div>" +
      '<div class="tab-panel" data-tab-panel="all"><div class="grid grid--1" id="chk-all"></div></div>' +
      '<div class="tab-panel" data-tab-panel="pending" hidden><div id="chk-pending"></div></div>' +
      '<div class="tab-panel" data-tab-panel="assigned" hidden><div id="chk-assigned"></div></div>' +
      '<div class="tab-panel" data-tab-panel="completed" hidden><div id="chk-completed"></div></div>';

    global.UI.initTabs(container.querySelector("#chk-tabs"));

    function renderList(el, items) {
      if (!items.length) {
        el.innerHTML = '<p class="muted">Nothing here.</p>';
        return;
      }
      el.innerHTML = "";
      items.forEach((it) => {
        const card = document.createElement("div");
        card.className = "card";
        card.style.marginBottom = "0.65rem";
        const stLabel = STATUSES.find((s) => s.id === it.status)?.label || it.status;
        card.innerHTML =
          '<div class="card__header">' +
          '<h3 class="card__title" style="font-size:0.95rem;">' +
          escapeHtml(it.title) +
          "</h3>" +
          '<span class="badge ' +
          (it.status === "completed"
            ? "badge--confirmed"
            : it.status === "assigned"
              ? "badge--info"
              : "badge--draft") +
          '">' +
          escapeHtml(stLabel) +
          "</span></div>" +
          '<p class="muted" style="font-size:0.85rem;margin:0 0 0.5rem;">Assignee: ' +
          escapeHtml(name(trip, it.assigneeId)) +
          "</p>" +
          '<div style="display:flex;flex-wrap:wrap;gap:0.35rem;">' +
          '<button type="button" class="btn btn--sm btn--secondary" data-ed="' +
          escapeHtml(it.id) +
          '">Edit</button>' +
          '<button type="button" class="btn btn--sm btn--secondary" data-st="' +
          escapeHtml(it.id) +
          '" data-next="pending">→ Pending</button>' +
          '<button type="button" class="btn btn--sm btn--secondary" data-st="' +
          escapeHtml(it.id) +
          '" data-next="assigned">→ Assigned</button>' +
          '<button type="button" class="btn btn--sm btn--secondary" data-st="' +
          escapeHtml(it.id) +
          '" data-next="completed">→ Done</button>' +
          '<button type="button" class="btn btn--sm btn--danger" data-del="' +
          escapeHtml(it.id) +
          '">Delete</button>' +
          "</div>";
        el.appendChild(card);

        card.querySelector("[data-ed]")?.addEventListener("click", () => {
          const x = trip.checklist.find((c) => c.id === it.id);
          if (x) openItemModal(state, trip, x);
        });
        card.querySelectorAll("[data-st]").forEach((btn) => {
          btn.addEventListener("click", () => {
            const id = btn.getAttribute("data-st");
            const nx = btn.getAttribute("data-next");
            const x = trip.checklist.find((c) => c.id === id);
            if (x) {
              x.status = nx;
              global.Storage.save(state);
              toast("Status updated");
              global.App.refresh();
            }
          });
        });
        card.querySelector("[data-del]")?.addEventListener("click", () => {
          if (!confirm("Delete item?")) return;
          trip.checklist = trip.checklist.filter((c) => c.id !== it.id);
          global.Storage.save(state);
          toast("Item removed");
          global.App.refresh();
        });
      });
    }

    renderList(container.querySelector("#chk-all"), trip.checklist);
    renderList(container.querySelector("#chk-pending"), byStatus.pending);
    renderList(container.querySelector("#chk-assigned"), byStatus.assigned);
    renderList(container.querySelector("#chk-completed"), byStatus.completed);

    document.getElementById("btn-chk-add")?.addEventListener("click", () => openItemModal(state, trip, null));
  }

  global.Checklist = { render };
})(typeof window !== "undefined" ? window : globalThis);
