<?php
require_once __DIR__ . '/../../controller/AuthController.php';
require_once __DIR__ . '/../../model/user.php';
require_once __DIR__ . '/../../controller/TripController.php';

$auth = new AuthController();

$currentUser = $auth->getCurrentUser();

$tripController = new TripController();

$trips = $tripController->getAllTrips($currentUser->user_id);

$active_trip_id = isset($_GET['trip_id']) ? $_GET['trip_id'] : ($trips[0]['trip_id'] ?? null);

$activeTrip = null;
if ($active_trip_id) {
    foreach ($trips as $t) {
        if ($t['trip_id'] == $active_trip_id) {
            $activeTrip = $t;
            break;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Members - TripSync</title>
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
    <label class="field-label">Active trip</label>
    <div class="select select--full" style="background: #f8f9fa; border-color: #e9ecef; cursor: default; color: #495057;">
        <?php 
            echo isset($activeTrip) ? htmlspecialchars($activeTrip['trip_name']) : 'No Active Trip'; 
        ?>
    </div>
    </div>
    <nav class="sidebar__nav" aria-label="Main navigation">
    <a href="index.php?trip_id=<?php echo $active_trip_id; ?>" 
       class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'is-active' : ''; ?>" 
       style="text-decoration:none;color:inherit;">
       <span class="nav-item__icon">◉</span> Dashboard
    </a>

    <a href="members.php?trip_id=<?php echo $active_trip_id; ?>" 
       class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'members.php') ? 'is-active' : ''; ?>" 
       style="text-decoration:none;color:inherit;">
       <span class="nav-item__icon">👥</span> Members
    </a>

    <a href="itinerary.php?trip_id=<?php echo $active_trip_id; ?>" 
       class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'itinerary.php') ? 'is-active' : ''; ?>" 
       style="text-decoration:none;color:inherit;">
       <span class="nav-item__icon">◎</span> Itinerary
    </a>

    <a href="voting.php?trip_id=<?php echo $active_trip_id; ?>" 
       class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'voting.php') ? 'is-active' : ''; ?>" 
       style="text-decoration:none;color:inherit;">
       <span class="nav-item__icon">◇</span> Voting
    </a>

    <a href="rsvp.php?trip_id=<?php echo $active_trip_id; ?>" 
       class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'rsvp.php') ? 'is-active' : ''; ?>" 
       style="text-decoration:none;color:inherit;">
       <span class="nav-item__icon">✓</span> RSVP
    </a>

    <a href="expenses.php?trip_id=<?php echo $active_trip_id; ?>" 
       class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'expenses.php') ? 'is-active' : ''; ?>" 
       style="text-decoration:none;color:inherit;">
       <span class="nav-item__icon">$</span> Expenses
    </a>

    <a href="chat.php?trip_id=<?php echo $active_trip_id; ?>" 
       class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'chat.php') ? 'is-active' : ''; ?>" 
       style="text-decoration:none;color:inherit;">
       <span class="nav-item__icon">💬</span> Chat
    </a>

    <a href="documents.php?trip_id=<?php echo $active_trip_id; ?>" 
       class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'documents.php') ? 'is-active' : ''; ?>" 
       style="text-decoration:none;color:inherit;">
       <span class="nav-item__icon">📄</span> Documents
    </a>

    <a href="checklist.php?trip_id=<?php echo $active_trip_id; ?>" 
       class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'checklist.php') ? 'is-active' : ''; ?>" 
       style="text-decoration:none;color:inherit;">
       <span class="nav-item__icon">☑</span> Checklist
    </a>
