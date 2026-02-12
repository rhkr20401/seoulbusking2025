<?php
/**
 * 공연 일정 데이터를 제공하는 API
 * 
 * 사용 예:
 * - 특정 년월 일정 가져오기: get_schedules.php?date=202504
 * - 특정 장소 일정 가져오기: get_schedules.php?date=202504&location_id=1
 * - 장소 목록만 가져오기: get_schedules.php?date=202504&getLocations=true
 * 
 * 모든 일정에 예약 상태 정보가 포함됩니다. (예약 가능 여부)
 */

// 디버깅 모드 - 개발 완료 후 false로 변경
$debug = true;

header('Content-Type: application/json; charset=UTF-8');

// DB 연결 정보 가져오기
include "db_2025.php";

// 입력 파라미터 받기
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m');
$location_id = isset($_GET['location_id']) ? intval($_GET['location_id']) : 0;
$getLocations = isset($_GET['getLocations']) && $_GET['getLocations'] === 'true';

if ($debug) {
    error_log("Requested date: $date, location_id: $location_id");
}

// 날짜 형식 처리 - YYYYMM 형식 지원
if (preg_match('/^\d{6}$/', $date)) {
    // YYYYMM 형식을 YYYY-MM 형식으로 변환
    $year = substr($date, 0, 4);
    $month = substr($date, 4, 2);
    $date = "$year-$month";
} elseif (!preg_match('/^\d{4}-\d{2}(-\d{2})?$/', $date)) {
    // 기본 날짜 형식으로 변환 시도
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        // 변환 실패시 현재 년월로 대체
        $date = date('Y-m');
    } else {
        $date = date('Y-m', $timestamp);
    }
}

// 년월 추출 (YYYY-MM 또는 YYYY-MM-DD에서)
$dateParts = explode('-', $date);
$year = $dateParts[0];
$month = $dateParts[1];

// 해당 월의 시작일과 마지막 일 계산
$startDate = "$year-$month-01";
$lastDay = date('t', strtotime($startDate));
$endDate = "$year-$month-$lastDay";

if ($debug) {
    error_log("Date range: $startDate to $endDate");
}

// 하위 호환성 유지 (이전 place 파라미터 지원)
if (!$location_id && isset($_GET['place'])) {
    $placeName = $_GET['place'];
    // place 이름으로 location_id 조회
    $placeStmt = $conn->prepare("SELECT id FROM `2025location` WHERE name = ?");
    if ($placeStmt) {
        $placeStmt->bind_param("s", $placeName);
        if ($placeStmt->execute()) {
            $placeResult = $placeStmt->get_result();
            if ($row = $placeResult->fetch_assoc()) {
                $location_id = $row['id'];
            }
        }
        $placeStmt->close();
    }
}

