document.addEventListener('DOMContentLoaded', () => {
        const loadComponent = (url, elementId, callback) => {
        fetch(url)
            .then(response => response.text())
            .then(data => {
                document.getElementById(elementId).innerHTML = data;
                if (callback) {
                    callback();
                }
            });
    };

    function updateAuthUI() {
        const authContainer = document.getElementById('auth-container');
        if (!authContainer) {
            console.error("Không tìm thấy #auth-container trong header.");
            return;
        }

        const token = localStorage.getItem('b4e_token');
        const userJson = localStorage.getItem('b4e_user');

        if (token && userJson) {
            // === Nếu ĐÃ ĐĂNG NHẬP ===
            const user = JSON.parse(userJson);
            // Tạo một avatar giả bằng chữ cái đầu
            const avatarLetter = user.username.charAt(0).toUpperCase();

            authContainer.innerHTML = `
                <div class="profile-container">
                    <div class="profile-avatar" tabindex="0" aria-label="Mở menu người dùng">
                        ${avatarLetter}
                    </div>
                    <ul class="profile-dropdown">
                        <li><a href="AccountManage.html">Xin chào, ${user.username}</a></li>
                        <li><a href="AccountManage.html">Quản lý tài khoản</a></li>
                        <li><a href="#" id="logout-button">Đăng xuất</a></li>
                    </ul>
                </div>
            `;

            // Thêm sự kiện cho nút Đăng xuất (id="logout-button")
            const logoutButton = document.getElementById('logout-button');
            if (logoutButton) {
                logoutButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Xóa token và thông tin user khỏi localStorage
                    localStorage.removeItem('b4e_token');
                    localStorage.removeItem('b4e_user');
                    
                    // Tải lại trang chủ
                    alert('Bạn đã đăng xuất.');
                    window.location.href = 'index.html';
                });
            }
            
            //Thêm logic để nhấp vào avatar thì hiện dropdown
            const avatar = document.querySelector('.profile-avatar');
            const dropdown = document.querySelector('.profile-dropdown');
            avatar.addEventListener('click', () => {
                 dropdown.classList.toggle('active');
            });

        } else {

            authContainer.innerHTML = `
                <a href="login.html" class="btn btn-outline">Đăng nhập</a>
                <a href="register.html" class="btn">Đăng ký</a>`;
        }
    }

    // TẢI HEADER VÀ FOOTER
    // Tải header và TRUYỀN HÀM updateAuthUI làm callback
    if (document.getElementById('main-header')) {
        loadComponent("layout/header.html", "main-header", updateAuthUI);
    }
    if (document.getElementById('main-footer')) {
        loadComponent("layout/footer.html", "main-footer");
    }

    // XỬ LÝ NÚT BACK TO TOP
    const backToTopButton = document.getElementById('backToTop');
    if (backToTopButton) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                backToTopButton.style.display = 'block'; 
            } else {
                backToTopButton.style.display = 'none';
            }
        });

        backToTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    // Lắng nghe cú click trên toàn bộ trang web
    document.addEventListener('click', (event) => {
        
        const toggleBtn = event.target.closest('#menu-toggle');
        if (toggleBtn) {
            const navLinks = document.querySelector('.nav-links');
            const icon = toggleBtn.querySelector('i');
            
            if (navLinks) {
                navLinks.classList.toggle('active');
                
                // Đổi icon
                if (icon) {
                    if (navLinks.classList.contains('active')) {
                        icon.classList.remove('fa-bars');
                        icon.classList.add('fa-times');
                    } else {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                }
            }
        }
        
        // //Click ra ngoài thì đóng menu
        // if (!event.target.closest('#menu-toggle') && !event.target.closest('.nav-links')) {
        //     const navLinks = document.querySelector('.nav-links');
        //     const toggleBtn = document.getElementById('menu-toggle');
            
        //     if (navLinks && navLinks.classList.contains('active')) {
        //         navLinks.classList.remove('active');
        //         if (toggleBtn) {
        //             const icon = toggleBtn.querySelector('i');
        //             if (icon) {
        //                 icon.classList.remove('fa-times');
        //                 icon.classList.add('fa-bars');
        //             }
        //         }
        //     }
        // }
    });
});