# Hướng dẫn bước 1–2: chuẩn bị Alwaysdata và sao lưu RentBase

**Đã thay bằng phương án một CSDL theo yêu cầu mới của người dùng.** Làm theo [hướng dẫn một CSDL từ đầu đến cuối](alwaysdata-single-database-guide.md); không cần tạo `rentbase_rental_test`. Các chỉ dẫn hai CSDL bên dưới là bản trước và không còn là phương án đang áp dụng.

Ngày soạn: 03/11/2026. Đây là hướng dẫn tự thao tác; kết quả đã thực hiện được ghi trong phần cập nhật ngay dưới đây.

**Cập nhật thực hiện:** sau khi Aiven Running, trợ lý đã kiểm tra đăng nhập hai vai trò, dữ liệu và ảnh, chạy bù nhắc hôm nay rồi hoàn thành bản sao mới. Tệp riêng `D:\KLTN\.cache\deployment\rentbase\backups\rentbase-backup-20261103-011808.tar.gz` đã khôi phục thử 29 bảng và 3 ảnh, số dư khớp, SHA256 bản tải xuống khớp hosting. Bước 2 chuẩn bị bản sao đã hoàn thành bằng công cụ hiện có; không bắt buộc xuất thêm SQL thủ công. Phần bước 2 dưới đây vẫn giữ để người dùng biết tự sao lưu hoặc xuất thêm tệp SQL khi cần. Bước 1 vẫn là việc tiếp theo người dùng thực hiện.

Tệp nén này chứa snapshot CSDL dạng `database.json.gz` kèm cấu trúc, ảnh và cấu hình; không phải tệp SQL để nhập trực tiếp trong Workbench. Bước chuyển thử sẽ dùng công cụ đọc snapshot/chuyển tương thích. Trước lần chuyển chính thức vẫn lấy snapshot chốt mới nếu dữ liệu đã thay đổi.

## Bước 1 — Chuẩn bị CSDL trên Alwaysdata

### 1. Kiểm tra tài khoản

1. Mở https://admin.alwaysdata.com và đăng nhập.
2. Chọn tài khoản **rentbase** ở thanh bên trái.
3. Mở **Usage**, kiểm tra dung lượng tổng đã dùng và dung lượng còn lại. Ghi lại số này vì CSDL cũng tính vào quota tài khoản.
4. Mở **Databases → MySQL**. Dù ứng dụng dùng MariaDB trên gói Public Cloud, mục quản trị vẫn mang tên MySQL.

### 2. Tạo hai CSDL rỗng

Tìm nút tạo CSDL mới, thường có nhãn **Add a database** hoặc dấu **+**. Tạo lần lượt:

| Mục | Tên đầy đủ dự kiến | Mục đích |
| --- | --- | --- |
| CSDL thử | `rentbase_rental_test` | Chuyển thử và kiểm tra nghiệp vụ |
| CSDL chính | `rentbase_rental_management` | Dùng khi chuyển chính thức |

Nếu ô Name đã có tiền tố cố định **`rentbase_`**, chỉ nhập `rental_test` hoặc `rental_management` vào phần còn lại; không nhập lặp tiền tố. Nếu trường cho nhập nguyên tên thì dùng tên đầy đủ. Đối chiếu tên cuối cùng sau khi lưu.

Nếu có chọn encoding/charset, chọn **utf8mb4**. Các mục khác có thể giữ mặc định; ghi lại phiên bản MariaDB đang hiển thị để kiểm tra tương thích ở bước 3. Bấm **Submit/Save** sau mỗi lần tạo. Nếu tên đã tồn tại, kiểm tra trạng thái thay vì xóa CSDL có sẵn.

### 3. Chuẩn bị tài khoản CSDL

Trong mục MySQL, tìm phần **Users/MySQL users** và tạo tài khoản riêng nếu chưa có tài khoản phù hợp:

| Ô | Giá trị |
| --- | --- |
| Name/Username | Dự kiến `rentbase_app`; nếu đã có tiền tố `rentbase_`, nhập `app` |
| Password | Một mật khẩu riêng, đủ mạnh, lưu riêng trên máy |
| Database permissions | Chọn **All privileges** cho đúng hai CSDL vừa tạo |
| Các CSDL khác | Không cấp thêm nếu không cần |

Tài khoản này dùng cho kết nối CSDL. Tài khoản chủ trọ đăng nhập website có mật khẩu riêng. Giữ mật khẩu CSDL trong tệp cấu hình riêng trên máy.

