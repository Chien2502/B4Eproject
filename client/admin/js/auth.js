const Auth = {
    // Hàm lấy token
    getToken: () => localStorage.getItem('b4e_token'),

    // Hàm kiểm tra đăng nhập bắt buộc
    requireLogin: () => {
        const token = localStorage.getItem('b4e_token');
        const userStr = localStorage.getItem('b4e_user');
        if (!token || !userStr) {
            alert('Phiên đăng nhập hết hạn hoặc chưa đăng nhập.');
            window.location.href = '../index.html'; // Chỉnh đường dẫn cho đúng với file của bạn
            throw new Error("Unauthorized"); // Dừng script lại
        }
        return JSON.parse(userStr);
    },

    // Hàm Fetch
    fetch: async (url, options = {}) => {
        const token = localStorage.getItem('b4e_token');

        // 1. Tự động thêm Header Authorization
        const headers = options.headers || {};
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        // 2. Tự động thêm Content-Type là JSON nếu không phải là FormData
        if (!(options.body instanceof FormData) && !headers['Content-Type']) {
            headers['Content-Type'] = 'application/json';
        }

        options.headers = headers;

        // 3. Gọi fetch gốc
        const response = await fetch(url, options);

        // 4. Tự động xử lý lỗi 401 (Hết phiên)
        if (response.status === 401) {
            alert("Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.");
            localStorage.removeItem('b4e_token');
            localStorage.removeItem('b4e_user');
            window.location.href = '../index.html';
            throw new Error("Session expired");
        }

        return response;
    }
};