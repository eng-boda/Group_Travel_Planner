<?php

require_once __DIR__ . '/../../controller/AuthController.php';
require_once __DIR__ . '/../../model/user.php';
require_once __DIR__ . '/../../controller/TripController.php';
require_once __DIR__ . '/../../model/rsvp.php';
require_once __DIR__ . '/../../controller/ItineraryController.php';

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
$rsvp = new RSVP();

$itineraryController = new ItineraryController();

$activities = $itineraryController->getActivities($active_trip_id);

$selected_activity = $_GET['activity_id'] ?? null;

$yesUsers = [];
$maybeUsers = [];
$noUsers = [];

if($selected_activity){

    $responses = $rsvp->getActivityResponses($selected_activity);

    if($responses){

        while($row = $responses->fetch_assoc()){

            if($row['response'] == 'yes'){
                $yesUsers[] = $row['name'];
            }

            elseif($row['response'] == 'maybe'){
                $maybeUsers[] = $row['name'];
            }

            else{
                $noUsers[] = $row['name'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>RSVP - TripSync</title>
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
    <div class="sidebar__footer"><div class="user-chip"><span class="avatar avatar--sm" style="background:#6366f1"><?php echo ucfirst($currentUser->name[0]) ?></span><div><div class="user-chip__name"><?php echo $currentUser->name ?></div><div class="user-chip__role">Organizer on this trip</div></div></div><a href="../Auth/logout.php" class="btn btn--ghost btn--sm" style="width:100%;margin-top:0.5rem;text-align:center;text-decoration:none;">Log out</a></div>
  </aside>
  <div class="sidebar-backdrop" aria-hidden="true"></div>
  <main class="main">
    <header class="topbar">
      <button type="button" class="btn btn--icon mobile-only" aria-label="Open menu">☰</button>
      <div class="topbar__titles"><p class="eyebrow">Attendance</p><h1 class="topbar__title">RSVP</h1><p class="muted topbar__session" style="margin:0.35rem 0 0;font-size:0.85rem;">Signed in as <?php echo $currentUser->name ?></p></div>
      <div class="topbar__actions"></div>
    </header>
    <div class="content">

<div class="card card--gradient">

  <div class="card__header"
       style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">

    <div>
      <h2 class="section-title" style="margin:0;">
        Activity Attendance
      </h2>

      <p class="muted" style="margin:4px 0 0;">
        Select an activity to see who is attending.
      </p>
    </div>

    <div style="min-width:260px;max-width:400px;width:100%;">

      <form method="GET">

        <input type="hidden"
               name="trip_id"
               value="<?php echo $active_trip_id; ?>">

        <select class="input"
                name="activity_id"
                onchange="this.form.submit()">

          <option value="">Select Activity</option>

          <?php
          if($activities){

              foreach($activities as $a){
          ?>

              <option value="<?php echo $a['activity_id']; ?>"

                <?php
                if($selected_activity == $a['activity_id']){
                    echo 'selected';
                }
                ?>>

                <?php echo htmlspecialchars($a['title']); ?>

              </option>

          <?php
              }
          }
          ?>

        </select>

      </form>

    </div>

  </div>

  <div class="grid grid--3" style="margin-top:1.5rem;">

    <!-- YES -->

    <div class="card" style="border-top:4px solid var(--success);">

      <h3 class="card__title">Going</h3>

      <div style="font-size:2.5rem;font-weight:800;color:var(--success);margin:0.5rem 0;">

        <?php echo count($yesUsers); ?>

      </div>

      <ul class="list-plain"
          style="border-top:1px solid #eee;padding-top:0.5rem;">

        <?php

        if(!empty($yesUsers)){

            foreach($yesUsers as $name){

                echo "<li>$name</li>";
            }

        } else {

            echo '<li class="muted">—</li>';
        }

        ?>

      </ul>

    </div>

    <!-- MAYBE -->

    <div class="card" style="border-top:4px solid var(--warning);">

      <h3 class="card__title">Maybe</h3>

      <div style="font-size:2.5rem;font-weight:800;color:var(--warning);margin:0.5rem 0;">

        <?php echo count($maybeUsers); ?>

      </div>

      <ul class="list-plain"
          style="border-top:1px solid #eee;padding-top:0.5rem;">

        <?php

        if(!empty($maybeUsers)){

            foreach($maybeUsers as $name){

                echo "<li>$name</li>";
            }

        } else {

            echo '<li class="muted">—</li>';
        }

        ?>

      </ul>

    </div>

    <!-- NO -->

    <div class="card" style="border-top:4px solid var(--danger);">

      <h3 class="card__title">Not Going</h3>

      <div style="font-size:2.5rem;font-weight:800;color:var(--danger);margin:0.5rem 0;">

        <?php echo count($noUsers); ?>

      </div>

      <ul class="list-plain"
          style="border-top:1px solid #eee;padding-top:0.5rem;">

        <?php

        if(!empty($noUsers)){

            foreach($noUsers as $name){

                echo "<li>$name</li>";
            }

        } else {

            echo '<li class="muted">—</li>';
        }

        ?>

      </ul>

    </div>

  </div>

</div>
    </div></main></div>
<div id="modal-root" class="modal-root" aria-live="polite"></div>
<div id="toast-root" class="toast-root"></div>
</body></html>
