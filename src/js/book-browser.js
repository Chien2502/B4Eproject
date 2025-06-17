// Chờ cho toàn bộ cấu trúc HTML của trang được tải xong rồi mới chạy mã
document.addEventListener('DOMContentLoaded', () => {
  if (document.querySelector('.catalog-container')) {
    // Đây là trang Danh mục sách (books.html)
    setupBookBrowser({
      gridSelector: '.books-grid',
      searchSelector: '.search-box input',
      categorySelector: '.filter-group a[data-category]',
      sortSelector: '.sort-options select',
      statusSelector: null, // Không có bộ lọc trạng thái ở trang này
      getCategoryValue: (element) => element.dataset.category,
      cardType: 'catalog'
    });
  }

  if (document.querySelector('.search-container')) {
    // Đây là trang Mượn sách (borrow.html)
    setupBookBrowser({
      gridSelector: '.books-grid',
      searchSelector: '.search-input input',
      categorySelector: '.category-filter',
      sortSelector: 'select[name="sort"]',
      statusSelector: 'select[name="status"]',
      getCategoryValue: (element) => element.textContent,
      cardType: 'borrow'
    });
  }
});



// HÀM THIẾT LẬP TRÌNH DUYỆT SÁCH
// Hàm này nhận một đối tượng cấu hình để biết cách tương tác với các phần tử trên trang
function setupBookBrowser(config) {

  // --- Lấy các phần tử HTML dựa trên cấu hình ---
  const bookGrid = document.querySelector(config.gridSelector);
  const searchInput = document.querySelector(config.searchSelector);
  const categoryFilters = document.querySelectorAll(config.categorySelector);
  const sortSelect = document.querySelector(config.sortSelector);
  const statusSelect = config.statusSelector ? document.querySelector(config.statusSelector) : null;

  // --- Biến lưu trữ trạng thái lọc hiện tại ---
  let currentSearchTerm = '';
  let currentCategory = 'Tất cả';
  let currentSortOrder = sortSelect ? sortSelect.value : 'newest';
  let currentStatus = 'all';

  // Nếu không tìm thấy lưới sách, dừng lại để không gây lỗi
  if (!bookGrid) {
    console.error("Không tìm thấy phần tử lưới sách với selector:", config.gridSelector);
    return;
  }

  // =================================================================
  // HÀM HIỂN THỊ SÁCH LÊN GIAO DIỆN
  // =================================================================
  function displayBooks(bookList) {
    bookGrid.innerHTML = ''; // Xóa sạch lưới sách trước khi hiển thị

    bookList.forEach(book => {
      const bookCard = document.createElement('div');

      // Chọn class và HTML dựa trên loại card từ config
      if (config.cardType === 'borrow') {
        bookCard.className = 'book-card';
        const isAvailable = book.status === 'available';
        bookCard.innerHTML = `
                    <div class="book-cover">
                        <img src="${book.image}" alt="${book.title}">
                        <div class="book-status ${isAvailable ? 'status-available' : 'status-borrowed'}">
                            ${isAvailable ? 'Có sẵn' : 'Đã cho mượn'}
                        </div>
                    </div>
                    <div class="book-details">
                        <h3 class="book-title">${book.title}</h3>
                        <p class="book-author">${book.author}</p>
                        <p class="book-description">${book.description}</p>
                        <div class="book-actions">
                            <a class="btn btn-primary ${!isAvailable ? 'disabled' : ''}" href="borrow-form.html?title=${encodeURIComponent(book.title)}" ${!isAvailable ? 'aria-disabled="true"' : ''}>Mượn sách</a>
                            <a class="btn1 btn-secondary" href="demopage.html?id=${book.id}">Chi tiết</a>
                        </div>
                    </div>
                `;
      } else { // Mặc định là 'catalog'
        bookCard.className = 'book-card';
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
      }
      bookGrid.appendChild(bookCard);
    });
  }

  // HÀM LỌC VÀ SẮP XẾP TỔNG HỢP
  function applyFiltersAndSort() {
    let filteredBooks = [...books]; // Tạo một bản sao của mảng sách gốc

    // 1. Lọc theo trạng thái (nếu có)
    if (currentStatus !== 'all') {
      filteredBooks = filteredBooks.filter(book => book.status === currentStatus);
    }

    // 2. Lọc theo thể loại
    if (currentCategory !== 'Tất cả') {
      filteredBooks = filteredBooks.filter(book => book.category === currentCategory);
    }

    // 3. Lọc theo từ khóa tìm kiếm
    if (currentSearchTerm) {
      filteredBooks = filteredBooks.filter(book =>
        book.title.toLowerCase().includes(currentSearchTerm) ||
        book.author.toLowerCase().includes(currentSearchTerm)
      );
    }

    // 4. Sắp xếp
    switch (currentSortOrder) {
      case 'popular':
        filteredBooks.sort(() => 0.5 - Math.random()); // Giả lập phổ biến
        break;
      case 'title_asc':
        filteredBooks.sort((a, b) => a.title.localeCompare(b.title, 'vi'));
        break;
      case 'title_desc':
        filteredBooks.sort((a, b) => b.title.localeCompare(a.title, 'vi'));
        break;
      case 'newest':
      default:
        filteredBooks.sort((a, b) => b.id - a.id); // Giả sử ID lớn hơn là mới hơn
        break;
    }

    displayBooks(filteredBooks);
  }

  // GẮN CÁC EVENT LISTENERS
  if (searchInput) {
    searchInput.addEventListener('input', (event) => {
      currentSearchTerm = event.target.value.toLowerCase().trim();
      applyFiltersAndSort();
    });
  }

  if (categoryFilters) {
    categoryFilters.forEach(filter => {
      filter.addEventListener('click', (event) => {
        event.preventDefault();
        categoryFilters.forEach(f => f.classList.remove('active'));
        filter.classList.add('active');
        currentCategory = config.getCategoryValue(filter).trim();
        applyFiltersAndSort();
      });
    })
  }

  if (sortSelect) {
    sortSelect.addEventListener('change', (event) => {
      currentSortOrder = event.target.value;
      applyFiltersAndSort();
    });
  }

  if (statusSelect) {
    statusSelect.addEventListener('change', (event) => {
      currentStatus = event.target.value;
      applyFiltersAndSort();
    });
  }

  applyFiltersAndSort();
}
