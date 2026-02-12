<?php
/**
 * 공연 예약 시스템
 * 
 * 기능:
 * - 월별 공연 일정 조회
 * - 장소별 필터링 (숨겨진 기능)
 * - 로그인한 공연팀이 공연장 예약 신청 (한 팀당 최대 3건까지 예약 가능(1차 신청 : 2건 / 2차 신청 : 1건))
 * - 자신이 신청한 예약은 당일에 한해 취소 가능
 * - 예약 및 예약 취소 기능은 10:00 ~ 18:00 사이에만 가능
 */

// 세션 시작 (로그인 상태 확인용)
session_start();

// 로그인 세션 유효성 검증
if (isset($_SESSION['team_number']) && isset($_SESSION['team_idx'])) {
    // 데이터베이스 연결
    include_once('../adm/db_2025.php');
    
    if ($conn) {
        $team_number = $_SESSION['team_number'];  // 공연팀 등록번호(number)
        $team_idx = $_SESSION['team_idx'];        // 공연팀 인덱스(idx)
        $session_id = session_id();
        
        // 세션 유효성 검사 - 가장 최근 세션이 현재 세션인지 확인
        $stmt = $conn->prepare("SELECT session_id FROM 2025team_sessions 
                                WHERE team_idx = ? ORDER BY created_at DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $team_idx);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                // 가장 최근 세션이 현재 세션과 다르면 강제 로그아웃
                if ($row['session_id'] != $session_id) {
                    // 세션 파기
                    session_unset();
                    session_destroy();
                    
                    // 로그인 페이지로 리다이렉트 (강제 로그아웃 표시)
                    header('Location: login.php?forced=1');
                    exit;
                }
            }
            
            $stmt->close();
        }
        
        // 오래된 세션 정리 (3일 이상 지난 것)
        $cleanup_stmt = $conn->prepare("DELETE FROM 2025team_sessions 
                                       WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)");
        if ($cleanup_stmt) {
            $cleanup_stmt->execute();
            $cleanup_stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>2025 서울거리공연 - 공연장 예약</title>
        
        <!-- SEO 및 소셜 미디어 메타 태그 -->
        <meta name="description" content="2025 서울거리공연 - 공연장 예약 시스템. 공연팀을 위한 공연장 예약 서비스">
        <meta property="og:title" content="2025 서울거리공연 - 공연장 예약">
        <meta property="og:description" content="2025 서울거리공연 - 공연장 예약 시스템. 공연팀을 위한 공연장 예약 서비스">
        <meta property="og:image" content="https://seoulbusking.com">
        <meta property="og:url" content="https://seoulbusking.com">
        <meta property="og:type" content="website">
        <link rel="icon" type="image/png" href="../../favicon.ico"/>
        
        <!-- 외부 CSS 및 JavaScript 라이브러리 -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/fonts-archive/GamtanRoadGamtan/GamtanRoadGamtan.css" type="text/css"/>
        <link rel="stylesheet" href="../css/reset.css">
        <link rel="stylesheet" href="../css/header.css">
        <link rel="stylesheet" href="../css/style_sub.css">
        <link rel="stylesheet" href="../css/style_reservation.css">
        <script src="https://kit.fontawesome.com/051ae6ca49.js" crossorigin="anonymous"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="../js/js_main.js"></script>
        <script>
            // PHP 변수를 JavaScript 변수로 전달
            <?php if(isset($_SESSION['team_idx'])): ?>
            window.teamIdx = <?php echo $_SESSION['team_idx']; ?>;
            <?php else: ?>
            window.teamIdx = null;
            <?php endif; ?>
            
            // 일정 입력 시에 적용
            // window.onload = function () {
            //   alert("서비스 점검 중입니다. 공식 홈페이지로 이동합니다.");
            //   window.location.href = "https://seoulbusking.com";
            // };
        </script>
        <script src="../js/js_reservation.js"></script>
    </head>
    <body>
        <!-- 전체 페이지 래퍼 -->
        <div class="page-wrapper">
            <div class="content-wrapper">
                <!-- 컨텐츠 영역 -->
                <section id="sub_section1" class="schedule">
                    <!-- 타이틀 영역 -->
                    <div class="title-area">
                        <img src="../img/logo.png" alt="로고">
                        <h1>공연팀 전용 예약 시스템</h1>
                    </div>

                    <!-- 로그인 상태와 예약 정책 -->
                    <div style="max-width: 100%; margin-bottom: 20px;">
                        <?php if(isset($_SESSION['team_number'])): ?>
                            <div style="width: 100%;">
                                <!-- 예약 현황 및 로그인 정보 (테이블 상단에 배치) -->
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; width: 95%; margin: 0 auto 12px auto;">
                                    <p style="color:#1565c0; font-size: 13px; font-weight: bold; margin: 0;">내 예약 현황 (<span id="active-count">0</span>/3)</p>
                                    <p style="font-size: 13px; margin: 0;"><span class="username" style="font-weight: bold;"><?php echo $_SESSION['team_name']; ?></span>님 환영합니다! <a href="logout.php" class="logout-link">로그아웃</a></p>
                                </div>
                                
                                <!-- 내 예약 목록 -->
                                <div class="reservation-list" style="width: 95%; margin-left: auto; margin-right: auto;">
                                    <!-- 예약 내역을 여기에 표시 -->
                                </div>
                                
                                <!-- 예약 정책 안내 -->
                                <div style="width: 95%; margin: 0 auto; padding-top: 8px;">
                                    <div style="color:#e53935; font-size: 12px; text-align: center; margin: 6px 0; line-height: 1.5;">
                                        <span style="font-weight: bold;">※</span>
                                        <span>예약/취소는 10:00~18:00 사이만 가능</span>
                                        <span style="margin: 0 8px;">|</span> 
                                        <span>취소는 예약한 당일에만 가능</span>
                                    </div>
                                </div>
                            
                            <?php if(isset($_GET['forced_logout']) && $_GET['forced_logout'] == 1): ?>
                                <div class="forced-logout-alert" style="margin-top: 10px; width: 100%; text-align: left;">
                                다른 기기에서의 로그인 세션이 종료되었습니다. 한 번에 한 기기에서만 로그인이 가능합니다.
                            </div>
                            <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div style="width: 100%;">
                                <p class="login-status-text" style="margin-bottom: 10px; width: 100%; text-align: center;">
                                    예약을 위해서는 <a href="login.php" class="login-link">로그인</a>이 필요합니다.
                                </p>
                                
                                <div style="width: 95%; margin: 0 auto; padding-top: 8px;">
                                    <div style="color:#e53935; font-size: 12px; text-align: center; margin: 6px 0; line-height: 1.5;">
                                        <span style="font-weight: bold;">※</span>
                                        <span>예약/취소는 10:00~18:00 사이만 가능</span>
                                        <span style="margin: 0 8px;">|</span> 
                                        <span>취소는 예약한 당일에만 가능</span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 예약 시스템 타이틀 (월 선택기 위로 이동) -->
                    <h2><span id="schedule-title">공연장 예약</span></h2>

                    <!-- 월 선택기 -->
                    <div class="month-selector">
                        <!-- JavaScript에서 동적으로 생성됩니다 -->
                    </div>

                    <!-- 장소 필터 (카테고리) -->
                    <div class="tab-container">
                        <div class="tab-title">공연장 선택</div>
                        <div class="schedule_tab">
                            <!-- JavaScript에서 동적으로 생성됩니다 -->
                        </div>
                    </div>

                    <!-- 선택된 장소 정보 -->
                    <div class="location-info">
                        <!-- 선택된 장소가 있으면 표시됩니다 -->
                    </div>

                    <!-- 일정 테이블 -->
                    <div id="schedule-table">
                        <div class="schedule-loading">일정을 불러오는 중입니다...</div>
                    </div>
                    
                    <!-- 연락처 정보 -->
                    <div class="contact-info">
                        <strong>서울거리공연 운영 사무국</strong><br>
                        <span>문의시간: 09시~18시(평일) / 12시~13시(점심시간 제외)</span><br>
                        <span>이메일: 2025seoulbusking@gmail.com</span>
                        
                        <div class="contact-buttons">
                            <a href="mailto:2025seoulbusking@gmail.com" class="contact-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="fill:rgb(255, 255, 255);margin-right: 4px;width: 15px;"><path d="M48 64C21.5 64 0 85.5 0 112c0 15.1 7.1 29.3 19.2 38.4L236.8 313.6c11.4 8.5 27 8.5 38.4 0L492.8 150.4c12.1-9.1 19.2-23.3 19.2-38.4c0-26.5-21.5-48-48-48L48 64zM0 176L0 384c0 35.3 28.7 64 64 64l384 0c35.3 0 64-28.7 64-64l0-208L294.4 339.2c-22.8 17.1-54 17.1-76.8 0L0 176z"/></svg>이메일 문의
                            </a>
                        </div>
                    </div>
                    
                    <!-- 홈페이지로 돌아가기 링크 -->
                    <div class="back-link">
                        <a href="../../index.html"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" style="fill: #06b77d;margin-right: 4px;width: 15px;"><path d="M9.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l160 160c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L109.2 288 416 288c17.7 0 32-14.3 32-32s-14.3-32-32-32l-306.7 0L214.6 118.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-160 160z"/></svg> 서울거리공연 홈페이지로 돌아가기</a>
                    </div>
                </section>
            </div>
        </div>
    </body>
</html>