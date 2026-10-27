# Thông báo chung của chủ trọ

Cập nhật 24/10/2026. Chủ trọ soạn nội dung, xem trước đúng người nhận rồi xác nhận gửi. Người thuê nhận trong hộp thông báo hiện có; nội dung đầy đủ có trang riêng.

## Người nhận và quyền xem

- `audience=all`: toàn bộ người đang ở có tài khoản người thuê hoạt động, gồm cả thành viên không phải đại diện.
- `audience=rooms`: chỉ những người đủ điều kiện ở các phòng được chọn. Có thể chọn nhiều phòng; mỗi tài khoản nhận một thông báo cho một lượt gửi.
- Không gửi cho người đã trả phòng, chưa đến ngày ở, chưa liên kết tài khoản hoặc tài khoản bị khóa. Không được phát hành khi không có người nhận.
- Danh sách và tên phòng được chốt tại thời điểm gửi. Người vào ở sau không nhận thông báo cũ; người đã nhận vẫn xem được sau khi trả phòng. Người ngoài danh sách nhận bị trả 404 khi truy cập chi tiết.
- Người thuê chỉ nhận nội dung, thời điểm gửi và trạng thái đọc của chính mình. Danh sách người nhận và thống kê dành cho chủ trọ.
- Nội dung đã gửi được giữ nguyên; đính chính bằng lượt gửi mới. Chưa có sửa/xóa hoặc thu hồi thông báo.

## API

Tất cả API yêu cầu phiên đăng nhập; API ghi yêu cầu CSRF. Các đường dẫn chủ trọ nằm dưới `/api/owner`:

| Phương thức và đường dẫn | Nội dung |
| --- | --- |
| GET `/announcements` | Danh sách, lọc `q` theo tiêu đề/nội dung và `audience=all/rooms` |
| POST `/announcements/preview` | Nội dung chuẩn hóa, phòng, danh sách người nhận, `recipient_count`, `can_send`, `preview_token` |
| POST `/announcements` | Gửi thông báo và các thông báo cá nhân trong cùng transaction |
| GET `/announcements/{id}` | Nội dung, người gửi, số người nhận/đã đọc/chưa đọc |
| GET `/announcements/{id}/recipients` | Phân trang người nhận; `read_status=read/unread`, phòng lúc gửi và thời điểm đọc |

Danh sách hỗ trợ `page` và `per_page` (1–100, mặc định 15). Lượt gửi giới hạn 20 yêu cầu/phút theo tuyến hiện có.

Xem trước nhận JSON:

```json
{
  "title": "Lịch kiểm tra hệ thống nước",
  "body": "Sáng mai kiểm tra van nước từ 8 giờ đến 9 giờ. Xin cảm ơn.",
  "audience": "rooms",
  "room_ids": [1, 2]
}
```

Tiêu đề tối đa 160 ký tự; nội dung tối đa 8.000 ký tự, văn bản thuần. `room_ids` bắt buộc với phạm vi `rooms`, 1–100 ID khác nhau, tồn tại trong CSDL. Khi gửi, giữ nguyên dữ liệu đã xem trước và bổ sung `preview_token` cùng `idempotency_key` mới (UUID phù hợp). Thành công lần đầu trả 201, thử lại cùng lượt trả 200 và `replayed=true`.

Nếu nội dung, phòng hoặc người nhận thay đổi giữa xem trước và gửi, API trả 409 và yêu cầu xem trước lại. Khóa chống trùng là duy nhất, phân biệt hoa/thường; nội dung/người gửi khác không được dùng lại khóa. Hệ thống đối chiếu lượt đã ghi nhận trước khi tính lại danh sách cư trú, nên thử lại sau lỗi mạng vẫn trả đúng thông báo cũ kể cả khi người nhận đã chuyển/trả phòng. Lỗi ghi bất kỳ người nhận nào làm rollback toàn bộ lượt gửi.

Người thuê dùng GET `/api/tenant/announcements/{id}`. Đọc qua GET không thay đổi dữ liệu. Hộp thông báo dùng `type=owner_announcement`; đánh dấu đọc một/tất cả qua API thông báo hiện có. Chủ trọ nhìn thấy số đã đọc sau khi làm mới. Trạng thái này cho biết tài khoản đã mở hoặc đánh dấu đã đọc trong ứng dụng, không xác nhận đã thực hiện nội dung.

## Lưu trữ và nâng cấp

- Migration `2026_10_24_000001_create_announcements.php` thêm bảng `announcements` và cột `app_notifications.announcement_id`. Khóa ngoại và unique `(announcement_id, user_id)` giữ liên kết, chống tạo trùng người nhận.
- `app_notifications` vừa là thông báo cá nhân vừa lưu thời điểm đọc. Dữ liệu chứa tiêu đề, trích nội dung tối đa 500 ký tự và tên phòng lúc gửi; trang chi tiết lấy toàn văn từ `announcements`.
- Không tạo lại 16 bảng gốc. Migration đã áp dụng trên MySQL và bản demo riêng; ánh xạ hiện tại 22 bảng nghiệp vụ, 78 quan hệ, 3 view. Rollback bị chặn nếu đã có thông báo gửi để tránh mất lịch sử.
- Gửi trong ứng dụng, chưa có email/SMS hoặc lịch gửi. Chuông thông báo dùng cơ chế cập nhật hiện có: mỗi 60 giây khi tab hiển thị, khi quay lại tab và khi người dùng làm mới.

## Kiểm tra

`AnnouncementTest` có 9 ca/95 assertions: đúng người nhận kể cả thành viên, quyền riêng tư, xem trước cũ, thử lại sau chuyển/trả phòng, đọc/lọc/phân trang, người mới không nhận lịch sử, CSRF/phân quyền/validation, rollback lỗi gửi và nâng cấp SQLite giữ dữ liệu/ràng buộc unique cũ. Kiểm tra tích hợp MySQL đạt 15 bước; dữ liệu 22 bảng giữ nguyên sau rollback.

Hướng dẫn thao tác hai vai trò: [tự kiểm tra thông báo chung](../../frontend/docs/announcements-self-test.md).
