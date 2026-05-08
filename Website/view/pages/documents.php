<?php
require_once __DIR__ . '/../../controller/AuthController.php';
require_once __DIR__ . '/../../controller/TripController.php';
require_once __DIR__ . '/../../controller/ItineraryController.php';
require_once __DIR__ . '/../../controller/DocumentController.php';
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    header('Location: ../Auth/login.php');
    exit;
}

$currentUser    = $auth->getCurrentUser();
$tripController = new TripController();
$trips          = $tripController->getAllTrips($currentUser->user_id);

$active_trip_id = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : ($trips[0]['trip_id'] ?? null);

$activeTrip = null;
if ($active_trip_id) {
    foreach ($trips as $t) {
        if ($t['trip_id'] == $active_trip_id) { $activeTrip = $t; break; }
    }
}

// Load activities for the attach-modal dropdown
$activities = [];
if ($active_trip_id) {
    $itCtrl     = new ItineraryController();
    $activities = $itCtrl->getActivities($active_trip_id) ?: [];
}

// Load documents
$docCtrl   = new DocumentController();
$documents = $active_trip_id ? $docCtrl->getDocumentsByTrip($active_trip_id) : [];

// Group by category for display
$byCategory = [];
foreach ($documents as $doc) {
    $byCategory[$doc['category']][] = $doc;
}

// Category labels
$categoryLabels = [
    'general'   => 'General',
    'transport' => 'Transport',
    'hotel'     => 'Hotel & Accommodation',
    'activity'  => 'Activities',
    'insurance' => 'Insurance',
    'finance'   => 'Finance',
    'other'     => 'Other',
];

