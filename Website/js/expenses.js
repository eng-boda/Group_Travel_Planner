/**
 * Expenses: equal/manual split, balances, history, budget bar
 */
(function (global) {
  const { escapeHtml, openModal, toast, memberSelectOptions } = global.UI;
  const { generateId, getTrip } = global.Storage;

  function computeBalances(trip) {
    const members = trip.members.filter((m) => m.status === "active");
    const net = {};
    members.forEach((m) => {
      net[m.id] = 0;
    });

    trip.expenses.forEach((e) => {
      const amount = e.amount || 0;
      const payer = e.paidBy;
      const splits = e.splits || {};
      const payerShare = splits[payer] != null ? splits[payer] : 0;
      net[payer] = (net[payer] || 0) + (amount - payerShare);
      members.forEach((m) => {
        if (m.id === payer) return;
        const sh = splits[m.id] != null ? splits[m.id] : 0;
        net[m.id] = (net[m.id] || 0) - sh;
      });
    });

    const creditors = [];
    const debtors = [];
    Object.keys(net).forEach((id) => {
      const v = Math.round(net[id] * 100) / 100;
      if (v > 0.01) creditors.push({ id, amount: v });
      if (v < -0.01) debtors.push({ id, amount: -v });
    });
    return { net, creditors, debtors };
  }

  function settleSuggestions(creditors, debtors, trip) {
    const name = (id) => {
      const m = trip.members.find((x) => x.id === id);
      return m ? m.name : id;
    };
    const lines = [];
    const c = creditors.map((x) => ({ ...x }));
    const d = debtors.map((x) => ({ ...x }));
    let i = 0,
      j = 0;
    while (i < c.length && j < d.length) {
      const pay = Math.min(c[i].amount, d[j].amount);
      if (pay > 0.01)
        lines.push(
          escapeHtml(name(d[j].id)) + " owes " + escapeHtml(name(c[i].id)) + " <strong>$" + pay.toFixed(2) + "</strong>"
        );
      c[i].amount -= pay;
      d[j].amount -= pay;
      if (c[i].amount < 0.02) i++;
      if (d[j].amount < 0.02) j++;
    }
    return lines;
  }

  function openExpenseModal(state, trip, expense) {
    const isEdit = !!expense;
    const activeMembers = trip.members.filter((m) => m.status === "active");
    const body = document.createElement("div");
    body.className = "form-grid";
    body.innerHTML =
      '<div class="form-row"><label>Title</label><input id="e-title" class="input" value="' +
      escapeHtml(expense ? expense.title : "") +
      '" /></div>' +
      '<div class="form-row"><label>Amount</label><input id="e-amt" type="number" min="0" step="0.01" class="input" value="' +
      escapeHtml(expense ? expense.amount : "") +
      '" /></div>' +
      '<div class="form-row"><label>Paid by</label><select id="e-payer" class="input">' +
      memberSelectOptions(activeMembers, expense ? expense.paidBy : activeMembers[0]?.id) +
      "</select></div>" +
      '<div class="form-row"><label>Split</label><select id="e-split" class="input">' +
      '<option value="equal"' +
      (!expense || expense.splitType === "equal" ? " selected" : "") +
      ">Equal</option>" +
      '<option value="manual"' +
      (expense && expense.splitType === "manual" ? " selected" : "") +
      ">Manual</option>" +
      "</select></div>" +
      '<div class="form-row" id="e-manual-wrap" hidden><label>Amount per member</label><div id="e-manual-fields"></div></div>';

    function buildManualFields() {
      const wrap = body.querySelector("#e-manual-fields");
      wrap.innerHTML = activeMembers
        .map(
          (m) =>
            '<div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.35rem;">' +
            '<span style="min-width:100px;font-size:0.85rem;">' +
            escapeHtml(m.name) +
            '</span><input type="number" min="0" step="0.01" class="input e-split-inp" data-mid="' +
            escapeHtml(m.id) +
            '" value="' +
            escapeHtml(
              expense && expense.splits && expense.splits[m.id] != null ? expense.splits[m.id] : ""
            ) +
            '" /></div>'
        )
        .join("");
    }

    const splitSel = body.querySelector("#e-split");
    const manualWrap = body.querySelector("#e-manual-wrap");
    function toggleManual() {
      const isMan = splitSel.value === "manual";
      manualWrap.hidden = !isMan;
      if (isMan) buildManualFields();
    }
    splitSel.addEventListener("change", toggleManual);
    toggleManual();

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
    save.textContent = isEdit ? "Save" : "Add expense";
    footer.appendChild(cancel);
    footer.appendChild(save);

    const dlg = openModal({ title: isEdit ? "Edit expense" : "New expense", body, footer, wide: true });
    cancel.addEventListener("click", () => dlg.close());
    save.addEventListener("click", () => {
      const title = body.querySelector("#e-title").value.trim();
      const amount = parseFloat(body.querySelector("#e-amt").value);
      const paidBy = body.querySelector("#e-payer").value;
      const splitType = body.querySelector("#e-split").value;
      if (!title || !(amount >= 0)) {
        toast("Title and valid amount required");
        return;
      }
      let splits = {};
      if (splitType === "equal") {
        const n = activeMembers.length || 1;
        const each = Math.round((amount / n) * 100) / 100;
        let sum = 0;
        activeMembers.forEach((m, i) => {
          const v = i === activeMembers.length - 1 ? amount - sum : each;
          splits[m.id] = Math.round(v * 100) / 100;
          sum += splits[m.id];
        });
      } else {
        body.querySelectorAll(".e-split-inp").forEach((inp) => {
          splits[inp.getAttribute("data-mid")] = parseFloat(inp.value) || 0;
        });
        const s = Object.values(splits).reduce((a, b) => a + b, 0);
        if (Math.abs(s - amount) > 0.05) {
          toast("Manual splits should sum to the expense amount");
          return;
        }
      }

      if (isEdit) {
        expense.title = title;
        expense.amount = amount;
        expense.paidBy = paidBy;
        expense.splitType = splitType;
        expense.splits = splits;
      } else {
        trip.expenses.push({
          id: generateId("exp"),
          title,
          amount,
          paidBy,
          splitType,
          splits,
          createdAt: Date.now(),
        });
      }
      global.Storage.save(state);
      toast(isEdit ? "Expense updated" : "Expense added");
      dlg.close();
      global.App.refresh();
    });
  }

  function render(container, state) {
    const trip = state.activeTripId ? getTrip(state, state.activeTripId) : null;
    const actions = document.getElementById("topbar-actions");
    if (actions) {
      actions.innerHTML =
        '<button type="button" class="btn btn--primary" id="btn-add-exp">+ Add expense</button>';
    }

    if (!trip) {
      container.innerHTML =
        '<div class="empty"><div class="empty__icon">$</div><p>Select a trip for expenses.</p></div>';
      return;
    }

    if (!global.Permissions.isTripParticipant(trip, state)) {
      if (actions) actions.innerHTML = "";
      container.innerHTML =
        '<div class="empty"><div class="empty__icon">🔒</div><p>You are not a member of this trip.</p></div>';
      return;
    }

    const totalSpent = trip.expenses.reduce((s, e) => s + (e.amount || 0), 0);
    const budget = trip.budget || 0;
    const pct = budget > 0 ? Math.min(100, Math.round((totalSpent / budget) * 100)) : 0;
    const { creditors, debtors } = computeBalances(trip);
    const lines = settleSuggestions(creditors, debtors, trip);
    const name = (id) => {
      const m = trip.members.find((x) => x.id === id);
      return m ? m.name : id;
    };

    container.innerHTML =
      '<div class="grid grid--2" style="margin-bottom:1rem;">' +
      '<div class="card card--gradient">' +
      '<h3 class="card__title">Budget progress</h3>' +
      '<p style="margin:0.5rem 0;font-size:0.95rem;">Spent <strong>$' +
      totalSpent.toFixed(2) +
      "</strong> of <strong>$" +
      budget.toFixed(2) +
      "</strong></p>" +
      '<div class="progress"><div class="progress__bar" style="width:' +
      pct +
      '%"></div></div>' +
      '<p class="muted" style="margin-top:0.5rem;font-size:0.85rem;">' +
      pct +
      "% utilized</p>" +
      "</div>" +
      '<div class="card">' +
      '<h3 class="card__title">Who owes who</h3>' +
      (lines.length
        ? "<ul class=\"list-plain\">" +
          lines.map((l) => "<li>" + l + "</li>").join("") +
          "</ul>"
        : '<p class="muted">All square — add expenses to see settlements.</p>') +
      "</div></div>" +
      '<div class="tabs" id="exp-tabs">' +
      '<button type="button" class="tab is-active" data-tab="hist">History</button>' +
      '<button type="button" class="tab" data-tab="bal">Balances</button>' +
      "</div>" +
      '<div class="tab-panel" data-tab-panel="hist"><div class="card"><ul class="list-plain" id="exp-list"></ul></div></div>' +
      '<div class="tab-panel" data-tab-panel="bal" hidden><div class="card"><ul class="list-plain" id="bal-list"></ul></div></div>';

    global.UI.initTabs(container.querySelector("#exp-tabs"));

    const list = container.querySelector("#exp-list");
    const sorted = trip.expenses.slice().sort((a, b) => (b.createdAt || 0) - (a.createdAt || 0));
    sorted.forEach((e) => {
      const li = document.createElement("li");
      li.style.display = "flex";
      li.style.justifyContent = "space-between";
      li.style.alignItems = "center";
      li.style.gap = "0.5rem";
      li.style.flexWrap = "wrap";
      li.innerHTML =
        "<div><strong>" +
        escapeHtml(e.title) +
        "</strong> · $" +
        (e.amount || 0).toFixed(2) +
        "<br/><span class=\"muted\" style=\"font-size:0.8rem;\">Paid by " +
        escapeHtml(name(e.paidBy)) +
        " · " +
        escapeHtml(e.splitType) +
        " split</span></div>" +
        '<div style="display:flex;gap:0.35rem;">' +
        '<button type="button" class="btn btn--sm btn--secondary" data-edit-exp="' +
        escapeHtml(e.id) +
        '">Edit</button>' +
        '<button type="button" class="btn btn--sm btn--danger" data-del-exp="' +
        escapeHtml(e.id) +
        '">Delete</button>' +
        "</div>";
      list.appendChild(li);
    });
    if (!sorted.length) list.innerHTML = '<li class="muted">No expenses yet.</li>';

    list.querySelectorAll("[data-edit-exp]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const ex = trip.expenses.find((x) => x.id === btn.getAttribute("data-edit-exp"));
        if (ex) openExpenseModal(state, trip, ex);
      });
    });
    list.querySelectorAll("[data-del-exp]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const id = btn.getAttribute("data-del-exp");
        if (!confirm("Delete expense?")) return;
        trip.expenses = trip.expenses.filter((x) => x.id !== id);
        global.Storage.save(state);
        toast("Expense deleted");
        global.App.refresh();
      });
    });

    const balList = container.querySelector("#bal-list");
    const { net } = computeBalances(trip);
    trip.members
      .filter((m) => m.status === "active")
      .forEach((m) => {
      const v = net[m.id] || 0;
      const li = document.createElement("li");
      li.innerHTML =
        "<strong>" +
        escapeHtml(m.name) +
        "</strong> · " +
        (v >= 0 ? "net +" : "net ") +
        v.toFixed(2);
      balList.appendChild(li);
    });

    document.getElementById("btn-add-exp")?.addEventListener("click", () => openExpenseModal(state, trip, null));
  }

  global.Expenses = { render };
})(typeof window !== "undefined" ? window : globalThis);
