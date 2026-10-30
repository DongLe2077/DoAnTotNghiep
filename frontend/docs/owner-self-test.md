# Tự kiểm tra giao diện chủ trọ CT4–CT8

Cập nhật 25/10/2026. Mở [bản dùng thử](http://127.0.0.1:5174/owner), đăng nhập **admin / 123**. Bản này dùng dữ liệu mẫu riêng và giữ các thay đổi khi khởi động lại. Bạn có thể thêm/sửa/thu tiền mà không ảnh hưởng MySQL của dự án. Khi chạy frontend và backend thông thường, màn hình với MySQL ở cổng 5173 và dùng tài khoản ứng dụng của bạn. Trên cửa sổ hẹp, bấm **☰ Mở menu** để chọn các chức năng.

Nếu cần khởi động lại: mở terminal trong `D:\KLTN\frontend`, chạy `npm run demo`, giữ cửa sổ chạy trong lúc dùng. Cần PHP trên PATH, backend đã cài Composer, cổng 5174 và 18001 trống. Dừng bằng Ctrl+C trước khi chạy lại. Nếu báo cổng đang được sử dụng, thử mở trang đăng nhập vì bản cũ có thể đang chạy. Các tab cùng trình duyệt dùng chung phiên; dùng trình duyệt/hồ sơ khác hoặc cửa sổ ẩn danh để thử hai vai trò đồng thời. Tài khoản mẫu chỉ tồn tại ở bản này.

## 1. Tổng quan CT8

Sau đăng nhập, xem thẻ phòng/người ở hiện tại, yêu cầu sửa chữa, thực thu/chi, hóa đơn kỳ chọn và công nợ mọi tháng. Chọn một tháng cũ chưa có phát sinh rồi bấm **Cập nhật**: thu/chi và hóa đơn tháng đó thay đổi; số người đang ở và công nợ mọi tháng vẫn theo hiện tại. Nhấn các liên kết để mở danh sách tương ứng.

Phần **Thống kê trực quan** có biểu đồ tròn trạng thái hóa đơn, biểu đồ cột tối đa 6 tháng và thanh phân bố phòng. Bấm chú giải trạng thái để mở đúng danh sách hóa đơn. Trong biểu đồ cột, chuyển **Hóa đơn / Thu / chi**, bấm cột tháng để xem số tiền chính xác và mở danh sách tháng đó. Thu nợ cũ được tính vào tháng thực nhận ở chế độ Thu / chi, còn chế độ Hóa đơn vẫn tính theo kỳ của hóa đơn. Biểu đồ phòng luôn là hiện tại. Tháng rỗng không tạo số liệu minh họa.

## 2. Điện nước và phát hành hóa đơn CT4

Nếu hóa đơn B202 đã được phát hành trong lúc dùng thử, mở lại từ **Hóa đơn** rồi tiếp tục mục 3. Chỉ số của kỳ đó đã khóa; không cần tạo thêm hóa đơn cùng tháng. Các thao tác thử được giữ cả khi bạn khởi động lại bản dùng thử.

1. Mở **Điện nước**, chọn đợt thuê **B202 / NHOM-HOANG**. Đã có chỉ số tháng vừa kết thúc: điện 100 → 130, nước 20 → 25. Có thể xem/sửa trước khi phát hành hóa đơn. Khoảng ngày phải khớp tháng và thời gian áp dụng dịch vụ.
2. Mở **Hóa đơn → Lập hóa đơn**, chọn cùng đợt thuê và tháng vừa kết thúc. Bấm xem trước, kiểm tra tiền phòng, điện, nước, internet, cảnh báo và tổng tiền. Với dữ liệu mẫu ban đầu, tổng là **3.080.000 ₫**: phòng 2.800.000, điện 105.000, nước 75.000, internet 100.000.
3. Xác nhận phát hành, mở chi tiết. Tải lại trang để kiểm tra đã lưu. Quay lại điện nước: chỉ số liên quan đã khóa.
4. Trong chi tiết hóa đơn, thử cấp/thu hồi quyền xem cho tài khoản phù hợp và gửi nhắc thanh toán khi còn nợ. Nếu dữ liệu thay đổi sau xem trước, giao diện yêu cầu xem trước lại.

Không phát hành lại cùng kỳ chỉ để sửa chỉ số. Hóa đơn đã có điều chỉnh, hủy/khôi phục có lý do và lịch sử theo [quy tắc hiện tại](invoice-controls-self-test.md); dòng gốc và phiếu thu được giữ nguyên. Chưa có xử lý hoàn tiền phiếu thu.

## 3. Thu tiền, biên nhận và công nợ CT5

1. Mở hóa đơn B202 vừa tạo → **Thu tiền**, nhập số nhỏ hơn còn cần thu, chọn thời điểm/phương thức → xem lại → xác nhận.
2. Kiểm tra nhãn thu một phần, số đã thu/còn thiếu và biên nhận; tải lại để kiểm tra kết quả.
3. Mở **Công nợ**: thử từng hóa đơn, gộp theo phòng và nhóm; mở liên kết quay về đúng phạm vi hóa đơn.
4. Thu tiếp toàn bộ số còn thiếu. Hóa đơn trở thành đã thu đủ, không còn trong danh sách công nợ; các biên nhận vẫn còn trong lịch sử.
5. Mở **Thu chi** để thấy khoản thu hóa đơn tự xuất hiện. Không tạo thêm thu khác để ghi lại cùng lần thu.

Nếu mất kết nối sau khi gửi, giữ yêu cầu đang chờ và dùng nút gửi lại; giao diện giữ nguyên nội dung/mã yêu cầu. Đừng nhập một khoản mới khi chưa xác định kết quả cũ. Các ca mất phản hồi/hết phiên/gửi lại đã có kiểm thử tự động.

## 4. Thu chi, nhật ký và hoàn tác CT6

1. Mở **Thu chi → Thêm khoản thu chi**. Tạo chi phí 150000 với nội dung dễ nhận biết, xem lại rồi xác nhận.
2. Tìm khoản vừa tạo, mở chi tiết và sửa số tiền/nội dung với lý do. Xem nhật ký trước/sau và người/thời điểm thay đổi.
3. Xóa khoản với lý do; bật danh sách khoản đã xóa và mở chi tiết.
4. Chọn hoàn tác sự kiện mới nhất, xem lại rồi xác nhận: khoản xóa được khôi phục và tổng chi cập nhật.
5. Thử lọc tháng hoặc khoảng ngày, loại khoản và tổng hợp theo ngày/tháng. Thu hóa đơn chỉ xem và mở biên nhận; sửa/xóa chỉ áp dụng khoản thu khác/chi phí.

Hai tab sửa cùng khoản có thể gặp thông báo dữ liệu đã đổi. Tải lại và chọn lại thao tác trên phiên bản mới. Hoàn tác chỉ áp dụng sự kiện mới nhất còn hợp lệ, không tự hoàn tác tiếp một sự kiện khác.

## 5. Báo hỏng và sửa chữa CT7

1. Mở **Sửa chữa**, lọc trạng thái/phòng/người gửi hoặc tìm nội dung. Mẫu B202 có yêu cầu **Vòi nước bếp bị rò**; các yêu cầu mẫu khác có ảnh riêng tư để kiểm tra hiển thị.
2. Mở yêu cầu chưa xử lý → tiếp nhận/đang xử lý, ghi tiến độ → xem lại và xác nhận.
3. Hoàn tất kèm kết quả sửa chữa. Tải lại: trạng thái/kết quả được lưu, yêu cầu hoàn tất không còn cho xử lý tiếp.
4. Kiểm tra chuông/hộp thông báo. Backend tạo cập nhật cho tác giả báo hỏng; đăng nhập `user / 123` để [thử gửi báo hỏng/ảnh và theo dõi kết quả](tenant-self-test.md). Các tab cùng trình duyệt dùng chung phiên, nên đổi tài khoản lần lượt hoặc dùng hai trình duyệt/hồ sơ riêng.

## 6. Các màn hình CT1–CT3 và bố cục

Tiếp tục thử Phòng, Dịch vụ, Người thuê và Quá trình thuê bằng dữ liệu mẫu. Thu nhỏ cửa sổ để kiểm tra menu thu gọn và biểu mẫu. Mỗi thao tác ảnh hưởng dữ liệu có bước xem lại hoặc xác nhận phù hợp. Đăng xuất/đăng nhập lại, kiểm tra không giữ nội dung riêng tư của tài khoản trước.

NT1–NT4 đã có [giao diện riêng cho người thuê](tenant-self-test.md). Đã có điều chỉnh/hủy hóa đơn, [chi phí sửa chữa](repair-expenses-self-test.md) và [sửa sai phiếu thu](payment-corrections-self-test.md). Xuất PDF/Excel và hoàn tiền thực tế chưa triển khai. Quên mật khẩu và đăng ký công khai vẫn để sau theo quyết định trước đó. Bản dùng thử chạy tại máy này; chưa triển khai lên máy chủ công khai.
