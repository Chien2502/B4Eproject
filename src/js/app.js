// Chờ cho toàn bộ cấu trúc HTML của trang được tải xong rồi mới chạy mã
document.addEventListener('DOMContentLoaded', () => {

  // Tìm phần tử container sẽ chứa danh sách sách
  const bookGrid = document.querySelector('.books-grid');

  // =================================================================
  // HÀM HIỂN THỊ DANH SÁCH SÁCH
  // Nhiệm vụ: Nhận vào một danh sách sách và hiển thị chúng lên giao diện
  // =================================================================
  function displayBooks(bookList) {
    // Nếu không tìm thấy bookGrid thì không làm gì cả (để tránh lỗi ở các trang khác)
    if (!bookGrid) {
      return;
    }

    // Xóa sạch mọi thứ đang có trong lưới sách trước khi hiển thị kết quả mới
    bookGrid.innerHTML = '';

    // Lặp qua mỗi cuốn sách trong danh sách được cung cấp
    bookList.forEach(book => {
      // Tạo một thẻ <div> mới cho mỗi cuốn sách
      const bookCard = document.createElement('div');
      // Thêm class 'book-card' để áp dụng CSS đã có
      bookCard.classList.add('book-card');

      // Dùng template literal (dấu `) để tạo cấu trúc HTML cho thẻ sách
      // Dữ liệu được lấy từ đối tượng 'book' (ví dụ: book.image, book.title)
      bookCard.innerHTML = `
                <div class="book-cover">
                    <img src="${book.image}" alt="${book.title}">
                </div>
                <div class="book-details">
                    <h3 class="book-title">${book.title}</h3>
                    <p class="book-author">${book.author}</p>
                    <div class="book-actions">
                         <a href="demopage.html?id=${book.id}" class="btn btn-primary">Xem chi tiết</a>
                    </div>
                </div>
            `;

      // Gắn thẻ sách vừa tạo vào trong lưới sách
      bookGrid.appendChild(bookCard);
    });
  }

  // =================================================================
  // LẦN CHẠY ĐẦU TIÊN
  // Hiển thị toàn bộ sách khi người dùng vừa tải trang
  // =================================================================
  // Biến 'books' được lấy từ tệp 'database.js' mà chúng ta đã nhúng vào HTML
  displayBooks(books);

});

document.addEventListener('DOMContentLoaded', () => {

  // =================================================================
  // PHẦN KHAI BÁO BIẾN VÀ LẤY CÁC PHẦN TỬ HTML
  // =================================================================
  const bookGrid = document.querySelector('.books-grid');
  const searchInput = document.querySelector('.search-box input');
  const categoryLinks = document.querySelectorAll('.filter-group a[data-category]');
  const sortSelect = document.querySelector('.sort-options select');

  let currentSearchTerm = '';
  let currentCategory = 'Tất cả';
  let currentSortOrder = 'newest';

  // =================================================================
  // HÀM HIỂN THỊ DANH SÁCH SÁCH (Giữ nguyên từ bước trước)
  // =================================================================
  function displayBooks(bookList) {
    if (!bookGrid) return;
    bookGrid.innerHTML = '';
    bookList.forEach(book => {
      const bookCard = document.createElement('div');
      bookCard.classList.add('book-card');
      bookCard.innerHTML = `
                <div class="book-cover">
                    <img src="${book.image}" alt="${book.title}">
                </div>
                <div class="book-details">
                    <h3 class="book-title">${book.title}</h3>
                    <p class="book-author">${book.author}</p>
                    <div class="book-actions">
                         <a href="demopage.html?id=${book.id}" class="btn btn-primary">Xem chi tiết</a>
                    </div>
                </div>
            `;
      bookGrid.appendChild(bookCard);
    });
  }

  // =================================================================
  // HÀM LỌC VÀ SẮP XẾP TỔNG HỢP
  // Đây là hàm trung tâm xử lý tất cả logic
  // =================================================================
  function applyFiltersAndSort() {
    // Bắt đầu với danh sách đầy đủ
    let filteredBooks = books;

    // 1. Áp dụng bộ lọc Thể loại
    if (currentCategory !== 'Tất cả') {
      filteredBooks = filteredBooks.filter(book => book.category === currentCategory);
    }

    // 2. Áp dụng bộ lọc Tìm kiếm
    if (currentSearchTerm) {
      filteredBooks = filteredBooks.filter(book =>
        book.title.toLowerCase().includes(currentSearchTerm) ||
        book.author.toLowerCase().includes(currentSearchTerm)
      );
    }

    // 3. Áp dụng Sắp xếp
    switch (currentSortOrder) {
      case 'popular':
        // (Tạm thời) Sắp xếp ngẫu nhiên để mô phỏng độ phổ biến
        filteredBooks.sort(() => 0.5 - Math.random());
        break;
      case 'title_asc':
        // Sắp xếp theo tên A-Z
        filteredBooks.sort((a, b) => a.title.localeCompare(b.title));
        break;
      case 'title_desc':
        // Sắp xếp theo tên Z-A
        filteredBooks.sort((a, b) => b.title.localeCompare(a.title));
        break;
      case 'newest':
      default:
        // Sắp xếp theo ID giảm dần (giả sử ID lớn hơn là sách mới hơn)
        filteredBooks.sort((a, b) => b.id - a.id);
        break;
    }

    // Cuối cùng, hiển thị kết quả đã được lọc và sắp xếp
    displayBooks(filteredBooks);
  }

  // =================================================================
  // GẮN CÁC BỘ LẮNG NGHE SỰ KIỆN (EVENT LISTENERS)
  // =================================================================

  // 1. Lắng nghe sự kiện người dùng gõ vào ô tìm kiếm
  if (searchInput) {
    searchInput.addEventListener('input', (event) => {
      currentSearchTerm = event.target.value.toLowerCase();
      applyFiltersAndSort();
    });
  }

  // 2. Lắng nghe sự kiện người dùng nhấn vào các link thể loại
  if (categoryLinks) {
    categoryLinks.forEach(link => {
      link.addEventListener('click', (event) => {
        event.preventDefault(); // Ngăn trang tải lại

        // Cập nhật trạng thái 'active' cho các link
        categoryLinks.forEach(l => l.classList.remove('active'));
        link.classList.add('active');

        currentCategory = link.dataset.category;
        applyFiltersAndSort();
      });
    });
  }

  // 3. Lắng nghe sự kiện người dùng thay đổi lựa chọn sắp xếp
  if (sortSelect) {
    sortSelect.addEventListener('change', (event) => {
      currentSortOrder = event.target.value;
      applyFiltersAndSort();
    });
  }

  // =================================================================
  // LẦN CHẠY ĐẦU TIÊN
  // =================================================================
  applyFiltersAndSort();

});
