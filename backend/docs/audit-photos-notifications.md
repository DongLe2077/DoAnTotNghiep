# Nhật ký thu chi, ảnh báo hỏng và thông báo

Đã bổ sung backend ngày 20/10/2026. Dùng cùng cookie session, CSRF và phân quyền hiện có. Frontend chưa được triển khai.

## CSDL và nâng cấp

Đã áp dụng 3 migration bổ sung trên MySQL hiện tại, giữ nguyên dữ liệu trong các cột cũ của 16 bảng:

- `cash_transaction_histories`: nhật ký thao tác, ảnh chụp trước/sau, người thực hiện, lý do, thao tác được hoàn tác.
- `maintenance_photos`: thông tin tệp ảnh riêng tư của yêu cầu báo hỏng.
- `app_notifications`: hộp thông báo cá nhân và khóa chống thông báo trùng.
- `cash_transactions` thêm `revision` (mặc định 0 cho dữ liệu cũ), `deleted_at` để xóa có thể khôi phục; `maintenance_requests` thêm `revision`.

Đợt nhật ký/ảnh/thông báo đưa CSDL lên 19 bảng nghiệp vụ. Sau khi thêm [chống gửi trùng thu chi](cash-idempotency.md), hiện có 20 bảng nghiệp vụ/20 model/72 quan hệ, 3 view cũ, cộng bảng hạ tầng `migrations`. Bản chụp schema đã cập nhật; `rental:verify-database` không coi bảng migration là model nghiệp vụ.

Trên máy khác đã có 16 bảng gốc, chạy `php artisan migrate`, sau đó `php artisan rental:verify-database`. Không dùng `migrate:fresh`, `refresh`, `reset`, `db:wipe` hoặc rollback migration trên dữ liệu đang sử dụng. Migration này bổ sung schema, không tạo lại 16 bảng gốc.

## Nhật ký và hoàn tác thu chi

Các API chỉ dành cho chủ trọ:

| Phương thức | Đường dẫn | Ý nghĩa |
| --- | --- | --- |
| GET | `/api/owner/cash-transactions/{id}/history` | Nhật ký phân trang, mới nhất trước; `current` chứa dữ liệu và token hiện tại kể cả đã xóa |
| POST | `/api/owner/cash-transactions/{id}/revert` | Hoàn tác thao tác mới nhất bằng `history_id`, `version_token`, `reason` |
| GET | `/api/owner/cash-transactions?state=deleted` | Các khoản đã xóa; `state=all` gồm cả đang dùng/đã xóa, mặc định `active` |

Thêm/sửa/xóa vẫn dùng API cũ, có thể bổ sung `reason` (tối đa 1.000 ký tự). Sửa/xóa vẫn yêu cầu token. Response khoản thu chi thêm `revision`, `is_deleted`, `deleted_at`. Xóa trả 204 nhưng giữ bản ghi; các sổ/tổng hợp/dashboard loại khoản đã xóa ra khỏi số tiền đang ghi nhận.

Ví dụ hoàn tác sau khi đọc nhật ký:

```json
{
  "history_id": 123,
  "version_token": "<current.version_token>",
  "reason": "Khôi phục khoản bị xóa nhầm"
}
```

Chỉ hoàn tác sự kiện mới nhất của đúng khoản và đúng phiên bản. Token cũ hoặc chọn sự kiện cũ trả 409; cần tải lại để tránh ghi đè thay đổi của người khác. Hoàn tác sửa sẽ lấy lại dữ liệu trước sửa; hoàn tác xóa sẽ khôi phục khoản; hoàn tác tạo sẽ xóa có thể khôi phục. Bản thân hoàn tác cũng sinh sự kiện `reverted`, nên có thể hoàn tác thao tác vừa hoàn tác. Đây là thao tác đảo sự kiện mới nhất, không phải chọn tùy ý một phiên bản cũ để ghi đè tất cả các bước sau.

Nhật ký có `action` (`created`, `updated`, `deleted`, `reverted`), `revision`, `before`, `after`, `performed_by`, `actor`, `reason`, `reverted_history_id`, `created_at`. Người tạo/thời gian tạo gốc luôn giữ nguyên; người sửa/hoàn tác lưu riêng. Thay đổi và ghi nhật ký nằm trong cùng transaction: lỗi nhật ký thì thay đổi tài chính cũng được hoàn tác. Lưu phiên bản tăng dần nên token cũ vẫn bị chặn khi nội dung quay về giá trị cũ trong cùng giây. Sửa không đổi nội dung không tạo thêm phiên bản.

