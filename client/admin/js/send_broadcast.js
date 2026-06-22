document.addEventListener('DOMContentLoaded', () => {
    Auth.requireLogin();
});

async function handleSendBroadcast(event) {
    event.preventDefault();

    const titleEl = document.getElementById('title');
    const messageEl = document.getElementById('message');
    const btnSend = document.getElementById('btn-send');
    const alertMessage = document.getElementById('alert-message');

    if (!titleEl.value.trim() || !messageEl.value.trim()) {
        alert('Vui lòng nhập đầy đủ tiêu đề và nội dung thông báo!');
        return;
    }

    if (!confirm('Xác nhận gửi thông báo này tới TOÀN BỘ người dùng hệ thống?')) {
        return;
    }

    // Vô hiệu hóa nút để tránh gửi trùng lặp
    btnSend.disabled = true;
    btnSend.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';
    alertMessage.innerHTML = '';

    try {
        const response = await Auth.fetch(`${CONFIG.API_BASE_URL}/api/admin/send_broadcast.php`, {
            method: 'POST',
            body: JSON.stringify({
                title: titleEl.value.trim(),
                message: messageEl.value.trim(),
                ref_id: null
            })
        });

        const result = await response.json();

        if (response.ok && (result.status === 'success' || !result.error)) {
            alertMessage.innerHTML = `
                <div style="background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; font-weight: bold;">
                    <i class="fas fa-check-circle"></i> ${result.message || 'Đã gửi thông báo hệ thống thành công.'}
                </div>
            `;
            // Reset form
            titleEl.value = '';
            messageEl.value = '';
        } else {
            alertMessage.innerHTML = `
                <div style="background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-triangle"></i> Lỗi: ${result.error || result.message || 'Không thể gửi thông báo.'}
                </div>
            `;
        }
    } catch (error) {
        console.error("Lỗi gửi broadcast:", error);
        alertMessage.innerHTML = `
            <div style="background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> Lỗi kết nối: ${error.message}
            </div>
        `;
    } finally {
        btnSend.disabled = false;
        btnSend.innerHTML = '<i class="fas fa-paper-plane"></i> Gửi thông báo ngay';
    }
}
