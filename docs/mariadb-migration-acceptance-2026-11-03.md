# Nghiệm thu chuyển RentBase sang MariaDB Alwaysdata — 03/11/2026

**Cập nhật 04/11:** đã quan sát lượt sao lưu 02:15 và nhắc hóa đơn 08:00 theo đồng hồ thật; 75 điểm kiểm tra HTTPS đạt, số dư và ảnh nguyên vẹn. Xem [biên bản vận hành sau một đêm](overnight-operation-acceptance-2026-11-04.md). Các kết quả ngày 03/11 bên dưới giữ làm bằng chứng thời điểm chuyển.

## Kết quả

Website **https://rentbase.alwaysdata.net/login** đã chuyển sang **một CSDL MariaDB 11.8.9 trên Alwaysdata**, tên `rentbase_rental_management`. Kích hoạt bản `2026-11-03-mariadb-015504` khoảng **08:58 ngày 03/11, giờ Việt Nam**. Đã mở lại website và kiểm tra qua HTTPS thật.

Vue/PWA và Laravel giữ nguyên địa chỉ. Android đã cài tiếp tục sử dụng địa chỉ này. Truy cập không còn phụ thuộc việc bật dịch vụ Aiven; vẫn cần mạng và có thời gian tải dữ liệu bình thường. Chưa có kiểm tra thiết bị thật sau một đêm trên CSDL mới, không cam kết thời gian phản hồi cố định hay không bao giờ gián đoạn.

## Dữ liệu được giữ

- 28 bảng nghiệp vụ + bảng `migrations`, đủ 3 view và 11 migration đã áp dụng.
- Đối chiếu toàn bộ dòng dữ liệu, 58 khóa ngoại và ba view với snapshot nguồn cuối cùng.
- 5 phòng, 4 người thuê, 3 đợt thuê, 12 chỉ số, 3 hóa đơn và 3 phiếu thu.
- 5 khoản chi hiện có; không giữ các khoản thử tạo trên MariaDB chưa công khai.
- 2 yêu cầu sửa chữa, đủ lịch sử và 3 ảnh; hash ảnh khớp.
- Công nợ **4.660.000đ**. Dữ liệu hóa đơn mẫu tập trung ở kỳ 09/2026 và 10/2026.
- 10 thông báo: gồm thông báo nhắc bù ngày 03/11 đã tạo khi Aiven hoạt động lại. Kiểm tra nhắc trên MariaDB trả `created: 0, replayed: 1`, số dư và tổng thông báo không đổi.

Aiven và MySQL phát triển trên Windows không bị thay đổi hoặc xóa trong lần chuyển này. Trước khi đổi kết nối đã chốt snapshot nhất quán khi website bảo trì. Mọi giao dịch thử trên đích được thay bằng dữ liệu chốt từ nguồn trước khi mở website.

## Những điểm tương thích đã xử lý

1. MariaDB biểu diễn JSON thành LONGTEXT có CHECK và thêm độ rộng hiển thị của một số số nguyên. Công cụ xác minh chuẩn hóa đúng các khác biệt này, vẫn kiểm tra nullability, độ chính xác tiền, cột sinh và khóa; chỉ chấp nhận CHECK thực sự buộc `json_valid`, không chấp nhận CHECK bị nới lỏng.
2. Thứ tự tên bảng/khóa trong `information_schema` có thể bỏ qua dấu gạch dưới. So sánh được sắp xếp nhất quán bằng PHP, không bỏ qua đối tượng thiếu/thừa.
3. Đã gặp lỗi 1267 ở thống kê công nợ khi MariaDB trộn collation của biểu thức trạng thái. Bộ chuyển tạo view với collation tường minh cho các chuỗi trạng thái; cả ba view vẫn trả đúng dữ liệu nguồn. Kết nối ứng dụng dùng `utf8mb4_unicode_ci`.
4. Kết nối PDO/Laravel dùng CA hệ thống và xác minh chứng chỉ máy chủ; đã xác nhận TLS `TLS_AES_256_GCM_SHA384`. Không tái sử dụng CA riêng của Aiven cho máy chủ mới.
5. Bộ cài cloud demo vẫn giữ chốt chỉ dành Aiven; không dùng seeder để chuyển và không nới bảo vệ để cài lại dữ liệu.

## Kiểm tra đã thực hiện

| Kiểm tra | Kết quả |
| --- | --- |
| Backend cục bộ sau thay đổi | **410 kiểm thử / 4.201 assertions đạt** |
| Model trên MariaDB | Đủ 28 bảng, 100 quan hệ; đọc metadata, eager load và cast thành công |
| Nghiệp vụ trên MariaDB chưa công khai | **51 bước đạt**: hai vai trò, các mô-đun, phân quyền, thu chi/thu tiền chống trùng, sửa sai phiếu thu, phát hành/hủy hóa đơn, báo hỏng/xử lý/lịch sử, tải ảnh và PDF |
| HTTPS thật sau chuyển | **32 bước đạt**, chỉ đọc dữ liệu nghiệp vụ: các mô-đun hai vai trò, công nợ, chặn xem sai quyền, ảnh/PDF, đăng nhập/đăng xuất |
| Giao diện thật | Đăng nhập chủ trọ trong trình duyệt tích hợp; tổng quan và biểu đồ tháng 10 hiển thị đúng tiền/phòng, không còn báo mất máy chủ |
| Kết nối trên Windows | PDO kết nối TLS thành công tới MariaDB 11.8.9 và đúng CSDL đích |
| Khôi phục MariaDB thật | Xuất native MariaDB, làm sạch đúng đích chưa công khai, khôi phục vào chính CSDL đó, đối chiếu toàn bộ dòng/view/FK/ảnh với nguồn trước khi chuyển |

