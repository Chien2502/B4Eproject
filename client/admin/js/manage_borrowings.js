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
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Không có dữ liệu.</td></tr>';
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

            // 3. Xử lý Badge Trạng thái
            const statusHtml = getStatusBadge(item.status, item.return_date, item.due_date);

            // 3b. Xử lý Vận chuyển & Thanh toán
            let deliveryHtml = '';
            if (item.delivery_type === 'pickup') {
                deliveryHtml = `<span style="color: #27ae60; font-weight: bold;"><i class="fas fa-hand-holding"></i> Nhận tại TV</span>`;
            } else if (item.delivery_type === 'delivery') {
                deliveryHtml = `
                    <span style="color: #2980b9; font-weight: bold;"><i class="fas fa-shipping-fast"></i> Giao tận nơi</span>
                    ${item.shipping_fee > 0 ? `<br><small style="color: #555;">Ship: ${formatVND(item.shipping_fee)}</small>` : ''}
                    ${item.delivery_address ? `<br><small style="color: #7f8c8d; font-size: 0.75rem; display: inline-block; max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${item.delivery_address}">Đ/C: ${item.delivery_address}</small>` : ''}
                `;
            } else {
                deliveryHtml = `<span style="color: #999;">-</span>`;
            }

            let paymentHtml = '';
            if (item.delivery_type === 'delivery') {
                const methodText = item.payment_method === 'vietqr' ? 'VietQR' : (item.payment_method === 'cod' ? 'COD' : 'Khác');
                let statusBadge = '';
                if (item.payment_status === 'pending') {
                    statusBadge = `<span class="badge" style="background-color: #f1c40f; color: #333; font-size: 0.75rem; padding: 2px 6px; border-radius: 4px; font-weight: bold;">Chờ TT</span>`;
                } else if (item.payment_status === 'paid') {
                    statusBadge = `<span class="badge" style="background-color: #2ecc71; color: white; font-size: 0.75rem; padding: 2px 6px; border-radius: 4px; font-weight: bold;">Đã TT</span>`;
                } else if (item.payment_status === 'failed') {
                    statusBadge = `<span class="badge" style="background-color: #e74c3c; color: white; font-size: 0.75rem; padding: 2px 6px; border-radius: 4px; font-weight: bold;">TT Lỗi</span>`;
                } else if (item.payment_status === 'refunded') {
                    statusBadge = `<span class="badge" style="background-color: #7f8c8d; color: white; font-size: 0.75rem; padding: 2px 6px; border-radius: 4px; font-weight: bold;">Đã hoàn</span>`;
                } else {
                    statusBadge = `<span class="badge" style="background-color: #bdc3c7; color: #333; font-size: 0.75rem; padding: 2px 6px; border-radius: 4px; font-weight: bold;">Không cần</span>`;
                }
                paymentHtml = `<div style="margin-top: 5px; font-size: 0.8rem;">TT: ${methodText} - ${statusBadge}</div>`;
            }

            let returnMethodHtml = '';
            if (item.return_method) {
                const methodText = item.return_method === 'self_deliver' ? 'Tự mang trả' : (item.return_method === 'pickup_shipper' ? 'Shipper thu hồi' : 'Khác');
                returnMethodHtml = `<div style="margin-top: 3px; font-size: 0.8rem; color: #e67e22;"><i class="fas fa-undo"></i> Trả: ${methodText}</div>`;
            }

            const deliveryAndPaymentHtml = `
                <div>
                    ${deliveryHtml}
                    ${paymentHtml}
                    ${returnMethodHtml}
                </div>
            `;

            // 4. Xử lý Nút Hành động
            let actionHtml = '';
            const btnStyle = 'padding: 6px 12px; font-size: 0.8rem; margin: 2px; display: inline-flex; align-items: center; gap: 4px; border: none; border-radius: 4px; color: white; cursor: pointer; font-weight: bold;';
            
            if (item.status === 'pending_approval') {
                actionHtml = `
                    <button class="btn btn-green" onclick="updateStatus(${item.id}, 'approved')" style="${btnStyle}">
                        <i class="fas fa-check"></i> Duyệt mượn
                    </button>
                    <button class="btn btn-red" onclick="updateStatus(${item.id}, 'cancelled')" style="${btnStyle}">
                        <i class="fas fa-times"></i> Từ chối
                    </button>
                `;
            } else if (item.status === 'approved') {
                if (item.delivery_type === 'delivery') {
                    actionHtml = `
                        <button class="btn btn-blue" onclick="updateStatus(${item.id}, 'preparing')" style="${btnStyle}">
                            <i class="fas fa-box"></i> Chuẩn bị sách
                        </button>
                        <button class="btn btn-red" onclick="updateStatus(${item.id}, 'cancelled')" style="${btnStyle}">
                            <i class="fas fa-times"></i> Hủy
                        </button>
                    `;
                } else {
                    // pickup
                    actionHtml = `
                        <button class="btn btn-green" onclick="updateStatus(${item.id}, 'borrowed')" style="${btnStyle}">
                            <i class="fas fa-hand-holding"></i> Xác nhận đã lấy
                        </button>
                        <button class="btn btn-red" onclick="updateStatus(${item.id}, 'cancelled')" style="${btnStyle}">
                            <i class="fas fa-times"></i> Hủy
                        </button>
                    `;
                }
            } else if (item.status === 'preparing') {
                actionHtml = `
                    <button class="btn btn-blue" onclick="updateStatus(${item.id}, 'shipped')" style="${btnStyle}">
                        <i class="fas fa-truck"></i> Bắt đầu giao
                    </button>
                `;
            } else if (item.status === 'shipped') {
                actionHtml = `
                    <button class="btn btn-green" onclick="updateStatus(${item.id}, 'borrowed')" style="${btnStyle}">
                        <i class="fas fa-check-circle"></i> Xác nhận đã giao
                    </button>
                `;
            } else if (['return_requested', 'return_approved', 'return_shipping'].includes(item.status)) {
                actionHtml = `
                    <button class="btn btn-green" onclick="updateStatus(${item.id}, 'returned')" style="${btnStyle}">
                        <i class="fas fa-check-circle"></i> Xác nhận đã nhận trả
                    </button>
                `;
            } else if (['borrowed', 'overdue'].includes(item.status)) {
                actionHtml = `<span style="color:#aaa; font-size: 0.85rem;"><i class="fas fa-hourglass-half"></i> Chờ user trả</span>`;
            } else {
                actionHtml = `<span style="color:#aaa; font-size: 0.85rem;"><i class="fas fa-check"></i> Đã xử lý</span>`;
            }

            // Gia hạn sách
            if (item.renew_status === 'pending') {
                actionHtml += `
                    <div style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed #ddd; text-align: left;">
                        <span style="font-size: 0.75rem; color: #e67e22; font-weight: bold;"><i class="fas fa-history"></i> Xin gia hạn ${item.renew_days} ngày</span><br>
                        <button class="btn btn-blue" onclick="handleRenewal(${item.id}, 'approve')" style="padding: 4px 8px; font-size: 0.75rem; margin-top:3px; border:none; border-radius:3px; color:white; cursor:pointer; font-weight:bold;">
                            Duyệt
                        </button>
                        <button class="btn btn-red" onclick="handleRenewal(${item.id}, 'reject')" style="padding: 4px 8px; font-size: 0.75rem; margin-top:3px; border:none; border-radius:3px; color:white; cursor:pointer; font-weight:bold;">
                            Từ chối
                        </button>
                    </div>
                `;
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
                <td>${deliveryAndPaymentHtml}</td>
                <td>${statusHtml}</td>
                <td>${actionHtml}</td>
            `;
            tbody.appendChild(tr);
        });

    } catch (error) {
        console.error('Lỗi tải dữ liệu:', error);
        document.getElementById('borrowingsTableBody').innerHTML = 
            '<tr><td colspan="6" style="text-align:center; color:red;">Lỗi kết nối server.</td></tr>';
    }
}

// --- 2. HÀM CẬP NHẬT TRẠNG THÁI ---
async function updateStatus(borrowingId, status) {
    let confirmMsg = `Xác nhận chuyển trạng thái phiếu mượn sang "${getStatusText(status)}"?`;
    if (status === 'cancelled') {
        confirmMsg = `Hủy yêu cầu mượn sách này?`;
    }
    if (!confirm(confirmMsg)) return;

    try {
        const response = await Auth.fetch(`${CONFIG.API_BASE_URL}/api/borrowings/update_status.php`, {
            method: 'POST',
            body: JSON.stringify({ borrowing_id: borrowingId, status: status })
        });

        if (!response.ok) {
            const text = await response.text();
            console.error("Server Error:", text);
            throw new Error(`Lỗi Server: ${response.status}`);
        }

        const result = await response.json();

        if (response.ok) {
            alert(result.message || 'Cập nhật trạng thái thành công!');
            fetchBorrowings();
        } else {
            alert('Lỗi logic: ' + (result.error || 'Không thể xử lý'));
        }
    } catch (error) {
        console.error("Chi tiết lỗi:", error);
        alert('Lỗi kết nối: ' + error.message);
    }
}

// Giữ lại hàm cũ để tránh lỗi gọi nếu có chỗ khác gọi
async function confirmReturn(borrowId) {
    return updateStatus(borrowId, 'returned');
}

// --- 3. HÀM XỬ LÝ GIA HẠN ---
async function handleRenewal(borrowId, action) {
    const msg = action === 'approve' ? 'Duyệt yêu cầu gia hạn này?' : 'Từ chối yêu cầu gia hạn này?';
    if (!confirm(msg)) return;

    try {
        const response = await Auth.fetch(`${CONFIG.API_BASE_URL}/api/admin/handle_renewal.php`, {
            method: 'POST',
            body: JSON.stringify({ borrow_id: borrowId, action: action })
        });
        
        const result = await response.json();
        if (response.ok) {
            alert(result.message || 'Thành công!');
            fetchBorrowings(); // Reload
        } else {
            alert('Lỗi: ' + (result.error || 'Không thể xử lý'));
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

// Định dạng tiền tệ VND
function formatVND(value) {
    if (!value) return '0đ';
    return Number(value).toLocaleString('vi-VN') + 'đ';
}

function getStatusText(status) {
    switch (status) {
        case 'pending_approval': return 'Chờ duyệt';
        case 'approved': return 'Đã duyệt';
        case 'preparing': return 'Đang chuẩn bị';
        case 'shipped': return 'Đang giao';
        case 'borrowed': return 'Đang mượn';
        case 'return_requested': return 'Yêu cầu trả';
        case 'return_approved': return 'Đã duyệt trả';
        case 'return_shipping': return 'Đang giao trả';
        case 'returned': return 'Đã trả sách';
        case 'overdue': return 'Quá hạn';
        case 'cancelled': return 'Đã hủy';
        default: return status;
    }
}

// Hàm tạo Badge trạng thái
function getStatusBadge(status, returnDate, dueDate) {
    const badgeStyle = 'display: inline-block; padding: 5px 10px; border-radius: 15px; font-size: 0.85rem; font-weight: bold; color: white; margin-bottom: 4px; text-align: center;';
    
    switch(status) {
        case 'pending_approval':
            return `<span class="badge" style="${badgeStyle} background-color: #7f8c8d;">Chờ duyệt mượn</span>`;
        case 'approved':
            return `<span class="badge" style="${badgeStyle} background-color: #3498db;">Đã duyệt mượn</span>`;
        case 'preparing':
            return `<span class="badge" style="${badgeStyle} background-color: #e67e22;">Đang chuẩn bị</span>`;
        case 'shipped':
            return `<span class="badge" style="${badgeStyle} background-color: #9b59b6;">Đang giao sách</span>`;
        case 'borrowed':
            return `<span class="badge" style="${badgeStyle} background-color: #f39c12;">Đang mượn</span>`;
        case 'return_requested':
            return `<span class="badge" style="${badgeStyle} background-color: #34495e;">Yêu cầu trả</span>`;
        case 'return_approved':
            return `<span class="badge" style="${badgeStyle} background-color: #16a085;">Duyệt trả</span>`;
        case 'return_shipping':
            return `<span class="badge" style="${badgeStyle} background-color: #2980b9;">Đang giao trả</span>`;
        case 'overdue':
            return `<span class="badge" style="${badgeStyle} background-color: #c0392b;">Quá hạn</span>`;
        case 'cancelled':
            return `<span class="badge" style="${badgeStyle} background-color: #d63031;">Đã hủy</span>`;
        case 'returned':
            let subBadge = '';
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
            return `<span class="badge" style="${badgeStyle} background-color: #95a5a6;">${status}</span>`;
    }
}