Nhật ký bắt đầu từ lúc nâng cấp; bản ghi cũ có thể sửa/xóa và hoàn tác từ lần thay đổi đầu tiên sau nâng cấp. Không thể phục hồi những khoản đã bị xóa thật trước khi có nhật ký. Không có API sửa/xóa nhật ký; cơ chế này theo dõi thao tác qua ứng dụng, không thay thế kiểm soát truy cập trực tiếp CSDL. Không chỉnh sửa biên nhận thanh toán hóa đơn qua chức năng này. POST thu chi đã có [mã chống gửi trùng](cash-idempotency.md), giữ ổn định cả sau sửa/xóa/hoàn tác.

## Ảnh báo hỏng

Người gửi tạo yêu cầu bằng API hiện có, sau đó tải ảnh bằng multipart form:

| Phương thức | Đường dẫn | Quyền |
| --- | --- | --- |
| POST | `/api/tenant/maintenance-requests/{id}/photos` | Người đã gửi yêu cầu, tài khoản tenant hoạt động, yêu cầu chưa hoàn tất |
| GET | `/api/tenant/maintenance-requests/{id}/photos/{photo}` | Người đã gửi yêu cầu |
| GET | `/api/owner/maintenance-requests/{id}/photos/{photo}` | Chủ trọ |

Tên trường `photos[]`, tối đa 5 ảnh khác nhau cho toàn bộ yêu cầu (không chỉ mỗi lần tải), mỗi ảnh tối đa 5 MB, kích thước tối đa 4.096 × 4.096 pixel. Chỉ JPEG/PNG/WebP; kiểm tra nội dung và đuôi tệp, không nhận SVG, ảnh giả hoặc tệp thực thi. PHP hiện cấu hình upload/post tối đa 40 MB, đủ 5 ảnh; máy triển khai cần giữ giới hạn máy chủ phù hợp.

Ảnh lưu tại `storage/app/private/maintenance` qua disk `maintenance`, tên ngẫu nhiên, không đưa qua `public/storage`. Response chỉ trả id, MIME, dung lượng, thời gian và URL API; mọi lần xem đều cần đúng quyền. Sao lưu cả CSDL và thư mục ảnh riêng tư. API chi tiết/danh sách báo hỏng có thêm `photos`.

Tải lại cùng nội dung ảnh vào cùng yêu cầu trả ảnh đã có, không nhân bản hoặc tiêu thêm lượt. Nếu ghi metadata/thao tác thất bại thì rollback CSDL và dọn tệp vừa ghi. Việc ghi tệp và CSDL có bù trừ lỗi, không phải giao dịch nguyên tử giữa hai hệ thống; sự cố dừng tiến trình đột ngột vẫn cần đối chiếu tệp mồ côi khi vận hành.

Người gửi vẫn có thể bổ sung ảnh cho yêu cầu đang mở sau khi rời phòng, vì quyền dựa vào người gửi gốc; người đại diện mới không được xem hoặc thêm ảnh yêu cầu của người khác. Yêu cầu hoàn tất giữ ảnh xem được nhưng không nhận thêm. Không có API sửa/xóa ảnh bằng chứng. Tải ảnh mới làm đổi phiên bản yêu cầu; chủ trọ cần tải lại token trước khi xử lý.

## Thông báo trong ứng dụng

Ngày 24/10/2026 bổ sung `owner_announcement`: chủ trọ gửi toàn bộ/theo phòng, người nhận được chốt lúc gửi, dùng chung hộp thư và API đánh dấu đọc. Người mới vào ở không nhận lịch sử; người đã nhận giữ quyền đọc sau chuyển/trả phòng. [Quy tắc và API thông báo chung](announcements.md).