Bộ frontend không có thay đổi trong lượt chuyển này nên không chạy lại toàn bộ kiểm thử giao diện cũ. Các kiểm tra HTTPS và giao diện thật trong bảng được thực hiện sau chuyển.

## Lịch nhắc và sao lưu

Scheduled Task **37205** tiếp tục chạy mỗi phút:

```sh
/usr/bin/php /home/rentbase/current/artisan schedule:run
```

- Nhắc hóa đơn quá hạn: **08:00 giờ Việt Nam**, chống trùng theo người nhận/hóa đơn/ngày.
- Sao lưu: **02:15 giờ Việt Nam**, chạy `/home/rentbase/deploy/backup-rental.php`.
- Đã thử thực thi tiến trình con sao lưu qua Laravel Scheduler, exit code 0; kiểm tra biên 02:14/02:15/02:16 lần lượt không đến hạn/đến hạn/không đến hạn. Không đổi đồng hồ hệ thống.
- Đã quan sát các lượt Scheduled Task bên ngoài tiếp tục exit code 0 sau chuyển. Ngoài giờ tác vụ, “No scheduled commands are ready to run” là bình thường.
- Tại thời điểm lập biên bản 03/11 chưa quan sát lượt đồng hồ thực tế 02:15 và 08:00 đầu tiên sau khi chuyển. **Đã đối chiếu thành công cả hai lượt ngày 04/11**, gồm bản sao lưu hợp lệ và một thông báo đúng người nhận, không trùng; xem biên bản cập nhật ở đầu tài liệu.

Sao lưu gồm cấu trúc/dữ liệu native MariaDB, ảnh riêng tư, cấu hình/APP_KEY và CA. Giữ tối đa 7 bộ MariaDB do công cụ tạo, có kiểm tra tên/marker/phạm vi đường dẫn; không dọn các bản Aiven cũ.

Bản đã tải xuống máy:

```text
D:\KLTN\.cache\deployment\rentbase\backups\mariadb-20261103-020700-1cf47e.tar.gz
SHA256: c1fdeb941604dc0871903a37b4c4e9f54fa5e1d8260fce5f9485a9220c461beb
```

Đây là bản riêng có thông tin xác thực, không đưa lên Git/GitHub. Thư mục `/home/rentbase/shared/migration` chứa công cụ mà sao lưu định kỳ đang dùng, không xóa như thư mục tạm thông thường. Bản nguồn trước chuyển và cấu hình Aiven được giữ riêng để đối chiếu.

Mã công cụ có bản không chứa bí mật trong `backend/scripts/migrate-alwaysdata-mariadb.php` và `backend/scripts/backup-alwaysdata-mariadb.php`. Công cụ chuyển gắn đúng tài khoản/đích của dự án, chỉ chạy CLI; không dùng để nạp hoặc làm sạch CSDL đang phục vụ website. Lệnh reset yêu cầu đích do công cụ sở hữu, chưa công khai và cấu hình live vẫn trỏ nguồn Aiven.

## Quản lý bằng MySQL Workbench

Tạo connection mới trên trang Home bằng dấu **+**:

| Trường | Giá trị |
| --- | --- |
| Connection Name | `RentBase Alwaysdata` |
| Connection Method | `Standard (TCP/IP)` |
| Hostname | `mysql-rentbase.alwaysdata.net` |
| Port | `3306` |
| Username | `rentbase_app` |
| Default Schema | `rentbase_rental_management` |
| Password | Nhập mật khẩu CSDL Alwaysdata đã đặt, giữ riêng |
| SSL | `Require and Verify Identity` |
| SSL CA File | Có thể dùng `C:\xampp\apache\bin\curl-ca-bundle.crt` đã dùng kiểm tra TLS thành công trên máy này |

Bấm Test Connection; nếu có lỗi chứng chỉ/đăng nhập, đối chiếu cấu hình thay vì tắt xác minh. Workbench dùng giao thức MySQL để kết nối; một số tính năng quản trị của Workbench có thể không dành cho MariaDB, có thể dùng phpMyAdmin khi cần. Việc kết nối từ Windows không tự đồng bộ CSDL local `rental_management` với bản online.

## Việc còn cần đối chiếu

1. Người dùng đóng/mở lại app Android, thử hai vai trò, camera/ảnh, PDF và 4G khi Windows tắt.
2. Mở lại sau một đêm không truy cập; ghi nhận lỗi hoặc thời gian chờ nếu có.
3. **Đã hoàn thành ngày 04/11:** kiểm tra log và kết quả lượt sao lưu 02:15, lượt nhắc 08:00. Tiếp tục theo dõi các ngày sau.
4. Tiếp tục giữ tài khoản hosting hoạt động theo điều kiện Free; đăng nhập trang quản trị định kỳ theo chính sách nhà cung cấp.
5. Mã nguồn và tài liệu đã sửa trong workspace; chưa commit/push GitHub trong lượt chuyển này.

Đã hoàn thành chuyển CSDL và nghiệm thu vận hành ngay sau chuyển; lượt tự động đêm/sáng đầu tiên được xác nhận ngày 04/11. Kiểm tra trên Android thật sau chuyển vẫn chờ người dùng thao tác.
