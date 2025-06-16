document.addEventListener('DOMContentLoaded', () => {

  const donationForm = document.getElementById('donationForm');

  if (donationForm) {
    donationForm.addEventListener('submit', function(event) {
      // Ngăn chặn hành vi mặc định của form (tải lại trang)
      event.preventDefault();

      // 1. Thu thập dữ liệu từ các ô input
      // 'this' ở đây chính là form đã được gửi đi
      const formData = new FormData(this);

      // Chuyển đổi FormData thành một đối tượng (object) thông thường
      const donationData = {};
      formData.forEach((value, key) => {
        donationData[key] = value;
      });

      // 2. Kiểm tra dữ liệu (Validation đơn giản)
      // Trong thực tế có thể kiểm tra kỹ hơn, ví dụ email có đúng định dạng không
      if (!donationData.fullName || !donationData.email || !donationData.phone || !donationData.bookTitle) {
        alert('Vui lòng điền đầy đủ các trường thông tin có dấu *.');
        return; // Dừng lại nếu thiếu thông tin
      }

      // 3. Xử lý dữ liệu: In ra Console để kiểm tra
      console.log("===== Thông tin quyên góp nhận được =====");
      console.log("Họ tên:", donationData.fullName);
      console.log("Email:", donationData.email);
      console.log("Số điện thoại:", donationData.phone);
      console.log("Tên sách:", donationData.bookTitle);
      console.log("Tác giả:", donationData.author);
      console.log("Tình trạng sách:", donationData.bookCondition);
      console.log("-----------------------------------------");

      // 4. Phản hồi cho người dùng và reset form
      alert('Cảm ơn bạn đã gửi thông tin quyên góp sách! Chúng tôi sẽ liên hệ với bạn sớm nhất có thể.');

      // Tự động xóa trắng form
      this.reset();
    });
  }
});
