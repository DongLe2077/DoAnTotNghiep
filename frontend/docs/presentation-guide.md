# Bản trình sản phẩm và tự thử hai vai trò

Bản trình có CSDL, ảnh, phiên đăng nhập và cổng riêng. Dữ liệu giả được giữ sau khi khởi động lại; chức năng vẫn gọi Laravel thật. Bản này không dùng MySQL `rental_management` hoặc bản thử cũ cổng 5174.

## Khởi động bằng CMD

```bat
cd /d D:\KLTN\frontend
set "PHP_BINARY=C:\xampp\php\php.exe"
npm run presentation
```

Đợi thông báo PHP đã chạy tại cổng 18002 rồi mở **http://127.0.0.1:5175/login**. Giữ cửa sổ CMD trong lúc thử; nhấn **Ctrl+C** để dừng. Nếu báo cổng đang được sử dụng, kiểm tra bản đang mở hoặc dừng đúng cửa sổ cũ trước khi chạy thêm.

Thư viện Composer của backend và npm của frontend phải được cài trước. Đường dẫn PHP trên phù hợp máy phát triển hiện tại; máy khác dùng PHP tương ứng. Bản trình dùng SQLite và tạo ảnh mẫu bằng GD.

| Tên đăng nhập | Mật khẩu ban đầu | Tình huống |
| --- | --- | --- |
| admin | 123 | Chủ trọ, đầy đủ chức năng quản lý |
| user | 123 | Đại diện phòng một người TEST-USER, hóa đơn đã thu một phần, sự cố có ảnh |
| nhom_demo | 123 | Đại diện TB-201, hai thành viên, hóa đơn thu đủ/quá hạn và lịch sử sửa chữa |
| thanhvien_demo | 123 | Thành viên thường TB-201, thấy phòng nhưng không tự có quyền hóa đơn/gửi báo hỏng |
| phong_demo | 123 | Đại diện TB-202, phòng có kỳ sẵn sàng lập hóa đơn |

Mở chủ trọ và người thuê bằng hai trình duyệt hoặc hai hồ sơ riêng. Các tab trong cùng hồ sơ dùng chung phiên. Mật khẩu đã đổi được giữ khi khởi động lại; tài khoản mẫu này phục vụ bản thử cục bộ.

## Dữ liệu đã chuẩn bị

Ở bộ dữ liệu tạo ngày 30/10/2026:

| Tình huống | Dữ liệu để đối chiếu |
| --- | --- |
| TB-201 tháng 08/2026 | Hóa đơn 3.080.000 đồng, đã thu đủ |
| TB-201 tháng 09/2026 | Hóa đơn 3.080.000 đồng, đã thu 500.000, còn 2.580.000; hạn thanh toán trong quá khứ |
| TEST-USER tháng 09/2026 | Hóa đơn 3.080.000 đồng, đã thu 1.000.000, còn 2.080.000 |
| TB-202 tháng 09/2026 | Đã có chỉ số, chưa phát hành hóa đơn; dùng để thử xem trước/phát hành |
| TB-203 / TB-204 | Một phòng trống / một phòng bảo trì |
| Sửa chữa TB-201 | Có ảnh minh họa, tiếp nhận → xử lý → hoàn tất, khoản chi vật tư 150.000 đồng liên kết |
| Báo hỏng TEST-USER | Đang chờ, có ảnh; dùng để thử cập nhật xử lý và thông báo |
| Thông tin nhà trọ | Tên/liên hệ/ngân hàng minh họa, xuất hiện trên PDF phù hợp |

Máy tạo dữ liệu lần đầu vào tháng khác sử dụng hai tháng trước thời điểm tạo. Mốc/ID chính xác được giữ trong `frontend/.cache/presentation/scenario.json`; bảng trên mô tả bộ đã tạo trên máy này. Những lần khởi động sau không ghi đè trạng thái bạn đã thử, tiền đã thu, mật khẩu hoặc thông tin nhà trọ.

Ảnh vòi nước được vẽ minh họa, không lấy ảnh cá nhân. Thông tin ngân hàng và liên hệ là dữ liệu giả.

## Kịch bản trình khoảng 10–12 phút

