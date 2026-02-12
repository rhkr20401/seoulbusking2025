<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include "db_2025.php";

header("Content-Type: application/json; charset=utf-8");
// 데이터 가져오기
$sql = "SELECT * FROM 2025location ORDER BY id ASC";
$result = $conn->query($sql);

$locations = [];

while ($row = $result->fetch_assoc()) {
    $locations[] = $row;
}

// JSON 형식으로 출력
echo json_encode($locations, JSON_UNESCAPED_UNICODE);

$conn->close();
?>