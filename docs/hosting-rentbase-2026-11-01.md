# Bản online RentBase — 01/11/2026

**Cập nhật 03/11:** đã chuyển bản online sang một CSDL MariaDB Alwaysdata. Xem [nghiệm thu mới, lịch sao lưu và cách kết nối Workbench](mariadb-migration-acceptance-2026-11-03.md). Các đoạn mô tả khởi tạo Aiven bên dưới là lịch sử triển khai ban đầu, không dùng để cài lại CSDL MariaDB đang chạy.

## Địa chỉ và nơi chạy

- Website: **https://rentbase.alwaysdata.net/login**.
- Vue/PWA và Laravel chạy cùng nguồn trên Alwaysdata; `/api` đi đến Laravel.
- MariaDB **11.8.9** trên Alwaysdata, database **`rentbase_rental_management`**, kết nối TLS có xác minh chứng chỉ. MySQL Aiven trước chuyển được giữ để đối chiếu.
- PHP web/CLI **8.4.27**, Composer **2.9.8**. Đã đạt kiểm tra yêu cầu nền tảng theo lockfile.
- Ảnh riêng tư, session và cache nằm ngoài web root, giữ qua các lần cập nhật.
- Máy Windows chỉ cần khi sửa/đưa bản mới lên. Điện thoại cần Internet để đăng nhập, xem dữ liệu và lưu nghiệp vụ.

## Tài khoản và dữ liệu mẫu

| Vai trò | Tên đăng nhập |
| --- | --- |
| Chủ trọ | `admin` |
| Người thuê phòng một người | `user` |

Bản online chỉ chứa dữ liệu giả. Dùng mật khẩu được cấp riêng cho từng tài khoản. Khi khởi tạo một bản cloud mới, lệnh cài tạo mật khẩu ngẫu nhiên và lưu thông tin trong `storage/app/private/cloud-demo-access.json`.

Đã khởi tạo bằng nghiệp vụ của ứng dụng, không sao chép dữ liệu người thuê từ MySQL trên Windows:

- 5 phòng, 4 người thuê; có phòng một người, hai người, phòng trống và bảo trì.
- 3 dịch vụ điện/nước/internet, 3 đợt thuê và 12 kỳ chỉ số.
- 3 hóa đơn kỳ **09/2026 và 10/2026**: thu đủ, thu một phần, còn nợ và quá hạn.
- Phòng **TB-202** có chỉ số để thử phát hành hóa đơn kỳ **10/2026**.
- 2 báo hỏng, ảnh riêng tư, lịch sử tiếp nhận/hoàn tất và chi phí sửa chữa liên kết.
- Thông báo chung gửi đến các tài khoản người thuê đang ở.
- Có thể thấy khoản chi nhỏ mang tên “Nghiệm thu hosting (dữ liệu mẫu)” và ảnh minh họa được tạo khi kiểm thử. Gửi lại cùng mã chống trùng chỉ ghi một khoản.

Trang tổng quan mặc định tháng 11 có thể chưa có hóa đơn phát hành trong tháng đó. Chọn tháng 9 hoặc 10 để xem các hóa đơn mẫu; khoản thu tiền thực nhận và chi phí phát sinh hiện ở tháng 11.

## Tự thử trên máy tính và Android

1. Mở URL trên, đăng nhập bằng tài khoản chủ trọ được cấp rồi thử phòng, dịch vụ, người thuê, hóa đơn, thu chi, công nợ và sửa chữa.
2. Đăng xuất rồi dùng tài khoản người thuê. Người thuê vào `/tenant` và chỉ thấy thông tin, hóa đơn thuộc quyền của mình.
3. Dùng cửa sổ thường và ẩn danh/hai trình duyệt nếu cần thử hai vai trò cùng lúc.
4. Trên Android, mở URL bằng Chrome thông thường, bấm **Cài ứng dụng**. Dùng nút cài của Chrome nếu có; hoặc menu **⋮ → Cài đặt và tạo lối tắt → Cài đặt** (một số máy ghi “Cài đặt ứng dụng”/“Thêm vào màn hình chính”). Xác nhận rồi mở biểu tượng **Nhà Trọ**.
5. Nếu đã cài từ `trycloudflare.com`, cài lại từ địa chỉ hosting mới. Biểu tượng của nguồn cũ không tự đổi địa chỉ.
6. Thử camera/chọn ảnh thật, mở PDF bằng ứng dụng xem tệp của điện thoại, bàn phím và nút quay lại.
7. Sau đó tắt máy Windows hoặc ngắt mạng Windows, dùng điện thoại 4G đăng nhập và lưu một thay đổi mẫu để xác nhận độc lập trên thiết bị thật.

