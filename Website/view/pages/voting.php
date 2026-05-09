<?php
session_start();
require_once __DIR__ . '/../../controller/AuthController.php';
require_once __DIR__ . '/../../model/user.php';
require_once __DIR__ . '/../../controller/TripController.php';
require_once __DIR__ . '/../../controller/VotingController.php';
require_once __DIR__ . '/../../controller/MemberController.php';

// Parse textarea lines into options array before any POST handler runs
if (isset($_POST['options_raw'])) {
    $_POST['options'] = array_filter(
        array_map('trim', explode("\n", $_POST['options_raw'])),
        fn($v) => $v !== ''
    );
}

$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    header('Location: ../Auth/login.php');
    exit;
}

$currentUser      = $auth->getCurrentUser();
$tripController   = new TripController();
$votingController = new VotingController();
$memberController = new MemberController();

$trips = $tripController->getAllTrips($currentUser->user_id);
if (!$trips) $trips = [];

$active_trip_id = isset($_GET['trip_id'])
    ? (int) $_GET['trip_id']
    : ($trips[0]['trip_id'] ?? null);

$activeTrip = null;
if ($active_trip_id) {
    foreach ($trips as $t) {
        if ($t['trip_id'] == $active_trip_id) { $activeTrip = $t; break; }
    }
}

$isOrganizer = $active_trip_id
    ? $memberController->isOrganizer($currentUser->user_id, $active_trip_id)
    : false;

$feedback      = '';
$feedback_type = 'success';

// ── POST: Create Poll ─────────────────────────────────────────────────────────
if (isset($_POST['create_poll']) && $active_trip_id) {
    $question = trim($_POST['question'] ?? '');
    $deadline = $_POST['deadline'] ?? date('Y-m-d', strtotime('+7 days'));
    $options  = $_POST['options'] ?? [];

    $result = $votingController->createPoll($active_trip_id, $question, $deadline, $options);
    if ($result['success']) {
        header("Location: voting.php?trip_id=$active_trip_id&msg=poll_created");
        exit;
    } else {
        $feedback      = $result['message'];
        $feedback_type = 'error';
    }
}

// ── POST: Cast Vote ───────────────────────────────────────────────────────────
if (isset($_POST['cast_vote']) && $active_trip_id) {
    $poll_id   = (int) ($_POST['poll_id']   ?? 0);
    $option_id = (int) ($_POST['option_id'] ?? 0);

    if ($poll_id && $option_id) {
        $result = $votingController->castVote(
            $poll_id, $option_id, $currentUser->user_id, $active_trip_id
        );
        if ($result['success']) {
            header("Location: voting.php?trip_id=$active_trip_id&msg=voted");
            exit;
        } else {
            $feedback      = $result['message'];
            $feedback_type = 'error';
        }
    }
}

// ── POST: Change Vote ─────────────────────────────────────────────────────────
if (isset($_POST['change_vote']) && $active_trip_id) {
    $poll_id   = (int) ($_POST['poll_id']   ?? 0);
    $option_id = (int) ($_POST['option_id'] ?? 0);

    if ($poll_id && $option_id) {
        $result = $votingController->changeVote($poll_id, $option_id, $currentUser->user_id);
        if ($result['success']) {
            header("Location: voting.php?trip_id=$active_trip_id&msg=vote_changed");
            exit;
        } else {
            $feedback      = $result['message'];
            $feedback_type = 'error';
        }
    }
}

// ── POST: Delete Poll (organizer only) ────────────────────────────────────────
if (isset($_POST['delete_poll']) && $active_trip_id && $isOrganizer) {
    $poll_id = (int) ($_POST['poll_id'] ?? 0);
    if ($poll_id) {
        $votingController->deletePoll($poll_id);
        header("Location: voting.php?trip_id=$active_trip_id&msg=poll_deleted");
        exit;
    }
}

