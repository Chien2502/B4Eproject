document.addEventListener('DOMContentLoaded', () => {
    Auth.requireLogin();
    fetchDonations();
});

const token = localStorage.getItem('b4e_token');

// --- 1. TẢI DANH SÁCH QUYÊN GÓP ---
async function fetchDonations() {
    try {
        // 2. Gọi API với Header Authorization
        const response = await fetch(`${CONFIG.API_BASE_URL}/api/admin/donations.php`, {
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
        
        // Xử lý mảng dữ liệu
        const donations = Array.isArray(result) ? result : (result.data || []);

        const tbody = document.getElementById('donationsTableBody');
        tbody.innerHTML = '';

        if (donations.length === 0) {
            // Hiện màn hình trống
            document.getElementById('table-container').style.display = 'none';
            document.getElementById('empty-state').style.display = 'block';
            return;
        }

        // Hiện bảng, ẩn màn hình trống
        document.getElementById('table-container').style.display = 'block';
        document.getElementById('empty-state').style.display = 'none';

        donations.forEach(item => {
            const tr = document.createElement('tr');
            tr.id = `row-${item.id}`;

            // 1. Thông tin sách & Tình trạng
            const publisherInfo = [item.book_publisher, item.book_year].filter(Boolean).join(' - ');
            const conditionHtml = `<span class="badge" style="background-color: #f1c40f; color: #333; font-size: 0.75rem; padding: 2px 6px; border-radius: 4px; font-weight: bold; display: inline-block; margin-top: 5px;">Tình trạng: ${item.book_condition || 'Tốt'}</span>`;
            
            const bookInfoHtml = `
                <span style="color:#007bff; font-weight:bold; font-size:1.1rem;">
                    ${item.book_title}
                </span><br>
                TG: ${item.book_author}<br>
                ${publisherInfo ? `<small style="color: #666;">NXB: ${publisherInfo}</small><br>` : ''}
                ${conditionHtml}
            `;

            // 2. Quyên góp & Nhận sách
            const donationTypeText = item.donation_type === 'give_away' ? 'Tặng sách' : (item.donation_type === 'exchange' ? 'Trao đổi sách' : item.donation_type);
            const pickupTypeText = item.pickup_type === 'self_deliver' ? 'Tự mang đến TV' : (item.pickup_type === 'user_ship' ? 'Gửi bưu điện/ship' : item.pickup_type);
            const addressHtml = item.pickup_address ? `<br><small style="color: #666; font-size: 0.75rem;">Đ/C nhận: ${item.pickup_address}</small>` : '';
            
            let imageHtml = '';
            if (item.image_url) {
                const fullImgUrl = `${CONFIG.IMG_BASE_URL}/${item.image_url}`.replace('//img', '/img');
                imageHtml = `
                    <div style="margin-top: 8px;">
                        <a href="${fullImgUrl}" target="_blank" title="Xem ảnh lớn">
                            <img src="${fullImgUrl}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;" onerror="this.src='../../img/default-book.png'">
                        </a>
                    </div>
                `;
            }

            const deliveryHtml = `
                <b>Loại:</b> ${donationTypeText}<br>
                <b>Nhận:</b> ${pickupTypeText}
                ${addressHtml}
                ${imageHtml}
            `;

            // 3. Trạng thái Badge
            const statusBadgeHtml = getDonationStatusBadge(item.status);

            // 4. Các nút Hành động dựa trên trạng thái
            let actionHtml = '';
            const btnStyle = 'padding: 6px 12px; font-size: 0.8rem; margin: 2px; border: none; border-radius: 4px; color: white; cursor: pointer; font-weight: bold; display: inline-flex; align-items: center; gap: 4px;';

            if (item.status === 'pending') {
                actionHtml = `
                    <button class="btn btn-green" onclick="updateDonationStatus(${item.id}, 'approved')" style="${btnStyle}">
                        <i class="fas fa-check"></i> Tiếp nhận
                    </button>
                    <button class="btn btn-red" onclick="updateDonationStatus(${item.id}, 'rejected')" style="${btnStyle}">
                        <i class="fas fa-times"></i> Từ chối
                    </button>
                `;
            } else if (item.status === 'approved') {
                actionHtml = `
                    <button class="btn btn-blue" onclick="updateDonationStatus(${item.id}, 'in_transit')" style="${btnStyle}">
                        <i class="fas fa-shipping-fast"></i> Đang vận chuyển
                    </button>
                    <button class="btn btn-green" onclick="updateDonationStatus(${item.id}, 'received')" style="${btnStyle}">
                        <i class="fas fa-box-open"></i> Đã nhận được sách
                    </button>
                `;
            } else if (item.status === 'in_transit') {
                actionHtml = `
                    <button class="btn btn-green" onclick="updateDonationStatus(${item.id}, 'received')" style="${btnStyle}">
                        <i class="fas fa-box-open"></i> Đã nhận được sách
                    </button>
                `;
            } else if (item.status === 'received') {
                actionHtml = `
                    <button class="btn btn-green" onclick="updateDonationStatus(${item.id}, 'processed')" style="${btnStyle}">
                        <i class="fas fa-check-double"></i> Hoàn thành (Nhập kho)
                    </button>
                `;
            } else if (item.status === 'processed') {
                actionHtml = `<span style="color:#aaa; font-size:0.85rem;"><i class="fas fa-check-double"></i> Đã nhập kho</span>`;
            } else if (item.status === 'rejected') {
                actionHtml = `<span style="color:#aaa; font-size:0.85rem;"><i class="fas fa-times-circle"></i> Đã từ chối</span>`;
            }

            tr.innerHTML = `
                <td>
                    <b>${item.username}</b><br>
                    <small style="color:#666;">${item.email}</small>
                </td>
                <td>${bookInfoHtml}</td>
                <td>${deliveryHtml}</td>
                <td>${statusBadgeHtml}</td>
                <td>${actionHtml}</td>
            `;
            tbody.appendChild(tr);
        });

    } catch (error) {
        console.error('Lỗi tải dữ liệu:', error);
        alert('Không thể tải danh sách quyên góp.');
    }
}

// --- 2. XỬ LÝ CẬP NHẬT TRẠNG THÁI QUYÊN GÓP ---
async function updateDonationStatus(id, status) {
    let confirmMsg = `Xác nhận chuyển trạng thái yêu cầu quyên góp sang "${getDonationStatusText(status)}"?`;
    if (status === 'processed') {
        confirmMsg = `Xác nhận sách đã nhận và THỰC HIỆN NHẬP KHO? Cuốn sách này sẽ chính thức được tạo và cho phép độc giả mượn.`;
    }
    if (status === 'rejected') {
        confirmMsg = `Bạn có chắc chắn muốn TỪ CHỐI yêu cầu quyên góp này?`;
    }
    if (!confirm(confirmMsg)) return;

    try {
        const response = await fetch(`${CONFIG.API_BASE_URL}/api/donations/update_status.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify({ donation_id: id, status: status })
        });

        if (!response.ok) {
            const text = await response.text();
            console.error("Server Error:", text);
            throw new Error(`Lỗi Server: ${response.status}`);
        }

        const result = await response.json();

        if (response.ok) {
            alert(result.message || 'Cập nhật trạng thái thành công!');
            fetchDonations(); // Tải lại danh sách
        } else {
            alert('Lỗi logic: ' + (result.error || 'Có lỗi xảy ra'));
        }
    } catch (error) {
        console.error(error);
        alert('Lỗi kết nối server: ' + error.message);
    }
}

// Giữ lại hàm cũ tương thích ngược (nếu có chỗ khác tham chiếu)
async function processDonation(id, action) {
    if (action === 'approve') {
        return updateDonationStatus(id, 'approved');
    } else {
        return updateDonationStatus(id, 'rejected');
    }
}

// --- HELPER FUNCTIONS ---

function getDonationStatusText(status) {
    switch (status) {
        case 'pending': return 'Chờ tiếp nhận';
        case 'approved': return 'Đã duyệt tiếp nhận';
        case 'in_transit': return 'Đang vận chuyển';
        case 'received': return 'Thư viện đã nhận';
        case 'processed': return 'Đã nhập kho';
        case 'rejected': return 'Đã từ chối';
        default: return status;
    }
}

function getDonationStatusBadge(status) {
    const style = 'display: inline-block; padding: 5px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: bold; color: white; text-align: center;';
    switch (status) {
        case 'pending':
            return `<span class="badge" style="${style} background-color: #f39c12;">Chờ tiếp nhận</span>`;
        case 'approved':
            return `<span class="badge" style="${style} background-color: #3498db;">Đã duyệt</span>`;
        case 'in_transit':
            return `<span class="badge" style="${style} background-color: #9b59b6;">Đang vận chuyển</span>`;
        case 'received':
            return `<span class="badge" style="${style} background-color: #1abc9c;">Đã nhận sách</span>`;
        case 'processed':
            return `<span class="badge" style="${style} background-color: #2ecc71;">Đã nhập kho</span>`;
        case 'rejected':
            return `<span class="badge" style="${style} background-color: #e74c3c;">Đã từ chối</span>`;
        default:
            return `<span class="badge" style="${style} background-color: #7f8c8d;">${status}</span>`;
    }
}