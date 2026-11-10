# Kích hoạt thanh toán payOS cho RentBase

RentBase tạo một link theo số dư hiện tại của hóa đơn. Giao dịch đúng toàn bộ số tiền còn thiếu được ghi vào sổ thu, tạo phiếu thu và gửi thông báo cho người thuê. Chủ trọ xem giao dịch lệch tiền hoặc lệch phiên bản hóa đơn tại trang chi tiết hóa đơn.

## Chuẩn bị bản trên Alwaysdata

1. Sao lưu MariaDB theo quy trình triển khai RentBase. Đưa backend, `composer.lock` và bản frontend đã đóng gói lên cùng một bản phát hành.
2. Cài các thư viện theo `composer.lock`. Trong thư mục Laravel của bản phát hành, áp dụng migration mới.

   ```sh
   composer install --no-dev --optimize-autoloader
   php artisan migrate --path=database/migrations/2026_11_09_000001_create_payos_checkout_tables.php --force
   ```

3. Trong trang quản trị payOS, dùng thông tin kết nối của kênh thu RentBase. Tạo lại các khóa đã từng được chia sẻ và nhập trực tiếp vào `/home/rentbase/shared/.env`. Giữ `APP_URL=https://rentbase.alwaysdata.net` và đặt `PAYOS_ENABLED=false` trong lúc kết nối webhook.

   ```dotenv
   PAYOS_ENABLED=false
   PAYOS_CLIENT_ID=
   PAYOS_API_KEY=
   PAYOS_CHECKSUM_KEY=
   ```

## Kết nối và kiểm tra

1. Sau khi cấu hình và đưa bản phát hành vào chạy, tải lại cấu hình Laravel bằng `php artisan config:cache`. Trên kênh thu payOS, lưu địa chỉ webhook `https://rentbase.alwaysdata.net/api/webhooks/payos`. Khi lưu, payOS gửi dữ liệu mẫu có chữ ký để xác minh địa chỉ. [Tài liệu webhook của payOS](https://payos.vn/docs/du-lieu-tra-ve/webhook/)
2. Xác nhận tác vụ Alwaysdata vẫn chạy `artisan schedule:run` mỗi phút. Laravel sẽ kiểm tra lại các link đang chờ nếu webhook đến chậm hoặc bị bỏ lỡ.
3. Đặt `PAYOS_ENABLED=true` và chạy lại `php artisan config:cache`. Dùng tài khoản người thuê được cấp quyền để mở một hóa đơn kiểm thử có số tiền nhỏ, tạo link payOS rồi kiểm tra luồng xác nhận từ ứng dụng ngân hàng. payOS xử lý giao dịch thử trên môi trường thật. [Hướng dẫn kiểm thử của payOS](https://payos.vn/docs/moi-truong-test/)
4. Đối chiếu đúng một phiếu thu, số dư còn lại bằng 0, thông báo của người thuê và lịch sử thu tiền của chủ trọ. Dùng dữ liệu giả lập cho các tình huống chuyển lệch tiền, chữ ký sai và webhook gửi lặp.

Khi cần tạm ngưng link mới, đặt `PAYOS_ENABLED=false` rồi tải lại cấu hình. Giữ kết nối webhook để các link đã phát hành vẫn có thể được đối chiếu.
