# Kế hoạch frontend riêng cho chủ trọ

Cập nhật ngày **23/10/2026**. CT1–CT8 đã có màn hình kết nối API; phần dưới lưu phạm vi và tiêu chí nghiệm thu. [Các màn hình chủ trọ](../../frontend/docs/owner-management.md), [bản dùng thử CT4–CT8](../../frontend/docs/owner-self-test.md). NT1–NT4 đã có giao diện/API và kiểm thử liên thông; xem hướng dẫn người thuê để thử cả hai vai trò.

## 1. Phạm vi và bố cục

Chủ trọ đã có `/owner`, menu riêng và màn hình tài khoản trong ứng dụng Vue tại `D:\KLTN\frontend`. Đăng nhập/đăng xuất/đổi mật khẩu, thông báo và toàn bộ CT1–CT8 kết nối Laravel; các trang chủ trọ không còn dùng trang chờ.

- Menu bên trái trên máy tính: Tổng quan, Phòng, Dịch vụ, Người thuê, Quá trình thuê, Điện nước, Hóa đơn, Công nợ, Thu chi, Sửa chữa.
- Thanh trên đã có tên màn hình, chuông thông báo/số chưa đọc, trạng thái phiên và đăng xuất; tài khoản ở menu. Hộp `/notifications` đã kết nối API dùng chung, có bộ lọc/phân trang/đọc và mở nội dung liên quan chỉ đọc.
- Trên điện thoại: menu thu gọn, bộ lọc dễ mở; bảng chuyển bố cục phù hợp, không chỉ thu nhỏ chữ.
- Mỗi danh sách có tìm kiếm/lọc/phân trang phù hợp API, trạng thái tải/rỗng/lỗi và liên kết xem chi tiết.
- Sau đăng nhập mở Tổng quan với số liệu API CT8; các chỉ số mở được danh sách liên quan.

Nền tảng P0 và TK0 đã được áp dụng vào CT1–CT8 dùng API thật. Mẫu `/design?view=owner` vẫn dùng dữ liệu minh họa để tham khảo bố cục. [Quy chuẩn thiết kế](../../frontend/docs/design-system.md), [kế hoạch chung](frontend-plan.md).

## Bản thiết kế màn hình cần chuẩn bị

| Gói | Bố cục và màn hình | Thao tác chính cần nổi bật |
| --- | --- | --- |
| CT1 Phòng | Thanh tìm kiếm/bộ lọc + danh sách phòng; số phòng, giá, số người/sức chứa, trạng thái; trang chi tiết và biểu mẫu thêm/sửa | Thêm phòng, xem chi tiết, sửa thông tin; trạng thái trống/có người phải đọc được bằng chữ. |
| CT1 Dịch vụ | Danh sách tên/đơn vị/cách tính/giá mặc định/trạng thái; biểu mẫu phù hợp kiểu dịch vụ | Thêm/sửa, bật/tắt theo API; phân biệt giá danh mục với giá đã chốt. |
| CT2 Người thuê | Danh sách + tìm kiếm; trang hồ sơ chia thông tin cá nhân, tài khoản, lịch sử | Thêm người thuê, sửa hồ sơ, thao tác tài khoản theo API. |
| CT3 Quá trình thuê | Danh sách đợt thuê; chi tiết có thành viên/đại diện, dịch vụ, thời gian và liên kết hóa đơn; luồng nhận phòng gồm chọn phòng → chọn người/đại diện → kiểm tra dịch vụ → xem lại | Nhận phòng; thêm/rời thành viên; chuyển/trả phòng bằng luồng xác nhận rõ tác động. |
| CT4 Điện nước | Chọn kỳ và phòng/đợt thuê; bảng hoặc biểu mẫu chỉ số cũ/mới/tiêu thụ; trạng thái đã khóa | Ghi/lưu chỉ số; thông báo lỗi ngay cạnh dữ liệu cần sửa. |
| CT4 Hóa đơn | Danh sách và bộ lọc; chi tiết từng dòng; màn hình xem trước và phát hành; quản lý quyền xem | Xem trước, phát hành, cấp/thu hồi quyền; nội dung và số tiền sau phát hành chỉ đọc, quyền xem và thu tiền vẫn xử lý ở chức năng tương ứng. |
| CT5 Thu tiền/công nợ | Chi tiết hóa đơn có đã thu/còn thiếu; biểu mẫu thu và bước kiểm tra; danh sách công nợ mở được hóa đơn | Thu một phần/đủ, xem biên nhận, thu tiếp nợ cũ; tránh nhầm tổng hóa đơn với số còn nợ. |
| CT6 Thu chi | Bộ lọc thời gian/loại; tổng hợp và sổ tiền; biểu mẫu tạo; mục đã xóa; trang/ngăn nhật ký trước/sau | Tạo thu/chi, sửa/xóa, xem lịch sử, hoàn tác sự kiện hợp lệ. |
| CT7 Sửa chữa | Danh sách theo trạng thái/phòng; chi tiết ảnh, nội dung, tiến độ; biểu mẫu cập nhật/kết quả | Tiếp nhận, cập nhật, hoàn tất; có thông báo khi dữ liệu đã bị người khác thay đổi. |
| CT8 Tổng quan | Bộ chọn kỳ; nhóm số liệu hiện tại và theo kỳ tách rõ; danh sách cần xử lý và liên kết chi tiết; biểu đồ nếu giúp đọc số liệu | Đi từ chỉ số tới đúng danh sách; ưu tiên công việc cần xử lý. |

