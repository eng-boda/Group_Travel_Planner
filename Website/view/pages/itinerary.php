<?php
require_once __DIR__ . '/../../controller/AuthController.php';
require_once __DIR__ . '/../../model/user.php';

$auth = new AuthController();

$currentUser = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Itinerary - TripSync</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../css/main.css" />
</head>
<body>
<div id="app" class="app">
  <aside class="sidebar">
    <div class="sidebar__brand"><div class="logo-mark" aria-hidden="true">✈</div><div><div class="logo-text">TripSync</div><div class="logo-sub">Collaborative Planner</div></div></div>
    <div class="sidebar__trip"><label class="field-label">Active trip</label><select class="select select--full"><option selected>Summer Europe Tour</option><option>Berlin Workshop</option><option>Tokyo Summit</option></select><a href="index.php" class="btn btn--ghost btn--sm sidebar__new-trip" style="text-decoration:none;text-align:center;">+ New trip</a></div>
    <nav class="sidebar__nav">
      <a href="index.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">◉</span> Dashboard</a>
      <a href="members.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">👥</span> Members</a>
      <a href="itinerary.php" class="nav-item is-active" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">◎</span> Itinerary</a>
      <a href="voting.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">◇</span> Voting</a>
      <a href="rsvp.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">✓</span> RSVP</a>
      <a href="expenses.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">$</span> Expenses</a>
      <a href="chat.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">💬</span> Chat</a>
      <a href="documents.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">📄</span> Documents</a>
      <a href="checklist.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">☑</span> Checklist</a>
    </nav>
    <div class="sidebar__footer"><div class="user-chip"><span class="avatar avatar--sm" style="background:#6366f1">A</span><div><div class="user-chip__name"><?php echo $currentUser->name ?></div><div class="user-chip__role">Organizer on this trip</div></div></div><a href="../Auth/logout.php" class="btn btn--ghost btn--sm" style="width:100%;margin-top:0.5rem;text-align:center;text-decoration:none;">Log out</a></div>
  </aside>
  <div class="sidebar-backdrop" aria-hidden="true"></div>
  <main class="main">
    <header class="topbar">
      <button type="button" class="btn btn--icon mobile-only" aria-label="Open menu">☰</button>
      <div class="topbar__titles"><p class="eyebrow">Planning</p><h1 class="topbar__title">Itinerary</h1><p class="muted topbar__session" style="margin:0.35rem 0 0;font-size:0.85rem;">Signed in as <?php echo $currentUser->name ?></p></div>
      <div class="topbar__actions"><button type="button" class="btn btn--secondary">+ Add day</button> <button type="button" class="btn btn--primary">+ Add activity</button></div>
    </header>
    <div class="content">

