# Báo hỏng và sửa chữa

Đã có API để người đại diện đang cư trú gửi báo hỏng, chủ trọ tiếp nhận/cập nhật kết quả, và người gửi theo dõi yêu cầu. Dùng bảng `maintenance_requests` cùng lịch sử cư trú/đại diện hiện có; không thay đổi cấu trúc CSDL.

## Vai trò và quyền xem

- Người gửi phải có tài khoản `tenant` hoạt động, đang ở và đang làm đại diện của đúng đợt thuê chưa kết thúc.
- Người thuê chỉ xem các yêu cầu do chính tài khoản mình gửi. Không được xem yêu cầu của thành viên khác, nhóm khác hoặc người đại diện cũ.
- Sau khi đổi đại diện, chuyển/trả phòng, người gửi vẫn đọc được yêu cầu cũ nếu tài khoản còn hoạt động; không còn quyền gửi báo hỏng cho đợt thuê cũ đã đóng hoặc đã thôi đại diện.
- Chủ trọ có tài khoản `owner` hoạt động xem toàn bộ, cập nhật tiến độ và hoàn tất, kể cả sau khi nhóm thuê đã chuyển/trả phòng.

Đăng nhập, giữ cookie và gửi CSRF cho POST/PUT/PATCH theo [hướng dẫn đăng nhập](authentication.md). Các API nhận/trả JSON. Chủ trọ không giả danh người thuê để gửi yêu cầu qua API của người thuê.

## API người thuê

| Phương thức | Đường dẫn | Chức năng |
| --- | --- | --- |
| GET | `/api/tenant/maintenance-requests/eligible-occupancies` | Đợt thuê hiện được phép báo hỏng |
| POST | `/api/tenant/occupancies/{occupancy}/maintenance-requests` | Gửi báo hỏng cho đợt thuê được phép |
| GET | `/api/tenant/maintenance-requests` | Danh sách yêu cầu do mình gửi |
| GET | `/api/tenant/maintenance-requests/{maintenanceRequest}` | Chi tiết và kết quả của yêu cầu do mình gửi |

Đọc `eligible-occupancies` để lấy `occupancy_id`, số phòng và nhóm thuê của mình. Không có đợt đủ điều kiện thì `data=[]`; không cần thử ID của các nhóm khác.

Ví dụ gửi:

```json
{
  "title": "Vòi nước nhà vệ sinh bị rò",
  "description": "Vòi ở bồn rửa rò liên tục dù đã khóa. Nhờ chủ trọ kiểm tra và sửa giúp."
}
```

`title` bắt buộc, tối đa 150 ký tự; `description` bắt buộc, tối đa 8.000 ký tự. Chỉ gửi hai trường này. Đợt thuê lấy từ URL; người gửi, khoảng đại diện và thời gian được lấy từ tài khoản/lịch sử hiện tại và máy chủ. Không gửi `occupancy_id`, `submitted_by`, `representative_assignment_id`, `status`, `resolution`, `submitted_at`, `updated_at` để ghi đè.

Thành công trả 201, `replayed=false`, `data.status=submitted`. Response có ID, tiêu đề/mô tả, phòng/nhóm, đợt thuê, người gửi, khoảng đại diện gốc, trạng thái/nhãn trạng thái, nội dung xử lý và thời gian gửi/cập nhật. Không trả thông tin tài khoản nhạy cảm hoặc token cập nhật của chủ trọ.

Nếu cùng tài khoản gửi lại **đúng tiêu đề và mô tả** cho cùng đợt thuê và yêu cầu cũ còn `submitted` hoặc `in_progress`, trả 200, `replayed=true` cùng ID cũ; không tạo thêm yêu cầu. Việc gửi lại vẫn cần còn quyền báo hỏng tại thời điểm gọi API. Nội dung khác hoặc yêu cầu cũ đã hoàn tất thì được tạo yêu cầu mới. Nếu mất phản hồi rồi đã chuyển/trả phòng, đọc danh sách của mình để kiểm tra yêu cầu đã gửi.

Người thuê thường, đại diện của nhóm khác, đại diện đã thôi vai trò, người đã ra khỏi đợt thuê hoặc đợt đã đóng không gửi mới được. API trả 404 với đợt thuê không thuộc phạm vi được phép. Chi tiết yêu cầu không thuộc người gửi và ID không tồn tại đều trả 404.

## API chủ trọ

| Phương thức | Đường dẫn | Chức năng |
| --- | --- | --- |
| GET | `/api/owner/maintenance-requests` | Danh sách yêu cầu |
| GET | `/api/owner/maintenance-requests/{maintenanceRequest}` | Chi tiết, người gửi và phiên bản hiện tại |
| PUT/PATCH | `/api/owner/maintenance-requests/{maintenanceRequest}` | Tiếp nhận, cập nhật tiến độ hoặc hoàn tất |

Chi tiết cho chủ trọ có thêm `reporter` (ID hồ sơ, họ tên, điện thoại), `can_process` và `version_token`. Token cần lấy từ response gần nhất và gửi khi cập nhật; không dùng token của yêu cầu khác.

Tiếp nhận hoặc ghi tiến độ:

```json
{
  "version_token": "<64 ký tự nhận được từ API>",
  "status": "in_progress",
  "resolution": "Đã liên hệ thợ, dự kiến kiểm tra chiều nay."
}
```

`resolution` có thể bỏ qua khi tiếp nhận; bỏ qua giữ nội dung đang có, gửi null sẽ xóa ghi chú tiến độ. Nội dung xử lý được hiển thị cho người gửi, không phải ghi chú nội bộ riêng của chủ trọ.

