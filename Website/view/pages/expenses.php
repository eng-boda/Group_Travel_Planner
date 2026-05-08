<?php
ob_start();
session_start();
require_once __DIR__ . '/../../controller/AuthController.php';
require_once __DIR__ . '/../../model/user.php';
require_once __DIR__ . '/../../controller/ExpenseController.php';
require_once __DIR__ . '/../../controller/TripController.php';
require_once __DIR__ . '/../../controller/NonCashController.php';

$auth = new AuthController();
$currentUser = $auth->getCurrentUser();
$user_id = $currentUser->user_id;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense']) && $currentUser) {
  $expenseController = new ExpenseController();
  $result = $expenseController->addExpense($_POST, $currentUser->user_id);

  if ($result) {
    $_SESSION['success_msg'] = "Expense Added Successfully";
    $tripId = isset($_POST['trip_id']) ? (int) $_POST['trip_id'] : 1;
    header("Location: expenses.php?trip_id=" . $tripId . "&added=1");
    exit();
  }

  $_SESSION['err_msg'] = "Failed to add expense";
  $tripId = isset($_POST['trip_id']) ? (int) $_POST['trip_id'] : 1;
  header("Location: expenses.php?trip_id=" . $tripId . "&error=1");
  exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_noncash']) && $currentUser) {

    $nonCashController = new NonCashController();

    $result = $nonCashController->addNonCash(
        $_POST,
        $_FILES['proof_file'],
        $currentUser->user_id
    );

    if ($result) {

        $_SESSION['success_msg'] = "Non-cash contribution added";

        header("Location: expenses.php?trip_id=" . $_POST['trip_id']);

        exit();
    }

    $_SESSION['err_msg'] = "Failed to add non-cash contribution";

    header("Location: expenses.php?trip_id=" . $_POST['trip_id']);

    exit();
}

    $tripController = new TripController();
$trips = $tripController->getAllTrips($currentUser->user_id);

$active_trip_id = isset($_GET['trip_id']) 
    ? (int)$_GET['trip_id'] 
    : ($trips[0]['trip_id'] ?? null);

$expenseController = new ExpenseController();
// Use ONE consistent name: $activeTripId
$activeTripId = $_GET['trip_id'] ?? null; 

$expenses = $activeTripId 
    ? $expenseController->getExpenses($activeTripId) // Changed from $active_trip_id
    : [];

$nonCashController = new NonCashController();
$nonCashContributions = $activeTripId
    ? $nonCashController->getNonCash($activeTripId)
    : [];    

$activeTrip = null;
if ($activeTripId) {
    foreach ($trips as $t) {
        if ($t['trip_id'] == $activeTripId) {
            $activeTrip = $t;
            break;
        }
    }
}
?>

<?php if (isset($_SESSION['success_msg'])): ?>
  <script>
    alert('<?php echo $_SESSION['success_msg']; ?>');
  </script>
  <?php unset($_SESSION['success_msg']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['err_msg'])): ?>
  <script>
    alert('<?php echo $_SESSION['err_msg']; ?>');
  </script>
  <?php unset($_SESSION['err_msg']); ?>
