//Hàm chuyển tab, lưu tab đang được chọn vào localstorage để không bị reload lại trang mặc định khi thực hiện hành động
        function openTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            
            const selectedTab = document.getElementById(tabName);
            if(selectedTab) {
                selectedTab.classList.add('active');
            }
            
            const btns = document.querySelectorAll('.tab-btn');
            btns.forEach(btn => {
                if(btn.getAttribute('onclick').includes(tabName)) {
                    btn.classList.add('active');
                }
            });

            localStorage.setItem('active_tab', tabName);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const token = localStorage.getItem('b4e_token');
            if(!token) { window.location.href = 'login.html'; return; }

          
            const savedTab = localStorage.getItem('active_tab');
            if (savedTab) {
                openTab(savedTab);
            } else {
                openTab('profile');
            }
        
            loadProfile(); 
            loadBorrowHistory();
            loadDonationHistory();

            async function loadProfile() {
                const res = await fetch('/api/auth/get_profile.php', { headers: { 'Authorization': 'Bearer ' + token } });
                const user = await res.json();
                document.getElementById('display-name').textContent = user.username;
                document.getElementById('email').value = user.email;
                document.getElementById('username').value = user.username;
                document.getElementById('phone').value = user.phone || '';
                document.getElementById('address').value = user.address || '';
            }
        
            document.getElementById('profileForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const data = {
                    username: document.getElementById('username').value,
                    phone: document.getElementById('phone').value,
                    address: document.getElementById('address').value
                };
                await fetch('/api/auth/update_profile.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token},
                    body: JSON.stringify(data)
                });
                alert('Đã cập nhật thông tin!');
                location.reload();
            });
    
            async function loadBorrowHistory() {
                const res = await fetch('/api/users/borrowings.php', { headers: { 'Authorization': 'Bearer ' + token } });
                const list = await res.json();
                const tbody = document.getElementById('borrow-list');
                tbody.innerHTML = '';

                list.forEach(item => {
                    let statusBadge = '';
                    let actionBtn = '';

                    if (item.status === 'borrowed') {
                        // TH1: Đang mượn -> Hiện nút Trả
                        statusBadge = '<span class="badge bg-yellow">Đang đọc</span>';
                        actionBtn = `<button class="btn-submit" 
                                     style="background:#e67e22; padding:5px 10px; font-size:0.8rem;" 
                                     onclick="returnBook(${item.id})">
                                     Gửi trả sách
                                     </button>`;
                    } 
                    else if (item.status === 'returning') {
                        // TH2: Đang trả (Mới thêm) -> Hiện badge chờ
                        statusBadge = '<span class="badge" style="background:#3498db; color:white;">Chờ xác nhận trả</span>';
                        actionBtn = '<small style="color:#666;">Đang xử lý...</small>';
                    }
                    else if (item.status === 'returned') {
                        // TH3: Đã trả xong -> Badge xanh
                        statusBadge = '<span class="badge bg-green">Đã hoàn tất</span>';
                        actionBtn = '<i class="fas fa-check" style="color:green;"></i>';
                    }
                    else if (item.status === 'overdue') {
                        statusBadge = '<span class="badge bg-red">Quá hạn</span>';
                        actionBtn = 'Vui lòng liên hệ Admin';
                    }

                    const row = `<tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <img src="${item.image_url}" style="width:30px; height:45px; object-fit:cover; border-radius:3px;">
                                <div>
                                    <b>${item.title}</b><br>
                                    <small>${item.author}</small>
                                </div>
                            </div>
                        </td>
                        <td>${item.borrow_date}</td>
                        <td>${item.due_date}</td>
                        <td>${statusBadge}</td>
                        <td style="text-align:center;">${actionBtn}</td>
                    </tr>`;
                    tbody.innerHTML += row;
                });
            }

            async function loadDonationHistory() {
                const res = await fetch('/api/users/donations.php', { headers: { 'Authorization': 'Bearer ' + token } });
                const list = await res.json();
                const tbody = document.getElementById('donation-list');
                tbody.innerHTML = '';

                list.forEach(item => {
                    let statusBadge = '';
                    if (item.status === 'pending') statusBadge = '<span class="badge bg-yellow">Chờ duyệt</span>';
                    else if (item.status === 'approved') statusBadge = '<span class="badge bg-green">Đã tiếp nhận</span>';
                    else statusBadge = '<span class="badge bg-red">Từ chối</span>';

                    const row = `<tr>
                        <td><b>${item.book_title}</b><br><small>${item.book_author}</small></td>
                        <td>${item.donation_type}</td>
                        <td>${item.created_at}</td>
                        <td>${statusBadge}</td>
                    </tr>`;
                    tbody.innerHTML += row;
                });
            }
        });

        // Hàm Trả Sách (Global)
        async function returnBook(borrowId) {
            if(!confirm('Bạn xác nhận đã gửi trả cuốn sách này cho thư viện?')) return;
            const token = localStorage.getItem('b4e_token');
            
            try {
                const res = await fetch('/api/users/return.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token},
                    body: JSON.stringify({ borrow_id: borrowId })
                });
                
                const result = await res.json();

                if(res.ok) {
                    alert(result.message); // Thông báo: Đã gửi yêu cầu...
                    location.reload();
                } else {
                    alert(result.error);
                }
            } catch (error) {
                alert('Lỗi kết nối.');
            }
        }