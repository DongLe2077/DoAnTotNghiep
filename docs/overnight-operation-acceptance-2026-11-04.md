# Kiểm tra vận hành sau một đêm — 04/11/2026

## Kết quả

**Bước 1 đạt trong lần kiểm tra sáng 04/11, giờ Việt Nam.** Website `https://rentbase.alwaysdata.net` kết nối MariaDB Alwaysdata, đăng nhập hai vai trò thành công. Đã xác nhận hai lượt chạy theo đồng hồ thật sau chuyển CSDL: sao lưu **02:15** và nhắc quá hạn **08:00**. Không dùng lượt chạy thủ công để thay bằng chứng này.

Việc kiểm tra nghiệp vụ chỉ đọc; các POST qua HTTPS giới hạn ở đăng nhập/đăng xuất. Không tạo hóa đơn, phiếu thu, khoản chi hay gửi nhắc bù trong lần kiểm tra này.

## Lịch tự động thực tế

Scheduled Task **37205** tiếp tục gọi `/usr/bin/php /home/rentbase/current/artisan schedule:run` mỗi phút. Nhật ký hosting dùng `+0100`; toàn bộ thời gian dưới đây đã đổi sang Việt Nam `+0700`.

| Nội dung | Bằng chứng thực tế | Kết quả |
| --- | --- | --- |
| Sao lưu 02:15 | Scheduler khởi chạy lúc **02:15:06**, tạo xong bản sao lúc **02:15:07**, tiến trình ngoài kết thúc mã 0 lúc **02:15:08** ngày 04/11 | Đạt |
| Nhắc quá hạn 08:00 | Scheduler khởi chạy tiến trình nhắc lúc **08:00:12**, CSDL ghi thông báo lúc **08:00:15**, tiến trình ngoài kết thúc mã 0 lúc **08:00:17** ngày 04/11 | Đạt |
| Các lượt kiểm tra lịch khác | Hơn 1.400 lượt hoàn tất kể từ chuyển CSDL, chưa ghi nhận mã kết thúc khác 0 trong phần log đã đọc | Đạt tại thời điểm kiểm tra |
| Lỗi ứng dụng | Không có bản ghi ERROR/CRITICAL/ALERT/EMERGENCY mới trong `laravel.log` kể từ lúc chuyển | Chưa phát hiện lỗi |

Ngoài giờ đến hạn, `No scheduled commands are ready to run` là kết quả bình thường. Mã 0 của tiến trình lịch được đối chiếu thêm với kết quả sao lưu và thông báo trong CSDL, không dùng riêng mã 0 để kết luận nghiệp vụ thành công.

## Nhắc hóa đơn và số tiền

- Hôm nay có **1** thông báo nhắc mới: thông báo 11, hóa đơn **HD-202610-2**, người nhận là tài khoản đang được cấp quyền xem hóa đơn đó.
- Khóa chống trùng đúng ngày/người nhận/hóa đơn; không có nhóm thông báo trùng theo ba trường này. Đây là đối chiếu dữ liệu và khóa hiện có, không phải gửi thử một lượt nhắc mới.
- Người nhận được xem thông báo; tài khoản `user` của phòng khác không nhìn thấy thông báo/hóa đơn này. Hóa đơn của `user` đến hạn 08/11 nên chưa thuộc lượt nhắc quá hạn hôm nay.
- Tổng thông báo tăng từ **10 lên 11**. So với snapshot lúc chuyển, chỉ bảng thông báo thay đổi tại thời điểm đối chiếu **08:52:43 ngày 04/11**.
- Các bảng hóa đơn, chi tiết, phiếu thu và thu chi giữ nguyên toàn bộ dòng dữ liệu so với lúc chuyển. Tổng tiền:

| Chỉ tiêu | Số tiền |
| --- | ---: |
| Tổng hóa đơn | 9.240.000đ |
| Đã thu theo hóa đơn | 4.580.000đ |
| Còn cần thu | **4.660.000đ** |
| Trong đó quá hạn | **2.580.000đ** |

