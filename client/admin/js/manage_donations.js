document.addEventListener('DOMContentLoaded', () => {
   Auth.requireLogin();
    fetchDonations();
});
const token = localStorage.getItem('b4e_token');
// --- 1. TẢI DANH SÁCH ---
async function fetchDonations() {
    try {

        
        
        // 2. Gọi API với Header Authorization
        const response = await fetch(`${CONFIG.API_BASE_URL}/api/admin/donations.php`, {
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
        const result = await response.json();
        
        // Xử lý mảng dữ liệu
        const donations = Array.isArray(result) ? result : (result.data || []);

        const tbody = document.getElementById('donationsTableBody');
        tbody.innerHTML = '';

        if (donations.length === 0) {
            // Hiện màn hình trống
            document.getElementById('table-container').style.display = 'none';
            document.getElementById('empty-state').style.display = 'block';
            return;
        }

        // Hiện bảng, ẩn màn hình trống
        document.getElementById('table-container').style.display = 'block';
        document.getElementById('empty-state').style.display = 'none';

        donations.forEach(item => {
            const tr = document.createElement('tr');
            tr.id = `row-${item.id}`;

            tr.innerHTML = `
                <td>
                    <b>${item.username}</b><br>
                    <small style="color:#666;">${item.email}</small>
                </td>
                <td>
                    <span style="color:#007bff; font-weight:bold; font-size:1.1rem;">
                        ${item.book_title}
                    </span><br>
                    TG: ${item.book_author}<br>
                    <small>NXB: ${item.book_publisher || 'N/A'} (${item.book_year || '-'})</small>
                </td>
                <td>
                    <span class="badge bg-yellow">${item.book_condition || 'Normal'}</span>
                </td>
                <td>${item.donation_type}</td>
                <td>
                    <button class="btn btn-green" onclick="processDonation(${item.id}, 'approve')" style="margin-right:5px;">
                        <i class="fas fa-check"></i> Tiếp nhận
                    </button>
                    <br>
                    <button class="btn btn-red" onclick="processDonation(${item.id}, 'reject')">
                        <i class="fas fa-times"></i> Từ chối
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });

    } catch (error) {
        console.error('Lỗi tải dữ liệu:', error);
        alert('Không thể tải danh sách quyên góp.');
    }
}

// --- 2. XỬ LÝ DUYỆT / TỪ CHỐI ---
async function processDonation(id, action) {
    const confirmMsg = action === 'approve' 
        ? 'Bạn có chắc chắn muốn DUYỆT sách này? Nó sẽ được thêm vào kho sách ngay lập tức.' 
        : 'Bạn có chắc chắn muốn TỪ CHỐI yêu cầu này?';

    if (!confirm(confirmMsg)) return;

    // Xác định API endpoint
    const apiEndpoint = action === 'approve' 
        ? '/api/admin/approve_donation.php' 
        : '/api/admin/reject_donation.php';

    try {
        const response = await fetch(`${CONFIG.API_BASE_URL}${apiEndpoint}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' ,
                'Authorization': 'Bearer ' + token},
            
            body: JSON.stringify({ donation_id: id })
        });

        const result = await response.json();

        if (response.ok) {
            alert(result.message || 'Thao tác thành công!');
            
            // Hiệu ứng xóa dòng mượt mà
            const row = document.getElementById(`row-${id}`);
            if (row) {
                row.style.transition = 'all 0.5s';
                row.style.opacity = '0';
                row.style.transform = 'translateX(20px)';
                
                setTimeout(() => {
                    row.remove();
                    // Kiểm tra lại nếu hết dòng thì hiện thông báo trống
                    const tbody = document.getElementById('donationsTableBody');
                    if (tbody.children.length === 0) {
                        document.getElementById('table-container').style.display = 'none';
                        document.getElementById('empty-state').style.display = 'block';
                    }
                }, 500);
            }
        } else {
            alert('Lỗi: ' + (result.error || 'Có lỗi xảy ra'));
        }
    } catch (error) {
        console.error(error);
        alert('Lỗi kết nối server.');
    }
}