<?php endif; ?>


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
    <div class="sidebar__brand"><div class="logo-mark" aria-hidden="true">✈</div><div><div class="logo-text">TripSync</div><div class="logo-sub">Collaborative Planner</div></div></div>
    <div class="sidebar__trip">
    <label class="field-label">Active trip</label>
    <div class="select select--full" style="background: #f8f9fa; border-color: #e9ecef; cursor: default; color: #495057;">
        <?php 
            echo isset($activeTrip) ? htmlspecialchars($activeTrip['trip_name']) : 'No Active Trip'; 
        ?>
    </div>
    </div>
    <nav class="sidebar__nav" aria-label="Main navigation">
    <a href="index.php?trip_id=<?php echo $activeTripId; ?>" 
       class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'is-active' : ''; ?>" 
       style="text-decoration:none;color:inherit;">
       <span class="nav-item__icon">◉</span> Dashboard
    </a>

    <a href="members.php?trip_id=<?php echo $activeTripId; ?>" 
       class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'members.php') ? 'is-active' : ''; ?>" 
       style="text-decoration:none;color:inherit;">
       <span class="nav-item__icon">👥</span> Members
    </a>

    <a href="itinerary.php?trip_id=<?php echo $activeTripId; ?>" 
       class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'itinerary.php') ? 'is-active' : ''; ?>" 
       style="text-decoration:none;color:inherit;">
       <span class="nav-item__icon">◎</span> Itinerary
    </a>

    <a href="voting.php?trip_id=<?php echo $activeTripId; ?>" 
       class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'voting.php') ? 'is-active' : ''; ?>" 
       style="text-decoration:none;color:inherit;">
       <span class="nav-item__icon">◇</span> Voting
    </a>

    <a href="rsvp.php?trip_id=<?php echo $activeTripId; ?>" 
       class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'rsvp.php') ? 'is-active' : ''; ?>" 
       style="text-decoration:none;color:inherit;">
       <span class="nav-item__icon">✓</span> RSVP
    </a>

    <a href="expenses.php?trip_id=<?php echo $activeTripId; ?>" 
       class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'expenses.php') ? 'is-active' : ''; ?>" 
       style="text-decoration:none;color:inherit;">
       <span class="nav-item__icon">$</span> Expenses
    </a>

    <a href="chat.php?trip_id=<?php echo $activeTripId; ?>" 
       class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'chat.php') ? 'is-active' : ''; ?>" 
       style="text-decoration:none;color:inherit;">
       <span class="nav-item__icon">💬</span> Chat
    </a>

    <a href="documents.php?trip_id=<?php echo $activeTripId; ?>" 
       class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'documents.php') ? 'is-active' : ''; ?>" 
       style="text-decoration:none;color:inherit;">
       <span class="nav-item__icon">📄</span> Documents
    </a>

    <a href="checklist.php?trip_id=<?php echo $activeTripId; ?>" 
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
      <p class="muted topbar__session" style="margin:0.35rem 0 0;font-size:0.85rem;">
         Signed in as <?php echo htmlspecialchars($currentUser->name ?? 'Guest'); ?>
      </p>
      <div class="topbar__actions"></div>
    </header>
    <div class="content">
<div class="card" style="margin-bottom:1rem;border-left:4px solid var(--success);"><h3 class="card__title">Budget · Berlin workshop</h3><p class="muted" style="margin:0 0 0.5rem;font-size:0.85rem;">$2,450.00 of $8,000.00 · 31% used</p><div class="progress" style="height:10px;"><div class="progress__bar" style="width:31%;background:var(--success);"></div></div></div>
<div class="card" style="margin-bottom:1rem;border-left:4px solid var(--warning);"><h3 class="card__title">Budget · Lisbon offsite</h3><p class="muted" style="margin:0 0 0.5rem;font-size:0.85rem;">$3,800.00 of $5,000.00 · 76% used</p><div class="progress" style="height:10px;"><div class="progress__bar" style="width:76%;background:var(--warning);"></div></div></div>
<div class="card" style="margin-bottom:1rem;border-left:4px solid var(--danger);"><h3 class="card__title">Budget · Tokyo summit</h3><p class="muted" style="margin:0 0 0.5rem;font-size:0.85rem;">¥850,000 of ¥900,000 · 94% used</p><div class="progress" style="height:10px;"><div class="progress__bar" style="width:94%;background:var(--danger);"></div></div><div class="card" style="margin-top:0.75rem;padding:0.75rem;background:rgba(239,68,68,0.12);border:1px solid var(--danger);"><p style="margin:0;font-weight:600;">⚠️ Warning: You have exceeded 90% of your trip budget!</p><button type="button" class="btn btn--sm btn--secondary" style="margin-top:0.5rem;">Dismiss</button></div></div>

