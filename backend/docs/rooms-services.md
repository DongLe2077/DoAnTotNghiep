# Quản lý phòng và dịch vụ

Đã triển khai API backend. Các đường dẫn dưới đây yêu cầu phiên đăng nhập của chủ trọ `owner`, tài khoản còn hoạt động. Người thuê bị chặn với HTTP 403; request ghi dữ liệu cần CSRF như hướng dẫn [đăng nhập](authentication.md). Frontend riêng chưa được xây ở bước này.

## Phòng

| Phương thức | Đường dẫn | Chức năng |
| --- | --- | --- |
| GET | `/api/owner/rooms` | Danh sách, tìm kiếm, lọc và phân trang |
| POST | `/api/owner/rooms` | Thêm phòng |
| GET | `/api/owner/rooms/{id}` | Chi tiết phòng và số người đang ở |
| PUT/PATCH | `/api/owner/rooms/{id}` | Sửa số phòng, sức chứa, trạng thái vận hành |
| DELETE | `/api/owner/rooms/{id}` | Xóa phòng chưa từng có lịch sử thuê |
| GET | `/api/owner/rooms/rental-rate` | Xem giá thuê mặc định chung |
| PUT | `/api/owner/rooms/rental-rate` | Đổi giá thuê mặc định cho toàn bộ phòng |

Thêm phòng đầu tiên:

```json
{
  "room_number": "A101",
  "capacity": 4,
  "default_monthly_rent": "3000000.00",
  "operational_status": "available"
}
```

Các phòng thêm sau có thể bỏ `default_monthly_rent`; backend dùng giá chung hiện có. Nếu gửi giá khác giá chung, API trả 422. Quy tắc giá chung lấy từ thiết kế CSDL hiện có (`rooms.default_monthly_rent`); các phòng hiện tại có cùng mức giá.

Ví dụ đổi giá chung:

```http
PUT /api/owner/rooms/rental-rate
Content-Type: application/json
X-XSRF-TOKEN: <token hiện tại>

{"default_monthly_rent":"3500000.00"}
```

Thao tác này chỉ cập nhật `rooms.default_monthly_rent`. Giá `occupancies.monthly_rent` đã chốt theo từng đợt thuê được giữ nguyên. Nếu CSDL cũ có giá mặc định không đồng nhất, endpoint giá chung có thể đưa các phòng về cùng một giá. Khi chưa có phòng, tạo phòng đầu tiên kèm giá trước.

Các trường phòng:

- `room_number`: bắt buộc khi tạo, tối đa 30 ký tự, duy nhất.
- `capacity`: số nguyên từ 1 đến 65535; không được giảm xuống dưới số người hiện đang ở.
- `default_monthly_rent`: không âm, tối đa 16 chữ số nguyên và 2 chữ số thập phân. Nên gửi dạng chuỗi để giữ độ chính xác.
- `operational_status`: `available` hoặc `maintenance`; bỏ khi tạo thì dùng `available`.
- `resident_count` và `occupancy_status`: backend lấy từ view `room_occupancy_summary`; client không được ghi đè.
- `can_delete`: chỉ true khi phòng chưa từng được tham chiếu trong `occupancies`.

Theo view hiện tại, phòng bảo trì có `occupancy_status=maintenance`; còn lại, có người đang ở thì `occupied`, chưa có người đang ở thì `vacant`. Chỉ tính thành viên chưa ra khỏi đợt thuê chưa kết thúc. Một đợt thuê đang mở nhưng chưa có thành viên có thể vẫn hiện `vacant`; bước nhận phòng cần kiểm tra cả đợt thuê đang mở.

Đặt trạng thái bảo trì không tự kết thúc đợt thuê hoặc chuyển người thuê đi. Phòng đã có lịch sử thuê trả 409 khi yêu cầu xóa, để giữ dữ liệu hóa đơn và cư trú liên quan.

Query danh sách: `q`, `operational_status`, `occupancy_status`, `page`, `per_page` (mặc định 15, tối đa 100). Ví dụ:

```http
GET /api/owner/rooms?q=A1&occupancy_status=vacant&per_page=15&page=1
```

## Danh mục dịch vụ

| Phương thức | Đường dẫn | Chức năng |
| --- | --- | --- |
| GET | `/api/owner/services` | Danh sách, tìm theo mã/tên, lọc và phân trang |
| POST | `/api/owner/services` | Thêm dịch vụ |
| GET | `/api/owner/services/{id}` | Chi tiết dịch vụ |
| PUT/PATCH | `/api/owner/services/{id}` | Sửa thông tin, đơn giá mặc định, bật/tắt dịch vụ |
| DELETE | `/api/owner/services/{id}` | Xóa dịch vụ chưa từng được sử dụng |

Ví dụ thêm dịch vụ điện:

```json
{
  "service_code": "ELECTRIC",
  "name": "Điện",
  "unit": "kwh",
  "billing_method": "metered",
  "default_unit_price": "3500.0000",
  "is_active": true
}
```

- `service_code`: tối đa 40 ký tự, duy nhất; `name` tối đa 100; `unit` tối đa 30. Các trường này không được rỗng.
- `billing_method`: `metered` (tính theo chỉ số) hoặc `per_room` (tính theo phòng).
- `default_unit_price`: không âm, tối đa 14 chữ số nguyên và 4 chữ số thập phân; nên gửi dạng chuỗi.
- `is_active`: mặc định true; gửi PATCH với false để ngừng áp dụng mới.
- Khi dịch vụ đã xuất hiện trong `occupancy_services`, API chặn xóa với 409, kể cả chỉ còn lịch sử cũ.

Đổi đơn giá, đơn vị hoặc cách tính trong danh mục không sửa các bản ghi `occupancy_services`. Những bản ghi đó giữ cấu hình và đơn giá theo khoảng thời gian đã áp dụng. Tắt danh mục không tự đóng các khoảng áp dụng hiện tại. Chức năng gán dịch vụ và thay đổi giá theo thời điểm của từng đợt thuê đã có trong [API quá trình thuê](tenant-occupancy.md).

Query danh sách: `q`, `billing_method`, `is_active=1|0`, `page`, `per_page`. Ví dụ:

```http
GET /api/owner/services?billing_method=metered&is_active=1
```

Danh sách trả JSON `data`, `links`, `meta` theo phân trang Laravel. Thêm thành công trả 201, cập nhật/xem trả 200, xóa trả 204, dữ liệu sai trả 422, không tìm thấy trả 404, xung đột lịch sử trả 409. PUT/PATCH đều chỉ cập nhật các trường hợp lệ được gửi lên.

## Kiểm thử và ràng buộc

```powershell
php artisan test --filter=RoomServiceManagementTest
php artisan test
php artisan rental:verify-database
```

Kiểm thử dùng CSDL SQLite trong bộ nhớ riêng. Cấu trúc 16 bảng trên MySQL được giữ nguyên. Backend khóa các phòng theo cùng thứ tự trong transaction khi tạo phòng hoặc cập nhật giá chung. Các API nhận/chuyển phòng sau này cần cùng khóa bản ghi phòng trước khi thay đổi số người ở để phối hợp với kiểm tra sức chứa.

Tài liệu tham chiếu: [Validation](https://laravel.com/docs/12.x/validation), [API Resources](https://laravel.com/docs/12.x/eloquent-resources).
