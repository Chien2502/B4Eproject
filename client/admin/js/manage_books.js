document.addEventListener('DOMContentLoaded', () => {
    Auth.requireLogin();
    fetchBooks();
});

// --- 1. HÀM TẢI DANH SÁCH SÁCH ---
async function fetchBooks() {
    try {
        // Gọi API lấy danh sách sách (API này trả về JSON mảng các sách)
        const response = await fetch(`${CONFIG.API_BASE_URL}/api/books/read.php`);
        const result = await response.json();

        const books = result.data ? result.data : result;
        
        const tbody = document.getElementById('booksTableBody');
        tbody.innerHTML = ''; 

        if (books.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Chưa có sách nào.</td></tr>';
            return;
        }

        // Duyệt qua từng sách và tạo dòng HTML
        books.forEach(book => {
            const tr = document.createElement('tr');
            tr.id = `row-${book.id}`; // ID để dùng cho chức năng xóa

            const imgUrl = book.image_url 
            ? `${CONFIG.API_BASE_URL}/api/uploads/${book.image_url}`
            : `/client/img/default-book.png`;

            // Xử lý trạng thái (Badge màu)
            let statusBadge = '';
            if (book.status === 'available') {
                statusBadge = '<span class="badge bg-green">Có sẵn</span>';
            } else if (book.status === 'borrowed') {
                statusBadge = '<span class="badge bg-yellow">Đang mượn</span>';
            } else {
                statusBadge = `<span class="badge bg-gray">${book.status}</span>`;
            }

            tr.innerHTML = `
                <td>#${book.id}</td>
                <td>
                    <img src="${imgUrl}" style="width:40px; height:60px; object-fit:cover; border-radius:3px;" onerror="this.src='../img/default-book.png'">
                </td>
                <td>
                    <b>${book.title}</b><br>
                    <small style="color:#666;">${book.author}</small>
                </td>
                <td>${book.category_name || 'Chưa phân loại'}</td>
                <td>${statusBadge}</td>
                <td>
                    <a href="book_form.html?id=${book.id}" class="btn btn-blue" title="Sửa" style="padding: 5px 10px;">
                        <i class="fas fa-edit"></i>
                    </a>
                    &nbsp;
                    <button class="btn btn-red" onclick="deleteBook(${book.id})" title="Xóa" style="padding: 5px 10px;">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });

    } catch (error) {
        console.error('Lỗi tải sách:', error);
        document.getElementById('booksTableBody').innerHTML = 
            '<tr><td colspan="6" style="text-align:center; color:red;">Lỗi kết nối server.</td></tr>';
    }
}
// --- 2. HÀM XÓA SÁCH ---
async function deleteBook(id) {
    if (!confirm('Bạn có chắc chắn muốn xóa cuốn sách này? Hành động này không thể hoàn tác.')) return;

    try {
        const response = await Auth.fetch(`${CONFIG.API_BASE_URL}/api/books/delete.php`, {
            method: 'POST',
            body: JSON.stringify({ id: id }) 
        });
        
        const result = await response.json();
        
        if (response.ok) {
            alert(result.message || 'Đã xóa thành công!');
            
            // 2. Xóa dòng khỏi bảng ngay lập tức (Hiệu ứng mờ dần cho mượt)
            const row = document.getElementById(`row-${id}`);
            if (row) {
                row.style.transition = 'opacity 0.5s';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 500);
            }
        } else {
            alert('Lỗi: ' + (result.error || 'Không thể xóa'));
        }
    } catch (error) {
        console.error(error);
        alert('Lỗi kết nối hoặc lỗi server.');
    }
}