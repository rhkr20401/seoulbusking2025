/**
 * 공연장 예약 시스템 JavaScript
 * 달력, 예약 시스템 관련 기능 구현
 */

$(document).ready(function () {
    /**
     * 초기 변수 설정 및 페이지 로드 시 실행되는 함수
     */
    
    // 현재 연월 구하기 (표시용)
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth() + 1;
    
    // URL 파라미터에서 연월 및 장소 ID 가져오기
    const urlParams = new URLSearchParams(window.location.search);
    let selectedYear = urlParams.get('year') || year;
    let selectedMonth = urlParams.get('month') || month;
    const selectedLocationId = urlParams.get('location_id');
    
    // 연월 형식 맞추기
    selectedYear = parseInt(selectedYear);
    selectedMonth = parseInt(selectedMonth);
    
    // 페이지 초기화 함수 호출
    initPage(selectedYear, selectedMonth, selectedLocationId);
    
    /**
     * 페이지 초기화 함수 - 필요한 데이터 로드 및 화면 구성
     * @param {number} year - 선택된 연도
     * @param {number} month - 선택된 월
     * @param {string|null} locationId - 선택된 장소 ID (없을 경우 null)
     */
    function initPage(year, month, locationId) {
        // 현재 연월 표시 업데이트
        updateTitle(year, month);
        
        // 월 선택자 설정
        updateMonthSelector(year, month);
        
        // 장소 목록 로드 및 표시
        loadLocations(year, month, locationId);
        
        // 일정 테이블 로드
        loadSchedules(year, month, locationId);
        
        // 로그인한 경우 예약 내역 로드
        if (typeof teamIdx !== 'undefined' && teamIdx !== null) {
            loadMyReservations(year, month);
        }
    }
    
    /**
     * 제목 업데이트 함수
     * @param {number} year - 선택된 연도
     * @param {number} month - 선택된 월
     */
    function updateTitle(year, month) {
        $('#schedule-title').text(`${month}월 공연장 예약`);
    }
    
    /**
     * 월 선택자 업데이트 함수
     * @param {number} year - 선택된 연도
     * @param {number} month - 선택된 월
     */
    function updateMonthSelector(year, month) {
        $('.month-selector').empty();
        
        for(let i = 1; i <= 12; i++) {
            const isActive = i === month ? 'active' : '';
            $('.month-selector').append(`
                <a href="#" data-month="${i}" class="${isActive}">${i}월</a>
            `);
        }
    }
    
    /**
     * 장소 목록 로드 함수
     * @param {number} year - 선택된 연도
     * @param {number} month - 선택된 월
     * @param {string|null} selectedLocationId - 선택된 장소 ID
     */
    function loadLocations(year, month, selectedLocationId) {
        // YYYYMM 형식으로 날짜 생성
        const monthStr = String(month).padStart(2, '0');
        const date = `${year}${monthStr}`;
        
        $.ajax({
            url: '../adm/get_schedules.php',
            type: 'GET',
            dataType: 'json',
            data: {
                date: date,
                getLocations: true
            },
            success: function(data) {
                // 장소 탭 초기화
                $('.schedule_tab').empty();
                
                // 전체 보기 옵션 추가
                const allClass = !selectedLocationId ? 'on all' : 'all';
                $('.schedule_tab').append(`<a href="#" class="${allClass}">전체</a>`);
                
                // 각 장소별 링크 추가
                if (Array.isArray(data)) {
                    data.forEach(function(location) {
                        const activeClass = selectedLocationId == location.id ? 'on' : '';
                        $('.schedule_tab').append(`
                            <a href="#" data-location-id="${location.id}" class="${activeClass}">${location.name}</a>
                        `);
                        
                        // 선택된 장소의 정보 가져오기
                        if(selectedLocationId == location.id) {
                            loadLocationInfo(location.id);
                        }
                    });
                    
                    // UI는 숨겨져 있지만 데이터와 기능은 유지
                    // 필요시 아래 주석을 제거하고 .tab-container의 display: none을 제거하면 됨
                    //$('.tab-container').show();
                }
            },
            error: function(xhr, status, error) {
                console.error("장소 목록을 불러오는 중 오류가 발생했습니다:", error);
                $('.schedule_tab').html('<p>장소 목록을 불러오는 중 오류가 발생했습니다.</p>');
            }
        });
    }
    
    /**
     * 장소 정보 로드 함수 - 선택된 장소의 상세 정보를 가져옴
     * @param {string} locationId - 장소 ID
     */
    function loadLocationInfo(locationId) {
        $.ajax({
            url: '../adm/get_location_info.php',
            type: 'GET',
            dataType: 'json',
            data: { id: locationId },
            success: function(data) {
                if(data && data.id) {
                    // 장소 정보 HTML 업데이트
                    $('.location-info').html(`
                        <h3>${data.name}</h3>
                        <p><strong>교통정보:</strong> ${data.station_info || '정보 없음'}</p>
                        <p><strong>주소:</strong> ${data.address || '정보 없음'}</p>
                        ${data.map_link ? `<a href="${data.map_link}" target="_blank">지도 보기</a>` : ''}
                    `).show();
                } else {
                    $('.location-info').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('장소 정보 로드 오류:', error);
                $('.location-info').hide();
            }
        });
    }
    
    /**
     * 일정 로드 함수 - 선택된 연월과 장소의 일정을 가져옴
     * @param {number} year - 선택된 연도
     * @param {number} month - 선택된 월
     * @param {string|null} locationId - 선택된 장소 ID (선택적)
     */
    function loadSchedules(year, month, locationId) {
        // YYYYMM 형식으로 날짜 생성
        const monthStr = String(month).padStart(2, '0');
        const date = `${year}${monthStr}`;
        
        // 로딩 표시
        $('#schedule-table').html('<div class="schedule-loading">일정을 불러오는 중입니다...</div>');
        
        let apiUrl = '../adm/get_schedules.php';
        let apiData = { date: date };
        
        if(locationId) {
            apiData.location_id = locationId;
        }
        
        $.ajax({
            url: apiUrl,
            type: 'GET',
            dataType: 'json',
            data: apiData,
            success: function(data) {
                console.log('API Response:', data);
                // 스케줄 데이터 확인
                if (data && data.schedules) {
                    console.log(`총 ${data.schedules.length}개의 일정이 로드됨`);
                    // 샘플 데이터 출력
                    if (data.schedules.length > 0) {
                        console.log('첫 번째 일정 샘플:', data.schedules[0]);
                    }
                } else {
                    console.warn('일정 데이터가 없거나 형식이 잘못되었습니다:', data);
                }
                
                // 일정 데이터를 테이블로 변환
                renderScheduleTable(data, year, month);
            },
            error: function(xhr, status, error) {
                console.error("일정을 불러오는 중 오류가 발생했습니다:", error);
                console.error("AJAX 상태:", status);
                console.error("응답 텍스트:", xhr.responseText);
                $('#schedule-table').html('<div class="schedule-empty">일정을 불러오는 중 오류가 발생했습니다.</div>');
            }
        });
    }
    
    /**
     * 예약 만들기 함수 - 선택한 일정에 예약 신청
     * @param {string} scheduleId - 예약할 일정 ID
     */
    function makeReservation(scheduleId) {
        $.ajax({
            url: '../adm/make_reservation.php',
            type: 'POST',
            dataType: 'json',
            data: { schedule_id: scheduleId },
            success: function(response) {
                if(response.success) {
                    alert('예약이 성공적으로 완료되었습니다.');
                    // 페이지 새로고침
                    location.reload();
                } else {
                    alert('예약에 실패했습니다: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error("예약 중 오류가 발생했습니다:", error);
                console.error("서버 응답:", xhr.responseText);
                
                // HTML 오류 메시지 확인
                if (xhr.responseText.indexOf('<') === 0) {
                    alert('서버 오류가 발생했습니다. 관리자에게 문의해주세요.');
                } else {
                    try {
                        var errorData = JSON.parse(xhr.responseText);
                        alert('예약에 실패했습니다: ' + (errorData.message || '알 수 없는 오류'));
                    } catch (e) {
                        alert('예약 처리 중 오류가 발생했습니다.');
                    }
                }
            }
        });
    }
    
    /**
     * 일정 테이블 렌더링 함수 - 달력 형태의 일정 테이블 생성
     * @param {Object} data - API에서 받아온 일정 데이터
     * @param {number} year - 선택된 연도
     * @param {number} month - 선택된 월
     */
    function renderScheduleTable(data, year, month) {
        // 디버깅: 테이블 렌더링 시작
        console.log('테이블 렌더링 시작:', year, month);
        console.log('데이터 유효성:', data && data.success && Array.isArray(data.schedules));
        
        // 해당 월의 마지막 날짜 구하기
        const lastDay = new Date(year, month, 0).getDate();
        
        // 해당 월의 첫 날짜의 요일 구하기 (0: 일요일, 6: 토요일)
        const firstDayOfMonth = new Date(year, month - 1, 1).getDay();
        
        // 테이블 시작
        let tableHtml = `
            <table>
                <tbody>
                    <tr>
                        <th>일</th>
                        <th>월</th>
                        <th>화</th>
                        <th>수</th>
                        <th>목</th>
                        <th>금</th>
                        <th>토</th>
                    </tr>
        `;
        
        // 날짜 셀 생성
        let dayCount = 1;
        
        // 현재 날짜 구하기 (오늘 날짜 표시용)
        const today = new Date();
        const currentYear = today.getFullYear();
        const currentMonth = today.getMonth() + 1;
        const currentDay = today.getDate();
        
        // 전체 주차 반복 (최대 6주)
        for (let weekIdx = 0; weekIdx < 6; weekIdx++) {
            // 날짜가 월의 마지막 날을 넘어가면 중단
            if (dayCount > lastDay) break;
            
            // 날짜 행 시작
            let dateRow = '<tr>';
            // 일정 행 시작
            let contentRow = '<tr>';
            
            // 요일 반복 (0: 일요일 ~ 6: 토요일)
            for (let dayOfWeek = 0; dayOfWeek < 7; dayOfWeek++) {
                // 첫 주이고, 현재 요일이 첫 날의 요일보다 작으면 빈 셀
                // 또는 날짜가 마지막 날을 넘어가면 빈 셀
                if ((weekIdx === 0 && dayOfWeek < firstDayOfMonth) || (dayCount > lastDay)) {
                    dateRow += '<th></th>';
                    contentRow += '<td class="empty-cell">-</td>';
                } else {
                    // 요일에 따른 색상 적용 (일요일: 빨간색, 토요일: 파란색)
                    let dayStyle = '';
                    if (dayOfWeek === 0) dayStyle = ' style="color:red"';
                    else if (dayOfWeek === 6) dayStyle = ' style="color:blue"';
                    
                    // 날짜 셀 추가
                    dateRow += `<th${dayStyle}>${dayCount}일(${['일', '월', '화', '수', '목', '금', '토'][dayOfWeek]})</th>`;
                    
                    // 해당 날짜의 일정을 렌더링하고 일정 유무에 따라 클래스 지정
                    const scheduleContent = renderDateSchedules(data, year, month, dayCount, today);
                    const cellClass = scheduleContent === '-' ? 'empty-day' : 'has-schedule';
                    contentRow += `<td valign="top" class="${cellClass}">${scheduleContent}</td>`;
                    
                    // 날짜 증가
                    dayCount++;
                }
            }
            
            // 행 종료
            dateRow += '</tr>';
            contentRow += '</tr>';
            
            // 테이블에 행 추가
            tableHtml += dateRow + contentRow;
        }
        
        // 테이블 종료
        tableHtml += '</tbody></table>';
        
        // 테이블 표시
        $('#schedule-table').html(tableHtml);
        
        // 데이터가 비어있는 경우 안내 메시지 추가
        if (!data || !data.success || !data.schedules || data.schedules.length === 0) {
            $('#schedule-table').append('<div class="schedule-empty">해당 월에 예정된 공연 일정이 없습니다.</div>');
        }
    }
    
    /**
     * 특정 날짜의 일정 렌더링 함수
     * @param {Object} data - API에서 받아온 일정 데이터
     * @param {number} year - 선택된 연도
     * @param {number} month - 선택된 월
     * @param {number} day - 날짜
     * @param {Date} today - 오늘 날짜
     * @return {string} - 해당 날짜의 일정 HTML
     */
    function renderDateSchedules(data, year, month, day, today) {
        // 날짜 형식 생성 (YYYY-MM-DD)
        let currentDate = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        let schedules = [];
        
        // 데이터 디버깅
        if (day === 1) { // 1일에만 로그 출력 (너무 많은 로그 방지)
            console.log('API Response:', data);
            if (data && data.schedules && data.schedules.length > 0) {
                console.log('First schedule sample:', data.schedules[0]);
            }
        }
        
        if (data && data.success && data.schedules) {
            // 해당 날짜의 일정만 필터링
            schedules = data.schedules.filter(function(schedule) {
                return schedule.date === currentDate;
            });
            
            // 날짜별 일정 디버깅
            if (schedules.length > 0 && day === 1) {
                console.log(`Schedules for ${currentDate}:`, schedules);
            }
        }
        
        // 일정이 없는 경우
        if (!schedules || schedules.length === 0) {
            return '-';
        }
        
        // 일정이 있는 경우
        let scheduleHtml = '';
        
        // 장소별로 그룹화
        const placeGroups = {};
        
        schedules.forEach(function(schedule) {
            if (!placeGroups[schedule.place]) {
                placeGroups[schedule.place] = [];
            }
            placeGroups[schedule.place].push(schedule);
        });
        
        // 각 장소 그룹 렌더링
        for (const place in placeGroups) {
            scheduleHtml += `<div class="place-name">${place}</div>`;
            
            // 해당 장소의 시간별 일정
            placeGroups[place].forEach(function(schedule) {
                let reservationBtn = getReservationButtonHtml(schedule, today);
            
                let eventNameHtml = schedule.event_name && schedule.event_name.trim() !== '' 
                    ? `<small class="event-name"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="fill:#06b77d; width:13px"><path d="M499.1 6.3c8.1 6 12.9 15.6 12.9 25.7l0 72 0 264c0 44.2-43 80-96 80s-96-35.8-96-80s43-80 96-80c11.2 0 22 1.6 32 4.6L448 147 192 223.8 192 432c0 44.2-43 80-96 80s-96-35.8-96-80s43-80 96-80c11.2 0 22 1.6 32 4.6L128 200l0-72c0-14.1 9.3-26.6 22.8-30.7l320-96c9.7-2.9 20.2-1.1 28.3 5z"/></svg> ${schedule.event_name}</small>` 
                    : '';
            
                let locationDetailHtml = schedule.location_detail_name && schedule.location_detail_name.trim() !== '' 
                    ? `<small class="location_detail_name"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" style="fill:#06b77d; width:10px"><path d="M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z"/></svg> ${schedule.location_detail_name}</small>` 
                    : '';
            
                scheduleHtml += `
                    <div class="schedule-time-slot">
                        <div class="schedule-time">${schedule.time_start}~${schedule.time_end}</div>
                        ${eventNameHtml}
                        ${locationDetailHtml}
                        ${reservationBtn}
                    </div>
                `;
            });
            
        }
        
        return scheduleHtml;
    }

    /**
     * 예약 버튼 HTML 생성 함수
     * @param {Object} schedule - 일정 데이터
     * @param {Date} today - 오늘 날짜
     * @return {string} - 예약 버튼 또는 상태 표시 HTML
     */
    function getReservationButtonHtml(schedule, today) {
        // 현재 날짜 이후인 경우만 예약 가능
        const scheduleDate = new Date(schedule.date);
        const isInFuture = scheduleDate > today;
        
        // 4월이면 예약 불가
        const scheduleMonth = scheduleDate.getMonth() + 1;
        if (scheduleMonth === 4) {
            return `<div class="reserve-btn-integrated reserve-disabled" style="background: #dadada; color: #949494; cursor:default;">신청불가</div>`;
        }

        // 과거 날짜의 경우 지난 일정 메시지 표시
        if (!isInFuture) {
            return `<div class="reserve-btn-integrated reserve-past">지난 일정</div>`;
        }
        
        // 팀 로그인 정보 가져오기 - PHP에서 전달된 변수 사용
        const teamIdx = typeof window.teamIdx !== 'undefined' ? window.teamIdx : null;
        
        // 예약 여부 확인
        const isReserved = schedule.reserved_team_id !== null;
        // 내 예약인지 확인 (로그인한 경우만)
        const isMine = teamIdx && schedule.reserved_team_id == teamIdx;
        
        if (isReserved) {
            if (isMine) {
                // 내가 예약한 경우 취소 버튼 표시
                // 예약 취소 가능 여부 체크를 위한 로직 추가
                const now = new Date();
                const currentHour = now.getHours();
                const currentMinute = now.getMinutes();
                
                // 취소 가능 시간 확인 (10:00 ~ 18:00)
                const isValidTime = 
                    (currentHour >= 10) && 
                    (currentHour < 18 || (currentHour === 18 && currentMinute === 0));
                
                // 주의: 서버에서만 당일 예약 여부를 확인할 수 있음
                // 클라이언트에서는 예약 시간만 체크
                
                // 디버깅 정보 출력
                console.log('예약 취소 조건 확인:', {
                    schedule_date: schedule.date,
                    currentTime: `${currentHour}:${currentMinute}`,
                    isValidTime: isValidTime,
                    schedule: schedule
                });
                
                // 실제 취소 가능 여부는 서버에서 판단됨
                // 일단 시간 조건만 만족하면 버튼 표시
                if (isValidTime) {
                    return `<div class="reserve-btn-integrated reserve-mine-disabled" style="background: #e3f2fd; color: #1565c0; cursor:default;">내 예약</div>`;
                } else {
                    // 취소 불가능한 경우 상태만 표시 (클릭 불가능하도록 클래스 다르게 지정)
                    return `<div class="reserve-btn-integrated reserve-mine-disabled" style="background: #e3f2fd; color: #1565c0; cursor:default;">내 예약</div>`;
                }
            } else {
                // 타인이 예약한 경우 예약 불가 표시
                return `<div class="reserve-btn-integrated reserve-taken">예약 완료</div>`;
            }
        } else {
            // 예약 가능한 경우 예약 버튼 표시 (로그인 여부와 상관없이)
            return `
                <button class="reserve-btn-integrated reserve-available" 
                    data-schedule-id="${schedule.id}"
                    data-date="${schedule.date}"
                    data-time="${schedule.time_start}~${schedule.time_end}"
                    data-place="${schedule.place}">예약가능</button>
            `;
        }
    }

    /**
     * 이벤트 핸들러 설정
     */
    
    // 월 선택자 클릭 이벤트
    $(document).on('click', '.month-selector a', function(e) {
        e.preventDefault();
        const monthNum = parseInt($(this).data('month'));
        
        // URL 파라미터 업데이트
        let url = `schedule.php?year=${selectedYear}&month=${monthNum}`;
        if(selectedLocationId) {
            url += `&location_id=${selectedLocationId}`;
        }
        
        window.location.href = url;
    });
    
    // 장소 탭 클릭 이벤트
    $(document).on('click', '.schedule_tab a', function (e) {
        e.preventDefault();
        
        $(".schedule_tab a").removeClass("on");
        $(this).addClass("on");
        
        // 전체 보기
        if($(this).hasClass('all')) {
            // 월 선택 유지하면서 장소 필터 제거
            let url = `schedule.php?year=${selectedYear}&month=${selectedMonth}`;
            window.location.href = url;
            return;
        }
        
        const locationId = $(this).data('location-id');
        
        // URL 파라미터 업데이트
        let url = `schedule.php?year=${selectedYear}&month=${selectedMonth}&location_id=${locationId}`;
        window.location.href = url;
    });
    
    // 예약 버튼 클릭 이벤트
    $(document).on('click', '.reserve-btn, .reserve-btn-integrated.reserve-available', function() {
        // 비활성화된 버튼은 클릭 이벤트 처리 안함
        if ($(this).hasClass('reserve-mine-disabled')) {
            return;
        }
        
        // 로그인 확인
        const teamIdx = typeof window.teamIdx !== 'undefined' ? window.teamIdx : null;
        if (!teamIdx) {
            // 비로그인 상태일 경우 로그인 요청 메시지 표시
            if (confirm('공연 예약은 로그인이 필요합니다. 로그인 페이지로 이동하시겠습니까?')) {
                window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
            }
            return;
        }

        const scheduleId = $(this).data('schedule-id');
        const date = $(this).data('date');
        const time = $(this).data('time');
        const place = $(this).data('place');
        
        console.log('버튼 클릭됨:', scheduleId, date, time, place);
        
        // 예약 확인
        if(confirm(`${date} ${time} ${place}에 공연 예약을 하시겠습니까?`)) {
            makeReservation(scheduleId);
        }
    });

    /**
     * 예약 내역 관련 함수
     */
    // 예약 내역 로드 함수
    function loadMyReservations(year, month) {
        $('.reservation-list').html(`
            <div class="loading" style="text-align: center; padding: 30px;">
                <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #06b77d; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: 0 auto 10px;"></div>
                <p>예약 내역을 불러오는 중입니다...</p>
            </div>
        `);
        
        $.ajax({
            url: '../adm/get_my_reservations.php',
            type: 'GET',
            dataType: 'json',
            data: { status: 'all' },
            success: function(response) {
                if (response.success) {
                    displayMyReservations(response.reservations, year, month);
                } else {
                    $('.reservation-list').html(`
                        <div class="empty-list">
                            <p>오류가 발생했습니다: ${response.message}</p>
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                $('.reservation-list').html(`
                    <div class="empty-list">
                        <p>예약 내역을 불러오는 중 오류가 발생했습니다.</p>
                    </div>
                `);
                console.error("예약 내역 불러오기 오류:", error);
            }
        });
    }
    
    // 예약 내역 표시 함수
    function displayMyReservations(reservations, selectedYear, selectedMonth) {
        if (!reservations || reservations.length === 0) {
            $('.reservation-list').html(`
                <div class="empty-list" style="text-align: center; padding: 10px; color: #777; font-size: 12px;">
                    <p>예약 내역이 없습니다.</p>
                </div>
            `);
            $('#active-count').text('0');
            $('#active-count').css('color', ''); // 색상 초기화
            return;
        }
        
        // 날짜 기준 오름차순 정렬 (과거→미래)
        reservations.sort(function(a, b) {
            // 날짜 문자열 비교 (YYYY-MM-DD)
            return a.date.localeCompare(b.date) || a.time.localeCompare(b.time);
        });
        
        // 현재 연월 구하기
        const currentDate = new Date();
        const currentMonth = currentDate.getMonth() + 1;
        const currentYear = currentDate.getFullYear();
        
        // 선택된 연월 (브라우저 URL의 연월이나 기본값)
        selectedYear = selectedYear || currentYear;
        selectedMonth = selectedMonth || currentMonth;
        
        // 선택한 월의 예약만 필터링
        const filteredReservations = reservations.filter(function(reservation) {
            const dateParts = reservation.date.split('-');
            const reservationYear = parseInt(dateParts[0], 10);
            const reservationMonth = parseInt(dateParts[1], 10);
            
            return reservationYear === parseInt(selectedYear) && 
                   reservationMonth === parseInt(selectedMonth) &&
                   (reservation.status === 'approved' || reservation.status === 'pending');
        });
        
        // 이번 달 활성화된 예약(approved) 개수 카운트
        let selectedMonthActiveCount = filteredReservations.filter(r => r.status === 'approved').length;
        
        // 최대 예약인 경우 숫자만 빨간색으로 변경
        if (selectedMonthActiveCount >= 2) {
            $('#active-count').css('color', '#e53935');  // 숫자만 빨간색으로 변경
        } else {
            $('#active-count').css('color', '');  // 기본 색상으로 복원
        }
        
        // 테이블 형식으로 변경 - 크기 축소 및 중앙정렬 적용
        let html = '';
        
        if (filteredReservations.length === 0) {
            html = `
                <div class="empty-list" style="text-align: center; padding: 10px; color: #777; font-size: 12px;">
                    <p>해당 월에는 예약된 일정이 없습니다.</p>
                </div>
            `;
        } else {
            html = `
                <table style="width: 100%; border-collapse: collapse; font-size: 12px; margin: 0;">
                    <thead>
                        <tr style="border-bottom: 1px solid #e0e0e0;">
                            <th style="padding: 5px 3px; text-align: center; white-space: nowrap; width: 15%;">날짜</th>
                            <th style="padding: 5px 3px; text-align: center; white-space: nowrap; width: 20%;">시간</th>
                            <th style="padding: 5px 3px; width: 45%;">장소</th>
                            <th style="padding: 5px 3px; text-align: center; width: 20%;">상태</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            filteredReservations.forEach(function(reservation) {
                const dateParts = reservation.date.split('-');
                const day = parseInt(dateParts[2], 10);
                const formattedDate = `${selectedMonth}/${day}`;
                
                // 예약 상태에 따른 텍스트
                let statusText = '';
                
                switch(reservation.status) {
                    case 'approved':
                        statusText = '예약확정';
                        break;
                    case 'pending':
                        statusText = '승인대기';
                        break;
                    default:
                        statusText = '알수없음';
                }
                
                // 테이블 행으로 예약 정보 표시
                html += `
                    <tr style="border-bottom: 1px solid #f0f0f0;">
                        <td style="padding: 5px 3px; text-align: center; width: 15%;">${formattedDate}</td>
                        <td style="padding: 5px 3px; text-align: center; width: 20%;">${reservation.time}</td>
                        <td style="padding: 5px 3px; width: 45%; overflow: hidden; text-overflow: ellipsis;">${reservation.location}</td>
                        <td style="padding: 5px 3px; text-align: center; width: 20%;">
                            ${reservation.status === 'approved' && reservation.can_cancel ? `
                                <button class="cancel-btn" data-schedule-id="${reservation.schedule_id}" 
                                    data-date="${reservation.date}" 
                                    data-time="${reservation.time}"
                                    data-location="${reservation.location}" 
                                    style="border: none; background: #FFC107; color: #7E5700; border-radius: 3px; 
                                        display: inline-block; padding: 4px 8px; cursor: pointer; font-size: 11px; font-weight: bold;
                                        min-width: 70px; text-align: center; height: 25px; line-height: 16px;">
                                    <span style="color: #7E5700; font-size: 11px; font-weight: bold;">취소가능</span>
                                </button>
                            ` : reservation.status === 'approved' ? `
                                <span style="display: inline-block; padding: 4px 8px; background-color: #e3f2fd; 
                                    color: #1565c0; border-radius: 3px; font-size: 11px; font-weight: bold;
                                    min-width: 70px; text-align: center; height: 25px; line-height: 16px;">
                                    ${statusText}
                                </span>
                            ` : `
                                <span style="display: inline-block; padding: 4px 8px; background-color: #fff8e1; 
                                    color: #ff8f00; border-radius: 3px; font-size: 11px; font-weight: bold;
                                    min-width: 70px; text-align: center; height: 25px; line-height: 16px;">
                                    ${statusText}
                                </span>
                            `}
                        </td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
            `;
        }
        
        // 예약 가능 건수 표시 업데이트 (월별 제한)
        $('#active-count').text(selectedMonthActiveCount);
        
        $('.reservation-list').html(html);
    }

    // 내 예약 목록의 취소 버튼 클릭 이벤트
    $(document).on('click', '.cancel-btn', function() {
        const scheduleId = $(this).data('schedule-id');
        const date = $(this).data('date');
        const time = $(this).data('time');
        const location = $(this).data('location');
        
        if(confirm(`${date} ${time} ${location}의 예약을 취소하시겠습니까?`)) {
            $.ajax({
                url: '../adm/cancel_reservation.php',
                type: 'POST',
                dataType: 'json',
                data: { schedule_id: scheduleId },
                success: function(response) {
                    if(response.success) {
                        alert('예약이 성공적으로 취소되었습니다.');
                        // 내역 새로고침
                        loadMyReservations(selectedYear, selectedMonth);
                        // 일정 테이블 새로고침 (예약 상태 반영)
                        loadSchedules(selectedYear, selectedMonth, selectedLocationId);
                    } else {
                        alert('예약 취소에 실패했습니다: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("예약 취소 중 오류가 발생했습니다:", error);
                    alert('예약 취소 중 오류가 발생했습니다.');
                }
            });
        }
    });

    /**
     * 예약 취소 함수 - 예약한 일정 취소
     * @param {string} scheduleId - 취소할 예약 일정 ID
     */
    function cancelReservation(scheduleId) {
        $.ajax({
            url: '../adm/cancel_reservation.php',
            type: 'POST',
            dataType: 'json',
            data: { schedule_id: scheduleId },
            success: function(response) {
                if(response.success) {
                    alert('예약이 성공적으로 취소되었습니다.');
                    // 페이지 새로고침
                    location.reload();
                } else {
                    alert('예약 취소에 실패했습니다: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error("예약 취소 중 오류가 발생했습니다:", error);
                console.error("서버 응답:", xhr.responseText);
                
                // HTML 오류 메시지 확인
                if (xhr.responseText.indexOf('<') === 0) {
                    alert('서버 오류가 발생했습니다. 관리자에게 문의해주세요.');
                } else {
                    try {
                        var errorData = JSON.parse(xhr.responseText);
                        alert('예약 취소에 실패했습니다: ' + (errorData.message || '알 수 없는 오류'));
                    } catch (e) {
                        alert('예약 취소 중 오류가 발생했습니다.');
                    }
                }
            }
        });
    }
}); 