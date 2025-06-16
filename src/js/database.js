
const books = [
  {
    id: 1,
    title: 'Nhà Giả Kim',
    author: 'Paulo Coelho',
    image: 'img/Book/Nhà_giả_kim.png', //
    category: 'Tiểu thuyết',
    description: 'Tiểu thuyết kể về hành trình của chàng chăn cừu Santiago đi tìm kho báu, nhưng giá trị lớn nhất mà cậu nhận được lại là sự thấu hiểu bản thân và thế giới.',
    status: 'available' // 'available' hoặc 'borrowed'
  },
  {
    id: 2,
    title: 'Đắc Nhân Tâm',
    author: 'Dale Carnegie',
    image: 'img/Book/đắc_nhân_tâm.jpg', //
    category: 'Kỹ năng sống',
    description: 'Cuốn sách kinh điển về nghệ thuật giao tiếp và đối nhân xử thế, giúp bạn xây dựng những mối quan hệ tốt đẹp và thành công trong cuộc sống.',
    status: 'available'
  },
  {
    id: 3,
    title: 'Thế Giới Phẳng',
    author: 'Thomas L. Friedman',
    image: 'img/Book/Thế_giới_phẳng.jpg', //
    category: 'Kinh tế',
    description: 'Một cái nhìn sâu sắc về toàn cầu hóa trong thế kỷ 21 và những thay đổi mang tính cách mạng mà nó mang lại cho thế giới.',
    status: 'borrowed'
  },
  {
    id: 4,
    title: 'Hai Số Phận',
    author: 'Jeffrey Archer',
    image: 'img/Book/hai_số_phận.webp', //
    category: 'Tiểu thuyết',
    description: 'Câu chuyện song hành đầy kịch tính về cuộc đời của hai người đàn ông sinh cùng ngày nhưng ở hai hoàn cảnh trái ngược nhau.',
    status: 'available'
  },
  {
    id: 5,
    title: 'Người Đua Diều',
    author: 'Khaled Hosseini',
    image: 'img/Book/nguoi-dua-dieu.png', //
    category: 'Tiểu thuyết',
    description: 'Một câu chuyện cảm động về tình bạn, sự phản bội và chuộc lỗi tại Afghanistan, lấy bối cảnh những biến động chính trị của đất nước này.',
    status: 'available'
  },
  {
    id: 6,
    title: 'Điểm Đến Của Cuộc Đời',
    author: 'Đặng Hoàng Giang',
    image: 'img/Book/điểm_đến_của_cuộc_đời.jpg', //
    category: 'Tâm lý học',
    description: 'Cuốn sách là hành trình đồng hành cùng những người ở cận kề cái chết, mang đến những chiêm nghiệm sâu sắc về sự sống và ý nghĩa cuộc đời.',
    status: 'borrowed'
  },
  {
    id: 7,
    title: 'Trăm Năm Cô Đơn',
    author: 'Gabriel García Márquez',
    image: 'img/Book/Trăm_năm_cô_đơn.jpeg', //
    category: 'Tiểu thuyết',
    description: 'Một kiệt tác của văn học hiện thực huyền ảo, kể về dòng họ Buendía ở ngôi làng Macondo hư cấu qua bảy thế hệ.',
    status: 'available'
  },
  {
    id: 8,
    title: 'Số Đỏ',
    author: 'Vũ Trọng Phụng',
    image: 'img/Book/số_đỏ.webp', //
    category: 'Tiểu thuyết',
    description: 'Tác phẩm châm biếm kinh điển của văn học Việt Nam, đả kích sâu cay xã hội thành thị Việt Nam trong thời kỳ Pháp thuộc.',
    status: 'available'
  },
  {
    id: 9,
    title: 'Súng, Vi Trùng và Thép',
    author: 'Jared Diamond',
    image: 'img/Book/súng_vi_trùng_và_thép.webp', //
    category: 'Khoa học',
    description: 'Lý giải tại sao các xã hội Á-Âu lại chinh phục và có ảnh hưởng lớn đến các xã hội khác, dưới góc độ địa lý, sinh học và môi trường.',
    status: 'available'
  },
  {
    id: 10,
    title: 'Vũ Trụ Trong Vỏ Hạt Dẻ',
    author: 'Stephen Hawking',
    image: 'img/Book/vũ_trụ_trong_vỏ_hạt_dẻ.jpg', //
    category: 'Khoa học',
    description: 'Stephen Hawking dẫn dắt người đọc vào những khám phá kỳ thú của vật lý lý thuyết, từ những chiều không gian phụ đến du hành thời gian.',
    status: 'borrowed'
  },
  {
    id: 11,
    title: 'Lược Sử Thời Gian',
    author: 'Stephen Hawking',
    image: 'img/Book/Lược_sử_thời_gian.jpg', //
    category: 'Khoa học',
    description: 'Một trong những cuốn sách khoa học phổ thông bán chạy nhất mọi thời đại, giải thích các khái niệm phức tạp về vũ trụ học.',
    status: 'available'
  },
  {
    id: 12,
    title: 'Gen Vị Kỷ',
    author: 'Richard Dawkins',
    image: 'img/Book/gen_vị_kỷ.jpg', //
    category: 'Khoa học',
    description: 'Cuốn sách đưa ra quan điểm rằng gen là đơn vị trung tâm của sự tiến hóa, và các cơ thể sống chỉ là những "cỗ máy sinh tồn" cho gen.',
    status: 'available'
  },
  {
    id: 13,
    title: 'Toán 12 (Giải tích & Hình học)',
    author: 'Bộ Giáo dục và Đào tạo',
    image: 'img/Book/Toán_12.jpg', //
    category: 'Sách giáo khoa',
    description: 'Bộ sách giáo khoa Toán lớp 12, bao gồm hai tập Giải tích và Hình học, theo chương trình chuẩn của Bộ Giáo dục và Đào tạo.',
    status: 'available'
  }
];
