// file: src/js/book-browser.js

document.addEventListener('DOMContentLoaded', () => {
    // Chúng ta chỉ cần kiểm tra một container duy nhất (trên trang borrow.html)
    // Bạn có thể đổi class trong HTML thành .book-browser-container cho tổng quát nếu muốn
    if (document.querySelector('.search-container') || document.querySelector('.books-grid')) {
        setupBookBrowser({
            gridSelector: '.books-grid',
            searchSelector: '.search-input input', // Đảm bảo class này khớp với HTML của bạn
            categorySelector: '.category-filter',  // Đảm bảo class này khớp với HTML của bạn
            sortSelector: 'select[name="sort"]',
            statusSelector: 'select[name="status"]'
        });
    }
});

function setupBookBrowser(config) {
    const bookGrid = document.querySelector(config.gridSelector);
    if (!bookGrid) return;

    let currentFilters = {
        search: '',
        category: 'Tất cả',
        status: 'all',
        sort: 'newest'
    };

    // 1. Gọi API
    async function fetchBooks() {
        const params = new URLSearchParams();
        if (currentFilters.search) params.append('search', currentFilters.search);
        if (currentFilters.category !== 'Tất cả') params.append('category', currentFilters.category);
        if (currentFilters.status !== 'all') params.append('status', currentFilters.status);
        params.append('sort', currentFilters.sort);

        const apiUrl = `/api/books/read.php?${params.toString()}`;
        bookGrid.innerHTML = '<p style="text-align:center; width:100%;">Đang tải dữ liệu...</p>';

        try {
            const response = await fetch(apiUrl);
            const books = await response.json();
            displayBooks(books);
        } catch (error) {
            console.error('Lỗi:', error);
            bookGrid.innerHTML = '<p style="text-align:center; color:red;">Không thể tải sách.</p>';
        }
    }

    // 2. Hiển thị (Giao diện thống nhất)
    function displayBooks(bookList) {
        bookGrid.innerHTML = '';
        if (bookList.length === 0) {
            bookGrid.innerHTML = '<p style="text-align:center; width:100%;">Không tìm thấy sách.</p>';
            return;
        }

        bookList.forEach(book => {
            const bookCard = document.createElement('div');
            bookCard.className = 'book-card';
            
            const isAvailable = book.status === 'available';
            const statusClass = isAvailable ? 'status-available' : 'status-borrowed';
            const statusText = isAvailable ? 'Có sẵn' : 'Đã mượn';

            // Luôn luôn hiển thị đầy đủ nút bấm
            bookCard.innerHTML = `
                <div class="book-cover">
                    <img src="${book.image_url}" alt="${book.title}" onerror="this.src='img/default-book.png'">
                    <div class="book-status ${statusClass}">${statusText}</div>
                </div>
                <div class="book-details">
                    <h3 class="book-title">${book.title}</h3>
                    <p class="book-author">${book.author}</p>
                    <div class="book-actions">
                        <button class="btn btn-primary" 
                                onclick="handleBorrow(${book.id})" 
                                ${!isAvailable ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''}>
                            ${isAvailable ? 'Mượn sách' : 'Đang bận'}
                        </button>
                        <a class="btn1 btn-secondary" href="demopage.html?id=${book.id}">Chi tiết</a>
                    </div>
                </div>
            `;
            bookGrid.appendChild(bookCard);
        });
    }

    // 3. Gắn sự kiện (Giữ nguyên logic cũ)
    const searchInput = document.querySelector(config.searchSelector);
    if (searchInput) searchInput.addEventListener('input', (e) => {
        currentFilters.search = e.target.value; fetchBooks();
    });

    const categoryLinks = document.querySelectorAll(config.categorySelector);
    categoryLinks.forEach(link => link.addEventListener('click', (e) => {
        e.preventDefault();
        categoryLinks.forEach(l => l.classList.remove('active'));
        link.classList.add('active');
        currentFilters.category = link.dataset.category || link.textContent;
        fetchBooks();
    }));

    const sortSelect = document.querySelector(config.sortSelector);
    if(sortSelect) sortSelect.addEventListener('change', (e) => {
        currentFilters.sort = e.target.value; fetchBooks();
    });

    const statusSelect = document.querySelector(config.statusSelector);
    if(statusSelect) statusSelect.addEventListener('change', (e) => {
        currentFilters.status = e.target.value; fetchBooks();
    });

    // Chạy lần đầu
    fetchBooks();
}

// Hàm xử lý mượn sách toàn cục
window.handleBorrow = async function(bookId) {
    const token = localStorage.getItem('b4e_token');
    if (!token) {
        alert('Vui lòng đăng nhập để mượn sách.');
        window.location.href = 'login.html';
        return;
    }
    if (!confirm('Xác nhận mượn cuốn sách này?')) return;

    try {
        const response = await fetch('/api/borrowings/create.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify({ book_id: bookId })
        });
        const result = await response.json();
        if (response.ok) {
            alert(result.message);
            location.reload();
        } else {
            alert(result.error);
        }
    } catch (error) {
        alert('Lỗi kết nối server.');
    }
};