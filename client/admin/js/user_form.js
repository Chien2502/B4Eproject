// Lấy ID từ URL
const urlParams = new URLSearchParams(window.location.search);
const userId = urlParams.get('id');

document.addEventListener('DOMContentLoaded', () => {
    const currentUser = Auth.requireLogin();
    window.currentUser = currentUser;
    loadUserDetails(userId);
});

// --- HÀM HỖ TRỢ: QUY ĐỔI ROLE RA ĐIỂM ---
function getRolePower(role) {
    switch(role) {
        case 'super-admin': return 3;
        case 'admin': return 2;
        default: return 1; // user
    }
}

// --- 1. TẢI THÔNG TIN ---
async function loadUserDetails(id) {
    try {
        const response = await Auth.fetch(`${CONFIG.API_BASE_URL}/api/admin/get_user.php?id=${id}`);
        const user = await response.json();

        // Điền dữ liệu vào form
        document.getElementById('userId').value = user.id;
        document.getElementById('email').value = user.email;
        document.getElementById('username').value = user.username;
        document.getElementById('phone').value = user.phone || '';
        document.getElementById('address').value = user.address || '';

        // --- XỬ LÝ LOGIC PHÂN QUYỀN TRONG SELECT BOX ---
        const roleSelect = document.getElementById('role');
        const myPower = getRolePower(window.currentUser.role);
        const targetUserPower = getRolePower(user.role);

        // A. Điền giá trị hiện tại
        roleSelect.value = user.role;

        // B. Duyệt qua các option để khóa những quyền cao hơn mình
        // Ví dụ: Mình là Admin (2), không thể chọn Super-Admin (3) cho người khác
        for (let i = 0; i < roleSelect.options.length; i++) {
            const option = roleSelect.options[i];
            const optionPower = getRolePower(option.value);

            if (optionPower > myPower) {
                option.disabled = true;
                option.innerText += " (Không đủ quyền)";
            }
        }

        // C. Trường hợp đặc biệt: Nếu người được sửa có quyền CAO HƠN hoặc BẰNG mình
        // (Ví dụ: Admin xem profile Super-Admin, hoặc Admin xem Admin khác)
        // Thì không được phép sửa Role của họ xuống thấp hơn (tránh lạm quyền)
        // Logic: Chỉ Super-Admin mới sửa được Super-Admin khác.
        if (targetUserPower >= myPower && window.currentUser.id != user.id) {
             roleSelect.disabled = true; // Khóa luôn ô chọn
             roleSelect.title = "Bạn không thể thay đổi quyền của người có cấp bậc ngang hoặc cao hơn.";
        }

    } catch (error) {
        console.error(error);
        alert('Lỗi: ' + error.message);
    }
}

// --- 2. XỬ LÝ LƯU (SUBMIT) ---
async function handleUserSubmit(e) {
    e.preventDefault();

    const btn = document.getElementById('btn-save');
    const roleSelect = document.getElementById('role');
    const selectedRole = roleSelect.value;
    
    // --- VALIDATE LOGIC TRƯỚC KHI GỬI ---
    const myPower = getRolePower(window.currentUser.role);
    const selectedRolePower = getRolePower(selectedRole);

    if (selectedRolePower > myPower) {
        alert("Lỗi bảo mật: Bạn không thể cấp quyền cao hơn quyền hạn của mình!");
        return;
    }

    // Hiệu ứng loading
    const originalText = btn.innerText;
    btn.innerText = 'Đang lưu...';
    btn.disabled = true;

    // Lấy dữ liệu từ form
    const data = {
        id: document.getElementById('userId').value,
        username: document.getElementById('username').value,
        role: selectedRole,
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
        btn.innerText = originalText;
        btn.disabled = false;
    }
}