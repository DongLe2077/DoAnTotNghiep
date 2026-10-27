# Tự kiểm tra thông báo chung — 24/10/2026

Bản demo dùng CSDL riêng và giữ dữ liệu khi khởi động lại. Mở [đăng nhập](http://127.0.0.1:5174/login). Nếu chưa chạy, vào `D:\KLTN\frontend` và chạy `npm run demo`.

## Chủ trọ soạn và gửi

1. Đăng nhập **admin / 123**, mở **Thông báo chung** trong menu. Có thể cuộn menu nếu cửa sổ thấp. Đường dẫn: `/owner/announcements`.
2. Chọn **Soạn thông báo**, điền tiêu đề và nội dung, ví dụ “Lịch kiểm tra nước” và thời gian kiểm tra.
3. Chọn **Người đang ở các phòng đã chọn**, tìm và chọn **TEST-USER**. Hoặc chọn toàn bộ người đang ở để thử gửi chung.
4. Nhấn **Xem trước và người nhận**. Đối chiếu nội dung và danh sách; chỉ tài khoản hoạt động của người đang ở mới xuất hiện, gồm cả thành viên thường. Phòng trống/người chưa có tài khoản không có người nhận.
5. Tích **Tôi đã kiểm tra nội dung và người nhận**, nhấn **Xác nhận gửi thông báo**. Trang chi tiết hiển thị số người nhận, đã đọc/chưa đọc và danh sách từng người.

Danh sách đã gửi có tìm theo nội dung/tiêu đề, lọc phạm vi và phân trang. Trang chi tiết lọc người đã đọc/chưa đọc; nút **Cập nhật trạng thái đọc** lấy số liệu mới.

## Người thuê nhận và đọc

1. Mở trình duyệt hoặc hồ sơ trình duyệt khác, đăng nhập **user / 123**. Các tab trong cùng trình duyệt dùng chung phiên nên sẽ đổi tài khoản cùng nhau.
2. Mở **Thông báo** trên thanh đầu trang; lọc loại **Thông báo chung**. Nhấn **Làm mới** nếu vừa gửi; số chuông tự cập nhật mỗi 60 giây khi tab hiển thị.
3. Chọn **Xem nội dung** ở thông báo mới. Hộp thoại mở đúng nội dung và đánh dấu đã đọc; liên kết trong hộp thoại mở trang đầy đủ.
4. Nếu mở trực tiếp `/tenant/announcements/{id}`, dùng nút **Đánh dấu đã đọc** khi muốn ghi nhận. GET trang chi tiết không tự ghi thay đổi.
5. Quay lại chủ trọ, nhấn **Cập nhật trạng thái đọc**. Đúng người nhận chuyển sang đã đọc và có thời điểm đọc.

## Các tình huống cần đối chiếu

- Phạm vi không có tài khoản hợp lệ: hiển thị 0 người nhận, không cho gửi.
- Nội dung hoặc danh sách người nhận đổi sau xem trước: yêu cầu xem trước lại, không gửi theo danh sách cũ.
- Lỗi mạng sau khi gửi: giữ trang hoặc tải lại trong cùng tab; **Gửi lại lượt đang chờ** kiểm tra đúng lượt cũ, không tạo thêm. Sau khi đóng cả phiên trình duyệt, kiểm tra danh sách đã gửi trước khi soạn lượt mới.
- Lỗi phiên/CSRF không tự gửi lại. Giao diện hướng dẫn thao tác tiếp và giữ nội dung để kiểm tra.
- Người mới chuyển vào không nhận thông báo gửi trước đó. Người đã được gửi giữ quyền đọc kể cả khi trả phòng; tài khoản khác không được đọc bằng cách đổi ID trên URL.
- Nội dung là văn bản thuần. Thông báo đã gửi không sửa/xóa; gửi thông báo đính chính mới khi cần.
- Thử màn hình điện thoại: nội dung xuống dòng, menu mở được, nút và bộ lọc thao tác được.

Chức năng gửi thông báo trong ứng dụng; chưa gửi email/SMS. Trạng thái đã đọc ghi nhận thao tác của tài khoản, không xác nhận người thuê đã làm theo nội dung.

Quy tắc và API: [backend](../../backend/docs/announcements.md).

Nghiệm thu 24/10/2026: 4 luồng thông báo chung và 23 luồng hộp thông báo/đăng nhập liên quan đều đạt trên Chrome (27/27, 3,5 phút). Đã xem ảnh chụp máy tính/điện thoại. Toàn bộ backend 328/328 kiểm thử, frontend 119/119 kiểm thử và bản đóng gói đạt; kiểm tra MySQL riêng đạt 15 bước, dữ liệu được rollback giữ nguyên. Hai tài khoản demo và API thông báo tương ứng đã kiểm tra đăng nhập thành công.
