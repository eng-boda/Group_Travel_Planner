<?php
ob_start();
session_start();
require_once __DIR__ . '/../../controller/AuthController.php';
require_once __DIR__ . '/../../model/user.php';
require_once __DIR__ . '/../../controller/ItineraryController.php';
require_once __DIR__ . '/../../controller/TripController.php';

$auth = new AuthController();
$currentUser = $auth->getCurrentUser();

$tripController = new TripController();
$trips = $tripController->getAllTrips($currentUser->user_id);

// active_trip_id: prefer GET, fall back to POST (for form submissions), then first trip
$active_trip_id = isset($_GET['trip_id'])
    ? (int)$_GET['trip_id']
    : (isset($_POST['trip_id']) ? (int)$_POST['trip_id'] : ($trips[0]['trip_id'] ?? null));

$activityController = new ItineraryController();

$conflict_data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_activity'])) {
        $result = $activityController->addActivity($_POST);
        if (is_array($result) && isset($result['has_conflict'])) {
            $conflict_data = $result;
        }
    } 
    elseif (isset($_POST['update_activity'])) {
        $result = $activityController->updateActivity($_POST);
        if (is_array($result) && isset($result['has_conflict'])) {
            $conflict_data = $result;
        }
    } 
    elseif (isset($_POST['delete_activity'])) {
        $activityController->deleteActivity($_POST['delete_activity_id'], $active_trip_id);
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

$activities = $active_trip_id ? $activityController->getActivities($active_trip_id) : [];
$grouped = [];
if ($activities) {
    foreach ($activities as $act) {
        $cleanDate = date('Y-m-d', strtotime($act['activity_date'])); 
        $grouped[$cleanDate][] = $act;
    }
}

$edit_activity = null;
if (isset($_GET['edit_activity_id'])) {
    $edit_activity = $activityController->getActivityById($_GET['edit_activity_id']);
}
?>
<?php if(isset($_SESSION['success_msg'])): ?>
    <script>
        alert('<?php echo $_SESSION['success_msg']; ?>');
    </script>
    <?php unset($_SESSION['success_msg']);?>
<?php endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Itinerary - TripSync</title>
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
              onchange="window.location.href='itinerary.php?trip_id=' + this.value">
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
      <div class="topbar__titles"><p class="eyebrow">Planning</p><h1 class="topbar__title">Itinerary</h1><p class="muted topbar__session" style="margin:0.35rem 0 0;font-size:0.85rem;">Signed in as <?php echo $currentUser->name ?></p></div>
      <div class="topbar__actions"><a href="#activity_form" class="btn btn--primary" style="text-decoration:none;">+ Add Activity</a></div>
    </header>
    <div class="content">

    <?php if ($conflict_data): ?>
    <div class="card" style="border: 2px solid #f59e0b; background: #fffbeb; margin-bottom: 1.5rem; padding: 1rem;">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
            <span style="font-size: 1.25rem;">⚠️</span>
            <div>
                <h4 style="margin: 0; color: #b45309; font-size: 1rem;">Timing Conflict!</h4>
                <p style="margin: 0.25rem 0 0; font-size: 0.85rem; color: #92400e;">
                    This activity is very close to <strong>"<?php echo htmlspecialchars($conflict_data['existing_title']); ?>"</strong> 
                    (Difference: <?php echo round($conflict_data['diff']); ?> mins).
                </p>
            </div>
        </div>
        
        <div style="display: flex; gap: 0.5rem;">
            <form method="POST">
                <?php foreach ($_POST as $key => $val): ?>
                    <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($val); ?>">
                <?php endforeach; ?>
                <input type="hidden" name="ignore_conflict" value="1">
                <button type="submit" name="<?php echo isset($_POST['add_activity']) ? 'add_activity' : 'update_activity'; ?>" 
                        class="btn btn--sm" style="background: #fef3c7; color: #b45309; border: 1px solid #f59e0b; cursor: pointer;">
                    Ignore & Save Anyway
                </button>
            </form>

            <button type="button" class="btn btn--sm btn--primary" onclick="document.getElementById('a-time').focus(); this.closest('.card').remove();">
                Edit Time
            </button>
        </div>
    </div>
<?php endif; ?>

<h2 class="section-title" style="margin-top:2rem;">Itinerary Activities</h2>

<div class="grid grid--2"> 
    <?php if (!empty($grouped)): ?>
        <?php foreach ($grouped as $date => $acts): ?>
            <?php foreach ($acts as $a): ?>
                <div class="card">
                  <div class="card__header">
                      <h3 class="card__title"><?php echo htmlspecialchars($a['title']); ?></h3>
                      <span class="badge <?php echo ($a['activity_state'] == 'Confirmed') ? 'badge--confirmed' : 'badge--draft'; ?>" style="font-size:0.65rem;">
                          <?php echo strtoupper(htmlspecialchars($a['activity_state'])); ?>
                      </span>
                  </div>
                  
                  <div class="activity-details" style="display: grid; gap: 0.5rem; margin-top: 0.5rem;">
                      <p class="muted" style="margin: 0;">
                          📅 <strong>Date:</strong> <?php echo date('F j, Y', strtotime($a['activity_date'])); ?>
                      </p>
                      <p class="muted" style="margin: 0;">
                          🕐 <strong>Time:</strong> <?php echo date('g:i A', strtotime($a['activity_time'])); ?>
                      </p>
                      <p class="muted" style="margin: 0;">
                          📍 <strong>Location:</strong> <?php echo htmlspecialchars($a['activity_location']); ?>
                      </p>
                      <p class="muted" style="margin: 0;">
                          🏷️ <strong>Type:</strong> <span class="tag"><?php echo htmlspecialchars($a['type']); ?></span>
                      </p>
                  </div>

                  <div style="margin-top:1rem; display:flex; gap:0.4rem;">
                      <a href="itinerary.php?trip_id=<?php echo $active_trip_id; ?>&edit_activity_id=<?php echo $a['activity_id']; ?>#activity_form" 
                        class="btn btn--sm btn--secondary" style="text-decoration:none; text-align:center;">
                        Edit
                      </a>

                      <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                          <input type="hidden" name="delete_activity_id" value="<?php echo $a['activity_id']; ?>">
                          <input type="hidden" name="trip_id" value="<?php echo $active_trip_id; ?>">
                          <button type="submit" name="delete_activity" class="btn btn--sm btn--danger">Remove</button>
                      </form>
                  </div>

                 <form action="../../controller/RSVPController.php" method="POST"
                       style="margin-top:1rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
                    <input type="hidden" name="activity_id" value="<?php echo $a['activity_id']; ?>">
                    <button type="submit" name="response" value="yes"   class="btn btn--sm">✅Yes</button>
                    <button type="submit" name="response" value="maybe" class="btn btn--sm btn--secondary">🤔Maybe</button>
                    <button type="submit" name="response" value="no"    class="btn btn--sm btn--danger">❌No</button>
                </form>

</div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="muted">No activities found for this trip. Use the form below to add one!</p>
    <?php endif; ?>
</div>

<h2 class="section-title" style="margin-top:2rem;">Compact list view</h2>

<?php 
if ($activeTrip) {
    $start = new DateTime($activeTrip['start_date']);
    $end = new DateTime($activeTrip['end_date']);
    $end->modify('+1 day'); 
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);

    $dayCounter = 1;
    foreach ($period as $date) {
        $dateStr = $date->format('Y-m-d');
        ?>
        <div class="card" style="margin-bottom:0.75rem;">
            <h3 class="card__title">
                Day <?php echo $dayCounter++; ?> 
                <span style="font-size: 0.8rem; font-weight: normal; color: #666;">
                    (<?php echo $date->format('M j'); ?>)
                </span>
            </h3>
            <ul class="list-plain">
                <?php if (isset($grouped[$dateStr])): ?>
                    <?php foreach ($grouped[$dateStr] as $a): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($a['title']); ?></strong> 
                            — <?php echo date('g:i A', strtotime($a['activity_time'])); ?> 
                            · <?php echo htmlspecialchars($a['activity_location']); ?>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="muted">No activities planned</li>
                <?php endif; ?>
            </ul>
        </div>
        <?php
    }
}
?>

