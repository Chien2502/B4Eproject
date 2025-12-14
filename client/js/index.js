document.addEventListener('DOMContentLoaded', () => {
        
        // 1. TẢI THỐNG KÊ
        fetch(`${CONFIG.API_BASE_URL}/api/public/stats.php`)
            .then(res => res.json())
            .then(data => {
                // Hàm định dạng số
                const fmt = (num) => new Intl.NumberFormat().format(num);

                document.getElementById('stat-books').innerText = fmt(data.books) + '+';
                document.getElementById('stat-users').innerText = fmt(data.users) + '+';
                document.getElementById('stat-donors').innerText = fmt(data.donors) + '+';
                document.getElementById('stat-borrows').innerText = fmt(data.borrows) + '+';
            })
            .catch(err => console.error('Lỗi tải thống kê:', err));

        // 2. TẢI SÁCH MỚI NHẤT
        fetch(`${CONFIG.API_BASE_URL}/api/books/read.php?sort=newest`)
            .then(res => res.json())
            .then(response => { 
                const container = document.getElementById('new-books-list');
                container.innerHTML = ''; 
                const bookList = response.data || []; 
                const newBooks = bookList.slice(0, 4); 

                if (newBooks.length === 0) {
                    container.innerHTML = '<p>Chưa có sách nào.</p>';
                    return;
                }

                newBooks.forEach(book => {
                    const div = document.createElement('div');
                    div.className = 'book-item';
                    
                    div.onclick = () => window.location.href = `books.html?id=${book.id}`;
                    div.style.cursor = 'pointer';

                    div.innerHTML = `
                        <img src="${CONFIG.IMG_BASE_URL}/${book.image_url}" alt="${book.title}" onerror="this.src='img/default-book.png'">
                        <h4>${book.title}</h4>
                        <p>${book.author}</p>
                    `;
                    container.appendChild(div);
                });
            })
            .catch(err => console.error('Lỗi tải sách mới:', err));
        });