// ── Load polls ────────────────────────────────────────────────────────────────
$pollsData = $active_trip_id
    ? $votingController->getPollsWithResults($active_trip_id, $currentUser->user_id)
    : [];

$msgMap = [
    'poll_created' => '✅ Poll created successfully!',
    'voted'        => '✅ Your vote has been recorded.',
    'vote_changed' => '✅ Your vote has been updated.',
    'poll_deleted' => '✅ Poll deleted.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Voting - TripSync</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../css/main.css" />
  <style>
    .poll-card           { margin-bottom: 1.25rem; }
    .poll-option-wrap    { margin-bottom: 1rem; }
    .poll-option__row    { display:flex; justify-content:space-between; align-items:center;
                           font-size:0.85rem; margin-bottom:0.25rem; }
    .poll-option__bar-wrap { height:8px; border-radius:999px;
                             background:rgba(15,23,42,.06); overflow:hidden; }
    .poll-option__bar    { height:100%; border-radius:999px;
                           background:linear-gradient(90deg,var(--accent-2),var(--accent));
                           transition:width .45s ease; }
    .voted-tag           { display:inline-block; background:#ede9fe; color:#6366f1;
                           font-size:.72rem; font-weight:700; padding:2px 8px;
                           border-radius:20px; margin-left:.4rem; vertical-align:middle; }
    .organizer-note      { font-size:.75rem; color:var(--text-muted); margin:.3rem 0 0;
                           display:flex; align-items:center; gap:.3rem; flex-wrap:wrap; }
    .organizer-badge     { display:inline-block; background:#fef3c7; color:#92400e;
                           font-size:.7rem; font-weight:600; padding:1px 7px;
                           border-radius:20px; }
    .action-row          { display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.45rem; }
    .feedback            { padding:.75rem 1rem; border-radius:10px; margin-bottom:1rem;
                           font-weight:600; font-size:.9rem; }
    .feedback--success   { background:rgba(16,185,129,.12); color:#047857; border:1px solid #6ee7b7; }
    .feedback--error     { background:rgba(239,68,68,.1);   color:#b91c1c; border:1px solid #fca5a5; }
  </style>
</head>
<body>
<div id="app" class="app">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar__brand">
      <div class="logo-mark" aria-hidden="true">✈</div>
      <div><div class="logo-text">TripSync</div><div class="logo-sub">Collaborative Planner</div></div>
    </div>
    <div class="sidebar__trip">
      <label class="field-label">Active trip</label>
      <div class="select select--full" style="background:#f8f9fa;border-color:#e9ecef;cursor:default;color:#495057;">
        <?php echo isset($activeTrip) ? htmlspecialchars($activeTrip['trip_name']) : 'No Active Trip'; ?>
      </div>
    </div>
    <nav class="sidebar__nav" aria-label="Main navigation">
      <?php
        $pages = [
          'index.php'     => ['◉', 'Dashboard'],
          'members.php'   => ['👥', 'Members'],
          'itinerary.php' => ['◎', 'Itinerary'],
          'voting.php'    => ['◇', 'Voting'],
          'rsvp.php'      => ['✓', 'RSVP'],
          'expenses.php'  => ['$', 'Expenses'],
          'documents.php' => ['📄', 'Documents'],
          'checklist.php' => ['☑', 'Checklist'],
        ];
        $current = basename($_SERVER['PHP_SELF']);
        foreach ($pages as $file => [$icon, $label]):
      ?>
        <a href="<?php echo $file; ?>?trip_id=<?php echo $active_trip_id; ?>"
           class="nav-item <?php echo ($current === $file) ? 'is-active' : ''; ?>"
           style="text-decoration:none;color:inherit;">
          <span class="nav-item__icon"><?php echo $icon; ?></span> <?php echo $label; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar__footer">
      <div class="user-chip">
        <span class="avatar avatar--sm" style="background:#6366f1">
          <?php echo strtoupper(mb_substr($currentUser->name, 0, 1)); ?>
        </span>
        <div>
          <div class="user-chip__name"><?php echo htmlspecialchars($currentUser->name); ?></div>
          <div class="user-chip__role">
            <?php echo $isOrganizer ? 'Organizer on this trip' : 'Member on this trip'; ?>
          </div>
        </div>
      </div>
      <a href="../Auth/logout.php" class="btn btn--ghost btn--sm"
         style="width:100%;margin-top:.5rem;text-align:center;text-decoration:none;">Log out</a>
    </div>
  </aside>

  <div class="sidebar-backdrop" aria-hidden="true"></div>

  <main class="main">
    <header class="topbar">
      <button type="button" class="btn btn--icon mobile-only" aria-label="Open menu">☰</button>
      <div class="topbar__titles">
        <p class="eyebrow">Decisions</p>
        <h1 class="topbar__title">Voting</h1>
        <p class="muted" style="margin:.35rem 0 0;font-size:.85rem;">
          Signed in as <?php echo htmlspecialchars($currentUser->name); ?>
        </p>
      </div>
    </header>

    <div class="content">

      <!-- Flash messages -->
      <?php if (isset($_GET['msg']) && isset($msgMap[$_GET['msg']])): ?>
        <div class="feedback feedback--success"><?php echo $msgMap[$_GET['msg']]; ?></div>
      <?php endif; ?>
      <?php if ($feedback): ?>
        <div class="feedback feedback--<?php echo $feedback_type; ?>">
          <?php echo htmlspecialchars($feedback); ?>
        </div>
      <?php endif; ?>

      <?php if (!$activeTrip): ?>
        <div class="card"><p class="muted">No trip selected. <a href="index.php">Go to dashboard.</a></p></div>
      <?php else: ?>

      <!-- ── Active polls ─────────────────────────────────────────────────── -->
      <?php if (empty($pollsData)): ?>
        <div class="card empty">
          <div class="empty__icon">◇</div>
          <p>No polls yet. Create the first one below!</p>
        </div>
      <?php endif; ?>

      <?php foreach ($pollsData as $pd):
        $poll               = $pd['poll'];
        $results            = $pd['results'];
        $grand_total_weight = (int) $pd['grand_total_weight']; // drives % bar
        $total_votes        = (int) $pd['total_votes'];        // shown in header
        $user_voted         = $pd['user_voted'];               // option_id or null
        $poll_id            = (int) $poll['poll_id'];
        $deadline           = $poll['deadline'];
        $is_expired         = (strtotime($deadline) < strtotime(date('Y-m-d')));
      ?>
      <div class="card poll-card">

        <!-- Poll header -->
        <div class="card__header">
          <div>
            <h3 class="card__title"><?php echo htmlspecialchars($poll['question']); ?></h3>
            <p class="muted" style="font-size:.78rem;margin:.2rem 0 0;">
              Deadline: <?php echo htmlspecialchars($deadline); ?>
              <?php if ($is_expired): ?>
                <span style="color:var(--danger);font-weight:600;margin-left:.4rem;">· Closed</span>
              <?php endif; ?>
              · <?php echo $total_votes; ?> vote<?php echo $total_votes !== 1 ? 's' : ''; ?>
            </p>
          </div>
          <?php if ($isOrganizer): ?>
            <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this poll?');">
              <input type="hidden" name="poll_id" value="<?php echo $poll_id; ?>" />
              <button type="submit" name="delete_poll" class="btn btn--sm btn--danger">Delete</button>
            </form>
          <?php endif; ?>
        </div>

        <!-- Options -->
        <?php foreach ($results as $opt):
          $oid        = (int)   $opt['option_id'];
          $votes      = (int)   $opt['vote_count'];
          $opt_weight = (int)   $opt['total_weight'];
          $org_count  = (int)   $opt['organizer_count'];
          $org_names  =         $opt['organizer_names'];
          // % bar driven by weighted score so organizer vote counts double
          $pct        = $grand_total_weight > 0 ? round(($opt_weight / $grand_total_weight) * 100) : 0;
          $is_my      = ($user_voted === $oid);
        ?>
        <div class="poll-option-wrap">

          <!-- Label row -->
          <div class="poll-option__row">
            <span>
              <?php echo htmlspecialchars($opt['option_text']); ?>
              <?php if ($is_my): ?>
                <span class="voted-tag">Your vote</span>
              <?php endif; ?>
            </span>
            <span style="font-weight:600;">
              <?php echo $pct; ?>%
              <span class="muted" style="font-weight:400;">
                · <?php echo $votes; ?> vote<?php echo $votes !== 1 ? 's' : ''; ?>
              </span>
            </span>
          </div>

          <!-- Progress bar -->
          <div class="poll-option__bar-wrap">
            <div class="poll-option__bar" style="width:<?php echo $pct; ?>%"></div>
          </div>

          <!-- Organizer note (only if at least one organizer chose this option) -->
          <?php if ($org_count > 0): ?>
            <p class="organizer-note">
              <span>Organizer<?php echo $org_count > 1 ? 's' : ''; ?> who chose this:</span>
              <?php foreach ($org_names as $oname): ?>
                <span class="organizer-badge"><?php echo htmlspecialchars($oname); ?></span>
              <?php endforeach; ?>
            </p>
          <?php endif; ?>

          <!-- Action buttons -->
          <div class="action-row">
            <?php if (!$is_expired): ?>
              <?php if (!$user_voted): ?>
                <!-- First-time vote -->
                <form method="POST" style="margin:0;">
                  <input type="hidden" name="poll_id"   value="<?php echo $poll_id; ?>" />
                  <input type="hidden" name="option_id" value="<?php echo $oid; ?>" />
                  <button type="submit" name="cast_vote" class="btn btn--sm btn--secondary">Vote</button>
                </form>

              <?php elseif ($is_my): ?>
                <!-- Already voted here — change to a different option -->
                <!-- (no button on current choice; shown on other options below) -->

              <?php else: ?>
                <!-- Voted elsewhere — offer to switch to this option -->
                <form method="POST" style="margin:0;">
                  <input type="hidden" name="poll_id"   value="<?php echo $poll_id; ?>" />
                  <input type="hidden" name="option_id" value="<?php echo $oid; ?>" />
                  <button type="submit" name="change_vote"
                          class="btn btn--sm btn--secondary">Change to this</button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          </div>

        </div>
        <?php endforeach; ?>

      </div>
      <?php endforeach; ?>

      <!-- ── Create poll form ─────────────────────────────────────────────── -->
      <h2 class="section-title" style="margin-top:2rem;">Create new poll</h2>
      <div class="card">
        <form method="POST" class="form-grid">
          <div class="form-row">
            <label for="f-question">Question</label>
            <input id="f-question" name="question" class="input"
                   placeholder="e.g. Where should we eat on Day 1?" required />
          </div>

          <div class="form-row">
            <label for="f-deadline">Deadline</label>
            <input id="f-deadline" name="deadline" type="date" class="input"
                   value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required />
          </div>

          <div class="form-row">
            <label for="f-options">Options (one per line, min 2)</label>
            <textarea name="options_raw" id="f-options" class="textarea"
                      placeholder="Option A&#10;Option B&#10;Option C" rows="4" required></textarea>
          </div>

          <div style="display:flex;gap:.5rem;justify-content:flex-end;">
            <button type="submit" name="create_poll" class="btn btn--primary">Create poll</button>
          </div>
        </form>
      </div>

      <?php endif; // activeTrip ?>

    </div>
  </main>
</div>

<div id="modal-root" class="modal-root" aria-live="polite"></div>
<div id="toast-root" class="toast-root"></div>
</body>
</html>