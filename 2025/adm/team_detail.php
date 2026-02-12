<?php
// 데이터베이스 연결 정보
include "db_2025.php";

// 팀 ID 가져오기
$team_code = isset($_GET['idx']) ? $conn->real_escape_string($_GET['idx']) : '';

if (!$team_code) {
    die("팀 정보가 없습니다.");
}

// 팀 정보 조회
$sql = "SELECT * FROM 2025team WHERE number = '$team_code'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $team = $result->fetch_assoc();
} else {
    die("해당 팀 정보를 찾을 수 없습니다.");
}

$conn->close();
?>

<!-- 팝업 내부 컨텐츠 -->
<div class="popup-inner">
    <!-- 팀 이미지 -->
    <img src="../img/team/2025/<?php echo htmlspecialchars($team['number']); ?>.webp" alt="<?php echo htmlspecialchars($team['team']); ?>">

    <!-- 팀 이름 -->
    <h2><?php echo htmlspecialchars($team['team']); ?></h2>

    <!-- 멤버 및 장르 -->
    <p><strong><?php echo htmlspecialchars($team['member']); ?></strong></p>
    <p><?php echo htmlspecialchars($team['cat']); ?></p>

    <!-- 소개 -->
    <p class="team-description"><?php echo nl2br(htmlspecialchars($team['content'])); ?></p>

    <!-- SNS 링크 -->
    <?php if (!empty($team['sns'])): ?>
        <a href="<?php echo htmlspecialchars($team['sns']); ?>" target="_blank"><i class="fa-brands fa-instagram"></i>   Instagram</a>
    <?php endif; ?>
    <?php if (!empty($team['sns2'])): ?>
        <a href="<?php echo htmlspecialchars($team['sns2']); ?>" target="_blank"><i class="fa-brands fa-youtube"></i>   YouTube</a>
    <?php endif; ?>

    <!-- 닫기 버튼 (SNS 버튼 아래) -->
    <button class="popup-close">닫기</button>
</div>