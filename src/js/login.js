document.addEventListener('DOMContentLoaded', () => {
    
    // 1. CÁC BIẾN UI
    const modal = document.getElementById('loginModal');
    const closeModalBtn = document.getElementById('closeModal');
    const loginForm = document.getElementById('loginForm');
    const togglePasswordBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const forgotPasswordLink = document.getElementById('forgotPasswordLink');
    const transferLink = document.querySelector('.transfer-link'); // Link chuyển sang đăng ký

    // 2. HÀM XỬ LÝ ĐÓNG & ĐIỀU HƯỚNG (SMART BACK)
    function handleCloseAndNavigate() {
        modal.style.display = 'none';
        const previousPage = document.referrer;

        // Nếu không có trang trước, hoặc trang trước là register (tránh vòng lặp), hoặc chính là login
        if (!previousPage || previousPage.includes('register.html') || previousPage.includes('login.html')) {
            window.location.href = 'index.html';
        } else {
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

    // 5. XỬ LÝ ĐĂNG NHẬP (GỌI API)
    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            const loginData = {
                email: email,
                password: password
            };
            
            // Sử dụng đường dẫn tương đối gốc
            const apiUrl = '/api/auth/login.php'; // Hoặc /api/auth/login.php tùy config server của bạn

            try {
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(loginData)
                });

                const result = await response.json();

                if (response.ok) {
                    // Lưu Token & User Info
                    localStorage.setItem('b4e_token', result.token);
                    localStorage.setItem('b4e_user', JSON.stringify(result.user));
                    
                    alert(result.message);
                    
                    // Chuyển hướng sau đăng nhập
                    // Nếu có trang trước đó (ví dụ đang xem sách và bị bắt đăng nhập) thì quay lại đó sẽ tốt hơn
                    // Tuy nhiên, mặc định vào AccountManage cũng ổn.
                    window.location.href = 'AccountManage.html'; 

                } else {
                    alert('Lỗi: ' + (result.error || 'Đăng nhập thất bại'));
                }

            } catch (error) {
                console.error('Lỗi khi gọi API:', error);
                alert('Không thể kết nối đến máy chủ. Vui lòng thử lại sau.');
            }
        });
    }

    // 6. CÁC LIÊN KẾT KHÁC
    if (transferLink) {
        transferLink.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'register.html';
        });
    }

    if (forgotPasswordLink) {
        forgotPasswordLink.addEventListener('click', function(e) {
            e.preventDefault();
            alert('Chức năng quên mật khẩu đang phát triển.');
        });
    }
});