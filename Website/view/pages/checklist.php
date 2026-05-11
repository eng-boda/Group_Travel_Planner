<?php
require_once __DIR__ . '/../../controller/AuthController.php';
require_once __DIR__ . '/../../model/user.php';
require_once __DIR__ . '/../../controller/TripController.php';
require_once __DIR__ . '/../../controller/ChecklistController.php';

$auth = new AuthController();
$currentUser = $auth->getCurrentUser();

$tripController = new TripController();
$checklistController = new ChecklistController();

$trips = $tripController->getAllTrips($currentUser->user_id);

$active_trip_id = isset($_GET['trip_id'])
    ? $_GET['trip_id']
    : ($trips[0]['trip_id'] ?? null);

$activeTrip = null;
if ($active_trip_id) {
    foreach ($trips as $t) {
        if ($t['trip_id'] == $active_trip_id) {
            $activeTrip = $t;
            break;
        }
    }
}

// Handle Add Item
if(isset($_POST['add_item'])) {
    $data = [
        'trip_id' => $active_trip_id,
        'user_id' => $currentUser->user_id,
        'itemName' => $_POST['item_name']
    ];

    $checklistController->add($data);
    header("Location: checklist.php?trip_id=".$active_trip_id);
    exit();
}

// Handle Status Change
if (isset($_GET['toggle']) && isset($_GET['current_status'])) {
    $item_id = $_GET['toggle'];
    $status = $_GET['current_status'];
    $checklistController->toggle($item_id, $status, $currentUser->user_id);
    header("Location: checklist.php?trip_id=" . $active_trip_id);
    exit();
}

$items = $checklistController->getAll($active_trip_id);
if($items) {
    $items = array_reverse($items); 
}

// Handle Delete Item
if (isset($_GET['delete'])) {
    $checklistController->delete($_GET['delete']);
    header("Location: checklist.php?trip_id=" . $active_trip_id);
    exit();
}
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
                    onchange="window.location.href='checklist.php?trip_id=' + this.value">
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
            <?php 
                $current_page = basename($_SERVER['PHP_SELF']); 
                $nav_items = [
                    ['url' => 'index.php',     'icon' => '◉',  'label' => 'Dashboard'],
                    ['url' => 'members.php',   'icon' => '👥', 'label' => 'Members'],
                    ['url' => 'itinerary.php', 'icon' => '◎',  'label' => 'Itinerary'],
                    ['url' => 'voting.php',    'icon' => '◇',  'label' => 'Voting'],
                    ['url' => 'rsvp.php',      'icon' => '✓',  'label' => 'RSVP'],
                    ['url' => 'expenses.php',  'icon' => '$',   'label' => 'Expenses'],
                    ['url' => 'documents.php', 'icon' => '📄', 'label' => 'Documents'],
                    ['url' => 'checklist.php', 'icon' => '☑',  'label' => 'Checklist'],
                ];

                foreach ($nav_items as $nav):
                    $active_class = ($current_page == $nav['url']) ? 'is-active' : '';
            ?>
                <a href="<?php echo $nav['url']; ?>?trip_id=<?php echo $active_trip_id; ?>" 
                   class="nav-item <?php echo $active_class; ?>" 
                   style="text-decoration:none;color:inherit;">
                    <span class="nav-item__icon"><?php echo $nav['icon']; ?></span> <?php echo $nav['label']; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar__footer">
            <div class="user-chip">
                <span class="avatar avatar--sm" style="background:#6366f1">
                    <?php echo ucfirst($currentUser->name[0]) ?>
                </span>
                <div>
                    <div class="user-chip__name"><?php echo $currentUser->name ?></div>
                    <div class="user-chip__role">Organizer on this trip</div>
                </div>
            </div>
            <a href="../Auth/logout.php" class="btn btn--ghost btn--sm" style="width:100%;margin-top:0.5rem;text-align:center;text-decoration:none;">Log out</a>
        </div>
    </aside>
    <main class="main">
        <header class="topbar">
            <div class="topbar__titles">
                <p class="eyebrow">Execution</p>
                <h1 class="topbar__title">Checklist</h1>
                <p class="muted topbar__session" style="margin:0.35rem 0 0; font-size:0.85rem;">
                    Signed in as <?php echo $currentUser->name ?>
                </p>
            </div>
        </header>

        <div class="content">
            <div class="tabs">
                <button type="button" class="tab is-active">All Items</button>
            </div>

            <?php if($items): ?>
    <?php foreach($items as $item): ?>
        <div class="card" style="margin-bottom:0.65rem;">
            <div class="card__header">
                <h3 class="card__title" style="font-size:0.95rem;">
                    <?php echo htmlspecialchars($item['itemName']); ?>
                </h3>
                <span class="badge <?php echo ($item['status'] == 'Done') ? 'badge--success' : 'badge--draft'; ?>">
                    <?php echo htmlspecialchars($item['status']); ?>
                </span>
            </div>

            <p class="muted" style="font-size:0.85rem; margin:0 0 0.5rem;">
                Added by : <?php echo htmlspecialchars($item['creator_name']); ?>
            </p>

            <?php if($item['status'] == 'Done'): ?>
                <p style="font-size:0.85rem; color: #2ecc71; font-weight: 500; margin-bottom: 0.5rem;">
                    ✅ Will be brought by: <?php echo htmlspecialchars($item['completer_name']); ?>
                </p>
                <a href="checklist.php?trip_id=<?php echo $active_trip_id; ?>&toggle=<?php echo $item['item_id']; ?>&current_status=Done" 
                   class="btn btn--sm btn--ghost" style="text-decoration:none; color: #6c757d; border: 1px solid #dee2e6;">
                   ↩ Undo
                </a>
            <?php else: ?>
                <a href="checklist.php?trip_id=<?php echo $active_trip_id; ?>&toggle=<?php echo $item['item_id']; ?>&current_status=<?php echo $item['status']; ?>" 
                   class="btn btn--sm btn--primary"
                   style="text-decoration: none;">
                   ✓ Complete
                </a>
                <a href="checklist.php?trip_id=<?php echo $active_trip_id; ?>&delete=<?php echo $item['item_id']; ?>" 
                   onclick="return confirm('Are you sure you want to delete this item?')"
                   style="text-decoration: none; color: #e74c3c; font-size: 1rem; margin-left: 10px;"
                   title="Delete Item">
                   ❌
                </a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
            <?php else: ?>
                <p class="muted">No items yet. Start by adding one below!</p>
            <?php endif; ?>

            <h2 class="section-title" style="margin-top:2rem;">New Item</h2>
            <div class="card">
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-row">
                            <label>Item Description</label>
                            <input type="text" name="item_name" class="input" placeholder="e.g. Pack chargers" required />
                        </div>
                        <div style="display:flex; justify-content:flex-end;">
                            <button type="submit" name="add_item" class="btn btn--primary">
                                Add to list
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>
</body>
</html>