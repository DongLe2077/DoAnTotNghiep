# Tự kiểm tra hạn đóng và điều chỉnh hóa đơn

Dùng tài khoản chủ trọ tại `/owner/invoices`, tài khoản người thuê tại `/tenant/invoices`. Bản demo tách riêng tại http://127.0.0.1:5174; tài khoản `admin` và `user`, mật khẩu ban đầu `123`. Hai vai trò nên mở bằng hai trình duyệt hoặc hai hồ sơ khác nhau vì các tab cùng trình duyệt dùng chung phiên.

1. Mở hóa đơn chưa có lần thu. Kiểm tra tổng các dòng gốc, tổng hiện tại, số đã thu, số còn thiếu và hạn đóng. Hóa đơn cũ hiển thị Chưa đặt hạn.
2. Bấm **Đặt / đổi hạn thanh toán**, chọn hôm nay hoặc ngày sau, ghi lý do → Xem lại thay đổi → xác nhận. Kiểm tra lịch sử và hạn mới bên người thuê. Không thể chọn một ngày trước hôm nay cho thao tác mới.
3. Bấm **Điều chỉnh số tiền**, nhập `-50000` để giảm hoặc `50000` để tăng, ghi lý do rồi xác nhận. Dòng gốc không đổi; tổng hiện tại, công nợ và báo cáo đổi đúng số tiền.
4. Thu một phần. Thử giảm khiến tổng nhỏ hơn số đã thu: hệ thống chặn. Nút hủy không xuất hiện khi đã có lần thu; không thể xóa phiếu thu.
5. Trên một hóa đơn khác chưa thu: hủy với lý do. Kiểm tra trạng thái Đã hủy, công nợ bằng 0, không có nút thu/nhắc. Người thuê vẫn xem được lý do và dòng gốc khi còn quyền.
6. Khôi phục hóa đơn với lý do: tổng đã điều chỉnh và hạn đã lưu được áp dụng trở lại. Hủy không cho lập một hóa đơn trùng cùng kỳ hoặc sửa chỉ số nguồn.
7. Mở **Công nợ → Hạn thanh toán → Quá hạn**; thử xem theo hóa đơn/phòng/nhóm. Ngày sau hạn đóng, hóa đơn còn thiếu mới quá hạn. Hóa đơn không hạn/đã trả đủ/đã hủy không nằm trong danh sách. Chỉ số “Công nợ quá hạn” tính theo số còn thiếu.
8. Mở Tổng quan: thẻ Hóa đơn quá hạn dẫn đến đúng bộ lọc. Người thuê có bộ lọc tương tự, xem số ngày quá hạn và thông báo cập nhật hóa đơn.
9. Nếu xác nhận bị mất kết nối: giữ nguyên yêu cầu, dùng **Kiểm tra thay đổi đang chờ → Gửi lại thay đổi đang chờ**. Có thể tải lại trang trong cùng phiên. Đừng tạo khoản điều chỉnh mới trước khi kiểm tra lịch sử. Nếu nhận lỗi phiên bản, tải lại hóa đơn và đối chiếu trước khi xác nhận lại.

Lưu ý: để kiểm tra quá hạn ngay cần một hóa đơn thử đã được đặt hạn trước đó. Hệ thống không cho đặt lùi hạn trên dữ liệu thật chỉ để tạo tình trạng quá hạn. Bộ kiểm thử có dữ liệu quá hạn riêng.
