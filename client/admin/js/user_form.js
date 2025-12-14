// Lấy ID từ URL
const urlParams = new URLSearchParams(window.location.search);
const userId = urlParams.get('id');

document.addEventListener('DOMContentLoaded', () => {
    Auth.requireLogin();
    loadUserDetails(userId);
});

// --- 1. TẢI THÔNG TIN ---
async function loadUserDetails(id) {
    try {
        const response = await fetch(`${CONFIG.API_BASE_URL}/api/admin/get_user.php?id=${id}`);
        
        if (!response.ok) throw new Error("Không thể tải thông tin.");

        const user = await response.json();

        // Điền dữ liệu vào form
        document.getElementById('userId').value = user.id;
        document.getElementById('email').value = user.email;
        document.getElementById('username').value = user.username;
        document.getElementById('role').value = user.role;
        document.getElementById('phone').value = user.phone || '';
        document.getElementById('address').value = user.address || '';

    } catch (error) {
        console.error(error);
        alert('Lỗi: ' + error.message);
    }
}

// --- 2. XỬ LÝ LƯU (SUBMIT) ---
async function handleUserSubmit(e) {
    e.preventDefault();

    const btn = document.getElementById('btn-save');
    btn.innerText = 'Đang lưu...';
    btn.disabled = true;

    // Lấy dữ liệu từ form
    const data = {
        id: document.getElementById('userId').value,
        username: document.getElementById('username').value,
        role: document.getElementById('role').value,
        phone: document.getElementById('phone').value,
        address: document.getElementById('address').value
    };

    try {
        const response = await Auth.fetch(`${CONFIG.API_BASE_URL}/api/admin/update_user.php`, {
            method: 'POST',
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (response.ok) {
            document.getElementById('alert-message').innerHTML = 
                `<div style='color:green; margin-bottom:15px; padding:10px; background:#d4edda; border-radius:4px;'>
                    ${result.message} <a href='manage_users.html' style='font-weight:bold;'>Quay lại danh sách</a>
                </div>`;
        } else {
            throw new Error(result.error || 'Lỗi cập nhật');
        }

    } catch (error) {
        document.getElementById('alert-message').innerHTML = 
            `<div style='color:red; margin-bottom:15px; padding:10px; background:#f8d7da; border-radius:4px;'>
                Lỗi: ${error.message}
            </div>`;
    } finally {
        btn.innerText = 'Lưu thay đổi';
        btn.disabled = false;
    }
} 