</nav>
    <div class="sidebar__footer">
      <div class="user-chip">
        <span class="avatar avatar--sm" style="background:#6366f1"><?php echo ucfirst($currentUser->name[0]) ?></span>
        <div>
          <div class="user-chip__name"><?php echo $currentUser->name ?></div>
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
        <p class="eyebrow">Team</p>
        <h1 class="topbar__title">Members</h1>
        <p class="muted topbar__session" style="margin:0.35rem 0 0;font-size:0.85rem;">Signed in as <?php echo $currentUser->name ?></p>
      </div>
      <div class="topbar__actions"></div>
    </header>
    <div class="content">

      <div class="members-hero card card--gradient">
        <div class="members-hero__text"><h2 class="section-title" style="margin:0 0 0.35rem;">Team &amp; access</h2><p class="muted" style="margin:0;">Organizers manage invites, roles, and trip settings.</p></div>
        <div class="members-invite"><input type="text" class="input" placeholder="Email or exact account name" value="newmember@email.com" /><button type="button" class="btn btn--primary">Invite</button></div>
      </div>
      <div class="grid grid--2" style="margin-top:1rem;">
        <div class="member-card card member-card--organizer"><div class="member-card__top"><span class="avatar" style="background:#6366f1">A</span><div class="member-card__info"><div class="member-card__name"><?php echo $currentUser->name ?> <span class="badge badge--info">You</span></div><div class="muted" style="font-size:0.82rem;">alex@example.com</div><div style="margin-top:0.35rem;display:flex;gap:0.35rem;flex-wrap:wrap;"><span class="badge badge--confirmed">Organizer</span><span class="badge badge--draft">Active</span></div></div></div><div class="member-card__actions"><button type="button" class="btn btn--sm btn--secondary" disabled>Promote</button><button type="button" class="btn btn--sm btn--secondary">Demote</button><button type="button" class="btn btn--sm btn--danger">Remove</button></div></div>
        <div class="member-card card"><div class="member-card__top"><span class="avatar" style="background:#8b5cf6">S</span><div class="member-card__info"><div class="member-card__name">Sara Ahmed</div><div class="muted" style="font-size:0.82rem;">sara@example.com</div><div style="margin-top:0.35rem;display:flex;gap:0.35rem;flex-wrap:wrap;"><span class="badge badge--info">Member</span><span class="badge badge--draft">Active</span></div></div></div><div class="member-card__actions"><button type="button" class="btn btn--sm btn--secondary">Promote</button><button type="button" class="btn btn--sm btn--secondary" disabled>Demote</button><button type="button" class="btn btn--sm btn--danger">Remove</button></div></div>
        <div class="member-card card"><div class="member-card__top"><span class="avatar" style="background:#06b6d4">M</span><div class="member-card__info"><div class="member-card__name">Mona Hassan</div><div class="muted" style="font-size:0.82rem;">mona@example.com</div><div style="margin-top:0.35rem;display:flex;gap:0.35rem;flex-wrap:wrap;"><span class="badge badge--info">Member</span><span class="badge badge--draft">Active</span></div></div></div><div class="member-card__actions"><button type="button" class="btn btn--sm btn--secondary">Promote</button><button type="button" class="btn btn--sm btn--secondary" disabled>Demote</button><button type="button" class="btn btn--sm btn--danger">Remove</button></div></div>
        <div class="member-card card"><div class="member-card__top"><span class="avatar" style="background:#f43f5e">A</span><div class="member-card__info"><div class="member-card__name">Ahmed Ali</div><div class="muted" style="font-size:0.82rem;">ahmed@example.com</div><div style="margin-top:0.35rem;display:flex;gap:0.35rem;flex-wrap:wrap;"><span class="badge badge--info">Member</span><span class="badge badge--draft">Active</span></div></div></div><div class="member-card__actions"><button type="button" class="btn btn--sm btn--secondary">Promote</button><button type="button" class="btn btn--sm btn--secondary" disabled>Demote</button><button type="button" class="btn btn--sm btn--danger">Remove</button></div></div>
      </div>
      <div class="card" style="margin-top:1rem;"><h3 class="card__title">Pending invitations</h3><ul class="list-plain">
        <li style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;flex-wrap:wrap;"><div><strong>john@example.com</strong><br/><span class="muted" style="font-size:0.78rem;">Pending · sent May 1, 2025, 10:30 AM</span></div><button type="button" class="btn btn--sm btn--secondary">Cancel</button></li>
        <li style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;flex-wrap:wrap;"><div><strong>lisa@example.com</strong><br/><span class="muted" style="font-size:0.78rem;">Pending · sent Apr 28, 2025, 3:15 PM</span></div><button type="button" class="btn btn--sm btn--secondary">Cancel</button></li>
      </ul></div>
    </div>
  </main>
</div>
<div id="modal-root" class="modal-root" aria-live="polite"></div>
<div id="toast-root" class="toast-root"></div>
</body>
</html>
