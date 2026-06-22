<?php
// File: C:\xampp\htdocs\B4Eproject\api\config\stale_helper.php

function checkAndUpdateStaleBorrowings($db) {
    try {
        // 1. Chuyển 'shipped' sang 'borrowed' sau 7 ngày
        $staleShipped = $db->query("
            SELECT br.id, br.book_id, br.user_id, b.title AS book_title 
            FROM borrowings br
            JOIN books b ON br.book_id = b.id
            WHERE br.status = 'shipped' AND br.shipped_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        while ($row = $staleShipped->fetch()) {
            $borrow_id = $row['id'];
            $book_id = $row['book_id'];
            $user_id = $row['user_id'];
            $book_title = $row['book_title'];
            
            // Cập nhật trạng thái sang 'borrowed'
            $db->prepare("UPDATE borrowings SET status = 'borrowed', approved_at = IFNULL(approved_at, NOW()) WHERE id = ?")->execute([$borrow_id]);
            $db->prepare("UPDATE books SET status = 'borrowed' WHERE id = ?")->execute([$book_id]);
            
            // Tạo thông báo tự động cho người dùng
            $db->prepare("INSERT INTO notifications (user_id, type, title, message, borrowing_id, is_read, created_at) 
                          VALUES (?, 'borrow_approved', 'Tự động xác nhận đã nhận sách 📚', 
                                  ?, ?, 0, NOW())")
               ->execute([
                   $user_id, 
                   "Hệ thống tự động xác nhận bạn đã nhận được sách \"{$book_title}\" sau 7 ngày vận chuyển.", 
                   $borrow_id
               ]);
        }

        // 2. Chuyển 'return_approved' trở lại 'borrowed' hoặc 'overdue' sau 7 ngày nếu không gửi ship
        $staleReturnApproved = $db->query("
            SELECT br.id, br.due_date, br.user_id, b.title AS book_title 
            FROM borrowings br
            JOIN books b ON br.book_id = b.id
            WHERE br.status = 'return_approved' AND br.return_approved_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        while ($row = $staleReturnApproved->fetch()) {
            $borrow_id = $row['id'];
            $due_date = $row['due_date'];
            $user_id = $row['user_id'];
            $book_title = $row['book_title'];
            
            // Kiểm tra xem đã quá hạn hay chưa để set status tương ứng
            $today = date('Y-m-d');
            $newStatus = ($today > $due_date) ? 'overdue' : 'borrowed';
            
            $db->prepare("UPDATE borrowings SET status = ? WHERE id = ?")->execute([$newStatus, $borrow_id]);
            
            // Gửi thông báo cho người dùng
            $title = ($newStatus === 'overdue') ? 'Yêu cầu trả sách quá hạn và đã bị hủy 🚨' : 'Yêu cầu trả sách đã bị hủy';
            $msg = ($newStatus === 'overdue') ? 
                "Yêu cầu trả cuốn \"{$book_title}\" đã bị hủy do quá 7 ngày không gửi. Sách của bạn hiện đã QUÁ HẠN trả từ ngày {$due_date}." : 
                "Yêu cầu trả cuốn \"{$book_title}\" đã bị hủy do quá 7 ngày không gửi. Sách đã quay lại trạng thái đang mượn.";
                
            $db->prepare("INSERT INTO notifications (user_id, type, title, message, borrowing_id, is_read, created_at) 
                          VALUES (?, 'system', ?, ?, ?, 0, NOW())")
               ->execute([$user_id, $title, $msg, $borrow_id]);
        }

        // 3. Hủy đơn mượn VietQR và trả trạng thái sách về 'available' sau 1 giờ nếu chưa được Admin duyệt/thanh toán
        $stalePayments = $db->query("
            SELECT br.id, br.book_id, br.user_id, b.title AS book_title 
            FROM borrowings br
            JOIN books b ON br.book_id = b.id
            WHERE br.payment_method = 'vietqr' 
              AND br.payment_status = 'pending' 
              AND br.status = 'pending_approval' 
              AND br.created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        while ($row = $stalePayments->fetch()) {
            $borrow_id = $row['id'];
            $book_id = $row['book_id'];
            $user_id = $row['user_id'];
            $book_title = $row['book_title'];
            
            // Hủy đơn mượn
            $db->prepare("UPDATE borrowings SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?")->execute([$borrow_id]);
            
            // Trả trạng thái sách về 'available'
            $db->prepare("UPDATE books SET status = 'available' WHERE id = ?")->execute([$book_id]);
            
            // Gửi thông báo cho người dùng
            $db->prepare("INSERT INTO notifications (user_id, type, title, message, ref_id, is_read, created_at) 
                          VALUES (?, 'system', 'Giao dịch chuyển tiền quá hạn ⏳', 
                                  ?, ?, 0, NOW())")
               ->execute([
                   $user_id, 
                   "Yêu cầu mượn cuốn \"{$book_title}\" của bạn đã tự động bị hủy do quá hạn 1 tiếng thanh toán phí ship qua VietQR.", 
                   $borrow_id
               ]);
        }
    } catch (Exception $e) {
        error_log("Error in checkAndUpdateStaleBorrowings: " . $e->getMessage());
    }
}
