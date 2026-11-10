# Kế hoạch chuyển CSDL RentBase sang Alwaysdata — 03/11/2026

**Đã triển khai phương án một CSDL ngày 03/11.** [Biên bản hoàn thành chuyển MariaDB](mariadb-migration-acceptance-2026-11-03.md) là trạng thái hiện hành; phần kế hoạch bên dưới giữ lịch sử chuẩn bị.

**Phạm vi đã đổi theo người dùng: chỉ tạo một CSDL Alwaysdata `rentbase_rental_management`.** Hướng dẫn hiện hành là [quy trình một CSDL từ đầu đến cuối](alwaysdata-single-database-guide.md). CSDL này được chuyển thử khi website còn dùng Aiven, sau khi kiểm tra/chốt dữ liệu mới trở thành CSDL chính. Phần đề xuất hai CSDL bên dưới giữ làm lịch sử, không phải yêu cầu người dùng tạo thêm CSDL thứ hai.

## Kết quả người dùng cần

Sau khi chuyển và nghiệm thu thành công, người dùng mở website hoặc ứng dụng Android rồi đăng nhập, sử dụng bình thường. Không có bước đợi bật lại CSDL Aiven 3–4 phút. Vẫn có thời gian tải trang và lấy dữ liệu qua Internet; chưa đo tốc độ trên CSDL mới nên chưa cam kết một số giây cố định.

Mục tiêu là website và CSDL được cung cấp thường trực trên Alwaysdata. Đây không phải cơ chế cho CSDL ngủ rồi đánh thức theo lượt truy cập. Không đồng nghĩa cam kết không bao giờ gián đoạn: bảo trì, lỗi nhà cung cấp, mạng và giới hạn tài khoản vẫn có thể ảnh hưởng.

Trong lúc chuyển chính thức sẽ có một khoảng bảo trì để chốt dữ liệu. Khoảng bảo trì một lần này khác với việc chờ khởi động mỗi lần mở ứng dụng.

**Trạng thái mới nhất:** đã phục hồi kết nối nguồn và hoàn thành bản sao chuẩn bị. Chưa tạo CSDL đích, chưa đổi kết nối website, chưa chuyển dữ liệu sang MariaDB hoặc mua gói dịch vụ.

**Cập nhật sau khi người dùng báo Aiven Running:** kết nối đã phục hồi khoảng 08:16 ngày 03/11, hai vai trò đăng nhập thành công. Đã sao lưu nguồn mới và khôi phục kiểm chứng 29 bảng/3 ảnh; bản sao tải về nằm tại `.cache/deployment/rentbase/backups/rentbase-backup-20261103-011808.tar.gz`. Bước 2 sao lưu chuẩn bị đã hoàn thành, bước 1 tạo CSDL Alwaysdata vẫn chờ người dùng thực hiện theo [hướng dẫn bước 1–2](alwaysdata-migration-steps-1-2-guide.md). Các ghi nhận mất kết nối bên dưới là căn cứ tại thời điểm lập kế hoạch, không phải trạng thái mới nhất.

## Căn cứ hiện tại

