document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.search-container') || document.querySelector('.books-grid')) {
        setupBookBrowser({
            gridSelector: '.books-grid',
            searchSelector: '.search-input input',
            categorySelector: '.category-filters',  
            sortSelector: 'select[name="sort"]',
            statusSelector: 'select[name="status"]'
        });
    }
});

function setupBookBrowser(config) {
    const bookGrid = document.querySelector(config.gridSelector);
    
    // Tạo container cho phân trang nếu chưa có
    let paginationContainer = document.querySelector('.pagination-controls');
    if (!paginationContainer) {
        paginationContainer = document.createElement('div');
        paginationContainer.className = 'pagination-controls';
        bookGrid.parentNode.insertBefore(paginationContainer, bookGrid.nextSibling);
    }

    if (!bookGrid) return;

    let currentFilters = {
        search: '',
        category: 'Tất cả',
        status: 'all',
        sort: 'newest',
        page: 1, 
        limit: 12
    };

    // 1. Gọi API
    async function fetchBooks() {
        const params = new URLSearchParams();
        if (currentFilters.search) params.append('search', currentFilters.search);
        if (currentFilters.category !== 'Tất cả') params.append('category', currentFilters.category);
        if (currentFilters.status !== 'all') params.append('status', currentFilters.status);
        params.append('sort', currentFilters.sort);
        
        //Thêm params phân trang
        params.append('page', currentFilters.page);
        params.append('limit', currentFilters.limit);

        const apiUrl = `/api/books/read.php?${params.toString()}`;
        
        // Hiển thị loading nhẹ
        bookGrid.style.opacity = '0.5';

        try {
            const response = await fetch(apiUrl);
            const result = await response.json(); 
            
            displayBooks(result.data); // Chỉ truyền mảng sách vào hàm hiển thị
            renderPagination(result.pagination); // Vẽ nút phân trang
            
            bookGrid.style.opacity = '1';
        } catch (error) {
            console.error('Lỗi:', error);
            bookGrid.innerHTML = '<p style="text-align:center; color:red;">Không thể tải sách.</p>';
        }
    }

    // 2. Hiển thị Sách
    function displayBooks(bookList) {
        bookGrid.innerHTML = '';
        if (!bookList || bookList.length === 0) {
            bookGrid.innerHTML = '<p style="text-align:center; width:100%;">Không tìm thấy sách.</p>';
            return;
        }
        bookList.forEach(book => {
            const bookCard = document.createElement('div');
            bookCard.className = 'book-card';
            
            const isAvailable = book.status === 'available';
            const statusClass = isAvailable ? 'status-available' : 'status-borrowed';
            const statusText = isAvailable ? 'Có sẵn' : 'Đã mượn';
            const safeTitle = book.title.replace(/'/g, "\\'");
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
                                onclick="openBorrowModal(${book.id}, '${safeTitle}')"
                                ${!isAvailable ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''}>
                            ${isAvailable ? 'Mượn sách' : 'Đang bận'}
                        </button>
                        <a class="btn1 btn-secondary" href="books.html?id=${book.id}">Chi tiết</a>
                    </div>
                </div>
            `;
             bookGrid.appendChild(bookCard);
        });
    }

    // 3. Hàm Vẽ nút Phân trang
    function renderPagination(pagination) {
        paginationContainer.innerHTML = '';
        
        if (pagination.total_pages <= 1) return; 
        // Nút Trước
        const prevBtn = document.createElement('button');
        prevBtn.innerText = '« Trước';
        prevBtn.disabled = pagination.current_page === 1;
        prevBtn.onclick = () => changePage(pagination.current_page - 1);
        paginationContainer.appendChild(prevBtn);

        // Các nút số trang (1, 2, 3...)
        for (let i = 1; i <= pagination.total_pages; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.innerText = i;
            if (i === pagination.current_page) pageBtn.classList.add('active');
            pageBtn.onclick = () => changePage(i);
            paginationContainer.appendChild(pageBtn);
        }

        // Nút Sau
        const nextBtn = document.createElement('button');
        nextBtn.innerText = 'Sau »';
        nextBtn.disabled = pagination.current_page === pagination.total_pages;
        nextBtn.onclick = () => changePage(pagination.current_page + 1);
        paginationContainer.appendChild(nextBtn);
    }

    // 4. Hàm đổi trang
    function changePage(newPage) {
        currentFilters.page = newPage;
        fetchBooks();
        bookGrid.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // 5. Gắn sự kiện (Cập nhật logic reset page về 1 khi lọc)
    const searchInput = document.querySelector(config.searchSelector);
    if (searchInput) searchInput.addEventListener('input', (e) => {
        currentFilters.search = e.target.value; 
        currentFilters.page = 1; // Reset về trang 1 khi tìm kiếm
        fetchBooks();
    });

    const categoryLinks = document.querySelectorAll(config.categorySelector);
    categoryLinks.forEach(link => link.addEventListener('click', (e) => {
        e.preventDefault();
        categoryLinks.forEach(l => l.classList.remove('active'));
        link.classList.add('active');
        currentFilters.category = link.dataset.category || link.textContent;
        currentFilters.page = 1;
        fetchBooks();
    }));

    const sortSelect = document.querySelector(config.sortSelector);
    if(sortSelect) sortSelect.addEventListener('change', (e) => {
        currentFilters.sort = e.target.value; 
        currentFilters.page = 1;
        fetchBooks();
    });

    const statusSelect = document.querySelector(config.statusSelector);
    if(statusSelect) statusSelect.addEventListener('change', (e) => {
        currentFilters.status = e.target.value; 
        currentFilters.page = 1;
        fetchBooks();
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
