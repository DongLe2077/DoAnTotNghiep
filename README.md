# Hệ thống quản lý nhà trọ

Mã nguồn đồ án quản lý phòng, người thuê, quá trình thuê và thu chi.

## Cấu trúc

- `backend/`: Laravel 12, PHP 8.2+, MySQL 8.0; xác thực bằng cookie session và CSRF.
- `frontend/`: vị trí dành cho giao diện, chưa khởi tạo ứng dụng.

## Chức năng backend

- Đăng nhập, phân quyền chủ trọ/người thuê và đổi mật khẩu.
- Phòng, dịch vụ và đơn giá theo thời gian.
- Hồ sơ người thuê, nhóm thuê, nhận/chuyển/trả phòng và lịch sử cư trú.
- Chỉ số điện nước, hóa đơn, công nợ, thu tiền một phần/toàn bộ.
- Thu chi, nhật ký chỉnh sửa, hoàn tác và khôi phục khoản xóa nhầm.
- Báo hỏng/sửa chữa, ảnh riêng tư và thông báo trong ứng dụng.
- Trang tổng quan và báo cáo theo tháng.

## Chạy backend

Ứng dụng sử dụng CSDL `rental_management` có sẵn 16 bảng gốc. Ba migration trong mã nguồn chỉ bổ sung phần mở rộng, không tạo lại CSDL gốc. Kho mã nguồn có bản chụp cấu trúc, không chứa dữ liệu người thuê hoặc mật khẩu kết nối.

```powershell
cd backend
composer install
Copy-Item .env.example .env # Chỉ khi chưa có .env
php artisan key:generate   # Chỉ khi APP_KEY chưa được đặt
```

Điền kết nối MySQL vào `.env`, sau đó:

```powershell
php artisan migrate
php artisan rental:verify-database
php artisan serve
```

Để chạy lịch nhắc thanh toán lúc 08:00 mỗi ngày, mở terminal riêng trong `backend/` và chạy `php artisan schedule:work`.

## Kiểm tra và tài liệu

```powershell
cd backend
php artisan test
php vendor/bin/pint --test
```

Lần kiểm tra ngày 20/10/2026: 279 kiểm thử đạt; kiểm tra MySQL thành công. CSDL hiện có 19 bảng nghiệp vụ, 70 quan hệ và 3 view; bảng `migrations` theo dõi nâng cấp.

- [Hướng dẫn backend và các API](backend/README.md)
- [Nhật ký thu chi, ảnh báo hỏng và thông báo](backend/docs/audit-photos-notifications.md)
- [Tình trạng và việc còn lại](backend/docs/backend-readiness.md)

`.env`, dữ liệu tải lên, thư viện cài đặt và các tệp chạy tạm được giữ ngoài Git.
