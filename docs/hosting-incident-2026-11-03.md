# Sự cố đăng nhập bản online — 03/11/2026

## Kết quả kiểm tra lúc khoảng 07:54 (giờ Việt Nam)

- `https://rentbase.alwaysdata.net/login` trả HTTP 200, tài nguyên giao diện vẫn được phục vụ.
- Điểm cấp CSRF trả HTTP 204; truy vấn tài khoản khi chưa đăng nhập trả HTTP 401 như dự kiến.
- Thử đăng nhập cả hai tài khoản mẫu `admin` và `user` đều trả HTTP 500.
- Laravel trên Alwaysdata không phân giải được hostname MySQL `rentbase-db-badongle-6e53.a.aivencloud.com`: `getaddrinfo ... Name or service not known`.
- DNS trên Windows và truy vấn riêng qua DNS 1.1.1.1 đều báo tên miền CSDL không tồn tại. Đây là lỗi kết nối đến địa chỉ CSDL, không chỉ là lỗi của riêng điện thoại.
- Nhật ký đã ghi nhận cùng lỗi từ **19:41 ngày 02/11**, trước các lần đăng nhập buổi sáng 03/11.
- Tác vụ 37205 vẫn khởi chạy mỗi phút. Các lượt ngoài 08:00 có thể exit 0 vì chưa đến giờ chạy lệnh nhắc; kết quả đó không xác nhận CSDL hoạt động.

## Việc cần xác minh để khôi phục

Đã yêu cầu người dùng kiểm tra **Aiven → rentbase-db → Overview**:

1. Nếu **Powered off**, bật lại dịch vụ và chờ **Running**.
2. Nếu **Running**, đối chiếu **Host**, **Port** và thông tin kết nối hiện hành với cấu hình website; không gửi mật khẩu trong chat.
3. Nếu không còn dịch vụ, xác minh tình trạng tài khoản/dịch vụ trước khi quyết định khôi phục từ bản sao riêng đã có. Không tạo lại hoặc thay thế CSDL khi chưa xác minh.

