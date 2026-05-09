<?php
require_once __DIR__ . '/../../controller/AuthController.php';
require_once __DIR__ . '/../../model/user.php';
require_once __DIR__ . '/../../controller/TripController.php';
require_once __DIR__ . '/../../controller/RoleController.php';
require_once __DIR__ . '/../../controller/MemberController.php';

$roleController   = new RoleController();
$memberController = new MemberController();

$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    header("Location: ../Auth/login.php");
    exit;
}

$currentUser    = $auth->getCurrentUser();
$tripController = new TripController();

// ── Create trip ──────────────────────────────────────────────────────────────
if (isset($_POST['create_trip'])) {
    $result = $tripController->addTrip($_POST, $currentUser->user_id);
    if ($result) {
        $_SESSION['message'] = "Trip Added Successfully";
        header("Location: index.php");
        exit;
    } else {
        $_SESSION['error'] = "Failed To Add Trip. Please try again.";
        header("Location: index.php");
        exit;
    }
}

$trips = $tripController->getAllTrips($currentUser->user_id);
if (!$trips) $trips = [];

$active_trip_id = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : ($trips[0]['trip_id'] ?? null);

// Verify the active trip actually belongs to this user's accessible trips
if ($active_trip_id) {
    $validIds = array_column($trips, 'trip_id');
    if (!in_array($active_trip_id, $validIds)) {
        $active_trip_id = $trips[0]['trip_id'] ?? null;
    }
}

$activeTrip = null;
if ($active_trip_id) {
    foreach ($trips as $t) {
        if ($t['trip_id'] == $active_trip_id) {
            $activeTrip = $t;
            break;
        }
    }
}

// Budget stats for active trip
$total_spent      = 0;
$budget_limit     = 0;
$progress_percent = 0;
if ($activeTrip) {
    $total_spent      = 1250; // placeholder — replace with real expense query when expenses are wired up
    $budget_limit     = $activeTrip['budget'];
    $progress_percent = ($budget_limit > 0) ? min(($total_spent / $budget_limit) * 100, 100) : 0;
}

// ── Delete trip ───────────────────────────────────────────────────────────────
if (isset($_POST['delete_trip'])) {
    $trip_id_to_delete = $_POST['delete_trip_id'];

    if (!$roleController->isLeader($currentUser->user_id, $trip_id_to_delete)) {
        $_SESSION['error'] = "Access Denied: You are not the leader of this trip!";
        header("Location: index.php");
        exit;
    }

    $isDeleted = $tripController->delete($trip_id_to_delete, $currentUser->user_id);

    if ($isDeleted !== false) {
        $_SESSION['message'] = "Trip Deleted Successfully";
    } else {
        $_SESSION['error'] = "Failed to delete the trip. Please try again.";
    }
    header("Location: index.php");
    exit;
}

// ── Edit trip (load form) ────────────────────────────────────────────────────
$edit_trip = null;
if (isset($_GET['edit_id'])) {
    $edit_trip = $tripController->getTripById($_GET['edit_id']);
}

// ── Update trip ───────────────────────────────────────────────────────────────
if (isset($_POST['update_trip'])) {
    $result = $tripController->update($_POST, $_POST['trip_id'], $currentUser->user_id);
    if ($result) {
        $_SESSION['message'] = "Trip Updated Successfully";
        header("Location: index.php");
        exit;
    } else {
        $_SESSION['error'] = "Failed to update the trip. Please try again.";
        header("Location: index.php");
        exit;
    }
}

// ── Dynamic team snapshot — members of the active trip ───────────────────────
$teamMembers = $active_trip_id ? $memberController->getTripMembers($active_trip_id) : [];

// Avatar colour palette (same as members.php)
$avatarColors = ['#6366f1', '#8b5cf6', '#06b6d4', '#f43f5e', '#10b981', '#f59e0b', '#ef4444'];
function snapshotColor($index) {
    global $avatarColors;
    return $avatarColors[$index % count($avatarColors)];
}

