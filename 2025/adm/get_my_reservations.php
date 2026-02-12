<?php
/**
 * 공연 예약 시스템 - 내 예약 조회 API
 * 
 * 기능:
 * - 로그인한 사용자의 예약 내역 조회
 * - 예약 상태별 필터링(전체, 예약중, 취소됨)
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// 타임존 설정 (한국 시간)
date_default_timezone_set('Asia/Seoul');

// 로그인 확인
if (!isset($_SESSION['team_idx']) || !isset($_SESSION['team_number'])) {
    echo json_encode([
        'success' => false,
        'message' => '로그인이 필요합니다.'
    ]);
    exit;
}

// 데이터베이스 연결
include_once('db_2025.php');

// 사용자 정보
$team_idx = $_SESSION['team_idx'];
$team_number = $_SESSION['team_number'];
$team_name = isset($_SESSION['team_name']) ? $_SESSION['team_name'] : '';

// 요청 파라미터
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

// 쿼리 빌드
$query = "SELECT 
              r.id, 
              r.schedule_id, 
              r.status, 
              r.notes,
              r.created_at,
              s.schedule_date, 
              s.time_start, 
              s.time_end, 
              l.name AS location_name,
              l.id AS location_id
          FROM 
              2025reservations r
          JOIN 
              2025schedules s ON r.schedule_id = s.id
          JOIN 
              2025location l ON s.location_id = l.id
          WHERE 
              r.team_id = ?";

// 상태별 필터링
if ($status === 'approved') {
    $query .= " AND r.status = 'approved'";
} else if ($status === 'pending') {
    $query .= " AND r.status = 'pending'";
} else if ($status === 'canceled') {
    $query .= " AND r.status = 'canceled'";
}

$query .= " AND s.is_active = 1 AND s.is_delete = 0";
$query .= " ORDER BY s.schedule_date DESC, s.time_start ASC";

// 쿼리 실행
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $team_idx);
$stmt->execute();
$result = $stmt->get_result();

// 결과 처리
$reservations = [];
while ($row = $result->fetch_assoc()) {
    // 현재 시간 정보
    $current_hour = intval(date('H'));
    $current_minute = intval(date('i'));
    $today = date('Y-m-d');
    
    // 예약 생성 날짜 추출 (created_at 기준)
    $created_date = date('Y-m-d', strtotime($row['created_at']));
    
    // 시간 조건: 10시 이상 18시 이하 (18시 정각까지 포함)
    $is_valid_time = ($current_hour >= 10) && 
                     ($current_hour < 18 || ($current_hour == 18 && $current_minute == 0));
    
    $reservations[] = [
        'id' => $row['id'],
        'schedule_id' => $row['schedule_id'],
        'date' => $row['schedule_date'],
        'time' => substr($row['time_start'], 0, 5) . '~' . substr($row['time_end'], 0, 5),
        'location' => $row['location_name'],
        'location_id' => $row['location_id'],
        'status' => $row['status'],
        'notes' => $row['notes'],
        'created_at' => $row['created_at'],
        // 예약 취소 가능 여부 확인 (예약한 당일이면서 10시~18시 사이, 상태가 approved 또는 pending)
        'can_cancel' => (
            ($row['status'] === 'approved' || $row['status'] === 'pending') && 
            $created_date === $today &&
            $is_valid_time
        )
    ];
}

// 예약 정보와 함께 사용자 정보도 제공
echo json_encode([
    'success' => true,
    'user' => [
        'team_idx' => $team_idx,
        'team_number' => $team_number,
        'team_name' => $team_name
    ],
    'reservations' => $reservations,
    'total_count' => count($reservations),
    'active_count' => count(array_filter($reservations, function($r) { 
        return $r['status'] === 'approved'; 
    })),
    'max_allowed' => 2,
    'available_count' => 2 - count(array_filter($reservations, function($r) { 
        return $r['status'] === 'approved'; 
    }))
]);

$stmt->close();
$conn->close();
?> 