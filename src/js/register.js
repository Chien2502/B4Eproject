document.addEventListener('DOMContentLoaded', () => {
    
    // 1. CÁC BIẾN UI
    const modal = document.getElementById('registerModal');
    const closeModalBtn = document.getElementById('closeModal');
    const registerForm = document.getElementById('registerForm');
    const togglePasswordBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    // 2. HÀM XỬ LÝ ĐÓNG & ĐIỀU HƯỚNG (SMART BACK)
    function handleCloseAndNavigate() {
        // Ẩn modal ngay lập tức cho mượt
        modal.style.display = 'none';

        // Lấy trang trước đó
        const previousPage = document.referrer;

        // Logic kiểm tra:
        // Nếu không có trang trước (nhập URL trực tiếp)
        // HOẶC trang trước là login.html
        // HOẶC trang trước là chính nó (register.html - trường hợp reload)
        if (!previousPage || previousPage.includes('login.html') || previousPage.includes('register.html')) {
            // -> Chuyển về Trang chủ
            window.location.href = 'index.html'; 
        } else {
            // Các trường hợp khác (từ trang chủ, trang mượn sách...) -> Quay lại
            window.history.back();
        }
    }

    // 3. GẮN SỰ KIỆN ĐÓNG MODAL
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', handleCloseAndNavigate);
    }

    window.addEventListener('click', (event) => {
        if (event.target === modal) {
            handleCloseAndNavigate();
        }
    });

    // 4. XỬ LÝ HIỆN/ẨN MẬT KHẨU
    if (togglePasswordBtn) {
        togglePasswordBtn.addEventListener('click', function() {
            const icon = this.querySelector('img');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.src = 'https://img.icons8.com/material-outlined/24/000000/invisible.png';
                icon.alt = 'Hide Password';
            } else {
                passwordInput.type = 'password';
                icon.src = 'https://img.icons8.com/material-outlined/24/000000/visible.png';
                icon.alt = 'Show Password';
            }
        });
    }

    // 5. XỬ LÝ SUBMIT FORM ĐĂNG KÝ (GỌI API)
    if (registerForm) {
        registerForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;

            const registerData = {
                username: username,
                email: email,
                password: password
            };

            try {
                // Gọi API Register (Đường dẫn tương đối gốc)
                const response = await fetch('/api/auth/register.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(registerData)
                });

                const result = await response.json();

                if (response.ok) {
                    // Thành công (201 Created)
                    alert('Đăng ký thành công! Vui lòng đăng nhập.');
                    window.location.href = 'login.html';
                } else {
                    // Lỗi (400, 409...)
                    alert('Lỗi: ' + (result.error || 'Đăng ký thất bại'));
                }

            } catch (error) {
                console.error('Error:', error);
                alert('Không thể kết nối đến máy chủ.');
            }
        });
    }
});