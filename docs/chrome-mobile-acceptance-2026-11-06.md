# Nghiệm thu Chrome ở chế độ điện thoại — 06/11/2026

Đã kiểm thử bằng Google Chrome trên Windows với màn hình điện thoại mô phỏng, không dùng BlueStacks. Phạm vi gồm 41 kịch bản nghiệp vụ chính trên bộ dữ liệu riêng và 61 điểm đối chiếu bản online.

## Kết quả

| Nhóm | Kết quả |
| --- | --- |
| 41 kịch bản nghiệp vụ được chọn | 41/41 có kết quả đạt sau các lượt đối chiếu lại |
| Bản online trên Chrome 156.0.8078.80 | 61/61 điểm đạt |
| Lỗi JavaScript chưa được xử lý / HTTP 5xx trong lượt online | 0 / 0 |
| Yêu cầu ghi nghiệp vụ phát sinh trong lượt online | 0; chỉ cho phép đăng nhập/đăng xuất |
| PDF hóa đơn online của người thuê | Tải thành công, đúng đầu tệp `%PDF-`, 32.594 byte |
| Ảnh sự cố online | Mở được ảnh riêng tư trong hộp xem ảnh, ảnh đã tải và giải mã |
| PWA: mất mạng, giao dịch chờ và cập nhật | 31/31 điểm đạt trên bản build với Laravel/SQLite riêng |

Lượt 41 kịch bản đầu có 39 đạt và 2 kiểm tra cần điều chỉnh. Kiểm tra đăng nhập tìm tên tài khoản trong thanh bên đang ẩn trên điện thoại; đã đổi sang mở menu khi cần, cả sau đăng nhập và sau tải lại. Kiểm tra bố cục đọc hai hộp giới hạn qua hai thời điểm tải phông khác nhau; đã đổi sang chờ phông và đo các vùng trong cùng một lần đọc bố cục, giữ nguyên yêu cầu không chồng lấn. Lượt kiểm tra lại bố cục đạt 5/5; lượt xác nhận đăng nhập cuối đạt 1/1. Tổng hợp theo kết quả cuối của từng kịch bản: 41 đạt, 0 còn lỗi. Đây là tổng hợp các lượt chạy, không phải một lượt đầu tiên đạt toàn bộ.

Các điều chỉnh nằm trong cấu hình và mã kiểm thử. Chưa ghi nhận lỗi ứng dụng cần sửa trong phạm vi đã kiểm tra.

31 điểm PWA xác nhận: mở lại khung công khai khi mất mạng, không lấy lại hóa đơn riêng tư từ bộ nhớ đệm; giữ nguyên giao dịch chờ và chỉ gửi khi người dùng chủ động thử lại; cập nhật phiên bản không làm mất bản nhập ở tab khác; tải PDF thật và lưu ảnh riêng tư qua Laravel; điều hướng quay lại danh sách. Các thử nghiệm này dùng Chrome trên máy tính; chiều cao bàn phím và ảnh từ bộ chọn camera được mô phỏng, không phải camera/bàn phím Android thật.

## Nghiệp vụ đã thử

- Đăng nhập hai vai trò, mật khẩu sai, giữ phiên sau tải lại, đăng xuất, chặn trang/API sai vai trò.
- Tạo/sửa/xóa phòng và dịch vụ trong dữ liệu thử; giữ độ chính xác giá; hồ sơ người thuê và tài khoản tùy chọn.
- Một hoặc nhiều người cùng phòng, giới hạn sức chứa, đại diện, thời gian áp dụng dịch vụ, chuyển/trả phòng và lịch sử.
- Chỉ số từng phòng và nhiều phòng, chỉ số đầu/kết thúc, lượng tiêu thụ, khóa sau lập hóa đơn và phục hồi hàng bị mất phản hồi.
- Xem trước/lập hóa đơn, hạn thanh toán, công nợ quá hạn, điều chỉnh/hủy/khôi phục có kiểm soát.
- Thu tiền một phần/đủ, chặn thu vượt, gửi lại khoản thu mất phản hồi không ghi trùng; hủy phiếu thu sai làm mở lại công nợ.
- Thu chi: xem lại trước khi ghi, sửa, xóa mềm, hoàn tác và lịch sử; chống tạo trùng khi mất phản hồi.
- Báo hỏng: gửi ảnh, kiểm tra ảnh không hợp lệ, gửi lại ảnh, xử lý/hoàn thành và lịch sử; chi phí nội bộ không lộ sang người thuê.
- Thông báo chung: xem trước người nhận, chống đối tượng nhận thay đổi, phục hồi sau mất phản hồi và người thuê xem nội dung.
- Tổng quan, biểu đồ, bộ lọc công nợ và số liệu đối chiếu với API.
- PDF thật cho chủ trọ/người thuê, từ chối sau thu hồi quyền và ảnh riêng tư.

