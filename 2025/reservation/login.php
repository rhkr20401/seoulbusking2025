<?php
/**
 * 공연팀 로그인 페이지
 * 2025team 테이블의 `number`를 ID로, `phone`의 뒤 네자리를 비밀번호로 사용 (null일 경우 로그인 불가)
 * 2025team_sessions 테이블을 통해 동시 접속 방지
 */

// 세션 시작
session_start();

// 이미 로그인된 경우 예약 페이지로 리다이렉트
if (isset($_SESSION['team_number'])) {
    header('Location: schedule.php');
    exit;
}

// 데이터베이스 연결 파일 포함
include_once('../adm/db_2025.php');

// 로그인 처리
$login_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 로그인 데이터 가져오기
    $team_number = isset($_POST['team_number']) ? trim($_POST['team_number']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    if (empty($team_number) || empty($password)) {
        $login_error = '아이디와 비밀번호를 모두 입력해주세요.';
    } else {
        // 데이터베이스 연결 확인
        if (!$conn) {
            $login_error = '데이터베이스 연결에 실패했습니다. 잠시 후 다시 시도해주세요.';
        } else {
            // SQL 인젝션 방지를 위한 prepared statement 사용
            $stmt = $conn->prepare("SELECT idx, team, phone FROM 2025team WHERE number = ?");
            
            // prepare 실패 시 에러 처리
            if (!$stmt) {
                $login_error = '데이터베이스 쿼리 준비에 실패했습니다: ' . $conn->error;
            } else {
                $stmt->bind_param('s', $team_number);
                $stmt->execute();
                $stmt->store_result();
                
                if ($stmt->num_rows === 1) {
                    $stmt->bind_result($team_idx, $team_name, $phone);
                    $stmt->fetch();
                    
                    // phone 컬럼이 비어있는 경우 로그인 불가
                    if (empty($phone)) {
                        $login_error = '로그인이 불가능한 계정입니다. 관리자에게 문의해주세요.';
                    } else {
                        // 비밀번호 검증 (phone 컬럼의 뒤 네자리)
                        $phone_last_4_digits = substr($phone, -4);
                        
                        if ($password === $phone_last_4_digits) {
                            // 로그인 세션 생성
                            $_SESSION['team_number'] = $team_number;  // 공연팀 등록번호(number)
                            $_SESSION['team_name'] = $team_name;      // 공연팀 이름(team)
                            $_SESSION['team_idx'] = $team_idx;        // 공연팀 인덱스(idx)
                            
                            // 현재 세션 ID 및 접속 정보 가져오기
                            $session_id = session_id();
                            $ip_address = $_SERVER['REMOTE_ADDR'];
                            
                            // 중복 로그인 처리
                            $forced_logout = 0;
                            
                            // 기존 로그인이 있는지 확인
                            $check_stmt = $conn->prepare("SELECT session_id FROM 2025team_sessions WHERE team_idx = ? ORDER BY created_at DESC LIMIT 1");
                            if ($check_stmt) {
                                $check_stmt->bind_param('i', $team_idx);
                                $check_stmt->execute();
                                $check_result = $check_stmt->get_result();
                                
                                if ($row = $check_result->fetch_assoc()) {
                                    // 이전 세션 ID가 현재와 다르면 강제 로그아웃으로 처리
                                    if ($row['session_id'] != $session_id) {
                                        $forced_logout = 1;
                                    }
                                }
                                $check_stmt->close();
                            }
                            
                            // 새 세션 정보 추가
                            $insert_stmt = $conn->prepare("INSERT INTO 2025team_sessions (team_idx, session_id, ip_address) VALUES (?, ?, ?)");
                            if ($insert_stmt) {
                                $insert_stmt->bind_param('iss', $team_idx, $session_id, $ip_address);
                                $insert_stmt->execute();
                                $insert_stmt->close();
                            }
                            
                            // 로그인 성공 - 예약 페이지로 리다이렉트
                            // 다른 세션 강제 로그아웃 파라미터 추가
                            $redirect_url = isset($_GET['redirect']) ? $_GET['redirect'] : 'schedule.php';
                            
                            // 강제 로그아웃 파라미터가 있는 경우 처리
                            if ($forced_logout == 1) {
                                // 기존 URL에 파라미터가 있는지 확인하여 적절하게 추가
                                if (strpos($redirect_url, '?') !== false) {
                                    $redirect_url .= '&forced_logout=1';
                                } else {
                                    $redirect_url .= '?forced_logout=1';
                                }
                            }
                            
                            header('Location: ' . $redirect_url);
                            exit;
                        } else {
                            $login_error = '아이디 또는 비밀번호가 일치하지 않습니다.';
                        }
                    }
                } else {
                    $login_error = '아이디 또는 비밀번호가 일치하지 않습니다.';
                }
                
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>2025 서울거리공연 - 공연팀 로그인</title>
        
        <!-- SEO 및 소셜 미디어 메타 태그 -->
        <meta name="description" content="2025 서울거리공연 - 공연팀 로그인. 공연팀을 위한 로그인 페이지">
        <meta property="og:title" content="2025 서울거리공연 - 공연팀 로그인">
        <meta property="og:description" content="2025 서울거리공연 - 공연팀 로그인. 공연팀을 위한 로그인 페이지">
        <meta property="og:image" content="https://seoulbusking.com">
        <meta property="og:url" content="https://seoulbusking.com">
        <meta property="og:type" content="website">
        <link rel="icon" type="image/png" href="../../favicon.ico"/>
        
        <!-- 외부 CSS 및 JavaScript 라이브러리 -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/fonts-archive/GamtanRoadGamtan/GamtanRoadGamtan.css" type="text/css"/>
        <link rel="stylesheet" href="../css/reset.css">
        <link rel="stylesheet" href="../css/header.css">
        <link rel="stylesheet" href="../css/style_sub.css">
        <script src="https://kit.fontawesome.com/051ae6ca49.js" crossorigin="anonymous"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="../js/js_main.js"></script>
        <style>
            /**
             * 기본 레이아웃 스타일
             * - 전체 페이지 배경, 섹션 스타일 등 기본 레이아웃 정의
             */
            body {
                background-color: #f8f9fa;
                font-family: 'GamtanRoadGamtan', sans-serif;
                margin: 0;
                padding: 0;
            }
            
            #sub_section1 {
                max-width: 550px;
                margin: 0 auto;
                padding: 12px 15px;
                background-color: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            }
            
            /**
             * 타이틀 영역 스타일 
             * - 로고, 제목 등의 상단 영역 스타일 정의
             */
            .title-area {
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 10px 0 0px;
                margin-bottom: 10px;
                border-bottom: 1px solid #eee;
            }
            
            .title-area img {
                height: 45px;
                margin-bottom: 10px;
            }
            
            .title-area h1 {
                font-size: 24px;
                font-weight: bold;
                margin-bottom: 12px;
                color: #333;
            }
            
            /**
             * 로그인 폼 스타일
             * - 로그인 입력 필드 및 버튼 스타일 정의
             */
            .login-form {
                margin: 20px auto;
                max-width: 480px;
                padding: 20px;
                background-color: #f9f9f9;
                border-radius: 8px;
                border: 1px solid #e0e0e0;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
                color: #333;
                font-size: 14px;
            }
            
            .form-group input {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
                box-sizing: border-box;
            }
            
            .form-group input:focus {
                border-color: #06b77d;
                outline: none;
                box-shadow: 0 0 0 2px rgba(6, 183, 125, 0.1);
            }
            
            .login-button {
                width: 100%;
                padding: 10px;
                background-color: #06b77d;
                color: white;
                border: none;
                border-radius: 4px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
                transition: background-color 0.3s;
            }
            
            .login-button:hover {
                background-color: #048c60;
            }
            
            /**
             * 에러 메시지 스타일
             * - 로그인 오류 메시지 스타일 정의
             */
            .error-message {
                color: #e53935;
                text-align: center;
                margin: 10px 0;
                font-size: 14px;
                padding: 5px;
                background-color: #ffebee;
                border-radius: 4px;
                display: <?php echo !empty($login_error) ? 'block' : 'none'; ?>;
            }
            
            /**
             * 안내 메시지 스타일
             * - 로그인 관련 안내 메시지 스타일 정의
             */
            .info-message {
                text-align: center;
                margin: 15px 0;
                font-size: 13px;
                color: #666;
                line-height: 1.5;
            }
            
            /**
             * 알림 메시지 스타일
             * - 로그인 정책 알림 메시지 스타일 정의
             */
            .policy-notice {
                background-color: #ffebee;
                border: 1px solid #ffcdd2;
                border-radius: 4px;
                padding: 8px 10px;
                margin: 10px 0 20px;
                font-size: 12px;
                color: #c62828;
                line-height: 1.5;
            }
            
            .policy-notice strong {
                display: block;
                margin-bottom: 4px;
                font-weight: bold;
                font-size: 12.5px;
                text-align: center;
                border-bottom: 1px dashed #ffcdd2;
                padding-bottom: 3px;
                margin-bottom: 5px;
            }
            
            /* 강제 로그아웃 알림 스타일 */
            .forced-logout-notice {
                background-color: #fff3e0;
                border: 1px solid #ffe0b2;
                border-radius: 4px;
                padding: 10px;
                margin: 10px 0 15px 0;
                font-size: 13px;
                color: #e65100;
                display: <?php echo isset($_GET['forced']) ? 'block' : 'none'; ?>;
            }
            
            /**
             * 전체 페이지 레이아웃 스타일
             * - 페이지 전체 구조를 정의
             */
            .page-wrapper {
                display: flex;
                flex-direction: column;
                min-height: 100vh;
                width: 100%;
                background-color: #f8f9fa;
                padding-bottom: 15px;
                box-sizing: border-box;
                overflow-x: hidden;
                margin: 0;
            }
            
            .content-wrapper {
                flex-grow: 1;
                padding: 10px 10px 0 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                box-sizing: border-box;
                width: 100%;
            }
            
            /**
             * 연락처 정보 스타일
             * - 하단 연락처 정보 박스 및 버튼 스타일
             */
            .contact-info {
                margin: 12px auto 0;
                padding: 10px 15px;
                background-color: #f5f5f5;
                border-radius: 8px;
                text-align: center;
                font-size: 13px;
                line-height: 1.5;
                color: #555;
                border: 1px solid #e0e0e0;
                max-width: 480px;
            }
            
            .contact-info strong {
                color: #333;
                font-weight: bold;
            }
            
            .contact-info a {
                color: #06b77d;
                text-decoration: none;
                margin: 0 5px;
            }
            
            .contact-info a:hover {
                text-decoration: underline;
            }
            
            .contact-info .contact-buttons {
                margin-top: 10px;
            }
            
            .contact-info .contact-buttons .contact-btn {
                display: inline-block;
                padding: 5px 10px;
                background-color: #06b77d;
                color: white;
                border: none;
                border-radius: 4px;
                margin: 0 5px;
                font-size: 12px;
                cursor: pointer;
                text-decoration: none;
            }
            
            .contact-info .contact-buttons .contact-btn:hover {
                background-color: #048c60;
            }
            
            .contact-info .contact-buttons .contact-btn i {
                margin-right: 5px;
            }
            
            /**
             * 하단 링크 영역 스타일
             * - 페이지 하단 링크 버튼 스타일
             */
            .back-link {
                margin: 15px auto 0;
                text-align: center;
                padding: 10px 0;
                border-top: 1px solid #eee;
                max-width: 480px;
            }
            
            .back-link a {
                color: #06b77d;
                text-decoration: none;
                font-size: 14px;
            }
            
            .back-link a:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <!-- 전체 페이지 래퍼 -->
        <div class="page-wrapper">
            <div class="content-wrapper">
                <!-- 컨텐츠 영역 -->
                <section id="sub_section1" class="login">
                    <!-- 타이틀 영역 -->
                    <div class="title-area">
                        <img src="../img/logo.png" alt="로고">
                        <h1>공연팀 로그인</h1>
                    </div>
                    
                    <!-- 로그인 폼 -->
                    <form class="login-form" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . (isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''); ?>" method="post">
                        <!-- 리다이렉트 URL 히든 필드 -->
                        <?php if (isset($_GET['redirect'])): ?>
                        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_GET['redirect']); ?>">
                        <?php endif; ?>
                        
                        <!-- 에러 메시지 -->
                        <div class="error-message"><?php echo $login_error; ?></div>
                        
                        <!-- 강제 로그아웃 알림 -->
                        <div class="forced-logout-notice">
                            <strong>새로운 로그인이 감지되어 기존 로그인은 자동으로 로그아웃 처리되었습니다.</strong>
                        </div>
                        
                        <!-- 로그인 설명 -->
                        <div class="info-message">
                            공연팀 아이디와 비밀번호를 입력하여 로그인해주세요.<br>
                            등록 번호와 비밀번호는 공연팀 예약 안내 이메일에 안내되어 있습니다.
                        </div>
                        
                        <!-- 로그인 정책 안내 -->
                        <div class="policy-notice">
                            <strong>로그인 정책 안내</strong>
                            공연팀은 한 번에 하나의 기기에서만 로그인이 가능합니다.<br />다른 기기에서 로그인 시 기존 로그인은 자동으로 로그아웃 됩니다.
                        </div>
                        
                        <!-- 아이디 입력 -->
                        <div class="form-group">
                            <label for="team_number">공연팀 등록번호</label>
                            <input type="text" id="team_number" name="team_number" placeholder="등록번호를 입력하세요" required>
                        </div>
                        
                        <!-- 비밀번호 입력 -->
                        <div class="form-group">
                            <label for="password">비밀번호</label>
                            <input type="password" id="password" name="password" placeholder="비밀번호를 입력하세요" required>
                        </div>
                        
                        <!-- 로그인 버튼 -->
                        <button type="submit" class="login-button">로그인</button>
                    </form>
                    
                    <!-- 연락처 정보 -->
                    <div class="contact-info">
                        <strong>서울거리공연 운영 사무국</strong><br>
                        <span>문의시간: 09시~18시(평일) / 12시~13시(점심시간 제외)</span><br>
                        <span>이메일: 2025seoulbusking@gmail.com</span>
                        
                        <div class="contact-buttons">
                            <a href="mailto:2025seoulbusking@gmail.com" class="contact-btn">
                                <i class="fa-solid fa-envelope"></i>이메일 문의
                            </a>
                        </div>
                    </div>
                    
                    <!-- 홈페이지로 돌아가기 링크 -->
                    <div class="back-link">
                        <a href="../../index.html"><i class="fa-solid fa-arrow-left"></i> 서울거리공연 홈페이지로 돌아가기</a>
                    </div>
                </section>
            </div>
        </div>
    </body>
</html>