1. **Tổng quan:** đăng nhập admin, xem phòng đang ở/trống/bảo trì, hóa đơn quá hạn và biểu đồ. Giải thích tháng tính tiền khác ngày thực thu.
2. **Nhóm và quyền:** mở TB-201 có hai thành viên; đối chiếu `nhom_demo` và `thanhvien_demo` ở trình duyệt riêng. Thành viên thường không tự có quyền xem hóa đơn hoặc gửi báo hỏng.
3. **Chỉ số và hóa đơn:** mở Điện nước → Nhập nhiều phòng để giới thiệu xem lại/kết quả từng dòng. Muốn ghi thử, chọn tháng hiện tại và thời điểm cuối kỳ không ở tương lai. Dùng TB-202 kỳ đã hoàn tất trong bảng trên để xem trước/phát hành hóa đơn.
4. **Thu tiền:** mở hóa đơn TEST-USER, thu thêm một khoản nhỏ bằng chủ trọ, tải PDF. Làm mới phía user để thấy lần thu và số còn nợ thay đổi. Chỉ số mới không tự phát hành hóa đơn; tải PDF không tự xác nhận đã chuyển khoản.
5. **Sửa chữa:** mở sự cố đang chờ TEST-USER, chuyển sang đang xử lý và ghi chú; user xem lại lịch sử/thông báo. Mở sự cố TB-201 đã hoàn tất để xem ảnh và chi phí liên kết.
6. **Thông báo chung:** xem trước người nhận, gửi nội dung mẫu ngắn, người thuê mở hộp thông báo; chủ trọ xem trạng thái đọc.
7. **Tự thử:** dành thời gian cho giảng viên thao tác và ghi góp ý theo màn hình/bước/kết quả mong đợi.

Để trình ngắn, chuẩn bị sẵn các tab phòng/hóa đơn/sửa chữa trước. Các thao tác nhận/chuyển/trả phòng, hủy/thay thế phiếu thu, hoàn tác thu chi và lỗi quyền có thể mở thêm khi được hỏi. Tập ít nhất hai lượt và dùng bản sao để đưa dữ liệu về mốc đã biết giữa các lượt.

## Lưu mốc mã nguồn và dữ liệu

Dừng bản trình bằng Ctrl+C, sau đó:

```bat
cd /d D:\KLTN\frontend
set "PHP_BINARY=C:\xampp\php\php.exe"
npm run presentation:checkpoint
```

Lệnh tạo thư mục mới trong `.cache/presentation-checkpoints/`, gồm CSDL nhất quán, ảnh riêng tư, tình huống, mã nguồn hiện tại và bản đóng gói frontend nếu có. Cấu hình bí mật, thư viện cài đặt, phiên đăng nhập và Git nội bộ được loại khỏi gói mã nguồn. Gói chỉ chứa dữ liệu giả của bản trình, không sao chép MySQL hoặc ảnh người dùng thật.

Lệnh còn khôi phục thử vào thư mục `restored/` riêng, kiểm tra toàn bộ bản ghi, khóa ngoại, số dư hóa đơn và từng ảnh. `manifest.json` lưu mã nguồn, dấu kiểm tra tệp và kết quả. Đây là bản sao cục bộ; để phòng hỏng máy cần giữ thêm bản sao ở thiết bị khác.

## Đưa dữ liệu bản trình về mốc đã lưu

Dừng bản trình trước, lấy **tên thư mục bản sao** vừa tạo rồi chạy:

```bat
npm run presentation:restore -- MA_BAN_SAO
npm run presentation
```

Thay `MA_BAN_SAO` bằng tên dạng `2026-10-30T...Z-...` được lệnh sao lưu in ra. Lệnh kiểm tra bản sao trước khi khôi phục **dữ liệu và ảnh** của bản trình; giữ bản dữ liệu trước đó trong `.cache/presentation-restore-safety/`. Mã nguồn hiện tại và MySQL được giữ nguyên. Khi có lỗi trong bước thay dữ liệu, chương trình đưa CSDL/ảnh cũ về lại.

Khôi phục làm các thao tác thử sau mốc đó biến mất khỏi bản trình đang mở. Khi muốn giữ các thao tác mới, tạo checkpoint mới trước.

## Ảnh dự phòng và nghiệm thu

Chạy `node scripts/review-presentation.mjs` khi bản trình đang mở để đối chiếu mẫu ban đầu và tạo ảnh trong `.cache/presentation-review/`. Công cụ này dùng Chrome, đọc dữ liệu, chụp hai vai trò và kiểm tra PDF/quyền; không ghi nghiệp vụ. Nếu bạn đã thay đổi tiền/trạng thái mẫu, khôi phục mốc ban đầu trước khi chạy đối chiếu các số cố định.

Ảnh dự phòng gồm tổng quan, thông tin nhà trọ, hóa đơn thu một phần/quá hạn, sửa chữa/chi phí và màn hình điện thoại của người thuê/thành viên. Kết quả tự động lưu ở `review.json`.

Trước buổi xem: kiểm tra đúng bản, ảnh mở được, PDF tải được, nguồn điện và hai trình duyệt sẵn sàng. Điện thoại thật/máy trình bày khác vẫn cần bạn thử trực tiếp; ảnh chụp màn hình hẹp chỉ kiểm tra kích thước mô phỏng.