<div class="card" style="margin-bottom:1rem;"><h3 class="card__title">Category analytics</h3><p class="muted" style="font-size:0.85rem;margin:0.5rem 0;">Base: USD</p><div style="display:flex;flex-wrap:wrap;gap:0.35rem;margin-bottom:0.75rem;"><span class="badge badge--info" style="font-size:0.75rem;">Transport 35%</span><span class="badge badge--info" style="font-size:0.75rem;">Lodging 30%</span><span class="badge badge--info" style="font-size:0.75rem;">Food &amp; dining 25%</span><span class="badge badge--info" style="font-size:0.75rem;">Other 10%</span></div>
<div style="margin-bottom:0.6rem;"><div style="display:flex;justify-content:space-between;font-size:0.85rem;"><span>🚗 Transport</span><strong>$2,170.00</strong></div><div class="progress" style="height:8px;margin-top:0.25rem;"><div class="progress__bar" style="width:35%"></div></div></div>
<div style="margin-bottom:0.6rem;"><div style="display:flex;justify-content:space-between;font-size:0.85rem;"><span>🏨 Lodging</span><strong>$1,860.00</strong></div><div class="progress" style="height:8px;margin-top:0.25rem;"><div class="progress__bar" style="width:30%"></div></div></div>
<div style="margin-bottom:0.6rem;"><div style="display:flex;justify-content:space-between;font-size:0.85rem;"><span>🍽 Food &amp; dining</span><strong>$1,550.00</strong></div><div class="progress" style="height:8px;margin-top:0.25rem;"><div class="progress__bar" style="width:25%"></div></div></div>
<div style="margin-bottom:0.6rem;"><div style="display:flex;justify-content:space-between;font-size:0.85rem;"><span>📌 Other</span><strong>$620.00</strong></div><div class="progress" style="height:8px;margin-top:0.25rem;"><div class="progress__bar" style="width:10%"></div></div></div>
<p class="muted" style="font-size:0.8rem;margin:0.75rem 0 0;">Most: <strong>Transport</strong> · Least: <strong>Other</strong></p></div>

<div class="grid grid--3" style="margin-bottom:1rem;">
<div class="card card--gradient"><h3 class="card__title">Expenses</h3><p style="margin:0.5rem 0 0;font-size:1.5rem;font-weight:700;">12</p><p class="muted" style="margin:0.25rem 0 0;font-size:0.85rem;">Matching filters</p></div>
<div class="card"><h3 class="card__title">Total converted (net)</h3><p style="margin:0.5rem 0 0;font-size:1.25rem;font-weight:700;">$6,200.00</p><p class="muted" style="margin:0.25rem 0 0;font-size:0.85rem;">After refunds · per trip base.</p></div>
<div class="card"><h3 class="card__title">Quick tip</h3><p class="muted" style="margin:0.5rem 0 0;font-size:0.85rem;">Use tabs for Settle debts &amp; Settlement.</p></div>
</div>

<div class="card" style="margin-bottom:1rem;"><h3 class="card__title">Kitty (shared pool)</h3><div class="form-grid"><div class="form-row"><label>Trip</label><select name="trip_id" class="input" required>

<?php foreach ($trips as $trip): ?>

<option value="<?php echo $trip['trip_id']; ?>"
    <?php echo ($trip['trip_id'] == $activeTripId) ? 'selected' : ''; ?>>

    <?php echo htmlspecialchars($trip['trip_name']); ?>

</option>

<?php endforeach; ?>

</select></div><div class="form-row"><label>Contributor name</label><input class="input" value="Ali" /></div><div class="form-row"><label>Amount (trip base)</label><input type="number" class="input" value="500" /></div><div class="form-row"><button type="button" class="btn btn--primary">Log contribution</button></div></div><div style="margin-top:1rem;"><div style="margin-bottom:0.75rem;padding:0.75rem;background:rgba(99,102,241,0.06);border-radius:10px;"><strong>Berlin workshop</strong> · Balance: $1,200.00<ul class="list-plain" style="margin:0.35rem 0 0;font-size:0.85rem;"><li>Ali: $500.00</li><li>Sara: $400.00</li><li>Mona: $300.00</li></ul></div></div></div>

<div class="tabs"><button type="button" class="tab is-active">Expenses</button><button type="button" class="tab">Settle debts</button><button type="button" class="tab">Settlement</button></div>

<div class="card" style="margin-bottom:1rem;"><h3 class="card__title">Filter &amp; search</h3><div class="form-grid" style="margin-top:0.75rem;"><div class="form-row"><label>Trip</label><select class="input">
    <option value="">All trips</option>
    <?php foreach ($trips as $trip): ?>
    <option value="<?php echo $trip['trip_id']; ?>">
        <?php echo htmlspecialchars($trip['trip_name']); ?>
    </option>
    <?php endforeach; ?>
