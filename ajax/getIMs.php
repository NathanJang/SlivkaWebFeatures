<?php
header('Content-type: text/html; charset=utf-8');
require_once "./PointsCenter.php";
$points_center = new PointsCenter($_GET['qtr']);
$IMs = $points_center->getIMs($_GET['team']);

echo json_encode($IMs);

?>