Hoàn tất:

```json
{
  "version_token": "<token hiện tại>",
  "status": "resolved",
  "resolution": "Đã thay gioăng và kiểm tra, vòi không còn rò nước."
}
```

Kết quả khi hoàn tất bắt buộc không rỗng, tối đa 8.000 ký tự. Có thể hoàn tất trực tiếp từ `submitted` nếu đã xử lý xong ngay. Chỉ nhận `in_progress` hoặc `resolved` trong cập nhật của chủ trọ; không đưa yêu cầu trở về `submitted` và không có trạng thái `cancelled` trong CSDL hiện tại.

| Trạng thái | Ý nghĩa | Cập nhật tiếp |
| --- | --- | --- |
| `submitted` | Đã gửi | `in_progress` hoặc `resolved` |
| `in_progress` | Đang xử lý | Cập nhật tiến độ hoặc `resolved` |
| `resolved` | Đã xử lý | Giữ nguyên kết quả; sự cố tái phát gửi yêu cầu mới |

Cập nhật thành công trả 200 với token mới. Token sai định dạng/thiếu trả 422; token đã cũ hoặc thuộc yêu cầu khác trả 409 và cần đọc lại. Việc kiểm tra thực hiện dưới khóa bản ghi trong transaction để tránh ghi đè khi nhiều phiên cùng xử lý.

Chủ trọ không được sửa người gửi, đợt thuê, đại diện gốc, tiêu đề/mô tả gốc hoặc các mốc thời gian. Không có API xóa yêu cầu hoặc sửa lại yêu cầu đã hoàn tất. Bảng chính lưu trạng thái/nội dung gần nhất; từ 25/10/2026 đã có [nhật ký từng lần cập nhật](maintenance-history.md), gồm trạng thái/ghi chú trước sau, tài khoản thực hiện và thời điểm. Dữ liệu cũ có mốc trạng thái hiện có, không dựng lại quá khứ.

## Lọc và phân trang

Hai danh sách hỗ trợ:

| Tham số | Ý nghĩa |
| --- | --- |
| `status` | `submitted`, `in_progress` hoặc `resolved` |
| `occupancy_id`, `room_id`, `rental_group_id` | Đợt thuê, phòng, nhóm thuê |
| `q` | Tìm trong tiêu đề, mô tả hoặc nội dung xử lý; tối đa 150 ký tự |
| `from`, `until` | Thời gian gửi từ/đến, bao gồm hai mốc, định dạng `Y-m-d H:i:s` |
| `page`, `per_page` | Trang, số bản ghi; mặc định 15, tối đa 100 |

Danh sách chủ trọ có thêm `submitted_by` để lọc người gửi. Người thuê luôn bị giới hạn bởi tài khoản mình trước khi lọc; query không mở rộng quyền xem. Sắp theo thời gian gửi mới nhất, rồi ID mới nhất; response có `data`, `links`, `meta`.

Các mốc thời gian theo `Asia/Ho_Chi_Minh`. Có thể chỉ gửi một mốc; nếu có cả hai, `until` phải từ `from` trở đi. Không gửi thời gian thì đọc toàn bộ thời gian trong phạm vi quyền và phân trang. Tìm kiếm coi `%`, `_`, `!` là ký tự nội dung.

## Liên quan đến quá trình thuê, phòng và chi phí

- Yêu cầu giữ nguyên đợt thuê, phòng và khoảng đại diện lúc gửi; đổi đại diện/chuyển phòng không chuyển yêu cầu cũ sang người/phòng mới.
- Chủ trọ vẫn xử lý yêu cầu cũ sau trả/chuyển phòng; tác giả vẫn thấy kết quả. Đại diện mới không tự nhận quyền xem các báo hỏng của tài khoản khác.
- [Quá trình thuê](tenant-occupancy.md) chặn chuyển/trả phòng lùi trước thời điểm đã gửi yêu cầu để giữ đúng lịch sử. Thời gian xử lý về sau không đổi thời gian gửi gốc.
- Gửi báo hỏng hoặc hoàn tất không tự đặt/bỏ trạng thái bảo trì phòng. Chủ trọ dùng [quản lý phòng](rooms-services.md) khi cần thay đổi trạng thái vận hành.
- Không tự tạo hóa đơn hoặc chi phí từ trạng thái sửa chữa. Khoản chi thực tế ghi riêng qua [quản lý thu chi](cash-book.md), có thể ghi mã yêu cầu trong mô tả để đối chiếu.
- Đã có [ảnh riêng tư và thông báo trong ứng dụng](audit-photos-notifications.md), giao diện chủ trọ xem ảnh/cập nhật/hoàn tất, cùng [giao diện người thuê gửi báo hỏng/ảnh và theo dõi](../../frontend/docs/tenant-self-test.md). Tạo yêu cầu trước rồi tải ảnh; ảnh lỗi thử lại riêng, không tạo lại nội dung báo hỏng. Chưa có phân công thợ hoặc thông báo email/SMS/Zalo.

## Kiểm thử

```powershell
php artisan test --filter=MaintenanceManagementTest
php artisan test
php artisan rental:verify-database
```

PHPUnit dùng SQLite trong bộ nhớ. Các ca gồm đại diện hợp lệ, phòng/nhóm khác, nội dung sai/giả mạo, yêu cầu trùng đang mở, cập nhật/hoàn tất, token cũ, phân quyền/CSRF, bộ lọc và khả năng xem/xử lý sau đổi đại diện, chuyển/trả phòng. Phần ảnh/thông báo được bổ sung bằng migration, không xóa dữ liệu nghiệp vụ cũ.