## Giao diện

Chrome mô phỏng điện thoại khởi đầu ở 390×844, có cảm ứng. Các kịch bản bố cục thử thêm màn hình hẹp 320px, chiều ngang, máy tính bảng và máy tính đến 1440px.

Bản online được duyệt ở 390×844, thêm 320×740 và 844×390 cho các màn hình trọng điểm. Đã kiểm tra các trang chủ trọ, các trang người thuê, menu mở/đóng/chuyển trang, tiêu đề cố định khi cuộn, thanh điều hướng dưới, bộ lọc tháng, trang lập hóa đơn và biểu mẫu báo hỏng. Không phát hiện cuộn ngang hoặc tiêu đề đè lên nút thao tác ở những trường hợp đã thử.

Ảnh bản online:

- [Đăng nhập](../frontend/.cache/mobile-review-20261106/online/01-login-390.png).
- [Tổng quan chủ trọ](../frontend/.cache/mobile-review-20261106/online/02-owner-dashboard-390.png).
- [Tiêu đề sau khi cuộn](../frontend/.cache/mobile-review-20261106/online/03-owner-sticky-header-390.png).
- [Phòng của người thuê](../frontend/.cache/mobile-review-20261106/online/04-tenant-home-390.png).
- [Chi tiết hóa đơn](../frontend/.cache/mobile-review-20261106/online/05-tenant-invoice-390.png).

## Dữ liệu và bằng chứng

Các kịch bản ghi dữ liệu chạy trên `frontend/.cache/e2e/auth.sqlite`, phiên/cache/storage riêng. Không kết nối MySQL cục bộ hoặc MariaDB online để tạo/sửa dữ liệu thử. Bộ chuẩn bị từ chối đường dẫn CSDL/storage nằm ngoài thư mục được quy định.

Lượt online tại `https://rentbase.alwaysdata.net` dùng phiên Chrome riêng. Bộ chặn yêu cầu chỉ cho GET/HEAD/OPTIONS và POST đăng nhập/đăng xuất; không tạo, sửa, xóa, đánh dấu thông báo đã đọc hoặc ghi thu tiền trên hosting.

Kết quả chi tiết trong `frontend/.cache/mobile-review-20261106/`:

- `summary.json`: kết quả cuối của từng kịch bản và lịch sử các lượt chạy.
- `results.json`, `recheck/results.json`, `auth-final/results.json`: kết quả gốc, giữ cả các lần kiểm tra cần sửa.
- `online/verification.json`: 61 điểm online và lỗi/yêu cầu ghi được ghi nhận.
- `pwa/verification.json`: 31 điểm mất mạng/cập nhật của bản build, kèm dấu hiệu rõ ràng chưa thử phần cứng Android.
- `business-screenshots/`: ảnh các bước nghiệp vụ trên dữ liệu thử.
- `online/`: ảnh từ hosting và PDF đã tải, lưu cục bộ trong thư mục bỏ qua Git.

## Chạy lại

Trong `D:\KLTN\frontend`, chạy `npm run test:e2e:mobile`. Cần Chrome đã cài, Node/PHP và các thư viện hiện có của dự án. Báo cáo mặc định ở `.cache/mobile-review/`; biến `MOBILE_REVIEW_OUTPUT` cho phép giữ báo cáo của từng lượt riêng. Không chạy đồng thời các bộ kiểm thử cùng khởi tạo lại `auth.sqlite`.

Để đối chiếu online, đặt mật khẩu của hai tài khoản kiểm thử trong biến môi trường `RENTBASE_REVIEW_OWNER_PASSWORD` và `RENTBASE_REVIEW_TENANT_PASSWORD`, rồi chạy `node scripts/review-online-mobile.mjs`. Kiểm tra mất mạng và cập nhật PWA bằng `node scripts/review-pwa-runtime.mjs` sau khi các kiểm thử CSDL riêng khác đã kết thúc.

## Phạm vi còn cần thiết bị thật

Đây là Chrome trên Windows mô phỏng điện thoại, chưa phải Android thật. Chưa xác nhận trong lượt này việc cài PWA và mở từ biểu tượng Android, camera thật, bàn phím/nút quay lại của Android, trình xem PDF Android hoặc chất lượng mạng di động thực tế. Ảnh đưa vào bộ chọn tệp trong kiểm thử là dữ liệu mẫu; không tính là nghiệm thu camera thiết bị.