Manifest, icon, service worker và quy trình cập nhật có xác nhận đã có. **Ngày 02/11/2026, người dùng xác nhận đã cài thành công trên điện thoại Android từ bản hosting.** Chưa ghi mẫu máy/phiên bản Chrome, chế độ mở độc lập, camera, trình xem PDF hay ca tắt Windows/dùng 4G. Trang cài riêng `/install` và QR chia sẻ vẫn thuộc phần có thể bổ sung sau; nút cài/hướng dẫn hiện tại dùng được.

## Cấu trúc triển khai

| Đường trên hosting | Nội dung |
| --- | --- |
| `/home/rentbase/current` | Liên kết tới bản phát hành đang chạy |
| `/home/rentbase/www` | Liên kết tới `current/public`, là web root của site mặc định |
| `/home/rentbase/releases/<release>/` | Laravel và bản build Vue của từng lần phát hành |
| `/home/rentbase/shared/.env` | Cấu hình/APP_KEY ổn định, chỉ tài khoản hosting đọc được |
| `/home/rentbase/shared/certs/aiven-ca.pem` | CA dùng xác minh MySQL |
| `/home/rentbase/shared/storage/` | Ảnh, session, cache, log và manifest dữ liệu mẫu |
| `/home/rentbase/shared/backups/` | Vùng sao lưu riêng tư |
| `/home/rentbase/deploy/` | Thông tin bản chạy, công cụ triển khai và bản trang mặc định cũ |

HTTPS kết thúc tại proxy Alwaysdata. Quy tắc HTTP→HTTPS của bản triển khai kiểm tra cả `HTTPS` và `X-Forwarded-Proto` để tránh lặp chuyển hướng. `/sw.js` có MIME JavaScript, `no-cache` và scope `/`; manifest revalidate; API/ảnh/PDF không được lưu trong bộ đệm công khai.

Khi đổi bản, giữ các asset cũ đang được trình duyệt dùng, chuyển liên kết `current` và giữ nguyên `shared`; không nạp lại dữ liệu mẫu. Bản trước còn trong `releases` để quay lại mã nguồn. Nếu bản tương lai đổi schema thì phải lập phương án dữ liệu riêng, không chỉ đổi liên kết mã nguồn.

## Khởi tạo CSDL rỗng và đóng gói

`backend/database/schema/mysql-cloud-baseline.json` chứa DDL đầy đủ 28 bảng/3 view, không có dữ liệu nghiệp vụ, không gắn database/DEFINER của máy Windows. Các migration mở rộng được ghi đúng trạng thái sau khi nạp baseline. Không chạy lại tùy tiện các migration đã áp dụng.

Lệnh `rental:install-cloud-demo --confirm=<host>/<database>` chỉ nhận MySQL Aiven, yêu cầu `CLOUD_DEMO_SETUP=true`, target khớp tuyệt đối và CSDL trống (hoặc lần khởi tạo dở đã có marker của chính nó). Chạy lại sau thành công giữ nguyên chỉnh sửa. Seeder không thể gọi trực tiếp để vượt kiểm tra này. Cờ cài mẫu tắt trong cấu hình web bình thường.

Tạo gói từ mã nguồn hiện tại:

```powershell
cd D:\KLTN\frontend
npm run hosting:build -- --demo
```

Bỏ `--demo` để không hiển thị hướng dẫn tài khoản mẫu. Gói nằm trong `.cache/deployment/rentbase/packages/`, có danh sách SHA256; không chứa `.env`, khóa SSH, vendor/dev dependency, CSDL hay ảnh riêng tư. Thư viện PHP cài trên host theo `composer.lock` với `--no-dev`.

