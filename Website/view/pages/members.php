<?php
require_once __DIR__ . '/../../controller/AuthController.php';
require_once __DIR__ . '/../../model/user.php';
require_once __DIR__ . '/../../controller/TripController.php';
require_once __DIR__ . '/../../controller/MemberController.php';

$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    header("Location: ../Auth/login.php");
    exit;
}

$currentUser = $auth->getCurrentUser();

$tripController   = new TripController();
$memberController = new MemberController();

$trips = $tripController->getAllTrips($currentUser->user_id);

$active_trip_id = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : ($trips[0]['trip_id'] ?? null);

$activeTrip = null;
if ($active_trip_id) {
    foreach ($trips as $t) {
        if ($t['trip_id'] == $active_trip_id) {
            $activeTrip = $t;
            break;
        }
    }
}

$feedback = '';
$feedback_type = 'success';

// ─── Handle POST actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $active_trip_id) {
    $action = $_POST['action'] ?? '';

    if ($action === 'invite') {
        $email  = trim($_POST['invite_email'] ?? '');
        $result = $memberController->invite($currentUser->user_id, $email, $active_trip_id);
        $feedback = $result['message'];
        $feedback_type = $result['success'] ? 'success' : 'error';

    } elseif ($action === 'promote') {
        $target = (int)($_POST['target_user_id'] ?? 0);
        $result = $memberController->promote($currentUser->user_id, $target, $active_trip_id);
        $feedback = $result['message'];
        $feedback_type = $result['success'] ? 'success' : 'error';

    } elseif ($action === 'demote') {
        $target = (int)($_POST['target_user_id'] ?? 0);
        $result = $memberController->demote($currentUser->user_id, $target, $active_trip_id);
        $feedback = $result['message'];
        $feedback_type = $result['success'] ? 'success' : 'error';

    } elseif ($action === 'remove') {
        $target = (int)($_POST['target_user_id'] ?? 0);
        $result = $memberController->remove($currentUser->user_id, $target, $active_trip_id);
        $feedback = $result['message'];
        $feedback_type = $result['success'] ? 'success' : 'error';

    } elseif ($action === 'cancel_invite') {
        $invite_id = (int)($_POST['invite_id'] ?? 0);
        $result = $memberController->cancelInvite($currentUser->user_id, $invite_id, $active_trip_id);
        $feedback = $result['message'];
        $feedback_type = $result['success'] ? 'success' : 'error';
    }
}

// ─── Load data ────────────────────────────────────────────────────────────────
$members        = $active_trip_id ? $memberController->getTripMembers($active_trip_id) : [];
$pendingInvites = $active_trip_id ? $memberController->getPendingInvites($active_trip_id) : [];
$isOrganizer    = $active_trip_id ? $memberController->isOrganizer($currentUser->user_id, $active_trip_id) : false;

$avatarColors = ['#6366f1', '#8b5cf6', '#06b6d4', '#f43f5e', '#10b981', '#f59e0b', '#ef4444'];