$totalSize = array_sum(array_column($documents, 'file_size'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Documents · TripSync</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../css/main.css" />
  <style>
    /* ── Drop-zone ─────────────────────────────── */
    .drop-zone {
      border: 2px dashed var(--border-strong);
      border-radius: var(--radius);
      padding: 2.5rem 1.5rem;
      text-align: center;
      background: rgba(99,102,241,.04);
      transition: background var(--transition), border-color var(--transition);
      cursor: pointer;
    }
    .drop-zone.drag-over {
      background: rgba(99,102,241,.1);
      border-color: var(--accent);
    }
    .drop-zone__icon { font-size: 2.4rem; display: block; margin-bottom: .5rem; }
    .drop-zone__sub  { font-size: .8rem; color: var(--text-muted); margin-top: .3rem; }

    /* ── Search bar ─────────────────────────────── */
    .search-bar {
      display: flex;
      gap: .5rem;
      align-items: center;
    }
    .search-bar .input { flex: 1; }

    /* ── Doc row ────────────────────────────────── */
    .doc-list { display: flex; flex-direction: column; gap: .5rem; }
    .doc-row {
      display: flex;
      align-items: center;
      gap: .75rem;
      padding: .75rem 1rem;
      border-radius: var(--radius-sm);
      background: var(--surface);
      border: 1px solid var(--border);
      transition: box-shadow var(--transition);
      flex-wrap: wrap;
    }
    .doc-row:hover { box-shadow: var(--shadow-md); }
    .doc-row__icon  { font-size: 1.6rem; flex-shrink: 0; }
    .doc-row__info  { flex: 1; min-width: 0; }
    .doc-row__name  { font-weight: 600; font-size: .92rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .doc-row__meta  { font-size: .76rem; color: var(--text-muted); margin-top: .1rem; }
    .doc-row__actions { display: flex; gap: .35rem; flex-shrink: 0; }
    .category-header {
      font-size: .78rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: var(--text-muted);
      margin: 1.25rem 0 .5rem;
    }

    /* ── Stats strip ────────────────────────────── */
    .stats-strip {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      margin-bottom: 1rem;
    }
    .stat-pill {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-pill);
      padding: .3rem .9rem;
      font-size: .82rem;
      color: var(--text-muted);
      display: flex;
      align-items: center;
      gap: .4rem;
    }
    .stat-pill b { color: var(--text); }

    /* ── Progress bar ───────────────────────────── */
    .progress-bar {
      height: 5px;
      background: var(--border);
      border-radius: 99px;
      overflow: hidden;
      margin: .5rem 0 .25rem;
    }
    .progress-bar__fill {
      height: 100%;
      background: linear-gradient(90deg, var(--accent), var(--accent-2));
      border-radius: 99px;
      transition: width .4s ease;
    }

    /* ── Upload progress overlay ────────────────── */
    #upload-progress {
      display: none;
      align-items: center;
      gap: .75rem;
      padding: .75rem 1rem;
      background: rgba(99,102,241,.08);
      border-radius: var(--radius-sm);
      margin-top: .75rem;
    }
    #upload-progress.visible { display: flex; }

    /* ── Toast reuse ────────────────────────────── */
    .toast {
      position: fixed;
      bottom: 1.5rem;
      right: 1.5rem;
      z-index: 9999;
      background: var(--text);
      color: #fff;
      padding: .75rem 1.25rem;
      border-radius: var(--radius-sm);
      font-size: .88rem;
      box-shadow: var(--shadow-lg);
      opacity: 0;
      transform: translateY(8px);
      transition: opacity .3s, transform .3s;
      max-width: 340px;
    }
    .toast.show { opacity: 1; transform: translateY(0); }
    .toast.success { border-left: 4px solid var(--success); }
    .toast.error   { border-left: 4px solid var(--danger); }

    @media(max-width:640px){
      .doc-row { flex-wrap: wrap; }
      .stats-strip { gap: .5rem; }
    }
  </style>
</head>
<body>
<div id="app" class="app">

  <!-- ═══════════════════ SIDEBAR ═══════════════════ -->
  <aside class="sidebar">
    <div class="sidebar__brand">
      <div class="logo-mark" aria-hidden="true">✈</div>
      <div><div class="logo-text">TripSync</div><div class="logo-sub">Collaborative Planner</div></div>
    </div>
    <div class="sidebar__trip">
      <label class="field-label">Active trip</label>
      <div class="select select--full" style="background:#f8f9fa;border-color:#e9ecef;cursor:default;color:#495057;">
        <?php echo $activeTrip ? htmlspecialchars($activeTrip['trip_name']) : 'No Active Trip'; ?>
      </div>
    </div>
    <nav class="sidebar__nav" aria-label="Main navigation">
      <?php
      $nav = [
        'index.php'     => ['◉', 'Dashboard'],
        'members.php'   => ['👥', 'Members'],
        'itinerary.php' => ['◎', 'Itinerary'],
        'voting.php'    => ['◇', 'Voting'],
        'rsvp.php'      => ['✓', 'RSVP'],
        'expenses.php'  => ['$', 'Expenses'],
        'chat.php'      => ['💬', 'Chat'],
        'documents.php' => ['📄', 'Documents'],
        'checklist.php' => ['☑', 'Checklist'],
      ];
      foreach ($nav as $page => [$icon, $label]):
        $active = basename($_SERVER['PHP_SELF']) === $page ? 'is-active' : '';
      ?>
      <a href="<?= $page ?>?trip_id=<?= $active_trip_id ?>"
         class="nav-item <?= $active ?>"
         style="text-decoration:none;color:inherit;">
        <span class="nav-item__icon"><?= $icon ?></span> <?= $label ?>
      </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar__footer">
      <div class="user-chip">
        <span class="avatar avatar--sm" style="background:#6366f1"><?= strtoupper($currentUser->name[0]) ?></span>
        <div>
          <div class="user-chip__name"><?= htmlspecialchars($currentUser->name) ?></div>
          <div class="user-chip__role">Member</div>
        </div>
      </div>
      <a href="../Auth/logout.php" class="btn btn--ghost btn--sm" style="width:100%;margin-top:.5rem;text-align:center;text-decoration:none;">Log out</a>
    </div>
  </aside>

  <div class="sidebar-backdrop" aria-hidden="true"></div>

  <!-- ═══════════════════ MAIN ═══════════════════ -->
  <main class="main">
    <header class="topbar">
      <button type="button" class="btn btn--icon mobile-only" aria-label="Open menu" id="menu-toggle">☰</button>
      <div class="topbar__titles">
        <p class="eyebrow">Files</p>
        <h1 class="topbar__title">Documents</h1>
        <p class="muted topbar__session" style="margin:.35rem 0 0;font-size:.85rem;">
          Signed in as <?= htmlspecialchars($currentUser->name) ?>
        </p>
      </div>
      <div class="topbar__actions" style="display:flex;gap:.5rem;align-items:center;">
        <?php if ($active_trip_id && count($documents) > 0): ?>
        <a href="../../controller/document_actions.php?action=export_zip&trip_id=<?= $active_trip_id ?>"
           class="btn btn--secondary btn--sm" title="Download all as ZIP">
          ⬇ Export All
        </a>
        <?php endif; ?>
        <button type="button" class="btn btn--primary" id="open-upload-modal">+ Upload</button>
      </div>
    </header>

    <div class="content">

      <?php if (!$active_trip_id): ?>
      <div class="card" style="text-align:center;padding:3rem;">
        <p style="font-size:2rem;">📄</p>
        <h3>No trip selected</h3>
        <p class="muted">Create or join a trip to manage its documents.</p>
      </div>
      <?php else: ?>

      <!-- Stats strip -->
      <div class="stats-strip">
        <div class="stat-pill">📄 <b><?= count($documents) ?></b> files</div>
        <div class="stat-pill">💾 <b><?= DocumentController::formatSize($totalSize) ?></b> used</div>
        <div class="stat-pill">📁 <b><?= count($byCategory) ?></b> categories</div>
      </div>

      <!-- Search bar -->
      <div class="card" style="margin-bottom:1rem;">
        <div class="search-bar">
          <input type="text" id="search-input" class="input" placeholder="Search by filename, category or activity…" />
          <button type="button" class="btn btn--secondary" id="search-btn">🔍 Search</button>
          <button type="button" class="btn btn--ghost btn--sm" id="clear-search" style="display:none;">✕</button>
        </div>
      </div>

      <!-- Document library -->
      <div class="card" id="doc-library">
        <div class="card__header">
          <h3 class="card__title">Library</h3>
          <span class="badge badge--info" id="doc-count"><?= count($documents) ?> files</span>
        </div>

        <div id="doc-list-container">
          <?php if (empty($documents)): ?>
          <div id="empty-state" style="text-align:center;padding:2rem 0;">
            <p style="font-size:1.8rem;">📂</p>
            <p class="muted">No documents yet. Upload your first file!</p>
          </div>
          <?php else: ?>
            <?php foreach ($byCategory as $cat => $catDocs): ?>
            <div class="category-header"><?= htmlspecialchars($categoryLabels[$cat] ?? ucfirst($cat)) ?></div>
            <div class="doc-list">
              <?php foreach ($catDocs as $doc): ?>
              <?php echo renderDocRow($doc, $active_trip_id); ?>
              <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <?php endif; ?>

    </div><!-- /content -->
  </main>
</div><!-- /app -->

<!-- ═══════════════════ UPLOAD MODAL ═══════════════════ -->
<div id="modal-root" class="modal-root" aria-live="polite">
<div class="modal-backdrop" id="upload-backdrop" style="display:none;" aria-hidden="true"></div>
<div class="modal" id="upload-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title" style="display:none;">
  <div class="modal__header">
    <h2 class="modal__title" id="modal-title">Upload Documents</h2>
    <button type="button" class="modal__close" id="close-upload-modal" aria-label="Close">✕</button>
  </div>
  <div class="modal__body">
    <form id="upload-form" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload" />
      <input type="hidden" name="trip_id" value="<?= $active_trip_id ?>" />

      <!-- Drop zone -->
      <div class="drop-zone" id="drop-zone" tabindex="0" role="button" aria-label="Drop files here or click to browse">
        <span class="drop-zone__icon">📁</span>
        <strong>Drag & drop files here</strong>
        <p class="drop-zone__sub">or click to browse · PDF, Word, Excel, Images, ZIP · Max 20 MB each</p>
        <input type="file" name="files[]" id="file-input" hidden multiple
               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp,.txt,.csv,.zip" />
      </div>

      <!-- Selected files preview -->
      <div id="file-preview-list" style="margin-top:.75rem;display:none;">
        <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:.35rem;">Selected files:</p>
        <ul id="file-preview-items" style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.35rem;"></ul>
      </div>

      <!-- Upload progress -->
      <div id="upload-progress">
        <span style="animation:spin 1s linear infinite;display:inline-block;">⟳</span>
        <span id="upload-progress-text">Uploading…</span>
      </div>

      <!-- Category -->
      <div class="form-row" style="margin-top:1rem;">
        <label for="category-select">Category</label>
        <select class="input" id="category-select" name="category">
          <?php foreach ($categoryLabels as $val => $lbl): ?>
          <option value="<?= $val ?>"><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Attach to activity (optional) -->
      <div class="form-row">
        <label for="activity-select-upload">Link to activity <span class="muted">(optional)</span></label>
        <select class="input" id="activity-select-upload" name="activity_id">
          <option value="">— Not linked —</option>
          <?php foreach ($activities as $act): ?>
          <option value="<?= $act['activity_id'] ?>">
            <?= htmlspecialchars($act['title']) ?> — <?= htmlspecialchars($act['activity_date'] ?? '') ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
  <div class="modal__footer">
    <button type="button" class="btn btn--ghost" id="cancel-upload">Cancel</button>
    <button type="button" class="btn btn--primary" id="submit-upload" disabled>Upload Files</button>
  </div>
</div>
</div>

<!-- ═══════════════════ ATTACH MODAL ═══════════════════ -->
<div class="modal-backdrop" id="attach-backdrop" style="display:none;" aria-hidden="true"></div>
<div class="modal" id="attach-modal" role="dialog" aria-modal="true" style="display:none;">
  <div class="modal__header">
    <h2 class="modal__title">Link to Activity</h2>
    <button type="button" class="modal__close" id="close-attach-modal">✕</button>
  </div>
  <div class="modal__body">
    <p class="muted" style="margin-bottom:.75rem;">Choose an itinerary activity to link this document to.</p>
    <input type="hidden" id="attach-doc-id" />
    <select class="input" id="attach-activity-select">
      <option value="">— Remove link —</option>
      <?php foreach ($activities as $act): ?>
      <option value="<?= $act['activity_id'] ?>">
        <?= htmlspecialchars($act['title']) ?> — <?= htmlspecialchars($act['activity_date'] ?? '') ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="modal__footer">
    <button type="button" class="btn btn--ghost" id="cancel-attach">Cancel</button>
    <button type="button" class="btn btn--primary" id="save-attach">Save Link</button>
  </div>
</div>

<!-- Toast -->
<div id="toast" class="toast" role="status" aria-live="polite"></div>

<style>
  @keyframes spin { to { transform: rotate(360deg); } }
</style>

<script>
// ═══════════════════════════════════════════════════════════════
// Config
// ═══════════════════════════════════════════════════════════════
const ACTIONS_URL = '../../controller/document_actions.php';
const TRIP_ID     = <?= (int)$active_trip_id ?>;

// ═══════════════════════════════════════════════════════════════
// Utility helpers
// ═══════════════════════════════════════════════════════════════
function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className   = `toast ${type} show`;
  setTimeout(() => { t.className = 'toast'; }, 3400);
}

