# Bước 1 mobile — thử ứng dụng qua HTTPS trên điện thoại

Đã có chế độ HTTPS tạm, dùng giao diện đóng gói và Laravel thật với CSDL/ảnh mẫu riêng tại `.cache/mobile-https`. MySQL `rental_management`, demo 5174 và bản trình 5175 được giữ riêng. Bước 2 đã thêm [biểu tượng/nút và hướng dẫn cài PWA](pwa-install-self-test.md).

## Mở bản đang chạy

Địa chỉ hiện hành được chương trình in khi chạy và lưu tại `frontend/.cache/mobile-https/access.txt`; bản ghi trạng thái ở `status.json`. Đường dẫn có dạng `https://…trycloudflare.com/login`.

Theo lựa chọn của người dùng, bản thử điện thoại dùng:

| Vai trò | Tài khoản | Mật khẩu |
| --- | --- | --- |
| Chủ trọ | admin | 123 |
| Người thuê | user | 123 |

Đây là tài khoản của CSDL mẫu mobile riêng. Khi khởi tạo, các tài khoản fixture ngoài danh sách cho phép bị khóa và toàn bộ mật khẩu nguồn được thay; chỉ hai tài khoản trên dùng mật khẩu ngắn theo yêu cầu. Tài khoản mẫu nhóm/thành viên có mật khẩu riêng trong `access.json`. Nếu bạn đổi mật khẩu qua giao diện, dùng mật khẩu mới ở những lần đăng nhập sau.

## Tự chạy bằng CMD

Lần đầu cần thư viện backend/frontend đã cài sẵn, PHP SQLite/GD và Node như bản web. Sau khi bản đang chạy đã dừng:

```bat
cd /d D:\KLTN\frontend
set "PHP_BINARY=C:\xampp\php\php.exe"
npm run mobile:https:setup
npm run mobile:https
```

`setup` tải công cụ Cloudflare chính thức vào thư mục tạm và kiểm tra SHA-256 của phiên bản đã khóa. Lệnh chạy sẽ kiểm tra kiểu dữ liệu, đóng gói giao diện, tạo dữ liệu mẫu nếu chưa có, kiểm tra bản sao/khôi phục và in URL HTTPS. Hai cổng cục bộ riêng là **5176** và **18003**; chỉ cổng phục vụ giao diện/API được chia sẻ.

Giữ máy tính có mạng và cửa sổ lệnh đang chạy. Nhấn **Ctrl+C** tại cửa sổ đã tự chạy để dừng. Nếu bản được mở trong phiên hỗ trợ, yêu cầu dừng phiên đó trước khi tự chạy thêm; thông báo cổng bận không có nghĩa cần tắt MySQL hoặc các chương trình khác.

[Cloudflare Quick Tunnel](https://developers.cloudflare.com/tunnel/get-started/quick-tunnels/) là đường dẫn thử, dừng khi tiến trình chia sẻ dừng và đổi tên miền ở lần chạy tiếp theo. Mật khẩu/dữ liệu mẫu được giữ; URL mới được in lại. Điện thoại có thể dùng Wi-Fi hoặc dữ liệu di động truy cập URL đó khi dịch vụ hoạt động.

## Thứ tự tự thử

1. Trên điện thoại, mở URL HTTPS có đuôi `/login`.
2. Đăng nhập **admin / 123**: xem tổng quan, phòng, hóa đơn và sửa chữa.
3. Mở hóa đơn TEST-USER: kiểm tra tổng 3.080.000 đồng, đã thu 1.000.000, còn 2.080.000 ở dữ liệu mới; tải PDF.
4. Mở báo hỏng TEST-USER: ảnh minh họa phải xem được.
5. Đăng xuất, đăng nhập **user / 123**: xem phòng, hóa đơn được cấp quyền, ảnh và tiến độ sửa chữa.
6. Người thuê truy cập khu vực chủ trọ phải bị chặn; đăng xuất phải trở về đăng nhập.
7. Thử làm mới trang hoặc mở thẳng đường dẫn chi tiết. Dữ liệu đã thao tác được giữ; các số mẫu thay đổi nếu bạn thu thêm tiền hoặc sửa nghiệp vụ.

Hai tab cùng trình duyệt có thể dùng chung phiên. Để thử đồng thời hai vai trò, dùng hai trình duyệt/hồ sơ phù hợp trên thiết bị.

## Tài khoản mẫu cũ đã tạo trước khi đổi sang mật khẩu ngắn

Để đặt lại đúng hai tài khoản mobile thành 123 trên bản mẫu riêng đã chuẩn bị:

```bat
cd /d D:\KLTN\frontend
php tests/e2e/support/set-mobile-demo-passwords.php 123
```

Nếu PHP không nằm trên PATH, dùng `C:\xampp\php\php.exe` thay cho `php`. Helper kiểm tra đúng đường dẫn SQLite và metadata mẫu trước khi ghi, tạo bản sao CSDL trước thay đổi và không mở kết nối MySQL.

## Dữ liệu, ảnh và kiểm tra

- Mỗi lần mở máy chủ tạo bản sao CSDL/ảnh vào `.cache/mobile-https/backups/` rồi khôi phục thử vào thư mục riêng, đối chiếu bản ghi, khóa ngoại, số dư và mã kiểm tra ảnh. `backup-latest.json` ghi mốc đã kiểm tra; sau đổi mật khẩu còn có bản CSDL trước thay đổi.
- Chỉ phục vụ tệp giao diện trong thư mục build đã định; `.env`, `.cache`, mã nguồn và bản sao không có URL tải công khai. API/ảnh/PDF riêng tư có `no-store`.
- Cookie phiên dùng HTTPS, HttpOnly, SameSite=Lax; API vẫn yêu cầu CSRF và kiểm tra vai trò/quyền.
- Tiến trình nhắc nền/hosting cố định chưa được thiết lập trong bản chia sẻ tạm này. Cài ứng dụng, mở lại khung offline và cập nhật có xác nhận đã có; [hướng dẫn Android](mobile-android-guide.md), [nghiệm thu PWA](pwa-mobile-acceptance-2026-11-01.md).

Kiểm tra lại bằng `node scripts/review-mobile-https.mjs` khi bản thử đang chạy. Công cụ đọc thông tin truy cập từ tệp riêng, dùng Chrome ở kích thước điện thoại, đối chiếu ảnh/PDF/quyền và tạo ảnh/kết quả trong `.cache/mobile-https/review/`. Công cụ không ghi nghiệp vụ hoặc dùng dữ liệu MySQL.

Kiểm thử cổng chia sẻ cục bộ: `node --test scripts/mobile-gateway.test.mjs`. Hướng dẫn này không công bố URL đang chạy hoặc mật khẩu riêng phát sinh trong kho Git.