$isOrganizer = $active_trip_id ? $roleController->isLeader($currentUser->user_id, $active_trip_id) : false;
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

    <!-- ── Active trip selector (interactive dropdown) ── -->
    <div class="sidebar__trip">
      <label class="field-label" for="trip-selector">Active trip</label>
      <select
        id="trip-selector"
        class="select select--full"
        onchange="window.location.href='index.php?trip_id=' + this.value"
      >
        <?php if (empty($trips)): ?>
          <option value="">No trips yet</option>
        <?php else: ?>
          <?php foreach ($trips as $t): ?>
            <option
              value="<?php echo (int)$t['trip_id']; ?>"
              <?php echo ($t['trip_id'] == $active_trip_id) ? 'selected' : ''; ?>
            >
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

    <div class="sidebar__footer">
      <div class="user-chip">
        <span class="avatar avatar--sm" style="background:#6366f1"><?php echo strtoupper(mb_substr($currentUser->name, 0, 1)); ?></span>
        <div>
          <div class="user-chip__name"><?php echo htmlspecialchars($currentUser->name); ?></div>
          <div class="user-chip__role"><?php echo $isOrganizer ? 'Organizer on this trip' : 'Member on this trip'; ?></div>
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
        <p class="muted topbar__session" style="margin:0.35rem 0 0;font-size:0.85rem;">Signed in as <?php echo htmlspecialchars($currentUser->name); ?></p>
      </div>
      <div class="topbar__actions">
        <a href="index.php#new_trip" class="btn btn--primary" style="text-decoration:none;">+ New trip</a>

        <?php if ($active_trip_id && $isOrganizer): ?>
          <a href="index.php?edit_id=<?php echo $active_trip_id; ?>&trip_id=<?php echo $active_trip_id; ?>#new_trip"
             class="btn btn--secondary" style="text-decoration:none;">Edit trip</a>

          <form method="POST" style="display:inline;"
                onsubmit="return confirm('Are you sure you want to delete the active trip?');">
            <input type="hidden" name="delete_trip_id" value="<?php echo $active_trip_id; ?>">
            <button type="submit" name="delete_trip" class="btn btn--danger">Delete</button>
          </form>
        <?php endif; ?>
      </div>
    </header>

    <div class="content">

      <!-- ── Flash messages (from V2) ──────────────────────────────────────── -->
      <?php if (isset($_SESSION['message'])): ?>
        <div style="background:#d1e7dd;color:#0f5132;padding:1rem;border-radius:8px;margin-bottom:1.5rem;border:1px solid #badbcc;">
          ✅ <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
        </div>
      <?php endif; ?>

      <?php if (isset($_SESSION['error'])): ?>
        <div style="background:#f8d7da;color:#842029;padding:1rem;border-radius:8px;margin-bottom:1.5rem;border:1px solid #f5c2c7;">
          ⚠️ <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
      <?php endif; ?>

      <div class="tabs">
        <button type="button" class="tab is-active">Overview</button>
        <button type="button" class="tab">All trips</button>
        <button type="button" class="tab">Members</button>
      </div>

      <!-- ── Overview cards (dynamic from V1) ──────────────────────────────── -->
      <div class="grid grid--3">
        <div class="card card--gradient">
          <div class="card__header">
            <h3 class="card__title">Trip window</h3>
            <span class="badge badge--info">Live</span>
          </div>
          <?php if ($activeTrip): ?>
            <p class="muted"><?php echo htmlspecialchars($activeTrip['start_date']); ?> → <?php echo htmlspecialchars($activeTrip['end_date']); ?></p>
            <p class="muted" style="margin-top:0.5rem;"><?php echo htmlspecialchars($activeTrip['trip_description'] ?? $activeTrip['description'] ?? '—'); ?></p>
          <?php else: ?>
            <p class="muted">No active trip selected.</p>
          <?php endif; ?>
        </div>

        <div class="card">
          <div class="card__header"><h3 class="card__title">Budget pulse</h3></div>
          <?php if ($activeTrip): ?>
            <p style="margin:0 0 0.5rem;font-size:0.9rem;">
              <strong>$<?php echo number_format($total_spent, 0); ?></strong>
              of <strong>$<?php echo number_format($budget_limit, 0); ?></strong>
            </p>
            <div class="progress"><div class="progress__bar" style="width:<?php echo $progress_percent; ?>%"></div></div>
            <p class="muted" style="margin-top:0.5rem;font-size:0.8rem;"><?php echo round($progress_percent); ?>% of budget used</p>
          <?php else: ?>
            <p class="muted">No trip selected.</p>
          <?php endif; ?>
        </div>

        <div class="card">
          <div class="card__header"><h3 class="card__title">Collaboration</h3></div>
          <?php
            $goingCount = count($teamMembers);
            $avatarRow  = array_slice($teamMembers, 0, 4);
          ?>
          <p class="muted" style="margin:0 0 0.5rem;"><?php echo $goingCount; ?> member<?php echo $goingCount !== 1 ? 's' : ''; ?> on this trip</p>
          <div class="avatar-row">
            <?php foreach ($avatarRow as $i => $m): ?>
              <span class="avatar avatar--sm"
                    style="background:<?php echo snapshotColor($i); ?>"
                    title="<?php echo htmlspecialchars($m['name'] ?? ''); ?>">
                <?php echo strtoupper(mb_substr($m['name'] ?? '?', 0, 1)); ?>
              </span>
            <?php endforeach; ?>
            <?php if (empty($avatarRow)): ?>
              <span class="muted" style="font-size:0.85rem;">No members yet</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ── Quick actions ──────────────────────────────────────────────────── -->
      <div class="card" style="margin-top:1rem;">
        <div class="card__header"><h3 class="card__title">Quick actions</h3></div>
        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
          <a href="members.php?trip_id=<?php echo $active_trip_id; ?>"  class="btn btn--secondary" style="text-decoration:none;">Team &amp; invites</a>
          <a href="itinerary.php?trip_id=<?php echo $active_trip_id; ?>" class="btn btn--secondary" style="text-decoration:none;">Open itinerary</a>
          <a href="voting.php?trip_id=<?php echo $active_trip_id; ?>"   class="btn btn--secondary" style="text-decoration:none;">Open voting</a>
          <a href="expenses.php?trip_id=<?php echo $active_trip_id; ?>" class="btn btn--secondary" style="text-decoration:none;">Log expense</a>
          <a href="chat.php?trip_id=<?php echo $active_trip_id; ?>"     class="btn btn--secondary" style="text-decoration:none;">Open chat</a>
        </div>
      </div>

      <!-- ── All trips (role-gated edit/delete from V1) ────────────────────── -->
      <h2 class="section-title" style="margin-top:2rem;">All trips</h2>
      <div class="grid grid--2">
        <?php if (!empty($trips)): ?>
          <?php foreach ($trips as $trip): ?>
            <div class="card">
              <div class="card__header">
                <h3 class="card__title"><?php echo htmlspecialchars($trip['trip_name']); ?></h3>
              </div>
              <p class="muted"><?php echo htmlspecialchars($trip['start_date'] ?? '—'); ?> → <?php echo htmlspecialchars($trip['end_date'] ?? '—'); ?></p>
              <div style="margin-top:0.75rem;display:flex;flex-wrap:wrap;gap:0.4rem;">
                <a href="index.php?trip_id=<?php echo $trip['trip_id']; ?>"
                   class="btn btn--sm btn--primary" style="text-decoration:none;text-align:center;">Open</a>

                <?php if ($roleController->isLeader($currentUser->user_id, $trip['trip_id'])): ?>
                  <a href="index.php?edit_id=<?php echo $trip['trip_id']; ?>&trip_id=<?php echo $trip['trip_id']; ?>#new_trip"
                     class="btn btn--sm btn--secondary" style="text-decoration:none;">Edit</a>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                    <input type="hidden" name="delete_trip_id" value="<?php echo $trip['trip_id']; ?>">
                    <button type="submit" name="delete_trip" class="btn btn--sm btn--danger">Delete</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="muted">No trips found. Create your first trip below!</p>
        <?php endif; ?>
      </div>

      <!-- ── Team snapshot (dynamic from V1) ──────────────────────────────── -->
      <div style="margin-top:2rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem;">
        <h2 class="section-title" style="margin:0;">
          Team snapshot
          <?php if ($activeTrip): ?>
            <span style="font-size:0.8rem;font-weight:400;color:var(--text-muted);">— <?php echo htmlspecialchars($activeTrip['trip_name']); ?></span>
          <?php endif; ?>
        </h2>
        <a href="members.php?trip_id=<?php echo $active_trip_id; ?>" class="btn btn--primary" style="text-decoration:none;">Open members panel</a>
      </div>

      <?php if (!empty($teamMembers)): ?>
        <div class="grid grid--2">
          <?php foreach ($teamMembers as $i => $member): ?>
            <?php
              $isSelf    = ((int)$member['user_id'] === (int)$currentUser->user_id);
              $isLeader  = ($member['role'] === 'leader');
              $roleLabel = $isLeader ? 'Organizer' : 'Member';
              $color     = snapshotColor($i);
              $initial   = strtoupper(mb_substr($member['name'] ?? '?', 0, 1));
            ?>
            <div class="card" style="padding:0.85rem;display:flex;align-items:center;gap:0.75rem;">
              <span class="avatar" style="background:<?php echo $color; ?>"><?php echo $initial; ?></span>
              <div style="flex:1;min-width:0;">
                <div style="font-weight:700;font-size:0.9rem;">
                  <?php echo htmlspecialchars($member['name'] ?? '—'); ?>
                  <?php if ($isSelf): ?><span class="badge badge--info" style="margin-left:0.3rem;">You</span><?php endif; ?>
                </div>
                <div class="muted" style="font-size:0.8rem;">
                  <?php echo $roleLabel; ?> · active
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="muted">No members found for this trip.</p>
      <?php endif; ?>

      <!-- ── New / Edit trip form ───────────────────────────────────────────── -->
      <h2 class="section-title" style="margin-top:2rem;" id="new_trip">
        <?php echo $edit_trip ? "Edit Trip: " . htmlspecialchars($edit_trip['trip_name']) : "New trip form"; ?>
      </h2>

      <form method="POST" class="card">
        <?php if ($edit_trip): ?>
          <input type="hidden" name="trip_id" value="<?php echo $edit_trip['trip_id']; ?>">
        <?php endif; ?>

        <div class="form-grid">
          <div class="form-row">
            <label for="f-name">Trip name</label>
            <input id="f-name" name="trip_name" class="input"
                   value="<?php echo $edit_trip ? htmlspecialchars($edit_trip['trip_name']) : 'Weekend Getaway'; ?>" required />
          </div>

          <div class="form-row">
            <label for="f-desc">Description</label>
            <textarea id="f-desc" name="description" class="textarea"><?php echo $edit_trip ? htmlspecialchars($edit_trip['trip_description'] ?? $edit_trip['description'] ?? '') : 'A fun trip with the team'; ?></textarea>
          </div>

          <div class="form-row">
            <label for="f-start">Start date</label>
            <input id="f-start" name="start_date" type="date" class="input"
                   value="<?php echo $edit_trip ? $edit_trip['start_date'] : '2025-08-01'; ?>" required />
          </div>

          <div class="form-row">
            <label for="f-end">End date</label>
            <input id="f-end" name="end_date" type="date" class="input"
                   value="<?php echo $edit_trip ? $edit_trip['end_date'] : '2025-08-05'; ?>" required />
          </div>

          <div class="form-row">
            <label for="f-budget">Trip budget</label>
            <input id="f-budget" name="budget" type="number" class="input"
                   value="<?php echo $edit_trip ? $edit_trip['budget'] : '3000'; ?>" />
          </div>

          <div class="form-row">
            <label for="f-currency">Base currency</label>
            <select id="f-currency" name="base_currency" class="input">
              <option value="USD" <?php echo ($edit_trip && $edit_trip['base_currency'] == 'USD') ? 'selected' : ''; ?>>USD</option>
              <option value="EUR" <?php echo ($edit_trip && $edit_trip['base_currency'] == 'EUR') ? 'selected' : ''; ?>>EUR</option>
              <option value="EGP" <?php echo ($edit_trip && $edit_trip['base_currency'] == 'EGP') ? 'selected' : ''; ?>>EGP</option>
            </select>
          </div>

          <div style="display:flex;gap:0.5rem;justify-content:flex-end;">
            <?php if ($edit_trip): ?>
              <a href="index.php?trip_id=<?php echo $active_trip_id; ?>" class="btn btn--secondary" style="text-decoration:none;">Cancel Edit</a>
              <button type="submit" name="update_trip" class="btn btn--primary">Update trip</button>
            <?php else: ?>
              <button type="submit" name="create_trip" class="btn btn--primary">Create trip</button>
            <?php endif; ?>
          </div>
        </div>
      </form>

    </div>
  </main>
</div>
<div id="modal-root" class="modal-root" aria-live="polite"></div>
<div id="toast-root" class="toast-root"></div>
</body>
</html>