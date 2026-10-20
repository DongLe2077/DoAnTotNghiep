# Thông tin cá nhân của người thuê

Yêu cầu tài khoản `tenant` đang hoạt động và cookie session. Hồ sơ luôn lấy từ `tenant_id` của tài khoản đăng nhập; tham số client không đổi được người xem.

| Phương thức | Đường dẫn | Kết quả |
| --- | --- | --- |
| GET | `/api/tenant/profile` | Hồ sơ cá nhân và thông tin tài khoản công khai |
| GET | `/api/tenant/current-stay` | Phòng, dịch vụ và quyền báo hỏng hiện tại |
| GET | `/api/tenant/stays?page=1&per_page=15` | Lịch sử của chính mình, mới nhất trước, tối đa 100 dòng/trang |
| PUT | `/api/auth/password` | Đổi mật khẩu hiện tại; dùng cho cả chủ trọ và người thuê |

## Hồ sơ và phòng

`profile` trả `id`, `full_name`, `phone_number`, `identity_number`, `account`. Không trả hash hoặc remember token. Đây là API xem; thông tin nhân thân vẫn do chủ trọ sửa qua chức năng quản lý người thuê.

`current-stay` trả `{"data":null}` khi chưa ở hoặc đã rời phòng. Tài khoản chưa liên kết hồ sơ trả 404 tại cả ba API thông tin; hồ sơ không có lịch sử thì `/stays` trả danh sách rỗng.

Phòng hiện tại gồm mã lượt cư trú `id`, `occupancy_id`, thời gian vào/rời ở và bắt đầu/kết thúc đợt thuê; `room` (id, số phòng, sức chứa, trạng thái), `group` (id, mã nhóm), `monthly_room_rent`, `is_active`, `is_current_representative`, `can_submit_maintenance`, `services`.

`monthly_room_rent` là giá thuê **toàn phòng** đã chốt, không phải phần tiền riêng của cá nhân. `services` chỉ gồm khoảng có hiệu lực hiện tại với giá/đơn vị/cách tính đã chốt, kể cả danh mục đã tắt; không lấy giá mặc định mới thay cho snapshot.

Không trả danh sách thành viên, điện thoại/giấy tờ người cùng phòng, lịch sử đại diện hoặc hóa đơn của người khác. Lịch sử chỉ gồm lượt ở của chính mình và không kèm dịch vụ hiện tại để tránh lộ dịch vụ sau khi người đó rời đi.

Thành viên bình thường cũng được xem phòng/dịch vụ của mình; chỉ đại diện đang ở có `can_submit_maintenance=true`. Gửi báo hỏng vẫn kiểm tra lại quyền. Quyền đại diện **không tự cấp quyền xem hóa đơn**; vẫn dùng grant riêng tại [API hóa đơn](meter-readings-invoices.md).

## Đổi mật khẩu

Gửi PUT có CSRF token hiện tại:

```json
{
  "current_password": "mat-khau-hien-tai",
  "password": "mat-khau-moi",
  "password_confirmation": "mat-khau-moi"
}
```

Phải đúng mật khẩu hiện tại, mật khẩu mới khác cũ, ít nhất 8 ký tự/tối đa 72 byte theo bcrypt, xác nhận khớp. Không cho sửa ID, tenant_id, username, role, trạng thái hoặc hash tại API này. Xác minh lại sau khi khóa dòng tài khoản để tránh ghi đè khi đồng thời đổi mật khẩu.

Thành công trả 200 và `requires_login=true`, đăng xuất, đổi session và CSRF trên trình duyệt hiện tại. Các phiên đăng nhập trước không còn dùng được ở lần gọi API tiếp theo do hash đổi. Frontend cần xóa trạng thái đăng nhập, lấy cookie CSRF và yêu cầu đăng nhập lại bằng mật khẩu mới.

Giới hạn 5 lần gọi/phút/tài khoản, kể cả lỗi dữ liệu. Sai mật khẩu hiện tại/dữ liệu trả 422, quá giới hạn 429, thiếu CSRF 419, chưa đăng nhập hoặc tài khoản khóa 401.

Kiểm thử: `TenantPortalTest` và phần đổi mật khẩu trong `AuthenticationTest`, dùng SQLite trong bộ nhớ, tách khỏi MySQL đang sử dụng.
