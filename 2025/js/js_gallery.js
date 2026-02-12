document.addEventListener("DOMContentLoaded", function () {
    const galleryContainer = document.getElementById("gallery-container");
    const modal = document.getElementById("modal");
    const modalImg = document.getElementById("modal-img");
    const prevBtn = document.getElementById("prev");
    const nextBtn = document.getElementById("next");
    const closeBtn = document.querySelector(".close");

    let allImages = [];      // 전체 이미지 목록
    let currentIndex = 0;    // 현재 클릭한 이미지 인덱스
    const perPage = 15;      // 페이지당 15장
    let currentPage = 1;

    // 1. 전체 이미지 JSON 불러오기
    async function loadGallery() {
        const res = await fetch("../adm/gallery.php");
        const data = await res.json();

        allImages = data; // [{src: "사진이름.jpg"}, ...]

        renderPage(currentPage);
        renderPagination();
    }

    // 2. 현재 페이지 렌더링
    function renderPage(page) {
        galleryContainer.innerHTML = "";

        const start = (page - 1) * perPage;
        const end = start + perPage;

        const pageImages = allImages.slice(start, end);

        pageImages.forEach((imgObj, i) => {
            const img = document.createElement("img");
            img.src = `../img/gallery/${imgObj.src}`;
            img.loading = "lazy";
            img.dataset.index = start + i;

            img.addEventListener("click", () => openModal(start + i));

            galleryContainer.appendChild(img);
        });
    }

    // 3. 페이지네이션 렌더링
    function renderPagination() {
        const totalPage = Math.ceil(allImages.length / perPage);
        const maxButtons = 5; // 한 번에 보여줄 페이지 수
        let html = "";

        // 현재 페이지가 포함된 "그룹" 계산
        const currentGroup = Math.ceil(currentPage / maxButtons);
        const startPage = (currentGroup - 1) * maxButtons + 1;
        let endPage = startPage + maxButtons - 1;

        if (endPage > totalPage) endPage = totalPage;

        // << 첫 그룹 이동
        html += `<button class="page-btn first-page" data-page="1">«</button>`;

        // < 이전 그룹 이동
        const prevGroupPage = startPage - maxButtons > 0 ? startPage - maxButtons : 1;
        html += `<button class="page-btn prev-page" data-page="${prevGroupPage}">‹</button>`;

        // 숫자 페이지 출력
        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="page-btn ${i === currentPage ? "active" : ""}" data-page="${i}">${i}</button>`;
        }

        // > 다음 그룹 이동
        const nextGroupPage = startPage + maxButtons <= totalPage ? startPage + maxButtons : totalPage;
        html += `<button class="page-btn next-page" data-page="${nextGroupPage}">›</button>`;

        // >> 마지막 그룹 이동
        const lastGroupStart = Math.floor((totalPage - 1) / maxButtons) * maxButtons + 1;
        html += `<button class="page-btn last-page" data-page="${lastGroupStart}">»</button>`;

        document.getElementById("pagination").innerHTML = html;

        // 클릭 이벤트
        document.querySelectorAll(".page-btn").forEach(btn => {
            btn.addEventListener("click", () => {
                const page = Number(btn.dataset.page);
                currentPage = page;
                renderPage(currentPage);
                renderPagination();
            });
        });
    }


    // 4. 모달 열기
    function openModal(index) {
        currentIndex = index;
        modal.style.display = "flex";
        updateModalImage();
    }

    // 5. 모달 이미지 업데이트
    function updateModalImage() {
        const fileName = allImages[currentIndex].src;

        // 확장자 제거 (마지막 '.' 기준 분리)
        const nameWithoutExt = fileName.replace(/\.[^/.]+$/, "");

        modalImg.src = `../img/gallery/${fileName}`;

        // 이미지 이름 표시
        const caption = document.getElementById("modal-caption");
        if (caption) caption.textContent = nameWithoutExt;
    }

    // 6. 모달 닫기
    closeBtn.addEventListener("click", () => modal.style.display = "none");
    modal.addEventListener("click", e => {
        if (e.target === modal) modal.style.display = "none";
    });

    // 7. 좌우 이동 버튼
    prevBtn.addEventListener("click", () => {
        currentIndex = (currentIndex - 1 + allImages.length) % allImages.length;
        updateModalImage();
    });

    nextBtn.addEventListener("click", () => {
        currentIndex = (currentIndex + 1) % allImages.length;
        updateModalImage();
    });

    loadGallery();
});
