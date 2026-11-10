# Chuyển RentBase sang một CSDL Alwaysdata — từ đầu đến cuối

**Đã thực hiện ngày 03/11/2026:** một CSDL `rentbase_rental_management` trên MariaDB 11.8.9 đã phục vụ website, dữ liệu/ảnh khớp nguồn, có sao lưu định kỳ. [Kết quả nghiệm thu và việc còn cần đối chiếu](mariadb-migration-acceptance-2026-11-03.md). Các bước dưới đây giữ làm quy trình tham chiếu; không tạo lại CSDL hoặc chạy lại phần nạp dữ liệu trên bản đang dùng.

Ngày cập nhật: 03/11/2026. Hướng dẫn này thay thế phương án tạo hai CSDL trong các bản trước.

## Mục tiêu và trạng thái

Chỉ tạo **một CSDL mới trên Alwaysdata: `rentbase_rental_management`**. Ban đầu website vẫn dùng Aiven; CSDL mới được dùng để chuyển thử và kiểm tra. Sau khi đạt, chính CSDL này trở thành CSDL chính của website. Không cần tạo `rentbase_rental_test`.

Website và Android giữ địa chỉ `https://rentbase.alwaysdata.net`. Sau khi chuyển đạt, người dùng không phải chờ bật lại CSDL Aiven khi mở app; vẫn có thời gian tải trang theo mạng và tài nguyên hosting.

Đã có bản sao nguồn ngày 03/11: `D:\KLTN\.cache\deployment\rentbase\backups\rentbase-backup-20261103-011808.tar.gz`. Bản này đã khôi phục thử 29 bảng, 3 ảnh và đối chiếu số dư. Nó chứa snapshot JSON có cấu trúc, ảnh và cấu hình; không phải tệp SQL để bấm Import trực tiếp trong phpMyAdmin. Khi chốt chuyển sẽ lấy bản mới nếu nguồn đã thay đổi.

**Trạng thái hiện tại:** đã chuyển website sang MariaDB; nguồn Aiven và bản sao trước chuyển được giữ riêng.

## 1. Vào đúng tài khoản hosting