function formatSize(bytes) {
  if (bytes < 1024)       return bytes + ' B';
  if (bytes < 1048576)    return (bytes/1024).toFixed(1)  + ' KB';
  if (bytes < 1073741824) return (bytes/1048576).toFixed(1) + ' MB';
  return (bytes/1073741824).toFixed(2) + ' GB';
}

function fileIcon(name) {
  const ext = name.split('.').pop().toLowerCase();
  const map = { pdf:'📄', doc:'📝', docx:'📝', xls:'📊', xlsx:'📊',
                ppt:'📑', pptx:'📑', jpg:'🖼️', jpeg:'🖼️', png:'🖼️',
                gif:'🖼️', webp:'🖼️', zip:'🗜️', rar:'🗜️', txt:'📃', csv:'📃' };
  return map[ext] || '📎';
}

function openModal(modalId, backdropId) {
  document.getElementById(backdropId).style.display = 'block';
  document.getElementById(modalId).style.display    = 'flex';
}

function closeModal(modalId, backdropId) {
  document.getElementById(backdropId).style.display = 'none';
  document.getElementById(modalId).style.display    = 'none';
}

// ═══════════════════════════════════════════════════════════════
// Sidebar mobile toggle
// ═══════════════════════════════════════════════════════════════
document.getElementById('menu-toggle')?.addEventListener('click', () => {
  document.querySelector('.sidebar')?.classList.toggle('is-open');
});
document.querySelector('.sidebar-backdrop')?.addEventListener('click', () => {
  document.querySelector('.sidebar')?.classList.remove('is-open');
});

