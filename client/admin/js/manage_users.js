document.addEventListener('DOMContentLoaded', () => {
    Auth.requireLogin();
    fetchUsers();
});

let currentAdminId = null; 

// --- 1. HÀM QUY ĐỔI ROLE THÀNH ĐIỂM SỐ ---
function getRolePower(role) {
    switch(role) {
        case 'super-admin': return 3;
        case 'admin': return 2;
        default: return 1; // user
    }
}

// --- 2. TẢI DANH SÁCH ---
async function fetchUsers() {
    // 1. Lấy thông tin Admin hiện tại từ LocalStorage
    const userStr = localStorage.getItem('b4e_user');
    if (!userStr) return; // Auth.requireLogin đã xử lý redirect rồi

    const currentUser = JSON.parse(userStr);
    const myRolePower = getRolePower(currentUser.role); 
    currentAdminId = currentUser.id;

    try {
        const response = await Auth.fetch(`${CONFIG.API_BASE_URL}/api/admin/users.php`, {
            method: 'GET'
        });

        const result = await response.json();
        const users = result.data || [];
        
        // Cập nhật lại ID từ server nếu cần thiết (ưu tiên localstorage để render nhanh, nhưng server là chuẩn nhất)
        if (result.current_admin_id) {
            currentAdminId = result.current_admin_id;
        }

        const tbody = document.getElementById('usersTableBody');
        tbody.innerHTML = '';

        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Không có người dùng nào.</td></tr>';
            return;
        }

        users.forEach(u => {
            const tr = document.createElement('tr');
            tr.id = `row-${u.id}`;

            // --- Xử lý Role Badge ---
            let roleBadge = '';
            if (u.role === 'admin') {
                roleBadge = '<span class="badge" style="background:#6f42c1; color:white;">Admin</span>';
            } else if (u.role === 'super-admin') {
                roleBadge = '<span class="badge" style="background:#d63384; color:white;">Super-Admin</span>';
            } else {
                roleBadge = '<span class="badge" style="background:#17a2b8; color:white;">User</span>';
            }

            // --- LOGIC ẨN/HIỆN NÚT ---
            const targetUserPower = getRolePower(u.role);
            let actionButtons = '';

            // Trường hợp 1: Là chính mình -> Hiện nút Sửa (thường mình được sửa thông tin mình), Ẩn nút Xóa
            // Lưu ý: So sánh lỏng (==) vì ID từ API có thể là number hoặc string
            if (u.id == currentAdminId) {
                actionButtons = `
                    <a href="user_form.html?id=${u.id}" class="btn btn-blue" title="Sửa thông tin cá nhân" style="padding: 5px 10px;">
                        <i class="fas fa-edit"></i>
                    </a>
                    <span style="color:#aaa; font-size:0.8rem; margin-left:5px;">(Bạn)</span>
                `;
            } 
            // Trường hợp 2: Quyền của mình CAO HƠN người kia -> Hiện đủ nút
            else if (myRolePower > targetUserPower) {
                actionButtons = `
                    <a href="user_form.html?id=${u.id}" class="btn btn-blue" title="Sửa quyền/Thông tin" style="padding: 5px 10px;">
                        <i class="fas fa-edit"></i>
                    </a>
                    &nbsp;
                    <button class="btn btn-red" onclick="deleteUser(${u.id})" title="Xóa tài khoản" style="padding: 5px 10px;">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
            } 
            // Trường hợp 3: Quyền thấp hơn hoặc bằng (Admin nhìn Admin khác, hoặc Admin nhìn Super-Admin) -> Ẩn hết
            else {
                actionButtons = `<span style="color:#aaa; font-style:italic; font-size:0.9rem;">Không đủ quyền hạn</span>`;
            }

            // Định dạng ngày
            const joinDate = u.created_at ? new Date(u.created_at).toLocaleDateString('vi-VN') : '---';

            tr.innerHTML = `
                <td>#${u.id}</td>
                <td>
                    <b>${u.username}</b><br>
                    <small style="color:#666;">${u.email}</small>
                </td>
                <td>${roleBadge}</td>
                <td>
                    <div style="font-size:0.9rem;">
                        <i class="fas fa-phone" style="width:15px; text-align:center;"></i> ${u.phone || '---'}<br>
                        <i class="fas fa-map-marker-alt" style="width:15px; text-align:center;"></i> ${u.address || '---'}
                    </div>
                </td>
                <td>${joinDate}</td>
                <td>
                    ${actionButtons}
                </td>
            `;
            tbody.appendChild(tr);
        });

    } catch (error) {
        console.error('Lỗi tải user:', error);
        document.getElementById('usersTableBody').innerHTML = 
            '<tr><td colspan="6" style="text-align:center; color:red;">Lỗi kết nối server.</td></tr>';
    }
}

// ... (Giữ nguyên hàm deleteUser)

// --- 2. HÀM XÓA USER ---
async function deleteUser(id) {
    if (!confirm('CẢNH BÁO: Xóa người dùng sẽ xóa luôn lịch sử mượn và quyên góp của họ. Bạn có chắc chắn không?')) return;

    const token = localStorage.getItem('b4e_token'); // Lấy token

    try {
        const response = await Auth.fetch(`${CONFIG.API_BASE_URL}/api/admin/delete_user.php`, {
            method: 'POST',
            body: JSON.stringify({ id: id })
        });
        
        const result = await response.json();
        
        if (response.ok) {
            alert(result.message || 'Đã xóa người dùng.');
            const row = document.getElementById(`row-${id}`);
            if(row) row.remove();
        } else {
            alert('Lỗi: ' + (result.error || 'Không thể xóa'));
        }
    } catch (error) {
        alert('Lỗi kết nối.');
    }
}