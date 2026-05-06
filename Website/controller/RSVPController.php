<?php
session_start();
require_once '../model/rsvp.php';

header('Content-Type: application/json');

// --- معالجة البيانات القادمة بصيغة JSON (عشان Itinerary JS) ---
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    if (isset($input['activity_id'])) $_POST['activity_id'] = $input['activity_id'];
    if (isset($input['response'])) $_POST['response'] = $input['response'];
}

// 1. معالجة طلب الـ GET (لجلب الأعداد والأسماء)
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['activity_id'])) {
    $activity_id = (int) $_GET['activity_id'];
    $rsvp = new rsvp();
    
    $data = $rsvp->getActivityAttendanceDetails($activity_id); 
    
    $mine = isset($_SESSION['user_id'])
            ? $rsvp->getMyResponse($activity_id, $_SESSION['user_id'])
            : null;

    echo json_encode([
        "success" => true, 
        "counts" => $data['counts'], 
        "names" => $data['names'], 
        "mine" => $mine
    ]);
    exit;
}

// 2. معالجة طلب الـ POST (لحفظ الرد الجديد)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // التأكد من أن المستخدم مسجل دخوله
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["success" => false, "error" => "User not logged in"]);
        exit;
    }

    // التأكد من وصول البيانات (سواء من Form أو JSON)
    if (!isset($_POST['activity_id']) || !isset($_POST['response'])) {
        echo json_encode(["success" => false, "error" => "Missing data", "received" => $_POST]);
        exit;
    }

    $activity_id = (int)$_POST['activity_id'];
    $response = $_POST['response'];
    $user_id = $_SESSION['user_id'];

    $rsvp = new rsvp();
    $success = $rsvp->createRSVP($activity_id, $user_id, $response);

    echo json_encode(["success" => $success]);
    exit;
}
?>