// ═══════════════════════════════════════════════════════════════
// Upload modal
// ═══════════════════════════════════════════════════════════════
const dropZone    = document.getElementById('drop-zone');
const fileInput   = document.getElementById('file-input');
const submitBtn   = document.getElementById('submit-upload');
const previewList = document.getElementById('file-preview-list');
const previewItems= document.getElementById('file-preview-items');
let selectedFiles = new DataTransfer();

document.getElementById('open-upload-modal').addEventListener('click', () =>
  openModal('upload-modal', 'upload-backdrop'));
document.getElementById('close-upload-modal').addEventListener('click', resetAndClose);
document.getElementById('cancel-upload').addEventListener('click', resetAndClose);
document.getElementById('upload-backdrop').addEventListener('click', resetAndClose);

function resetAndClose() {
  closeModal('upload-modal', 'upload-backdrop');
  selectedFiles = new DataTransfer();
  previewItems.innerHTML = '';
  previewList.style.display = 'none';
  submitBtn.disabled = true;
  document.getElementById('upload-progress').classList.remove('visible');
}

// Click drop zone → open file picker
dropZone.addEventListener('click', () => fileInput.click());
dropZone.addEventListener('keydown', e => { if (e.key==='Enter'||e.key===' ') fileInput.click(); });

// Drag & drop
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
  e.preventDefault();
  dropZone.classList.remove('drag-over');
  addFiles(e.dataTransfer.files);
});

