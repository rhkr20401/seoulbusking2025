<?php
// 파일 이름 설정 전 date 파싱
$dateParam = $_GET['date'] ?? null;
if ($dateParam && preg_match('/^(\d{4})(\d{2})$/', $dateParam, $matches)) {
    $year = (int)$matches[1];
    $month = (int)$matches[2];
    $monthName = $month . "월";
    $filename = "구구라_" . $monthName . "_예약일정.csv";
} else {
    $year = null;
    $month = null;
    $filename = "구구라_예약일정.csv";
}

// CSV 다운로드 설정
header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=" . rawurlencode($filename));

include "db_2025.php";
$conn->set_charset("utf8");

ini_set('display_errors', 1);
error_reporting(E_ALL);

// WHERE 조건
$where = "s.is_active = 1 AND s.is_delete = 0";
if ($year && $month) {
    $start = "$year-" . str_pad($month, 2, "0", STR_PAD_LEFT) . "-01";
    $end = date("Y-m-t", strtotime($start));
    $where .= " AND s.schedule_date BETWEEN '$start' AND '$end'";
}

// CSV 출력
$output = fopen("php://output", "w");

// ✅ UTF-8 BOM (엑셀 한글깨짐 방지)
fwrite($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, ["공연 날짜", "시작 시간", "종료 시간", "공연 장소", "팀 이름", "전화번호", "예약 상태"]);

$sql = "
SELECT 
    s.schedule_date,
    s.time_start,
    s.time_end,
    l.name AS location_name,
    IFNULL(t.team, '예약없음') AS team_name,
    IFNULL(t.phone, '') AS phone_number,
    CASE 
        WHEN r.status = 'approved' THEN '예약확정'
        ELSE ''
    END AS status_text,
    r.notes,
    r.created_at
FROM `2025schedules` s
LEFT JOIN `2025location` l ON s.location_id = l.id
LEFT JOIN (
    SELECT * FROM `2025reservations` WHERE status = 'approved'
) r ON r.schedule_id = s.id
LEFT JOIN `2025team` t ON r.team_id = t.idx
WHERE s.is_active = 1 AND s.is_delete = 0
";

if ($year && $month) {
    $start = "$year-" . str_pad($month, 2, "0", STR_PAD_LEFT) . "-01";
    $end = date("Y-m-t", strtotime($start));
    $sql .= " AND s.schedule_date BETWEEN '$start' AND '$end'";
}

$sql .= " ORDER BY s.schedule_date, s.time_start";

$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
$conn->close();
exit;
?>
