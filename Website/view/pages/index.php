<?php
require_once __DIR__ . '/../../controller/AuthController.php';
require_once __DIR__ . '/../../model/user.php';

$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    header("Location: ../Auth/login.php");
    exit;
}

$currentUser = $auth->getCurrentUser();
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Trips Dashboard - TripSync</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../css/main.css" />
</head>
<body>
<div id="app" class="app">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar__brand">
      <div class="logo-mark" aria-hidden="true">✈</div>
      <div>
        <div class="logo-text">TripSync</div>
        <div class="logo-sub">Collaborative Planner</div>
      </div>
    </div>
    <div class="sidebar__trip">
      <label class="field-label" for="trip-select">Active trip</label>
      <select id="trip-select" class="select select--full">
        <option value="trip1" selected>Summer Europe Tour</option>
        <option value="trip2">Berlin Workshop</option>
        <option value="trip3">Tokyo Summit</option>
      </select>
      <a href="index.php" class="btn btn--ghost btn--sm sidebar__new-trip" style="text-decoration:none;text-align:center;">+ New trip</a>
    </div>
    <nav class="sidebar__nav" aria-label="Main navigation">
      <a href="index.php" class="nav-item is-active" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">◉</span> Dashboard</a>
      <a href="members.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">👥</span> Members</a>
      <a href="itinerary.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">◎</span> Itinerary</a>
      <a href="voting.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">◇</span> Voting</a>
      <a href="rsvp.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">✓</span> RSVP</a>
      <a href="expenses.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">$</span> Expenses</a>
      <a href="chat.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">💬</span> Chat</a>
      <a href="documents.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">📄</span> Documents</a>
      <a href="checklist.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">☑</span> Checklist</a>
    </nav>
    <div class="sidebar__footer">
      <div class="user-chip">
        <span class="avatar avatar--sm" style="background:#6366f1">A</span>
        <div>
          <div class="user-chip__name"> <?php echo $currentUser->name ?></div>
          <div class="user-chip__role">Organizer on this trip</div>
        </div>
      </div>
      <a href="../Auth/logout.php" class="btn btn--ghost btn--sm" style="width:100%;margin-top:0.5rem;text-align:center;text-decoration:none;">Log out</a>
    </div>
  </aside>
  <div class="sidebar-backdrop" id="sidebar-backdrop" aria-hidden="true"></div>
  <main class="main">
    <header class="topbar">
      <button type="button" class="btn btn--icon mobile-only" aria-label="Open menu">☰</button>
      <div class="topbar__titles">
        <p class="eyebrow">Workspace</p>
        <h1 class="topbar__title">Trips Dashboard</h1>
        <p class="muted topbar__session" style="margin:0.35rem 0 0;font-size:0.85rem;">Signed in as <?php echo $currentUser->name ?></p>
      </div>
      <div class="topbar__actions"><a href="index.php" class="btn btn--primary" style="text-decoration:none;">+ New trip</a> <button type="button" class="btn btn--secondary">Edit trip</button> <button type="button" class="btn btn--danger">Delete</button></div>
    </header>
    <div class="content">

      <div class="tabs"><button type="button" class="tab is-active">Overview</button><button type="button" class="tab">All trips</button><button type="button" class="tab">Members</button></div>
      <div class="grid grid--3">
        <div class="card card--gradient"><div class="card__header"><h3 class="card__title">Trip window</h3><span class="badge badge--info">Live</span></div><p class="muted">2025-07-10 → 2025-07-20</p><p class="muted" style="margin-top:0.5rem;">Summer tour across Europe visiting major cities and landmarks</p></div>
        <div class="card"><div class="card__header"><h3 class="card__title">Budget pulse</h3></div><p style="margin:0 0 0.5rem;font-size:0.9rem;"><strong>$1,250</strong> of <strong>$5,000</strong></p><div class="progress"><div class="progress__bar" style="width:25%"></div></div><p class="muted" style="margin-top:0.5rem;font-size:0.8rem;">25% of budget used</p></div>
        <div class="card"><div class="card__header"><h3 class="card__title">Collaboration</h3></div><p class="muted" style="margin:0 0 0.5rem;">4 going · 2 active polls</p><div class="avatar-row"><span class="avatar avatar--sm" style="background:#6366f1" title="Alex">A</span><span class="avatar avatar--sm" style="background:#8b5cf6" title="Sara">S</span><span class="avatar avatar--sm" style="background:#06b6d4" title="Mona">M</span><span class="avatar avatar--sm" style="background:#f43f5e" title="Ahmed">A</span></div></div>
      </div>
      <div class="card" style="margin-top:1rem;"><div class="card__header"><h3 class="card__title">Quick actions</h3></div><div style="display:flex;flex-wrap:wrap;gap:0.5rem;"><a href="members.php" class="btn btn--secondary" style="text-decoration:none;">Team &amp; invites</a><a href="itinerary.php" class="btn btn--secondary" style="text-decoration:none;">Open itinerary</a><a href="voting.php" class="btn btn--secondary" style="text-decoration:none;">Open voting</a><a href="expenses.php" class="btn btn--secondary" style="text-decoration:none;">Log expense</a><a href="chat.php" class="btn btn--secondary" style="text-decoration:none;">Open chat</a></div></div>

      <h2 class="section-title" style="margin-top:2rem;">All trips</h2>
      <div class="grid grid--2">
        <div class="card"><div class="card__header"><h3 class="card__title">Summer Europe Tour</h3><span class="badge badge--confirmed">Active</span></div><p class="muted">2025-07-10 → 2025-07-20</p><div style="margin-top:0.75rem;display:flex;flex-wrap:wrap;gap:0.4rem;"><button type="button" class="btn btn--sm btn--primary">Open</button><button type="button" class="btn btn--sm btn--secondary">Edit</button><button type="button" class="btn btn--sm btn--danger">Delete</button></div></div>
        <div class="card"><div class="card__header"><h3 class="card__title">Berlin Workshop</h3></div><p class="muted">2025-09-01 → 2025-09-05</p><div style="margin-top:0.75rem;display:flex;flex-wrap:wrap;gap:0.4rem;"><button type="button" class="btn btn--sm btn--primary">Open</button><span class="muted" style="font-size:0.8rem;align-self:center;">Member access</span></div></div>
        <div class="card"><div class="card__header"><h3 class="card__title">Tokyo Summit</h3></div><p class="muted">2025-11-15 → 2025-11-22</p><div style="margin-top:0.75rem;display:flex;flex-wrap:wrap;gap:0.4rem;"><button type="button" class="btn btn--sm btn--primary">Open</button><button type="button" class="btn btn--sm btn--secondary">Edit</button><button type="button" class="btn btn--sm btn--danger">Delete</button></div></div>
      </div>

      <div style="margin-top:2rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem;"><h2 class="section-title" style="margin:0;">Team snapshot</h2><a href="members.php" class="btn btn--primary" style="text-decoration:none;">Open members panel</a></div>
      <div class="grid grid--2">
        <div class="card" style="padding:0.85rem;display:flex;align-items:center;gap:0.75rem;"><span class="avatar" style="background:#6366f1">A</span><div style="flex:1;min-width:0;"><div style="font-weight:700;font-size:0.9rem;"><?php echo $currentUser->name ?></div><div class="muted" style="font-size:0.8rem;">Organizer · active</div></div></div>
        <div class="card" style="padding:0.85rem;display:flex;align-items:center;gap:0.75rem;"><span class="avatar" style="background:#8b5cf6">S</span><div style="flex:1;min-width:0;"><div style="font-weight:700;font-size:0.9rem;">Sara Ahmed</div><div class="muted" style="font-size:0.8rem;">Member · active</div></div></div>
        <div class="card" style="padding:0.85rem;display:flex;align-items:center;gap:0.75rem;"><span class="avatar" style="background:#06b6d4">M</span><div style="flex:1;min-width:0;"><div style="font-weight:700;font-size:0.9rem;">Mona Hassan</div><div class="muted" style="font-size:0.8rem;">Member · active</div></div></div>
        <div class="card" style="padding:0.85rem;display:flex;align-items:center;gap:0.75rem;"><span class="avatar" style="background:#f43f5e">A</span><div style="flex:1;min-width:0;"><div style="font-weight:700;font-size:0.9rem;">Ahmed Ali</div><div class="muted" style="font-size:0.8rem;">Member · active</div></div></div>
      </div>

      <h2 class="section-title" style="margin-top:2rem;">New trip form</h2>
      <div class="card"><div class="form-grid">
        <div class="form-row"><label for="f-name">Trip name</label><input id="f-name" class="input" value="Weekend Getaway" /></div>
        <div class="form-row"><label for="f-desc">Description</label><textarea id="f-desc" class="textarea">A fun trip with the team</textarea></div>
        <div class="form-row"><label for="f-start">Start date</label><input id="f-start" type="date" class="input" value="2025-08-01" /></div>
        <div class="form-row"><label for="f-end">End date</label><input id="f-end" type="date" class="input" value="2025-08-05" /></div>
        <div class="form-row"><label for="f-budget">Trip budget</label><input id="f-budget" type="number" class="input" value="3000" /></div>
        <div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" class="btn btn--secondary">Cancel</button><button type="button" class="btn btn--primary">Create trip</button></div>
      </div></div>
    </div>
  </main>
</div>
<div id="modal-root" class="modal-root" aria-live="polite"></div>
<div id="toast-root" class="toast-root"></div>
</body>
</html>
