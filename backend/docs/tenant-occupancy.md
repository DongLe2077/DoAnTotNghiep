# API người thuê và quá trình thuê

Đã triển khai backend trên các bảng nghiệp vụ hiện có và [giao diện CT2–CT3](../../frontend/docs/owner-management.md); không thêm bảng cho gói giao diện. Tất cả API dưới đây thuộc `/api/owner`, yêu cầu tài khoản `owner` đang hoạt động. Giữ session cookie và gửi CSRF cho request ghi theo [hướng dẫn đăng nhập](authentication.md).

## Hồ sơ và tài khoản

| Phương thức | Đường dẫn sau `/api/owner` | Chức năng |
| --- | --- | --- |
| GET/POST | `/tenants` | Danh sách hoặc tạo hồ sơ |
| GET/PUT/PATCH/DELETE | `/tenants/{id}` | Xem, sửa, xóa hồ sơ chưa có tài khoản/lịch sử |
| PUT | `/tenants/{id}/account` | Tạo/cập nhật tài khoản đăng nhập của hồ sơ |
| GET | `/tenants/{id}/stays` | Lịch sử cư trú, gồm cả đợt đã kết thúc |

Hồ sơ gồm `full_name` (tối đa 150 ký tự), `phone_number` (chuỗi, tối đa 20), `identity_number` (không bắt buộc, tối đa 30, duy nhất khi có giá trị). Số điện thoại phải gửi dạng chuỗi để giữ số 0 đầu.

Danh sách hỗ trợ `q` tìm họ tên/SĐT/CCCD, `is_residing=1|0`, `page`, `per_page` (mặc định 15, tối đa 100). Response có `account` và `current_stay` nếu có; không trả hash mật khẩu. Tạo hồ sơ không tự tạo tài khoản.

Ví dụ tạo hồ sơ:

```json
{"full_name":"Nguyễn Văn Nam","phone_number":"0900123456","identity_number":"012345678901"}
```

Chỉ khi Nam cần đăng nhập mới gọi `PUT /tenants/{id}/account`:

```json
{
  "username":"nam_demo",
  "password":"YourChosenPassword123!",
  "password_confirmation":"YourChosenPassword123!",
  "is_active":true
}
```

Đây là dữ liệu minh họa, không phải tài khoản đã được tạo. Khi cập nhật tài khoản đang có, chỉ cần gửi trường muốn đổi; nếu đổi mật khẩu phải gửi xác nhận. Mật khẩu từ 8 ký tự, tối đa 72 byte theo bcrypt hiện tại, được hash trước khi lưu.

Role luôn là `tenant`, liên kết tới đúng hồ sơ trên URL; payload không được nâng quyền hay đổi liên kết sang hồ sơ khác. Không dùng endpoint này để đổi tài khoản chủ trọ. Trước khi khóa tài khoản đang là đại diện, cần đổi đại diện hoặc trả phòng.

## Nhóm thuê

| Phương thức | Đường dẫn | Chức năng |
| --- | --- | --- |
| GET/POST | `/rental-groups` | Danh sách hoặc tạo nhóm |
| GET/PUT/PATCH/DELETE | `/rental-groups/{id}` | Xem, đổi mã nhóm, xóa nhóm chưa có lịch sử |

Tạo nhóm bằng `{"group_code":"NHOM-NAM"}`. Mã tối đa 40 ký tự, duy nhất. Nhóm có thể chỉ gồm một người; thành viên được ghi theo từng đợt ở phòng. Nhóm đã có đợt thuê không bị xóa. Danh sách hỗ trợ `q`, `page`, `per_page`.

## Nhận phòng và lịch sử

| Phương thức | Đường dẫn | Chức năng |
| --- | --- | --- |
| GET | `/occupancies` | Danh sách đợt thuê |
| POST | `/occupancies` | Nhận phòng, tạo một đợt thuê |
| GET | `/occupancies/{id}` | Chi tiết, thành viên, đại diện, các khoảng dịch vụ |
| POST | `/occupancies/{id}/members` | Thêm thành viên |
| POST | `/occupancies/{id}/members/{stay_id}/leave` | Ghi một thành viên rời đi |
| POST | `/occupancies/{id}/representative` | Chọn/đổi người đại diện |
| POST | `/occupancies/{id}/services` | Áp dụng dịch vụ hoặc mở khoảng giá mới |
| POST | `/occupancies/{id}/services/{period_id}/end` | Ngừng một khoảng áp dụng dịch vụ |
| POST | `/occupancies/{id}/transfer` | Chuyển cả nhóm sang phòng khác |
| POST | `/occupancies/{id}/checkout` | Trả phòng và kết thúc đợt thuê |