Kiểm tra cấu trúc trên host dùng `php artisan rental:verify-database --database=defaultdb`; đã đối chiếu **28 bảng, 100 quan hệ, 3 view** và 11 migration ở trạng thái đã áp dụng.

## Lịch nhắc hóa đơn

Mã lịch 08:00 theo `Asia/Ho_Chi_Minh` đã có. **Đã sửa tác vụ 37205 ngày 02/11 và xác nhận ít nhất hai lượt tự động liên tiếp exit code 0.** Lỗi đường dẫn PHP cũ không nạp `php.ini` của tài khoản đã được khắc phục bằng lệnh bên dưới. [Biên bản lịch nhắc và dữ liệu mẫu](scheduler-and-demo-audit-2026-11-02.md). Không dùng `schedule:work` hoặc tiến trình chạy thường trực trên gói này.

Trong **Advanced → Scheduled tasks**, thêm tác vụ theo khoảng **1 phút**, lệnh:

```sh
/usr/bin/php /home/rentbase/current/artisan schedule:run
```

Working directory nhập `current` sau tiền tố `/home/rentbase/`, SSH user `rentbase`. `schedule:list` hiển thị đúng lịch; đã thử tạo nhắc hóa đơn mẫu rồi gửi lại, không tạo trùng và không đổi số dư. Đã kiểm tra mốc 08:00 trong tiến trình riêng; lượt thực tế 08:00 sau sửa tiếp tục được theo dõi. [Scheduled Tasks Alwaysdata](https://help.alwaysdata.com/en/docs/web-hosting/tasks/).

## Kết quả kiểm tra

- Backend: **406 kiểm thử / 4.193 assertions** đạt.
- Frontend: **134 kiểm thử** đạt; bản Vue/PWA production build thành công.
- Hosting HTTPS thật: **58 bước** đạt, gồm hai vai trò, các mô-đun đọc dữ liệu, PDF tiếng Việt, tải/mở ảnh riêng, chống gửi trùng thu chi, HTTP→HTTPS, session/CSRF, đăng xuất và chặn đường riêng tư.
- Đã kiểm tra form thu chi khi đồng hồ thiết bị nhanh hơn máy chủ **5 phút**; thời điểm mặc định lấy mốc phản hồi máy chủ, không sửa bản nhập hoặc giao dịch đang chờ của người dùng.
- Chrome với hồ sơ thông thường: manifest hợp lệ, không có lỗi điều kiện cài PWA. Cửa sổ ẩn danh dùng kiểm thử nghiệp vụ không được dùng để kết luận khả năng cài app.
- Sau đổi bản phát hành, ảnh cũ và dữ liệu giữ nguyên; chạy lại lệnh khởi tạo không gieo lại dữ liệu.
- Đã sao lưu và khôi phục thật vào một MySQL database tạm riêng: **29 bảng (28 nghiệp vụ + migrations)**, toàn bộ dòng dữ liệu, số dư và **3 ảnh** khớp. Database tạm được gỡ sau kiểm tra; CSDL website không bị thay thế. Bản sao riêng được giữ trên hosting và tải về máy.
- Cài Android thật: người dùng xác nhận thành công ngày 02/11/2026. Camera thật, trình xem PDF, chế độ mở từ biểu tượng và ca tắt Windows/dùng 4G còn chờ người dùng đối chiếu.

## Duy trì

- Giữ bản sao MySQL, ảnh, CA và APP_KEY ở vùng riêng; thử khôi phục và đối chiếu số dư/ảnh sau các thay đổi quan trọng.
- Đăng nhập quản trị Alwaysdata theo yêu cầu duy trì gói Free; với tài khoản mới là mỗi 120 ngày. Theo dõi thông báo không hoạt động của Aiven, không phát lưu lượng giả để né chính sách.
- Kiểm tra dung lượng host/MySQL khi thêm nhiều ảnh. Gói miễn phí chưa phải cam kết tải cao hoặc SLA.
- Không đưa `D:\KLTN\.cache\deployment\rentbase\` lên Git vì chứa cấu hình và khóa riêng.

Kết quả nghiệm thu, bản phát hành hiện hành và bản sao xác minh được ghi trong hồ sơ bàn giao riêng `.cache/deployment/rentbase/`; phần nghiệm thu thiết bị Android thật còn cần người dùng đối chiếu.
