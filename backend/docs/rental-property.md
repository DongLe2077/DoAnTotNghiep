# Thông tin nhà trọ

Hồ sơ dùng chung cho một nhà trọ trong phạm vi ứng dụng hiện tại. Chủ trọ mở **Thông tin nhà trọ** để cấu hình; các PDF xuất tiếp theo dùng thông tin này. Không tự suy ra tên, địa chỉ hoặc tài khoản ngân hàng từ dữ liệu demo/người dùng.

## API

| Phương thức sau `/api/owner` | Kết quả |
| --- | --- |
| GET `/property` | Các trường hiện tại, `configured`, `revision`, `version_token`, `updated_at` |
| PUT `/property` | Lưu toàn bộ thông tin sau bước xem lại/xác nhận; yêu cầu token vừa đọc |

Yêu cầu tài khoản chủ trọ hoạt động; PUT có CSRF. Người thuê không truy cập API cấu hình. Người được cấp quyền xem chứng từ sẽ thấy các thông tin được đưa lên PDF.

- Bắt buộc: `name` tối đa 150 ký tự, `address` tối đa 500.
- Tùy chọn: `contact_name`, `phone`, `email`, `notes` (ghi chú trên chứng từ, tối đa 2.000 ký tự).
- `bank_name`, `bank_account_number`, `bank_account_name`: để trống cả ba hoặc nhập đủ cả ba. Số tài khoản là chuỗi để giữ số 0 đầu, không chuyển thành số thực.
- Gửi đủ các trường khi PUT; trường tùy chọn có thể null để xóa nội dung cũ. Actor, ID, revision và thời gian do máy chủ đặt.

Hồ sơ có một hàng ID=1. HMAC phiên bản và khóa hàng bảo vệ khi hai nơi sửa cùng lúc; token cũ hoặc hai lần khởi tạo đồng thời trả 409 để tải lại/đối chiếu. Lưu lại đúng nội dung không tăng revision. Đây không phải chức năng quản lý nhiều nhà trọ/chủ sở hữu.

Email này là liên hệ in trên chứng từ, không thêm email đăng nhập hoặc chức năng quên mật khẩu. Tài khoản ngân hàng chỉ là thông tin hướng dẫn chuyển khoản, không tự xác nhận đã nhận tiền.

## PDF và dữ liệu cũ

Khi chưa cấu hình, GET trả `configured: false`; PDF ghi rõ chưa có thông tin, không ngăn người dùng tải lịch sử hóa đơn có quyền xem. Tệp đã tải giữ nội dung tại thời điểm xuất; cập nhật hồ sơ không sửa lại những tệp đó.

Migration `2026_10_28_000001_create_rental_properties.php` thêm bảng mới, không thay đổi các bảng người thuê, hóa đơn hoặc tài khoản cũ. Rollback bị chặn khi đã có hồ sơ để tránh mất cấu hình.

[PDF hóa đơn và phiếu thu](pdf-documents.md), [hướng dẫn tự thử](../../frontend/docs/property-pdf-self-test.md).
