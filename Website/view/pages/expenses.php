<?php
session_start();
require_once __DIR__ . '/../../controller/AuthController.php';
require_once __DIR__ . '/../../controller/DBController.php';
require_once __DIR__ . '/../../controller/EmailController.php';
require_once __DIR__ . '/../../model/user.php';
require_once __DIR__ . '/../../controller/TripController.php';
require_once __DIR__ . '/../../controller/MemberController.php';
require_once __DIR__ . '/../../model/expense.php';
require_once __DIR__ . '/../../model/alert.php';
require_once __DIR__ . '/../../model/split.php';
require_once __DIR__ . '/../../model/kitty.php';
require_once __DIR__ . '/../../model/settlement.php';

// ── Auth ──────────────────────────────────────────────────────────────────────
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    header("Location: ../Auth/login.php");
    exit;
}
$currentUser    = $auth->getCurrentUser();
$tripController = new TripController();
$trips          = $tripController->getAllTrips($currentUser->user_id);
if (!$trips) $trips = [];

// ── Active trip ───────────────────────────────────────────────────────────────
$active_trip_id = isset($_GET['trip_id'])        ? (int)$_GET['trip_id']
                : (isset($_POST['active_trip_id']) ? (int)$_POST['active_trip_id']
                : ($trips[0]['trip_id'] ?? null));

$activeTrip = null;
if ($active_trip_id && $trips) {
    foreach ($trips as $t) {
        if ($t['trip_id'] == $active_trip_id) { $activeTrip = $t; break; }
    }
}

// ── Models ────────────────────────────────────────────────────────────────────
$expenseModel    = new Expense();
$alertModel      = new Alert();
$splitModel      = new Split();
$kittyModel      = new Kitty();
$settlementModel = new Settlement();
$emailCtrl       = new EmailController();
$memberCtrl      = new MemberController();

$feedback      = '';
$feedback_type = 'success';

// ═════════════════════════════════════════════════════════════════════════════
//  POST HANDLERS
// ═════════════════════════════════════════════════════════════════════════════

// ── 1. Add Expense ─────────────────────────────────────────────────────────
if (isset($_POST['add_expense']) && $active_trip_id) {
    $db = new DBController();
    if ($db->openConnection()) {
        $category_id      = (int)$_POST['category_id'];
        $desc             = $db->connection->real_escape_string(trim($_POST['description']));
        $orig_amount      = (float)$_POST['original_amount'];
        $orig_currency    = $db->connection->real_escape_string($_POST['original_currency']);
        $uploaded_by      = $currentUser->user_id;
        $converted_amount = $orig_amount;

        $validCat = $db->select("SELECT category_id FROM category WHERE category_id = $category_id");
        if (!$validCat) {
            $feedback = "Invalid category selected."; $feedback_type = 'error';
        } else {
            $q = "INSERT INTO expense (trip_id, category_id, original_currency, description,
                                       original_amount, converted_amount, uploaded_by)
                  VALUES ($active_trip_id, $category_id, '$orig_currency', '$desc',
                          $orig_amount, $converted_amount, $uploaded_by)";
            $newExpenseId = $db->insert($q);
            $db->closeConnection();

            // Save uneven split if members selected
            if ($newExpenseId && !empty($_POST['split_members'])) {
                $members   = $_POST['split_members'];
                $amounts   = $_POST['split_amounts'];
                $splitData = [];
                $total     = array_sum($amounts);
                foreach ($members as $i => $uid) {
                    $amt = (float)($amounts[$i] ?? 0);
                    if ($amt <= 0) continue;
                    $splitData[] = [
                        'userId'      => (int)$uid,
                        'shareAmount' => $amt,
                        'percentage'  => $total > 0 ? round(($amt / $total) * 100, 2) : 0,
                    ];
                }
                if (!empty($splitData)) $splitModel->saveSplits((int)$newExpenseId, $splitData);
            }

            header("Location: expenses.php?trip_id=$active_trip_id&msg=expense_added");
            exit;
        }
        if ($db->connection) $db->closeConnection();
    }
}

// ── 2. Save Budget Alert ────────────────────────────────────────────────────
if (isset($_POST['save_alert']) && $active_trip_id) {
    $threshold = (float)$_POST['alert_threshold'];
    if ($threshold < 1 || $threshold > 100) {
        $feedback = "Threshold must be between 1 and 100."; $feedback_type = 'error';
    } else {
        $message = "Warning: You have exceeded " . $threshold . "% of your trip budget!";
        $result  = $alertModel->createAlert($active_trip_id, $threshold, $message);
        if ($result) {
            header("Location: expenses.php?trip_id=$active_trip_id&msg=alert_saved");
            exit;
        } else {
            $feedback = "Failed to save alert. Please try again."; $feedback_type = 'error';
        }
    }
}

// ── 3. Add Kitty Contribution ───────────────────────────────────────────────
if (isset($_POST['add_kitty_contribution']) && $active_trip_id) {
    $amount = (float)($_POST['kitty_amount'] ?? 0);
    $result = $kittyModel->addContribution($active_trip_id, $currentUser->user_id, $amount);
    if ($result === true) {
        header("Location: expenses.php?trip_id=$active_trip_id&msg=kitty_added");
        exit;
    } else {
        $feedback = is_string($result) ? $result : 'Failed to add contribution.';
        $feedback_type = 'error';
    }
}

// ── 4. Deduct from Kitty ────────────────────────────────────────────────────
if (isset($_POST['deduct_kitty']) && $active_trip_id) {
    $amount = (float)($_POST['deduct_amount'] ?? 0);
    $result = $kittyModel->deductFromKitty($active_trip_id, $amount);
    if ($result === true) {
        header("Location: expenses.php?trip_id=$active_trip_id&msg=kitty_deducted");
        exit;
    } else {
        $feedback = is_string($result) ? $result : 'Deduction failed.';
        $feedback_type = 'error';
    }
}

