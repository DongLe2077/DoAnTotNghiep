# Đăng nhập và phân quyền

## Công nghệ hiện tại

| Thành phần | Công nghệ đã có |
| --- | --- |
| Backend | PHP 8.2.12, Laravel 12.70.0 |
| CSDL | MySQL 8.0.43, database `rental_management` |
| Truy cập dữ liệu | Eloquent ORM, 19 model và 70 quan hệ sau nâng cấp nhật ký/ảnh/thông báo |
| Xác thực | Laravel Auth, guard `web`, session cookie HttpOnly |
| Session và giới hạn đăng nhập | Session/cache lưu file, không thêm bảng CSDL |
| Frontend riêng | Thư mục `D:\KLTN\frontend` đang trống, chưa khởi tạo framework |
| Trang `/` hiện tại | Blade mặc định của Laravel; scaffold có khai báo Vite/Tailwind, chưa có ứng dụng React/Vue |

Luồng dùng cookie session của Laravel, phù hợp giao diện trình duyệt thuộc cùng website. Không cấp bearer token. Khi phát triển frontend riêng, dùng cùng hostname với backend, ví dụ `localhost:5173` và `localhost:8000`, hoặc `127.0.0.1:5173` và `127.0.0.1:8000`.

## API

| Phương thức | Đường dẫn | Yêu cầu |
| --- | --- | --- |
| GET | `/api/auth/csrf-cookie` | Khởi tạo session và cookie `XSRF-TOKEN`, trả 204 |
| POST | `/api/auth/login` | CSRF, JSON gồm `username` và `password` |
| GET | `/api/auth/me` | Đã đăng nhập, tài khoản đang hoạt động |
| POST | `/api/auth/logout` | Đã đăng nhập, CSRF |
| PUT | `/api/auth/password` | Tài khoản hoạt động, CSRF, xác minh mật khẩu hiện tại; thành công yêu cầu đăng nhập lại |
| GET | `/api/owner/access` | Đã đăng nhập, đang hoạt động, vai trò `owner` |
| GET | `/api/tenant/access` | Đã đăng nhập, đang hoạt động, vai trò `tenant` |

Hai đường dẫn `/access` dùng để kiểm tra ranh giới quyền. Các API nghiệp vụ tiếp theo sẽ được đặt trong nhóm tương ứng; quyền với từng bản ghi cần được kiểm tra khi bổ sung nghiệp vụ.

Các route được khai báo trong `routes/auth.php`, đăng ký qua nhóm middleware `web` để luôn có session và kiểm tra CSRF, dù URL bắt đầu bằng `/api`. Không chuyển sang nhóm `api` mặc định nếu chưa bổ sung middleware xác thực phiên tương ứng.

## Luồng frontend gọi API

1. Gọi `GET /api/auth/csrf-cookie` với cookie credentials được bật.
2. Đọc cookie `XSRF-TOKEN`, URL-decode giá trị và gửi trong header `X-XSRF-TOKEN` cho POST.
3. Gọi `POST /api/auth/login` với `username` và mật khẩu gốc. Backend tự kiểm tra hash; không gửi `password_hash` từ trình duyệt.
4. Lưu thông tin người dùng trong bộ nhớ giao diện và gọi `GET /api/auth/me` khi tải lại trang. Cookie session do trình duyệt quản lý.
5. Dựa vào `data.role` để hiển thị khu vực tương ứng. Backend vẫn kiểm tra quyền cho từng request.
6. Khi đăng xuất, gửi POST kèm CSRF token hiện tại; xóa trạng thái tài khoản phía giao diện.

Ví dụ request đăng nhập:

```http
POST /api/auth/login
Accept: application/json
Content-Type: application/json
X-XSRF-TOKEN: <giá trị cookie XSRF-TOKEN đã URL-decode>
Cookie: <cookie do trình duyệt quản lý>

{"username":"<tên đăng nhập của ứng dụng>","password":"<mật khẩu của ứng dụng>"}
```

Ví dụ response 200:

```json
{
  "message": "Đăng nhập thành công.",
  "data": {
    "id": 1,
    "username": "example_owner",
    "role": "owner",
    "tenant_id": null,
    "is_active": true
  }
}
```

