<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include "db_2025.php";

header("Content-Type: application/json; charset=utf-8");

$sql = "SELECT * FROM `2025team` ORDER BY idx ASC";
$result = mysqli_query($conn, $sql);

$teams = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if ($row["number"] == "T001" || $row["number"] == "T002" || $row["number"] == "T003") {
            // 테스트 팀은 외부에 노출하지 않음
            continue;
        }

        $teams[] = [
            "id" => $row["idx"],
            "code" => $row["number"],
            "name" => mb_convert_encoding($row["team"], "UTF-8", "UTF-8"),
            "members" => mb_convert_encoding($row["member"], "UTF-8", "UTF-8"),
            "genre" => mb_convert_encoding($row["cat"], "UTF-8", "UTF-8"),
            "description" => mb_convert_encoding(strip_tags($row["content"]), "UTF-8", "UTF-8"),
            "image" => "https://seoulbusking.com/img/team/" . $row["number"] . ".webp",
            "instagram" => mb_convert_encoding($row["sns"], "UTF-8", "UTF-8"),
            "youtube" => mb_convert_encoding($row["sns2"], "UTF-8", "UTF-8")
            // "phone" => mb_convert_encoding($row["phone"], "UTF-8", "UTF-8") // 외부에 노출하지 않음
        ];
    }
}

// JSON 변환
$json = json_encode($teams, JSON_UNESCAPED_UNICODE);

// JSON 변환 실패 시 디버그 정보 출력
if ($json === false) {
    $error = json_last_error_msg();
    echo json_encode([
        "error" => "JSON 인코딩 실패",
        "message" => $error
    ]);
    exit;
}

echo $json;
?>