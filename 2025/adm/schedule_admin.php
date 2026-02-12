<?php
// 데이터베이스 연결
include "db_2025.php";

// 시간대 설정을 한국 시간으로
date_default_timezone_set('Asia/Seoul');

// 기본 년월 설정
$currentDate = isset($_GET['date']) ? $_GET['date'] : date('Ym');
$year = substr($currentDate, 0, 4);
$month = substr($currentDate, 4, 2);

// 해당 월의 시작일과 종료일 계산
$start_date = "{$year}-{$month}-01";
$end_date = date('Y-m-t', strtotime($start_date));

// 액션 처리 (추가, 수정, 삭제, 상태변경)
$message = "";

// 소프트 삭제 액션
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("UPDATE `2025schedules` SET is_delete = 1 WHERE id = ?");
    if (!$stmt) {
        echo "<script>alert('쿼리 준비 중 오류가 발생했습니다: " . $conn->error . "');</script>";
    } else {
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            echo "<script>alert('삭제 중 오류가 발생했습니다: " . $stmt->error . "');</script>";
        } else {
            echo "<script>
                alert('일정이 삭제 처리되었습니다.');
                window.location.href = '?date=" . $currentDate . "';
            </script>";
            exit;
        }
        $stmt->close();
    }
}

// 활성화/비활성화 토글 액션
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $reason = isset($_GET['reason']) ? $_GET['reason'] : '';

    // 현재 상태 확인
    $checkStmt = $conn->prepare("SELECT is_active FROM `2025schedules` WHERE id = ?");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    if ($row = $checkResult->fetch_assoc()) {
        $newStatus = $row['is_active'] ? 0 : 1;
        $statusText = $newStatus ? '활성화' : '비활성화';

        if ($newStatus == 0) {
            // 비활성화 시 취소 사유 저장
            $updateStmt = $conn->prepare("UPDATE `2025schedules` SET is_active = ?, cancel_reason = ? WHERE id = ?");
            $updateStmt->bind_param("isi", $newStatus, $reason, $id);
        } else {
            // 활성화 시 사유 비움
            $updateStmt = $conn->prepare("UPDATE `2025schedules` SET is_active = ?, cancel_reason = NULL WHERE id = ?");
            $updateStmt->bind_param("ii", $newStatus, $id);
        }

        if ($updateStmt->execute()) {
            echo "<script>
                alert('일정이 {$statusText} 처리되었습니다.');
                window.location.href = '?date=" . $currentDate . "';
            </script>";
            exit;
        } else {
            echo "<script>alert('상태 변경 중 오류가 발생했습니다.');</script>";
        }
        $updateStmt->close();
    }
    $checkStmt->close();
}

