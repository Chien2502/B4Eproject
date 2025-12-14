document.addEventListener('DOMContentLoaded', () => {
   Auth.requireLogin();
    fetchBorrowings();
});

// --- 1. TẢI DANH SÁCH MƯỢN TRẢ ---
async function fetchBorrowings() {
    try {
        const token = localStorage.getItem('b4e_token');
        
        // 2. Gọi API với Header Authorization
        const response = await fetch(`${CONFIG.API_BASE_URL}/api/admin/borrowings.php`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });

        // 3. Xử lý lỗi 401 (Token hết hạn hoặc không hợp lệ)
        if (response.status === 401) {
            alert("Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.");
            localStorage.clear(); // Xóa rác
            window.location.href = '../index.html';
            return;
        }
        const result = await response.json();
        
        // Xử lý dữ liệu trả về
        const borrowings = result.data ? result.data : result;

        const tbody = document.getElementById('borrowingsTableBody');
        tbody.innerHTML = '';

        if (!borrowings || borrowings.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Không có dữ liệu.</td></tr>';
            return;
        }

        borrowings.forEach(item => {
            const tr = document.createElement('tr');
            
            // 1. Xử lý ảnh bìa
            const imgUrl = item.image_url 
                ? `${CONFIG.IMG_BASE_URL}/${item.image_url}`.replace('//img', '/img')
                : '../../img/default-book.png';

            // 2. Xử lý Ngày tháng (Format DD/MM/YYYY)
            const borrowDate = formatDate(item.borrow_date);
            const dueDate = formatDate(item.due_date);
            const returnDate = item.return_date ? formatDate(item.return_date) : '';

            // 3. Xử lý Badge Trạng thái (Chuyển logic switch case PHP sang JS)
            const statusHtml = getStatusBadge(item.status, item.return_date, item.due_date);

            // 4. Xử lý Nút Hành động
            let actionHtml = '';
            // Nếu trạng thái là: Đang mượn, Đang trả, hoặc Quá hạn -> Hiện nút Xác nhận
            if (['borrowed', 'returning', 'overdue'].includes(item.status)) {
                actionHtml = `
                    <button class="btn btn-green" onclick="confirmReturn(${item.id})" style="padding: 6px 12px; font-size: 0.9rem;">
                        <i class="fas fa-check-circle"></i> Xác nhận đã nhận
                    </button>
                `;
            } else {
                actionHtml = `<span style="color:#aaa;"><i class="fas fa-check"></i> Đã xử lý</span>`;
            }

            // 5. Render HTML
            tr.innerHTML = `
                <td>
                    <b>#${item.user_id}</b><br>
                    <b>${item.username}</b><br>
                    <small style="color:#666;">${item.phone || 'Chưa có SĐT'}</small>
                </td>
                <td>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <img src="${imgUrl}" style="width:30px; height:45px; object-fit:cover; border-radius:3px;" onerror="this.src='../img/default-book.png'">
                        <span>${item.book_title}</span>
                    </div>
                </td>
                <td>
                    Mượn: ${borrowDate}<br>
                    <span style="color: #d9534f;">Hạn: ${dueDate}</span>
                    ${returnDate ? `<br>Trả: ${returnDate}` : ''}
                </td>
                <td>${statusHtml}</td>
                <td>${actionHtml}</td>
            `;
            tbody.appendChild(tr);
        });

    } catch (error) {
        console.error('Lỗi tải dữ liệu:', error);
        document.getElementById('borrowingsTableBody').innerHTML = 
            '<tr><td colspan="5" style="text-align:center; color:red;">Lỗi kết nối server.</td></tr>';
    }
}

// --- 2. HÀM XỬ LÝ TRẢ SÁCH ---

async function confirmReturn(borrowId) {
    if (!confirm('Xác nhận sách đã trả?')) return;

    try {
        const response = await Auth.fetch(`${CONFIG.API_BASE_URL}/api/admin/confirm_return.php`, {
            method: 'POST',
            body: JSON.stringify({ borrow_id: borrowId }) 
        });

        if (!response.ok) {
            const text = await response.text();
            console.error("Server Error:", text);
            throw new Error(`Lỗi Server: ${response.status}`);
        }

        const result = await response.json();

        if (response.ok) {
            alert(result.message || 'Thành công!');
            fetchBorrowings();
        } else {
            alert('Lỗi logic: ' + (result.error || 'Không thể xử lý'));
        }
    } catch (error) {
        console.error("Chi tiết lỗi:", error);
        alert('Lỗi kết nối: ' + error.message);
    }
}

// --- HELPER FUNCTIONS ---

// Hàm định dạng ngày
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

// Hàm tạo Badge trạng thái
function getStatusBadge(status, returnDate, dueDate) {
    const badgeStyle = 'display: inline-block; padding: 5px 10px; border-radius: 15px; font-size: 0.85rem; font-weight: bold; color: white; margin-bottom: 4px;';
    
    switch(status) {
        case 'returning':
            return `<span class="badge" style="${badgeStyle} background-color: #3498db;">User báo đã trả</span>`;
        case 'borrowed':
            return `<span class="badge" style="${badgeStyle} background-color: #f39c12;">Đang mượn</span>`;
        case 'overdue':
            return `<span class="badge" style="${badgeStyle} background-color: #c0392b;">Quá hạn</span>`;
        case 'returned':
            let subBadge = '';
            // Logic so sánh ngày trả thực tế với ngày hẹn
            if (returnDate && dueDate) {
                const ret = new Date(returnDate);
                const due = new Date(dueDate);
                if (ret <= due) {
                    subBadge = `<br><span class="badge" style="${badgeStyle} background-color: #2ecc71; font-size: 0.75rem; margin-top: 2px;">Đúng hạn</span>`;
                } else {
                    subBadge = `<br><span class="badge" style="${badgeStyle} background-color: #e74c3c; font-size: 0.75rem; margin-top: 2px;">Trả muộn</span>`;
                }
            }
            return `<span class="badge" style="${badgeStyle} background-color: #27ae60;">Đã trả sách</span>${subBadge}`;
        default:
            return `<span class="badge">${status}</span>`;
    }
}