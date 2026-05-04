/**
 * Expenses page only — Features 12–22: multi-currency, splits, debt settlement,
 * analytics, kitty, OCR sim, recurring, budget alerts, settlement workflow,
 * non-cash, refunds. Local mock data + page-local state.
 */
(function (global) {
  const { escapeHtml, toast, openModal, initTabs } = global.UI;
  const { generateId, getTrip } = global.Storage;

  /* ========== FEATURE 12: Multi-currency (from→to rates) ========== */
  const MOCK_CURRENCIES = [
    { code: "USD", name: "US Dollar", decimalPlaces: 2 },
    { code: "EUR", name: "Euro", decimalPlaces: 2 },
    { code: "GBP", name: "British Pound", decimalPlaces: 2 },
    { code: "JPY", name: "Japanese Yen", decimalPlaces: 0 },
    { code: "CAD", name: "Canadian Dollar", decimalPlaces: 2 },
    { code: "EGP", name: "Egyptian Pound", decimalPlaces: 2 },
  ];

  /** 1 unit of from_currency × rate = to_currency */
  const MOCK_EXCHANGE_RATES = [
    { from_currency: "USD", to_currency: "USD", rate: 1 },
    { from_currency: "EUR", to_currency: "USD", rate: 1.09 },
    { from_currency: "GBP", to_currency: "USD", rate: 1.27 },
    { from_currency: "CAD", to_currency: "USD", rate: 0.74 },
    { from_currency: "EGP", to_currency: "USD", rate: 0.0204 },
    { from_currency: "JPY", to_currency: "USD", rate: 0.00645 },
    { from_currency: "USD", to_currency: "JPY", rate: 155.2 },
    { from_currency: "EUR", to_currency: "JPY", rate: 169.2 },
    { from_currency: "GBP", to_currency: "JPY", rate: 197.1 },
    { from_currency: "JPY", to_currency: "JPY", rate: 1 },
    { from_currency: "CAD", to_currency: "JPY", rate: 114.8 },
    { from_currency: "EGP", to_currency: "JPY", rate: 3.16 },
  ];

  const MOCK_CATEGORIES = [
    { category_id: "cat_transport", name: "Transport", icon: "🚗" },
    { category_id: "cat_lodging", name: "Lodging", icon: "🏨" },
    { category_id: "cat_food", name: "Food & dining", icon: "🍽" },
    { category_id: "cat_other", name: "Other", icon: "📌" },
  ];

  const MOCK_TRIPS = [
    {
      trip_id: "trip_berlin",
      name: "Berlin workshop",
      base_currency: "USD",
      budget: 8000,
      members: [
        { userId: "m_ali", name: "Ali" },
        { userId: "m_sara", name: "Sara" },
        { userId: "m_mona", name: "Mona" },
        { userId: "m_ahmed", name: "Ahmed" },
      ],
    },
    {
      trip_id: "trip_lisbon",
      name: "Lisbon offsite",
      base_currency: "USD",
      budget: 5000,
      members: [
        { userId: "m_ali", name: "Ali" },
        { userId: "m_sara", name: "Sara" },
        { userId: "m_mona", name: "Mona" },
      ],
    },
    {
      trip_id: "trip_tokyo",
      name: "Tokyo summit",
      base_currency: "JPY",
      budget: 900000,
      members: [
        { userId: "m_ali", name: "Ali" },
        { userId: "m_sara", name: "Sara" },
      ],
    },
  ];

  const CAT_NONCASH = { category_id: "cat_noncash", name: "Non-cash", icon: "🎁" };

  /* ========== Page-local state ========== */
  let pageExpenses = [];
  let editingExpenseId = null;
  let filterTripId = "";
  let filterCategoryId = "";
  let filterSearch = "";
  let budgetAlertDismissed = {}; // trip_id -> true
  let settlementState = {}; // trip_id -> { settlementId, status, approvals: { userId: bool } }
  let nonCashItems = [];
  let pageRefunds = []; // flat log for summary { refundId, expense_id, totalAmount, trip_id, note }
  let recurringSchedules = []; // { recurringId, trip_id, startDate, endDate, frequency, cancelled, sourceExpenseId }
  let kittyByTrip = {}; // trip_id -> { kitty_id, contributions: [{ contributionId, amount, userId, name }] }

  function tripById(id) {
    return MOCK_TRIPS.find((t) => t.trip_id === id) || null;
  }

  function categoryById(id) {
    if (id === CAT_NONCASH.category_id) return CAT_NONCASH;
    return MOCK_CATEGORIES.find((c) => c.category_id === id) || MOCK_CATEGORIES[MOCK_CATEGORIES.length - 1];
  }

  function currencyByCode(code) {
    return MOCK_CURRENCIES.find((c) => c.code === code) || { code, name: code, decimalPlaces: 2 };
  }

  function formatMoney(amount, currencyCode) {
    const meta = currencyByCode(currencyCode);
    try {
      return new Intl.NumberFormat(undefined, {
        style: "currency",
        currency: currencyCode,
        minimumFractionDigits: meta.decimalPlaces,
        maximumFractionDigits: meta.decimalPlaces,
      }).format(amount);
    } catch {
      return (Math.round(amount * 100) / 100).toFixed(meta.decimalPlaces) + " " + currencyCode;
    }
  }

  function getDirectRate(from, to) {
    if (!from || !to) return null;
    if (from === to) return 1;
    const row = MOCK_EXCHANGE_RATES.find((r) => r.from_currency === from && r.to_currency === to);
    return row && row.rate > 0 ? row.rate : null;
  }

  function convertToBase(originalAmount, originalCurrency, tripId) {
    const trip = tripById(tripId);
    if (!trip) return { ok: false, converted: null, rate: null, base: "USD" };
    const base = trip.base_currency;
    const rate = getDirectRate(originalCurrency, base);
    if (rate == null) return { ok: false, converted: null, rate: null, base };
    const converted = Math.round(originalAmount * rate * 100) / 100;
    return { ok: true, converted, rate, base };
  }

  function netConverted(e) {
    const r = (e.refunds || []).reduce((s, x) => s + (x.totalAmount || 0), 0);
    return Math.max(0, (e.converted_amount || 0) - r);
  }

  function ensureKitty(tripId) {
    const t = tripById(tripId);
    if (!t) return null;
    if (!kittyByTrip[tripId]) {
      kittyByTrip[tripId] = {
        kitty_id: tripId + "_kitty",
        trip_id: tripId,
        contributions: [],
      };
    }
    return kittyByTrip[tripId];
  }

  function kittyContributionsTotal(tripId) {
    const k = ensureKitty(tripId);
    return k.contributions.reduce((s, c) => s + (c.amount || 0), 0);
  }

  function kittySpentTotal(tripId) {
    return pageExpenses
      .filter((e) => e.trip_id === tripId && e.pay_from_kitty)
      .reduce((s, e) => s + (e.converted_amount || 0), 0);
  }

  function kittyBalance(tripId) {
    return kittyContributionsTotal(tripId) - kittySpentTotal(tripId);
  }

  function tripBudgetAmount(tripId, appState) {
    const mock = tripById(tripId);
    if (mock && mock.budget != null) return mock.budget;
    if (appState && appState.activeTripId === tripId) {
      const tr = getTrip(appState, tripId);
      if (tr && tr.budget != null) return tr.budget;
    }
    return mock ? mock.budget : 0;
  }

  function totalSpendForBudget(tripId) {
    return pageExpenses
      .filter((e) => e.trip_id === tripId)
      .reduce((s, e) => s + netConverted(e), 0);
  }

  function budgetBarHtml(tripId, appState) {
    const budget = tripBudgetAmount(tripId, appState);
    const spent = totalSpendForBudget(tripId);
    const pct = budget > 0 ? Math.min(100, Math.round((spent / budget) * 100)) : 0;
    let color = "var(--success)";
    if (pct >= 90) color = "var(--danger)";
    else if (pct >= 70) color = "var(--warning)";
    const trip = tripById(tripId);
    const name = trip ? trip.name : tripId;
    const base = trip ? trip.base_currency : "USD";
    return (
      '<div class="card" style="margin-bottom:1rem;border-left:4px solid ' +
      color +
      ';">' +
      '<h3 class="card__title">Budget · ' +
      escapeHtml(name) +
      "</h3>" +
      '<p class="muted" style="margin:0 0 0.5rem;font-size:0.85rem;">' +
      escapeHtml(formatMoney(spent, base)) +
      " of " +
      escapeHtml(formatMoney(budget, base)) +
      " · " +
      pct +
      "% used</p>" +
      '<div class="progress" style="height:10px;"><div class="progress__bar" style="width:' +
      pct +
      "%;background:" +
      color +
      ';"></div></div>' +
      budgetAlertBanner(tripId, pct) +
      "</div>"
    );
  }

  function budgetAlertBanner(tripId, pct) {
    if (pct < 90 || budgetAlertDismissed[tripId]) return "";
    return (
      '<div id="exp-budget-alert-' +
      escapeHtml(tripId) +
      '" class="card" style="margin-top:0.75rem;padding:0.75rem;background:rgba(239,68,68,0.12);border:1px solid var(--danger);">' +
      '<p style="margin:0;font-weight:600;">⚠️ Warning: You have exceeded 90% of your trip budget!</p>' +
      '<button type="button" class="btn btn--sm btn--secondary" style="margin-top:0.5rem;" data-dismiss-budget="' +
      escapeHtml(tripId) +
      '">Dismiss</button></div>'
    );
  }

  function allBudgetBarsHtml(appState) {
    const ids = filterTripId ? [filterTripId] : MOCK_TRIPS.map((t) => t.trip_id);
    return ids.map((id) => budgetBarHtml(id, appState)).join("");
  }

  /* ========== FEATURE 15 + 21: Analytics (incl. non-cash) ========== */
  function analyticsSectionHtml() {
    const rows = pageExpenses.slice();
    const byTripBase = {};
    rows.forEach((e) => {
      const tr = tripById(e.trip_id);
      const b = tr ? tr.base_currency : "USD";
      if (!byTripBase[b]) byTripBase[b] = { total: 0, byCat: {} };
      const cat = e.category_id || "cat_other";
      const v = netConverted(e);
      byTripBase[b].total += v;
      byTripBase[b].byCat[cat] = (byTripBase[b].byCat[cat] || 0) + v;
    });
    nonCashItems.forEach((nc) => {
      const tr = tripById(nc.trip_id);
      const b = tr ? tr.base_currency : "USD";
      if (!byTripBase[b]) byTripBase[b] = { total: 0, byCat: {} };
      const v = nc.estimated_value_base || 0;
      byTripBase[b].total += v;
      const k = CAT_NONCASH.category_id;
      byTripBase[b].byCat[k] = (byTripBase[b].byCat[k] || 0) + v;
    });

    const bases = Object.keys(byTripBase);
    if (!bases.length) {
      return (
        '<div class="card" style="margin-bottom:1rem;"><h3 class="card__title">Category analytics</h3>' +
        '<p class="muted">Add expenses to see breakdown.</p></div>'
      );
    }

    let html = '<div class="card" style="margin-bottom:1rem;"><h3 class="card__title">Category analytics</h3>';
    bases.forEach((base) => {
      const { total, byCat } = byTripBase[base];
      if (total <= 0) return;
      const cats = Object.keys(byCat).map((id) => ({
        id,
        cat: categoryById(id),
        amt: byCat[id],
        pct: Math.round((byCat[id] / total) * 100),
      }));
      cats.sort((a, b) => b.amt - a.amt);
      const maxCat = cats.length ? cats[0] : null;
      const minCat = cats.length ? cats[cats.length - 1] : null;
      html += '<p class="muted" style="font-size:0.85rem;margin:0.5rem 0;">Base: ' + escapeHtml(base) + "</p>";
      html += '<div style="display:flex;flex-wrap:wrap;gap:0.35rem;margin-bottom:0.75rem;">';
      cats.forEach((c) => {
        html +=
          '<span class="badge badge--info" style="font-size:0.75rem;">' +
          escapeHtml(c.cat.name + " " + c.pct + "%") +
          "</span>";
      });
      html += "</div>";
      cats.forEach((c) => {
        const w = Math.round((c.amt / total) * 100);
        html +=
          '<div style="margin-bottom:0.6rem;">' +
          '<div style="display:flex;justify-content:space-between;font-size:0.85rem;"><span>' +
          escapeHtml(c.cat.icon + " " + c.cat.name) +
          "</span><strong>" +
          escapeHtml(formatMoney(c.amt, base)) +
          "</strong></div>" +
          '<div class="progress" style="height:8px;margin-top:0.25rem;"><div class="progress__bar" style="width:' +
          w +
          '%;"></div></div></div>';
      });
      if (maxCat && minCat) {
        html +=
          '<p class="muted" style="font-size:0.8rem;margin:0.75rem 0 0;">Most: <strong>' +
          escapeHtml(maxCat.cat.name) +
          "</strong> · Least: <strong>" +
          escapeHtml(minCat.cat.name) +
          "</strong></p>";
      }
    });
    html += "</div>";
    return html;
  }

  /* ========== FEATURE 13: splits ========== */
  function findMemberByPaidBy(trip, paidByText) {
    if (!trip || !paidByText) return null;
    const q = paidByText.trim().toLowerCase();
    return trip.members.find((m) => m.name.toLowerCase() === q || m.userId === q) || null;
  }

  function buildSplitsEqual(trip, netTotal) {
    const n = trip.members.length || 1;
    const each = Math.round((netTotal / n) * 100) / 100;
    let sum = 0;
    return trip.members.map((m, i) => {
      const amt = i === trip.members.length - 1 ? Math.round((netTotal - sum) * 100) / 100 : each;
      sum += amt;
      return { userId: m.userId, name: m.name, percentage: Math.round(10000 / n) / 100, amount: amt };
    });
  }

  function buildSplitsCustom(trip, netTotal, pctMap) {
    const rows = [];
    let acc = 0;
    trip.members.forEach((m, i) => {
      const p = pctMap[m.userId] || 0;
      const last = i === trip.members.length - 1;
      const raw = last ? netTotal - acc : (netTotal * p) / 100;
      const amt = Math.round(raw * 100) / 100;
      acc += amt;
      rows.push({ userId: m.userId, name: m.name, percentage: p, amount: amt });
    });
    return rows;
  }

  /** After refunds, scale split amounts so they sum to net converted (Feature 22). */
  function scaleSplitsToNet(e) {
    if (!e.splits || !e.splits.length || e.pay_from_kitty) return;
    const net = netConverted(e);
    const sum = e.splits.reduce((s, x) => s + (x.amount || 0), 0);
    if (sum <= 0) return;
    const f = net / sum;
    e.splits = e.splits.map((s) => ({
      ...s,
      amount: Math.round(s.amount * f * 100) / 100,
    }));
  }

  /* ========== FEATURE 14: debt minimization ========== */
  function computeMemberNets(tripId) {
    const trip = tripById(tripId);
    if (!trip) return {};
    const net = {};
    trip.members.forEach((m) => {
      net[m.userId] = 0;
    });
    pageExpenses.forEach((e) => {
      if (e.trip_id !== tripId || e.pay_from_kitty) return;
      const nc = netConverted(e);
      if (nc <= 0) return;
      const payer = findMemberByPaidBy(trip, e.paid_by);
      if (payer) net[payer.userId] = (net[payer.userId] || 0) + nc;
      (e.splits || []).forEach((s) => {
        net[s.userId] = (net[s.userId] || 0) - (s.amount || 0);
      });
    });
    return net;
  }

  function minimizeDebts(tripId) {
    const trip = tripById(tripId);
    if (!trip) return { txs: [], base: "USD" };
    const base = trip.base_currency;
    const net = computeMemberNets(tripId);
    const bal = trip.members.map((m) => ({
      userId: m.userId,
      name: m.name,
      n: Math.round((net[m.userId] || 0) * 100) / 100,
    }));
    const txs = [];
    const debtors = bal
      .filter((b) => b.n < -0.01)
      .map((b) => ({ ...b, owe: -b.n }))
      .sort((a, b) => b.owe - a.owe);
    const creditors = bal
      .filter((b) => b.n > 0.01)
      .map((b) => ({ ...b, credit: b.n }))
      .sort((a, b) => b.credit - a.credit);
    let di = 0,
      ci = 0;
    while (di < debtors.length && ci < creditors.length) {
      const pay = Math.min(debtors[di].owe, creditors[ci].credit);
      if (pay > 0.01) {
        txs.push({
          fromName: debtors[di].name,
          toName: creditors[ci].name,
          amount: Math.round(pay * 100) / 100,
          base,
        });
      }
      debtors[di].owe -= pay;
      creditors[ci].credit -= pay;
      if (debtors[di].owe < 0.02) di++;
      if (creditors[ci].credit < 0.02) ci++;
    }
    return { txs, base };
  }

  /* ========== Settlement (Feature 20) ========== */
  function ensureSettlement(tripId) {
    if (!settlementState[tripId]) {
      settlementState[tripId] = {
        settlementId: generateId("stl"),
        status: "pending",
        tripId,
        approvals: {},
      };
    }
    return settlementState[tripId];
  }

  function isTripLocked(tripId) {
    const s = settlementState[tripId];
    return s && s.status === "settled";
  }

  function allSigned(tripId) {
    const trip = tripById(tripId);
    const s = settlementState[tripId];
    if (!trip || !s) return false;
    return trip.members.every((m) => s.approvals[m.userId]);
  }

  /* ========== Recurring (Feature 18) ========== */
  function countOccurrences(schedule) {
    return pageExpenses.filter((e) => e.recurring_id === schedule.recurringId).length;
  }

  function nextOccurrenceDate(schedule) {
    if (schedule.cancelled) return null;
    const start = new Date(schedule.startDate + "T12:00:00");
    const end = new Date(schedule.endDate + "T12:00:00");
    const now = new Date();
    let cur = new Date(start);
    const stepDays = schedule.frequency === "Daily" ? 1 : schedule.frequency === "Weekly" ? 7 : 30;
    while (cur <= end) {
      if (cur >= now) return cur.toISOString().slice(0, 10);
      cur.setDate(cur.getDate() + stepDays);
    }
    return null;
  }

  function remainingOccurrences(schedule) {
    if (schedule.cancelled) return 0;
    const start = new Date(schedule.startDate + "T12:00:00");
    const end = new Date(schedule.endDate + "T12:00:00");
    let cur = new Date(start);
    const stepDays = schedule.frequency === "Daily" ? 1 : schedule.frequency === "Weekly" ? 7 : 30;
    let n = 0;
    const now = new Date();
    while (cur <= end) {
      if (cur >= now) n++;
      cur.setDate(cur.getDate() + stepDays);
    }
    return n;
  }

  /* ========== Filters / summary ========== */
  function getFilteredExpenses() {
    return pageExpenses.filter((e) => {
      if (filterTripId && e.trip_id !== filterTripId) return false;
      if (filterCategoryId && e.category_id !== filterCategoryId) return false;
      if (filterSearch) {
        const q = filterSearch.toLowerCase();
        if (!String(e.description || "").toLowerCase().includes(q)) return false;
      }
      return true;
    });
  }

  function summaryHtml(rows) {
    const count = rows.length;
    const totalsByBase = {};
    rows.forEach((e) => {
      const b = tripById(e.trip_id)?.base_currency || "USD";
      totalsByBase[b] = (totalsByBase[b] || 0) + netConverted(e);
    });
    const baseKeys = Object.keys(totalsByBase);
    let totalInner = "";
    if (!baseKeys.length) totalInner = '<p class="muted" style="margin:0.5rem 0 0;">—</p>';
    else if (baseKeys.length === 1) {
      const b = baseKeys[0];
      totalInner =
        '<p style="margin:0.5rem 0 0;font-size:1.25rem;font-weight:700;">' +
        escapeHtml(formatMoney(totalsByBase[b], b)) +
        "</p>";
    } else {
      totalInner =
        '<ul class="list-plain" style="margin:0.5rem 0 0;">' +
        baseKeys
          .map((b) => "<li><strong>" + escapeHtml(formatMoney(totalsByBase[b], b)) + "</strong></li>")
          .join("") +
        "</ul>";
    }
    return (
      '<div class="grid grid--3" style="margin-bottom:1rem;">' +
      '<div class="card card--gradient"><h3 class="card__title">Expenses</h3>' +
      '<p style="margin:0.5rem 0 0;font-size:1.5rem;font-weight:700;">' +
      escapeHtml(String(count)) +
      '</p><p class="muted" style="margin:0.25rem 0 0;font-size:0.85rem;">Matching filters</p></div>' +
      '<div class="card"><h3 class="card__title">Total converted (net)</h3>' +
      totalInner +
      '<p class="muted" style="margin:0.25rem 0 0;font-size:0.85rem;">After refunds · per trip base.</p></div>' +
      '<div class="card"><h3 class="card__title">Quick tip</h3>' +
      '<p class="muted" style="margin:0.5rem 0 0;font-size:0.85rem;">Use tabs for Settle debts &amp; Settlement.</p></div></div>'
    );
  }

  function tripOptions(sel) {
    return (
      '<option value="">— Select trip —</option>' +
      MOCK_TRIPS.map(
        (t) =>
          '<option value="' +
          escapeHtml(t.trip_id) +
          '"' +
          (t.trip_id === sel ? " selected" : "") +
          ">" +
          escapeHtml(t.name) +
          "</option>"
      ).join("")
    );
  }

  function categoryOptions(sel) {
    return MOCK_CATEGORIES.map(
      (c) =>
        '<option value="' +
        escapeHtml(c.category_id) +
        '"' +
        (c.category_id === sel ? " selected" : "") +
        ">" +
        escapeHtml(c.icon + " " + c.name) +
        "</option>"
    ).join("");
  }

  function currencyOptions(sel) {
    return MOCK_CURRENCIES.map(
      (c) =>
        '<option value="' +
        escapeHtml(c.code) +
        '"' +
        (c.code === sel ? " selected" : "") +
        ">" +
        escapeHtml(c.name + " (" + c.code + ")") +
        "</option>"
    ).join("");
  }

  function syncFormConverted(container) {
    const tripId = container.querySelector("#exp-f-trip")?.value || "";
    const orig = parseFloat(container.querySelector("#exp-f-orig")?.value);
    const cur = container.querySelector("#exp-f-curr")?.value || "USD";
    const out = container.querySelector("#exp-f-converted");
    const trip = tripById(tripId);
    if (!out || !trip) {
      if (out) out.value = "";
      return;
    }
    if (!tripId || !(orig >= 0)) {
      out.value = "";
      return;
    }
    const conv = convertToBase(orig, cur, tripId);
    if (!conv.ok) {
      out.value = "N/A (no direct rate to " + trip.base_currency + ")";
      return;
    }
    const meta = currencyByCode(trip.base_currency);
    out.value =
      formatMoney(orig, cur) +
      " → " +
      formatMoney(conv.converted, conv.base) +
      " (× " +
      conv.rate +
      ")";
  }

  function syncSplitPreview(container) {
    const tripId = container.querySelector("#exp-f-trip")?.value;
    const trip = tripById(tripId);
    const wrap = container.querySelector("#exp-split-fields");
    const warn = container.querySelector("#exp-split-warn");
    if (!wrap || !trip) return;
    const payKitty = container.querySelector("#exp-f-kitty")?.checked;
    if (payKitty) {
      wrap.innerHTML = '<p class="muted">Kitty payment — no member split.</p>';
      if (warn) warn.textContent = "";
      return;
    }
    const mode = container.querySelector('input[name="exp-split-mode"]:checked')?.value || "equal";
    const orig = parseFloat(container.querySelector("#exp-f-orig")?.value);
    const cur = container.querySelector("#exp-f-curr")?.value;
    const conv = convertToBase(orig, cur, tripId);
    const net = conv.ok ? conv.converted : 0;
    if (mode === "equal") {
      wrap.innerHTML = buildSplitsEqual(trip, net)
        .map(
          (s) =>
            '<div style="font-size:0.85rem;margin-bottom:0.25rem;">' +
            escapeHtml(s.name) +
            ": " +
            escapeHtml(formatMoney(s.amount, trip.base_currency)) +
            " (" +
            s.percentage +
            "%)</div>"
        )
        .join("");
      if (warn) warn.textContent = "";
      return;
    }
    let sumPct = 0;
    const pctMap = {};
    trip.members.forEach((m) => {
      const inp = wrap.querySelector('.exp-pct-inp[data-uid="' + m.userId + '"]');
      const p = inp ? parseFloat(inp.value) || 0 : 0;
      pctMap[m.userId] = p;
      sumPct += p;
    });
    if (warn)
      warn.textContent =
        Math.abs(sumPct - 100) > 0.01
          ? "Percentages must sum to 100% (currently " + sumPct.toFixed(1) + "%)"
          : "";
    const rows = buildSplitsCustom(trip, net, pctMap);
    let html = trip.members
      .map((m, idx) => {
        const s = rows[idx];
        return (
          '<div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.35rem;flex-wrap:wrap;"><span style="min-width:70px;">' +
          escapeHtml(m.name) +
          '</span><input type="number" class="input exp-pct-inp" style="width:70px;" data-uid="' +
          escapeHtml(m.userId) +
          '" min="0" max="100" step="0.1" value="' +
          (pctMap[m.userId] || 0) +
          '" /> % → <strong>' +
          escapeHtml(formatMoney(s.amount, trip.base_currency)) +
          "</strong></div>"
        );
      })
      .join("");
    wrap.innerHTML = html;
    wrap.querySelectorAll(".exp-pct-inp").forEach((inp) => {
      inp.addEventListener("input", () => syncSplitPreview(container));
    });
  }

  function renderCustomSplitInputs(container, trip, pctMap) {
    const wrap = container.querySelector("#exp-split-fields");
    if (!wrap || !trip) return;
    wrap.innerHTML = trip.members
      .map((m) => {
        const p = pctMap[m.userId] != null ? pctMap[m.userId] : 100 / trip.members.length;
        return (
          '<div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.35rem;"><span style="min-width:70px;">' +
          escapeHtml(m.name) +
          '</span><input type="number" class="input exp-pct-inp" style="width:70px;" data-uid="' +
          escapeHtml(m.userId) +
          '" min="0" max="100" step="0.1" value="' +
          p +
          '" /> %</div>'
        );
      })
      .join("");
    wrap.querySelectorAll(".exp-pct-inp").forEach((inp) => {
      inp.addEventListener("input", () => syncSplitPreview(container));
    });
    syncSplitPreview(container);
  }

  function clearForm(container) {
    editingExpenseId = null;
    const form = container.querySelector("#exp-expense-form");
    if (!form) return;
    form.querySelector("#exp-f-trip").value = "";
    form.querySelector("#exp-f-desc").value = "";
    form.querySelector("#exp-f-orig").value = "";
    form.querySelector("#exp-f-curr").value = "USD";
    form.querySelector("#exp-f-cat").value = MOCK_CATEGORIES[0].category_id;
    form.querySelector("#exp-f-paid").value = "";
    form.querySelector("#exp-f-converted").value = "";
    form.querySelector("#exp-f-kitty").checked = false;
    form.querySelector("#exp-f-recurring").checked = false;
    form.querySelector("#exp-recurring-fields").hidden = true;
    form.querySelector("#exp-ocr-preview").innerHTML = "";
    form.querySelector("#exp-ocr-meta").textContent = "";
    const eq = form.querySelector('#exp-split-equal');
    if (eq) eq.checked = true;
    form.querySelector("#exp-split-fields").innerHTML = "";
    form.querySelector("#exp-split-warn").textContent = "";
    form.querySelector("#exp-f-submit").textContent = "Add expense";
    form.querySelector("#exp-f-cancel-edit").hidden = true;
  }

  function fillForm(container, e) {
    editingExpenseId = e.expense_id;
    const form = container.querySelector("#exp-expense-form");
    if (!form) return;
    const trip = tripById(e.trip_id);
    form.querySelector("#exp-f-trip").value = e.trip_id || "";
    form.querySelector("#exp-f-desc").value = e.description || "";
    form.querySelector("#exp-f-orig").value = e.original_amount != null ? String(e.original_amount) : "";
    form.querySelector("#exp-f-curr").value = e.original_currency || "USD";
    form.querySelector("#exp-f-cat").value = e.category_id || MOCK_CATEGORIES[0].category_id;
    form.querySelector("#exp-f-paid").value = e.paid_by || "";
    form.querySelector("#exp-f-kitty").checked = !!e.pay_from_kitty;
    syncFormConverted(container);
    const eq = form.querySelector('#exp-split-equal');
    const cu = form.querySelector('#exp-split-custom');
    if (e.split_mode === "custom") {
      cu.checked = true;
      const pct = {};
      (e.splits || []).forEach((s) => {
        pct[s.userId] = s.percentage;
      });
      if (trip) renderCustomSplitInputs(container, trip, pct);
    } else {
      eq.checked = true;
      if (trip) syncSplitPreview(container);
    }
    form.querySelector("#exp-f-recurring").checked = !!e.recurring_id;
    form.querySelector("#exp-recurring-fields").hidden = !e.recurring_id;
    if (e.recurring_meta) {
      form.querySelector("#exp-f-rec-start").value = e.recurring_meta.startDate || "";
      form.querySelector("#exp-f-rec-end").value = e.recurring_meta.endDate || "";
      form.querySelector("#exp-f-rec-freq").value = e.recurring_meta.frequency || "Monthly";
    }
    form.querySelector("#exp-f-submit").textContent = "Save changes";
    form.querySelector("#exp-f-cancel-edit").hidden = false;
    form.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  function kittySectionHtml() {
    return (
      '<div class="card" style="margin-bottom:1rem;"><h3 class="card__title">Kitty (shared pool)</h3>' +
      '<div class="form-grid"><div class="form-row"><label>Trip</label>' +
      '<select id="exp-kitty-trip" class="input">' +
      MOCK_TRIPS.map(
        (t) => '<option value="' + escapeHtml(t.trip_id) + '">' + escapeHtml(t.name) + "</option>"
      ).join("") +
      '</select></div><div class="form-row"><label>Contributor name</label>' +
      '<input id="exp-kitty-name" class="input" placeholder="Name" /></div>' +
      '<div class="form-row"><label>Amount (' +
      escapeHtml("trip base") +
      ')</label>' +
      '<input id="exp-kitty-amt" type="number" min="0" step="0.01" class="input" /></div>' +
      '<div class="form-row"><button type="button" class="btn btn--primary" id="exp-kitty-add">Log contribution</button></div></div>' +
      '<div id="exp-kitty-balances" style="margin-top:1rem;"></div></div>'
    );
  }

  function renderKittyBalances(container) {
    const el = container.querySelector("#exp-kitty-balances");
    if (!el) return;
    el.innerHTML = MOCK_TRIPS.map((t) => {
      const k = ensureKitty(t.trip_id);
      const bal = kittyBalance(t.trip_id);
      const lines = k.contributions
        .map(
          (c) =>
            "<li>" +
            escapeHtml(c.name || c.userId) +
            ": " +
            escapeHtml(formatMoney(c.amount, t.base_currency)) +
            "</li>"
        )
        .join("");
      return (
        '<div style="margin-bottom:0.75rem;padding:0.75rem;background:rgba(99,102,241,0.06);border-radius:var(--radius-sm);">' +
        "<strong>" +
        escapeHtml(t.name) +
        "</strong> · Balance: " +
        escapeHtml(formatMoney(bal, t.base_currency)) +
        '<ul class="list-plain" style="margin:0.35rem 0 0;font-size:0.85rem;">' +
        (lines || "<li class=\"muted\">No contributions yet.</li>") +
        "</ul></div>"
      );
    }).join("");
  }

  function nonCashSectionHtml() {
    return (
      '<div class="card" style="margin-bottom:1rem;"><h3 class="card__title">Non-cash contributions</h3>' +
      '<form id="exp-noncash-form" class="form-grid"><div class="form-row"><label>Trip</label>' +
      '<select name="trip_id" class="input" required>' +
      MOCK_TRIPS.map((t) => '<option value="' + escapeHtml(t.trip_id) + '">' + escapeHtml(t.name) + "</option>").join("") +
      '</select></div><div class="form-row"><label>Description</label>' +
      '<input name="description" class="input" required /></div>' +
      '<div class="form-row"><label>Contributor</label>' +
      '<input name="contributer" class="input" required /></div>' +
      '<div class="form-row"><label>Estimated value (trip base)</label>' +
      '<input name="estimated_value_base" type="number" min="0" step="0.01" class="input" required /></div>' +
      '<div class="form-row"><label>Proof file</label>' +
      '<input name="proof" type="file" accept=".pdf,image/*" class="input" /></div>' +
      '<div class="form-row"><button type="submit" class="btn btn--primary">Add non-cash</button></div></form>' +
      '<div style="overflow-x:auto;margin-top:1rem;"><table class="table-exp" style="width:100%;font-size:0.85rem;">' +
      '<tbody id="exp-noncash-body"></tbody></table></div></div>'
    );
  }

  function renderNonCashRows(container, appState) {
    const tb = container.querySelector("#exp-noncash-body");
    if (!tb) return;
    tb.innerHTML = nonCashItems
      .map((nc) => {
        const tr = tripById(nc.trip_id);
        const base = tr ? tr.base_currency : "USD";
        return (
          "<tr><td>" +
          escapeHtml(nc.description) +
          "</td><td>" +
          escapeHtml(nc.contributer) +
          "</td><td>" +
          escapeHtml(formatMoney(nc.estimated_value_base, base)) +
          "</td><td>" +
          escapeHtml(nc.proof_file || "—") +
          "</td><td>" +
          escapeHtml(nc.status) +
          "</td><td>" +
          escapeHtml(nc.leader_comment || "—") +
          '</td><td><button type="button" class="btn btn--sm btn--secondary" data-nc-leader="' +
          escapeHtml(nc.non_cash_id) +
          '">Leader review</button></td></tr>'
        );
      })
      .join("");
    tb.querySelectorAll("[data-nc-leader]").forEach((btn) => {
      btn.addEventListener("click", () =>
        openNonCashLeaderModal(container, btn.getAttribute("data-nc-leader"), appState)
      );
    });
  }

  function openNonCashLeaderModal(container, id, appState) {
    const nc = nonCashItems.find((x) => x.non_cash_id === id);
    if (!nc) return;
    const body = document.createElement("div");
    body.className = "form-grid";
    body.innerHTML =
      '<div class="form-row"><label>Comment</label><textarea id="nc-comment" class="textarea">' +
      escapeHtml(nc.leader_comment || "") +
      '</textarea></div><div class="form-row"><label>Status</label>' +
      '<select id="nc-status" class="input">' +
      ["Pending", "Approved", "Rejected"]
        .map(
          (s) =>
            "<option" +
            (nc.status === s ? " selected" : "") +
            ">" +
            s +
            "</option>"
        )
        .join("") +
      "</select></div>";
    const footer = document.createElement("div");
    footer.style.cssText = "display:flex;gap:0.5rem;justify-content:flex-end;";
    const save = document.createElement("button");
    save.type = "button";
    save.className = "btn btn--primary";
    save.textContent = "Save";
    footer.appendChild(save);
    const dlg = openModal({ title: "Non-cash review", body, footer });
    save.addEventListener("click", () => {
      nc.leader_comment = body.querySelector("#nc-comment").value.trim();
      nc.status = body.querySelector("#nc-status").value;
      dlg.close();
      toast("Non-cash updated");
      render(container, appState || {});
    });
  }

  function refundsSummaryHtml() {
    if (!pageRefunds.length)
      return '<div class="card" style="margin-bottom:1rem;"><h3 class="card__title">Refunds</h3><p class="muted">No refunds logged.</p></div>';
    const byTrip = {};
    pageRefunds.forEach((r) => {
      const tr = tripById(r.trip_id);
      const b = tr ? tr.base_currency : "USD";
      if (!byTrip[b]) byTrip[b] = [];
      byTrip[b].push(r);
    });
    let h =
      '<div class="card" style="margin-bottom:1rem;"><h3 class="card__title">Refunds summary</h3><ul class="list-plain">';
    pageRefunds.forEach((r) => {
      const tr = tripById(r.trip_id);
      const b = tr ? tr.base_currency : "USD";
      h +=
        "<li>" +
        escapeHtml(r.refundId) +
        " · expense " +
        escapeHtml(r.expense_id) +
        ": " +
        escapeHtml(formatMoney(r.totalAmount, b)) +
        "</li>";
    });
    h += "</ul></div>";
    return h;
  }

  function recurringSectionHtml() {
    const activeRec = recurringSchedules.filter((s) => !s.cancelled);
    const rows = activeRec
      .map((s) => {
        const tr = tripById(s.trip_id);
        const next = nextOccurrenceDate(s);
        const rem = remainingOccurrences(s);
        return (
          "<li style=\"margin-bottom:0.5rem;\"><strong>" +
          escapeHtml(tr ? tr.name : s.trip_id) +
          "</strong> · " +
          escapeHtml(s.frequency) +
          " · Next: " +
          escapeHtml(next || "—") +
          " · Remaining est.: " +
          rem +
          ' <button type="button" class="btn btn--sm btn--danger" data-rec-cancel="' +
          escapeHtml(s.recurringId) +
          '">Cancel recurring</button></li>'
        );
      })
      .join("");
    return (
      '<div class="card" style="margin-bottom:1rem;"><h3 class="card__title">Recurring expenses</h3>' +
      (activeRec.length
        ? '<ul class="list-plain">' + rows + "</ul>"
        : '<p class="muted">No active recurring schedules.</p>') +
      "</div>"
    );
  }

  function settleDebtsPanelHtml() {
    const trips = MOCK_TRIPS.map((t) => {
      const { txs, base } = minimizeDebts(t.trip_id);
      const lines = txs
        .map(
          (x) =>
            "<li>" +
            escapeHtml(x.fromName + " pays " + x.toName + ": " + formatMoney(x.amount, x.base)) +
            "</li>"
        )
        .join("");
      return (
        '<div class="card" style="margin-bottom:0.75rem;"><h4 class="card__title" style="font-size:1rem;">' +
        escapeHtml(t.name) +
        "</h4>" +
        "<p class=\"muted\" style=\"font-size:0.85rem;\">Minimum transactions: " +
        txs.length +
        "</p>" +
        (lines ? "<ul class=\"list-plain\">" + lines + "</ul>" : '<p class="muted">All square.</p>') +
        "</div>"
      );
    }).join("");
    return '<div class="tab-panel" data-tab-panel="debts" hidden>' + trips + "</div>";
  }

  function settlementPanelHtml() {
    const blocks = MOCK_TRIPS.map((t) => {
      ensureSettlement(t.trip_id);
      const s = settlementState[t.trip_id];
      const signed = allSigned(t.trip_id);
      if (signed && s.status !== "settled") {
        s.status = "settled";
      }
      const btns = t.members
        .map((m) => {
          const ok = !!s.approvals[m.userId];
          return (
            '<div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.35rem;">' +
            escapeHtml(m.name) +
            (ok
              ? ' <span class="badge badge--confirmed">✅ Signed</span>'
              : ' <button type="button" class="btn btn--sm btn--secondary" data-sign-off="' +
                escapeHtml(t.trip_id) +
                '" data-uid="' +
                escapeHtml(m.userId) +
                '">Sign off</button>') +
            "</div>"
          );
        })
        .join("");
      const banner =
        s.status === "settled"
          ? '<div class="badge badge--confirmed" style="display:block;margin:0.5rem 0;padding:0.5rem;">✅ Trip is Settled!</div>'
          : "";
      return (
        '<div class="card" style="margin-bottom:0.75rem;"><h4 class="card__title" style="font-size:1rem;">' +
        escapeHtml(t.name) +
        "</h4>" +
        banner +
        btns +
        '<button type="button" class="btn btn--sm btn--secondary" style="margin-top:0.5rem;" data-reset-stl="' +
        escapeHtml(t.trip_id) +
        '">Reset settlement</button></div>'
      );
    }).join("");
    return '<div class="tab-panel" data-tab-panel="settle" hidden>' + blocks + "</div>";
  }

  function openRefundModal(container, expenseId, appState) {
    const ex = pageExpenses.find((e) => e.expense_id === expenseId);
    if (!ex) return;
    const trip = tripById(ex.trip_id);
    const base = trip ? trip.base_currency : "USD";
    const body = document.createElement("div");
    body.className = "form-grid";
    body.innerHTML =
      '<div class="form-row"><label>Refund amount (' +
      escapeHtml(base) +
      ')</label>' +
      '<input id="rf-amt" type="number" min="0" step="0.01" class="input" max="' +
      netConverted(ex) +
      '" /></div>' +
      '<p class="muted" style="font-size:0.8rem;">Partial refunds allowed. Net expense and splits update.</p>';
    const footer = document.createElement("div");
    footer.style.cssText = "display:flex;gap:0.5rem;justify-content:flex-end;";
    const go = document.createElement("button");
    go.type = "button";
    go.className = "btn btn--primary";
    go.textContent = "Log refund";
    footer.appendChild(go);
    const dlg = openModal({ title: "Log refund", body, footer });
    go.addEventListener("click", () => {
      const amt = parseFloat(body.querySelector("#rf-amt").value);
      if (!(amt > 0)) {
        toast("Enter refund amount");
        return;
      }
      const currentNet = netConverted(ex);
      if (amt > currentNet + 0.01) {
        toast("Refund cannot exceed net expense");
        return;
      }
      const rid = generateId("rf");
      ex.refunds = ex.refunds || [];
      ex.refunds.push({ refundId: rid, totalAmount: amt });
      pageRefunds.push({
        refundId: rid,
        expense_id: ex.expense_id,
        totalAmount: amt,
        trip_id: ex.trip_id,
      });
      scaleSplitsToNet(ex);
      dlg.close();
      toast("Refund logged");
      render(container, appState || {});
    });
  }

  /* ========== FEATURE 17: OCR simulation ========== */
  function wireOcr(container, form) {
    const inp = form.querySelector("#exp-f-receipt");
    if (!inp) return;
    inp.addEventListener("change", () => {
      const file = inp.files && inp.files[0];
      const prev = form.querySelector("#exp-ocr-preview");
      const meta = form.querySelector("#exp-ocr-meta");
      if (!file) return;
      const url = URL.createObjectURL(file);
      prev.innerHTML =
        '<div style="position:relative;"><img src="' +
        url +
        '" alt="" style="max-height:120px;border-radius:8px;" /><div id="exp-ocr-loading" hidden style="position:absolute;inset:0;background:rgba(255,255,255,0.85);display:grid;place-items:center;font-weight:600;">Extracting…</div></div>';
      const loadEl = prev.querySelector("#exp-ocr-loading");
      loadEl.hidden = false;
      setTimeout(() => {
        loadEl.hidden = true;
        const mockAmt = Math.round(50 + Math.random() * 450);
        form.querySelector("#exp-f-orig").value = String(mockAmt);
        form.querySelector("#exp-f-desc").value = "Extracted from receipt";
        const today = new Date().toISOString().slice(0, 10);
        meta.textContent = "OCR · " + today + " · " + file.name;
        form.dataset.ocrPending = JSON.stringify({
          uploadId: generateId("up"),
          filePath: file.name,
          uploadedBy: "local_user",
          extractedData: { amount: mockAmt, description: "Extracted from receipt", date: today },
          processor_id: "proc_mock_1",
          trip_id: form.querySelector("#exp-f-trip").value || "",
        });
        syncFormConverted(container);
        syncSplitPreview(container);
        toast("Receipt processed (simulated)");
      }, 1500);
    });
  }

  function wireForm(container, appState) {
    const form = container.querySelector("#exp-expense-form");
    if (!form) return;
    ["#exp-f-trip", "#exp-f-orig", "#exp-f-curr"].forEach((sel) => {
      form.querySelector(sel)?.addEventListener("input", () => {
        syncFormConverted(container);
        syncSplitPreview(container);
      });
      form.querySelector(sel)?.addEventListener("change", () => {
        syncFormConverted(container);
        syncSplitPreview(container);
      });
    });
    form.querySelector("#exp-f-kitty")?.addEventListener("change", () => syncSplitPreview(container));
    form.querySelectorAll('input[name="exp-split-mode"]').forEach((r) => {
      r.addEventListener("change", () => {
        const trip = tripById(form.querySelector("#exp-f-trip").value);
        if (r.value === "custom" && trip) {
          const eq = 100 / trip.members.length;
          const pct = {};
          trip.members.forEach((m) => {
            pct[m.userId] = eq;
          });
          renderCustomSplitInputs(container, trip, pct);
        } else syncSplitPreview(container);
      });
    });
    form.querySelector("#exp-f-trip")?.addEventListener("change", () => {
      const trip = tripById(form.querySelector("#exp-f-trip").value);
      if (trip && form.querySelector('#exp-split-custom').checked) {
        const eq = 100 / trip.members.length;
        const pct = {};
        trip.members.forEach((m) => {
          pct[m.userId] = eq;
        });
        renderCustomSplitInputs(container, trip, pct);
      } else syncSplitPreview(container);
    });
    form.querySelector("#exp-f-recurring")?.addEventListener("change", () => {
      form.querySelector("#exp-recurring-fields").hidden = !form.querySelector("#exp-f-recurring").checked;
    });
    form.querySelector("#exp-f-cancel-edit")?.addEventListener("click", () => {
      clearForm(container);
      syncFormConverted(container);
    });
    wireOcr(container, form);

    form.addEventListener("submit", (ev) => {
      ev.preventDefault();
      const trip_id = form.querySelector("#exp-f-trip").value;
      const trip = tripById(trip_id);
      if (!trip) {
        toast("Select a trip");
        return;
      }
      if (isTripLocked(trip_id)) {
        toast("This trip is settled — use Reset settlement to edit.");
        return;
      }
      const description = form.querySelector("#exp-f-desc").value.trim();
      const original_amount = parseFloat(form.querySelector("#exp-f-orig").value);
      const original_currency = form.querySelector("#exp-f-curr").value;
      const category_id = form.querySelector("#exp-f-cat").value;
      const paid_by = form.querySelector("#exp-f-paid").value.trim();
      const pay_from_kitty = form.querySelector("#exp-f-kitty").checked;
      const conv = convertToBase(original_amount, original_currency, trip_id);
      if (!description || !(original_amount >= 0) || !paid_by) {
        toast("Fill description, amount, and paid by");
        return;
      }
      if (!conv.ok) {
        toast("No exchange rate for this pair — N/A");
        return;
      }
      if (pay_from_kitty) {
        const bal = kittyBalance(trip_id);
        if (conv.converted > bal + 0.01) {
          toast("Insufficient kitty balance (" + formatMoney(bal, trip.base_currency) + ")");
          return;
        }
      }
      const mode = form.querySelector('input[name="exp-split-mode"]:checked')?.value || "equal";
      let split_mode = mode;
      let splits = [];
      if (!pay_from_kitty) {
        if (mode === "custom") {
          let sumPct = 0;
          const pctMap = {};
          trip.members.forEach((m) => {
            const inp = form.querySelector('.exp-pct-inp[data-uid="' + m.userId + '"]');
            const p = inp ? parseFloat(inp.value) || 0 : 0;
            pctMap[m.userId] = p;
            sumPct += p;
          });
          if (Math.abs(sumPct - 100) > 0.01) {
            toast("Custom split percentages must sum to 100%");
            return;
          }
          splits = buildSplitsCustom(trip, conv.converted, pctMap);
        } else {
          splits = buildSplitsEqual(trip, conv.converted);
        }
      }

      let recurring_id = null;
      let recurring_meta = null;
      if (form.querySelector("#exp-f-recurring").checked) {
        recurring_id = editingExpenseId
          ? pageExpenses.find((x) => x.expense_id === editingExpenseId)?.recurring_id || generateId("rec")
          : generateId("rec");
        recurring_meta = {
          startDate: form.querySelector("#exp-f-rec-start").value,
          endDate: form.querySelector("#exp-f-rec-end").value,
          frequency: form.querySelector("#exp-f-rec-freq").value,
        };
        if (!recurring_meta.startDate || !recurring_meta.endDate) {
          toast("Recurring requires start and end dates");
          return;
        }
        if (
          !editingExpenseId ||
          !recurringSchedules.find((r) => r.recurringId === recurring_id)
        ) {
          recurringSchedules.push({
            recurringId: recurring_id,
            trip_id,
            startDate: recurring_meta.startDate,
            endDate: recurring_meta.endDate,
            frequency: recurring_meta.frequency,
            cancelled: false,
            sourceExpenseId: null,
          });
        }
      }

      let receipt_meta = null;
      if (form.dataset.ocrPending) {
        try {
          receipt_meta = JSON.parse(form.dataset.ocrPending);
          receipt_meta.trip_id = trip_id;
        } catch (_) {}
        delete form.dataset.ocrPending;
      }

      const payload = {
        trip_id,
        category_id,
        original_currency,
        description,
        original_amount,
        converted_amount: conv.converted,
        paid_by,
        pay_from_kitty,
        split_mode,
        splits,
        refunds: editingExpenseId ? pageExpenses.find((x) => x.expense_id === editingExpenseId)?.refunds || [] : [],
        recurring_id: form.querySelector("#exp-f-recurring").checked ? recurring_id : null,
        recurring_meta: form.querySelector("#exp-f-recurring").checked ? recurring_meta : null,
        receipt_meta: receipt_meta || (editingExpenseId ? pageExpenses.find((x) => x.expense_id === editingExpenseId)?.receipt_meta : null),
      };

      if (editingExpenseId) {
        const ex = pageExpenses.find((x) => x.expense_id === editingExpenseId);
        if (ex) {
          Object.assign(ex, payload);
          if (!payload.recurring_id) {
            ex.recurring_id = null;
            ex.recurring_meta = null;
          }
          if (ex.refunds && ex.refunds.length) scaleSplitsToNet(ex);
        }
        toast("Updated");
        clearForm(container);
      } else {
        pageExpenses.push({
          expense_id: generateId("exp"),
          ...payload,
        });
        toast("Added");
        clearForm(container);
      }
      form.querySelector("#exp-ocr-preview").innerHTML = "";
      form.querySelector("#exp-ocr-meta").textContent = "";
      render(container, appState);
    });
  }

  function wireFilters(container, appState) {
    const t = container.querySelector("#exp-filter-trip");
    const c = container.querySelector("#exp-filter-cat");
    const s = container.querySelector("#exp-filter-search");
    const onChange = () => {
      filterTripId = t ? t.value : "";
      filterCategoryId = c ? c.value : "";
      filterSearch = s ? s.value.trim() : "";
      render(container, appState);
    };
    t?.addEventListener("change", onChange);
    c?.addEventListener("change", onChange);
    s?.addEventListener("input", onChange);
  }

  function render(container, appState) {
    const appStateSafe = appState || {};
    const filtered = getFilteredExpenses();

    container.innerHTML =
      allBudgetBarsHtml(appStateSafe) +
      analyticsSectionHtml() +
      summaryHtml(filtered) +
      kittySectionHtml() +
      '<div class="tabs" id="exp-main-tabs">' +
      '<button type="button" class="tab is-active" data-tab="main">Expenses</button>' +
      '<button type="button" class="tab" data-tab="debts">Settle debts</button>' +
      '<button type="button" class="tab" data-tab="settle">Settlement</button>' +
      "</div>" +
      '<div class="tab-panel" data-tab-panel="main">' +
      '<div class="card" style="margin-bottom:1rem;"><h3 class="card__title">Filter &amp; search</h3>' +
      '<div class="form-grid" style="margin-top:0.75rem;">' +
      '<div class="form-row"><label>Trip</label><select id="exp-filter-trip" class="input">' +
      '<option value="">All trips</option>' +
      MOCK_TRIPS.map(
        (t) =>
          '<option value="' +
          escapeHtml(t.trip_id) +
          '"' +
          (t.trip_id === filterTripId ? " selected" : "") +
          ">" +
          escapeHtml(t.name) +
          "</option>"
      ).join("") +
      '</select></div><div class="form-row"><label>Category</label>' +
      '<select id="exp-filter-cat" class="input">' +
      '<option value="">All categories</option>' +
      MOCK_CATEGORIES.map(
        (c) =>
          '<option value="' +
          escapeHtml(c.category_id) +
          '"' +
          (c.category_id === filterCategoryId ? " selected" : "") +
          ">" +
          escapeHtml(c.icon + " " + c.name) +
          "</option>"
      ).join("") +
      '</select></div><div class="form-row"><label>Search description</label>' +
      '<input id="exp-filter-search" type="search" class="input" value="' +
      escapeHtml(filterSearch) +
      '" /></div></div></div>' +
      '<div class="card" style="margin-bottom:1rem;"><h3 class="card__title">' +
      (editingExpenseId ? "Edit expense" : "Add expense") +
      '</h3><form id="exp-expense-form" class="form-grid">' +
      '<div class="form-row"><label>Trip</label><select id="exp-f-trip" class="input" required>' +
      tripOptions("") +
      '</select></div><div class="form-row"><label>Description</label>' +
      '<input id="exp-f-desc" class="input" required /></div>' +
      '<div class="form-row"><label>Original amount</label>' +
      '<input id="exp-f-orig" type="number" min="0" step="0.01" class="input" required /></div>' +
      '<div class="form-row"><label>Original currency</label>' +
      '<select id="exp-f-curr" class="input">' +
      currencyOptions("USD") +
      '</select></div><div class="form-row"><label>Converted (live)</label>' +
      '<input id="exp-f-converted" class="input" readonly style="background:rgba(99,102,241,0.06);" />' +
      '<p class="muted" style="font-size:0.75rem;margin:0.25rem 0 0;">Direct rate only; else N/A.</p></div>' +
      '<div class="form-row"><label>Category</label><select id="exp-f-cat" class="input">' +
      categoryOptions(MOCK_CATEGORIES[0].category_id) +
      '</select></div><div class="form-row"><label>Paid by</label>' +
      '<input id="exp-f-paid" class="input" required /></div>' +
      '<div class="form-row"><label class="checkbox-label"><input type="checkbox" id="exp-f-kitty" /> Pay from kitty</label></div>' +
      '<div class="form-row"><label>Upload receipt (OCR sim)</label>' +
      '<input type="file" id="exp-f-receipt" accept="image/jpeg,image/png,image/webp" class="input" />' +
      '<div id="exp-ocr-preview" style="margin-top:0.5rem;"></div>' +
      '<p id="exp-ocr-meta" class="muted" style="font-size:0.75rem;"></p></div>' +
      '<div class="form-row"><label class="checkbox-label"><input type="checkbox" id="exp-f-recurring" /> Make recurring</label>' +
      '<div id="exp-recurring-fields" hidden style="margin-top:0.5rem;display:grid;gap:0.5rem;">' +
      '<input type="date" id="exp-f-rec-start" class="input" />' +
      '<input type="date" id="exp-f-rec-end" class="input" />' +
      '<select id="exp-f-rec-freq" class="input"><option>Daily</option><option>Weekly</option><option>Monthly</option></select>' +
      "</div></div>" +
      '<div class="form-row"><label>Split</label>' +
      '<div style="display:flex;gap:1rem;margin-bottom:0.5rem;">' +
      '<label><input type="radio" name="exp-split-mode" id="exp-split-equal" value="equal" checked /> Equal</label>' +
      '<label><input type="radio" name="exp-split-mode" id="exp-split-custom" value="custom" /> Custom %</label></div>' +
      '<p id="exp-split-warn" style="color:var(--danger);font-size:0.8rem;margin:0 0 0.35rem;"></p>' +
      '<div id="exp-split-fields"></div></div>' +
      '<div class="form-row" style="display:flex;gap:0.5rem;flex-wrap:wrap;">' +
      '<button type="submit" class="btn btn--primary" id="exp-f-submit">Add expense</button>' +
      '<button type="button" class="btn btn--secondary" id="exp-f-cancel-edit" hidden>Cancel edit</button></div>' +
      "</form></div>" +
      recurringSectionHtml() +
      refundsSummaryHtml() +
      nonCashSectionHtml() +
      "</div>" +
      settleDebtsPanelHtml() +
      settlementPanelHtml();

    initTabs(container.querySelector("#exp-main-tabs"));

    if (editingExpenseId) {
      const ex = pageExpenses.find((x) => x.expense_id === editingExpenseId);
      if (ex) fillForm(container, ex);
      else editingExpenseId = null;
    }

    wireFilters(container, appStateSafe);
    wireForm(container, appStateSafe);
    syncFormConverted(container);
    syncSplitPreview(container);
    renderKittyBalances(container);
    renderNonCashRows(container, appStateSafe);

    container.querySelector("#exp-kitty-add")?.addEventListener("click", () => {
      const tid = container.querySelector("#exp-kitty-trip").value;
      const name = container.querySelector("#exp-kitty-name").value.trim();
      const amt = parseFloat(container.querySelector("#exp-kitty-amt").value);
      if (!tid || !name || !(amt > 0)) {
        toast("Trip, name, and amount required");
        return;
      }
      const k = ensureKitty(tid);
      k.contributions.push({
        contributionId: generateId("kc"),
        amount: amt,
        kittyId: k.kitty_id,
        userId: "u_" + name.replace(/\s+/g, "_").toLowerCase(),
        name,
      });
      container.querySelector("#exp-kitty-name").value = "";
      container.querySelector("#exp-kitty-amt").value = "";
      toast("Contribution logged");
      render(container, appStateSafe);
    });

    container.querySelectorAll("[data-dismiss-budget]").forEach((btn) => {
      btn.addEventListener("click", () => {
        budgetAlertDismissed[btn.getAttribute("data-dismiss-budget")] = true;
        render(container, appStateSafe);
      });
    });

    container.querySelectorAll("[data-rec-cancel]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const id = btn.getAttribute("data-rec-cancel");
        const s = recurringSchedules.find((x) => x.recurringId === id);
        if (s) s.cancelled = true;
        toast("Recurring cancelled");
        render(container, appStateSafe);
      });
    });

    container.querySelectorAll("[data-sign-off]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const tid = btn.getAttribute("data-sign-off");
        const uid = btn.getAttribute("data-uid");
        const st = ensureSettlement(tid);
        st.approvals[uid] = true;
        if (allSigned(tid)) st.status = "settled";
        toast("Signed off");
        render(container, appStateSafe);
      });
    });

    container.querySelectorAll("[data-reset-stl]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const tid = btn.getAttribute("data-reset-stl");
        delete settlementState[tid];
        toast("Settlement reset");
        render(container, appStateSafe);
      });
    });

    container.querySelector("#exp-noncash-form")?.addEventListener("submit", (ev) => {
      ev.preventDefault();
      const fd = new FormData(ev.target);
      const proof = fd.get("proof");
      const proofName =
        proof && typeof proof === "object" && "name" in proof && proof.name ? proof.name : "";
      nonCashItems.push({
        non_cash_id: generateId("nc"),
        trip_id: fd.get("trip_id"),
        estimated_value_base: parseFloat(fd.get("estimated_value_base")),
        description: fd.get("description"),
        contributer: fd.get("contributer"),
        proof_file: proofName || null,
        leader_comment: "",
        status: "Pending",
      });
      ev.target.reset();
      toast("Non-cash added");
      render(container, appStateSafe);
    });

    const submitBtn = container.querySelector("#exp-f-submit");
    if (submitBtn) submitBtn.textContent = editingExpenseId ? "Save changes" : "Add expense";
    const cancelBtn = container.querySelector("#exp-f-cancel-edit");
    if (cancelBtn) cancelBtn.hidden = !editingExpenseId;
  }

  global.Expenses = { render, formatMoney };
})(typeof window !== "undefined" ? window : globalThis);
  
