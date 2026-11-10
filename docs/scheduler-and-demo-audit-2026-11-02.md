# Kiểm tra lịch nhắc và dữ liệu mô phỏng — 02/11/2026

## Kết quả

- Tác vụ Alwaysdata **37205** đã được người dùng sửa sang PHP có nạp cấu hình của tài khoản; đã quan sát **10 lượt tự động thành công**, gồm hai lượt cuối liên tiếp exit code 0.
- Kiểm tra mốc lịch trong tiến trình riêng: 07:59 không đến hạn, **08:00 đến hạn**, 08:01 không đến hạn, múi giờ `Asia/Ho_Chi_Minh`. Không đổi giờ hệ thống.
- Thử lệnh nhắc với dữ liệu giả: lần đầu tạo **1 thông báo**, lần gửi lại tạo **0**, trả lại thông báo đã có. Đúng người nhận, người thuê khác không xem được; số dư hóa đơn giữ nguyên.
- MySQL **Aiven `defaultdb`** đã có các tình huống mô phỏng chính. Chưa có bản ghi mẫu cho mọi chức năng đã lập trình.
- Không đặt lại/gieo lại dữ liệu và không thay hạn thanh toán, hóa đơn, phiếu thu hoặc các thao tác người dùng đã thử. Phép thử nhắc bổ sung một thông báo mẫu.

## 1. Lịch nhắc hóa đơn

| Thuộc tính | Giá trị đã kiểm tra |
| --- | --- |
| Scheduled Task | 37205, RentBase Scheduler |
| Value đúng | `/usr/bin/php /home/rentbase/current/artisan schedule:run` |
| Working directory | `/home/rentbase/current` |
| SSH user | `rentbase` |
| Tần suất kiểm tra | Mỗi phút |
| Lịch nghiệp vụ | `0 8 * * *`, 08:00 giờ Việt Nam |
| Lệnh nghiệp vụ | `rental:remind-invoices --overdue` |
| Kênh nhắc | Thông báo riêng trong ứng dụng |

Nguyên nhân trước đó: gọi trực tiếp `/usr/alwaysdata/php/8.4/bin/php` không nạp `php.ini` của tài khoản, thiếu extension; log trả exit code 255. Dùng `/usr/bin/php` của Alwaysdata nạp đúng cấu hình. Hai lượt tự động đã đối chiếu lúc **09:39 và 09:40 giờ Việt Nam** trả mã 0; các bằng chứng mới nhất được giữ trong hồ sơ riêng ngoài Git.

Thông báo `No scheduled commands are ready to run` là bình thường ngoài 08:00. Kiểm tra hai lượt tự động này xác nhận bộ lịch chạy được; chưa quan sát trực tiếp một lượt đồng hồ thực tế 08:00 sau sửa. Lượt kế tiếp theo lịch là 08:00 ngày 03/11 nếu tác vụ tiếp tục hoạt động.

### Thử nghiệp vụ nhắc và chống trùng

| Ca | Kết quả |
| --- | --- |
| Chạy `--overdue --dry-run` | Có 1 hóa đơn ứng viên, không tạo thông báo |
| Chạy nhắc thật trên hóa đơn mẫu | `created=1`, `replayed=0`, `candidates=1` |
| Chạy lại trong cùng ngày | `created=0`, `replayed=1`, `candidates=1` |
| Người nhận | `nhom_demo`, đại diện phòng TB-201 |
| Hóa đơn | `HD-202610-2`, còn nợ 2.580.000 đồng, hạn 29/10/2026 |
| Quyền xem | Người nhận thấy thông báo; tài khoản `user` không thấy thông báo của người khác |
| Số dư | Không đổi trước/sau phép thử |
| Thông báo trong CSDL | Tăng từ 8 lên 9; không xóa các thông báo đã có |
| Tiến trình nhắc do Scheduler khởi chạy | Đã thực thi chính event trong tiến trình con: exit code 0, `created=0`, `replayed=1`; số dư và số thông báo giữ nguyên |

Hóa đơn đã thu đủ và hóa đơn chưa quá hạn không nằm trong danh sách của lần chạy `--overdue` này. Khóa chống trùng áp dụng theo người nhận, hóa đơn và ngày. Tài khoản người thuê thử có hóa đơn hạn 08/11 nên chưa nhận nhắc quá hạn cho hóa đơn đó.

Đây là thông báo ở mục chuông của ứng dụng; việc cài PWA không tự biến nó thành Web Push khi đóng ứng dụng.

## 2. Dữ liệu online hiện có

| Nhóm | Số lượng/ý nghĩa |
| --- | --- |
| Bảng nghiệp vụ | 28 bảng; bảng `migrations` được quản lý riêng |
| Phòng | 5 |
| Người thuê / tài khoản | 4 người thuê, 5 tài khoản gồm chủ trọ |
| Nhóm thuê / đợt thuê | 3 nhóm, 3 đợt đang mở |
| Dịch vụ / khoảng áp dụng | 3 dịch vụ, 9 khoảng áp dụng |
| Chỉ số điện/nước | 12 bản ghi cho các kỳ mẫu |
| Hóa đơn / dòng chi tiết | 3 hóa đơn, 12 dòng |
| Phiếu thu hóa đơn | 3 |
| Khoản thu chi riêng | 5 khoản chi, gồm vật tư sửa chữa và khoản nghiệm thu nhỏ có ghi dữ liệu mẫu |
| Sự cố / ảnh | 2 sự cố, 3 ảnh; cả 3 tệp tồn tại và khớp SHA256 lưu trong CSDL |
| Lịch sử sửa chữa | 4 mốc: 2 lần gửi và 2 lần cập nhật |
| Chi phí sửa chữa | 1 liên kết đang hiệu lực, 1 mốc lịch sử liên kết |
| Thông báo chung | 1 thông báo toàn nhà, gửi đến 4 tài khoản người thuê |

