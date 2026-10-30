# Bản trình và hoàn thiện giao diện — 30/10/2026

## Đã thực hiện

- Bản trình `npm run presentation` tại 5175/18002; CSDL, ảnh và cookie riêng, giữ trạng thái sau khởi động. Môi trường chỉ chấp nhận đúng đường dẫn SQLite/storage của bản trình; không dùng `rental_management` hoặc ghi đè demo 5174.
- Dữ liệu mẫu: một người/nhiều thành viên/đại diện; phòng trống/bảo trì; hóa đơn thu đủ, thu một phần, quá hạn; kỳ sẵn sàng lập hóa đơn; sự cố có ảnh, lịch sử và chi phí liên kết; cấu hình nhà trọ dùng trên PDF.
- Công cụ lưu mốc mã nguồn/CSDL/ảnh, kiểm tra khôi phục vào thư mục mới và khôi phục dữ liệu bản trình sau tập. Khi khôi phục, giữ dữ liệu trước đó để đối chiếu; không thay mã nguồn/MySQL.
- Hộp thoại có hiệu ứng nhẹ 180 ms bằng CSS; dùng tiếp hành vi bàn phím và quản lý tiêu điểm của dialog hiện tại. [Cơ chế CSS dialog](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/dialog).
- Đồng bộ phản hồi nút/thẻ, thông báo có nội dung đọc đầy đủ; khung chờ ở tổng quan, phòng/dịch vụ, hóa đơn và phòng người thuê. Khi chưa tải được dữ liệu, không hiển thị số tiền 0 như kết quả thật.
- Cảnh báo bản nháp khi rời thông tin nhà trọ, nhận phòng và gửi báo hỏng; lựa chọn ở lại giữ nội dung. Biểu mẫu đã lưu đi tiếp bình thường; đăng xuất/đổi phiên không bị bản nháp cũ chặn.
- Tiến độ lượt nhập nhiều phòng, kết quả từng dòng và các bản chờ hiện có; giữ vị trí cuộn khi quay lại bằng lịch sử trình duyệt. Hiệu ứng tôn trọng thiết lập giảm chuyển động.

## Kết quả kiểm tra

| Phần | Kết quả |
| --- | --- |
| Toàn bộ frontend unit | 131/131 |
| Kiểm tra kiểu dữ liệu và đóng gói | Đạt |
| Các helper PHP đã thay đổi | Cú pháp và Pint đạt |
| Chrome toàn bộ | 94/94, một lượt hoàn chỉnh trong 27,0 phút |
| Đối chiếu bản trình riêng | 30 kiểm tra, 10 ảnh màn hình hai vai trò |
| Tập luồng nghiệp vụ trên bản trình | 15 kiểm tra đạt |
| Sao lưu/khôi phục | Dữ liệu mọi bảng, khóa ngoại, số dư và 3 ảnh khớp |
| Khôi phục sau lượt ghi thử | Đạt; dữ liệu trước khôi phục được giữ riêng |
| Cổng bận/mã bản sao sai | Từ chối trước khi chạy/ghi dữ liệu; cổng bận có thông báo gọn |

Bộ trình dùng 28 bảng nghiệp vụ hiện có và một bảng metadata fixture `presentation_seed`; bảng này chỉ nằm trong SQLite trình bày, không là migration ứng dụng. Kết quả 29 bảng trong biên bản khôi phục gồm cả metadata đó.

Lượt tập ghi một khoản thu bằng giao diện, gửi lại nguyên mã/nội dung và xác minh không tạo phiếu trùng; người thuê thấy đúng số dư và tải PDF. Đã xem trước/phát hành hóa đơn TB-202, cập nhật sửa chữa và kiểm tra lịch sử/ảnh người thuê. Sau đó dừng máy chủ, khôi phục về mốc trước lượt tập và đối chiếu toàn bộ dữ liệu/ảnh.

Lượt sáu ca giao diện đầu đạt 5/6; một ca so sánh thông báo 503 với nội dung giả lập thay vì câu lỗi đã được lớp API chuẩn hóa. Đã sửa cách đối chiếu trong kiểm thử. Hành vi báo lỗi/thử lại của ứng dụng được giữ.

Lượt Chrome cuối chạy toàn bộ thư mục `tests/e2e` với Chrome, một worker, không thử lại tự động: xác thực, thông báo, thiết kế, hóa đơn/thu tiền/kiểm soát, thu chi, quản lý thuê, sửa chữa/lịch sử/chi phí, tổng quan/công nợ, người thuê/ảnh, giao diện mới, nhập nhiều phòng và PDF đều đạt trong cùng lượt. Môi trường Laravel/SQLite/ảnh của bộ tự động tách khỏi bản trình và MySQL. Nhật ký cục bộ: `.cache/e2e/presentation-polish-all.log`.

Ảnh đọc từ dữ liệu giả; công cụ chụp không ghi nghiệp vụ. Các công cụ tự thử, nhật ký, CSDL, ảnh và mốc dữ liệu ở `.cache/`, ngoài Git. Gói mã nguồn sao lưu loại cấu hình bí mật, phiên và thư viện cài đặt. Mật khẩu đơn giản chỉ thuộc bản dùng thử cục bộ.

## Phạm vi còn lại trước buổi xem

- Người dùng tập kịch bản và cho giảng viên tự thử; ghi nhận góp ý cụ thể.
- Thử điện thoại thật/máy trình bày khác; ảnh kích thước 320–390px kiểm tra bố cục mô phỏng.
- Bản sao hiện nằm tại máy phát triển; sao chép thêm sang thiết bị khác để dự phòng hỏng máy.
- Hosting, video, Excel, ảnh đồng hồ, hợp đồng/cọc và Word vẫn thuộc các đợt chọn riêng.

[Cách chạy CMD, tài khoản và thao tác lưu mốc/khôi phục](presentation-guide.md). Bản trình đang dùng được đưa về dữ liệu ban đầu sau nghiệm thu, không để lại khoản thu hay hóa đơn của lượt tập.