Mỗi bố cục có bản máy tính và bản điện thoại. Danh sách dài dùng phân trang/bộ lọc theo API; thao tác nguy hiểm có lời giải thích và bước xem lại. Trạng thái tải/rỗng/lỗi/mất quyền/xung đột phải được thiết kế cùng màn hình chính.

## 2. Thứ tự xây dựng

### CT1. Phòng và dịch vụ — đã có bản kết nối API

- Màn hình Phòng: danh sách, tìm/lọc, số người/sức chứa/trạng thái, thêm/sửa và chi tiết.
- Chỉ hiển thị thao tác xóa khi nghiệp vụ backend cho phép; hiển thị lý do khi bị chặn.
- Màn hình Dịch vụ: danh mục, đơn vị/cách tính, giá mặc định và bật/tắt theo API.
- Phân biệt giá danh mục với giá đã áp dụng cho đợt thuê.

Hoàn thành khi: tạo/sửa được phòng và dịch vụ; tải lại thấy đúng dữ liệu; lỗi được hiển thị gần trường nhập.

### CT2. Người thuê và tài khoản — đã có bản kết nối API

- Danh sách, tìm kiếm, thêm/sửa, chi tiết hồ sơ và lịch sử cư trú.
- Thao tác tài khoản theo API hiện có; phân biệt hồ sơ người thuê với tài khoản đăng nhập.
- Hỗ trợ người thuê không có tài khoản; không công khai giấy tờ/thông tin cá nhân ngoài phạm vi được phép.

Hoàn thành khi: quản lý được hồ sơ có/không có tài khoản và chọn được người thuê trong bước nhận phòng.

### CT3. Quá trình thuê — đã có bản kết nối API

- Danh sách/chi tiết đợt thuê đang mở và đã đóng.
- Nhận phòng một người hoặc nhiều người; quản lý thành viên và người đại diện.
- Gán/kết thúc dịch vụ, xem giá chốt và thời gian sử dụng; chuyển/trả phòng theo API.
- Liên kết phòng, người thuê, dịch vụ, chỉ số và hóa đơn từ chi tiết đợt thuê.
- Có bước xem lại trước thao tác ảnh hưởng tiền hoặc chuyển/trả phòng.

Đã có bộ chọn tìm kiếm/phân trang theo tên, quản lý nhóm thuê, nhận phòng ba bước và chi tiết giữ toàn bộ lịch sử. Dịch vụ được chọn/áp dụng riêng sau khi nhận phòng thành công để mỗi thao tác phản ánh đúng API hiện có. Liên kết chỉ số/hóa đơn đã mở các màn hình CT4–CT5 theo đúng đợt thuê; nghiệm thu NT1 tiếp tục khi xây giao diện người thuê.

Hoàn thành khi: làm được phòng một/nhiều người, chặn vượt sức chứa và giữ đúng lịch sử khi đổi đại diện/chuyển/trả phòng. Dùng NT1 để kiểm tra người thuê thấy đúng phòng và lịch sử; NT1 có thể đã được thiết kế/nối API bằng dữ liệu thử từ trước.

### CT4. Chỉ số điện nước và hóa đơn — đã có bản kết nối API

- Màn hình ghi chỉ số theo đợt thuê/dịch vụ/khoảng ghi; chỉ số cũ/mới và tiêu thụ.
- Thêm/sửa/xóa theo quyền API; giải thích chỉ số đã bị khóa do phát hành hóa đơn.
- Danh sách hóa đơn, bộ lọc, chi tiết từng dòng, xem trước và phát hành bằng `preview_token`.
- Cấp/thu hồi quyền xem hóa đơn; không mặc định mọi thành viên hay đại diện mới đều có quyền.

