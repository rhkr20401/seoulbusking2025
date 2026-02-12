<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$db_host = "localhost";  
$db_user = "seoulbusking2025";     
$db_pass = "creacon0312**";     
$db_name = "seoulbusking2025";      

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
mysqli_set_charset($conn, "utf8mb4");

if (!$conn) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["error" => "DB 연결 실패", "detail" => mysqli_connect_error()]);
    exit;
}
?>