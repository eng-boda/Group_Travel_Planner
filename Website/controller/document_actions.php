<?php
/**
 * document_actions.php
 * Handles all AJAX requests from documents.php
 * Expects:  POST action = upload | delete | attach | download | export_zip | search
 */

require_once __DIR__ . '/AuthController.php';
require_once __DIR__ . '/DocumentController.php';

header('Content-Type: application/json');

$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$currentUser = $auth->getCurrentUser();
$user_id     = (int)$currentUser->user_id;
$action      = $_POST['action'] ?? $_GET['action'] ?? '';
$docCtrl     = new DocumentController();

switch ($action) {

    // ------------------------------------------------------------------
    case 'upload':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { bad(); }
        $result = $docCtrl->upload($_POST, $_FILES, $user_id);
        echo json_encode($result);
        break;

    // ------------------------------------------------------------------
    case 'delete':
        $doc_id = (int)($_POST['doc_id'] ?? 0);
        if (!$doc_id) { bad('Missing doc_id'); }
        echo json_encode($docCtrl->delete($doc_id, $user_id));
        break;

    // ------------------------------------------------------------------
    case 'attach':
        $doc_id      = (int)($_POST['doc_id'] ?? 0);
        $activity_id = !empty($_POST['activity_id']) ? (int)$_POST['activity_id'] : null;
        if (!$doc_id) { bad('Missing doc_id'); }
        echo json_encode($docCtrl->attachToActivity($doc_id, $activity_id, $user_id));
        break;

    // ------------------------------------------------------------------
    case 'download':
        // Not JSON — sends binary file directly
        $doc_id = (int)($_GET['doc_id'] ?? 0);
        if (!$doc_id) { http_response_code(400); exit('Missing doc_id'); }
        $docCtrl->download($doc_id);
        break;

    // ------------------------------------------------------------------
    case 'export_zip':
        $trip_id = (int)($_GET['trip_id'] ?? 0);
        if (!$trip_id) { http_response_code(400); exit('Missing trip_id'); }
        $docCtrl->exportZip($trip_id);
        break;

    // ------------------------------------------------------------------
    case 'search':
        $trip_id = (int)($_POST['trip_id'] ?? 0);
        $query   = trim($_POST['query'] ?? '');
        $docs    = $docCtrl->search($trip_id, $query);
        echo json_encode(['success' => true, 'documents' => $docs]);
        break;

    // ------------------------------------------------------------------
    case 'list':
        $trip_id = (int)($_GET['trip_id'] ?? 0);
        $docs    = $docCtrl->getDocumentsByTrip($trip_id);
        echo json_encode(['success' => true, 'documents' => $docs]);
        break;

    default:
        bad('Unknown action');
}

function bad(string $msg = 'Bad request'): void {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}
?>