### Phòng và người thuê

| Phòng | Tình huống |
| --- | --- |
| TEST-USER | 1 người / sức chứa 1, gắn tài khoản `user` |
| TB-201 | 2 người / sức chứa 2, có người đại diện |
| TB-202 | 1 người / sức chứa 1, có chỉ số để thử lập hóa đơn |
| TB-203 | Phòng trống |
| TB-204 | Phòng bảo trì |

### Hóa đơn và công nợ

| Hóa đơn | Phòng/kỳ | Tổng | Đã thu | Còn nợ | Tình huống |
| --- | --- | ---: | ---: | ---: | --- |
| HD-202609-2 | TB-201, 09/2026 | 3.080.000 | 3.080.000 | 0 | Đã thu đủ |
| HD-202610-2 | TB-201, 10/2026 | 3.080.000 | 500.000 | 2.580.000 | Thu một phần, quá hạn |
| HD-202610-1 | TEST-USER, 10/2026 | 3.080.000 | 1.000.000 | 2.080.000 | Thu một phần, chưa đến hạn |

Tổng công nợ của ba hóa đơn là **4.660.000 đồng**. Chọn tháng **09 hoặc 10/2026** trên giao diện để xem các hóa đơn này. Tháng 11 mặc định chưa có hóa đơn mẫu được phát hành trong tháng đó.

Đã chạy **xem trước** hóa đơn của **TB-202, kỳ 10/2026**: `can_issue=true`, tổng **3.080.000 đồng**, không có blocker. Chưa phát hành hóa đơn này để người dùng còn tình huống tự thử trên giao diện.

### Sửa chữa và thông báo

- Có 1 yêu cầu vừa gửi của tài khoản `user`, có ảnh để xem và có thể thử bổ sung ảnh/xử lý.
- Có 1 yêu cầu đã hoàn tất tại TB-201, có lịch sử tiếp nhận → xử lý → hoàn tất và khoản chi vật tư 150.000 đồng liên kết.
- Có thông báo chung toàn nhà và thông báo cập nhật sửa chữa.
- Có thêm thông báo nhắc quá hạn được kiểm tra trong lượt nghiệm thu này.

## 3. Những tình huống chưa có bản ghi mẫu sẵn

Các mục dưới đây là **thiếu dữ liệu minh họa online**, không có nghĩa chức năng chưa được lập trình:

| Nhóm | Chưa có mẫu sẵn |
| --- | --- |
| Quá trình thuê | Chuyển/trả phòng, thành viên rời nhóm, thay người đại diện; hiện chưa có đợt/lượt ở đã kết thúc |
| Phí dịch vụ | Đổi giá giữa kỳ, nhiều khoảng giá trên cùng đợt/dịch vụ, tính theo người hoặc phát sinh giữa tháng |
| Hóa đơn | Hóa đơn chưa thu lần nào; lịch sử điều chỉnh, hủy/khôi phục; bảng `invoice_changes` còn 0 dòng |
| Phiếu thu | Sửa sai/hủy hiệu lực/thay thế phiếu; bảng `payment_corrections` còn 0 dòng |
| Thu chi | Thu khác; sửa/xóa/hoàn tác/khôi phục khoản; lịch sử hiện chỉ có thao tác tạo |
| Sửa chữa | Yêu cầu đang dừng ở trạng thái xử lý; lịch sử gỡ/đổi liên kết chi phí |
| Thông báo chung | Gửi riêng theo phòng và trạng thái đã đọc |
| Nhập nhiều phòng | Chưa có lịch sử yêu cầu nhập hàng loạt trong `meter_reading_requests`; chỉ số mẫu hiện có được tạo bằng nghiệp vụ ghi từng kỳ |

Bộ mẫu hiện tại dùng được cho các luồng trình chính. Nếu chọn mở rộng dữ liệu, bổ sung vào phòng/nhóm có dấu hiệu dữ liệu mẫu riêng, giữ nguyên hóa đơn và các thao tác đã thử; chuẩn bị bản sao trước khi làm. Không chạy lại bộ khởi tạo CSDL rỗng trên dữ liệu hiện có.

## 4. Bằng chứng và phạm vi kiểm tra

Hồ sơ riêng tại `D:\KLTN\.cache\deployment\rentbase\` có các tệp:

- `scenario-audit-20261102.json`: số lượng, tình huống, quyền cấp hóa đơn và đối chiếu ảnh từ MySQL online.
- `scheduler-job-verification-20261102.json`: lệnh đúng và các lượt tự động đã quan sát thành công.
- `reminder-verification-20261102.json`: mốc giờ, hai lượt lệnh nhắc, người nhận, chống trùng và số dư.
- `ready-invoice-audit-20261102.json`: kết quả xem trước TB-202.
- `scheduled-child-verification-20261102.json`: kết quả thực thi tiến trình nhắc do Scheduler tạo, ngoài giờ đến hạn và sau khi thông báo mẫu đã tồn tại.
- `handoff.json`: trạng thái bàn giao cập nhật.

Lượt này kiểm tra cấu hình và dữ liệu trên hosting thật; không thay mã nghiệp vụ, không chạy lại toàn bộ 406/134 kiểm thử và không gộp kết quả này thành số kiểm thử mới. Cài Android đã được người dùng xác nhận trước đó; camera/PDF và ca tắt Windows/dùng 4G trên thiết bị thật vẫn là bước nghiệm thu riêng.
