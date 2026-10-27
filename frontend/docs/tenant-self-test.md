# Tự kiểm tra giao diện người thuê NT1–NT4

Đã bổ sung nhận thông báo chung của chủ trọ trong NT4, có bộ lọc riêng và trang nội dung đầy đủ. [Thử gửi/nhận bằng admin và user](announcements-self-test.md).

Cập nhật 23/10/2026. Giao diện dùng API Laravel thật, phiên đăng nhập và CSRF; không dùng số liệu minh họa trên các màn hình nghiệp vụ.

Đã đạt 110 kiểm thử frontend, 62 kiểm thử trình duyệt hai vai trò và bản build/TypeScript. Các ca NT bao gồm quyền theo tài khoản, thu một phần/đủ/thu hồi quyền, ảnh lỗi/thử lại sau mất phản hồi, thông báo sửa chữa sau trả phòng và điện thoại. Backend đạt 309 kiểm thử; kiểm thử ảnh riêng tư được chạy lại sau cập nhật thông báo lỗi tiếng Việt. Tất cả dữ liệu tự động là SQLite thử riêng.

## Mở bản dùng thử

- Địa chỉ: [Phòng của tôi](http://127.0.0.1:5174/tenant).
- Người thuê mẫu: **user / 123**.
- Chủ trọ mẫu: **admin / 123**.
- Bản dùng thử tách dữ liệu MySQL. Dùng `npm run demo` trong `D:\KLTN\frontend` khi cần khởi động; khởi động lại giữ dữ liệu đã thử. Tài khoản user có phòng TEST-USER, dịch vụ và hóa đơn mẫu.
- Các tab cùng trình duyệt dùng chung phiên. Muốn xem hai vai trò cùng lúc, dùng hai trình duyệt hoặc hai hồ sơ trình duyệt riêng; hoặc đăng xuất rồi đổi tài khoản lần lượt.

Trên điện thoại có bốn mục dưới cùng: **Phòng · Hóa đơn · Báo hỏng · Cá nhân**. Mục Cá nhân dẫn tới hồ sơ, lịch sử và tài khoản; menu ☰ vẫn chứa đầy đủ các đường dẫn.

## NT1. Phòng, hồ sơ và lịch sử

1. Đăng nhập người thuê, xem phòng B202, nhóm, thời điểm vào ở và giá thuê **toàn phòng** đã chốt. Giá này không phải phần phải trả riêng của từng người.
2. Xem dịch vụ và đơn giá đã áp dụng. Chủ trọ thay giá danh mục không tự đổi giá đã chốt cho đợt thuê.
3. Mở **Cá nhân** để xem tên, điện thoại, giấy tờ của chính mình. Nếu cần sửa, liên hệ chủ trọ; không có nút tự sửa thông tin nhân thân.
4. Mở **Lịch sử cư trú** để xem các lần vào/rời phòng của mình. Sau chuyển/trả phòng, làm mới để thấy thay đổi.
5. Người chưa có phòng được thông báo rõ và vẫn mở được lịch sử, hóa đơn còn quyền, yêu cầu do mình gửi. Tài khoản chưa liên kết hồ sơ được hướng dẫn liên hệ chủ trọ.

## NT2. Hóa đơn và các lần thanh toán

1. Chủ trọ phát hành hóa đơn cho B202 hoặc cấp quyền xem cho đại diện phù hợp. Nếu chưa có, làm theo [luồng CT4–CT5](owner-self-test.md).
2. Đăng nhập người thuê, mở **Hóa đơn**, lọc kỳ/trạng thái rồi mở chi tiết. Kiểm tra từng dòng phòng/dịch vụ/chỉ số và số đã thanh toán/còn thiếu.
3. Chủ trọ thu một phần, người thuê bấm **Cập nhật thanh toán**: số dư và lịch sử/biên nhận thay đổi. Thu đủ thì nhãn thành **Đã thanh toán đủ**.
4. Chủ trọ thu hồi quyền: khi tải lại/đọc lại hoặc quay lại tab, chi tiết không còn hiển thị dữ liệu cũ. Danh sách chỉ còn những hóa đơn được cấp quyền.

Người thuê không có nút ghi nhận thu tiền hoặc tự xác nhận đã trả tiền. Đây là lịch sử chủ trọ đã ghi nhận, chưa phải thanh toán trực tuyến. Đổi đại diện không tự chuyển quyền hóa đơn cũ.

## NT3. Báo hỏng và ảnh sự cố

1. Vào **Báo hỏng → Gửi báo hỏng**. Chỉ đại diện hiện tại đang cư trú được gửi mới; thành viên thường hoặc người đã rời phòng vẫn xem được yêu cầu mình từng gửi.
2. Chọn phòng đủ quyền, nhập tiêu đề/mô tả. Bấm **Chọn ảnh từ thiết bị**, xem trước và bỏ ảnh nếu chọn nhầm. Tối đa 5 ảnh/yêu cầu, JPG/PNG/WebP, mỗi ảnh tối đa 5 MB và 4096 × 4096 px.
3. Bấm **Xem lại trước khi gửi → Xác nhận gửi báo hỏng**. Nội dung được tạo trước rồi mới gửi ảnh. Nếu ảnh lỗi, màn hình báo rõ yêu cầu đã lưu và cho gửi lại **chỉ ảnh**, không tạo lại báo hỏng.
4. Khi mất phản hồi tải ảnh, giữ các ảnh đang chờ và gửi lại; backend nhận diện nội dung ảnh trùng để không lưu bản sao. Ảnh được mở qua API riêng tư, không có đường dẫn công khai.
5. Mở chi tiết để xem ảnh phóng to và tiến độ. Có thể bổ sung ảnh trong lúc chưa hoàn tất và còn chỗ; tổng cộng tối đa 5 ảnh.
6. Chủ trọ tiếp nhận/ghi tiến độ/hoàn tất. Người thuê bấm **Cập nhật tiến độ** hoặc mở từ thông báo để xem kết quả. Yêu cầu đã hoàn tất vẫn xem ảnh được nhưng không nhận thêm ảnh/sửa nội dung.
7. Sau chuyển/trả phòng, người gửi vẫn xem yêu cầu cũ của mình; người đại diện mới không được đọc báo hỏng do tài khoản khác gửi.

Nếu mất phản hồi khi **tạo yêu cầu**, hãy kiểm tra danh sách trước khi gửi lại. Nội dung trùng chỉ được gộp nếu yêu cầu cũ còn chưa hoàn tất. Ảnh chưa gửi chỉ giữ trong bộ nhớ trang, không lưu vào browser storage; rời trang/tải lại sẽ phải chọn lại ảnh cần bổ sung.

## NT4. Thông báo và tài khoản

Mở chuông để xem/đọc thông báo. **Xem nội dung** tải lại đối tượng theo quyền; **Mở trang chi tiết** chuyển tới đúng hóa đơn/báo hỏng. Có lọc/phân trang, đọc một/tất cả. **Tài khoản** dùng lại đổi mật khẩu và đăng xuất; đổi mật khẩu thành công yêu cầu đăng nhập lại.

Quên mật khẩu, đăng ký công khai, tự sửa giấy tờ, thanh toán trực tuyến và phân công thợ chưa nằm trong phạm vi hiện tại. Giao diện không cho người thuê truy cập sổ thu chi, người thuê khác hoặc các chức năng quản trị.

Cập nhật nghiệm thu 23/10/2026: 319 backend, 115 frontend và 66 kịch bản Chrome đạt; đã bổ sung hạn thanh toán, công nợ quá hạn và điều chỉnh/hủy/khôi phục hóa đơn. Xem [biên bản mới nhất](business-acceptance.md) và [tự thử hóa đơn](invoice-controls-self-test.md). Các số kiểm thử phía trên là của đợt hoàn thiện NT trước đó.