- Website và backend đang chạy trên Alwaysdata: `https://rentbase.alwaysdata.net/login`.
- CSDL hiện tại là MySQL 8.4 trên Aiven, database `defaultdb`. Sáng 03/11 người dùng xác nhận Powered off và đang bật lại; kiểm tra gần nhất trong lượt lập kế hoạch vẫn chưa kết nối được.
- Snapshot cấu trúc trong dự án có **28 bảng nghiệp vụ, 3 view**; cần giữ thêm bảng `migrations` và trạng thái migration đã áp dụng.
- Có **16 bảng dùng collation `utf8mb4_0900_ai_ci`**, **7 bảng có cột sinh tự động**, **8 bảng có JSON**, **16 bảng có CHECK**. Đây là các điểm cần kiểm tra khi đổi hệ CSDL.
- Alwaysdata Public Cloud cung cấp **MariaDB**, không mặc định là MySQL 8. Phiên bản CSDL thực tế phải đọc trong tài khoản; phiên bản chương trình client trên SSH không xác nhận phiên bản server. [Tài liệu MariaDB/MySQL Alwaysdata](https://help.alwaysdata.com/en/docs/web-hosting/databases/mariadb/).
- Các thư mục đã đo trên hosting: releases khoảng 111 MB, shared 1,9 MB, deploy 688 KB. Đây chỉ là các thư mục ứng dụng, chưa phải tổng mức sử dụng quota tính cả CSDL, email và các mục khác.
- Có bản sao ngày 01/11 đã từng khôi phục kiểm chứng; cần lấy bản mới nhất khi Aiven hoạt động. Không âm thầm dùng bản cũ để thay cho dữ liệu mới.
- Lượt nhắc 08:00 ngày 03/11 đã được gọi nhưng lệnh con thất bại vì CSDL không kết nối được; phải xử lý chạy bù khi kết nối phục hồi.

## Kiến trúc sau chuyển

```text
Trình duyệt / ứng dụng Android PWA
               │ HTTPS
               ▼
    rentbase.alwaysdata.net
      Vue + Laravel trên Alwaysdata
               │ kết nối CSDL được bảo vệ
               ▼
      MariaDB do Alwaysdata quản lý

Ảnh riêng tư + session + cấu hình: shared trên hosting
Lịch nhắc: Scheduled Task Alwaysdata → Laravel → MariaDB
```

Giữ đường dẫn website, APP_KEY, dữ liệu session và ảnh hiện có. Ứng dụng Android đã cài tiếp tục dùng cùng địa chỉ; không cần cài lại chỉ vì đổi nơi lưu CSDL. Nếu có bản frontend mới thì dùng quy trình cập nhật PWA đã có.

## Thứ tự thực hiện

| Bước | Việc thực hiện | Điều kiện hoàn thành |
| --- | --- | --- |
| 1. Kiểm tra tài khoản và chuẩn bị đích | Kiểm tra gói, quota toàn tài khoản, phiên bản MariaDB, quyền tạo CSDL/user; tạo vùng thử riêng và CSDL đích riêng | Có kết nối đích hợp lệ, đủ dung lượng cho dữ liệu và bản sao; chưa đổi website |
| 2. Chốt bản sao nguồn | Khi Aiven Running, xuất cấu trúc và toàn bộ dòng dữ liệu trong snapshot nhất quán; ghi số dòng, số dư và ảnh; giữ APP_KEY/cấu hình riêng | Có bản sao mới, thời điểm rõ, tải được bản sao ra ngoài hosting |
| 3. Chuyển thử và sửa tương thích | Nạp vào CSDL thử, xử lý khác biệt MySQL/MariaDB, cập nhật công cụ xác minh nếu cần | Đủ bảng/view/khóa/ràng buộc; dữ liệu và số tiền khớp nguồn |
| 4. Nghiệm thu ứng dụng với CSDL thử | Đăng nhập hai vai trò và thử các nghiệp vụ quan trọng trên MariaDB thật | Đọc/ghi đúng, đúng quyền, không trùng tiền, ảnh/PDF dùng được |
| 5. Chuyển kết nối chính thức | Tạm dừng ghi và lịch ghi dữ liệu, lấy snapshot chốt, nạp đích, kiểm tra, đổi cấu hình Laravel, kiểm tra nhanh rồi mở lại | Website và tác vụ nền chỉ ghi vào một CSDL đích duy nhất |
| 6. Kiểm tra Android và lịch nhắc | Thử qua 4G, mở từ biểu tượng, tắt Windows; chạy bù nhắc và theo dõi lượt 08:00 thực tế | Không cần máy Windows bật; nhắc đúng người/ngày và không trùng |
| 7. Theo dõi, sao lưu và bàn giao | Thử sau một đêm không truy cập; đo tốc độ; thử khôi phục bản sao; cấu hình Workbench; cập nhật tài liệu | Có bằng chứng sử dụng lại không phải bật CSDL, khôi phục được và quản lý dữ liệu thuận tiện |

## Chi tiết phần kỹ thuật cần làm

### 1. CSDL đích và thông tin kết nối

- Tạo qua **Databases → MySQL** của Alwaysdata. Tên dự kiến: `rentbase_rental_test` và `rentbase_rental_management`; xác nhận tên thực tế theo giao diện trước khi sử dụng.
- Host dự kiến theo mẫu của nhà cung cấp là `mysql-rentbase.alwaysdata.net`, port 3306; lấy giá trị chính xác từ trang quản trị, không coi tên dự kiến là kết nối đã được cấp.
- Dùng tài khoản CSDL có quyền trên đúng CSDL cần dùng. Giữ mật khẩu trong vùng cấu hình riêng, không đưa vào Git/frontend.
- Kiểm tra kết nối được bảo vệ và xác minh máy chủ; không tắt xác minh chứng chỉ để xử lý lỗi. Workbench dùng kết nối được nhà cung cấp hỗ trợ hoặc SSH tunnel phù hợp.
- Không giả định tài khoản SQL được phép `CREATE DATABASE`; tạo vùng thử/khôi phục qua giao diện hoặc API quản trị được cấp quyền.

### 2. Tương thích MySQL → MariaDB

- Đọc phiên bản server, SQL mode và các collation thực tế hỗ trợ. Đối chiếu cách so sánh tiếng Việt, hoa/thường và dấu trước khi chọn collation thay thế, không thay toàn bộ chuỗi một cách máy móc.
- Giữ ngữ nghĩa cột sinh tự động và unique index đang ngăn hai đợt thuê/người đại diện/quyền xem hoạt động cùng lúc. Không nạp giá trị thủ công vào cột sinh.
- Kiểm tra JSON, Laravel casts, CHECK, số thập phân, khóa ngoại và khóa ghép. Không gỡ ràng buộc để làm cho import chạy qua.
- Tạo và kiểm tra đủ 3 view `invoice_balances`, `room_occupancy_summary`, `active_invoice_access`; giữ quy tắc quyền xem và tính tiền.
- Kiểm tra bộ đếm ID sau import; giữ ID và liên kết ảnh, hóa đơn, phiếu thu, lịch sử.
- Cập nhật công cụ xác minh schema để phân biệt khác biệt biểu diễn hợp lệ của MariaDB (ví dụ JSON/metadata cột) với sai cấu trúc thật. Lưu bằng chứng cấu trúc đích; không bỏ qua toàn bộ kiểm tra schema.
- Bộ cài mẫu hiện chỉ cho phép Aiven không được dùng để vượt kiểm tra mục tiêu. Viết quy trình chuyển có xác nhận đích rõ ràng và kiểm tra CSDL trống; không tắt các chốt bảo vệ và không gieo lại dữ liệu mẫu.
- Công cụ sao lưu/khôi phục đang gắn Aiven cũng cần hỗ trợ đích mới; xác minh lại trên MariaDB, không coi kiểm thử MySQL cũ là bằng chứng đã đạt MariaDB.

### 3. Nghiệm thu dữ liệu và nghiệp vụ

- So sánh số dòng từng bảng và nội dung quan trọng; số dư hóa đơn/công nợ, tiền đã thu, thu chi phải bằng snapshot nguồn.
- Kiểm tra hash/tồn tại của toàn bộ ảnh; giữ ảnh ở vùng riêng và kiểm tra quyền mở ảnh/PDF.
- Thử chủ trọ, người thuê, chặn truy cập trái vai trò, đổi người đại diện và quyền xem hóa đơn.
- Thử điện nước, hóa đơn, thu tiền một phần/đủ, gửi lại yêu cầu thu tiền/thu chi không trùng, sửa sai phiếu thu và lịch sử.
- Thử báo hỏng, tải/mở ảnh, chi phí sửa chữa và thông báo.
- Kiểm thử ghi trên vùng thử bằng dữ liệu thử riêng. Khi đã chuyển thật, chỉ dùng giao dịch nghiệm thu có nhãn và kế hoạch đối chiếu rõ.
- Chạy kiểm tra tích hợp với MariaDB thật; bộ SQLite hiện có chỉ là một phần của kiểm chứng.

### 4. Chuyển chính thức và phương án quay lại

1. Ghi lại bản phát hành, cấu hình cũ, bản sao nguồn và kết quả kiểm tra đích.
2. Khóa các luồng ghi trên web/API và tạm dừng tác vụ có thể ghi; chờ yêu cầu đang chạy hoàn tất. PWA không tự gửi lại giao dịch sau bảo trì.
3. Lấy snapshot nguồn cuối cùng, nhập vào CSDL đích đã chuẩn bị và đối chiếu. Không chỉ dùng bản chuyển thử vì dữ liệu nguồn có thể đã thay đổi.
4. Đổi cấu hình kết nối Laravel và các công cụ vận hành sang MariaDB; làm mới cấu hình cache theo quy trình triển khai. Giữ APP_KEY và thư mục shared.
5. Kiểm tra nhanh hai vai trò, số dư, ảnh/PDF và trạng thái sẵn sàng có truy vấn CSDL thật; `/up` đơn thuần chưa chứng minh CSDL hoạt động.
6. Mở lại ghi và khôi phục đúng một tác vụ lịch. Chạy bù nhắc ngày hiện tại bằng cơ chế chống trùng đã có.

Trước khi mở lại ghi, nếu đích chưa đạt thì giữ bảo trì hoặc quay về nguồn **chỉ khi nguồn đang hoạt động và đã kiểm tra**. Aiven đang tắt không phải phương án quay lại sẵn sàng. Sau khi MariaDB đã nhận giao dịch mới, không đơn giản đổi `.env` về Aiven: phải giữ bản sao mới, đối soát và chuyển các dữ liệu mới để tránh mất giao dịch.

### 5. Lịch nhắc và vận hành sau chuyển

- Giữ Scheduled Task 37205, lệnh `/usr/bin/php /home/rentbase/current/artisan schedule:run`, mỗi phút; nhắc 08:00 theo `Asia/Ho_Chi_Minh`.
- Không chuyển lịch nhắc sang MySQL Events: Alwaysdata Public Cloud không hỗ trợ tính năng đó. [Tài liệu CSDL](https://help.alwaysdata.com/en/docs/web-hosting/databases/mariadb/).
- Kiểm tra cả lỗi của lệnh con trong log Laravel và thông báo thực tạo; exit code 0 của tác vụ ngoài chưa đủ.
- Thiết lập sao lưu định kỳ CSDL và ảnh, giữ bản ngoài hosting, giới hạn số bản theo quota; thử khôi phục vào vùng riêng có sẵn.
- Kiểm tra khả năng đăng nhập/xem dữ liệu sau một đêm không ai mở app, không tạo lưu lượng giả để giữ dịch vụ hoạt động.
- Thiết lập kiểm tra sẵn sàng có kết nối CSDL và thông báo lỗi rõ hơn cho người dùng, không để lộ mật khẩu/chi tiết kết nối.
- Thêm connection Workbench cho CSDL mới; MySQL local `rental_management` tiếp tục là dữ liệu phát triển riêng, không tự đồng bộ.

## Điều kiện của gói Free

Alwaysdata công bố Free 0 EUR/tháng, 1 GB dung lượng; quota tính cả tệp, thư và CSDL. Kế hoạch ưu tiên tận dụng gói này cho khóa luận, chưa yêu cầu mua gói. Cần kiểm tra quota toàn tài khoản trước khi nạp dữ liệu. [Gói dịch vụ](https://www.alwaysdata.com/en/offers/), [cách tính quota](https://help.alwaysdata.com/en/docs/admin-billing/billing/public-cloud-prices/).

Chủ tài khoản mới phải đăng nhập **trang quản trị Alwaysdata ít nhất mỗi 120 ngày** để tránh đình chỉ vì thiếu hoạt động; việc người thuê mở website không thay thế thao tác này. Các điều kiện duy trì gói vẫn áp dụng khi dùng CSDL thường trực. [Chính sách đình chỉ](https://help.alwaysdata.com/en/docs/admin-billing/profile/suspension/).

## Lịch triển khai dự kiến

- **Buổi 1:** kiểm tra tài khoản, chuẩn bị CSDL đích, bản sao mới và chuyển thử.
- **Buổi 2:** xử lý tương thích, nghiệm thu nghiệp vụ, chuyển chính thức nếu đạt, kiểm tra Android/Workbench.
- **Sáng hôm sau:** kiểm tra sau một đêm không truy cập và lượt nhắc 08:00; chốt biên bản, bản sao và tài liệu trình diễn.

Đây là ước lượng khi có quyền truy cập và nguồn Aiven sẵn sàng; thời gian chờ Aiven hoặc sửa khác biệt MariaDB có thể kéo dài. Thời gian bảo trì chỉ chốt sau khi đã đo thử quá trình xuất/nhập, không hứa trước một khoảng gián đoạn chưa kiểm chứng.

## Tiêu chí chốt hoàn thành

- Người dùng mở app sau thời gian không truy cập và dùng được mà không phải Power on CSDL.
- Đăng nhập đúng khu vực chủ trọ/người thuê; dữ liệu và số tiền khớp bản nguồn mới nhất.
- Ảnh/PDF hoạt động, ứng dụng Android tiếp tục dùng cùng địa chỉ, Windows tắt vẫn dùng được.
- Lượt nhắc tự động 08:00 thành công thực tế, đúng người nhận và không trùng.
- Sao lưu và khôi phục MariaDB đã thử; tài liệu, cấu hình vận hành và Workbench được bàn giao.

Khi các tiêu chí này đạt mới đánh dấu chuyển hoàn tất. Hiện tại website vẫn trỏ Aiven; kế hoạch này chưa thay đổi trạng thái đó.
