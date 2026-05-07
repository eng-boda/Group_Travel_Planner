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
  <title>Checklist - TripSync</title>
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
      <a href="chat.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">💬</span> Chat</a>
      <a href="documents.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">📄</span> Documents</a>
      <a href="checklist.php" class="nav-item is-active" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">☑</span> Checklist</a>
    </nav>
    <div class="sidebar__footer"><div class="user-chip"><span class="avatar avatar--sm" style="background:#6366f1">A</span><div><div class="user-chip__name"><?php echo $currentUser->name ?></div><div class="user-chip__role">Organizer on this trip</div></div></div><a href="../Auth/logout.php" class="btn btn--ghost btn--sm" style="width:100%;margin-top:0.5rem;text-align:center;text-decoration:none;">Log out</a></div>
  </aside>
  <div class="sidebar-backdrop" aria-hidden="true"></div>
  <main class="main">
    <header class="topbar">
      <button type="button" class="btn btn--icon mobile-only" aria-label="Open menu">☰</button>
      <div class="topbar__titles"><p class="eyebrow">Execution</p><h1 class="topbar__title">Checklist</h1><p class="muted topbar__session" style="margin:0.35rem 0 0;font-size:0.85rem;">Signed in as <?php echo $currentUser->name ?></p></div>
      <div class="topbar__actions"><button type="button" class="btn btn--primary">+ Add item</button></div>
    </header>
    <div class="content">

