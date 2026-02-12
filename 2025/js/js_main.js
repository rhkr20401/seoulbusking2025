$(document).ready(function(){
    // 사이드바
    const toggleBtn = $('.navbar_toggleBtn svg');
    const closeBtn = $('.sidebar_closeBtn svg');
    const sidebar = $('.sidebar');

    // 요소가 존재할 때만 이벤트 리스너 추가
    toggleBtn.on('click', function() {
        sidebar.toggleClass('active');
    });

    closeBtn.on('click', function() {
        sidebar.removeClass('active');
    });
});
