document.addEventListener('DOMContentLoaded', () => {
    Auth.requireLogin();

    loadDashboardStats();
});

async function loadDashboardStats() {
    try {
        const token = localStorage.getItem('b4e_token');
        
        // 2. Gọi API với Header Authorization
        const response = await fetch(`${CONFIG.API_BASE_URL}/api/admin/get_stats.php`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });

        // 3. Xử lý lỗi 401 (Token hết hạn hoặc không hợp lệ)
        if (response.status === 401) {
            alert("Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.");
            localStorage.clear(); // Xóa rác
            window.location.href = '../index.html';
            return;
        }

        // 4. Parse JSON
        const data = await response.json();

        // Kiểm tra lỗi từ server (nếu có)
        if (data.error) {
            console.error(data.error);
            return;
        }

        // 5. Cập nhật giao diện
        // Điền dữ liệu vào các thẻ HTML
        updateStat('stat-books', data.books || 0);
        updateStat('stat-users', data.users || 0);
        updateStat('stat-pending', data.pending_donations || 0);
        updateStat('stat-returning', data.returning_books || 0);

        // Hiển thị nút hành động nhanh
        if (data.pending_donations > 0) {
            const btnPending = document.getElementById('action-pending');
            if (btnPending) btnPending.style.display = 'block';
        }
        
        if (data.returning_books > 0) {
            const btnReturn = document.getElementById('action-returning');
            if (btnReturn) btnReturn.style.display = 'block';
        }

    } catch (error) {
        console.error('Lỗi tải Dashboard:', error);
    }
}

// Hàm cập nhật số liệu
function updateStat(elementId, value) {
    const el = document.getElementById(elementId);
    if (el) el.innerText = value;
}