fileInput.addEventListener('change', () => addFiles(fileInput.files));

function addFiles(files) {
  for (const file of files) {
    selectedFiles.items.add(file);
  }
  renderPreview();
}

function renderPreview() {
  previewItems.innerHTML = '';
  const files = selectedFiles.files;
  if (!files.length) { previewList.style.display='none'; submitBtn.disabled=true; return; }

  previewList.style.display = 'block';
  submitBtn.disabled = false;

  for (let i=0; i<files.length; i++) {
    const f = files[i];
    const li = document.createElement('li');
    li.style.cssText='display:flex;align-items:center;gap:.5rem;font-size:.83rem;background:var(--surface);padding:.4rem .7rem;border-radius:8px;border:1px solid var(--border);';
    li.innerHTML = `<span>${fileIcon(f.name)}</span>
      <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${f.name}</span>
      <span style="color:var(--text-muted);">${formatSize(f.size)}</span>
      <button type="button" data-idx="${i}" style="background:none;border:none;cursor:pointer;color:var(--danger);font-size:1rem;" aria-label="Remove">✕</button>`;
    li.querySelector('button').addEventListener('click', function() {
      removeFile(parseInt(this.dataset.idx));
    });
    previewItems.appendChild(li);
  }
}

function removeFile(idx) {
  const dt = new DataTransfer();
  const files = selectedFiles.files;
  for (let i=0; i<files.length; i++) { if (i!==idx) dt.items.add(files[i]); }
  selectedFiles = dt;
  renderPreview();
}

// Submit upload
document.getElementById('submit-upload').addEventListener('click', async () => {
  if (!selectedFiles.files.length) return;

  const progress = document.getElementById('upload-progress');
  const progressText = document.getElementById('upload-progress-text');
  submitBtn.disabled = true;
  progress.classList.add('visible');
  progressText.textContent = `Uploading ${selectedFiles.files.length} file(s)…`;

  const fd = new FormData(document.getElementById('upload-form'));
  // Override file input with our accumulated files
  fd.delete('files[]');
  for (const f of selectedFiles.files) fd.append('files[]', f);

  try {
    const res  = await fetch(ACTIONS_URL, { method:'POST', body: fd });
    const data = await res.json();

    if (data.success) {
      showToast(data.message, 'success');
      resetAndClose();
      reloadDocList();
    } else {
      showToast(data.message || 'Upload failed.', 'error');
      submitBtn.disabled = false;
    }
  } catch(e) {
    showToast('Network error — please try again.', 'error');
    submitBtn.disabled = false;
  } finally {
    progress.classList.remove('visible');
  }
});

