document.addEventListener("DOMContentLoaded", () => {
    // 1. Kiểm tra bảo mật (Sơ bộ)
    checkAdminAuth();

    // 2. Render giao diện chung

    createOverlay();

    renderSidebar();
    renderHeader();
    
    displayAdminInfo();
});

// Hàm tạo Overlay động
function createOverlay() {
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    overlay.onclick = toggleSidebar; // Bấm vào vùng tối thì đóng sidebar
    document.body.appendChild(overlay);
}

// --- 1. RENDER SIDEBAR ---
function renderSidebar() {
    const path = window.location.pathname;
    const page = path.split("/").pop();

    const sidebarHTML = `
        <div class="sidebar" id="adminSidebar">
            <button class="sidebar-close-btn" onclick="toggleSidebar()">
                <i class="fas fa-times"></i>
            </button>

            <div class="sidebar-brand">
                <h2>B4E Admin</h2>
            </div>
            
            <nav class="sidebar-menu">
                <a href="index.html" class="${page === 'index.html' || page === '' ? 'active' : ''}">
                    <i class="fas fa-home"></i> Tổng quan
                </a>
                <a href="manage_books.html" class="${page.includes('book') ? 'active' : ''}">
                    <i class="fas fa-book"></i> Quản lý Sách
                </a>

                <a href="manage_categories.html" class="${page.includes('categor') ? 'active' : ''}">
                    <i class="fas fa-book"></i> Quản lý Thể loại sách
                </a>
    
                <a href="manage_donations.html" class="${page.includes('donation') ? 'active' : ''}">
                    <i class="fas fa-gift"></i> Duyệt Quyên góp
                </a>
                <a href="manage_borrowings.html" class="${page.includes('borrow') ? 'active' : ''}">
                    <i class="fas fa-exchange-alt"></i> Quản lý Mượn/Trả
                </a>
                <a href="manage_users.html" class="${page.includes('user') ? 'active' : ''}">
                    <i class="fas fa-users"></i> Quản lý Người dùng
                </a>

                </a>
                <a href="../index.html">
                    <i class="fas fa-home"></i> Quay về trang chủ
                </a>
                
                <div class="sidebar-divider"></div>
                
                <a href="#" onclick="handleLogout()" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Đăng xuất
                </a>
            </nav>
        </div>
    `;

    document.body.insertAdjacentHTML('afterbegin', sidebarHTML);
}

// --- 2. RENDER HEADER ---
function renderHeader() {
    // Tìm thẻ main-content để chèn Header vào đầu nó
    const mainContent = document.querySelector('.main-content');
    if (!mainContent) return;

    const headerHTML = `
        <header class="top-header">
            <div class="header-left">
                <button id="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="page-title">Hệ Thống Quản Trị Thư Viện</span>
            </div>
            
            <div class="header-right">
                <div class="admin-profile">
                    <div class="admin-info">
                        <small class="admin-role">Quản trị viên</small>
                    </div>
                </div>
            </div>
        </header>
    `;

    mainContent.insertAdjacentHTML('afterbegin', headerHTML);
}

// --- 3. CÁC HÀM TIỆN ÍCH ---
function checkAdminAuth() {
    // 1. Lấy dữ liệu từ LocalStorage
    const token = localStorage.getItem('b4e_token');
    const userStr = localStorage.getItem('b4e_user');

    // 2. Kiểm tra sơ bộ: Nếu không có token -> Đá về login
    if (!token || !userStr) {
        alert('Vui lòng đăng nhập để truy cập trang quản trị!');
        window.location.href = '/client/index.html'; 
        return;
    }

    try {
        const user = JSON.parse(userStr);
        
        // 3. Kiểm tra quyền: Chỉ Admin/Super-Admin mới được ở lại
        if (user.role !== 'admin' && user.role !== 'super-admin') {
            alert('Bạn không có quyền truy cập trang này!');
            window.location.href = '/client/index.html'; 
            return;
        }
        
    } catch (e) {
        localStorage.clear();
        window.location.href = '/client/index.html';
    }
}

// Hiển thị tên Admin lấy từ localStorage
function displayAdminInfo() {
    const adminName = localStorage.getItem('b4e_username');
    if (adminName) {
        const nameEl = document.getElementById('layoutAdminName');
        if (nameEl) nameEl.innerText = adminName;
    }
}

// Xử lý Đăng xuất
window.handleLogout = function() {
    if (confirm('Bạn có chắc chắn muốn đăng xuất?')) {
        // Xóa thông tin lưu trữ
        localStorage.removeItem('b4e_token');
        localStorage.removeItem('b4e_user');
        localStorage.removeItem('b4e_role');
        
        // Chuyển hướng về trang login
        window.location.href = '/client/index.html';
    }
};

// Toggle Sidebar (Cho mobile)
window.toggleSidebar = function() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const body = document.body;

    if (window.innerWidth <= 768) {
        // --- LOGIC MOBILE ---
        // Thay vì dùng 'collapsed', ta dùng 'mobile-active' để trượt ra
        sidebar.classList.toggle('mobile-active');
        overlay.classList.toggle('active');

        // Khóa cuộn trang khi mở menu (để tránh user cuộn nội dung bên dưới)
        if (sidebar.classList.contains('mobile-active')) {
            body.style.overflow = 'hidden';
        } else {
            body.style.overflow = '';
        }
    } else {
        // --- LOGIC DESKTOP ---
        sidebar.classList.toggle('collapsed');
        const main = document.querySelector('.main-content');
        if (main) main.classList.toggle('expanded');
    }
};