Hoàn thành khi: chỉ số → xem trước → phát hành → xem hóa đơn; dữ liệu đổi phải xem trước lại, tiền lấy từ backend. Nội dung/số tiền sau phát hành chỉ đọc; cấp/thu hồi quyền và thu tiền vẫn thực hiện ở các chức năng tương ứng.

### CT5. Thu tiền và công nợ — đã có bản kết nối API

- Từ hóa đơn mở biểu mẫu thu một phần/thu đủ, xem số còn thiếu, phương thức và biên nhận.
- Lịch sử thu, danh sách công nợ, lọc và mở hóa đơn để thu tiếp.
- Giữ `idempotency_key` và payload khi thử lại; thông báo rõ kết quả, không thu vượt.
- Nợ từ đợt đã đóng vẫn được hiển thị và xử lý theo quyền backend.

Hoàn thành khi: hóa đơn có quyền xem ở NT2; chủ trọ thu từng phần rồi đủ, người thuê thấy đúng số liệu; mất phản hồi rồi thử lại không tạo biên nhận trùng.

### CT6. Sổ thu chi, nhật ký và hoàn tác — đã có bản kết nối API

- Sổ gộp thu hóa đơn, thu khác và chi phí; bộ lọc và tổng hợp theo thời gian.
- Tạo thu khác/chi phí; sửa/xóa, xem mục đã xóa và lịch sử thay đổi.
- Xem trước/sau, lý do và hoàn tác sự kiện mới nhất theo API.
- Tạo khoản dùng mã chống trùng; sửa/xóa/hoàn tác dùng phiên bản mới nhất.

Hoàn thành khi: thử lại không nhân đôi kể cả khoản đã sửa/xóa, không tự khôi phục; hai tab có xung đột được thông báo và tổng số tiền đúng sau thay đổi/hoàn tác.

### CT7. Sửa chữa và thông báo — đã có bản kết nối API

- Danh sách yêu cầu theo trạng thái/phòng/người gửi; chi tiết nội dung và ảnh riêng tư.
- Tiếp nhận, cập nhật tiến độ/ghi chú, hoàn tất với kết quả theo API.
- Xử lý xung đột phiên bản; trạng thái hoàn tất tuân thủ giới hạn backend.
- Sử dụng hộp thông báo: chưa đọc, đọc một/tất cả, mở tóm tắt yêu cầu còn quyền truy cập. CT7 đã có danh sách/ảnh riêng tư và thao tác xử lý đầy đủ; gửi báo hỏng/ảnh từ giao diện người thuê NT3 là bước tiếp theo.
- Tích hợp nhắc hóa đơn theo API; tác vụ nhắc tự động cần bộ lập lịch backend hoạt động.

Hoàn thành khi: luồng NT3 gửi báo hỏng/ảnh → chủ trọ xử lý → tác giả theo dõi và nhận thông báo NT4 chạy được, kể cả khi tác giả đã rời phòng.

### CT8. Tổng quan và hoàn thiện — đã có bản kết nối API

- Phòng/người ở hiện tại, yêu cầu sửa chữa chờ xử lý, hóa đơn tháng, công nợ và thực thu/chi.
- Bộ lọc theo phạm vi API; từ thẻ số liệu mở danh sách liên quan.
- Phân biệt hóa đơn đã phát hành với tiền đã thu, kỳ hóa đơn với ngày thu/chi, hiện tại với tháng chọn.
- Hoàn thiện biểu đồ nếu cần, bàn phím, điện thoại và các trạng thái trang.

Hoàn thành khi: số liệu khớp danh sách cùng phạm vi và cập nhật sau thao tác; chạy được luồng nhận phòng → hóa đơn → thu tiền → công nợ → trả phòng/thu nợ cũ.

## 3. Nghiệm thu và phần để sau

- Kiểm tra API và lỗi ngay trong từng CT; chạy lại luồng toàn hệ thống theo [checklist chung](frontend-plan.md#6-checklist-nghiệm-thu-trình-duyệt).
- Kiểm tra chủ trọ trên máy tính và điện thoại; người thuê không vào được các màn hình/API quản trị.
- Quên mật khẩu, email và tính năng mới ngoài API hiện có để giai đoạn mở rộng theo yêu cầu.
- Dữ liệu demo và hướng dẫn bàn giao dùng chung với [kế hoạch người thuê](frontend-tenant-plan.md).