// 장소 목록만 요청된 경우
if ($getLocations) {
    $query = "SELECT l.id, l.name, l.station_info, l.address, l.map_link
              FROM `2025location` l
              INNER JOIN `2025schedules` s ON l.id = s.location_id
              WHERE s.schedule_date BETWEEN ? AND ?
              AND s.is_delete = 0
              GROUP BY l.id, l.name
              ORDER BY l.name";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['error' => "쿼리 준비 중 오류가 발생했습니다: " . $conn->error]);
        $conn->close();
        exit;
    }
    
    $stmt->bind_param("ss", $startDate, $endDate);
    if (!$stmt->execute()) {
        echo json_encode(['error' => "쿼리 실행 중 오류가 발생했습니다: " . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    $result = $stmt->get_result();
    
    $locations = [];
    while ($row = $result->fetch_assoc()) {
        $locations[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'station_info' => $row['station_info'],
            'address' => $row['address'],
            'map_link' => $row['map_link']
        ];
    }
    
    echo json_encode($locations);
    $stmt->close();
    $conn->close();
    exit;
}

// 일정 조회용 쿼리 준비
// 기본 쿼리 (예약 정보 포함)
$baseQuery = "SELECT s.id, s.schedule_date, TIME_FORMAT(s.time_start, '%H:%i') as time_start, 
              TIME_FORMAT(s.time_end, '%H:%i') as time_end, s.location_id, l.name as place_name,
              s.location_detail_name, s.event_name, r.team_id as reserved_team_id,
              s.is_active, s.cancel_reason";

$baseQuery .= " FROM `2025schedules` s
               LEFT JOIN `2025location` l ON s.location_id = l.id
               LEFT JOIN `2025reservations` r ON s.id = r.schedule_id AND (r.status = 'approved' OR r.status = 'pending')";

// WHERE 절 구성
$whereClause = " WHERE s.schedule_date BETWEEN ? AND ? AND s.is_delete = 0";

if ($debug) {
    error_log("Date range: $startDate to $endDate");
    error_log("Query: $baseQuery $whereClause");
}

$params = [$startDate, $endDate];
$types = "ss";

// 특정 장소 ID가 제공된 경우, 해당 장소로 필터링
if ($location_id > 0) {
    $whereClause .= " AND s.location_id = ?";
    $params[] = $location_id;
    $types .= "i";
}

// 정렬 추가
$orderClause = " ORDER BY s.schedule_date, s.time_start, l.name";

// 최종 쿼리 조합
$query = $baseQuery . $whereClause . $orderClause;

// 조회 결과를 저장할 배열
$schedules = [];

// 쿼리 실행
$stmt = $conn->prepare($query);
if (!$stmt) {
    $response = [
        'success' => false,
        'message' => 'Database error: ' . $conn->error,
        'schedules' => []
    ];
    if ($debug) {
        error_log("DB Error: " . $conn->error);
    }
} else {
    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        $response = [
            'success' => false,
            'message' => 'Query execution failed: ' . $stmt->error,
            'schedules' => []
        ];
        if ($debug) {
            error_log("Query execution error: " . $stmt->error);
        }
    } else {
        $result = $stmt->get_result();
        
        // 디버깅: 결과 행 수 로깅
        if ($debug) {
            error_log("Query result rows: " . $result->num_rows);
        }
        
        // 결과를 배열로 변환
        while ($row = $result->fetch_assoc()) {
            $schedule = [
                'id' => $row['id'],
                'date' => $row['schedule_date'],
                'time_start' => $row['time_start'],
                'time_end' => $row['time_end'],
                'location_id' => $row['location_id'],
                'place' => $row['place_name'],
                'location_detail_name' => $row['location_detail_name'],
                'event_name' => $row['event_name'],
                'reserved_team_id' => $row['reserved_team_id'],
                'is_active' => intval($row['is_active']),
                'cancel_reason' => $row['cancel_reason']
            ];

            // 공연팀 장르(cat) 가져오기
            if (!empty($row['reserved_team_id'])) {
                $team_idx = intval($row['reserved_team_id']);
                $stmtTeam = $conn->prepare("SELECT cat FROM 2025team WHERE idx = ?");
                $stmtTeam->bind_param("i", $team_idx);
                $stmtTeam->execute();
                $resultTeam = $stmtTeam->get_result();
                $teamData = $resultTeam->fetch_assoc();
                $schedule['genre'] = $teamData['cat'] ?? null;  // 여기!
            } else {
                $schedule['genre'] = null;  // 여기!
            }

            $schedules[] = $schedule;
        }
        
        $response = [
            'success' => true,
            'message' => '',
            'year' => $year,
            'month' => $month,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'location_id' => $location_id,
            'schedules' => $schedules
        ];
    }
    
    $stmt->close();
}

// 데이터베이스 연결 닫기
$conn->close();

// 디버깅: 응답 데이터 로깅
if ($debug) {
    error_log("Response contains " . count($schedules) . " schedules");
}

// JSON 응답 출력
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); 