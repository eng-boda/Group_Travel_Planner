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
  <title>Chat - TripSync</title>
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
      <a href="itinerary.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">◎</span> Itinerary</a>
      <a href="voting.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">◇</span> Voting</a>
      <a href="rsvp.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">✓</span> RSVP</a>
      <a href="expenses.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">$</span> Expenses</a>
      <a href="chat.php" class="nav-item is-active" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">💬</span> Chat</a>
      <a href="documents.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">📄</span> Documents</a>
      <a href="checklist.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">☑</span> Checklist</a>
    </nav>
    <div class="sidebar__footer"><div class="user-chip"><span class="avatar avatar--sm" style="background:#6366f1">A</span><div><div class="user-chip__name"><?php echo $currentUser->name ?></div><div class="user-chip__role">Organizer on this trip</div></div></div><a href="../Auth/logout.php" class="btn btn--ghost btn--sm" style="width:100%;margin-top:0.5rem;text-align:center;text-decoration:none;">Log out</a></div>
  </aside>
  <div class="sidebar-backdrop" aria-hidden="true"></div>
  <main class="main">
    <header class="topbar">
      <button type="button" class="btn btn--icon mobile-only" aria-label="Open menu">☰</button>
      <div class="topbar__titles"><p class="eyebrow">Collaboration</p><h1 class="topbar__title">Chat</h1><p class="muted topbar__session" style="margin:0.35rem 0 0;font-size:0.85rem;">Signed in as <?php echo $currentUser->name ?></p></div>
      <div class="topbar__actions"><button type="button" class="btn btn--secondary">Simulate activity</button></div>
    </header>
    <div class="content">

<div class="chat-layout">
  <div class="card">
    <div class="card__header"><h3 class="card__title">Trip conversation</h3><span class="badge badge--info">Workspace</span></div>
    <div class="thread-list">
      <div class="msg"><span class="avatar avatar--sm" style="background:#6366f1">A</span><div class="msg__bubble"><div class="msg__author"><?php echo $currentUser->name ?></div><p class="msg__text">Hey team! I've booked the hotel for Day 1-3. Check the documents tab for confirmation.</p><div class="msg__time">May 5, 10:30 AM</div><button type="button" class="btn btn--sm btn--secondary" style="margin-top:0.35rem;">Reply</button></div></div>
      <div class="msg msg--reply"><span class="avatar avatar--sm" style="background:#8b5cf6">S</span><div class="msg__bubble"><div class="msg__author">Sara Ahmed</div><p class="msg__text">Great! I'll add it to the itinerary.</p><div class="msg__time">May 5, 10:45 AM</div><button type="button" class="btn btn--sm btn--secondary" style="margin-top:0.35rem;">Reply</button></div></div>
      <div class="msg"><span class="avatar avatar--sm" style="background:#06b6d4">M</span><div class="msg__bubble"><div class="msg__author">Mona Hassan</div><p class="msg__text">Just shared notes in Documents 📎</p><div class="msg__time">May 5, 11:20 AM</div><button type="button" class="btn btn--sm btn--secondary" style="margin-top:0.35rem;">Reply</button></div></div>
      <div class="msg"><span class="avatar avatar--sm" style="background:#f43f5e">A</span><div class="msg__bubble"><div class="msg__author">Ahmed Ali</div><p class="msg__text">Can we lock Day 2 dinner time?</p><div class="msg__time">May 5, 2:15 PM</div><button type="button" class="btn btn--sm btn--secondary" style="margin-top:0.35rem;">Reply</button></div></div>
      <div class="msg msg--reply"><span class="avatar avatar--sm" style="background:#6366f1">A</span><div class="msg__bubble"><div class="msg__author"><?php echo $currentUser->name ?></div><p class="msg__text">Let's vote on it — I'll create a poll.</p><div class="msg__time">May 5, 2:30 PM</div><button type="button" class="btn btn--sm btn--secondary" style="margin-top:0.35rem;">Reply</button></div></div>
      <div class="msg msg--reply"><span class="avatar avatar--sm" style="background:#8b5cf6">S</span><div class="msg__bubble"><div class="msg__author">Sara Ahmed</div><p class="msg__text">Sounds good, 7pm or 8pm works for me.</p><div class="msg__time">May 5, 2:35 PM</div><button type="button" class="btn btn--sm btn--secondary" style="margin-top:0.35rem;">Reply</button></div></div>
      <div class="msg"><span class="avatar avatar--sm" style="background:#6366f1">A</span><div class="msg__bubble"><div class="msg__author"><?php echo $currentUser->name ?></div><p class="msg__text">Demo: Parking is P2 under the hotel — code 4821.</p><div class="msg__time">May 5, 4:00 PM</div><button type="button" class="btn btn--sm btn--secondary" style="margin-top:0.35rem;">Reply</button></div></div>
    </div>
    <div class="typing-indicator is-visible">Sara is typing…</div>
    <div class="chat-composer"><textarea class="textarea" placeholder="Share an update…" rows="3"></textarea><div style="display:flex;gap:0.5rem;flex-wrap:wrap;"><button type="button" class="btn btn--primary">Send message</button><button type="button" class="btn btn--secondary">Clear reply target</button></div></div>
  </div>
  <div class="card"><h3 class="card__title">Tips</h3><p class="muted" style="font-size:0.88rem;">Use <strong>Reply</strong> to keep threads tidy. Everything is stored in your browser session.</p><button type="button" class="btn btn--secondary" style="margin-top:0.75rem;width:100%;">Add demo thread</button></div>
</div>
    </div></main></div>
<div id="modal-root" class="modal-root" aria-live="polite"></div>
<div id="toast-root" class="toast-root"></div>
</body></html>
