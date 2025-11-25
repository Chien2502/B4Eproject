document.addEventListener('DOMContentLoaded', async () => {
    // 1. Lấy ID từ URL (ví dụ: demopage.html?id=123)
    const urlParams = new URLSearchParams(window.location.search);
    const bookId = urlParams.get('id');

    if (!bookId) {
        alert('Không tìm thấy ID sách.');
        window.location.href = 'borrow.html';
        return;
    }

    // 2. Gọi API lấy chi tiết sách
    try {
        const response = await fetch(`/api/books/read_single.php?id=${bookId}`);
        
        if (!response.ok) {
            throw new Error('Không tìm thấy sách hoặc lỗi server.');
        }

        const book = await response.json();
        renderBookDetails(book);

    } catch (error) {
        console.error(error);
        document.getElementById('book-content').innerHTML = 
            `<h2 style="text-align:center; color:red;">${error.message}</h2>`;
    }
});

// 3. Hàm hiển thị dữ liệu lên HTML
function renderBookDetails(book) {
    // Điền thông tin
    document.title = `${book.title} - B4E Library`;
    document.getElementById('book-img').src = book.image_url || 'img/default-book.png';
    document.getElementById('book-title').textContent = book.title;
    document.getElementById('book-author').textContent = book.author;
    document.getElementById('book-category').textContent = book.category_name || 'Chưa phân loại';
    document.getElementById('book-year').textContent = book.year || 'Đang cập nhật';
    document.getElementById('book-publisher').textContent = book.publisher || 'Đang cập nhật';
    document.getElementById('book-desc').textContent = book.description || 'Chưa có mô tả.';

    // Xử lý trạng thái và nút bấm
    const statusBadge = document.getElementById('status-badge');
    const borrowBtn = document.getElementById('btn-borrow');

    if (book.status === 'available') {
        statusBadge.textContent = 'Có sẵn';
        statusBadge.className = 'book-status status-available';
        
        // Kích hoạt nút mượn
        borrowBtn.disabled = false;
        borrowBtn.textContent = 'Mượn sách này';
        borrowBtn.onclick = () => handleBorrowDetail(book.id);
    } else {
        statusBadge.textContent = 'Đã được mượn';
        statusBadge.className = 'book-status status-borrowed';
        
        // Vô hiệu hóa nút mượn
        borrowBtn.disabled = true;
        borrowBtn.textContent = 'Tạm hết sách';
        borrowBtn.style.opacity = '0.5';
        borrowBtn.style.cursor = 'not-allowed';
    }
}

// 4. Hàm xử lý mượn sách (Dành riêng cho trang chi tiết)
async function handleBorrowDetail(bookId) {
    const token = localStorage.getItem('b4e_token');

    if (!token) {
        alert('Bạn cần đăng nhập để mượn sách.');
        window.location.href = 'login.html';
        return;
    }

    if (!confirm('Bạn có chắc chắn muốn mượn cuốn sách này?')) return;

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
            alert('Mượn sách thành công!');
            location.reload(); // Tải lại trang để cập nhật trạng thái nút bấm
        } else {
            alert('Lỗi: ' + result.error);
        }
    } catch (error) {
        console.error(error);
        alert('Không thể kết nối đến máy chủ.');
    }
}