Người dùng đã xác nhận trạng thái **Powered off** và đang bật lại dịch vụ. Chưa xác minh lý do dịch vụ bị tắt. [Tài liệu Aiven](https://aiven.io/docs/products/mysql/concepts/mysql-free-tier) cho biết gói Free không có hạn dùng cố định nhưng có thể bị tắt khi không hoạt động; thông tin này chỉ nêu khả năng, không xác nhận lý do dịch vụ cụ thể bị tắt.

Sau khi nhận trạng thái, kiểm tra lại DNS, kết nối MySQL có TLS, đăng nhập cả hai vai trò, dữ liệu và lịch nhắc 08:00 trước khi kết luận đã khắc phục.

Người dùng tiếp tục xác nhận dịch vụ đang **Building / Rebuilding / Starting** sau khi bật. Chưa xác nhận Running hoặc đăng nhập hoạt động trở lại.

## Lượt nhắc lúc 08:00 ngày 03/11

Alwaysdata đã thực sự gọi lệnh nhắc lúc **08:00:09**. Tuy nhiên, nhật ký Laravel ghi lỗi DNS của CSDL lúc **08:00:12**, rồi ghi lệnh con thất bại với **exit code 1** lúc **08:00:13**. Log tác vụ ngoài vẫn hiển thị `DONE` và exit code 0: không dùng riêng kết quả tác vụ ngoài để kết luận đã gửi nhắc thành công.

Sau khi CSDL khôi phục, cần chạy bù lệnh nhắc ngày hiện tại và kiểm tra chống trùng/đúng người nhận. Chưa thực hiện được bước này khi kết nối CSDL vẫn chưa sẵn sàng.

## Khả năng tự bật khi có người truy cập

Website/PWA hiện chưa tích hợp chức năng yêu cầu bật dịch vụ Aiven. [API Aiven](https://rest.aiven.io/doc/) có thao tác cập nhật dịch vụ với `powered: true`; vì vậy có thể xây một luồng ở backend để kiểm tra trạng thái, yêu cầu bật khi có lượt truy cập thật và hiển thị trạng thái chờ cho người dùng. Khóa quản trị phải giữ trên máy chủ, tách khỏi mã frontend. Đây là phương án đề xuất, chưa triển khai và chưa tạo khóa truy cập mới.

[Aiven mô tả việc bật lại](https://aiven.io/docs/platform/concepts/service-power-cycle) có thể cần từ vài phút đến vài giờ tùy quá trình khôi phục. Cơ chế bật theo truy cập không giải quyết việc nhắc hóa đơn phải chạy khi không có người mở app. Ưu tiên xem xét CSDL chạy thường trực; có thể đánh giá MariaDB/MySQL do Alwaysdata cung cấp, nhưng phải kiểm tra tương thích và đối chiếu dữ liệu trước khi chuyển. Chưa đổi nhà cung cấp hay cấu hình CSDL.

## Dữ liệu và cấu hình

- Không thay đổi dữ liệu nghiệp vụ, mật khẩu, cấu hình hosting hay bản phát hành trong quá trình chẩn đoán.
- Chưa đọc lại được CSDL để xác nhận số lượng dữ liệu hiện tại.
- Có bản sao ngày 01/11 đã từng khôi phục kiểm chứng thành công; đây chưa phải bằng chứng về trạng thái CSDL ngày 03/11.
- Bằng chứng kiểm tra HTTP được lưu trong `.cache/hosting-availability-20261103.json`, không chứa cookie hoặc thông tin xác thực.

## Cập nhật khôi phục khoảng 08:16–08:17 ngày 03/11

- Người dùng báo Aiven đã **Running**. Sau một khoảng cập nhật DNS, hostname cũ hoạt động lại; không cần đổi cấu hình kết nối.
- Website đăng nhập và đăng xuất được bằng cả hai tài khoản mẫu; API xác nhận vai trò `owner` cho `admin` và `tenant` cho `user`.
- MySQL 8.4.11 `defaultdb` kết nối có TLS. Kiểm tra chỉ đọc đủ 28 bảng nghiệp vụ, các số lượng dòng khớp lần kiểm tra trước khi sự cố; công nợ 4.660.000đ và cả 3 ảnh khớp hash.
- Chạy bù nhắc ngày 03/11 cho hóa đơn mẫu quá hạn `HD-202610-2`: lần đầu tạo 1, lần sau tạo 0 và trả lại 1 thông báo đã có. Đúng người nhận, ẩn với người thuê khác, số dư không đổi. Tổng thông báo tăng từ 9 lên 10 do lượt nhắc bù này.
- Biên bản nhắc bù được lưu riêng `reminder-verification-20261103.json`; biên bản ngày 02/11 được giữ nguyên.
- Bản sao mới `/home/rentbase/shared/backups/20261103-011808-046db7` đã khôi phục thử thành công: 29 bảng gồm migrations, 58 khóa ngoại được đối chiếu, số dư và 3 ảnh khớp. CSDL tạm do công cụ tạo đã được gỡ sau kiểm tra.
- Đã tải tệp `rentbase-backup-20261103-011808.tar.gz` về vùng riêng trên máy, SHA256 khớp hosting: `be24fb3c754b76ec2fc462be95b3d79c91065d38992933dd06bd04eef61537c5`. Bản sao gồm dữ liệu/cấu trúc, ảnh, APP_KEY/cấu hình và CA; không đưa lên Git.
- Kết nối hiện tại đã phục hồi; chưa đổi website sang Alwaysdata MariaDB. Lượt nhắc 08:00 tự động đã thất bại vì CSDL khi đó không hoạt động; lượt nhắc bù 08:17 thành công không thay thế bằng chứng lượt tự động kế tiếp.
