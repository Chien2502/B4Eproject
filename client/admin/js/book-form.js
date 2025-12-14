// Biến kiểm tra chế độ Edit
let isEditMode = false;
const urlParams = new URLSearchParams(window.location.search);
const bookId = urlParams.get('id');

document.addEventListener('DOMContentLoaded', () => {
    // 1. Kiểm tra đăng nhập
    Auth.requireLogin();

    // 2. Tải danh sách thể loại
    loadCategories();

    // 3. Nếu có ID -> Chế độ Edit
    if (bookId) {
        isEditMode = true;
        document.getElementById('page-title').innerText = 'Chỉnh sửa Sách';
        document.getElementById('btn-submit').innerText = 'Cập nhật';
        loadBookDetails(bookId);
    }
});

// --- HÀM TẢI DỮ LIỆU ---

async function loadCategories() {
    try {
        const response = await Auth.fetch(`${CONFIG.API_BASE_URL}/api/categories/read.php`);
        const categories = await response.json(); 
        
        const select = document.getElementById('categorySelect');
        categories.forEach(cat => {
            const option = document.createElement('option');
            option.value = cat.id;
            option.textContent = cat.name;
            select.appendChild(option);
        });
    } catch (err) { console.error("Lỗi tải thể loại:", err); }
}

async function loadBookDetails(id) {
    try {
        const response = await Auth.fetch(`${CONFIG.API_BASE_URL}/api/books/read_single.php?id=${id}`);
        const book = await response.json();

        // Điền dữ liệu vào form
        document.getElementById('title').value = book.title;
        document.getElementById('author').value = book.author;
        document.getElementById('publisher').value = book.publisher;
        document.getElementById('year').value = book.year;
        document.getElementById('description').value = book.description;
        
        document.getElementById('categorySelect').value = book.category_id;

        // Hiển thị ảnh cũ
        if (book.image_url) {
            const previewDiv = document.getElementById('current-image-preview');
            const img = document.getElementById('img-preview');
            // Xử lý đường dẫn ảnh
            img.src = `${CONFIG.API_BASE_URL}/api/uploads/${book.image_url}`; 
            previewDiv.style.display = 'block';
        }

    } catch (err) { alert("Không thể tải thông tin sách!"); }
}

// --- XỬ LÝ SUBMIT FORM ---

async function handleBookSubmit(e) {
    e.preventDefault();
    
    const btn = document.getElementById('btn-submit');
    const originalText = btn.innerText;
    btn.innerText = 'Đang lưu...';
    btn.disabled = true;

    // Chuẩn bị dữ liệu
    const formData = new FormData();
    formData.append('title', document.getElementById('title').value);
    formData.append('author', document.getElementById('author').value);
    formData.append('category_id', document.getElementById('categorySelect').value);
    formData.append('publisher', document.getElementById('publisher').value);
    formData.append('year', document.getElementById('year').value);
    formData.append('description', document.getElementById('description').value);

    // File ảnh
    const fileInput = document.getElementById('imageInput');
    if (fileInput.files[0]) {
        formData.append('image', fileInput.files[0]);
    }

    let url = `${CONFIG.API_BASE_URL}/api/books/create.php`;
    if (isEditMode) {
        url = `${CONFIG.API_BASE_URL}/api/books/update.php`;
        formData.append('id', bookId);
    }

    try {
        const res = await Auth.fetch(url, {
            method: 'POST',
            body: formData 
        });

        const result = await res.json();

        if (res.ok) {
            document.getElementById('alert-message').innerHTML = `<div style="color:green; margin-bottom:15px;">${result.message}</div>`;
            if (!isEditMode) document.getElementById('bookForm').reset();
        } else {
            throw new Error(result.error || 'Lỗi không xác định');
        }
    } catch (error) {
        document.getElementById('alert-message').innerHTML = `<div style="color:red; margin-bottom:15px;">Lỗi: ${error.message}</div>`;
    } finally {
        btn.innerText = originalText;
        btn.disabled = false;
    }
}
// --- XỬ LÝ MODAL THỂ LOẠI (Thêm nhanh thể loại mới) ---

function openCatModal() {
    const modal = document.getElementById('catModal');
    if (modal) {
        modal.style.display = 'flex';
        // Focus vào ô nhập liệu ngay khi mở
        const input = document.getElementById('newCatName');
        if (input) input.focus();
    }
}

function closeCatModal() {
    const modal = document.getElementById('catModal');
    if (modal) {
        modal.style.display = 'none';
        // Reset ô nhập liệu để lần sau mở ra trống trơn
        document.getElementById('newCatName').value = ''; 
    }
}

async function saveCategory() {
    const nameInput = document.getElementById('newCatName');
    const name = nameInput.value.trim();

    if (!name) {
        alert('Vui lòng nhập tên thể loại!');
        return;
    }

    // Khóa nút để tránh bấm nhiều lần
    const btnSave = document.querySelector('#catModal .btn-green');
    if(btnSave) {
        btnSave.disabled = true;
        btnSave.innerText = 'Đang lưu...';
    }

    try {
        const res = await Auth.fetch(`${CONFIG.API_BASE_URL}/api/categories/create.php`, {
            method: 'POST',
            body: JSON.stringify({ name: name })
        });

        const result = await res.json();

        if (res.ok) {
            // 1. Thêm option mới vào thẻ Select
            const select = document.getElementById('categorySelect');
            const option = document.createElement('option');
            
            // API cần trả về {id: 123, name: "Tên mới", message: "..."}
            option.value = result.id; 
            option.text = result.name || name; 
            option.selected = true; // Tự động chọn luôn thể loại vừa tạo
            
            select.appendChild(option);

            // 2. Thông báo và đóng modal
            alert('Đã thêm thể loại mới thành công!');
            closeCatModal();
        } else {
            alert('Lỗi: ' + (result.error || 'Không thể tạo thể loại'));
        }
    } catch (err) { 
        console.error(err);
        alert('Lỗi kết nối server.'); 
    } finally {
        // Mở lại nút bấm
        if(btnSave) {
            btnSave.disabled = false;
            btnSave.innerText = 'Lưu';
        }
    }
}