<div class="conflict-banner"><span>⚠</span><div><strong>Possible schedule overlap</strong> on Day 1.</div></div>
<div class="tabs"><button type="button" class="tab is-active">Board</button><button type="button" class="tab">Compact list</button></div>
<div class="day-board">
  <div class="day-column"><div class="day-column__head"><span class="day-column__label">Day 1</span><button type="button" class="btn btn--sm btn--secondary">Add here</button></div><div class="day-activities">
    <div class="activity-card"><div style="font-weight:700;">City Walking Tour</div><span class="badge badge--confirmed">confirmed</span><div class="activity-card__meta">🕐 09:00 · Central Square</div><div class="activity-card__meta">Meet at the fountain</div><div class="activity-card__meta">☀️ 28°C · Partly cloudy</div><div style="margin-top:0.6rem;padding-top:0.6rem;border-top:1px solid rgba(99,102,241,0.12);"><div style="font-size:0.72rem;font-weight:600;color:#64748b;margin-bottom:0.35rem;text-transform:uppercase;letter-spacing:.04em;">Your RSVP</div><div style="display:flex;gap:0.35rem;flex-wrap:wrap;align-items:center;"><button type="button" class="btn btn--sm btn--primary">✅ Yes</button><button type="button" class="btn btn--sm btn--secondary">🤔 Maybe</button><button type="button" class="btn btn--sm btn--secondary">❌ No</button><span style="font-size:0.78rem;color:#64748b;margin-left:0.25rem;">3 going · 1 maybe · 0 not going</span></div></div><div style="margin-top:0.5rem;display:flex;gap:0.35rem;"><button type="button" class="btn btn--sm btn--secondary">Edit</button><button type="button" class="btn btn--sm btn--danger">Delete</button></div></div>
    <div class="activity-card"><div style="font-weight:700;">Museum Visit</div><span class="badge badge--confirmed">confirmed</span><div class="activity-card__meta">🕐 14:00 · National Museum</div><div style="margin-top:0.6rem;padding-top:0.6rem;border-top:1px solid rgba(99,102,241,0.12);"><div style="font-size:0.72rem;font-weight:600;color:#64748b;margin-bottom:0.35rem;text-transform:uppercase;">Your RSVP</div><div style="display:flex;gap:0.35rem;flex-wrap:wrap;"><button type="button" class="btn btn--sm btn--secondary">✅ Yes</button><button type="button" class="btn btn--sm btn--primary">🤔 Maybe</button><button type="button" class="btn btn--sm btn--secondary">❌ No</button><span style="font-size:0.78rem;color:#64748b;margin-left:0.25rem;">2 going · 2 maybe · 0 not going</span></div></div><div style="margin-top:0.5rem;display:flex;gap:0.35rem;"><button type="button" class="btn btn--sm btn--secondary">Edit</button><button type="button" class="btn btn--sm btn--danger">Delete</button></div></div>
    <div class="activity-card"><div style="font-weight:700;">Dinner Reservation</div><span class="badge badge--draft">draft</span><div class="activity-card__meta">🕐 19:30 · La Bella Italia</div><div style="margin-top:0.5rem;display:flex;gap:0.35rem;"><button type="button" class="btn btn--sm btn--secondary">Edit</button><button type="button" class="btn btn--sm btn--danger">Delete</button></div></div>
  </div></div>
  <div class="day-column"><div class="day-column__head"><span class="day-column__label">Day 2</span><button type="button" class="btn btn--sm btn--secondary">Add here</button></div><div class="day-activities">
    <div class="activity-card"><div style="font-weight:700;">Beach Day</div><span class="badge badge--confirmed">confirmed</span><div class="activity-card__meta">🕐 10:00 · Sunny Beach</div><div class="activity-card__meta">☀️ 32°C · Clear</div><div style="margin-top:0.5rem;display:flex;gap:0.35rem;"><button type="button" class="btn btn--sm btn--secondary">Edit</button><button type="button" class="btn btn--sm btn--danger">Delete</button></div></div>
    <div class="activity-card"><div style="font-weight:700;">Sunset Cruise</div><span class="badge badge--draft">draft</span><div class="activity-card__meta">🕐 17:00 · Harbor Marina</div><div class="activity-card__meta">Bring warm jacket</div><div style="margin-top:0.5rem;display:flex;gap:0.35rem;"><button type="button" class="btn btn--sm btn--secondary">Edit</button><button type="button" class="btn btn--sm btn--danger">Delete</button></div></div>
  </div></div>
  <div class="day-column"><div class="day-column__head"><span class="day-column__label">Day 3</span><button type="button" class="btn btn--sm btn--secondary">Add here</button></div><div class="day-activities">
    <div class="activity-card"><div style="font-weight:700;">Checkout & Airport</div><span class="badge badge--confirmed">confirmed</span><div class="activity-card__meta">🕐 08:00 · Hotel Lobby</div><div style="margin-top:0.5rem;display:flex;gap:0.35rem;"><button type="button" class="btn btn--sm btn--secondary">Edit</button><button type="button" class="btn btn--sm btn--danger">Delete</button></div></div>
  </div></div>
</div>
<h2 class="section-title" style="margin-top:2rem;">Compact list view</h2>
<div class="card" style="margin-bottom:0.75rem;"><h3 class="card__title">Day 1</h3><ul class="list-plain"><li><strong>City Walking Tour</strong> — 09:00 · Central Square</li><li><strong>Museum Visit</strong> — 14:00 · National Museum</li><li><strong>Dinner Reservation</strong> — 19:30 · La Bella Italia</li></ul></div>
<div class="card" style="margin-bottom:0.75rem;"><h3 class="card__title">Day 2</h3><ul class="list-plain"><li><strong>Beach Day</strong> — 10:00 · Sunny Beach</li><li><strong>Sunset Cruise</strong> — 17:00 · Harbor Marina</li></ul></div>
<div class="card" style="margin-bottom:0.75rem;"><h3 class="card__title">Day 3</h3><ul class="list-plain"><li><strong>Checkout &amp; Airport</strong> — 08:00 · Hotel Lobby</li></ul></div>
<h2 class="section-title" style="margin-top:2rem;">New activity form</h2>
<div class="card"><div class="form-grid">
<div class="form-row"><label>Title</label><input class="input" value="Guided Tour" /></div>
<div class="form-row"><label>Time</label><input type="time" class="input" value="10:00" /></div>
<div class="form-row"><label>Location</label><input class="input" value="Old Town" /></div>
<div class="form-row"><label>Notes</label><textarea class="textarea">Bring comfortable shoes</textarea></div>
<div class="form-row"><label>Activity type</label><select class="input"><option>Indoor</option><option selected>Outdoor</option></select></div>
<div class="form-row"><label>Status</label><select class="input"><option>Draft</option><option selected>Confirmed</option></select></div>
<div class="form-row"><label>Attach document</label><select class="input"><option value="">— None —</option><option>boarding-pass.pdf</option></select></div>
<div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" class="btn btn--secondary">Cancel</button><button type="button" class="btn btn--primary">Add activity</button></div>
</div></div>
    </div></main></div>
<div id="modal-root" class="modal-root" aria-live="polite"></div>
<div id="toast-root" class="toast-root"></div>
</body></html>
