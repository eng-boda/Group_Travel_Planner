<?php
require_once __DIR__ . '/../../controller/AuthController.php';

$auth = new AuthController();
$auth->logout();

header('Location: login.php');
exit;