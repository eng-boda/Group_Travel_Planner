<?php

require_once __DIR__ . '/../model/rsvp.php';
require_once __DIR__ . '/../controller/AuthController.php';

session_start();

$auth = new AuthController();
$currentUser = $auth->getCurrentUser();

if (!$currentUser) {
    die("User not logged in properly");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $rsvp = new RSVP();

    $rsvp->activity_id = $_POST['activity_id'];
    $rsvp->user_id = $currentUser->user_id;  // use the object like everywhere else
    $rsvp->response = $_POST['response'];

    if ($rsvp->saveResponse()) {
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    } else {
        echo "Failed";
    }
}