Các giá trị trong ví dụ chỉ minh họa định dạng, không phải tài khoản được tạo sẵn.

## CORS và cookie

`config/cors.php` mặc định cho phép hai origin phát triển `http://localhost:5173` và `http://127.0.0.1:5173`, kèm `Access-Control-Allow-Credentials: true`. Có thể đổi trong `.env`:

```dotenv
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173
```

Sau khi sửa cấu hình, chạy `php artisan config:clear`. Dùng `credentials: 'include'` khi gọi fetch; nếu chọn Axios cho frontend, bật `withCredentials` và `withXSRFToken`. Sau đăng nhập, CSRF token có thể thay đổi; luôn lấy giá trị cookie hiện tại khi gửi POST.

Khi triển khai qua HTTPS, cấu hình `APP_URL`, origin frontend và `SESSION_SECURE_COOKIE=true` theo tên miền triển khai. Cookie session mặc định có HttpOnly và SameSite=Lax; cookie XSRF được phép đọc để tạo header CSRF.

## Quy tắc xác thực và phân quyền

- Chỉ chấp nhận tài khoản có `is_active=true` và role `owner` hoặc `tenant`.
- Mật khẩu được kiểm tra qua `users.password_hash`; không trả hash hoặc remember token qua API.
- Role, tenant_id và trạng thái do CSDL quyết định; dữ liệu role do client gửi khi đăng nhập không được dùng để cấp quyền.
- Đổi mã session và CSRF token khi đăng nhập/đăng xuất.
- Kiểm tra lại trạng thái và role ở mỗi request đã xác thực. Khóa tài khoản sẽ chặn phiên đang dùng; `auth.session` kiểm tra thay đổi mật khẩu cho phiên đã thiết lập.
- Sau 5 lần sai cùng tên đăng nhập/IP trong 60 giây, trả 429 kèm `Retry-After`. Một IP bị giới hạn tổng cộng 30 request đăng nhập/phút.
- Không có API đăng ký công khai hoặc đổi quyền trong bước này. Các tài khoản hiện có trong `users` được giữ nguyên.

| HTTP | Ý nghĩa |
| --- | --- |
| 200 | Đăng nhập/đăng xuất hoặc truy vấn thành công |
| 204 | Khởi tạo cookie CSRF hoặc xử lý CORS preflight thành công |
| 401 | Sai thông tin đăng nhập, tài khoản không được phép đăng nhập hoặc phiên không còn hợp lệ |
| 403 | Đã xác thực nhưng không có quyền vào khu vực yêu cầu |
| 409 | Đang đăng nhập; cần đăng xuất trước khi đổi tài khoản |
| 419 | Thiếu/sai CSRF token; lấy lại cookie và thử lại |
| 422 | Thiếu hoặc sai định dạng dữ liệu |
| 429 | Vượt giới hạn đăng nhập |

## Kiểm tra với tài khoản thực tế

Giữ cửa sổ `php artisan serve` đang chạy. Mở PowerShell khác:

```powershell
cd D:\KLTN\backend
.\scripts\check-auth.ps1
```

Script sẽ hỏi tên đăng nhập và mật khẩu ứng dụng, kiểm tra đăng nhập → thông tin tài khoản → đúng/sai khu vực quyền → đăng xuất → hết quyền truy cập. Mật khẩu không được in hoặc ghi vào file. Tài khoản ứng dụng là dữ liệu trong bảng `users`; thông tin kết nối MySQL trong `.env` phục vụ kết nối CSDL.

## Kiểm thử tự động

```powershell
php artisan test
php artisan rental:verify-database
```

Các kiểm thử xác thực dùng SQLite trong bộ nhớ, gồm cả luồng cookie/CSRF thực, phân quyền hai chiều, trạng thái tài khoản, đổi role, thay đổi mật khẩu và giới hạn đăng nhập. Không tạo hoặc sửa tài khoản trong MySQL để chạy kiểm thử.

Tài liệu Laravel tham chiếu: [Authentication](https://laravel.com/docs/12.x/authentication), [Middleware](https://laravel.com/docs/12.x/middleware).
