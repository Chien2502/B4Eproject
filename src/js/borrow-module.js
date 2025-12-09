(function() {
    // Biến nội bộ lưu ID sách đang chọn
    let _currentBookId = null;

    function loadBorrowModal() {
        fetch('/src/layout/modal_borrow.html')
            .then(response => response.text())
            .then(html => {
                const div = document.createElement('div');
                div.innerHTML = html;
                document.body.appendChild(div.firstElementChild);

                setupEventListeners();
            })
            .catch(err => console.error('Lỗi tải modal mượn sách:', err));
    }

    function setupEventListeners() {
        const modal = document.getElementById('borrowModal');
        const btnCloseX = document.getElementById('btnCloseX');
        const btnCancel = document.getElementById('btnCancelBorrow');
        const btnConfirm = document.getElementById('btnConfirmBorrow');

        const closeModal = () => {
            modal.style.display = 'none';
            _currentBookId = null;
        };

        if(btnCloseX) btnCloseX.onclick = closeModal;
        if(btnCancel) btnCancel.onclick = closeModal;

        // Đóng khi click ra ngoài
        window.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        // Sự kiện Xác nhận Mượn (Gọi API)
        if(btnConfirm) btnConfirm.onclick = async () => {
            const modal = document.getElementById('borrowModal');
            const bookIdToBorrow = modal.dataset.bookId; 

            if (!bookIdToBorrow) {
            console.error("Không tìm thấy ID sách để mượn");
            return;
            }

            const token = localStorage.getItem('b4e_token');
            const originalText = btnConfirm.innerText;
            
            btnConfirm.innerText = 'Đang xử lý...';
            btnConfirm.disabled = true;

            try {
                const res = await fetch('/api/borrowings/create.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + token
                    },
                    body: JSON.stringify({ book_id: bookIdToBorrow })
                });

                const result = await res.json();

                if (res.ok) {
                    alert('Mượn sách thành công! Vui lòng trả đúng hạn.');
                    closeModal();
                    location.reload(); // Tải lại trang để cập nhật trạng thái
                } else {
                    alert('Lỗi: ' + result.error);
                }
            } catch (error) {
                alert('Lỗi kết nối server.');
            } finally {
                btnConfirm.innerText = originalText;
                btnConfirm.disabled = false;
            }
        };
    }

    window.openBorrowModal = function(bookId, bookTitle) {
    console.log("1. [DEBUG] Bắt đầu hàm openBorrowModal");

    // 1. Kiểm tra Token
    const token = localStorage.getItem('b4e_token');
    if (!token) {
        alert('Vui lòng đăng nhập để mượn sách.');
        window.location.href = 'login.html';
        return;
    }

    // 2. Tìm Modal
    const modal = document.getElementById('borrowModal');
    if (!modal) {
        console.error("2. [LỖI] Không tìm thấy modal có ID='borrowModal'");
        alert('Giao diện đang tải, vui lòng thử lại sau giây lát.');
        return;
    }

    // 3. Lưu BookID vào chính cái Modal (Cách này an toàn hơn dùng biến ngoài)
    // Chúng ta lưu vào data-attribute
    modal.dataset.bookId = bookId;
    console.log("3. [DEBUG] Đã lưu BookID:", bookId);

    // 4. Điền thông tin (Kèm kiểm tra kỹ càng)
    const titleEl = document.getElementById('modalBookTitle');
    const dateEl = document.getElementById('modalBorrowDate');
    const dueEl = document.getElementById('modalDueDate');

    if (titleEl) {
        titleEl.innerText = bookTitle;
    } else {
        console.error("4. [LỖI] Không tìm thấy ID 'modalBookTitle' trong HTML modal");
    }

    // 5. Tính ngày tháng
    const today = new Date();
    const dueDate = new Date();
    dueDate.setDate(today.getDate() + 14);

    const fmt = (d) => d.toLocaleDateString('vi-VN', {day: '2-digit', month: '2-digit', year: 'numeric'});

    if (dateEl) dateEl.innerText = fmt(today);
    if (dueEl) dueEl.innerText = fmt(dueDate);

    // 6. Hiện Modal
    modal.style.display = 'flex';
    console.log("5. [SUCCESS] Đã mở modal thành công");
};

    document.addEventListener('DOMContentLoaded', loadBorrowModal);

})();