// 추가 또는 수정 액션
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 입력 데이터 유효성 검사
    $errors = [];
    
    // 날짜 선택 타입에 따른 처리
    $dates = [];
    
    // 디버깅 - POST 데이터 출력 (전체)
    debug_to_console("POST 데이터 전체:");
    debug_to_console($_POST);
    
    // 날짜 필드 값 추출 및 확인
    $multiple_dates = isset($_POST['multiple_dates']) ? trim($_POST['multiple_dates']) : '';
    
    debug_to_console("날짜 필드 데이터:");
    debug_to_console([
        'multiple_dates' => $multiple_dates
    ]);

    if (!empty($multiple_dates)) {
        // 복수 날짜
        $multiDates = explode(',', $multiple_dates);
        // 디버깅
        debug_to_console("날짜 모드: " . $multiple_dates);
        debug_to_console("날짜 배열:");
        debug_to_console($multiDates);
        
        // 빈 값 제거
        $multiDates = array_filter($multiDates, function($date) {
            return trim($date) !== '';
        });
        
        // 날짜 검증
        foreach ($multiDates as $date) {
            $date = trim($date);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $errors[] = "날짜는 YYYY-MM-DD 형식이어야 합니다. 잘못된 날짜: " . $date;
                break;
            }
        }
        $dates = $multiDates;
    }
    
    // 중복 날짜 제거
    $dates = array_unique($dates);
    
    // 디버깅 - 최종 날짜 배열
    debug_to_console("최종 날짜 배열:");
    debug_to_console($dates);
    debug_to_console("날짜 개수: " . count($dates));
    
    if (empty($dates)) {
        $errors[] = "날짜는 필수 입력 항목입니다.";
    }
    
    if (empty($_POST['time_start'])) {
        $errors[] = "시작 시간은 필수 입력 항목입니다.";
    }
    
    if (empty($_POST['time_end'])) {
        $errors[] = "종료 시간은 필수 입력 항목입니다.";
    }
    
    if (empty($_POST['location_id'])) {
        $errors[] = "장소는 필수 입력 항목입니다.";
    }
    
    // 시간 형식 검증
    if (!empty($_POST['time_start']) && !preg_match('/^\d{2}:\d{2}$/', $_POST['time_start'])) {
        $errors[] = "시작 시간은 HH:MM 형식이어야 합니다.";
    }
    
    if (!empty($_POST['time_end']) && !preg_match('/^\d{2}:\d{2}$/', $_POST['time_end'])) {
        $errors[] = "종료 시간은 HH:MM 형식이어야 합니다.";
    }
    
    if (empty($errors)) {
        $time_start = $_POST['time_start'];
        $time_end = $_POST['time_end'];
        $location_id = $_POST['location_id'];
        $event_name = isset($_POST['event_name']) ? trim($_POST['event_name']) : '';
        $location_detail_name = isset($_POST['location_detail_name']) ? trim($_POST['location_detail_name']) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (isset($_POST['id']) && is_numeric($_POST['id'])) {
            // 기존 일정 수정 (단일 날짜만 지원)
            $id = intval($_POST['id']);
            
            // 수정 모드에서는 단일 날짜만 사용
            if (empty($dates)) {
                $errors[] = "날짜는 필수 입력 항목입니다.";
            } else {
                $date = $dates[0];
                
                $stmt = $conn->prepare("UPDATE `2025schedules` SET schedule_date = ?, time_start = ?, time_end = ?, location_id = ?, event_name = ?, location_detail_name = ?, is_active = ? WHERE id = ?");
                if (!$stmt) {
                    echo "<script>alert('쿼리 준비 중 오류가 발생했습니다: " . $conn->error . "');</script>";
                } else {
                    $stmt->bind_param("sssisssi", $date, $time_start, $time_end, $location_id, $event_name, $location_detail_name, $is_active, $id);
                    if (!$stmt->execute()) {
                        echo "<script>alert('수정 중 오류가 발생했습니다: " . $stmt->error . "');</script>";
                    } else {
                    // 공연팀 변경/지정 또는 예약 취소
                    $reservation_id     = isset($_POST['reservation_id']) ? intval($_POST['reservation_id']) : 0;
                    $new_team_id        = isset($_POST['team_id']) ? intval($_POST['team_id']) : 0;
                    $cancel_reservation = isset($_POST['cancel_reservation']) && $_POST['cancel_reservation'] == '1';

                    if ($reservation_id > 0) {
                        // 기존 예약이 있는 경우: 취소 또는 팀 변경
                        if ($cancel_reservation) {
                            $cancelStmt = $conn->prepare("UPDATE `2025reservations` SET status = 'canceled' WHERE id = ?");
                            if ($cancelStmt) {
                                $cancelStmt->bind_param("i", $reservation_id);
                                $cancelStmt->execute();
                                $cancelStmt->close();
                            }
                        } elseif ($new_team_id > 0) {
                            $updateStmt = $conn->prepare("UPDATE `2025reservations` SET team_id = ? WHERE id = ?");
                            if ($updateStmt) {
                                $updateStmt->bind_param("ii", $new_team_id, $reservation_id);
                                $updateStmt->execute();
                                $updateStmt->close();
                            }
                        }
                    } else {
                        // 예약이 없었는데, 사용자가 팀을 선택한 경우: 새 예약 생성
                        if ($new_team_id > 0) {
                            // $id 는 위에서 이미 $_POST['id']로 받아온 '스케줄 PK'
                            $ins = $conn->prepare("
                                INSERT INTO `2025reservations` (schedule_id, team_id, status, created_at)
                                VALUES (?, ?, 'approved', NOW())
                            ");
                            if ($ins) {
                                $ins->bind_param("ii", $id, $new_team_id);
                                $ins->execute();
                                $ins->close();
                            }
                        }
                    }
                        $message = "일정이 성공적으로 수정되었습니다.";
                        // 리다이렉션을 통해 폼 재제출 방지
                        echo "<script>
                            alert('일정이 수정되었습니다.');
                            window.location.href = '?date=" . $currentDate . "';
                        </script>";
                        exit;
                    }
                    $stmt->close();
                }
            }
        } else {
            // 새 일정 추가 (다중 날짜 지원)
            $successCount = 0;
            $errorCount = 0;
            
            // 추가 디버깅 - 날짜 배열 정보 출력
            debug_to_console("추가할 날짜 목록:");
            debug_to_console($dates);
            
            // 트랜잭션 시작
            $conn->begin_transaction();
            debug_to_console("트랜잭션 시작");
            
            try {
                $stmt = $conn->prepare("INSERT INTO `2025schedules` (schedule_date, time_start, time_end, location_id, event_name, location_detail_name, is_active, is_delete) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
                if (!$stmt) {
                    debug_to_console("쿼리 준비 오류: " . $conn->error);
                    throw new Exception("쿼리 준비 중 오류가 발생했습니다: " . $conn->error);
                }
                
                debug_to_console("날짜 반복 처리 시작 - 날짜 수: " . count($dates));
                foreach ($dates as $index => $date) {
                    debug_to_console("처리 중인 날짜 #{$index}: {$date}");
                    $stmt->bind_param("sssissi", $date, $time_start, $time_end, $location_id, $event_name, $location_detail_name, $is_active);
                    
                    debug_to_console("쿼리 실행 준비: " . json_encode([
                        'date' => $date,
                        'time_start' => $time_start,
                        'time_end' => $time_end,
                        'location_id' => $location_id,
                        'event_name' => $event_name,
                        'location_detail_name' => $location_detail_name,
                        'is_active' => $is_active
                    ]));
                    
                    if (!$stmt->execute()) {
                        $errorCount++;
                        debug_to_console("일정 추가 실패: " . $date . " - " . $stmt->error);
                    } else {
                        $successCount++;
                        debug_to_console("일정 추가 성공: " . $date . " - Last ID: " . $conn->insert_id);
                    }
                }
                
                $stmt->close();
                
                // 트랜잭션 커밋
                debug_to_console("트랜잭션 커밋 시도 - 성공: {$successCount}, 실패: {$errorCount}");
                $conn->commit();
                debug_to_console("트랜잭션 커밋 완료");
                
                if ($successCount > 0) {
                    $message = "{$successCount}개의 일정이 성공적으로 추가되었습니다.";
                    if ($errorCount > 0) {
                        $message .= " {$errorCount}개의 일정 추가에 실패했습니다.";
                    }
                    
                    // 리다이렉션
                    echo "<script>
                        alert('{$message}');
                        window.location.href = '?date=" . $currentDate . "';
                    </script>";
                    exit;
                } else {
                    echo "<script>alert('일정 추가에 실패했습니다.');</script>";
                }
                
            } catch (Exception $e) {
                // 트랜잭션 롤백
                $conn->rollback();
                echo "<script>alert('오류가 발생했습니다: " . $e->getMessage() . "');</script>";
            }
        }
    } else {
        // 오류 메시지 출력
        echo "<script>alert('입력 오류: " . implode("\\n", $errors) . "');</script>";
    }
}

// 장소 목록 조회 (드롭다운용)
$places = [];
$locationsData = []; // 장소 정보를 저장할 배열 (ID와 이름)

// 공연팀 목록 조회
$teamsData = [];
$teamStmt = $conn->prepare("SELECT idx, team FROM `2025team` ORDER BY team");
if ($teamStmt && $teamStmt->execute()) {
    $teamResult = $teamStmt->get_result();
    while ($teamRow = $teamResult->fetch_assoc()) {
        $teamsData[] = $teamRow;
    }
    $teamStmt->close();
}

// 방법 2: 2025location 테이블에서 장소 목록 가져오기
$locationStmt = $conn->prepare("SELECT id, name FROM `2025location` ORDER BY name");
if (!$locationStmt) {
    echo "<script>alert('2025location 테이블 장소 목록 쿼리 준비 중 오류가 발생했습니다: " . $conn->error . "');</script>";
} else {
    if (!$locationStmt->execute()) {
        echo "<script>alert('2025location 테이블 장소 목록 조회 중 오류가 발생했습니다: " . $locationStmt->error . "');</script>";
    } else {
        $locationResult = $locationStmt->get_result();
        while ($locationRow = $locationResult->fetch_assoc()) {
            $locationsData[] = [
                'id' => $locationRow['id'],
                'name' => $locationRow['name']
            ];
            $places[$locationRow['id']] = $locationRow['name']; // ID를 키로, 이름을 값으로 저장
        }
    }
    $locationStmt->close();
}

// 장소 목록 정렬
sort($places);

// 일정 데이터 조회
$query = "SELECT s.id, s.schedule_date, TIME_FORMAT(s.time_start, '%H:%i') AS time_start, 
           TIME_FORMAT(s.time_end, '%H:%i') AS time_end, s.location_id, l.name AS place_name,
           s.event_name, s.location_detail_name, s.is_active, s.is_delete, s.cancel_reason, r.id as reservation_id, r.status as reservation_status,
           r.created_at as reservation_date,
           t.team as team_name, t.number as team_number, t.phone as team_phone
           FROM `2025schedules` s
           LEFT JOIN `2025location` l ON s.location_id = l.id
           LEFT JOIN `2025reservations` r ON s.id = r.schedule_id AND (r.status = 'approved' OR r.status = 'pending')
           LEFT JOIN `2025team` t ON r.team_id = t.idx
           WHERE s.schedule_date BETWEEN ? AND ? AND s.is_delete = 0
           ORDER BY s.schedule_date, s.time_start, l.name";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo "<script>alert('쿼리 준비 중 오류가 발생했습니다: " . $conn->error . "');</script>";
    $result = null;
} else {
    $stmt->bind_param("ss", $start_date, $end_date);
    if (!$stmt->execute()) {
        echo "<script>alert('쿼리 실행 중 오류가 발생했습니다: " . $stmt->error . "');</script>";
        $result = null;
    } else {
        $result = $stmt->get_result();
    }
}