// ═══════════════════════════════════════════════════════════════
// Delete document
// ═══════════════════════════════════════════════════════════════
async function deleteDoc(docId, btn) {
  if (!confirm('Delete this document? This cannot be undone.')) return;
  btn.disabled = true;
  try {
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('doc_id', docId);
    const res  = await fetch(ACTIONS_URL, { method:'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      showToast('Document deleted.', 'success');
      reloadDocList();
    } else {
      showToast(data.message || 'Delete failed.', 'error');
      btn.disabled = false;
    }
  } catch(e) {
    showToast('Network error.', 'error');
    btn.disabled = false;
  }
}

// ═══════════════════════════════════════════════════════════════
// Attach modal
// ═══════════════════════════════════════════════════════════════
document.getElementById('close-attach-modal').addEventListener('click',  () => closeModal('attach-modal','attach-backdrop'));
document.getElementById('cancel-attach').addEventListener('click',        () => closeModal('attach-modal','attach-backdrop'));
document.getElementById('attach-backdrop').addEventListener('click',      () => closeModal('attach-modal','attach-backdrop'));

function openAttachModal(docId, currentActivityId) {
  document.getElementById('attach-doc-id').value = docId;
  const sel = document.getElementById('attach-activity-select');
  sel.value = currentActivityId || '';
  openModal('attach-modal', 'attach-backdrop');
}

document.getElementById('save-attach').addEventListener('click', async () => {
  const docId      = document.getElementById('attach-doc-id').value;
  const activityId = document.getElementById('attach-activity-select').value;

  const fd = new FormData();
  fd.append('action',      'attach');
  fd.append('doc_id',      docId);
  fd.append('activity_id', activityId);

  try {
    const res  = await fetch(ACTIONS_URL, { method:'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      showToast('Activity link saved.', 'success');
      closeModal('attach-modal', 'attach-backdrop');
      reloadDocList();
    } else {
      showToast('Failed to save link.', 'error');
    }
  } catch(e) {
    showToast('Network error.', 'error');
  }
});

// ═══════════════════════════════════════════════════════════════
// Search
// ═══════════════════════════════════════════════════════════════
document.getElementById('search-btn').addEventListener('click', doSearch);
document.getElementById('search-input').addEventListener('keydown', e => { if(e.key==='Enter') doSearch(); });
document.getElementById('clear-search').addEventListener('click', () => {
  document.getElementById('search-input').value = '';
  document.getElementById('clear-search').style.display = 'none';
  reloadDocList();
});

async function doSearch() {
  const q = document.getElementById('search-input').value.trim();
  if (!q) { reloadDocList(); return; }

  document.getElementById('clear-search').style.display = 'inline-flex';
  const fd = new FormData();
  fd.append('action',   'search');
  fd.append('trip_id',  TRIP_ID);
  fd.append('query',    q);

  const res  = await fetch(ACTIONS_URL, { method:'POST', body: fd });
  const data = await res.json();
  if (data.success) renderDocList(data.documents);
}

// ═══════════════════════════════════════════════════════════════
// Reload document list via AJAX
// ═══════════════════════════════════════════════════════════════
async function reloadDocList() {
  const res  = await fetch(`${ACTIONS_URL}?action=list&trip_id=${TRIP_ID}`);
  const data = await res.json();
  if (data.success) renderDocList(data.documents);
}

function renderDocList(docs) {
  const container = document.getElementById('doc-list-container');
  const countBadge = document.getElementById('doc-count');
  countBadge.textContent = `${docs.length} file${docs.length!==1?'s':''}`;

  if (!docs.length) {
    container.innerHTML = `<div style="text-align:center;padding:2rem 0;">
      <p style="font-size:1.8rem;">📂</p>
      <p class="muted">No documents found.</p>
    </div>`;
    return;
  }

  // Group by category
  const groups = {};
  docs.forEach(d => { (groups[d.category] = groups[d.category]||[]).push(d); });

  const catLabels = <?= json_encode($categoryLabels) ?>;

  let html = '';
  for (const [cat, catDocs] of Object.entries(groups)) {
    html += `<div class="category-header">${catLabels[cat] || cat}</div><div class="doc-list">`;
    catDocs.forEach(doc => { html += buildDocRow(doc); });
    html += '</div>';
  }
  container.innerHTML = html;

  // Re-bind dynamic buttons
  bindDocListEvents();
}

function buildDocRow(doc) {
  const icon       = fileIcon(doc.file_name);
  const size       = formatSize(parseInt(doc.file_size)||0);
  const activity   = doc.activity_title ? `· 📌 ${escHtml(doc.activity_title)}` : '';
  const uploadedBy = doc.uploaded_by_name ? `by ${escHtml(doc.uploaded_by_name)}` : '';
  const dateStr    = doc.uploaded_at ? new Date(doc.uploaded_at).toLocaleDateString() : '';

  return `<div class="doc-row" data-doc-id="${doc.doc_id}">
    <span class="doc-row__icon">${icon}</span>
    <div class="doc-row__info">
      <div class="doc-row__name" title="${escHtml(doc.file_name)}">${escHtml(doc.file_name)}</div>
      <div class="doc-row__meta">${size} ${uploadedBy} · ${dateStr} ${activity}</div>
    </div>
    <div class="doc-row__actions">
      <a href="../../controller/document_actions.php?action=download&doc_id=${doc.doc_id}"
         class="btn btn--sm btn--secondary" title="Download" download>⬇</a>
      <button type="button" class="btn btn--sm btn--ghost attach-btn"
              data-doc-id="${doc.doc_id}"
              data-activity-id="${doc.activity_id || ''}"
              title="Link to activity">📌</button>
      <button type="button" class="btn btn--sm btn--danger delete-btn"
              data-doc-id="${doc.doc_id}" title="Delete">🗑</button>
    </div>
  </div>`;
}

function bindDocListEvents() {
  document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', () => deleteDoc(btn.dataset.docId, btn));
  });
  document.querySelectorAll('.attach-btn').forEach(btn => {
    btn.addEventListener('click', () => openAttachModal(btn.dataset.docId, btn.dataset.activityId));
  });
}

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Bind initial server-rendered rows
bindDocListEvents();

