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
  <title>Documents - TripSync</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../css/main.css" />
</head>
<body>
<div id="app" class="app">
  <aside class="sidebar">
    <div class="sidebar__brand"><div class="logo-mark" aria-hidden="true">✈</div><div><div class="logo-text">TripSync</div><div class="logo-sub">Collaborative Planner</div></div></div>

    <!-- FIXED: interactive trip selector matching expenses.php -->
    <div class="sidebar__trip">
      <label class="field-label">Active trip</label>
      <select class="select select--full"
              onchange="window.location.href='documents.php?trip_id=' + this.value">
        <?php if (empty($trips)): ?>
          <option value="">No trips yet</option>
        <?php else: ?>
          <?php foreach ($trips as $t): ?>
            <option value="<?php echo (int)$t['trip_id']; ?>"
                    <?php echo ($t['trip_id'] == $active_trip_id) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($t['trip_name']); ?>
            </option>
          <?php endforeach; ?>
        <?php endif; ?>
      </select>
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
    <div class="sidebar__footer"><div class="user-chip"><span class="avatar avatar--sm" style="background:#6366f1"><?php echo ucfirst($currentUser->name[0]) ?></span><div><div class="user-chip__name"><?php echo $currentUser->name ?></div><div class="user-chip__role">Organizer on this trip</div></div></div><a href="../Auth/logout.php" class="btn btn--ghost btn--sm" style="width:100%;margin-top:0.5rem;text-align:center;text-decoration:none;">Log out</a></div>
  </aside>
  <div class="sidebar-backdrop" aria-hidden="true"></div>
  <main class="main">
    <header class="topbar">
      <button type="button" class="btn btn--icon mobile-only" aria-label="Open menu">☰</button>
      <div class="topbar__titles"><p class="eyebrow">Files</p><h1 class="topbar__title">Documents</h1><p class="muted topbar__session" style="margin:0.35rem 0 0;font-size:0.85rem;">Signed in as <?php echo $currentUser->name ?></p></div>
      <div class="topbar__actions"><label class="btn btn--primary" style="cursor:pointer;margin:0;">+ Upload<input type="file" hidden multiple /></label></div>
    </header>
    <div class="content">

<div class="card"><div class="card__header"><h3 class="card__title">Library</h3><span class="badge badge--info">4 files</span></div>
<ul class="list-plain">
  <li style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;flex-wrap:wrap;"><div><strong>boarding-pass.pdf</strong><br/><span class="muted" style="font-size:0.8rem;">245 KB · Linked: Day 3: Checkout &amp; Airport</span></div><div style="display:flex;gap:0.35rem;"><button type="button" class="btn btn--sm btn--secondary">Attach…</button><button type="button" class="btn btn--sm btn--danger">Delete</button></div></li>
  <li style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;flex-wrap:wrap;"><div><strong>hotel-confirmation.pdf</strong><br/><span class="muted" style="font-size:0.8rem;">128 KB · Linked: Day 1: Museum Visit</span></div><div style="display:flex;gap:0.35rem;"><button type="button" class="btn btn--sm btn--secondary">Attach…</button><button type="button" class="btn btn--sm btn--danger">Delete</button></div></li>
  <li style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;flex-wrap:wrap;"><div><strong>restaurant-menu.jpg</strong><br/><span class="muted" style="font-size:0.8rem;">892 KB · Linked: —</span></div><div style="display:flex;gap:0.35rem;"><button type="button" class="btn btn--sm btn--secondary">Attach…</button><button type="button" class="btn btn--sm btn--danger">Delete</button></div></li>
  <li style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;flex-wrap:wrap;"><div><strong>travel-insurance.pdf</strong><br/><span class="muted" style="font-size:0.8rem;">1.2 MB · Linked: —</span></div><div style="display:flex;gap:0.35rem;"><button type="button" class="btn btn--sm btn--secondary">Attach…</button><button type="button" class="btn btn--sm btn--danger">Delete</button></div></li>
</ul></div>
<p class="muted" style="margin-top:0.75rem;font-size:0.85rem;">Uploads are simulated — file metadata is stored locally.</p>
<h2 class="section-title" style="margin-top:2rem;">Attach to activity form</h2>
<div class="card"><div class="form-grid"><p class="muted">Link this file to an itinerary activity.</p><div class="form-row"><label>Activity</label><select class="input"><option value="">— Not attached —</option><option>Day 1: City Walking Tour</option><option>Day 1: Museum Visit</option><option>Day 2: Beach Day</option><option>Day 3: Checkout &amp; Airport</option></select></div><div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" class="btn btn--secondary">Cancel</button><button type="button" class="btn btn--primary">Save link</button></div></div></div>
    </div></main></div>
<div id="modal-root" class="modal-root" aria-live="polite"></div>
<div id="toast-root" class="toast-root"></div>
</body></html>