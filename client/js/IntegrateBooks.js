

  // Lọc sách theo thể loại
  document.addEventListener('DOMContentLoaded', function() {
  const categoryFilters = document.querySelectorAll('.category-filter');

  categoryFilters.forEach(filter => {
  filter.addEventListener('click', function() {
  // Xóa active class từ tất cả các filter
  categoryFilters.forEach(f => f.classList.remove('active'));

  // Thêm active class vào filter được chọn
  this.classList.add('active');

  // Lọc sách theo thể loại (chức năng này sẽ được xây dựng sau)
  // filterBooksByCategory(this.textContent);
});
});

  // Xử lý sự kiện tìm kiếm
  const searchInput = document.querySelector('.search-input input');
  searchInput.addEventListener('input', function() {
  // Chức năng tìm kiếm sẽ được xây dựng sau
  // searchBooks(this.value);
});

  // Xử lý sự kiện lọc theo trạng thái
  const statusFilter = document.querySelector('select[name="status"]');
  statusFilter.addEventListener('change', function() {
  // Chức năng lọc theo trạng thái sẽ được xây dựng sau
  // filterBooksByStatus(this.value);
});

  // Xử lý sự kiện sắp xếp
  const sortOption = document.querySelector('select[name="sort"]');
  sortOption.addEventListener('change', function() {
  // Chức năng sắp xếp sẽ được xây dựng sau
  // sortBooks(this.value);
});

  // Xử lý sự kiện khi click vào nút chi tiết
  const detailButtons = document.querySelectorAll('.btn-secondary');
  detailButtons.forEach(button => {
  button.addEventListener('click', function () {
  window.location.href = 'demopage.html';
});
});

  // Mô phỏng dữ liệu sách
  const books = [
{
  id: 1,
  title: 'Nhà Giả Kim',
  author: 'Paulo Coelho',
  category: 'Tiểu thuyết',
  description: 'Tiểu thuyết kể về hành trình của chàng chăn cừu Santiago đi tìm kho báu...',
  status: 'available',
  coverImage: '/api/placeholder/220/250'
},
{
  id: 2,
  title: 'Trăm Năm Cô Đơn',
  author: 'Gabriel García Márquez',
  category: 'Tiểu thuyết',
  description: 'Tác phẩm kể về lịch sử của gia đình Buendía qua nhiều thế hệ...',
  status: 'borrowed',
  coverImage: '/api/placeholder/220/250'
},
  // Thêm các sách khác...
  ];

  // Hiển thị modal chi tiết sách
  function showBookDetails(bookId) {
  // Tìm thông tin sách từ ID
  const book = books.find(b => b.id === bookId);
  if (!book) return;

  // Tạo modal
  const modal = document.createElement('div');
  modal.className = 'book-modal';
  modal.innerHTML = `
                    <div class="modal-content">
                        <span class="close-modal">&times;</span>
                        <div class="modal-book-details">
                            <div class="modal-book-cover">
                                <img src="${book.coverImage}" alt="${book.title}">
                                <div class="book-status ${book.status === 'available' ? 'status-available' : 'status-borrowed'}">
                                    ${book.status === 'available' ? 'Có sẵn' : 'Đã cho mượn'}
                                </div>
                            </div>
                            <div class="modal-book-info">
                                <h2>${book.title}</h2>
                                <p class="modal-book-author">Tác giả: ${book.author}</p>
                                <p class="modal-book-category">Thể loại: ${book.category}</p>
                                <p class="modal-book-description">${book.description}</p>
                                <div class="modal-book-actions">
                                    <button class="btn btn-primary" ${book.status === 'borrowed' ? 'disabled' : ''}>
                                        Mượn sách
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

  // Thêm modal vào body
  document.body.appendChild(modal);

  // Hiển thị modal
  setTimeout(() => {
  modal.style.opacity = '1';
}, 10);

  // Xử lý sự kiện đóng modal
  const closeBtn = modal.querySelector('.close-modal');
  closeBtn.addEventListener('click', () => {
  modal.style.opacity = '0';
  setTimeout(() => {
  document.body.removeChild(modal);
}, 300);
});
}

  // Chức năng lọc sách theo thể loại
  function filterBooksByCategory(category) {
  const bookSections = document.querySelectorAll('.books-section');

  if (category === 'Tất cả') {
  // Hiển thị tất cả các section
  bookSections.forEach(section => {
  section.style.display = 'block';
});
} else {
  // Ẩn tất cả các section trước
  bookSections.forEach(section => {
  section.style.display = 'none';
});

  // Hiển thị section phù hợp với thể loại
  const matchingSection = document.querySelector(`.books-section h2.section-title:contains("${category}")`).parentElement;
  if (matchingSection) {
  matchingSection.style.display = 'block';
}
}
}

  // Thêm hàm tiện ích cho việc tìm kiếm trong text
  Element.prototype.contains = function(text) {
  return this.textContent.includes(text);
};

  // Chức năng tìm kiếm sách
  function searchBooks(query) {
  if (!query) {
  // Nếu không có từ khóa tìm kiếm, hiển thị tất cả sách
  document.querySelectorAll('.book-card').forEach(card => {
  card.style.display = 'block';
});
  return;
}

  query = query.toLowerCase();

  // Lọc và hiển thị sách phù hợp
  document.querySelectorAll('.book-card').forEach(card => {
  const title = card.querySelector('.book-title').textContent.toLowerCase();
  const author = card.querySelector('.book-author').textContent.toLowerCase();
  const description = card.querySelector('.book-description').textContent.toLowerCase();

  if (title.includes(query) || author.includes(query) || description.includes(query)) {
  card.style.display = 'block';
} else {
  card.style.display = 'none';
}
});
}

  // Lọc sách theo trạng thái
  function filterBooksByStatus(status) {
  if (status === 'all') {
  // Hiển thị tất cả sách
  document.querySelectorAll('.book-card').forEach(card => {
  card.style.display = 'block';
});
  return;
}

  // Lọc theo trạng thái
  document.querySelectorAll('.book-card').forEach(card => {
  const bookStatus = card.querySelector('.book-status').classList.contains('status-available') ? 'available' : 'borrowed';

  if (bookStatus === status) {
  card.style.display = 'block';
} else {
  card.style.display = 'none';
}
});
}

  // Sắp xếp sách
  function sortBooks(sortOption) {
  const booksGrids = document.querySelectorAll('.books-grid');

  booksGrids.forEach(grid => {
  const books = Array.from(grid.querySelectorAll('.book-card'));

  books.sort((a, b) => {
  const titleA = a.querySelector('.book-title').textContent;
  const titleB = b.querySelector('.book-title').textContent;

  switch (sortOption) {
  case 'title_asc':
  return titleA.localeCompare(titleB);
  case 'title_desc':
  return titleB.localeCompare(titleA);
  case 'popular':
  // Giả lập sắp xếp theo độ phổ biến (sẽ được thay thế bằng dữ liệu thực)
  return Math.random() - 0.5;
  case 'newest':
  default:
  // Giả lập sắp xếp theo thời gian (sẽ được thay thế bằng dữ liệu thực)
  return Math.random() - 0.5;
}
});

  // Xóa tất cả sách hiện tại
  books.forEach(book => grid.removeChild(book));

  // Thêm lại sách đã sắp xếp
  books.forEach(book => grid.appendChild(book));
});
}


  // Kết nối các chức năng với giao diện
  document.querySelectorAll('.category-filter').forEach(filter => {
  filter.addEventListener('click', function() {
  const category = this.textContent;
  filterBooksByCategory(category);
});
});

  document.querySelector('.search-input input').addEventListener('input', function() {
  searchBooks(this.value);
});

  document.querySelector('select[name="status"]').addEventListener('change', function() {
  filterBooksByStatus(this.value);
});

  document.querySelector('select[name="sort"]').addEventListener('change', function() {
  sortBooks(this.value);
});
});
