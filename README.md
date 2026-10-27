# Hệ thống quản lý nhà trọ

Mã nguồn đồ án quản lý phòng, người thuê, quá trình thuê và thu chi.

## Cấu trúc

- `backend/`: Laravel 12, PHP 8.2+, MySQL 8.0; xác thực bằng cookie session và CSRF.
- `frontend/`: Vue 3 + TypeScript + Vite; xác thực/tài khoản/thông báo và CT1–CT8 chủ trọ đã nối API, gồm phòng/người thuê, điện nước/hóa đơn/thu tiền, công nợ, thu chi/hoàn tác, sửa chữa và tổng quan. NT1–NT4 người thuê đã có phòng/hồ sơ/lịch sử, hóa đơn/biên nhận, báo hỏng/ảnh và thông báo. [Tự kiểm tra người thuê](frontend/docs/tenant-self-test.md). [Các màn hình](frontend/docs/owner-management.md), [bản dùng thử và tự kiểm tra](frontend/docs/owner-self-test.md).

## Chức năng backend

- Đăng nhập, phân quyền chủ trọ/người thuê và đổi mật khẩu.
- Phòng, dịch vụ và đơn giá theo thời gian.
- Hồ sơ người thuê, nhóm thuê, nhận/chuyển/trả phòng và lịch sử cư trú.
- Chỉ số điện nước, hóa đơn, công nợ, thu tiền một phần/toàn bộ.
- Thu chi, nhật ký chỉnh sửa, hoàn tác và khôi phục khoản xóa nhầm.
- Báo hỏng/sửa chữa, ảnh riêng tư và thông báo trong ứng dụng.
- Lịch sử xử lý sửa chữa: trạng thái/ghi chú trước sau, người thực hiện, thời điểm và mốc dữ liệu cũ.
- Thông báo chung của chủ trọ: gửi toàn bộ/theo phòng, xem trước người nhận, chống gửi trùng và theo dõi đã đọc.
- Trang tổng quan và báo cáo theo tháng.

## Chạy backend

Ứng dụng sử dụng CSDL `rental_management` có sẵn 16 bảng gốc. Bảy migration trong mã nguồn chỉ bổ sung phần mở rộng, không tạo lại CSDL gốc. Kho mã nguồn có bản chụp cấu trúc, không chứa dữ liệu người thuê hoặc mật khẩu kết nối.

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

## Chạy frontend

Giữ backend ở `http://127.0.0.1:8000`, mở terminal khác:

```powershell
cd D:\KLTN\frontend
npm ci
npm run dev
```

Mở `http://127.0.0.1:5173/login`, đăng nhập bằng tài khoản ứng dụng đã có. Frontend proxy `/api` đến Laravel, dùng cookie session/CSRF hiện tại. [Hướng dẫn frontend và kiểm thử](frontend/README.md).

## Kiểm tra và tài liệu

```powershell
cd backend
php artisan test
php vendor/bin/pint --test
```

Ngày 24/10/2026: 328 kiểm thử backend, 119 kiểm thử frontend và 27 kịch bản Chrome liên quan thông báo chung/hộp thông báo/đăng nhập đạt; đóng gói TypeScript/Vite và quy chuẩn PHP đạt. Kiểm tra mới trên MySQL cho thông báo chung đạt 15 bước, giữ nguyên dữ liệu sau rollback. CSDL hiện có 22 bảng nghiệp vụ, 78 quan hệ và 3 view; bảng `migrations` theo dõi nâng cấp. Bộ 66 kịch bản trình duyệt toàn bộ trước đó đã đạt ngày 23/10/2026.

Nghiệm thu giao diện 25/10/2026: đã sửa ô ngày giờ tràn ở 320px, hướng dẫn tài khoản/vai trò, bộ chọn lặp nhãn và nội dung demo; đã kiểm tra lại các luồng hai vai trò. [Biên bản, kết quả từng lượt và giới hạn nghiệm thu](frontend/docs/ui-acceptance.md).

Bổ sung lịch sử sửa chữa 25/10/2026: 336 kiểm thử backend, 119 frontend, 25 kịch bản Chrome liên quan và bản đóng gói đạt. Kiểm tra MySQL mới đạt 14 bước, dữ liệu 23 bảng giữ nguyên sau rollback. CSDL hiện tại có 23 bảng nghiệp vụ, 81 quan hệ và 3 view; dữ liệu cũ giữ nguyên khi nâng cấp.

- [Hướng dẫn backend và các API](backend/README.md)
- [Nhật ký thu chi, ảnh báo hỏng và thông báo](backend/docs/audit-photos-notifications.md)
- [Tình trạng và việc còn lại](backend/docs/backend-readiness.md)
- [Chống gửi trùng thu khác/chi phí](backend/docs/cash-idempotency.md)
- [Thông báo chung: quy tắc/API](backend/docs/announcements.md), [tự kiểm tra trên giao diện](frontend/docs/announcements-self-test.md)
- [Lịch sử sửa chữa: quy tắc/API](backend/docs/maintenance-history.md), [tự kiểm tra](frontend/docs/maintenance-history-self-test.md)
- [Kế hoạch giao diện frontend](backend/docs/frontend-plan.md)
- [Kế hoạch chuẩn bị sản phẩm để giảng viên đánh giá đầu tháng 11/2026](docs/initial-report-plan-2026-11.md)

`.env`, dữ liệu tải lên, thư viện cài đặt và các tệp chạy tạm được giữ ngoài Git.

Cập nhật 23/10/2026: đã có điều chỉnh/hủy/khôi phục hóa đơn có lịch sử, hạn thanh toán và công nợ quá hạn. Xem [nghiệm thu nghiệp vụ](frontend/docs/business-acceptance.md) và [quy tắc hóa đơn](backend/docs/invoice-controls.md).