1. Mở https://admin.alwaysdata.com.
2. Đăng nhập và chọn tài khoản **rentbase** ở thanh bên trái.
3. Vào **Usage**, xem tổng dung lượng còn trống.
4. Mở **Databases → MySQL**. Mục quản trị mang tên MySQL; dịch vụ Public Cloud thực tế dùng MariaDB. [Tài liệu Alwaysdata](https://help.alwaysdata.com/en/docs/web-hosting/databases/mariadb/).

## 2. Tạo một CSDL

1. Bấm nút tạo mới, thường là **Add a database** hoặc dấu **+**.
2. Điền tên:

| Giao diện ô Name | Bạn nhập |
| --- | --- |
| Đã có tiền tố cố định `rentbase_` | `rental_management` |
| Cho nhập nguyên tên | `rentbase_rental_management` |

3. Nếu có mục charset/encoding, chọn **utf8mb4**. Giữ các lựa chọn khác mặc định và ghi lại phiên bản server đang hiển thị.
4. Bấm **Submit/Save**.
5. Xác nhận danh sách có CSDL **`rentbase_rental_management`**. Nếu tên đã tồn tại, kiểm tra dữ liệu/quyền hiện có trước khi tiếp tục; không xóa CSDL có sẵn để làm lại.

Ở bước này CSDL mới để rỗng.

## 3. Tạo user CSDL và cấp quyền

Trong mục MySQL, tìm **Users/MySQL users**, bấm thêm user nếu chưa có tài khoản phù hợp.

| Trường | Giá trị |
| --- | --- |
| Username | `rentbase_app`; nếu đã có tiền tố cố định `rentbase_`, nhập `app` |
| Password | Mật khẩu riêng do bạn đặt và giữ trên máy |
| Quyền cho `rentbase_rental_management` | **All privileges** |
| CSDL khác | Không cấp thêm quyền nếu không cần |

Lưu lại. User này dùng cho kết nối CSDL. Tài khoản chủ trọ đăng nhập website có mật khẩu riêng.

## 4. Kiểm tra user đăng nhập được

1. Trong trang MySQL, mở **phpMyAdmin**.
2. Dùng user CSDL vừa chuẩn bị để đăng nhập; nếu phpMyAdmin hỏi server thì lấy đúng host trong trang quản trị.
3. Xác nhận thấy `rentbase_rental_management` và chọn nó.
4. Mở tab **SQL**, chạy câu chỉ đọc:

```sql
SELECT DATABASE() AS selected_database, VERSION() AS database_server_version;
```

Kết quả cần cho thấy CSDL đang chọn đúng tên và có phiên bản server. Nếu database trả NULL, chọn tên CSDL ở bên trái rồi chạy lại.

Thông tin kết nối cần ghi lại:

```text
Host:     mysql-rentbase.alwaysdata.net — đối chiếu trang quản trị
Port:     3306
Database: rentbase_rental_management
User:     tên user đã lưu thực tế
```

## 5. Đưa thông tin kết nối vào tệp riêng trên máy

Tệp chuẩn bị thông tin đích nằm tại:

```text
D:\KLTN\.cache\deployment\rentbase\.env.mariadb
```

Mở bằng Notepad. Điền tên CSDL/user đúng thực tế và mật khẩu mới vào dòng `DB_PASSWORD=` rồi lưu. Có thể mở từ CMD bằng:

```cmd
notepad "D:\KLTN\.cache\deployment\rentbase\.env.mariadb"
```

Đây là tệp thông tin CSDL đích, chưa phải thao tác đổi cấu hình website. Không sửa `.env` đang chạy trên hosting ở giai đoạn này. Mật khẩu được giữ trong tệp riêng, không gửi vào chat hay GitHub.

Báo lại: đã tạo CSDL, tên CSDL/user thực tế, phiên bản MariaDB và đã điền tệp. Các thông tin này cho phép tiến hành chuyển thử mà không cần bạn gửi mật khẩu.

## 6. Chuyển dữ liệu vào chính CSDL này và kiểm tra

Phần này trợ lý thực hiện khi có kết nối đích; chưa được thực hiện chỉ bằng việc tạo CSDL.

1. Xác minh đúng host, tên đích và CSDL đang rỗng, kết nối được bảo vệ. Đọc cấu trúc nguồn/bản sao; chuẩn bị quy trình nạp phù hợp với MariaDB.
2. Xử lý tương thích collation, JSON, cột sinh tự động, CHECK, khóa ngoại và 3 view. Không bỏ ràng buộc để nhập cho xong.
3. Nạp cấu trúc/dữ liệu từ bản sao vào **`rentbase_rental_management`**; lúc này website chưa kết nối tới CSDL mới.
4. Kiểm tra 28 bảng nghiệp vụ + migrations, 3 view, số dòng, tiền đã thu, công nợ, thu chi và liên kết ảnh. Số dư phải khớp snapshot nguồn tại cùng thời điểm; con số đã kiểm tra ngày 03/11 là 4.660.000đ.
5. Cập nhật công cụ xác minh/sao lưu vốn gắn với MySQL/Aiven để hỗ trợ MariaDB đúng cách; không tắt các chốt xác nhận CSDL đích.
6. Chạy kiểm tra đăng nhập, phân quyền, hóa đơn, thu tiền, thu chi chống trùng, sửa chữa, ảnh và PDF bằng kết nối thử riêng. Tác vụ thử không gửi nhắc/thông báo hay thay đổi dữ liệu trên Aiven.
7. Thử khôi phục vào chính CSDL đích đang chưa công khai. Các dữ liệu phát sinh khi thử được thay bằng snapshot nguồn chốt trước khi cho website dùng CSDL mới.

Hiện chưa có tệp SQL chuyển MariaDB được nghiệm thu để người dùng tự nhập. Bản `.tar.gz` đã sao lưu và SQL xuất nguyên bản MySQL không thay thế bước xử lý tương thích này.

## 7. Đổi kết nối website sau khi kiểm tra đạt

Phần này trợ lý thực hiện và xác nhận lại kết quả với người dùng:

1. Tạm bảo trì để ngừng ghi dữ liệu và tạm dừng tác vụ ghi; chờ yêu cầu đang chạy hoàn tất.
2. Sao lưu nguồn mới nhất, lấy số dư/số dòng đối chiếu và nạp bản chốt vào CSDL đích đã xác minh. Chỉ thay nội dung vùng thử chưa công khai đã tạo cho việc chuyển; không ghi đè một CSDL khác có dữ liệu chưa xác minh.
3. Cập nhật cấu hình CSDL trên hosting sang MariaDB Alwaysdata và làm mới cache cấu hình. Giữ APP_KEY, ảnh và địa chỉ website.
4. Kiểm tra kết nối, hai vai trò và dữ liệu trước khi mở lại ghi.
5. Khôi phục đúng một Scheduled Task 37205, dùng `/usr/bin/php /home/rentbase/current/artisan schedule:run`, mỗi phút; lịch nhắc 08:00 giờ Việt Nam.
6. Đối chiếu thông báo thật và lỗi lệnh con; chạy nhắc bù nếu cần với cơ chế chống trùng đã có.

Trước khi mở ghi, nếu chưa đạt thì giữ bảo trì hoặc dùng lại nguồn đang hoạt động sau kiểm tra. Sau khi CSDL mới có giao dịch thật, không đơn giản đổi về bản nguồn cũ vì sẽ thiếu giao dịch mới; cần sao lưu và đối soát trước.

## 8. Bạn tự thử từ máy tính và điện thoại

1. Mở `https://rentbase.alwaysdata.net/login` và đăng nhập bằng tài khoản chủ trọ được cấp.
2. Xem tổng quan, phòng, người thuê, hóa đơn, công nợ, thu chi và sửa chữa.
3. Đăng xuất rồi dùng tài khoản người thuê để kiểm tra chỉ thấy dữ liệu được cấp quyền.
4. Mở app Android đã cài, thử ảnh sự cố và PDF.
5. Tắt máy Windows, dùng điện thoại 4G đăng nhập và lưu một báo hỏng thử.
6. Để qua một đêm không mở app, sáng hôm sau thử lại; kiểm tra lượt nhắc 08:00 thực tế.

Kết nối Workbench cho CSDL mới được cấu hình theo thông tin đã kiểm chứng, dùng TLS/SSH phù hợp. Có thể dùng phpMyAdmin để quản lý trong lúc chưa thiết lập Workbench.

## 9. Chốt bàn giao

- Có biên bản dữ liệu và nghiệp vụ trên MariaDB; bản sao mới được giữ trên hosting và tải về máy.
- Cấu hình sao lưu/khôi phục và tài liệu đã cập nhật theo một CSDL đích.
- Website và app dùng CSDL Alwaysdata, Aiven vẫn được giữ trong giai đoạn đối chiếu; chưa xóa dịch vụ nguồn.
- Chủ tài khoản mới đăng nhập trang quản trị Alwaysdata ít nhất mỗi 120 ngày theo chính sách duy trì Free; người thuê mở website không thay thế thao tác này. [Chính sách](https://help.alwaysdata.com/en/docs/admin-billing/profile/suspension/).

Chỉ đánh dấu chuyển hoàn tất khi các kiểm tra đạt. Việc tạo CSDL và user mới riêng lẻ chưa làm website đổi khỏi Aiven.