// Also bind delete/attach from server-rendered HTML (via event delegation fallback)
document.addEventListener('click', e => {
  const db = e.target.closest('.delete-btn');
  if (db && !db.__bound) { db.__bound = true; deleteDoc(db.dataset.docId, db); }
  const ab = e.target.closest('.attach-btn');
  if (ab && !ab.__bound) { ab.__bound = true; openAttachModal(ab.dataset.docId, ab.dataset.activityId); }
});
</script>

</body>
</html>

<?php
// ═══════════════════════════════════════════════════════════════
// PHP helper — render a single doc row (used in initial page load)
// ═══════════════════════════════════════════════════════════════
function renderDocRow(array $doc, int $trip_id): string {
    $icon        = DocumentController::fileIcon($doc['file_name']);
    $size        = DocumentController::formatSize((int)$doc['file_size']);
    $activity    = !empty($doc['activity_title']) ? '· 📌 ' . htmlspecialchars($doc['activity_title']) : '';
    $uploadedBy  = !empty($doc['uploaded_by_name']) ? 'by ' . htmlspecialchars($doc['uploaded_by_name']) : '';
    $dateStr     = !empty($doc['uploaded_at'])
                    ? date('M j, Y', strtotime($doc['uploaded_at']))
                    : '';

    return '<div class="doc-row" data-doc-id="' . $doc['doc_id'] . '">
        <span class="doc-row__icon">' . $icon . '</span>
        <div class="doc-row__info">
          <div class="doc-row__name" title="' . htmlspecialchars($doc['file_name']) . '">'
              . htmlspecialchars($doc['file_name']) . '</div>
          <div class="doc-row__meta">' . $size . ' ' . $uploadedBy . ' · ' . $dateStr . ' ' . $activity . '</div>
        </div>
        <div class="doc-row__actions">
          <a href="../../controller/document_actions.php?action=download&doc_id=' . $doc['doc_id'] . '"
             class="btn btn--sm btn--secondary" title="Download" download>⬇</a>
          <button type="button" class="btn btn--sm btn--ghost attach-btn"
                  data-doc-id="' . $doc['doc_id'] . '"
                  data-activity-id="' . ($doc['activity_id'] ?? '') . '"
                  title="Link to activity">📌</button>
          <button type="button" class="btn btn--sm btn--danger delete-btn"
                  data-doc-id="' . $doc['doc_id'] . '" title="Delete">🗑</button>
        </div>
    </div>';
}
?>