function getAvatarColor($index) {
    global $avatarColors;
    return $avatarColors[$index % count($avatarColors)];
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
  <style>
    .feedback-banner {
        padding: 0.75rem 1rem;
        border-radius: var(--radius-sm);
        font-size: 0.88rem;
        font-weight: 500;
        margin-bottom: 1rem;
    }
    .feedback-banner--success {
        background: rgba(16, 185, 129, 0.12);
        color: #047857;
        border: 1px solid rgba(16, 185, 129, 0.3);
    }
    .feedback-banner--error {
        background: rgba(239, 68, 68, 0.1);
        color: #b91c1c;
        border: 1px solid rgba(239, 68, 68, 0.3);
    }
    .btn:disabled,
    .btn[disabled] {
        opacity: 0.4;
        cursor: not-allowed;
        pointer-events: none;
    }
  </style>
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

    <!-- FIXED: interactive trip selector matching expenses.php -->
    <div class="sidebar__trip">
      <label class="field-label">Active trip</label>
      <select class="select select--full"
              onchange="window.location.href='members.php?trip_id=' + this.value">
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
        <p class="eyebrow">Team</p>
        <h1 class="topbar__title">Members</h1>
        <p class="muted topbar__session" style="margin:0.35rem 0 0;font-size:0.85rem;">Signed in as <?php echo htmlspecialchars($currentUser->name); ?></p>
      </div>
      <div class="topbar__actions"></div>
    </header>
    <div class="content">

      <?php if ($feedback): ?>
        <div class="feedback-banner feedback-banner--<?php echo $feedback_type; ?>">
          <?php echo htmlspecialchars($feedback); ?>
        </div>
      <?php endif; ?>

      <!-- Invite section -->
      <div class="members-hero card card--gradient">
        <div class="members-hero__text">
          <h2 class="section-title" style="margin:0 0 0.35rem;">Team &amp; access</h2>
          <p class="muted" style="margin:0;">
            <?php echo $isOrganizer ? 'Organizers manage invites, roles, and trip settings.' : 'Only organizers can manage invites and roles.'; ?>
          </p>
        </div>
        <form method="POST" action="members.php?trip_id=<?php echo $active_trip_id; ?>" class="members-invite">
          <input type="hidden" name="action" value="invite" />
          <input
            type="email"
            name="invite_email"
            class="input"
            placeholder="Email address to invite"
            <?php echo !$isOrganizer ? 'disabled' : ''; ?>
            required
          />
          <button
            type="submit"
            class="btn btn--primary"
            <?php echo !$isOrganizer ? 'disabled' : ''; ?>
          >Invite</button>
        </form>
      </div>

      <!-- Members list -->
      <div class="grid grid--2" style="margin-top:1rem;">
        <?php foreach ($members as $i => $member):
            $isSelf      = ((int)$member['user_id'] === (int)$currentUser->user_id);
            $isLeader    = ($member['role'] === 'leader');
            $avatarColor = getAvatarColor($i);
            $initial     = strtoupper(mb_substr($member['name'] ?? '?', 0, 1));
            $roleBadge   = $isLeader ? 'Organizer' : 'Member';
            $badgeClass  = $isLeader ? 'badge--confirmed' : 'badge--info';
            $cardClass   = ($isSelf && $isLeader) ? 'member-card card member-card--organizer' : 'member-card card';
        ?>
          <div class="<?php echo $cardClass; ?>">
            <div class="member-card__top">
              <span class="avatar" style="background:<?php echo $avatarColor; ?>"><?php echo $initial; ?></span>
              <div class="member-card__info">
                <div class="member-card__name">
                  <?php echo htmlspecialchars($member['name'] ?? '—'); ?>
                  <?php if ($isSelf): ?>
                    <span class="badge badge--info">You</span>
                  <?php endif; ?>
                </div>
                <div class="muted" style="font-size:0.82rem;"><?php echo htmlspecialchars($member['email']); ?></div>
                <div style="margin-top:0.35rem;display:flex;gap:0.35rem;flex-wrap:wrap;">
                  <span class="badge <?php echo $badgeClass; ?>"><?php echo $roleBadge; ?></span>
                  <span class="badge badge--draft">Active</span>
                </div>
              </div>
            </div>
            <div class="member-card__actions">
              <form method="POST" action="members.php?trip_id=<?php echo $active_trip_id; ?>" style="display:inline;">
                <input type="hidden" name="action" value="promote" />
                <input type="hidden" name="target_user_id" value="<?php echo $member['user_id']; ?>" />
                <button type="submit" class="btn btn--sm btn--secondary"
                  <?php echo (!$isOrganizer || $isLeader) ? 'disabled' : ''; ?>>Promote</button>
              </form>
              <form method="POST" action="members.php?trip_id=<?php echo $active_trip_id; ?>" style="display:inline;">
                <input type="hidden" name="action" value="demote" />
                <input type="hidden" name="target_user_id" value="<?php echo $member['user_id']; ?>" />
                <button type="submit" class="btn btn--sm btn--secondary"
                  <?php echo (!$isOrganizer || !$isLeader || $isSelf) ? 'disabled' : ''; ?>>Demote</button>
              </form>
              <form method="POST" action="members.php?trip_id=<?php echo $active_trip_id; ?>" style="display:inline;"
                    onsubmit="return confirm('Remove <?php echo htmlspecialchars($member['name'] ?? 'this member'); ?> from the trip?');">
                <input type="hidden" name="action" value="remove" />
                <input type="hidden" name="target_user_id" value="<?php echo $member['user_id']; ?>" />
                <button type="submit" class="btn btn--sm btn--danger"
                  <?php echo (!$isOrganizer || $isSelf) ? 'disabled' : ''; ?>>Remove</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>

        <?php if (empty($members)): ?>
          <p class="muted">No members found for this trip.</p>
        <?php endif; ?>
      </div>

      <!-- Pending invitations -->
      <div class="card" style="margin-top:1rem;">
        <h3 class="card__title">Pending invitations</h3>
        <?php if (!empty($pendingInvites)): ?>
          <ul class="list-plain">
            <?php foreach ($pendingInvites as $invite): ?>
              <li style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                <div>
                  <strong><?php echo htmlspecialchars($invite['email']); ?></strong><br/>
                  <span class="muted" style="font-size:0.78rem;">
                    Pending · sent <?php echo date('M j, Y, g:i A', strtotime($invite['sent_at'])); ?>
                  </span>
                </div>
                <form method="POST" action="members.php?trip_id=<?php echo $active_trip_id; ?>" style="display:inline;">
                  <input type="hidden" name="action" value="cancel_invite" />
                  <input type="hidden" name="invite_id" value="<?php echo $invite['invite_id']; ?>" />
                  <button type="submit" class="btn btn--sm btn--secondary"
                    <?php echo !$isOrganizer ? 'disabled' : ''; ?>>Cancel</button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="muted" style="font-size:0.9rem;">No pending invitations.</p>
        <?php endif; ?>
      </div>

    </div>
  </main>
</div>
<div id="modal-root" class="modal-root" aria-live="polite"></div>
<div id="toast-root" class="toast-root"></div>
</body>
</html>