document.addEventListener('DOMContentLoaded', () => {
    Auth.requireLogin();
    fetchCategories();
});

let isEditMode = false;

// --- 1. TẢI DANH SÁCH ---
async function fetchCategories() {
    try {
        const response = await fetch(`${CONFIG.API_BASE_URL}/api/categories/read.php`);
        const categories = await response.json();
        
        const tbody = document.getElementById('categoryTableBody');
        tbody.innerHTML = '';

        if (!categories || categories.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center">Chưa có thể loại nào.</td></tr>';
            return;
        }

        categories.forEach(cat => {
            const tr = document.createElement('tr');
            tr.id = `row-${cat.id}`;
            tr.innerHTML = `
                <td>#${cat.id}</td>
                <td><b>${cat.name}</b></td>
                <td>
                    <button class="btn btn-blue" onclick="openModal(${cat.id}, '${cat.name}')" style="padding:5px 10px;">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-red" onclick="deleteCategory(${cat.id})" style="padding:5px 10px;">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });

    } catch (error) {
        console.error(error);
        alert('Lỗi tải danh sách thể loại');
    }
}

// --- 2. XỬ LÝ MODAL ---
function openModal(id = null, name = '') {
    const modal = document.getElementById('categoryModal');
    const title = document.getElementById('modalTitle');
    const idInput = document.getElementById('catId');
    const nameInput = document.getElementById('catName');

    modal.style.display = 'flex';
    
    if (id) {
        // Chế độ Sửa
        isEditMode = true;
        title.innerText = 'Cập nhật Thể loại';
        idInput.value = id;
        nameInput.value = name;
    } else {
        // Chế độ Thêm
        isEditMode = false;
        title.innerText = 'Thêm Thể loại Mới';
        idInput.value = '';
        nameInput.value = '';
    }
    nameInput.focus();
}

function closeModal() {
    document.getElementById('categoryModal').style.display = 'none';
}

// --- 3. LƯU (THÊM / SỬA) ---
async function handleSave(e) {
    e.preventDefault();
    
    const id = document.getElementById('catId').value;
    const name = document.getElementById('catName').value.trim();
    const btn = document.getElementById('btn-save');

    if (!name) return alert("Vui lòng nhập tên thể loại");

    btn.disabled = true;
    btn.innerText = "Đang lưu...";

    try {
        let url = `${CONFIG.API_BASE_URL}/api/categories/create.php`;
        let method = 'POST';
        let body = { name: name };

        if (isEditMode) {
            url = `${CONFIG.API_BASE_URL}/api/categories/update.php`;
            // method = 'PUT'; // Một số host chặn PUT, dùng POST cho lành
            body = { id: id, name: name };
        }

        const response = await Auth.fetch(url, {
            method: method,
            body: JSON.stringify(body)
        });

        const result = await response.json();

        if (response.ok) {
            alert(result.message || 'Thành công!');
            closeModal();
            fetchCategories(); // Tải lại bảng
        } else {
            throw new Error(result.error || 'Có lỗi xảy ra');
        }

    } catch (error) {
        alert('Lỗi: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.innerText = "Lưu";
    }
}

// --- 4. XÓA THỂ LOẠI ---
async function deleteCategory(id) {
    if (!confirm('Bạn có chắc chắn muốn xóa thể loại này? Lưu ý: Nếu có sách thuộc thể loại này, bạn sẽ không xóa được.')) return;

    try {
        const response = await Auth.fetch(`${CONFIG.API_BASE_URL}/api/categories/delete.php`, {
            method: 'POST',
            body: JSON.stringify({ id: id })
        });

        const result = await response.json();

        if (response.ok) {
            alert('Đã xóa thành công!');
            // Xóa dòng HTML
            const row = document.getElementById(`row-${id}`);
            if(row) row.remove();
        } else {
            alert('Lỗi: ' + (result.error || 'Không thể xóa'));
        }
    } catch (error) {
        alert('Lỗi kết nối server.');
    }
}