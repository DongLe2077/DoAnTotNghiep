# Tự kiểm tra lịch sử sửa chữa — 25/10/2026

Trong CMD, chạy `cd /d D:\KLTN\frontend`, rồi `npm run demo`. Mở [đăng nhập](http://127.0.0.1:5174/login). Bản demo giữ dữ liệu thử khi khởi động lại và tách MySQL.

1. Đăng nhập **admin / 123**, mở **Sửa chữa**, chọn một yêu cầu đang mở. Nếu yêu cầu mẫu đã hoàn tất, dùng tài khoản người đại diện đang ở để gửi yêu cầu mới.
2. Trang chi tiết có **Lịch sử xử lý** bên dưới phần ảnh. Yêu cầu có trước nâng cấp hiển thị mốc bắt đầu lưu lịch sử và giải thích chỉ còn trạng thái/ghi chú gần nhất của quá khứ. Yêu cầu mới có mục **Đã gửi yêu cầu**.
3. Chọn **Tiếp nhận / xử lý**, ghi “Đã hẹn thợ kiểm tra”, xem lại và xác nhận. Lịch sử có dòng mới: người cập nhật, thời điểm, trạng thái trước/sau và ghi chú.
4. Cập nhật ghi chú thành “Chờ linh kiện”, giữ trạng thái đang xử lý. Có dòng mới; mở **Ghi chú trước khi cập nhật** để xem nội dung cũ.
5. Thử xóa ghi chú hiện tại và xác nhận. Lịch sử ghi rõ đã xóa nội dung hiện tại; các ghi chú trước vẫn xem được. Gửi lại cùng trạng thái/ghi chú không tạo dòng mới.
6. Khi hoàn tất, điền kết quả và xác nhận. Lịch sử được giữ, yêu cầu không cho sửa tiếp như quy tắc hiện có.
7. Mở trình duyệt/hồ sơ khác, đăng nhập **user / 123** nếu đây là tác giả yêu cầu đã chọn. Vào **Báo hỏng** → chi tiết → **Lịch sử xử lý**. Người thuê thấy các cập nhật/ghi chú của yêu cầu mình gửi; không thấy tên đăng nhập chủ trọ hoặc lịch sử của người khác.
8. Chọn **Làm mới lịch sử** để lấy các lần mới. Khi có hơn 10 sự kiện, dùng **Trang trước/Trang sau** trong khung lịch sử. Thử thêm trên cửa sổ hẹp/điện thoại.

Người gửi vẫn xem lịch sử sau khi trả phòng nếu tài khoản còn hoạt động. Đại diện mới hoặc thành viên cùng phòng không tự nhận quyền đọc yêu cầu của người khác. Người gửi báo hỏng mới vẫn phải đủ điều kiện đại diện như trước.

Nếu mất phản hồi khi chủ trọ cập nhật, tải lại yêu cầu hoặc dùng **Tải bản mới nhất** khi có xung đột để đối chiếu kết quả. Lượt đã lưu có một sự kiện; không xác nhận thêm nội dung chỉ để thử lại mạng. Ảnh mới làm đổi phiên bản yêu cầu nhưng không tạo dòng xử lý giả.

Các ghi chú cũ là một phần lịch sử và được chia sẻ với người gửi, không có nút xóa nhật ký. Nhật ký sửa chữa không tự tạo khoản chi; chủ trọ tiếp tục ghi chi phí thực tế trong sổ thu chi.

[Quy tắc và API](../../backend/docs/maintenance-history.md).

Kiểm tra 25/10/2026: 336 backend, 119 frontend, 25 kịch bản Chrome liên quan, bản đóng gói và Pint đạt. MySQL đạt 14 bước, giữ nguyên dữ liệu sau rollback. Các bản ghi và ảnh MySQL/demo cũ được giữ khi thêm bảng lịch sử; không dựng các cập nhật quá khứ chưa được lưu.