</select></div><div class="form-row"><label>Category</label><select class="input"><option value="">All categories</option><option>🚗 Transport</option><option>🏨 Lodging</option><option>🍽 Food &amp; dining</option><option>📌 Other</option></select></div><div class="form-row"><label>Search description</label><input type="search" class="input" /></div></div></div>

<div class="card" style="margin-bottom:1rem;"><h3 class="card__title">Add expense</h3><form method="POST" class="form-grid">
<div class="form-row">
<label>Trip</label>

<select name="trip_id" class="input" required>
    <option value="" disabled selected>Choose trip...</option>
    <?php foreach ($trips as $trip): ?>
    <option value="<?php echo $trip['trip_id']; ?>"
        <?php echo ($trip['trip_id'] == $activeTripId) ? 'selected' : ''; ?>>
        <?php echo htmlspecialchars($trip['trip_name']); ?>
    </option>
    <?php endforeach; ?>
</select>
</div>
<div class="form-row"><label>Description</label><input name="description" class="input" value="" required /></div>
<div class="form-row"><label>Original amount</label><input type="number" step="0.01" min="0" name="original_amount" class="input" value="" required /></div>
<div class="form-row"><label>Original currency</label><select name="original_currency" class="input" required><option value="" disabled selected>Choose currency...</option><option value="USD">USD</option><option value="EUR">EUR</option><option value="GBP">GBP</option><option value="JPY">JPY</option><option value="CAD">CAD</option><option value="EGP">EGP</option></select></div>
<div class="form-row"><label>Converted amount</label><input type="number" step="0.01" min="0" name="converted_amount" class="input" value="" /><p class="muted" style="font-size:0.75rem;margin:0.25rem 0 0;">Leave empty to use original amount.</p></div>
<div class="form-row">
  <label>Category</label>
  <select name="category_id" class="input" required>
    <option value="" disabled selected>Choose category...</option>
    <option value="1">🚗 Transport</option>
    <option value="2">🏨 Lodging</option>
    <option value="3">🍽 Food & Dining</option>
    <option value="4">📌 Other</option>
  </select>
</div>

<div class="form-row" style="display:flex;gap:0.5rem;flex-wrap:wrap;"><button type="submit" name="add_expense" class="btn btn--primary">Add expense</button></div>
</form></div>

<div class="card" style="margin-bottom:1rem;"><h3 class="card__title">Expenses (Trip <?php echo $activeTripId; ?>)</h3>
<?php if (!$expense): ?>
  <p class="muted" style="margin:0;">No expenses yet.</p>
