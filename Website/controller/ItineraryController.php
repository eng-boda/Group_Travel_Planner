<?php
header('Content-Type: application/json');
require_once '../model/activity.php';

$activityModel = new activity();

// بنفترض إن الـ trip_id مبعوت في الـ URL
if (isset($_GET['trip_id'])) {
    $tripId = intval($_GET['trip_id']);
    $list = $activityModel->getActivitiesByTrip($tripId);

    if ($list) {
        echo json_encode(["success" => true, "activities" => $list]);
    } else {
        echo json_encode(["success" => false, "activities" => []]);
    }
}
?>