// ── 5. Initiate Settlement ──────────────────────────────────────────────────
if (isset($_POST['initiate_settlement']) && $active_trip_id) {
    $members   = $memberCtrl->getTripMembers($active_trip_id);
    $memberIds = array_column($members, 'user_id');
    $result    = $settlementModel->initiate($active_trip_id, $memberIds);

    if ($result === 'already_exists') {
        $feedback = 'A settlement is already pending for this trip.';
        $feedback_type = 'error';
    } elseif ($result && is_numeric($result)) {
        // Try to send emails (works if XAMPP has mail configured, silently skips if not)
        $netBalances = $splitModel->getNetBalances($active_trip_id);
        $balanceMap  = array_column($netBalances, 'netBalance', 'userId');
        $tripName    = $activeTrip['trip_name'] ?? 'Your Trip';
        $scheme      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host        = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $url         = "$scheme://$host/view/pages/expenses.php?trip_id=$active_trip_id";

        foreach ($members as $m) {
            if (empty($m['email'])) continue;
            $bal = $balanceMap[$m['user_id']] ?? 0;
            @$emailCtrl->sendSettlementRequest($m['email'], $m['name'], $tripName, $bal, $url);
        }
        header("Location: expenses.php?trip_id=$active_trip_id&msg=settlement_initiated");
        exit;
    } else {
        $feedback = 'Failed to initiate settlement.';
        $feedback_type = 'error';
    }
}

// ── 6. Approve Settlement ───────────────────────────────────────────────────
if (isset($_POST['approve_settlement']) && $active_trip_id) {
    $settlementId = (int)$_POST['settlement_id'];
    $result       = $settlementModel->approve($settlementId, $currentUser->user_id);

    if ($result === 'completed') {
        $members  = $memberCtrl->getTripMembers($active_trip_id);
        $tripName = $activeTrip['trip_name'] ?? 'Your Trip';
        @$emailCtrl->sendSettlementCompleted($members, $tripName);
        header("Location: expenses.php?trip_id=$active_trip_id&msg=settlement_completed");
        exit;
    } elseif ($result === 'approved') {
        header("Location: expenses.php?trip_id=$active_trip_id&msg=approval_saved");
        exit;
    } else {
        $feedback = 'Could not record your approval. (' . $result . ')';
        $feedback_type = 'error';
    }
}

// ── 7. Reject Settlement ────────────────────────────────────────────────────
if (isset($_POST['reject_settlement']) && $active_trip_id) {
    $settlementId = (int)$_POST['settlement_id'];
    $settlementModel->reject($settlementId, $currentUser->user_id);
    header("Location: expenses.php?trip_id=$active_trip_id&msg=settlement_rejected");
    exit;
}

// ── 8. Cancel Settlement ────────────────────────────────────────────────────
if (isset($_POST['cancel_settlement']) && $active_trip_id) {
    $settlementId = (int)$_POST['settlement_id'];
    $settlementModel->cancel($settlementId);
    header("Location: expenses.php?trip_id=$active_trip_id&msg=settlement_cancelled");
    exit;
}

// ── 9. Non-Cash Contribution ────────────────────────────────────────────────
$nonCashMessage = '';
$nonCashStatus  = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_non_cash'])) {
    $db   = new DBController();
    $conn = $db->openConnection() ? $db->connection : null;
    if ($conn) {
        $conn->autocommit(false);
        $t_id = (int)$_POST['trip_id'];
        $c_id = (int)$currentUser->user_id;
        $val  = (float)$_POST['estimated_value'];
        $desc = $conn->real_escape_string($_POST['description']);
        $file = $_FILES['proof_file']['name'] ?? null;
        $stmt = $conn->prepare("INSERT INTO non_cash_contribution
                                (trip_id, contributor_id, estimatedValue, description, proof_file, status)
                                VALUES (?, ?, ?, ?, ?, 'pending')");
        if ($stmt) {
            $stmt->bind_param("iidss", $t_id, $c_id, $val, $desc, $file);
            if ($stmt->execute()) {
                $conn->commit();
                $nonCashMessage = 'Contribution submitted successfully!';
                $nonCashStatus  = 'success';
            } else {
                $conn->rollback();
                $nonCashMessage = 'Database error: ' . $stmt->error;
                $nonCashStatus  = 'error';
            }
            $stmt->close();
        }
        $db->closeConnection();
    }
}

// ═════════════════════════════════════════════════════════════════════════════
//  LOAD DATA
// ═════════════════════════════════════════════════════════════════════════════
$total_spent      = 0;
$budget_limit     = 0;
$progress_percent = 0;
$alert            = null;
$expenses         = [];
$categoryTotals   = [];
$alertTriggered   = false;
$kitty            = null;
$kittyContribs    = [];
$settlement       = null;
$approvals        = [];
$netBalances      = [];
$members          = [];
$isLeader         = false;
$myApproval       = null;
$nameMap          = [];

if ($activeTrip) {
    $budget_limit     = (float)$activeTrip['budget'];
    $total_spent      = $expenseModel->getTotalSpent($active_trip_id);
    $progress_percent = $budget_limit > 0 ? min(($total_spent / $budget_limit) * 100, 100) : 0;
    $alert            = $alertModel->getAlertByTrip($active_trip_id);
    $expenses         = $expenseModel->getExpensesByTrip($active_trip_id);
    $categoryTotals   = $expenseModel->getSpentByCategory($active_trip_id);
    $members          = $memberCtrl->getTripMembers($active_trip_id);
    $nameMap          = array_column($members, 'name', 'user_id');

    // Determine if current user is the leader
    foreach ($members as $m) {
        if ((int)$m['user_id'] === (int)$currentUser->user_id && $m['role'] === 'leader') {
            $isLeader = true;
            break;
        }
    }
    // Fallback: trip creator is treated as leader if no roles row found
    if (!$isLeader && (int)($activeTrip['created_by'] ?? 0) === (int)$currentUser->user_id) {
        $isLeader = true;
    }

    $netBalances = $splitModel->getNetBalances($active_trip_id);
    $kitty       = $kittyModel->getOrCreateKitty($active_trip_id);
    $kittyContribs = $kittyModel->getContributions($active_trip_id);

    $settlement = $settlementModel->getByTrip($active_trip_id);
    if ($settlement) {
        $approvals = $settlementModel->getApprovals((int)$settlement['settlementId']);
        foreach ($approvals as $a) {
            if ((int)$a['user_id'] === (int)$currentUser->user_id) {
                $myApproval = $a;
                break;
            }
        }
    }

    // Check and fire budget alert
    if ($alert && $budget_limit > 0) {
        $currentPercent = ($total_spent / $budget_limit) * 100;
        $alertTriggered = $currentPercent >= (float)$alert['threshold'];

        if ($alertTriggered && $alertModel->shouldSendEmail($alert['last_email_sent'] ?? null)) {
            @$emailCtrl->sendBudgetAlert(
                $members, $activeTrip['trip_name'],
                $total_spent, $budget_limit,
                (float)$alert['threshold'], $currentPercent
            );
            $alertModel->markEmailSent($active_trip_id);
        }
    }
}