Không có API sửa trực tiếp/xóa đợt thuê, thành viên cũ hoặc khoảng dịch vụ cũ. Các thay đổi đi qua thao tác nghiệp vụ để giữ lịch sử và ràng buộc.

Ví dụ phòng một người, giả sử hồ sơ Nam có ID 10, nhóm ID 7 và phòng ID 2:

```json
{
  "rental_group_id":7,
  "room_id":2,
  "tenant_ids":[10],
  "started_at":"2026-10-18 09:00:00",
  "representative_tenant_id":10
}
```

Các ID/thời gian trên chỉ minh họa; dùng ID thực tế và thời gian sự kiện đã xảy ra. Có thể bỏ `representative_tenant_id` khi chưa có tài khoản đại diện; chọn sau qua API đại diện. Nếu gửi trường này thì người được chọn phải là thành viên và có tài khoản `tenant` đang hoạt động. Giá `monthly_rent` do backend chốt từ giá mặc định phòng, không nhập trực tiếp.

Nhận phòng bị chặn khi phòng bảo trì, đã có đợt thuê chưa kết thúc (kể cả chưa có người), quá sức chứa, nhóm đang ở phòng khác hoặc người thuê có khoảng cư trú trùng thời gian. Sau trả phòng, có thể bắt đầu đợt mới ngay tại mốc kết thúc trước đó.

Danh sách đợt thuê hỗ trợ `status=active|ended`, `room_id`, `rental_group_id`, `tenant_id`, `page`, `per_page`. Lọc `tenant_id` tìm cả lịch sử từng tham gia, kể cả người đã rời một đợt vẫn còn hoạt động. Chi tiết trả các `members`, `representatives`, `services` gồm cả khoảng đã kết thúc; mỗi bản ghi có cờ `is_active`.

## Thành viên và người đại diện

Thêm thành viên:

```json
{"tenant_ids":[11,12],"moved_in_at":"2026-10-18 10:00:00"}
```

Chỉ nhận thêm khi phòng đang vận hành, không vượt sức chứa; một người không đồng thời có hai lần cư trú đang mở. Mỗi lần gửi tối đa 100 người. Thành viên cuối cùng cần dùng trả phòng thay vì để lại đợt thuê rỗng.

Cho một người rời đi:

```json
{"moved_out_at":"2026-10-18 11:00:00"}
```

Nếu người rời đi đang là đại diện, thêm `replacement_tenant_id` là thành viên còn ở có tài khoản hợp lệ. Việc đổi đại diện và kết thúc cư trú diễn ra cùng transaction.

Chọn/đổi đại diện:

```json
{"tenant_id":11,"started_at":"2026-10-18 11:00:00"}
```

