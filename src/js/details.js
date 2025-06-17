document.addEventListener('DOMContentLoaded', () => {

  // --- BƯỚC 1: LẤY VÀ KIỂM TRA ID TỪ URL ---
  const urlParams = new URLSearchParams(window.location.search);
  const bookIdStr = urlParams.get('id'); // Lấy ID dưới dạng chuỗi '1', '2',...

  // In ra Console để chúng ta có thể thấy những gì đang xảy ra
  console.log("ID lấy từ URL (dạng chuỗi):", bookIdStr);

  // KIỂM TRA 1: ID có tồn tại trên URL không?
  if (!bookIdStr) {
    displayError("Lỗi: Không có ID sách trong địa chỉ URL.", "Hãy quay lại trang danh mục và nhấn vào nút 'Xem chi tiết' của một cuốn sách.");
    return; // Dừng thực thi ngay lập tức nếu không có ID
  }

  const bookId = parseInt(bookIdStr); // Chuyển ID từ chuỗi thành số
  console.log("ID sau khi chuyển thành số:", bookId);

  // KIỂM TRA 2: ID có phải là một số hợp lệ không?
  if (isNaN(bookId)) {
    displayError("Lỗi: ID sách không hợp lệ.", "ID trên URL không phải là một con số.");
    return; // Dừng thực thi nếu ID không phải là số
  }

  // --- BƯỚC 2: TÌM SÁCH TRONG DATABASE ---
  console.log("Đang tìm kiếm sách với ID:", bookId);
  // Biến 'books' phải có sẵn từ tệp 'database.js'
  const book = books.find(b => b.id === bookId);
  console.log("Kết quả tìm sách:", book); // Sẽ in ra đối tượng sách nếu tìm thấy, hoặc 'undefined' nếu không thấy

  // --- BƯỚC 3: HIỂN THỊ KẾT QUẢ ---
  if (book) {
    // Nếu tìm thấy sách, gọi hàm hiển thị chi tiết
    displayBookDetails(book);
  } else {
    // Nếu không tìm thấy, hiển thị lỗi
    displayError(`Lỗi: Không tìm thấy sách với ID = ${bookId}.`, "Cuốn sách này có thể không tồn tại trong cơ sở dữ liệu của chúng ta.");
  }
});

/**
 * Hàm này nhận vào một đối tượng sách và điền thông tin vào trang HTML.
 * @param {object} book - Đối tượng sách cần hiển thị.
 */
function displayBookDetails(book) {
  // --- Cập nhật tiêu đề trang ---
  document.title = `${book.title} - Thư viện Cộng đồng`;

  // --- Điền thông tin cho CỘT BÊN PHẢI ---
  document.getElementById('book-title-main').textContent = book.title;
  document.getElementById('book-author-main').textContent = book.author;
  document.getElementById('book-category').textContent = book.category;

  // Xử lý mô tả có nhiều dòng
  const descriptionHTML = book.description.split('\n').map(p => `<p>${p}</p>`).join('');
  document.getElementById('book-description').innerHTML = descriptionHTML;

  // --- Điền thông tin cho CỘT BÊN TRÁI ---
  document.getElementById('book-cover-img').src = book.image;
  document.getElementById('book-cover-img').alt = book.title;
  document.getElementById('book-title-left').textContent = book.title;
  document.getElementById('book-author-left').textContent = book.author;

  // Cập nhật link và trạng thái cho nút
  const borrowLink = document.getElementById('borrow-link');
  const statusBadge = document.getElementById('book-status-badge');

  if (book.status === 'available') {
    statusBadge.textContent = 'Có sẵn';
    statusBadge.className = 'status-badge'; // Class mặc định màu xanh
    borrowLink.href = `borrow-form.html?title=${encodeURIComponent(book.title)}`;
    borrowLink.style.display = 'block'; // Hiển thị nút
  } else {
    statusBadge.textContent = 'Đã cho mượn';
    statusBadge.className = 'status-badge borrowed'; // Class màu vàng
    borrowLink.style.display = 'none'; // Ẩn nút mượn sách
  }
}
/**
 * Hàm này hiển thị một thông báo lỗi trên trang.
 * @param {string} mainMessage - Thông báo lỗi chính.
 * @param {string} secondaryMessage - Thông báo phụ, hướng dẫn.
 */
function displayError(mainMessage, secondaryMessage) {
  const container = document.querySelector('.container.mx-auto');
  if (container) {
    container.innerHTML = `
            <div class="text-center py-10">
                <h1 class="text-3xl font-bold text-red-600">${mainMessage}</h1>
                <p class="mt-4">${secondaryMessage}</p>
                <a href="books.html" class="mt-6 inline-block bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600">Quay lại trang danh mục</a>
            </div>
        `;
  }
}
