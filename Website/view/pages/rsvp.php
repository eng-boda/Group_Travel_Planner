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
  <title>RSVP - TripSync</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../css/main.css" />
</head>
<body>
<div id="app" class="app">
  <aside class="sidebar">
    <div class="sidebar__brand"><div class="logo-mark" aria-hidden="true">✈</div><div><div class="logo-text">TripSync</div><div class="logo-sub">Collaborative Planner</div></div></div>
    <div class="sidebar__trip"><label class="field-label">Active trip</label><select class="select select--full"><option selected>Summer Europe Tour</option><option>Berlin Workshop</option><option>Tokyo Summit</option></select><a href="index.html" class="btn btn--ghost btn--sm sidebar__new-trip" style="text-decoration:none;text-align:center;">+ New trip</a></div>
    <nav class="sidebar__nav">
      <a href="index.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">◉</span> Dashboard</a>
      <a href="members.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">👥</span> Members</a>
      <a href="itinerary.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">◎</span> Itinerary</a>
      <a href="voting.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">◇</span> Voting</a>
      <a href="rsvp.php" class="nav-item is-active" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">✓</span> RSVP</a>
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
      <div class="topbar__titles"><p class="eyebrow">Attendance</p><h1 class="topbar__title">RSVP</h1><p class="muted topbar__session" style="margin:0.35rem 0 0;font-size:0.85rem;">Signed in as <?php echo $currentUser->name ?></p></div>
      <div class="topbar__actions"></div>
    </header>
    <div class="content">

<div class="card card--gradient">
  <div class="card__header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
    <div><h2 class="section-title" style="margin:0;">Activity Attendance</h2><p class="muted" style="margin:4px 0 0;">Select an activity to see who is attending.</p></div>
    <div style="min-width:260px;max-width:400px;width:100%;"><select class="input"><option>Day 1 · 09:00 · City Walking Tour</option><option>Day 1 · 14:00 · Museum Visit</option><option>Day 2 · 10:00 · Beach Day</option><option>Day 2 · 17:00 · Sunset Cruise</option><option>Day 3 · 08:00 · Checkout &amp; Airport</option></select></div>
  </div>
  <div class="grid grid--3" style="margin-top:1.5rem;">
    <div class="card" style="border-top:4px solid var(--success);"><h3 class="card__title">Going</h3><div style="font-size:2.5rem;font-weight:800;color:var(--success);margin:0.5rem 0;">3</div><ul class="list-plain" style="border-top:1px solid #eee;padding-top:0.5rem;"><li><?php echo $currentUser->name ?></li><li>Sara Ahmed</li><li>Ahmed Ali</li></ul></div>
    <div class="card" style="border-top:4px solid var(--warning);"><h3 class="card__title">Maybe</h3><div style="font-size:2.5rem;font-weight:800;color:var(--warning);margin:0.5rem 0;">1</div><ul class="list-plain" style="border-top:1px solid #eee;padding-top:0.5rem;"><li>Mona Hassan</li></ul></div>
    <div class="card" style="border-top:4px solid var(--danger);"><h3 class="card__title">Not going</h3><div style="font-size:2.5rem;font-weight:800;color:var(--danger);margin:0.5rem 0;">0</div><ul class="list-plain" style="border-top:1px solid #eee;padding-top:0.5rem;"><li class="muted">—</li></ul></div>
  </div>
</div>
    </div></main></div>
<div id="modal-root" class="modal-root" aria-live="polite"></div>
<div id="toast-root" class="toast-root"></div>
</body></html>/body></html>