<div class="tabs"><button type="button" class="tab is-active">All</button><button type="button" class="tab">Pending</button><button type="button" class="tab">Assigned</button><button type="button" class="tab">Completed</button></div>
<div class="card" style="margin-bottom:0.65rem;"><div class="card__header"><h3 class="card__title" style="font-size:0.95rem;">Book flights</h3><span class="badge badge--confirmed">Completed</span></div><p class="muted" style="font-size:0.85rem;margin:0 0 0.5rem;">Assignee: <?php echo $currentUser->name ?></p><div style="display:flex;flex-wrap:wrap;gap:0.35rem;"><button type="button" class="btn btn--sm btn--secondary">Edit</button><button type="button" class="btn btn--sm btn--secondary">→ Pending</button><button type="button" class="btn btn--sm btn--secondary">→ Assigned</button><button type="button" class="btn btn--sm btn--secondary">→ Done</button><button type="button" class="btn btn--sm btn--danger">Delete</button></div></div>
<div class="card" style="margin-bottom:0.65rem;"><div class="card__header"><h3 class="card__title" style="font-size:0.95rem;">Reserve hotel rooms</h3><span class="badge badge--confirmed">Completed</span></div><p class="muted" style="font-size:0.85rem;margin:0 0 0.5rem;">Assignee: Sara Ahmed</p><div style="display:flex;flex-wrap:wrap;gap:0.35rem;"><button type="button" class="btn btn--sm btn--secondary">Edit</button><button type="button" class="btn btn--sm btn--secondary">→ Pending</button><button type="button" class="btn btn--sm btn--secondary">→ Assigned</button><button type="button" class="btn btn--sm btn--secondary">→ Done</button><button type="button" class="btn btn--sm btn--danger">Delete</button></div></div>
<div class="card" style="margin-bottom:0.65rem;"><div class="card__header"><h3 class="card__title" style="font-size:0.95rem;">Arrange airport transfer</h3><span class="badge badge--info">Assigned</span></div><p class="muted" style="font-size:0.85rem;margin:0 0 0.5rem;">Assignee: Ahmed Ali</p><div style="display:flex;flex-wrap:wrap;gap:0.35rem;"><button type="button" class="btn btn--sm btn--secondary">Edit</button><button type="button" class="btn btn--sm btn--secondary">→ Pending</button><button type="button" class="btn btn--sm btn--secondary">→ Assigned</button><button type="button" class="btn btn--sm btn--secondary">→ Done</button><button type="button" class="btn btn--sm btn--danger">Delete</button></div></div>
<div class="card" style="margin-bottom:0.65rem;"><div class="card__header"><h3 class="card__title" style="font-size:0.95rem;">Purchase travel insurance</h3><span class="badge badge--info">Assigned</span></div><p class="muted" style="font-size:0.85rem;margin:0 0 0.5rem;">Assignee: Mona Hassan</p><div style="display:flex;flex-wrap:wrap;gap:0.35rem;"><button type="button" class="btn btn--sm btn--secondary">Edit</button><button type="button" class="btn btn--sm btn--secondary">→ Pending</button><button type="button" class="btn btn--sm btn--secondary">→ Assigned</button><button type="button" class="btn btn--sm btn--secondary">→ Done</button><button type="button" class="btn btn--sm btn--danger">Delete</button></div></div>
<div class="card" style="margin-bottom:0.65rem;"><div class="card__header"><h3 class="card__title" style="font-size:0.95rem;">Create packing list</h3><span class="badge badge--draft">Pending</span></div><p class="muted" style="font-size:0.85rem;margin:0 0 0.5rem;">Assignee: <?php echo $currentUser->name ?></p><div style="display:flex;flex-wrap:wrap;gap:0.35rem;"><button type="button" class="btn btn--sm btn--secondary">Edit</button><button type="button" class="btn btn--sm btn--secondary">→ Pending</button><button type="button" class="btn btn--sm btn--secondary">→ Assigned</button><button type="button" class="btn btn--sm btn--secondary">→ Done</button><button type="button" class="btn btn--sm btn--danger">Delete</button></div></div>
<div class="card" style="margin-bottom:0.65rem;"><div class="card__header"><h3 class="card__title" style="font-size:0.95rem;">Confirm restaurant reservations</h3><span class="badge badge--draft">Pending</span></div><p class="muted" style="font-size:0.85rem;margin:0 0 0.5rem;">Assignee: Sara Ahmed</p><div style="display:flex;flex-wrap:wrap;gap:0.35rem;"><button type="button" class="btn btn--sm btn--secondary">Edit</button><button type="button" class="btn btn--sm btn--secondary">→ Pending</button><button type="button" class="btn btn--sm btn--secondary">→ Assigned</button><button type="button" class="btn btn--sm btn--secondary">→ Done</button><button type="button" class="btn btn--sm btn--danger">Delete</button></div></div>
<div class="card" style="margin-bottom:0.65rem;"><div class="card__header"><h3 class="card__title" style="font-size:0.95rem;">Exchange currency</h3><span class="badge badge--draft">Pending</span></div><p class="muted" style="font-size:0.85rem;margin:0 0 0.5rem;">Assignee: Ahmed Ali</p><div style="display:flex;flex-wrap:wrap;gap:0.35rem;"><button type="button" class="btn btn--sm btn--secondary">Edit</button><button type="button" class="btn btn--sm btn--secondary">→ Pending</button><button type="button" class="btn btn--sm btn--secondary">→ Assigned</button><button type="button" class="btn btn--sm btn--secondary">→ Done</button><button type="button" class="btn btn--sm btn--danger">Delete</button></div></div>

<h2 class="section-title" style="margin-top:2rem;">New checklist item form</h2>
<div class="card"><div class="form-grid">
<div class="form-row"><label>Title</label><input class="input" value="Pack chargers" /></div>
<div class="form-row"><label>Assign to</label><select class="input"><option><?php echo $currentUser->name ?></option><option>Sara Ahmed</option><option>Mona Hassan</option><option>Ahmed Ali</option></select></div>
<div class="form-row"><label>Status</label><select class="input"><option selected>Pending</option><option>Assigned</option><option>Completed</option></select></div>
<div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" class="btn btn--secondary">Cancel</button><button type="button" class="btn btn--primary">Add item</button></div>
</div></div>
    </div></main></div>
<div id="modal-root" class="modal-root" aria-live="polite"></div>
<div id="toast-root" class="toast-root"></div>
</body></html>
