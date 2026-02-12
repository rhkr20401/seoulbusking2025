<?php
/**
 * 로그아웃 처리 페이지
 * 세션을 파기하고 로그인 페이지로 리다이렉트
 */

// 세션 시작
session_start();

// 로그인 상태인 경우에만 세션에서 값을 가져옴
if (isset($_SESSION['team_idx'])) {
    $team_idx = $_SESSION['team_idx'];
    
    // 데이터베이스 연결
    include_once('../adm/db_2025.php');
    
    if ($conn) {
        // 현재 세션 ID 가져오기
        $session_id = session_id();
        
        // 현재 세션을 DB에서 삭제
        $delete_stmt = $conn->prepare("DELETE FROM 2025team_sessions WHERE team_idx = ? AND session_id = ?");
        if ($delete_stmt) {
            $delete_stmt->bind_param('is', $team_idx, $session_id);
            $delete_stmt->execute();
            $delete_stmt->close();
        }
    }
}

// 세션 파기
session_unset();
session_destroy();

// 로그인 페이지로 리다이렉트
header('Location: login.php');
exit;
?> 