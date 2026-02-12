<?php
/**
 * 공연 예약 시스템 - 예약 신청 처리 API
 * 
 * 기능:
 * - 로그인 확인
 * - 예약 가능 시간 체크 (10:00 ~ 18:00)
 * - 최대 예약 건수 체크 (1차 2건 / 2차 1건 => 총 3건)
 * - 예약 중복 체크
 * - 예약 데이터 저장
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
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// 필수 파라미터 체크
if ($schedule_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => '예약할 일정 정보가 필요합니다.'
    ]);
    exit;
}

// 예약 시간 체크 (10:00 ~ 18:00)
$current_time = date('H:i');
$current_hour = intval(date('H'));
$current_minute = intval(date('i'));
$time_formatted = date('Y-m-d H:i:s');

// 디버깅용 시간 정보를 포함하기 위한 변수
$debug_info = [];
$debug_info['current_time'] = $current_time;
$debug_info['server_time'] = $time_formatted;

// 10시 이전이거나 18시 이후인 경우 예약 불가능
if ($current_hour < 10 || ($current_hour == 18 && $current_minute > 0) || $current_hour > 18) {
    echo json_encode([
        'success' => false,
        'message' => '예약은 10:00~18:00 사이에만 가능합니다.',
        'debug' => $debug_info
    ]);
    exit;
}

// 일정 정보 확인
$stmt = $conn->prepare("SELECT s.schedule_date, s.time_start, s.time_end, l.name as location_name 
                        FROM 2025schedules s
                        JOIN 2025location l ON s.location_id = l.id 
                        WHERE s.id = ? AND s.is_active = 1 AND s.is_delete = 0");
$stmt->bind_param('i', $schedule_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => '예약 가능한 일정이 아닙니다.'
    ]);
    $stmt->close();
    exit;
}

$schedule = $result->fetch_assoc();
$stmt->close();

// 과거 일정 체크
if (strtotime($schedule['schedule_date']) < strtotime(date('Y-m-d'))) {
    echo json_encode([
        'success' => false,
        'message' => '지난 일정은 예약할 수 없습니다.'
    ]);
    exit;
}

// 이미 예약된 일정인지 확인
$stmt = $conn->prepare("SELECT team_id FROM 2025reservations 
WHERE schedule_id = ? AND (status = 'approved' OR status = 'pending')");
$stmt->bind_param('i', $schedule_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $reservation = $result->fetch_assoc();
    
    // 자신이 예약한 일정인지 확인
    if ($reservation['team_id'] == $team_idx) {
        echo json_encode([
            'success' => false,
            'message' => '이미 예약하신 일정입니다.',
            'already_mine' => true
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '이미 다른 팀이 예약한 일정입니다.',
            'already_mine' => false
        ]);
    }
    
    $stmt->close();
    exit;
}
$stmt->close();

// 예약 일정의 월 가져오기
$schedule_month = date('m', strtotime($schedule['schedule_date']));
$schedule_year = date('Y', strtotime($schedule['schedule_date']));

// 최대 예약 건수 체크 (월별 2건)
$stmt = $conn->prepare("SELECT COUNT(*) as reservation_count 
                        FROM 2025reservations r
                        JOIN 2025schedules s ON r.schedule_id = s.id
                        WHERE r.team_id = ? AND r.status = 'approved'
                        AND s.is_active = 1 AND s.is_delete = 0
                        AND MONTH(s.schedule_date) = ? 
                        AND YEAR(s.schedule_date) = ?");
$stmt->bind_param('iii', $team_idx, $schedule_month, $schedule_year);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

// 1차 신청 예약 건수 2건
// if ($row['reservation_count'] >= 2) {
//     echo json_encode([
//         'success' => false,
//         'message' => '1차 신청 최대 예약 건수를 초과했습니다.'
//     ]);
//     exit;
// }

// 2차 신청 예약 건수 +1건 = 총 3건
if ($row['reservation_count'] >= 3) {
    echo json_encode([
        'success' => false,
        'message' => '해당 월 최대 예약 건수(3건)를 초과했습니다.'
    ]);
    exit;
}

// 신청 제한
// if ($row['reservation_count'] >= 0) {
//     echo json_encode([
//         'success' => false,
//         'message' => '신청 기간이 아닙니다.'
//     ]);
//     exit;
// }

// 예약 데이터 저장
$stmt = $conn->prepare("INSERT INTO 2025reservations 
                        (team_id, schedule_id, status, notes, created_at) 
                        VALUES (?, ?, 'approved', ?, NOW())");
$stmt->bind_param('iis', $team_idx, $schedule_id, $notes);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => '예약이 완료되었습니다.',
        'schedule' => [
            'date' => $schedule['schedule_date'],
            'time' => substr($schedule['time_start'], 0, 5) . '~' . substr($schedule['time_end'], 0, 5),
            'location' => $schedule['location_name']
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => '예약 처리 중 오류가 발생했습니다: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?> 