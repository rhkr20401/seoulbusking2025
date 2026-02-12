<?php
/**
 * 공연 예약 시스템 - 예약 취소 처리 API
 * 
 * 기능:
 * - 로그인 확인
 * - 본인 예약만 취소 가능
 * - 예약 당일에만 취소 가능
 * - 취소 가능 시간 체크 (10:00 ~ 18:00)
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

// 요청 파라미터
$schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;

// 필수 파라미터 체크
if ($schedule_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => '취소할 예약 정보가 필요합니다.'
    ]);
    exit;
}

// 취소 시간 체크 (10:00 ~ 18:00)
$current_time = date('H:i');
$current_hour = intval(date('H'));
$current_minute = intval(date('i'));
$time_formatted = date('Y-m-d H:i:s');

// 디버깅용 시간 정보를 포함하기 위한 변수
$debug_info = [];
$debug_info['current_time'] = $current_time;
$debug_info['server_time'] = $time_formatted;

// 10시 이전이거나 18시 이후인 경우 취소 불가능
if ($current_hour < 10 || ($current_hour == 18 && $current_minute > 0) || $current_hour > 18) {
    echo json_encode([
        'success' => false,
        'message' => '예약 취소는 10:00~18:00 사이에만 가능합니다.',
        'debug' => $debug_info
    ]);
    exit;
}

// 예약 정보 확인
$stmt = $conn->prepare("
    SELECT r.id, r.team_id, r.status, r.created_at, s.schedule_date, s.time_start, s.time_end, l.name as location_name
    FROM 2025reservations r
    JOIN 2025schedules s ON r.schedule_id = s.id
    JOIN 2025location l ON s.location_id = l.id
    WHERE r.schedule_id = ? AND (r.status = 'approved' OR r.status = 'pending')
");
$stmt->bind_param('i', $schedule_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => '해당 예약 정보를 찾을 수 없습니다.',
        'code' => 'not_found'
    ]);
    $stmt->close();
    exit;
}

$reservation = $result->fetch_assoc();
$stmt->close();

// 본인 예약만 취소 가능
if ($reservation['team_id'] != $team_idx) {
    echo json_encode([
        'success' => false,
        'message' => '본인이 예약한 일정만 취소할 수 있습니다.',
        'code' => 'not_owner'
    ]);
    exit;
}

// 예약 당일에만 취소 가능 - 로직 수정
// 공연 날짜가 아닌 예약을 한 날짜와 오늘이 동일한지 확인
$today = date('Y-m-d');
$created_date = date('Y-m-d', strtotime($reservation['created_at']));

// 디버깅용 시간 정보 추가
$debug_info['today'] = $today;
$debug_info['created_date'] = $created_date;

if ($created_date != $today) {
    echo json_encode([
        'success' => false,
        'message' => '예약 취소는 예약한 당일에만 가능합니다.',
        'code' => 'not_same_day',
        'debug' => $debug_info
    ]);
    exit;
}

// 예약 취소 처리
$stmt = $conn->prepare("UPDATE 2025reservations SET status = 'canceled', updated_at = NOW() WHERE id = ?");
$stmt->bind_param('i', $reservation['id']);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => '예약이 취소되었습니다.',
        'schedule' => [
            'date' => $reservation['schedule_date'],
            'time' => substr($reservation['time_start'], 0, 5) . '~' . substr($reservation['time_end'], 0, 5),
            'location' => $reservation['location_name']
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => '예약 취소 처리 중 오류가 발생했습니다: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?> 