Đủ **58** quan hệ khóa ngoại được kiểm tra, không có dòng mồ côi. Còn đủ 5 phòng, 4 người thuê, 3 đợt thuê, 12 chỉ số, 3 hóa đơn, 3 phiếu thu, 5 khoản chi, 2 yêu cầu sửa chữa và 3 ảnh.

## Kiểm tra bản sao lưu sáng nay

- Bản sao chứa **29 bảng** gồm migrations, **3 view**, dữ liệu native MariaDB, cấu hình cần khôi phục và **3 ảnh**.
- Đã đọc trực tiếp nội dung gói, giải nén phần dữ liệu trong bộ nhớ; số dòng khớp manifest, mã SHA256 của dữ liệu và toàn bộ ảnh khớp. Cấu hình trong gói khớp cấu hình live tại thời điểm đối chiếu.
- Đã tải bản sao xuống thư mục riêng trên Windows và đối chiếu SHA256 với hosting:

```text
D:\KLTN\.cache\deployment\rentbase\backups\mariadb-20261103-191507-26c8b7.tar.gz
SHA256: bde673edd95accae76c622cd857612c073af2bd6257071b8e334f3a3dce6da48
Dung lượng: 150.855 byte
```

Tên file dùng UTC: `20261103-191507` tương ứng **02:15:07 ngày 04/11 tại Việt Nam**. Gói có thông tin xác thực nên giữ riêng, không đưa vào Git/GitHub hoặc thư mục web công khai.

Hôm nay kiểm tra tính toàn vẹn của gói và bản tải xuống, **không khôi phục đè lên CSDL đang phục vụ**. Ca khôi phục native MariaDB thật đã thực hiện trước khi mở website ngày 03/11; xem [biên bản chuyển CSDL](mariadb-migration-acceptance-2026-11-03.md).

## Website, đăng nhập, ảnh và PDF

**75 điểm kiểm tra qua HTTPS đạt**, sử dụng phiên mới độc lập cho `admin` và `user`:

- Đăng nhập/duy trì phiên/đăng xuất hai vai trò; dữ liệu đăng nhập không được cache.
- Đọc 12 đường dẫn nghiệp vụ chủ trọ và 5 đường dẫn người thuê; tổng quan kỳ 10/2026 và công nợ trả dữ liệu bình thường.
- Chặn truy cập sai vai trò, hóa đơn người khác và ảnh/PDF khi chưa đăng nhập.
- PDF hóa đơn trả tệp PDF hợp lệ; chủ trọ đọc được cả 3 ảnh, người thuê đọc được ảnh của yêu cầu mình.
- Cả 3 tệp ảnh gốc trên hosting còn tồn tại và khớp hash trong CSDL.
- Đã đăng nhập bằng giao diện thật, mở tổng quan và trang công nợ; giao diện hiển thị đúng **4.660.000đ**, trong đó **2.580.000đ** quá hạn.

Trong lượt đo này, riêng yêu cầu đăng nhập API mất khoảng **0,76 giây** mỗi tài khoản. Đây là số đo của lượt thử từ Windows, không phải cam kết tốc độ trên mọi mạng hoặc đo tải đông người.

## Bằng chứng và giới hạn

Hồ sơ riêng ngoài Git:

```text
D:\KLTN\.cache\deployment\rentbase\review\overnight-20261104\server-audit.json
D:\KLTN\.cache\deployment\rentbase\review\overnight-20261104\https-checks.json
D:\KLTN\.cache\overnight-receivables-20261104.jpg
```

Không phát hiện lỗi cần sửa mã/cấu hình trong phạm vi bước 1. Kết quả xác nhận lượt vận hành đêm 03 → sáng 04/11, không thay thế theo dõi nhiều ngày liên tiếp.

**Bước kế tiếp là nghiệm thu Android thật:** mở app từ biểu tượng sau một đêm, thử hai vai trò, chụp/gửi ảnh, mở PDF và đọc/ghi bằng 4G khi Windows tắt. Những thao tác thiết bị thật này chưa được đánh dấu đạt bởi các kiểm tra HTTPS trên máy tính.
