document.addEventListener("DOMContentLoaded", () => {
    // 1. Kiểm tra bảo mật (Sơ bộ)
    checkAdminAuth();

    // 2. Render giao diện chung
    renderSidebar();
    renderHeader();
    
    displayAdminInfo();
});

// --- 1. RENDER SIDEBAR ---
function renderSidebar() {
    // Xác định file hiện tại để active menu
    const path = window.location.pathname;
    const page = path.split("/").pop(); // Lấy tên file (vd: index.html)

    const sidebarHTML = `
        <div class="sidebar">
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
                <a href="manage_donations.html" class="${page.includes('donation') ? 'active' : ''}">
                    <i class="fas fa-gift"></i> Duyệt Quyên góp
                </a>
                <a href="manage_borrowings.html" class="${page.includes('borrow') ? 'active' : ''}">
                    <i class="fas fa-exchange-alt"></i> Quản lý Mượn/Trả
                </a>
                <a href="manage_users.html" class="${page.includes('user') ? 'active' : ''}">
                    <i class="fas fa-users"></i> Quản lý Người dùng
                </a>
                
                <div class="sidebar-divider"></div>
                
                <a href="#" onclick="handleLogout()" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Đăng xuất
                </a>
            </nav>
        </div>
    `;

    // Chèn vào đầu thẻ body
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
                    <img src="../../img/default-avatar.png" alt="Admin" class="admin-avatar">
                    <div class="admin-info">
                        <span class="admin-name" id="layoutAdminName">Admin</span>
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
    const main = document.querySelector('.main-content');
    if (sidebar) sidebar.classList.toggle('collapsed');
    if (main) main.classList.toggle('expanded');
};