// Progress bar colour
$barColor = 'var(--success)';
if ($progress_percent >= 90)     $barColor = 'var(--danger)';
elseif ($progress_percent >= 70) $barColor = 'var(--warning)';

// Categories — loaded from DB
$db2 = new DBController();
$categories = [];
if ($db2->openConnection()) {
    $catResult  = $db2->select("SELECT * FROM category ORDER BY name");
    $categories = $catResult ?: [];
    $db2->closeConnection();
}

// If category table is empty, seed default categories automatically
if (empty($categories)) {
    $dbSeed = new DBController();
    if ($dbSeed->openConnection()) {
        $dbSeed->connection->query("INSERT IGNORE INTO category (name, icon) VALUES
            ('Transport', '🚗'),
            ('Lodging', '🏨'),
            ('Food & Dining', '🍽️'),
            ('Entertainment', '🎬'),
            ('Shopping', '🛒'),
            ('Fuel', '⛽'),
            ('Flight', '✈️'),
            ('Other', '📌')");
        $catResult  = $dbSeed->select("SELECT * FROM category ORDER BY name");
        $categories = $catResult ?: [];
        $dbSeed->closeConnection();
    }
}

// Message map
$msgMap = [
    'expense_added'        => '✅ Expense added successfully!',
    'alert_saved'          => '✅ Budget alert threshold saved!',
    'kitty_added'          => '✅ Kitty contribution recorded!',
    'kitty_deducted'       => '✅ Amount deducted from kitty!',
    'settlement_initiated' => '✅ Settlement started — all members can now sign off below.',
    'settlement_completed' => '🎉 All members have signed off — trip is now Settled!',
    'approval_saved'       => '✅ Your sign-off has been recorded.',
    'settlement_rejected'  => '⚠️ You rejected the settlement. The leader must restart.',
    'settlement_cancelled' => '✅ Settlement cancelled.',
];

// Category icon helper
function getCategoryIcon(string $name): string {
    $icons = [
        'transport' => '🚗', 'lodging' => '🏨', 'food' => '🍽️',
        'dining'    => '🍽️', 'other'   => '📌', 'fuel' => '⛽',
        'flight'    => '✈️', 'entertainment' => '🎬', 'shopping' => '🛒',
    ];
    $lower = strtolower($name);
    foreach ($icons as $k => $ic) { if (strpos($lower, $k) !== false) return $ic; }
    return '📦';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Expenses - TripSync</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../css/main.css" />
  <style>
    .split-row { display:flex; gap:.5rem; align-items:center; margin-bottom:.4rem; }
    .split-row select, .split-row input { flex:1; }
    .badge { display:inline-block; padding:2px 10px; border-radius:20px; font-size:.75rem; font-weight:600; }
    .badge--pending   { background:#fef3c7; color:#92400e; }
    .badge--approved  { background:#d1fae5; color:#065f46; }
    .badge--rejected  { background:#fee2e2; color:#991b1b; }
    .badge--completed { background:#dbeafe; color:#1e40af; }
    .section-tabs { display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1rem; }
    .section-tab  { padding:6px 16px; border-radius:20px; font-size:.82rem; font-weight:600;
                    cursor:pointer; border:2px solid var(--border); background:var(--surface);
                    color:var(--text-secondary); transition:all .15s; }
    .section-tab.active { border-color:#6366f1; background:#6366f1; color:#fff; }
    .section-panel { display:none; }
    .section-panel.active { display:block; }
    .alert-banner { padding:.85rem 1rem; border-radius:10px; display:flex; align-items:flex-start;
                    gap:.75rem; margin-bottom:1rem; }
    .alert-banner--warn { background:rgba(239,68,68,.1); border:1px solid var(--danger); }
    .alert-banner--info { background:rgba(99,102,241,.08); border:1px solid #a5b4fc; }
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
      <select class="select select--full" onchange="window.location='expenses.php?trip_id='+this.value">
        <?php foreach ($trips as $t): ?>
          <option value="<?php echo $t['trip_id']; ?>" <?php echo ($t['trip_id'] == $active_trip_id) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($t['trip_name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <a href="index.php" class="btn btn--ghost btn--sm" style="text-decoration:none;text-align:center;margin-top:.35rem;display:block;">+ New trip</a>
    </div>
    <nav class="sidebar__nav">
      <a href="index.php?trip_id=<?php echo $active_trip_id; ?>"     class="nav-item" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">◉</span> Dashboard</a>
      <a href="members.php?trip_id=<?php echo $active_trip_id; ?>"   class="nav-item" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">👥</span> Members</a>
      <a href="itinerary.php?trip_id=<?php echo $active_trip_id; ?>" class="nav-item" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">◎</span> Itinerary</a>
      <a href="voting.php?trip_id=<?php echo $active_trip_id; ?>"    class="nav-item" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">◇</span> Voting</a>
      <a href="rsvp.php?trip_id=<?php echo $active_trip_id; ?>"      class="nav-item" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">✓</span> RSVP</a>
      <a href="expenses.php?trip_id=<?php echo $active_trip_id; ?>"  class="nav-item is-active" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">$</span> Expenses</a>
      <a href="chat.php?trip_id=<?php echo $active_trip_id; ?>"      class="nav-item" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">💬</span> Chat</a>
      <a href="documents.php?trip_id=<?php echo $active_trip_id; ?>" class="nav-item" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">📄</span> Documents</a>
      <a href="checklist.php?trip_id=<?php echo $active_trip_id; ?>" class="nav-item" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">☑</span> Checklist</a>
    </nav>
    <div class="sidebar__footer">
      <div class="user-chip">
        <span class="avatar avatar--sm" style="background:#6366f1"><?php echo strtoupper(substr($currentUser->name, 0, 1)); ?></span>
        <div>
          <div class="user-chip__name"><?php echo htmlspecialchars($currentUser->name); ?></div>
          <div class="user-chip__role"><?php echo $isLeader ? 'Organizer' : 'Member'; ?> on this trip</div>
        </div>
      </div>
      <a href="../Auth/logout.php" class="btn btn--ghost btn--sm" style="width:100%;margin-top:.5rem;text-align:center;text-decoration:none;">Log out</a>
    </div>
  </aside>

  <div class="sidebar-backdrop" aria-hidden="true"></div>

  <main class="main">
    <header class="topbar">
      <button type="button" class="btn btn--icon mobile-only" aria-label="Open menu">☰</button>
      <div class="topbar__titles">
        <p class="eyebrow">Budget</p>
        <h1 class="topbar__title">Expenses</h1>
        <p class="muted" style="margin:.25rem 0 0;font-size:.85rem;">Signed in as <?php echo htmlspecialchars($currentUser->name); ?></p>
      </div>
    </header>

    <div class="content">

      <!-- Flash messages -->
      <?php if (isset($_GET['msg']) && isset($msgMap[$_GET['msg']])): ?>
        <div class="card" style="margin-bottom:1rem;border-left:4px solid var(--success);padding:.75rem;">
          <p style="margin:0;font-weight:600;"><?php echo $msgMap[$_GET['msg']]; ?></p>
        </div>
      <?php endif; ?>

      <?php if ($feedback): ?>
        <div class="card" style="margin-bottom:1rem;padding:.75rem;
             border-left:4px solid <?php echo $feedback_type === 'error' ? 'var(--danger)' : 'var(--success)'; ?>;">
          <p style="margin:0;font-weight:600;"><?php echo htmlspecialchars($feedback); ?></p>
        </div>
      <?php endif; ?>

      <?php if (!$activeTrip): ?>
        <div class="card"><p class="muted">No trips found. <a href="index.php">Create a trip first.</a></p></div>
      <?php else: ?>

      <!-- Budget bar -->
      <div class="card" style="margin-bottom:1rem;border-left:4px solid <?php echo $barColor; ?>;">
        <h3 class="card__title">Budget · <?php echo htmlspecialchars($activeTrip['trip_name']); ?></h3>
        <p class="muted" style="margin:0 0 .5rem;font-size:.85rem;">
          $<?php echo number_format($total_spent,2); ?> of
          $<?php echo number_format($budget_limit,2); ?> ·
          <?php echo round($progress_percent); ?>% used
        </p>
        <div class="progress" style="height:10px;">
          <div class="progress__bar" style="width:<?php echo $progress_percent; ?>%;background:<?php echo $barColor; ?>;"></div>
        </div>
      </div>

      <!-- Budget alert banner (shown in UI when triggered) -->
      <?php if ($alertTriggered): ?>
        <div class="alert-banner alert-banner--warn">
          <span style="font-size:1.4rem;">⚠️</span>
          <div>
            <strong><?php echo htmlspecialchars($alert['message']); ?></strong>
            <p style="margin:.25rem 0 0;font-size:.84rem;color:var(--text-secondary);">
              You've spent $<?php echo number_format($total_spent,2); ?> —
              <?php echo round(($total_spent/$budget_limit)*100); ?>% of your
              $<?php echo number_format($budget_limit,2); ?> budget.
            </p>
          </div>
        </div>
      <?php endif; ?>

      <!-- Budget alert settings -->
      <div class="card" style="margin-bottom:1rem;">
        <h3 class="card__title">⚙️ Budget Alert</h3>
        <p class="muted" style="font-size:.84rem;margin:.1rem 0 .75rem;">
          Get notified (on-screen + email if configured) when your group exceeds a spending threshold.
        </p>
        <form method="POST" class="form-grid">
          <input type="hidden" name="active_trip_id" value="<?php echo $active_trip_id; ?>" />
          <div class="form-row">
            <label>Alert me when budget usage reaches (%)</label>
            <input type="number" name="alert_threshold" class="input" min="1" max="100"
                   value="<?php echo $alert ? htmlspecialchars($alert['threshold']) : 80; ?>" required />
            <p class="muted" style="font-size:.8rem;margin:.25rem 0 0;">
              Current: <?php echo $alert ? $alert['threshold'].'%' : 'Not set'; ?>
            </p>
          </div>
          <div style="display:flex;justify-content:flex-end;">
            <button type="submit" name="save_alert" class="btn btn--primary">Save Alert</button>
          </div>
        </form>
      </div>

      <!-- Summary cards -->
      <div class="grid grid--3" style="margin-bottom:1rem;">
        <div class="card card--gradient">
          <h3 class="card__title">Expenses</h3>
          <p style="margin:.5rem 0 0;font-size:1.5rem;font-weight:700;"><?php echo count($expenses); ?></p>
          <p class="muted" style="margin:.25rem 0 0;font-size:.85rem;">Total logged</p>
        </div>
        <div class="card">
          <h3 class="card__title">Total spent</h3>
          <p style="margin:.5rem 0 0;font-size:1.25rem;font-weight:700;">$<?php echo number_format($total_spent,2); ?></p>
          <p class="muted" style="margin:.25rem 0 0;font-size:.85rem;">Converted amount</p>
        </div>
        <div class="card">
          <h3 class="card__title">Kitty balance</h3>
          <p style="margin:.5rem 0 0;font-size:1.25rem;font-weight:700;">
            $<?php echo $kitty ? number_format($kitty['totalBalance'],2) : '0.00'; ?>
          </p>
          <p class="muted" style="margin:.25rem 0 0;font-size:.85rem;">Group pool</p>
        </div>
      </div>

      <!-- Section tabs (properly named) -->
      <div class="section-tabs">
        <button class="section-tab active"  onclick="showTab('tab-expenses',this)">💳 Expenses</button>
        <button class="section-tab"         onclick="showTab('tab-split',this)">⚖️ Split Expenses</button>
        <button class="section-tab"         onclick="showTab('tab-kitty',this)">💰 Group Kitty</button>
        <button class="section-tab"         onclick="showTab('tab-settlement',this)">✅ Settlement</button>
        <button class="section-tab"         onclick="showTab('tab-noncash',this)">📦 Non-Cash</button>
        <button class="section-tab"         onclick="showTab('tab-analytics',this)">📊 Analytics</button>
      </div>

      <!-- ═══════════════════════ TAB: EXPENSES ═══════════════════════ -->
      <div id="tab-expenses" class="section-panel active">

        <div class="card" style="margin-bottom:1rem;">
          <h3 class="card__title">Add Expense</h3>
          <form method="POST" class="form-grid" id="expenseForm">
            <input type="hidden" name="active_trip_id" value="<?php echo $active_trip_id; ?>" />
            <div class="form-row">
              <label>Description</label>
              <input name="description" class="input" placeholder="e.g. Taxi to venue" required />
            </div>
            <div class="form-row">
              <label>Amount</label>
              <input type="number" name="original_amount" id="totalAmount" class="input"
                     step="0.01" min="0.01" required oninput="recalcSplits()" />
            </div>
            <div class="form-row">
              <label>Currency</label>
              <select name="original_currency" class="input">
                <option value="USD">US Dollar (USD)</option>
                <option value="EUR">Euro (EUR)</option>
                <option value="GBP">British Pound (GBP)</option>
                <option value="JPY">Japanese Yen (JPY)</option>
                <option value="EGP">Egyptian Pound (EGP)</option>
              </select>
            </div>
            <div class="form-row">
              <label>Category</label>
              <?php if (empty($categories)): ?>
                <p class="muted" style="font-size:.85rem;">⚠️ No categories found. Please add categories to the database.</p>
                <input type="hidden" name="category_id" value="0" />
              <?php else: ?>
                <select name="category_id" class="input" required>
                  <option value="" disabled selected>Choose category…</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['category_id']; ?>">
                      <?php echo getCategoryIcon($cat['name']) . ' ' . htmlspecialchars($cat['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
            </div>

            <!-- Uneven Split -->
            <div class="form-row">
              <label style="display:flex;align-items:center;gap:.5rem;">
                <input type="checkbox" id="enableSplit" onchange="toggleSplit(this.checked)" />
                Split this expense unevenly among members
              </label>
            </div>
            <div id="splitSection" style="display:none;">
              <p class="muted" style="font-size:.82rem;margin:.25rem 0 .5rem;">
                Enter each member's share. Amounts must add up to the total.
              </p>
              <div id="splitRows">
                <?php foreach ($members as $m): ?>
                <div class="split-row">
                  <label style="flex:1.5;font-size:.85rem;"><?php echo htmlspecialchars($m['name']); ?></label>
                  <input type="hidden" name="split_members[]" value="<?php echo $m['user_id']; ?>" />
                  <input type="number" name="split_amounts[]" class="input split-amt"
                         step="0.01" min="0" placeholder="0.00"
                         oninput="checkSplitTotal()" style="max-width:130px;" />
                  <span class="split-pct muted" style="font-size:.8rem;min-width:42px;text-align:right;">0%</span>
                </div>
                <?php endforeach; ?>
              </div>
              <p id="splitWarning" style="display:none;color:var(--danger);font-size:.82rem;margin:.3rem 0 0;">
                ⚠️ Split amounts don't match the total.
              </p>
            </div>

            <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:.5rem;">
              <button type="submit" name="add_expense" class="btn btn--primary">Add Expense</button>
            </div>
          </form>
        </div>

        <!-- Expenses list -->
        <div class="card">
          <h3 class="card__title">All Expenses</h3>
          <?php if (empty($expenses)): ?>
            <p class="muted">No expenses logged yet.</p>
          <?php else: ?>
          <div style="overflow-x:auto;margin-top:.75rem;">
            <table style="width:100%;font-size:.85rem;border-collapse:collapse;">
              <thead>
                <tr style="border-bottom:2px solid var(--border);">
                  <th style="padding:.5rem;text-align:left;">Description</th>
                  <th style="padding:.5rem;text-align:left;">Category</th>
                  <th style="padding:.5rem;text-align:right;">Amount</th>
                  <th style="padding:.5rem;text-align:left;">Currency</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($expenses as $exp): ?>
                <tr style="border-bottom:1px solid var(--border);">
                  <td style="padding:.5rem;"><?php echo htmlspecialchars($exp['description']); ?></td>
                  <td style="padding:.5rem;"><?php echo htmlspecialchars($exp['category_name'] ?? '—'); ?></td>
                  <td style="padding:.5rem;text-align:right;">$<?php echo number_format($exp['converted_amount'],2); ?></td>
                  <td style="padding:.5rem;"><?php echo htmlspecialchars($exp['original_currency']); ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="2" style="padding:.5rem;font-weight:700;">Total</td>
                  <td style="padding:.5rem;text-align:right;font-weight:700;">$<?php echo number_format($total_spent,2); ?></td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div><!-- /tab-expenses -->

      <!-- ═══════════════════════ TAB: SPLIT EXPENSES ═══════════════════════ -->
      <div id="tab-split" class="section-panel">
        <div class="card" style="margin-bottom:1rem;">
          <h3 class="card__title">⚖️ Member Balances</h3>
          <p class="muted" style="font-size:.85rem;margin:.3rem 0 .75rem;">
            Net balance = total paid by member − their share of all expenses.
            <strong>Positive</strong> = group owes them. <strong>Negative</strong> = they owe the group.
          </p>
          <?php if (empty($netBalances)): ?>
            <p class="muted">No split data yet. Add expenses and enable splitting when logging an expense.</p>
          <?php else: ?>
          <div style="overflow-x:auto;">
            <table style="width:100%;font-size:.85rem;border-collapse:collapse;">
              <thead>
                <tr style="border-bottom:2px solid var(--border);">
                  <th style="padding:.5rem;text-align:left;">Member</th>
                  <th style="padding:.5rem;text-align:right;">Paid</th>
                  <th style="padding:.5rem;text-align:right;">Owes</th>
                  <th style="padding:.5rem;text-align:right;">Net Balance</th>
                  <th style="padding:.5rem;text-align:left;">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($netBalances as $row):
                  $net  = (float)$row['netBalance'];
                  $cls  = $net > 0 ? 'color:#065f46' : ($net < 0 ? 'color:var(--danger)' : '');
                  $name = $nameMap[$row['userId']] ?? 'User #' . $row['userId'];
                ?>
                <tr style="border-bottom:1px solid var(--border);">
                  <td style="padding:.5rem;"><?php echo htmlspecialchars($name); ?></td>
                  <td style="padding:.5rem;text-align:right;">$<?php echo number_format($row['totalPaid'],2); ?></td>
                  <td style="padding:.5rem;text-align:right;">$<?php echo number_format($row['totalOwed'],2); ?></td>
                  <td style="padding:.5rem;text-align:right;font-weight:700;<?php echo $cls; ?>">
                    <?php echo ($net >= 0 ? '+' : '') . '$' . number_format(abs($net),2); ?>
                  </td>
                  <td style="padding:.5rem;">
                    <?php if ($net > 0): ?>
                      <span class="badge badge--approved">Gets back</span>
                    <?php elseif ($net < 0): ?>
                      <span class="badge badge--rejected">Owes</span>
                    <?php else: ?>
                      <span class="badge">Even</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>

        <div class="card">
          <h3 class="card__title">Expense Split Detail</h3>
          <?php if (empty($expenses)): ?>
            <p class="muted">No expenses yet.</p>
          <?php else: ?>
          <?php foreach ($expenses as $exp):
            $splits = $splitModel->getSplitsByExpense((int)$exp['expense_id']);
          ?>
          <div style="margin-bottom:1rem;padding:.75rem;background:var(--surface);border-radius:var(--radius-sm);">
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <strong><?php echo htmlspecialchars($exp['description']); ?></strong>
              <span>$<?php echo number_format($exp['converted_amount'],2); ?></span>
            </div>
            <?php if (empty($splits)): ?>
              <p class="muted" style="font-size:.82rem;margin:.4rem 0 0;">No split recorded (full amount to uploader).</p>
            <?php else: ?>
              <div style="margin-top:.5rem;font-size:.83rem;">
                <?php foreach ($splits as $s): ?>
                <div style="display:flex;justify-content:space-between;padding:.2rem 0;border-bottom:1px solid var(--border);">
                  <span><?php echo htmlspecialchars($s['userName'] ?? 'User'); ?></span>
                  <span>$<?php echo number_format($s['shareAmount'],2); ?>
                    <span class="muted">(<?php echo $s['percentage']; ?>%)</span>
                  </span>
                </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div><!-- /tab-split -->

      <!-- ═══════════════════════ TAB: GROUP KITTY ═══════════════════════ -->
      <div id="tab-kitty" class="section-panel">
        <div class="card" style="margin-bottom:1rem;">
          <h3 class="card__title">💰 Group Kitty</h3>
          <p class="muted" style="font-size:.85rem;margin:.3rem 0 1rem;">
            Members contribute upfront. The trip leader spends from the pool.
          </p>
          <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
            <div style="font-size:2rem;font-weight:800;color:#6366f1;">
              $<?php echo $kitty ? number_format($kitty['totalBalance'],2) : '0.00'; ?>
            </div>
            <div class="muted" style="font-size:.85rem;">Current kitty balance</div>
          </div>

          <form method="POST" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1rem;">
            <input type="hidden" name="active_trip_id" value="<?php echo $active_trip_id; ?>" />
            <div style="flex:1;min-width:180px;">
              <label class="field-label">My contribution amount</label>
              <input type="number" name="kitty_amount" class="input" step="0.01" min="0.01" placeholder="0.00" required />
            </div>
            <button type="submit" name="add_kitty_contribution" class="btn btn--primary">💰 Contribute</button>
          </form>

          <?php if ($isLeader): ?>
          <form method="POST" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end;
                                     padding:.75rem;background:rgba(99,102,241,.06);border-radius:8px;margin-bottom:1rem;">
            <input type="hidden" name="active_trip_id" value="<?php echo $active_trip_id; ?>" />
            <div style="flex:1;min-width:180px;">
              <label class="field-label">Deduct (spend from kitty)</label>
              <input type="number" name="deduct_amount" class="input" step="0.01" min="0.01" placeholder="0.00" required />
            </div>
            <button type="submit" name="deduct_kitty" class="btn btn--ghost"
                    style="border-color:#6366f1;color:#6366f1;"
                    onclick="return confirm('Deduct this amount from the kitty?')">
              Deduct (Leader)
            </button>
          </form>
          <?php endif; ?>

          <h4 style="margin:.5rem 0 .5rem;font-size:.9rem;">Contribution History</h4>
          <?php if (empty($kittyContribs)): ?>
            <p class="muted" style="font-size:.85rem;">No contributions yet.</p>
          <?php else: ?>
          <table style="width:100%;font-size:.85rem;border-collapse:collapse;">
            <thead>
              <tr style="border-bottom:2px solid var(--border);">
                <th style="padding:.4rem;text-align:left;">Member</th>
                <th style="padding:.4rem;text-align:right;">Amount</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($kittyContribs as $c): ?>
              <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:.4rem;"><?php echo htmlspecialchars($c['userName']); ?></td>
                <td style="padding:.4rem;text-align:right;">$<?php echo number_format($c['amount'],2); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div><!-- /tab-kitty -->

      <!-- ═══════════════════════ TAB: SETTLEMENT ═══════════════════════ -->
      <div id="tab-settlement" class="section-panel">
        <div class="card" style="margin-bottom:1rem;">
          <h3 class="card__title">✅ Settlement Approval</h3>
          <p class="muted" style="font-size:.85rem;margin:.3rem 0 1rem;">
            Once the leader starts a settlement, every member can <strong>approve or reject</strong> it
            directly here — no email needed. The trip is marked <strong>Settled</strong> only when
            everyone approves.
          </p>

          <?php if (!$settlement || $settlement['status'] === 'rejected'): ?>
            <!-- No active settlement -->
            <?php if ($settlement && $settlement['status'] === 'rejected'): ?>
              <div class="alert-banner alert-banner--warn" style="margin-bottom:1rem;">
                <span>⚠️</span>
                <div><strong>Settlement was rejected.</strong> As leader, you can restart it below.</div>
              </div>
            <?php endif; ?>

            <?php if (!empty($netBalances)): ?>
            <h4 style="margin:.5rem 0 .5rem;font-size:.9rem;">Final balances to settle</h4>
            <table style="width:100%;font-size:.84rem;border-collapse:collapse;margin-bottom:1rem;">
              <thead>
                <tr style="border-bottom:2px solid var(--border);">
                  <th style="padding:.4rem;text-align:left;">Member</th>
                  <th style="padding:.4rem;text-align:right;">Net Balance</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($netBalances as $row):
                  $net  = (float)$row['netBalance'];
                  $name = $nameMap[$row['userId']] ?? 'User #' . $row['userId'];
                  $clr  = $net >= 0 ? '#065f46' : 'var(--danger)';
                ?>
                <tr style="border-bottom:1px solid var(--border);">
                  <td style="padding:.4rem;"><?php echo htmlspecialchars($name); ?></td>
                  <td style="padding:.4rem;text-align:right;font-weight:700;color:<?php echo $clr; ?>">
                    <?php echo ($net >= 0 ? '+' : '') . '$' . number_format(abs($net),2); ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>

            <?php if ($isLeader): ?>
            <form method="POST">
              <input type="hidden" name="active_trip_id" value="<?php echo $active_trip_id; ?>" />
              <button type="submit" name="initiate_settlement" class="btn btn--primary"
                      onclick="return confirm('Start settlement? All members will be asked to sign off.')">
                🚀 Start Settlement
              </button>
            </form>
            <?php else: ?>
              <div class="alert-banner alert-banner--info">
                <span>ℹ️</span>
                <div>No active settlement. Ask the trip leader to start one.</div>
              </div>
            <?php endif; ?>

          <?php elseif ($settlement['status'] === 'completed'): ?>
            <!-- Completed -->
            <div style="padding:1.25rem;background:#d1fae5;border-radius:10px;text-align:center;">
              <div style="font-size:2.5rem;">🎉</div>
              <strong style="color:#065f46;font-size:1.1rem;">Trip is Settled!</strong>
              <p style="color:#065f46;margin:.3rem 0 0;font-size:.9rem;">All members have signed off. No further action needed.</p>
            </div>

          <?php else: ?>
            <!-- Pending settlement — sign-off panel -->
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem;">
              <span class="badge badge--pending" style="font-size:.85rem;padding:4px 14px;">⏳ Awaiting sign-offs</span>
              <?php if ($isLeader): ?>
              <form method="POST" style="margin:0;">
                <input type="hidden" name="active_trip_id" value="<?php echo $active_trip_id; ?>" />
                <input type="hidden" name="settlement_id"  value="<?php echo $settlement['settlementId']; ?>" />
                <button type="submit" name="cancel_settlement" class="btn btn--ghost btn--sm"
                        style="color:var(--danger);border-color:var(--danger);"
                        onclick="return confirm('Cancel this settlement?')">Cancel Settlement</button>
              </form>
              <?php endif; ?>
            </div>

            <!-- Sign-off status table -->
            <table style="width:100%;font-size:.85rem;border-collapse:collapse;margin-bottom:1rem;">
              <thead>
                <tr style="border-bottom:2px solid var(--border);">
                  <th style="padding:.5rem;text-align:left;">Member</th>
                  <th style="padding:.5rem;text-align:left;">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($approvals as $ap): ?>
                <tr style="border-bottom:1px solid var(--border);">
                  <td style="padding:.5rem;">
                    <?php echo htmlspecialchars($ap['userName']); ?>
                    <?php if ((int)$ap['user_id'] === (int)$currentUser->user_id): ?>
                      <span class="badge" style="background:#ede9fe;color:#6366f1;">You</span>
                    <?php endif; ?>
                  </td>
                  <td style="padding:.5rem;">
                    <span class="badge badge--<?php echo $ap['status']; ?>">
                      <?php echo ucfirst($ap['status']); ?>
                    </span>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>

            <!-- My action buttons — shown directly in UI, no email needed -->
            <?php if ($myApproval && $myApproval['status'] === 'pending'): ?>
            <div class="alert-banner alert-banner--info" style="margin-bottom:1rem;">
              <span>👆</span>
              <div>It's your turn to sign off on this settlement. Review the balances above then choose below.</div>
            </div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
              <form method="POST" style="margin:0;">
                <input type="hidden" name="active_trip_id" value="<?php echo $active_trip_id; ?>" />
                <input type="hidden" name="settlement_id"  value="<?php echo $settlement['settlementId']; ?>" />
                <button type="submit" name="approve_settlement" class="btn btn--primary">
                  ✅ I Approve — Sign Off
                </button>
              </form>
              <form method="POST" style="margin:0;">
                <input type="hidden" name="active_trip_id" value="<?php echo $active_trip_id; ?>" />
                <input type="hidden" name="settlement_id"  value="<?php echo $settlement['settlementId']; ?>" />
                <button type="submit" name="reject_settlement" class="btn btn--ghost"
                        style="color:var(--danger);border-color:var(--danger);"
                        onclick="return confirm('Reject the settlement? The leader will need to restart.')">
                  ❌ Reject
                </button>
              </form>
            </div>
            <?php elseif ($myApproval): ?>
              <p class="muted" style="font-size:.85rem;">
                You have already <strong><?php echo $myApproval['status']; ?></strong> this settlement.
              </p>
            <?php else: ?>
              <p class="muted" style="font-size:.85rem;">
                You are not listed in this settlement. Contact the trip leader.
              </p>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div><!-- /tab-settlement -->

      <!-- ═══════════════════════ TAB: NON-CASH ═══════════════════════ -->
      <div id="tab-noncash" class="section-panel">
        <?php if ($nonCashMessage): ?>
          <div class="card" style="margin-bottom:1rem;padding:.75rem;
               background:<?php echo $nonCashStatus==='success'?'rgba(34,197,94,.12)':'rgba(239,68,68,.12)'; ?>;
               border:1px solid <?php echo $nonCashStatus==='success'?'#22c55e':'var(--danger)'; ?>;">
            <p style="margin:0;font-weight:600;">
              <?php echo $nonCashStatus==='success'?'✅':'⚠️'; ?> <?php echo $nonCashMessage; ?>
            </p>
          </div>
        <?php endif; ?>
        <div class="card">
          <h3 class="card__title">📦 Non-Cash Contributions</h3>
          <p class="muted" style="font-size:.84rem;margin:.1rem 0 .75rem;">
            Record in-kind contributions like a car, equipment, or accommodation.
          </p>
          <form method="POST" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="trip_id" value="<?php echo $active_trip_id; ?>" />
            <div class="form-row">
              <label>Description</label>
              <input name="description" class="input" placeholder="e.g. Company van for transport" required />
            </div>
            <div class="form-row">
              <label>Contributor</label>
              <input class="input" value="<?php echo htmlspecialchars($currentUser->name); ?>" disabled />
            </div>
            <div class="form-row">
              <label>Estimated value (<?php echo $activeTrip['base_currency'] ?? 'USD'; ?>)</label>
              <input type="number" name="estimated_value" class="input" step="0.01" required />
            </div>
            <div class="form-row">
              <label>Proof file (Optional)</label>
              <input type="file" name="proof_file" class="input" accept=".pdf,image/*" />
            </div>
            <div style="display:flex;justify-content:flex-end;margin-top:.5rem;">
              <button type="submit" name="add_non_cash" class="btn btn--primary">Add Contribution</button>
            </div>
          </form>
        </div>
      </div><!-- /tab-noncash -->

      <!-- ═══════════════════════ TAB: ANALYTICS ═══════════════════════ -->
      <div id="tab-analytics" class="section-panel">
        <?php if (!empty($categoryTotals)): ?>
        <div class="card">
          <h3 class="card__title">📊 Category Analytics</h3>
          <p class="muted" style="font-size:.85rem;margin:.3rem 0 .75rem;">
            Base currency: <?php echo htmlspecialchars($activeTrip['base_currency'] ?? 'USD'); ?>
          </p>
          <?php foreach ($categoryTotals as $cat):
            $catPct = $total_spent > 0 ? ($cat['total'] / $total_spent) * 100 : 0;
            $icon   = getCategoryIcon($cat['category_name'] ?? 'Uncategorized');
          ?>
          <div style="margin-bottom:.6rem;">
            <div style="display:flex;justify-content:space-between;font-size:.85rem;">
              <span><?php echo $icon . ' ' . htmlspecialchars($cat['category_name'] ?? 'Uncategorized'); ?></span>
              <strong>$<?php echo number_format($cat['total'],2); ?></strong>
            </div>
            <div class="progress" style="height:8px;margin-top:.25rem;">
              <div class="progress__bar" style="width:<?php echo round($catPct); ?>%;"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
          <div class="card"><p class="muted">No expenses with categories to analyse yet.</p></div>
        <?php endif; ?>
      </div><!-- /tab-analytics -->

      <?php endif; // end activeTrip check ?>
    </div>
  </main>
</div>

<div id="modal-root" class="modal-root" aria-live="polite"></div>
<div id="toast-root" class="toast-root"></div>

<script>
function showTab(id, btn) {
    document.querySelectorAll('.section-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.section-tab').forEach(b => b.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    btn.classList.add('active');
}

function toggleSplit(show) {
    document.getElementById('splitSection').style.display = show ? 'block' : 'none';
    if (show) recalcSplits();
}

function recalcSplits() {
    const total     = parseFloat(document.getElementById('totalAmount').value) || 0;
    const amtInputs = document.querySelectorAll('.split-amt');
    if (amtInputs.length === 0) return;
    const share = total > 0 ? (total / amtInputs.length).toFixed(2) : 0;
    amtInputs.forEach(inp => { inp.value = share; });
    checkSplitTotal();
}

function checkSplitTotal() {
    const total = parseFloat(document.getElementById('totalAmount').value) || 0;
    const amts  = document.querySelectorAll('.split-amt');
    let sum = 0;
    amts.forEach(inp => { sum += parseFloat(inp.value) || 0; });
    sum = parseFloat(sum.toFixed(2));

    const warn = document.getElementById('splitWarning');
    if (warn) warn.style.display = (total > 0 && Math.abs(sum - total) > 0.01) ? 'block' : 'none';

    amts.forEach(inp => {
        const pct = total > 0 ? ((parseFloat(inp.value)||0) / total * 100).toFixed(1) : '0.0';
        const row = inp.closest('.split-row');
        if (row) row.querySelector('.split-pct').textContent = pct + '%';
    });
}

// Auto-open settlement tab if URL has msg related to settlement
(function(){
    const p = new URLSearchParams(window.location.search);
    const m = p.get('msg') || '';
    if (m.startsWith('settlement') || m === 'approval_saved') {
        const btn = document.querySelector('.section-tab:nth-child(4)');
        if (btn) showTab('tab-settlement', btn);
    }
})();
</script>
</body>
</html>
