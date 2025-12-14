document.addEventListener('DOMContentLoaded', () => {
    Auth.requireLogin();
    // Gọi hàm tải danh sách
    fetchUsers();
});

let currentAdminId = null; 

// --- 1. TẢI DANH SÁCH ---
async function fetchUsers() {
    const token = localStorage.getItem('b4e_token'); // Lấy token để gửi đi

    try {
        const response = await Auth.fetch(`${CONFIG.API_BASE_URL}/api/admin/users.php`, {
            method: 'GET'
        });

        const result = await response.json();
        const users = result.data || [];
        
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

            // Xử lý Role Badge
            let roleBadge = '';
            if (u.role === 'admin') {
                roleBadge = '<span class="badge" style="background:#6f42c1; color:white;">Admin</span>';
            } else if (u.role === 'super-admin') {
                roleBadge = '<span class="badge" style="background:#d63384; color:white;">Super-Admin</span>';
            } else {
                roleBadge = '<span class="badge" style="background:#17a2b8; color:white;">User</span>';
            }

            // Xử lý Nút Xóa (Logic: Không hiện nút xóa nếu là chính mình)
            let deleteBtn = '';
            
            // So sánh ID (chuyển về string để so sánh cho chắc chắn)
            if (String(u.id) !== String(currentAdminId)) {
                deleteBtn = `
                    <button class="btn btn-red" onclick="deleteUser(${u.id})" title="Xóa tài khoản" style="padding: 5px 10px;">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
            } else {
                deleteBtn = `<span style="color:#aaa; font-size:0.8rem;">(Bạn)</span>`;
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
                    <a href="user_form.html?id=${u.id}" class="btn btn-blue" title="Sửa quyền/Thông tin" style="padding: 5px 10px;">
                        <i class="fas fa-edit"></i>
                    </a>
                    &nbsp;
                    ${deleteBtn}
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

// --- 2. HÀM XÓA USER ---
async function deleteUser(id) {
    if (!confirm('CẢNH BÁO: Xóa người dùng sẽ xóa luôn lịch sử mượn và quyên góp của họ. Bạn có chắc chắn không?')) return;

    const token = localStorage.getItem('b4e_token'); // Lấy token

    try {
        const response = await fetch(`${CONFIG.API_BASE_URL}/api/admin/delete_user.php`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token // <--- QUAN TRỌNG: Gửi token khi xóa
            },
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