<h2 class="section-title" id="activity_form"><?php echo $edit_activity ? 'Edit Activity' : 'New Activity'; ?></h2>

<form method="POST" class="card">
    <input type="hidden" name="trip_id" value="<?php echo $active_trip_id; ?>" />
    
    <?php if($edit_activity): ?>
        <input type="hidden" name="activity_id" value="<?php echo $edit_activity['activity_id']; ?>">
    <?php endif; ?>

    <div class="form-grid">
        <div class="form-row">
            <label for="a-title">Activity Title</label>
            <input id="a-title" name="title" class="input" 
                   value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ($edit_activity ? htmlspecialchars($edit_activity['title']) : ''); ?>" 
                   placeholder="e.g., Eiffel Tower Visit" required />
        </div>

        <div class="form-row">
            <label for="a-date">Activity Date</label>
            <input 
                id="a-date" 
                type="date" 
                name="activity_date" 
                class="input"
                min="<?php echo $activeTrip['start_date'] ?? ''; ?>"
                max="<?php echo $activeTrip['end_date'] ?? ''; ?>"
                value="<?php 
                    if(isset($_POST['activity_date'])) {
                        echo $_POST['activity_date'];
                    } elseif($edit_activity && !empty($edit_activity['activity_date'])) {
                        echo date('Y-m-d', strtotime($edit_activity['activity_date'])); 
                    } else {
                        echo '';
                    }
                ?>" 
                required 
            />
        </div>

        <div class="form-row">
            <label for="a-time">Time</label>
            <input id="a-time" type="time" name="activity_time" class="input" 
                   value="<?php echo isset($_POST['activity_time']) ? $_POST['activity_time'] : ($edit_activity ? $edit_activity['activity_time'] : ''); ?>" required />
        </div>

        <div class="form-row">
            <label for="country-input">Country</label>
            <input 
                id="country-input" 
                list="countries-list" 
                class="input" 
                placeholder="Type to search country..." 
                oninput="handleCountryInput(this.value)"
                autocomplete="off"
            />
            <datalist id="countries-list"></datalist>
        </div>

        <div class="form-row">
            <label for="a-loc">City / Governorate</label>
            <input 
                id="a-loc" 
                name="location" 
                list="cities-list" 
                class="input" 
                placeholder="Type to search city..." 
                value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ($edit_activity ? htmlspecialchars($edit_activity['activity_location']) : ''); ?>"
                required 
                autocomplete="off"
            />
            <datalist id="cities-list"></datalist>
        </div>

        <div class="form-row">
            <label for="a-type">Activity Type</label>
            <select id="a-type" name="type" class="input">
                <?php $currentType = isset($_POST['type']) ? $_POST['type'] : ($edit_activity ? $edit_activity['type'] : ''); ?>
                <option value="Indoor"  <?php echo ($currentType == 'Indoor')  ? 'selected' : ''; ?>>Indoor</option>
                <option value="Outdoor" <?php echo ($currentType == 'Outdoor') ? 'selected' : ''; ?>>Outdoor</option>
            </select>
        </div>

        <div class="form-row">
            <label for="a-status">Status</label>
            <select id="a-status" name="activity_state" class="input">
                <?php $currentState = isset($_POST['activity_state']) ? $_POST['activity_state'] : ($edit_activity ? $edit_activity['activity_state'] : 'Confirmed'); ?>
                <option value="Confirmed" <?php echo ($currentState == 'Confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                <option value="Draft"     <?php echo ($currentState == 'Draft')     ? 'selected' : ''; ?>>Draft</option>
            </select>
        </div>

        <div style="display:flex;gap:0.5rem;justify-content:flex-end;">
            <?php if($edit_activity): ?>
                <a href="itinerary.php?trip_id=<?php echo $active_trip_id; ?>" class="btn btn--secondary" style="text-decoration:none;">Cancel Edit</a>
                <button type="submit" name="update_activity" class="btn btn--primary">Update activity</button>
            <?php else: ?>
                <button type="submit" name="add_activity" class="btn btn--primary">Add activity</button>
            <?php endif; ?>
        </div>
    </div>
</form>
</div></div>
    </div></main></div>
<div id="modal-root" class="modal-root" aria-live="polite"></div>
<div id="toast-root" class="toast-root"></div>
</body>

<script>
let locationData = {};

async function initLocationSystem() {
    try {
        const response = await fetch('countries.json'); 
        locationData = await response.json();
        
        const countryDataList = document.getElementById('countries-list');
        const countries = Object.keys(locationData).sort();
        
        countries.forEach(country => {
            const option = document.createElement('option');
            option.value = country;
            countryDataList.appendChild(option);
        });
    } catch (error) {
        console.error("Failed to load countries.json:", error);
    }
}

function handleCountryInput(val) {
    const cityDataList = document.getElementById('cities-list');
    
    if (locationData[val]) {
        cityDataList.innerHTML = '';
        locationData[val].sort().forEach(city => {
            const option = document.createElement('option');
            option.value = city;
            cityDataList.appendChild(option);
        });
    } else {
        cityDataList.innerHTML = '';
    }
}

document.addEventListener('DOMContentLoaded', initLocationSystem);
</script>

</html>