Khoảng đại diện cũ được đóng, khoảng mới được tạo; không đổi người đại diện đã ghi trên hóa đơn cũ. Giữ nguyên các quyền xem hóa đơn đã cấp riêng trong `invoice_access_grants`; không tự cấp hóa đơn cũ cho đại diện mới. Chủ trọ có thể cấp/thay thế hoặc thu hồi quyền từng hóa đơn qua [API hóa đơn](meter-readings-invoices.md#cấp-và-thu-hồi-quyền-người-thuê). Điều này khớp view `active_invoice_access` hiện có, vốn dựa vào grant chưa thu hồi và trạng thái tài khoản.

## Dịch vụ và giá theo thời gian

Áp dụng dịch vụ, mặc định lấy đơn vị, cách tính và giá từ danh mục:

```json
{"service_id":1,"effective_from":"2026-10-18 09:00:00"}
```

Có thể gửi `unit_price` (không âm, tối đa 14 chữ số nguyên/4 thập phân), `unit`, `billing_method` (`metered` hoặc `per_room`) để chốt cấu hình riêng. Gửi lại cùng dịch vụ ở một mốc sau sẽ đóng khoảng cũ và tạo khoảng mới, không sửa đơn giá quá khứ:

```json
{"service_id":1,"effective_from":"2026-10-18 12:00:00","unit_price":"4000.0000"}
```

Ngừng một khoảng: `{"effective_until":"2026-10-18 13:00:00"}`. Dịch vụ danh mục đã tắt không được gán mới, nhưng khoảng đang áp dụng có thể kết thúc; khi chuyển cả nhóm, cấu hình hiện hành được tiếp tục sang phòng mới với đúng snapshot.

Không đóng/đổi giá dịch vụ hay trả phòng trước phần thời gian đã ghi chỉ số điện/nước hoặc đã được ghi trên dòng hóa đơn. Đã có [API ghi chỉ số và lập hóa đơn](meter-readings-invoices.md). Nếu đổi giá điện/nước giữa kỳ, cần ghi chỉ số tại mốc đổi giá để tính theo đúng từng snapshot; nếu chuyển phòng, nhập chỉ số nền của đồng hồ phòng mới.

## Chuyển và trả phòng

Chuyển cả nhóm:

```json
{"room_id":3,"transferred_at":"2026-10-18 14:00:00"}
```

Backend kiểm tra phòng đích sẵn sàng/đủ sức chứa, đóng các khoảng cũ rồi tạo đợt mới liên kết `previous_occupancy_id`. Giữ cùng nhóm, giá thuê đã chốt, thành viên, đại diện và snapshot dịch vụ còn hiệu lực. Có thể gửi `representative_tenant_id` để chọn thành viên khác làm đại diện ở phòng mới. Chỉ số đồng hồ cũ không được sao chép sang đồng hồ phòng mới.

Trả phòng:

```json
{"ended_at":"2026-10-18 15:00:00"}
```

Kết thúc đợt thuê, cư trú, đại diện và dịch vụ đang mở trong cùng transaction. Hóa đơn, thanh toán và quyền hóa đơn đã cấp trước đó được giữ nguyên. Thao tác trả phòng không tự lập hóa đơn cuối cùng hoặc xóa công nợ. Chọn người đại diện trước khi kết thúc đợt thuê; sau đó ghi đủ chỉ số đến đúng mốc kết thúc, xem trước và phát hành qua [API hóa đơn](meter-readings-invoices.md). Có thể bổ sung kỳ chỉ số cuối cho đợt đã đóng khi nằm đúng trong thời gian thuê/dịch vụ và tháng chưa phát hành hóa đơn. Nếu đợt đã đóng mà chưa từng có đại diện, chủ trọ có thể bổ sung người còn cư trú lúc kết thúc để lập hóa đơn theo [hướng dẫn thu tiền và bổ sung đại diện](invoice-payments.md).

## Thời gian và an toàn dữ liệu

- Dùng `YYYY-MM-DD HH:mm:ss` theo `Asia/Ho_Chi_Minh`, không đặt lịch tương lai ở các API này.
- Ghi sự kiện theo thứ tự thời gian. Một thay đổi không được trước mốc sự kiện đã lưu trong đợt thuê (cư trú, đại diện, dịch vụ, kỳ chỉ số, kỳ hóa đơn và thời điểm phát hành/yêu cầu sửa chữa).
- Đại diện đang cư trú có thể [gửi báo hỏng và theo dõi sửa chữa](maintenance-requests.md). Các yêu cầu giữ nguyên đợt thuê/phòng khi đổi đại diện hoặc chuyển/trả phòng; người gửi vẫn xem được yêu cầu của mình.
- Mốc kết thúc phải sau mốc bắt đầu của mọi khoảng cần đóng; không tạo khoảng có độ dài 0.
- Khóa phòng theo thứ tự ID, sau đó nhóm/đợt thuê và hồ sơ liên quan để phối hợp với các API sức chứa/giá chung. Kiểm tra trùng cư trú được đọc lại sau khi khóa hồ sơ người thuê.
- Nếu chuyển phòng/đổi đại diện lỗi ở bất kỳ bước nào, toàn bộ thay đổi được hoàn tác; không để lại nửa đợt đã đóng hoặc bản ghi chuyển phòng dang dở.
- HTTP 401: chưa đăng nhập/tài khoản bị khóa; 403: sai vai trò; 404: bản ghi không tồn tại hoặc không thuộc đợt thuê trên URL; 409: trạng thái đang dùng/trùng thời gian; 422: dữ liệu hoặc thời gian không hợp lệ.

Kiểm thử:

```powershell
php artisan test --filter=TenancyManagementTest
php artisan test
php artisan rental:verify-database
```

Kiểm thử tự động dùng SQLite trong bộ nhớ, không sửa dữ liệu MySQL hiện có. Tham chiếu: [Laravel database transactions](https://laravel.com/docs/12.x/database#database-transactions), [Validation](https://laravel.com/docs/12.x/validation).
