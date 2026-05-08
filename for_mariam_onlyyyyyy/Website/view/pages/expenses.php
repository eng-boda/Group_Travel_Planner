<?php
session_start();
require_once __DIR__ . '/../../controller/AuthController.php';
require_once __DIR__ . '/../../controller/DBController.php';
require_once __DIR__ . '/../../model/user.php';
require_once __DIR__ . '/../../controller/TripController.php';
require_once __DIR__ . '/../../model/expense.php';
require_once __DIR__ . '/../../model/alert.php';

$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    header("Location: ../Auth/login.php");
    exit;
}

$currentUser = $auth->getCurrentUser();
$tripController = new TripController();
$trips = $tripController->getAllTrips($currentUser->user_id);

// Get active trip — check GET, then POST, then first trip
$active_trip_id = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] 
                : (isset($_POST['active_trip_id']) ? (int)$_POST['active_trip_id'] 
                : ($trips[0]['trip_id'] ?? null));
$activeTrip = null;
if ($active_trip_id && $trips) {
    foreach ($trips as $t) {
        if ($t['trip_id'] == $active_trip_id) {
            $activeTrip = $t;
            break;
        }
    }
}

$expenseModel = new Expense();
$alertModel = new Alert();

// ── Handle: Save Alert Threshold ──────────────────────────────────────────────
if (isset($_POST['save_alert']) && $active_trip_id) {
    $threshold = (float)$_POST['alert_threshold'];
    $message = "Warning: You have exceeded " . $threshold . "% of your trip budget!";
    $alertModel->createAlert($active_trip_id, $threshold, $message);
    header("Location: expenses.php?trip_id=$active_trip_id&saved=alert");
    exit;
}

// ── Handle: Add Expense ────────────────────────────────────────────────────────
if (isset($_POST['add_expense']) && $active_trip_id) {
    $db = new DBController();
    if ($db->openConnection()) {
        $desc         = $db->connection->real_escape_string(trim($_POST['description']));
        $orig_amount  = (float)$_POST['original_amount'];
        $orig_currency = $db->connection->real_escape_string($_POST['original_currency']);
        $category_id  = (int)$_POST['category_id'];
        $uploaded_by  = $currentUser->user_id;
        $converted_amount = $orig_amount; // 1:1 for now

        $query = "INSERT INTO expense (trip_id, category_id, original_currency, description, original_amount, converted_amount, uploaded_by)
                  VALUES ('$active_trip_id', '$category_id', '$orig_currency', '$desc', '$orig_amount', '$converted_amount', '$uploaded_by')";
        $db->insert($query);
        $db->closeConnection();
    }
    header("Location: expenses.php?trip_id=$active_trip_id&saved=expense");
    exit;
}

// ── Load data for active trip ──────────────────────────────────────────────────
$total_spent = 0;
$budget_limit = 0;
$progress_percent = 0;
$alert = null;
$expenses = [];
$categoryTotals = [];
$alertTriggered = false;

if ($activeTrip) {
    $budget_limit = (float)$activeTrip['budget'];
    $total_spent = $expenseModel->getTotalSpent($active_trip_id);
    $progress_percent = ($budget_limit > 0) ? min(($total_spent / $budget_limit) * 100, 100) : 0;
    $alert = $alertModel->getAlertByTrip($active_trip_id);
    $expenses = $expenseModel->getExpensesByTrip($active_trip_id);
    $categoryTotals = $expenseModel->getSpentByCategory($active_trip_id);

    // Check if threshold is exceeded
    if ($alert && $budget_limit > 0) {
        $currentPercent = ($total_spent / $budget_limit) * 100;
        $alertTriggered = $currentPercent >= (float)$alert['threshold'];
    }
}

// Progress bar color
$barColor = 'var(--success)';
if ($progress_percent >= 90) $barColor = 'var(--danger)';
elseif ($progress_percent >= 70) $barColor = 'var(--warning)';

