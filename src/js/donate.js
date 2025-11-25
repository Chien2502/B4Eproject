document.addEventListener('DOMContentLoaded', () => {
    const donationForm = document.getElementById('donationForm');

    const token = localStorage.getItem('b4e_token');

    if (donationForm) {
        donationForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            if (!token) {
                alert('Bạn cần đăng nhập để thực hiện quyên góp.');
                window.location.href = 'login.html';
                return;
            }

            const formData = new FormData(this);
            

            const donationData = {
                book_title: formData.get('bookTitle'),      
                book_author: formData.get('author'),       
                book_publisher: formData.get('publisher'),
                book_year: formData.get('publicationYear'),         
                book_condition: formData.get('bookCondition'),  
                donation_type: formData.get('donationType') 
            };

            // Validation đơn giản phía Client
            if (!donationData.book_title || !donationData.book_author) {
                alert('Vui lòng điền Tên sách và Tác giả.');
                return;
            }

            // 3. Gọi API
            try {
                const response = await fetch('/api/donations/create.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + token
                    },
                    body: JSON.stringify(donationData)
                });

                const result = await response.json();

                if (response.ok) {
                    alert(result.message); // "Cảm ơn bạn!..."
                    this.reset(); // Xóa trắng form
                } else {
                    // Xử lý lỗi (ví dụ 401 Unauthorized)
                    if (response.status === 401) {
                        alert('Phiên đăng nhập hết hạn. Vui lòng đăng nhập lại.');
                        window.location.href = 'login.html';
                    } else {
                        alert('Lỗi: ' + result.error);
                    }
                }
            } catch (error) {
                console.error('Lỗi:', error);
                alert('Không thể kết nối đến máy chủ.');
            }
        });
    }
    else {
        console.error("LỖI: Không tìm thấy form có id='donationForm'");
    }
});