// 수정 모드인 경우 데이터 불러오기
$editMode = false;
$editData = [
    'id' => '',
    'schedule_date' => '',
    'time_start' => '',
    'time_end' => '',
    'location_id' => '',
    'event_name' => '',
    'location_detail_name' => '',
    'is_active' => 1
];

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $editQuery = "SELECT s.id, s.schedule_date, TIME_FORMAT(s.time_start, '%H:%i') AS time_start, 
                 TIME_FORMAT(s.time_end, '%H:%i') AS time_end, s.location_id, l.name AS place_name,
                 s.event_name, s.location_detail_name, s.is_active, s.is_delete,
                 r.id as reservation_id, r.status as reservation_status, t.idx as team_id, t.team as team_name
                 FROM `2025schedules` s
                 LEFT JOIN `2025location` l ON s.location_id = l.id
                 LEFT JOIN `2025reservations` r ON s.id = r.schedule_id AND (r.status = 'approved' OR r.status = 'pending')
                LEFT JOIN `2025team` t ON r.team_id = t.idx
                 WHERE s.id = ?";
    
    $editStmt = $conn->prepare($editQuery);
    if (!$editStmt) {
        echo "<script>alert('수정 데이터 쿼리 준비 중 오류가 발생했습니다: " . $conn->error . "');</script>";
    } else {
        $editStmt->bind_param("i", $editId);
        if (!$editStmt->execute()) {
            echo "<script>alert('수정 데이터 조회 중 오류가 발생했습니다: " . $editStmt->error . "');</script>";
        } else {
            $editResult = $editStmt->get_result();
            if ($editRow = $editResult->fetch_assoc()) {
                $editMode = true;
                $reservation_id = $editData['reservation_id'] ?? 0;
                $team_id = $editData['team_id'] ?? 0;

                $editData = $editRow;
            } else {
                echo "<script>
                    alert('해당 ID의 일정을 찾을 수 없습니다.');
                    window.location.href = '?date=" . $currentDate . "';
                </script>";
                exit;
            }
        }
        $editStmt->close();
    }
}