// Load categories from DB
$db2 = new DBController();
$categories = [];
if ($db2->openConnection()) {
    $catResult = $db2->select("SELECT * FROM category");
    $categories = $catResult ? $catResult : [];
    $db2->closeConnection();
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
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../css/main.css" />
</head>
<body>
<div id="app" class="app">
  <aside class="sidebar">
    <div class="sidebar__brand">
      <div class="logo-mark" aria-hidden="true">✈</div>
      <div><div class="logo-text">TripSync</div><div class="logo-sub">Collaborative Planner</div></div>
    </div>
    <div class="sidebar__trip">
      <label class="field-label">Active trip</label>
      <select class="select select--full" onchange="window.location='expenses.php?trip_id='+this.value">
        <?php if ($trips): foreach ($trips as $t): ?>
          <option value="<?php echo $t['trip_id']; ?>" <?php echo ($t['trip_id'] == $active_trip_id) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($t['trip_name']); ?>
          </option>
        <?php endforeach; endif; ?>
      </select>
      <a href="index.php" class="btn btn--ghost btn--sm sidebar__new-trip" style="text-decoration:none;text-align:center;">+ New trip</a>
    </div>
    <nav class="sidebar__nav">
      <a href="index.php" class="nav-item" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">◉</span> Dashboard</a>
      <a href="members.php" class="nav-item" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">👥</span> Members</a>
      <a href="itinerary.php" class="nav-item" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">◎</span> Itinerary</a>
      <a href="voting.php" class="nav-item" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">◇</span> Voting</a>
      <a href="rsvp.php" class="nav-item" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">✓</span> RSVP</a>
      <a href="expenses.php" class="nav-item is-active" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">$</span> Expenses</a>
      <a href="chat.php" class="nav-item" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">💬</span> Chat</a>
      <a href="documents.php" class="nav-item" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">📄</span> Documents</a>
      <a href="checklist.php" class="nav-item" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">☑</span> Checklist</a>
    </nav>
    <div class="sidebar__footer">
      <div class="user-chip">
        <span class="avatar avatar--sm" style="background:#6366f1"><?php echo strtoupper(substr($currentUser->name, 0, 1)); ?></span>
        <div>
          <div class="user-chip__name"><?php echo htmlspecialchars($currentUser->name); ?></div>
          <div class="user-chip__role">Organizer on this trip</div>
        </div>
      </div>
      <a href="../Auth/logout.php" class="btn btn--ghost btn--sm" style="width:100%;margin-top:0.5rem;text-align:center;text-decoration:none;">Log out</a>
    </div>
  </aside>

  <div class="sidebar-backdrop" aria-hidden="true"></div>

  <main class="main">
    <header class="topbar">
      <button type="button" class="btn btn--icon mobile-only" aria-label="Open menu">☰</button>
      <div class="topbar__titles">
        <p class="eyebrow">Budget</p>
        <h1 class="topbar__title">Expenses</h1>
        <p class="muted topbar__session" style="margin:0.35rem 0 0;font-size:0.85rem;">
          Signed in as <?php echo htmlspecialchars($currentUser->name); ?>
        </p>
      </div>
      <div class="topbar__actions"></div>
    </header>

    <div class="content">

      <?php if (isset($_GET['saved'])): ?>
        <div class="card" style="margin-bottom:1rem;border-left:4px solid var(--success);padding:0.75rem;">
          <p style="margin:0;font-weight:600;">✅ <?php echo $_GET['saved'] === 'alert' ? 'Alert threshold saved!' : 'Expense added successfully!'; ?></p>
        </div>
      <?php endif; ?>

      <?php if (!$activeTrip): ?>
        <div class="card"><p class="muted">No trips found. <a href="index.php">Create a trip first.</a></p></div>
      <?php else: ?>

      <!-- ══ BUDGET PULSE (dynamic) ══════════════════════════════════════════ -->
      <div class="card" style="margin-bottom:1rem;border-left:4px solid <?php echo $barColor; ?>;">
        <h3 class="card__title">Budget · <?php echo htmlspecialchars($activeTrip['trip_name']); ?></h3>
        <p class="muted" style="margin:0 0 0.5rem;font-size:0.85rem;">
          $<?php echo number_format($total_spent, 2); ?> of 
          $<?php echo number_format($budget_limit, 2); ?> · 
          <?php echo round($progress_percent); ?>% used
        </p>
        <div class="progress" style="height:10px;">
          <div class="progress__bar" style="width:<?php echo $progress_percent; ?>%;background:<?php echo $barColor; ?>;"></div>
        </div>
      </div>

      <!-- ══ BUDGET THRESHOLD ALERT (Function 19) ════════════════════════════ -->
      <?php if ($alertTriggered): ?>
        <div class="card" style="margin-bottom:1rem;padding:0.75rem;background:rgba(239,68,68,0.12);border:1px solid var(--danger);">
          <p style="margin:0;font-weight:600;">⚠️ <?php echo htmlspecialchars($alert['message']); ?></p>
          <p class="muted" style="margin:0.25rem 0 0;font-size:0.85rem;">
            You've spent $<?php echo number_format($total_spent, 2); ?> which is 
            <?php echo round(($total_spent / $budget_limit) * 100); ?>% of your 
            $<?php echo number_format($budget_limit, 2); ?> budget.
          </p>
        </div>
      <?php endif; ?>

      <!-- ══ SET ALERT THRESHOLD ══════════════════════════════════════════════ -->
      <div class="card" style="margin-bottom:1rem;">
        <h3 class="card__title">⚙️ Budget Alert Settings</h3>
        <form method="POST" class="form-grid" style="margin-top:0.75rem;">
          <input type="hidden" name="active_trip_id" value="<?php echo $active_trip_id; ?>" />
          <div class="form-row">
            <label>Alert me when budget usage reaches (%)</label>
            <input type="number" name="alert_threshold" class="input" min="1" max="100"
              value="<?php echo $alert ? $alert['threshold'] : 80; ?>" required />
            <p class="muted" style="font-size:0.8rem;margin:0.25rem 0 0;">
              Current setting: <?php echo $alert ? $alert['threshold'] . '%' : 'Not set'; ?>
            </p>
          </div>
          <div style="display:flex;justify-content:flex-end;">
            <button type="submit" name="save_alert" class="btn btn--primary">Save Alert</button>
          </div>
        </form>
      </div>

      <!-- ══ CATEGORY ANALYTICS ═══════════════════════════════════════════════ -->
      <?php if (!empty($categoryTotals)): ?>
      <div class="card" style="margin-bottom:1rem;">
        <h3 class="card__title">Category analytics</h3>
        <p class="muted" style="font-size:0.85rem;margin:0.5rem 0;">Base: <?php echo htmlspecialchars($activeTrip['base_currency'] ?? 'USD'); ?></p>
        <?php foreach ($categoryTotals as $cat):
          $catPercent = $total_spent > 0 ? ($cat['total'] / $total_spent) * 100 : 0;
        ?>
          <div style="margin-bottom:0.6rem;">
            <div style="display:flex;justify-content:space-between;font-size:0.85rem;">
              <span><?php echo htmlspecialchars($cat['category_name'] ?? 'Uncategorized'); ?></span>
              <strong>$<?php echo number_format($cat['total'], 2); ?></strong>
            </div>
            <div class="progress" style="height:8px;margin-top:0.25rem;">
              <div class="progress__bar" style="width:<?php echo round($catPercent); ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- ══ SUMMARY CARDS ════════════════════════════════════════════════════ -->
      <div class="grid grid--3" style="margin-bottom:1rem;">
        <div class="card card--gradient">
          <h3 class="card__title">Expenses</h3>
          <p style="margin:0.5rem 0 0;font-size:1.5rem;font-weight:700;"><?php echo count($expenses); ?></p>
          <p class="muted" style="margin:0.25rem 0 0;font-size:0.85rem;">Total logged</p>
        </div>
        <div class="card">
          <h3 class="card__title">Total spent</h3>
          <p style="margin:0.5rem 0 0;font-size:1.25rem;font-weight:700;">$<?php echo number_format($total_spent, 2); ?></p>
          <p class="muted" style="margin:0.25rem 0 0;font-size:0.85rem;">Converted amount</p>
        </div>
        <div class="card">
          <h3 class="card__title">Remaining budget</h3>
          <p style="margin:0.5rem 0 0;font-size:1.25rem;font-weight:700;">
            $<?php echo number_format(max($budget_limit - $total_spent, 0), 2); ?>
          </p>
          <p class="muted" style="margin:0.25rem 0 0;font-size:0.85rem;">Left to spend</p>
        </div>
      </div>

      <!-- ══ ADD EXPENSE FORM (dynamic) ═══════════════════════════════════════ -->
      <div class="card" style="margin-bottom:1rem;">
        <h3 class="card__title">Add expense</h3>
        <form method="POST" class="form-grid">
          <input type="hidden" name="active_trip_id" value="<?php echo $active_trip_id; ?>" />
          <div class="form-row">
            <label>Description</label>
            <input name="description" class="input" placeholder="e.g. Taxi to venue" required />
          </div>
          <div class="form-row">
            <label>Original amount</label>
            <input type="number" name="original_amount" class="input" step="0.01" min="0" required />
          </div>
          <div class="form-row">
            <label>Original currency</label>
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
            <select name="category_id" class="input">
              <?php if ($categories): foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['category_id']; ?>">
                  <?php echo htmlspecialchars($cat['name']); ?>
                </option>
              <?php endforeach; else: ?>
                <option value="1">General</option>
              <?php endif; ?>
            </select>
          </div>
          <div style="display:flex;gap:0.5rem;justify-content:flex-end;">
            <button type="submit" name="add_expense" class="btn btn--primary">Add expense</button>
          </div>
        </form>
      </div>

      <!-- ══ EXPENSES LIST (dynamic) ══════════════════════════════════════════ -->
      <div class="card" style="margin-bottom:1rem;">
        <h3 class="card__title">All expenses</h3>
        <?php if (empty($expenses)): ?>
          <p class="muted">No expenses logged yet.</p>
        <?php else: ?>
          <div style="overflow-x:auto;margin-top:0.75rem;">
            <table style="width:100%;font-size:0.85rem;border-collapse:collapse;">
              <thead>
                <tr style="border-bottom:1px solid var(--border);">
                  <th style="padding:0.5rem;text-align:left;">Description</th>
                  <th style="padding:0.5rem;text-align:left;">Category</th>
                  <th style="padding:0.5rem;text-align:right;">Amount</th>
                  <th style="padding:0.5rem;text-align:left;">Currency</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($expenses as $exp): ?>
                  <tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:0.5rem;"><?php echo htmlspecialchars($exp['description']); ?></td>
                    <td style="padding:0.5rem;"><?php echo htmlspecialchars($exp['category_name'] ?? '—'); ?></td>
                    <td style="padding:0.5rem;text-align:right;">$<?php echo number_format($exp['converted_amount'], 2); ?></td>
                    <td style="padding:0.5rem;"><?php echo htmlspecialchars($exp['original_currency']); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="2" style="padding:0.5rem;font-weight:700;">Total</td>
                  <td style="padding:0.5rem;text-align:right;font-weight:700;">$<?php echo number_format($total_spent, 2); ?></td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <?php endif; // end activeTrip check ?>

    </div>
  </main>
</div>
<div id="modal-root" class="modal-root" aria-live="polite"></div>
<div id="toast-root" class="toast-root"></div>
</body>
</html>