<?php else: ?>
  <div style="overflow-x:auto;margin-top:0.75rem;">
    <table style="width:100%;font-size:0.85rem;">
      <thead>
        <tr>
          <th style="text-align:left;padding:0.5rem;">Description</th>
          <th style="text-align:left;padding:0.5rem;">Original</th>
          <th style="text-align:left;padding:0.5rem;">Converted</th>
          <th style="text-align:left;padding:0.5rem;">Category</th>
          <th style="text-align:left;padding:0.5rem;">Uploaded by</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($expense as $e): ?>
          <tr>
            <td style="padding:0.5rem;"><?php echo htmlspecialchars($e['description']); ?></td>
            <td style="padding:0.5rem;"><?php echo htmlspecialchars($e['original_currency']) . ' ' . htmlspecialchars($e['original_amount']); ?></td>
            <td style="padding:0.5rem;"><?php echo htmlspecialchars($e['converted_amount']); ?></td>
            <td style="padding:0.5rem;"><?php echo htmlspecialchars($e['category_id']); ?></td>
            <td style="padding:0.5rem;"><?php echo htmlspecialchars($e['uploaded_by']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</div>

<div class="card" style="margin-bottom:1rem;"><h3 class="card__title">Recurring expenses</h3><ul class="list-plain"><li style="margin-bottom:0.5rem;"><strong>Berlin workshop</strong> · Monthly · Next: 2025-08-01 · Remaining est.: 5 <button type="button" class="btn btn--sm btn--danger">Cancel recurring</button></li></ul></div>
<div class="card" style="margin-bottom:1rem;"><h3 class="card__title">Refunds summary</h3><ul class="list-plain"><li>rf_001 · expense exp_taxi: $15.00</li><li>rf_002 · expense exp_hotel: $120.00</li></ul></div>

<div class="card" style="margin-bottom:1rem;"><h3 class="card__title">Non-cash contributions</h3><form class="form-grid"><div class="form-row"><label>Trip</label><select name="trip_id" class="input" required>

<?php foreach ($trips as $trip): ?>

<option value="<?php echo $trip['trip_id']; ?>"
    <?php echo ($trip['trip_id'] == $activeTripId) ? 'selected' : ''; ?>>

    <?php echo htmlspecialchars($trip['trip_name']); ?>

</option> 

<?php endforeach; ?>
</select>
<form method="POST" enctype="multipart/form-data" class="form-grid">



<div class="form-row">
<label>Description</label>
<input name="description" class="input" required />
</div>

<div class="form-row">
<label>Estimated value</label>
<input type="number" step="0.01"
name="estimatedValue"
class="input"
required />
</div>

<div class="form-row">
<label>Proof file</label>
<input type="file"
name="proof_file"
class="input"
accept=".pdf,image/*" />
</div>

<div class="form-row">
<button type="submit"
name="add_noncash"
class="btn btn--primary">

Add non-cash

</button>
</div>

</form>
<h2 class="section-title" style="margin-top:2rem;">Settle debts</h2>
<div class="card" style="margin-bottom:0.75rem;"><h4 class="card__title" style="font-size:1rem;">Berlin workshop</h4><p class="muted" style="font-size:0.85rem;">Minimum transactions: 2</p><ul class="list-plain"><li>Sara pays Ali: $120.00</li><li>Mona pays Ali: $85.00</li></ul></div>
<div class="card" style="margin-bottom:0.75rem;"><h4 class="card__title" style="font-size:1rem;">Lisbon offsite</h4><p class="muted" style="font-size:0.85rem;">Minimum transactions: 1</p><ul class="list-plain"><li>Mona pays Sara: $210.00</li></ul></div>
<div class="card" style="margin-bottom:0.75rem;"><h4 class="card__title" style="font-size:1rem;">Tokyo summit</h4><p class="muted" style="font-size:0.85rem;">Minimum transactions: 0</p><p class="muted">All square.</p></div>

<h2 class="section-title" style="margin-top:2rem;">Settlement</h2>
<div class="card" style="margin-bottom:0.75rem;"><h4 class="card__title" style="font-size:1rem;">Berlin workshop</h4><div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.35rem;">Ali <span class="badge badge--confirmed">✅ Signed</span></div><div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.35rem;">Sara <span class="badge badge--confirmed">✅ Signed</span></div><div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.35rem;">Mona <button type="button" class="btn btn--sm btn--secondary">Sign off</button></div><div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.35rem;">Ahmed <button type="button" class="btn btn--sm btn--secondary">Sign off</button></div><button type="button" class="btn btn--sm btn--secondary" style="margin-top:0.5rem;">Reset settlement</button></div>
<div class="card" style="margin-bottom:0.75rem;"><h4 class="card__title" style="font-size:1rem;">Tokyo summit</h4><div class="badge badge--confirmed" style="display:block;margin:0.5rem 0;padding:0.5rem;">✅ Trip is Settled!</div><div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.35rem;">Ali <span class="badge badge--confirmed">✅ Signed</span></div><div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.35rem;">Sara <span class="badge badge--confirmed">✅ Signed</span></div><button type="button" class="btn btn--sm btn--secondary" style="margin-top:0.5rem;">Reset settlement</button></div>

<h2 class="section-title" style="margin-top:2rem;">Log refund form</h2>
<div class="card"><div class="form-grid"><div class="form-row"><label>Refund amount (USD)</label><input type="number" class="input" value="25" /></div><p class="muted" style="font-size:0.8rem;">Partial refunds allowed.</p><div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" class="btn btn--primary">Log refund</button></div></div></div>

<h2 class="section-title" style="margin-top:2rem;">Non-cash review form</h2>
<div class="card"><div class="form-grid"><div class="form-row"><label>Comment</label><textarea class="textarea">Looks good, approved.</textarea></div><div class="form-row"><label>Status</label><select class="input"><option>Pending</option><option selected>Approved</option><option>Rejected</option></select></div><div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" class="btn btn--primary">Save</button></div></div></div>
    </div></main></div>
<div id="modal-root" class="modal-root" aria-live="polite"></div>
<div id="toast-root" class="toast-root"></div>
</body></html>