Tên mục, vị trí phần cấp quyền có thể khác theo phiên bản giao diện; đối chiếu tên CSDL và user sau khi lưu. [Tài liệu Alwaysdata](https://help.alwaysdata.com/en/docs/web-hosting/databases/mariadb/).

### 4. Ghi lại kết nối và kiểm tra

Ghi lại từ trang quản trị:

```text
Host:     mysql-rentbase.alwaysdata.net (đối chiếu giá trị thực tế)
Port:     3306
User:     tên user vừa lưu
Test DB:  rentbase_rental_test
Main DB:  rentbase_rental_management
```

Mở liên kết **phpMyAdmin** trong mục MySQL, đăng nhập bằng user CSDL vừa chuẩn bị. Xác nhận nhìn thấy hai CSDL rỗng. Trong tab SQL có thể chạy câu chỉ đọc:

```sql
SELECT VERSION() AS database_server_version;
```

Ghi kết quả phiên bản. Kết thúc bước 1 khi user đăng nhập được và nhìn thấy đúng hai CSDL. Chưa nhập dữ liệu hay đổi `.env` website.

## Bước 2 — Sao lưu nguồn Aiven và tệp trên hosting

### 1. Chờ nguồn Aiven sẵn sàng

1. Mở https://console.aiven.io, chọn **rentbase-db → Overview**.
2. Chờ **Running**. Nếu vẫn Building/Rebuilding/Starting thì chưa xuất MySQL.
3. Mở connection Aiven trong MySQL Workbench; kiểm tra bằng **Test Connection** nếu đang ở cửa sổ cấu hình.
4. Tài khoản trước đây là **avnadmin**, không phải `avien`. Host, port, mật khẩu và CA lấy từ dịch vụ đang dùng. Dùng kết nối TLS đã cấu hình; không tắt SSL để vượt lỗi.
5. Mở schema **defaultdb** và kiểm tra có bảng `users`, `rooms`, `invoices`, `payments` cùng các bảng nghiệp vụ khác.

Đây là CSDL online cần sao lưu, khác `rental_management` trong connection MySQL local trên Windows.

### 2. Chuẩn bị nơi lưu

Trong File Explorer, mở thư mục đã có:

```text
D:\KLTN\.cache\deployment\rentbase\backups
```

Thư mục này đang giữ bản sao cũ ngày 01/11, đồng thời nằm ngoài Git. Tạo tệp mới có tên/ngày riêng; không ghi đè bản cũ. Khi xuất nhiều lần cùng ngày, thêm hậu tố `-2`, `-3`.

### 3. Xuất SQL bằng MySQL Workbench

1. Trong connection Aiven, chọn **Server → Data Export**.
2. Tích schema **defaultdb**.
3. Chọn toàn bộ bảng và view của schema, gồm cả `migrations`. Theo cấu trúc đã kiểm tra trước đó có 28 bảng nghiệp vụ + 1 bảng migrations và 3 view; nếu thực tế khác thì ghi nhận để đối chiếu, không xóa đối tượng cho khớp con số.
4. Chọn **Dump Structure and Data**.
5. Chọn **Export to Self-Contained File**.
6. Chọn nơi lưu, ví dụ:

```text
D:\KLTN\.cache\deployment\rentbase\backups\rentbase-aiven-20261103.sql
```

7. Tích **Create Dump in a Single Transaction** để dữ liệu InnoDB trong tệp nhất quán tại thời điểm bắt đầu xuất.
8. Không tích **Include Create Schema**: bản này sẽ được xem xét để nạp sang tên CSDL khác.
9. Trong **Advanced Options**, đặt `set-gtid-purged` thành **OFF** nếu mục này xuất hiện. Chọn `no-tablespaces` nếu có; nếu giao diện không có tùy chọn hoặc xuất lỗi, giữ nguyên nhật ký lỗi để xử lý, không coi bản lỗi là bản sao thành công.
10. Bấm **Start Export**, đợi hoàn tất rồi xem **Export Progress**. Chỉ xác nhận thành công khi không có lỗi xuất.

Trong lúc xuất, tránh tạo/sửa cấu trúc CSDL và tạm ngừng tự thử các thao tác ghi trên website. Đây là bản sao chuẩn bị; trước lúc chuyển chính thức sẽ chốt thêm snapshot cuối cùng vì dữ liệu có thể thay đổi sau khi xuất.

[Hướng dẫn Workbench](https://dev.mysql.com/doc/workbench/en/wb-admin-export-import-management.html), [hướng dẫn sao lưu Aiven](https://aiven.io/docs/products/mysql/howto/migrate-database-mysqldump).

### 4. Kiểm tra tệp SQL

- File tồn tại, dung lượng lớn hơn 0 và thời gian tạo đúng lần vừa xuất.
- Mở bằng Notepad, tìm được các phần `CREATE TABLE`, `CREATE VIEW` và các lệnh `INSERT INTO` của bảng có dữ liệu.
- Nếu file có lệnh `USE defaultdb` hoặc DEFINER thì giữ nguyên bản sao gốc; bước xử lý tương thích sẽ làm trên bản sao riêng. Chưa import file trực tiếp vào MariaDB.
- Tệp xuất chưa chứng minh tự khôi phục thành công; bước chuyển thử và đối chiếu sẽ xác nhận điều đó.

### 5. Sao lưu ảnh và cấu hình trên Alwaysdata

Tệp SQL không chứa nội dung ảnh. Ảnh và cấu hình ứng dụng nằm ở thư mục shared trên hosting. Có thể sao lưu phần này ngay cả khi Aiven đang Building.

Mở **Command Prompt (CMD)** trên Windows, dán từng dòng dưới đây. Khối lệnh dùng cú pháp CMD, không dán nguyên vào PowerShell. Khóa SSH là khóa đã cài từ trước; không có mật khẩu trong lệnh.

```cmd
set "RENTBASE_FILES_BACKUP=rentbase-files-20261103-%RANDOM%%RANDOM%.tar.gz"
ssh -i "D:\KLTN\.cache\deployment\rentbase\ssh\id_ed25519" -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes rentbase@ssh-rentbase.alwaysdata.net "tar -czf /home/rentbase/shared/backups/%RENTBASE_FILES_BACKUP% -C /home/rentbase/shared .env storage/app/private certs"
scp -i "D:\KLTN\.cache\deployment\rentbase\ssh\id_ed25519" -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes rentbase@ssh-rentbase.alwaysdata.net:/home/rentbase/shared/backups/%RENTBASE_FILES_BACKUP% "D:\KLTN\.cache\deployment\rentbase\backups"
```

Chỉ chạy dòng `scp` sau khi dòng `ssh` hoàn tất thành công, không báo lỗi. Lệnh tạo tên riêng bằng số ngẫu nhiên để không đè bản sao trước. Nó đóng gói `.env`, `storage/app/private` và `certs`, không xóa hay đổi kết nối ứng dụng. Không đưa log/cache tạm hay thư mục backups vào chính tệp nén.

Kiểm tra cấu trúc tệp tải về bằng lệnh trong cùng cửa sổ CMD:

```cmd
tar -tzf "D:\KLTN\.cache\deployment\rentbase\backups\%RENTBASE_FILES_BACKUP%" .env storage/app/private/maintenance
```

Kỳ vọng thấy `.env` và các tệp ảnh dưới `storage/app/private/maintenance`. Danh sách tên tệp không xác nhận tính toàn vẹn nội dung; bước nghiệm thu tiếp theo sẽ đối chiếu hash và khôi phục.

Tệp nén chứa mật khẩu CSDL và APP_KEY, vì vậy giữ ở vùng riêng này; không tải lên repository hoặc gửi tệp nén trong chat. APP_KEY và ảnh phải được giữ qua lần chuyển CSDL.

### 6. Ghi nhận trước khi chuyển thử

Kết thúc bước 1–2 khi có:

1. Hai CSDL Alwaysdata rỗng và một user đăng nhập được.
2. Host, port, tên user, tên hai CSDL và phiên bản MariaDB đã ghi nhận.
3. Tệp SQL mới từ Aiven xuất thành công, gồm cấu trúc và dữ liệu.
4. Tệp nén ảnh/cấu hình đã tải xuống máy, giữ cạnh tệp SQL.

Có thể báo lại tên hai CSDL, tên user và phiên bản MariaDB để tiếp tục; không gửi mật khẩu. Nếu Aiven chưa Running thì bước sao lưu SQL vẫn chưa hoàn tất, dù đã sao lưu được tệp trên hosting.

Các thao tác bước 1–2 chỉ chuẩn bị đích và bản sao. Website vẫn kết nối Aiven cho tới lúc chuyển chính thức ở bước sau.
