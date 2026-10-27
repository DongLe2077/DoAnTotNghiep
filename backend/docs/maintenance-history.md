# Lịch sử xử lý sửa chữa — 25/10/2026

Đã bổ sung nhật ký riêng cho mỗi yêu cầu báo hỏng, hiển thị trên trang chi tiết của chủ trọ và người gửi yêu cầu. Ghi chú hiện tại tiếp tục nằm trên yêu cầu; các trạng thái/ghi chú trước đó được giữ lại trong lịch sử.

## Quy tắc ghi nhận

- Yêu cầu mới do API tạo có sự kiện `submitted`, đúng người gửi và thời điểm gửi.
- Chủ trọ đổi trạng thái, sửa hoặc xóa ghi chú có sự kiện `updated`: trạng thái và ghi chú trước/sau, tài khoản thực hiện, thời điểm, revision. Tên đăng nhập và vai trò được chụp lúc thực hiện để việc đổi tên tài khoản sau đó không đổi lịch sử.
- Ghi chú được chia sẻ với chính người gửi yêu cầu, kể cả các ghi chú cũ. Xóa ghi chú hiện tại không xóa lịch sử trước đó.
- Thao tác không thay đổi dữ liệu không tạo sự kiện hoặc thông báo. Token cũ trả 409, yêu cầu đã hoàn tất giữ nguyên kết quả. Gửi lại sau mất phản hồi không ghi đè hoặc nhân đôi sự kiện; tải bản mới nhất để đối chiếu.
- Cập nhật yêu cầu, revision, nhật ký và thông báo nằm trong cùng giao dịch. Ghi nhật ký thất bại thì rollback cả thao tác. Thông tin người thực hiện lấy từ phiên đăng nhập và kiểm tra tài khoản chủ trọ hoạt động trong giao dịch.
- Upload ảnh tiếp tục làm thay đổi revision để ngăn ghi đè từ form cũ, nhưng không tạo sự kiện xử lý. Các revision trong nhật ký do đó có thể có khoảng cách.
- Nhật ký chỉ đọc qua API, không có thao tác sửa/xóa sự kiện. Chức năng này không tự ghi chi phí hoặc đổi trạng thái phòng.

## Dữ liệu cũ

Migration thêm một `baseline` cho mỗi yêu cầu có sẵn: trạng thái/ghi chú hiện có, thời điểm bắt đầu ghi nhận nhật ký; không sửa bản ghi yêu cầu gốc và không suy đoán tài khoản đã cập nhật trước đây. Không tạo các sự kiện tiếp nhận/hoàn tất giả cho quá khứ. Ngày gửi và lần cập nhật gần nhất của yêu cầu cũ vẫn được giữ nguyên.

Nếu có bản ghi nhập từ nguồn khác sau migration chưa có nhật ký, lần xử lý đầu tiên sẽ lưu mốc trạng thái trước đó trong cùng giao dịch rồi mới ghi cập nhật mới. GET lịch sử không tự tạo mốc hoặc sửa dữ liệu.

## API và quyền xem

| Phương thức | Đường dẫn | Quyền |
| --- | --- | --- |
| GET | `/api/owner/maintenance-requests/{id}/history` | Chủ trọ hoạt động |
| GET | `/api/tenant/maintenance-requests/{id}/history` | Chính tài khoản gửi yêu cầu |

Sắp xếp mới nhất trước theo ID, mặc định 10 mục/trang. Nhận `page >= 1`, `per_page` từ 1–50. Kết quả có `data`, `links`, `meta` phân trang và `history_context.baseline_recorded_at` (null nếu không có mốc dữ liệu cũ).

Mỗi mục có `id`, `action`, `revision`, `before/after.status`, `before/after.resolution`, `actor`, `created_at`. `before.status` có thể null ở lần gửi/mốc cũ. Mốc dữ liệu cũ có `actor=null`.

Chủ trọ thấy ID/tên đăng nhập/vai trò của người thực hiện ở thời điểm ghi. Người thuê thấy nhãn “Bạn” hoặc “Chủ trọ”, không nhận ID/tên đăng nhập tài khoản khác. Sau đổi đại diện/chuyển/trả phòng, tác giả gốc vẫn đọc lịch sử của mình; thành viên khác và đại diện mới không tự được cấp quyền (404). Các giới hạn này giống quyền xem báo hỏng/ảnh hiện có.

## CSDL và nâng cấp

Migration `2026_10_25_000001_create_maintenance_request_histories.php` thêm bảng `maintenance_request_histories`, FK đến `maintenance_requests` và `users`, unique `(maintenance_request_id, revision)`. Không thêm/sửa cột hoặc tạo lại các bảng cũ. CSDL hiện có 23 bảng nghiệp vụ, 81 quan hệ Eloquent, 3 view.

Đã sao lưu trước khi nâng cấp MySQL và demo SQLite, đối chiếu dữ liệu cũ giữ nguyên. Migration rollback bị chặn khi đã có lịch sử để tránh mất nhật ký; việc phục hồi cần dùng bản sao và quy trình có kiểm soát.

Kiểm thử backend bao gồm sự kiện khởi tạo, gửi trùng, đổi trạng thái/ghi chú/xóa ghi chú, snapshot tài khoản, phiên bản cũ, quyền sau trả phòng, giả mạo actor, phân trang, migration dữ liệu cũ và rollback khi ghi nhật ký lỗi. Kiểm tra MySQL chạy trong giao dịch rollback, 14 bước đạt, dữ liệu 23 bảng giữ nguyên.

[Hướng dẫn thử trên giao diện](../../frontend/docs/maintenance-history-self-test.md).

Nghiệm thu cuối 25/10/2026: toàn bộ backend 336/336 (3.428 assertions), frontend 119/119, bản đóng gói TypeScript/Vite và Pint đạt; 25/25 kịch bản Chrome cho lịch sử/sửa chữa/người thuê/thông báo đạt (4,6 phút). Đã xem ảnh chụp dòng thời gian máy tính và điện thoại. Các ca trình duyệt dùng SQLite riêng; kiểm tra MySQL nêu trên rollback toàn bộ dữ liệu thử.
