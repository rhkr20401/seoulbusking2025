<?php
/**
 * 공연 예약 시스템 - 장소 정보 조회 API
 * 
 * 기능:
 * - 장소 ID를 받아 해당 장소의 상세 정보 제공
 */

header('Content-Type: application/json; charset=utf-8');

// 데이터베이스 연결
include_once('db_2025.php');

// 요청 파라미터
$location_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 필수 파라미터 체크
if ($location_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => '장소 ID가 필요합니다.'
    ]);
    exit;
}

// 장소 정보 조회
$stmt = $conn->prepare("SELECT id, number, name, station_info, address, map_link 
                       FROM 2025location 
                       WHERE id = ?");
$stmt->bind_param('i', $location_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => '해당 장소 정보를 찾을 수 없습니다.'
    ]);
} else {
    $location = $result->fetch_assoc();
    echo json_encode($location);
}

$stmt->close();
$conn->close();
?> 