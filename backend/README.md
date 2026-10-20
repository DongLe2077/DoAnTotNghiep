# Rental Management Backend

Backend Laravel 12, sử dụng PHP 8.2 trở lên và CSDL MySQL `rental_management` có sẵn.

## Công nghệ và đăng nhập

- Backend hiện tại: PHP 8.2.12, Laravel 12.70.0, Eloquent ORM, MySQL 8.0.43.
- Frontend riêng trong `D:\KLTN\frontend` chưa được khởi tạo. Trang `/` hiện là Blade mặc định của Laravel. Các khai báo Vite/Tailwind trong `backend/package.json` thuộc scaffold, chưa phải ứng dụng React hoặc Vue.
- Đã có API đăng nhập, đăng xuất, lấy tài khoản hiện tại và phân quyền `owner`/`tenant`, sử dụng cookie session và CSRF của Laravel.
- Hướng dẫn API và kiểm tra đăng nhập: [docs/authentication.md](docs/authentication.md).
- Đã có API quản lý phòng và danh mục dịch vụ cho chủ trọ: [docs/rooms-services.md](docs/rooms-services.md).
- Đã có API hồ sơ/tài khoản người thuê, nhóm thuê, nhận phòng, thành viên, đại diện, dịch vụ theo kỳ, chuyển/trả phòng và lịch sử: [docs/tenant-occupancy.md](docs/tenant-occupancy.md).
- Đã có API ghi chỉ số điện/nước, xem trước và phát hành hóa đơn tháng, tra cứu công nợ, cấp/thu hồi quyền xem cho người thuê: [docs/meter-readings-invoices.md](docs/meter-readings-invoices.md).
- Đã có API thu tiền mặt/chuyển khoản, thu một phần/toàn bộ, chống gửi trùng/thu vượt và bổ sung đại diện lập hóa đơn cho đợt thuê đã đóng: [docs/invoice-payments.md](docs/invoice-payments.md).
- Đã có API quản lý thu khác/chi phí, sổ thu chi gồm tiền hóa đơn đã thu và tổng hợp chênh lệch theo ngày/tháng: [docs/cash-book.md](docs/cash-book.md).
- Đã có API đại diện gửi báo hỏng, chủ trọ tiếp nhận/cập nhật kết quả, người gửi xem tiến độ và lịch sử của mình: [docs/maintenance-requests.md](docs/maintenance-requests.md).
- Đã có tổng quan và công nợ theo hóa đơn/phòng/nhóm: [docs/dashboard-receivables.md](docs/dashboard-receivables.md).
- Đã có hồ sơ/phòng/dịch vụ hiện tại, lịch sử riêng của người thuê và đổi mật khẩu: [docs/tenant-portal.md](docs/tenant-portal.md).
- Phạm vi backend đã có và việc trước bàn giao: [docs/backend-readiness.md](docs/backend-readiness.md).
- Đã có nhật ký/hoàn tác thu chi, ảnh báo hỏng riêng tư và thông báo nhắc thanh toán/cập nhật sửa chữa: [docs/audit-photos-notifications.md](docs/audit-photos-notifications.md).

## Cấu hình và khởi động

Chạy các lệnh trong `D:\KLTN\backend`:

```powershell
composer install
Copy-Item .env.example .env # Chỉ thực hiện nếu chưa có .env.
php artisan key:generate  # Chỉ thực hiện khi APP_KEY chưa có giá trị.
```

Điền thông tin kết nối trong `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rental_management
DB_USERNAME=root
DB_PASSWORD="your-local-password"
```

Nếu mật khẩu có ký tự đặc biệt, dùng dấu nháy phù hợp với định dạng dotenv. Không đưa `.env` vào kho mã nguồn.

```powershell
php artisan config:clear
php artisan migrate # Chỉ các migration mở rộng; CSDL phải có sẵn 16 bảng nghiệp vụ gốc.
php artisan rental:verify-database
php artisan serve
```

Ứng dụng chạy tại `http://127.0.0.1:8000`. Đường dẫn kiểm tra hoạt động Laravel: `/up`.

## CSDL hiện có

- Đã kết nối MySQL 8.0.43; từ 16 bảng gốc, hiện mở rộng thành 19 bảng nghiệp vụ/19 model và 70 quan hệ Eloquent. Bảng `migrations` dùng theo dõi nâng cấp.
- Chi tiết bảng, cột, kiểu dữ liệu, thời gian và khóa ngoại: [docs/database-mapping.md](docs/database-mapping.md).
- Bản chụp cấu trúc 19 bảng nghiệp vụ và 3 view (không chứa bản ghi): `database/schema/rental_management.json`.
- Các migration/dữ liệu mẫu mặc định đã loại bỏ; 3 migration mới chỉ bổ sung nhật ký thu chi, ảnh báo hỏng và thông báo. Dữ liệu cũ được giữ nguyên.
- Session và cache lưu bằng file; queue chạy đồng bộ, không cần thêm bảng hệ thống.
- Múi giờ ứng dụng: `Asia/Ho_Chi_Minh`.
- Không chạy `migrate:fresh`, `migrate:refresh` hoặc `db:wipe` trên CSDL hiện có.

## Kiểm tra

```powershell
php artisan test
php artisan rental:verify-database
composer validate --no-check-publish
composer check-platform-reqs
```

PHPUnit mặc định dùng SQLite trong bộ nhớ, tách khỏi CSDL MySQL hiện có. Lệnh `rental:verify-database` chỉ đọc metadata và truy vấn thử model/quan hệ trên MySQL; không tạo bảng hoặc ghi dữ liệu.

PHP CLI hiện tại dùng `C:\xampp\php\php.ini`. Nếu Composer báo thiếu ZIP khi tải thư viện, có thể bật extension chỉ cho lệnh cài đặt:

```powershell
php -d extension=zip C:\ProgramData\ComposerSetup\bin\composer.phar install
```
