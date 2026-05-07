<?php
ob_start();
session_start();
require_once __DIR__ . '/../../controller/AuthController.php';
require_once __DIR__ . '/../../model/user.php';
require_once __DIR__ . '/../../controller/ItineraryController.php';

$auth = new AuthController();
$currentUser = $auth->getCurrentUser();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_activity'])) {
    $activityController = new ItineraryController();
    $result = $activityController->addActivity($_POST);

    if ($result) {
      $_SESSION['success_msg'] = "Activity Added Successfully";
        header("Location: itinerary.php?added=1");
        exit(); 
    }
}

$activityController = new ItineraryController();
$activities = $activityController->getActivities(1);
$grouped = [];

if ($activities) {
    foreach ($activities as $act) {
        $grouped[$act['activity_date']][] = $act;
    }
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
    <div class="sidebar__trip"><label class="field-label">Active trip</label><select class="select select--full"><option selected>Summer Europe Tour</option><option>Berlin Workshop</option><option>Tokyo Summit</option></select><a href="index.php" class="btn btn--ghost btn--sm sidebar__new-trip" style="text-decoration:none;text-align:center;">+ New trip</a></div>
    <nav class="sidebar__nav">
      <a href="index.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">◉</span> Dashboard</a>
      <a href="members.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">👥</span> Members</a>
      <a href="itinerary.php" class="nav-item is-active" style="text-decoration:none;color:inherit;"><span class="nav-item__icon">◎</span> Itinerary</a>
      <a href="voting.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">◇</span> Voting</a>
      <a href="rsvp.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">✓</span> RSVP</a>
      <a href="expenses.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">$</span> Expenses</a>
      <a href="chat.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">💬</span> Chat</a>
      <a href="documents.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">📄</span> Documents</a>
      <a href="checklist.php" class="nav-item " style="text-decoration:none;color:inherit;"><span class="nav-item__icon">☑</span> Checklist</a>
    </nav>
    <div class="sidebar__footer"><div class="user-chip"><span class="avatar avatar--sm" style="background:#6366f1">A</span><div><div class="user-chip__name"><?php echo $currentUser->name ?></div><div class="user-chip__role">Organizer on this trip</div></div></div><a href="../Auth/logout.php" class="btn btn--ghost btn--sm" style="width:100%;margin-top:0.5rem;text-align:center;text-decoration:none;">Log out</a></div>
  </aside>
  <div class="sidebar-backdrop" aria-hidden="true"></div>
  <main class="main">
    <header class="topbar">
      <button type="button" class="btn btn--icon mobile-only" aria-label="Open menu">☰</button>
      <div class="topbar__titles"><p class="eyebrow">Planning</p><h1 class="topbar__title">Itinerary</h1><p class="muted topbar__session" style="margin:0.35rem 0 0;font-size:0.85rem;">Signed in as <?php echo $currentUser->name ?></p></div>
      <div class="topbar__actions"><button type="button" class="btn btn--secondary">+ Add day</button> <button type="button" class="btn btn--primary">+ Add activity</button></div>
    </header>
    <div class="content">


<div class="tabs"><button type="button" class="tab is-active">Board</button><button type="button" class="tab">Compact list</button></div>
<div class="day-board"> <?php foreach ($grouped as $date => $acts): ?>
        <div class="day-column"> <div class="day-column__head">
                <span class="day-column__label">Day: <?= $date ?></span>
                
            </div>
            
            <div class="day-activities"> <?php foreach ($acts as $a): ?>
                    <div class="activity-card">
                        <div class="activity-card__title">
                            <strong><?= $a['title'] ?></strong>
                        </div>
                        <div class="activity-card__meta">
                            🕐 <?= $a['activity_time'] ?> · <?= $a['activity_location'] ?>
                        </div>
                        <span class="badge badge--confirmed" style="margin-top:0.5rem; font-size:0.65rem;">Confirmed</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<h2 class="section-title" style="margin-top:2rem;">Compact list view</h2>
<div class="card" style="margin-bottom:0.75rem;"><h3 class="card__title">Day 1</h3><ul class="list-plain"><li><strong>City Walking Tour</strong> — 09:00 · Central Square</li><li><strong>Museum Visit</strong> — 14:00 · National Museum</li><li><strong>Dinner Reservation</strong> — 19:30 · La Bella Italia</li></ul></div>
<div class="card" style="margin-bottom:0.75rem;"><h3 class="card__title">Day 2</h3><ul class="list-plain"><li><strong>Beach Day</strong> — 10:00 · Sunny Beach</li><li><strong>Sunset Cruise</strong> — 17:00 · Harbor Marina</li></ul></div>
<div class="card" style="margin-bottom:0.75rem;"><h3 class="card__title">Day 3</h3><ul class="list-plain"><li><strong>Checkout &amp; Airport</strong> — 08:00 · Hotel Lobby</li></ul></div>
<h2 class="section-title" style="margin-top:2rem;">New activity form</h2>

<form method="POST" class="card">

<div class="form-grid">

<input
  type="hidden"
  name="trip_id"
  value="1"
/>

<div class="form-row">
<label>Title</label>

<input
  name="title"
  class="input"
  value=""
  placeholder="e.g., Eiffel Tower Visit"
/>
</div>

<div class="form-row">
<label>Date</label>

<input
  type="date"
  name="activity_date"
  class="input"
/>
</div>

<div class="form-row">
<label>Time</label>

<input
  type="time"
  name="activity_time"
  class="input"
  value=""
/>
</div>

<div class="form-row">
<label>Location</label>

<input
  name="location"
  class="input"
  value=""
  placeholder="enter activity location..."
/>
</div>



<div class="form-row">
<label>Activity type</label>

<select
  name="type"
  class="input"
>
<option value="" disabled selected>Choose activity type...</option>
  <option>Indoor</option>
  <option >Outdoor</option>
</select>

</div>

<div class="form-row">
<label>Status</label>

<select
  name="activity_state"
  class="input"
>
<option value="" disabled selected>Choose activity stat...</option>
  <option>Draft</option>
  <option >Confirmed</option>
</select>

</div>

<div style="display:flex;gap:0.5rem;justify-content:flex-end;">

<button
  type="button"
  class="btn btn--secondary"
>
Cancel
</button>

<button
  type="submit"
  name="add_activity"
  class="btn btn--primary"
>
Add activity
</button>

</div>

</div>
</form>
</div></div>
    </div></main></div>
<div id="modal-root" class="modal-root" aria-live="polite"></div>
<div id="toast-root" class="toast-root"></div>
</body></html>