- `maintenance_submitted`: báo hỏng mới gửi cho các chủ trọ đang hoạt động. Yêu cầu trùng đang mở không tạo thông báo mới.
- `maintenance_updated`: tiến độ, ghi chú và hoàn tất gửi đúng người báo hỏng gốc, kể cả đã trả phòng. Thao tác không đổi nội dung không tạo thông báo.
- `invoice_reminder`: nhắc khoản hóa đơn chưa thanh toán đủ cho người đang có **grant xem hóa đơn**, không tự chuyển cho đại diện mới. Cần grant hợp lệ và tài khoản hoạt động. Mỗi hóa đơn/người nhận/ngày tối đa một thông báo, kể cả thông báo đã đọc.

| Phương thức | Đường dẫn | Nội dung |
| --- | --- | --- |
| GET | `/api/notifications` | Hộp thông báo cá nhân, mới nhất trước |
| GET | `/api/notifications/unread-count` | Số thông báo còn hiển thị chưa đọc |
| PATCH | `/api/notifications/{id}/read` | Đánh dấu một thông báo của mình; gửi lại không đổi thời điểm đã đọc |
| POST | `/api/notifications/read-all` | Đánh dấu tất cả thông báo hiện có quyền xem |
| POST | `/api/owner/invoices/{id}/reminder` | Chủ trọ nhắc một hóa đơn; 201 tạo mới, 200 đã nhắc hôm nay, 409 không có nợ/grant hợp lệ |

Lọc hộp thư bằng `unread_only=1`, `type`, `page`, `per_page` (tối đa 100). Thông báo trả id, loại, `data.title/body` và trạng thái sửa chữa nếu có, mã đối tượng, thời gian tạo/đọc. Giao diện hiển thị nội dung như văn bản. Không trả khóa chống trùng hoặc thông tin đăng nhập người khác.

Thông báo hóa đơn được lọc lại quyền ở mỗi lần truy cập và đếm chưa đọc: thu hồi grant, đổi người được cấp quyền, thanh toán đủ hoặc hóa đơn bất thường thì thông báo cũ không còn hiện. Hộp thư không được dùng để lấy lại nội dung hóa đơn đã bị thu hồi quyền. Người khác không đọc/đánh dấu được thông báo; tài khoản khóa bị chặn như các API khác.

### Nhắc tự động

Đã khai báo lịch trong `routes/console.php`: chạy mỗi ngày 08:00 theo `Asia/Ho_Chi_Minh`, chống chạy chồng. Để lịch thực thi, máy chạy backend phải có tiến trình lập lịch Laravel hoạt động; chỉ `artisan serve` không chạy lịch này. Có thể mở terminal riêng khi phát triển:

```powershell
cd D:\KLTN\backend
php artisan schedule:work
```

Khi triển khai, quản lý tiến trình lập lịch bằng dịch vụ hoặc bộ lập lịch hệ điều hành. Chưa tạo tác vụ Windows hoặc tự chạy tiến trình thường trực trong lần bổ sung này. Có thể chạy/kiểm tra thủ công:

```powershell
php artisan rental:remind-invoices --dry-run
php artisan rental:remind-invoices
php artisan rental:remind-invoices --billing-month=2026-01
```

`--dry-run` chỉ đếm hóa đơn ứng viên có nợ, không ghi thông báo và chưa loại ứng viên thiếu grant; khi chạy thật sẽ đếm riêng trường hợp bỏ qua. Mặc định nhắc mọi tháng còn nợ; chưa gọi là quá hạn vì chưa có ngày đến hạn. Thông báo là trong ứng dụng, chưa gửi email/SMS/Zalo hoặc đẩy thời gian thực; frontend lấy danh sách/số chưa đọc khi tải trang hoặc định kỳ.

## Kiểm thử

`CashTransactionHistoryTest`, `MaintenancePhotosTest`, `AppNotificationsTest` kiểm tra hoàn tác/xóa/khôi phục, phiên bản cũ, lỗi ghi nhật ký, tệp giả/giới hạn/dọn tệp, quyền xem và CSRF, chống thông báo trùng, quyền bị thu hồi và hóa đơn đã trả. Kiểm tra tích hợp MySQL cũng chạy trong transaction hoàn tác, đối chiếu dữ liệu 19 bảng và dọn ảnh thử.

Kết quả ngày 20/10/2026: 279 kiểm thử/2.660 assertions đạt, 21 bước tích hợp MySQL đạt, Pint đạt; kiểm tra ánh xạ đủ 19 bảng nghiệp vụ và 70 quan hệ.