// 월 이름 (한글)
$monthNames = [
    '01' => '1월', '02' => '2월', '03' => '3월', '04' => '4월', 
    '05' => '5월', '06' => '6월', '07' => '7월', '08' => '8월', 
    '09' => '9월', '10' => '10월', '11' => '11월', '12' => '12월'
];

$monthName = $monthNames[$month] ?? $month.'월';

// 다음 달과 이전 달 계산
$prevMonth = date('Ym', strtotime($start_date . ' -1 month'));
$nextMonth = date('Ym', strtotime($start_date . ' +1 month'));

// 디버깅 함수 추가
function debug_to_console($data) {
    echo "<script>console.log(" . json_encode($data) . ");</script>";
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <title>2025 서울거리공연 - 일정 관리</title>
    <style>
        body {
            font-family: 'Malgun Gothic', sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            background-color: #d4edda;
            border-radius: 4px;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .nav {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .nav a {
            padding: 8px 16px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .month-nav {
            text-align: center;
            margin-bottom: 20px;
        }
        .month-nav a {
            margin: 0 10px;
            text-decoration: none;
            color: #007bff;
        }
        form {
            margin-bottom: 30px;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 5px;
        }
        .form-row {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="date"],
        input[type="time"],
        input[type="text"],
        select {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .checkbox-row {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .checkbox-row label {
            display: inline;
            margin-left: 10px;
            font-weight: normal;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        /* 테이블 스타일 기본 */
        table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
            vertical-align: top;
        }
        
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        /* 열 너비 조정 */
        th:nth-child(1) { width: 13%; } /* 날짜 */
        th:nth-child(2), th:nth-child(3) { width: 7%; } /* 시작/종료 시간 */
        th:nth-child(4) { width: 15%; } /* 장소 */
        th:nth-child(5) { width: 10%; } /* 행사명 */
        th:nth-child(6) { width: 10%; } /* 행사명 */
        th:nth-child(7) { width: 6%; } /* 상태 */
        th:nth-child(8) { width: 20%; } /* 공연팀 */
        th:nth-child(9) { width: 12%; } /* 관리 */
        
        /* 버튼 스타일 */
        .edit-btn, .toggle-btn, .delete-btn {
            display: block;
            width: 95%;
            padding: 4px 0;
            margin: 0 auto 5px auto;
            text-decoration: none;
            color: white;
            border-radius: 3px;
            text-align: center;
            font-size: 12px;
            opacity: 0.85;
        }
        
        .edit-btn:hover, .toggle-btn:hover, .delete-btn:hover {
            opacity: 1;
        }
        
        .edit-btn {
            background-color: #007bff;
        }
        .delete-btn {
            background-color: #dc3545;
        }
        .toggle-btn {
            background-color: #6c757d;
        }
        
        /* 비활성화된 행 스타일 */
        .inactive {
            opacity: 0.7;
            background-color: #f8f9fa;
        }
        
        /* 상태 뱃지 스타일 */
        .status-badge {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 12px;
        }
        .active-badge {
            background-color: #28a745;
            color: white;
        }
        .inactive-badge {
            background-color: #dc3545;
            color: white;
        }
        
        /* 팀 정보 스타일 */
        .team-name {
            display: inline-block;
            font-size: 14px;
            font-weight: bold;
        }
        .team-pending {
            display: inline-block;
            font-size: 14px;
            font-weight: bold;
            color: #FFA500;
        }
        .team-contact, .reservation-date {
            display: block;
            font-size: 12px;
            color: #555;
            margin: 3px 0 3px 15px;
            position: relative;
        }
        .team-contact:before, .reservation-date:before {
            content: "•";
            position: absolute;
            left: -10px;
        }
        .no-reserve-badge {
            display: inline-block;
            color: #6c757d;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>2025 서울거리공연 - <?php echo $monthName; ?> 일정 관리</h1>
        
        <?php if (!empty($message)): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class="month-nav">
            <a href="?date=<?php echo $prevMonth; ?>">&laquo; 이전 달</a>
            <strong><?php echo $year.'년 '.$monthName; ?></strong>
            <a href="?date=<?php echo $nextMonth; ?>">다음 달 &raquo;</a>
        </div>
        
        <form method="post" action="" onsubmit="return validateForm()">
            <h2><?php echo $editMode ? '일정 수정' : '새 일정 추가'; ?></h2>
            
            <?php if ($editMode): ?>
                <input type="hidden" name="id" value="<?php echo $editData['id']; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <label for="date">날짜:</label>
                <?php if ($editMode): ?>
                    <input type="date" id="date_to_add" name="date_to_add" value="<?php echo $editData['schedule_date']; ?>" required>
                    <input type="hidden" id="multiple_dates" name="multiple_dates" value="<?php echo $editData['schedule_date']; ?>">
                <?php else: ?>
                    <!-- <div class="date-picker-container" style="display: flex; gap: 10px; margin-bottom: 10px;">
                        <input type="date" id="date_to_add" style="flex: 1;" value="<?php echo $editData['schedule_date']; ?>">
                        <button type="button" onclick="addDate()" style="width: 90px; background-color: #28a745; border-radius: 4px; border: none; color: white; cursor: pointer;">날짜 추가</button>
                    </div> -->
                    <!-- Flatpickr용 input -->
                    <input type="text" id="multi_date_picker" placeholder="날짜를 선택하세요" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <input type="hidden" id="multiple_dates" name="multiple_dates" value="<?php echo $editData['schedule_date'] ?? ''; ?>">

                    <div id="selected_dates" style="margin-top: 10px; border: 1px solid #ddd; border-radius: 4px; padding: 10px; background-color: #f9f9f9;"></div>
                    <small style="display: block; margin-top: 5px; color: #6c757d;">* 달력에서 여러 날짜를 클릭해 선택하고 삭제할 수 있습니다.</small>
                <?php endif; ?>
            </div>
            
            <div class="form-row">
                <label for="time_start">시작 시간:</label>
                <input type="time" id="time_start" name="time_start" value="<?php echo $editData['time_start']; ?>" required>
            </div>
            
            <div class="form-row">
                <label for="time_end">종료 시간:</label>
                <input type="time" id="time_end" name="time_end" value="<?php echo $editData['time_end']; ?>" required>
            </div>
            
            <div class="form-row">
                <label for="location_id">장소:</label>
                <select id="location_id" name="location_id" required>
                    <option value="">장소를 선택하세요</option>
                    <?php foreach ($locationsData as $location): ?>
                        <option value="<?php echo $location['id']; ?>" <?php echo ($editData['location_id'] == $location['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($location['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>* 2025location 테이블에 등록된 공식 공연 장소 목록입니다.</small>
            </div>

            <div class="form-row">
                <label for="location_detail_name">세부장소명:</label>
                <input type="text" id="location_detail_name" name="location_detail_name" value="<?php echo htmlspecialchars($editData['location_detail_name'] ?? ''); ?>" placeholder="세부장소명을 입력하세요">
            </div>

            <div class="form-row">
                <label for="event_name">행사명:</label>
                <input type="text" id="event_name" name="event_name" value="<?php echo htmlspecialchars($editData['event_name'] ?? ''); ?>" placeholder="행사명을 입력하세요">
            </div>
            
            <div class="checkbox-row">
                <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo (isset($editData['is_active']) && $editData['is_active'] == 1) ? 'checked' : ''; ?>>
                <label for="is_active">일정 활성화</label>
                <br><small>* 비활성화된 일정은 공연취소입니다.</small>
            </div>
            
            <?php if ($editMode): ?>
                <?php if (!empty($editData['reservation_id'])): ?>
                    <input type="hidden" name="reservation_id" value="<?= $editData['reservation_id'] ?>">
                <?php endif; ?>

                <div class="form-row">
                    <label for="team_id">
                        <?= !empty($editData['reservation_id']) ? '공연팀 변경:' : '공연팀 지정:' ?>
                    </label>
                    <select id="team_id" name="team_id">
                        <option value=""><?= !empty($editData['reservation_id']) ? '-- 변경 안함 --' : '-- 팀 선택 --' ?></option>
                        <?php foreach ($teamsData as $team): ?>
                            <option value="<?= $team['idx'] ?>" <?= (!empty($editData['team_id']) && $editData['team_id'] == $team['idx']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($team['team']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!empty($editData['reservation_id'])): ?>
                    <div class="checkbox-row">
                        <input type="checkbox" id="cancel_reservation" name="cancel_reservation" value="1">
                        <label for="cancel_reservation">예약 취소</label>
                    </div>
                <?php endif; ?>
            <?php endif; ?>



            <button type="submit"><?php echo $editMode ? '수정 완료' : '일정 추가'; ?></button>
            
            <?php if ($editMode): ?>
                <a href="?date=<?php echo $currentDate; ?>" style="margin-left: 10px; text-decoration: none;">취소</a>
            <?php endif; ?>
        </form>
        
        <h2>일정 목록</h2>
        <table>
            <thead>
                <tr>
                    <th>날짜</th>
                    <th>시작 시간</th>
                    <th>종료 시간</th>
                    <th>장소</th>
                    <th>세부장소명</th>
                    <th>행사명</th>
                    <th>상태</th>
                    <th>공연팀</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr class="<?php echo ($row['is_active'] == 0) ? 'inactive' : ''; ?>">
                            <td><?php echo date('Y년 m월 d일', strtotime($row['schedule_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['time_start']); ?></td>
                            <td><?php echo htmlspecialchars($row['time_end']); ?></td>
                            <td><?php echo htmlspecialchars($row['place_name']); ?></td>
                            <td><?php echo !empty($row['location_detail_name']) ? htmlspecialchars($row['location_detail_name']) : '-'; ?></td>
                            <td><?php echo !empty($row['event_name']) ? htmlspecialchars($row['event_name']) : '-'; ?></td>
                            <td>
                                <?php if ($row['is_active'] == 1): ?>
                                    <span class="status-badge active-badge">활성</span>
                                <?php else: ?>
                                    <span class="status-badge inactive-badge">
                                        비활성
                                        <?php if (!empty($row['cancel_reason'])): ?>
                                            (<?= htmlspecialchars($row['cancel_reason']) ?>)
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['reservation_id'])): ?>
                                    <?php if ($row['reservation_status'] == 'approved'): ?>
                                        <span class="team-name"><?php echo htmlspecialchars($row['team_name']); ?></span>
                                        <span class="team-contact">연락처: <?php echo htmlspecialchars($row['team_phone']); ?></span>
                                        <?php if (!empty($row['reservation_date'])): ?>
                                            <span class="reservation-date">신청일: <?php echo date('Y.m.d H:i', strtotime($row['reservation_date'])); ?></span>
                                        <?php endif; ?>
                                    <?php elseif ($row['reservation_status'] == 'pending'): ?>
                                        <span class="team-pending"><?php echo htmlspecialchars($row['team_name']) . " (대기 중)"; ?></span>
                                        <span class="team-contact">연락처: <?php echo htmlspecialchars($row['team_phone']); ?></span>
                                        <?php if (!empty($row['reservation_date'])): ?>
                                            <span class="reservation-date">신청일: <?php echo date('Y.m.d H:i', strtotime($row['reservation_date'])); ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="no-reserve-badge">예약 없음</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <p style="margin:0 0 5px 0">
                                    <a href="?date=<?php echo $currentDate; ?>&edit=<?php echo $row['id']; ?>" class="edit-btn">수정</a>
                                </p>
                                <p style="margin:0 0 5px 0">
                                   <a href="#" class="toggle-btn" 
                                        onclick="toggleSchedule(<?php echo $row['id']; ?>, <?php echo $row['is_active']; ?>)">
                                            <?php echo ($row['is_active'] == 1) ? '비활성화' : '활성화'; ?>
                                    </a>
                                </p>
                                <script>
                                    function toggleSchedule(id, is_active) {
                                        if (is_active == 1) {
                                            // 현재 활성화 상태 → 비활성화로 전환 → 사유 입력
                                            let reason = prompt("취소 사유를 입력해주세요.");
                                            if (reason !== null) {
                                                window.location.href = `?toggle=${id}&reason=${encodeURIComponent(reason)}`;
                                            }
                                        } else {
                                            // 현재 비활성화 상태 → 활성화 → 확인만
                                            if (confirm("정말 활성화하시겠습니까?")) {
                                                window.location.href = `?toggle=${id}&reason=`;
                                            }
                                        }
                                    }
                                </script>
                                <p style="margin:0">
                                    <a href="?date=<?php echo $currentDate; ?>&delete=<?php echo $row['id']; ?>" class="delete-btn" onclick="return confirm('정말 삭제하시겠습니까?')">삭제</a>
                                </p>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">등록된 일정이 없습니다.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    $date = $_GET['date'] ?? date('Ym');
    ?>

    <form action="download_schedule.php" method="get" style="margin-bottom: 10px;">
        <input type="hidden" name="date" value="<?= $date ?>">
        <button type="submit">이 달 일정 엑셀 다운로드</button>
    </form>






</body>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log("페이지 로드됨 - 초기화 시작");
        
        // 날짜 입력 필드에 기본값으로 오늘 날짜 설정
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        
        const dateToAdd = document.getElementById('date_to_add');
        if (dateToAdd && !document.querySelector('input[name="id"]')) {
            dateToAdd.value = `${year}-${month}-${day}`;
        }
        
        // 선택된 날짜 없음 메시지 초기화
        const selectedDatesContainer = document.getElementById('selected_dates');
        if (selectedDatesContainer && !document.querySelector('input[name="id"]')) {
            selectedDatesContainer.innerHTML = '<p>선택된 날짜가 없습니다.</p>';
        }
        
        console.log("초기화 완료");
    });

    // 폼 유효성 검사 함수
    function validateForm() {
        // 수정 모드에서는 이미 날짜가 있으므로 검증 통과
        if (document.querySelector('input[name="id"]')) {
            return true;
        }
        
        // 날짜 선택 확인
        const multipleDates = document.getElementById('multiple_dates').value;
        if (!multipleDates || multipleDates.trim() === '') {
            alert('최소 한 개 이상의 날짜를 선택해주세요.');
            return false;
        }
        
        // 시작 시간 확인
        const timeStart = document.getElementById('time_start').value;
        if (!timeStart) {
            alert('시작 시간을 입력해주세요.');
            return false;
        }
        
        // 종료 시간 확인
        const timeEnd = document.getElementById('time_end').value;
        if (!timeEnd) {
            alert('종료 시간을 입력해주세요.');
            return false;
        }
        
        // 장소 확인
        const locationId = document.getElementById('location_id').value;
        if (!locationId) {
            alert('장소를 선택해주세요.');
            return false;
        }
        
        return true;
    }

    // 날짜 추가 함수
    function addDate() {
        try {
            const dateToAdd = document.getElementById('date_to_add').value;
            if (!dateToAdd) {
                alert('날짜를 선택해주세요.');
                return;
            }
            
            // 유효한 날짜 형식인지 확인 (YYYY-MM-DD)
            if (!/^\d{4}-\d{2}-\d{2}$/.test(dateToAdd)) {
                alert('유효한 날짜 형식이 아닙니다 (YYYY-MM-DD).');
                return;
            }
            
            // 날짜가 이미 선택되어 있는지 확인
            const hiddenInput = document.getElementById('multiple_dates');
            let selectedDates = [];
            
            // 기존 값이 있는 경우에만 split 실행
            if (hiddenInput.value && hiddenInput.value.trim() !== '') {
                selectedDates = hiddenInput.value.split(',');
            }
            
            // 디버깅 - 현재 선택된 날짜 배열 출력
            console.log("현재 선택된 날짜:", selectedDates);
            
            if (selectedDates.includes(dateToAdd)) {
                alert('이미 선택한 날짜입니다.');
                return;
            }
            
            // 날짜 추가
            selectedDates.push(dateToAdd);
            
            // 정렬
            selectedDates.sort();
            
            // 값 업데이트
            hiddenInput.value = selectedDates.join(',');
            
            // 디버깅 - 추가 후 선택된 날짜 배열 출력
            console.log("추가 후 선택된 날짜:", selectedDates);
            console.log("hidden input 값:", hiddenInput.value);
            
            // 화면에 표시
            displaySelectedDates(selectedDates, 'selected_dates');
            
            // 입력 필드 초기화
            document.getElementById('date_to_add').value = '';
        } catch (error) {
            console.error("날짜 추가 중 오류 발생:", error);
            alert('날짜 추가 중 오류가 발생했습니다.');
        }
    }
    
    // 선택된 날짜 표시 함수
    function displaySelectedDates(dates, containerId) {
        const container = document.getElementById(containerId);
        
        if (!dates.length) {
            container.innerHTML = '<p>선택된 날짜가 없습니다.</p>';
            return;
        }
        
        let html = '<div>';
        html += '<p style="font-weight: bold; margin-bottom: 10px;">선택된 날짜 (' + dates.length + '개)</p>';
        html += '<div style="display: flex; flex-wrap: wrap; gap: 8px;">';
        
        dates.forEach(date => {
            // 날짜 형식 변환 (YYYY-MM-DD → YYYY년 MM월 DD일)
            const formattedDate = date.replace(/(\d{4})-(\d{2})-(\d{2})/, '$1년 $2월 $3일');
            html += `<span style="background: #e9ecef; padding: 5px 10px; border-radius: 4px; font-size: 13px; display: inline-flex; align-items: center; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                ${formattedDate}
                <a href="javascript:void(0)" onclick="removeDate('${date}')" style="margin-left: 8px; color: #dc3545; text-decoration: none; font-weight: bold; font-size: 16px; line-height: 1;">×</a>
            </span>`;
        });
        
        html += '</div></div>';
        
        container.innerHTML = html;
    }
    
    // 날짜 삭제 함수 (복수 날짜 선택용)
    function removeDate(dateToRemove) {
        const hiddenInput = document.getElementById('multiple_dates');
        let selectedDates = hiddenInput.value ? hiddenInput.value.split(',') : [];
        
        // 날짜 제거
        selectedDates = selectedDates.filter(date => date !== dateToRemove);
        
        // 값 업데이트
        hiddenInput.value = selectedDates.join(',');
        
        // 화면에 표시
        displaySelectedDates(selectedDates, 'selected_dates');
    }

    // URL에서 year, month 파라미터 가져오기
    const urlParams = new URLSearchParams(window.location.search);
    const selectedYear = urlParams.get('year') || new Date().getFullYear();
    const selectedMonth = urlParams.get('month') || (new Date().getMonth() + 1);

    // 엑셀 다운로드 폼에 값 세팅
    $('#excelYear').val(selectedYear);
    $('#excelMonth').val(selectedMonth);

</script>

<<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ko.js"></script>

<script>
let fp;

document.addEventListener('DOMContentLoaded', () => {
  const $hidden = document.getElementById('multiple_dates');
  const $container = document.getElementById('selected_dates');

  const selected = $hidden.value ? $hidden.value.split(',') : [];

  // flatpickr 초기화
  fp = flatpickr("#multi_date_picker", {
  mode: "multiple",
  dateFormat: "Y-m-d",
  locale: "ko",
  defaultDate: [],
  onChange: (dates, dateStr, instance) => {
    const val = dates.map(d => instance.formatDate(d, "Y-m-d"));
    document.getElementById('multiple_dates').value = val.join(',');
    drawDates(val);
  }
});

  drawDates(selected);

  window.removeDate = (date) => {
    const updated = ($hidden.value || '')
      .split(',')
      .filter(d => d && d !== date);
    $hidden.value = updated.join(',');
    fp.setDate(updated, false);
    drawDates(updated);
  };

  function drawDates(dates) {
    if (!dates.length) return $container.innerHTML = '<p>선택된 날짜가 없습니다.</p>';

    $container.innerHTML = `
      <p style="font-weight: bold; margin-bottom: 10px;">선택된 날짜 (${dates.length}개)</p>
      <div style="display: flex; flex-wrap: wrap; gap: 8px;">
        ${dates.map(date => `
          <span style="background: #e9ecef; padding: 5px 10px; border-radius: 4px; font-size: 13px; display: inline-flex; align-items: center;">
            ${date.replace(/(\d{4})-(\d{2})-(\d{2})/, '$1년 $2월 $3일')}
            <a href="javascript:void(0)" onclick="removeDate('${date}')" style="margin-left: 8px; color: #dc3545; font-weight: bold;">×</a>
          </span>
        `).join('')}
      </div>`;
  }
});
</script>



</html>

<?php
// 데이터베이스 연결 닫기
// 이미 닫힌 statement 객체는 다시 닫지 않도록 수정
// stmt 변수를 확인하고 처리하는 대신, 새로운 변수들로 관리

// 쿼리 결과가 있으면 정리
if (isset($result) && $result) {
    $result->free();
}

// 데이터베이스 연결 닫기
if (isset($conn) && $conn) {
    $conn->close();
}
?> 