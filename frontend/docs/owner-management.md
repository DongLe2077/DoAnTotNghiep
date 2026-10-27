# Giao diện chủ trọ CT1–CT8

Cập nhật 23/10/2026. Các màn hình dưới đây gọi API Laravel thật bằng phiên đăng nhập và CSRF hiện có. Chủ trọ đăng nhập rồi sử dụng menu bên trái; người thuê không được vào các trang/API này.

| Gói | Đường dẫn | Chức năng |
| --- | --- | --- |
| CT1 | `/owner/rooms` | Tìm/lọc/phân trang, số người/sức chứa, trạng thái, chi tiết, thêm/sửa/xóa phòng chưa có lịch sử; giá thuê mặc định chung có bước xem lại. |
| CT1 | `/owner/services` | Danh mục, tìm/lọc/phân trang, thêm/sửa, bật/tắt, xóa mục chưa dùng; đơn giá giữ tối đa bốn chữ số thập phân. |
| CT2 | `/owner/tenants` và `/:id` | Tìm/lọc/phân trang, tạo/sửa hồ sơ, tài khoản tùy chọn, khóa/mở/đổi mật khẩu, lịch sử cư trú và xóa hồ sơ chưa có tài khoản/lịch sử. |
| CT3 | `/owner/groups` | Tìm/phân trang, tạo/đổi mã nhóm, xóa nhóm chưa có lịch sử. |
| CT3 | `/owner/stays` | Lọc trạng thái/phòng/người thuê, phân trang và mở chi tiết đợt thuê. |
| CT3 | `/owner/stays/new` | Ba bước: phòng/nhóm/thời điểm → thành viên/đại diện → xem lại và nhận phòng. Tạo nhóm ngay trong luồng; hỗ trợ phòng một người và người không có tài khoản. |
| CT3 | `/owner/stays/:id` | Thành viên, đại diện, các khoảng dịch vụ; bổ sung/rời thành viên, thay đại diện, áp dụng/đổi giá/ngừng dịch vụ, chuyển cả nhóm, trả phòng và lịch sử trước/sau. |
| CT4 | `/owner/meters` | Tìm đợt thuê, xem mốc gần nhất, ghi/sửa/xóa chỉ số và nhận biết khoảng đã khóa bởi hóa đơn. |
| CT4 | `/owner/invoices`, `/owner/invoices/new`, `/:id` | Lọc, xem trước các dòng tiền/cảnh báo, bổ sung đại diện lịch sử khi cần, phát hành bằng `preview_token`, chi tiết và cấp/thu hồi quyền xem. |
| CT5 | `/owner/invoices/:id`, `/owner/payments` | Thu một phần/toàn bộ, biên nhận và lịch sử; giữ đúng mã/nội dung khi gửi lại sau mất phản hồi, tải lại hoặc hết phiên. |
| CT5 | `/owner/receivables` | Tổng còn cần thu; lọc tháng/phòng/nhóm; xem từng hóa đơn hoặc gộp phòng/nhóm, gồm đợt thuê đã đóng; mở đúng phạm vi hóa đơn để thu tiếp. |
| CT6 | `/owner/cash` | Sổ thu hóa đơn/thu khác/chi phí, lọc/tổng hợp ngày-tháng; tạo chống trùng, sửa/xóa, mục đã xóa, lịch sử trước/sau và hoàn tác sự kiện mới nhất. |
| CT7 | `/owner/maintenance`, `/:id` | Tìm/lọc/phân trang báo hỏng, ảnh riêng tư, tiếp nhận/cập nhật/hoàn tất với kết quả và kiểm tra phiên bản; cập nhật thông báo. |
| CT8 | `/owner` | Phòng/người ở hiện tại, việc cần xử lý, hóa đơn kỳ chọn, công nợ mọi tháng, thực thu/chi theo ngày phát sinh và liên kết danh sách. |
| Thông báo chung | `/owner/announcements`, `/new`, `/:id` | Gửi toàn bộ/theo phòng, xem trước người nhận, xác nhận gửi/chống trùng, lịch sử và lọc trạng thái đọc. [Tự kiểm tra](announcements-self-test.md). |

Sau nhận phòng, dịch vụ được áp dụng riêng trong chi tiết đợt thuê. API nhận phòng không nhận danh sách dịch vụ, nên giao diện không ghép nhiều lần ghi thành một lần lưu giả định. Mỗi lần áp dụng dịch vụ có kết quả và lỗi riêng.

Giá mặc định danh mục được phân biệt với giá đã chốt cho đợt thuê/khoảng dịch vụ. Nhận/chuyển/trả phòng và thay đổi dịch vụ đều có bước xem lại trước khi ghi. Ngày giờ gửi đúng `YYYY-MM-DD HH:mm:ss` theo giờ Việt Nam, không đổi theo múi giờ trình duyệt. Backend kiểm tra lại sức chứa, lịch sử, tài khoản đại diện, thời gian, chỉ số/hóa đơn và khóa dữ liệu trong transaction.

Các bộ chọn dùng tên phòng/người/dịch vụ/nhóm, tìm kiếm qua API và tải thêm từng trang; không buộc nhập ID hoặc tải toàn bộ danh mục. Đại diện chỉ cho chọn thành viên đang ở có tài khoản hoạt động. Thành viên cuối dùng Trả phòng; thành viên đại diện rời đi cần người thay thế.

Lỗi nhập hiển thị gần trường; lỗi ghi giữ biểu mẫu để người dùng sửa hoặc chủ động thử lại. Nút lưu chặn nhấn lặp, lấy CSRF trước thao tác và không tự gửi lại ghi sau lỗi mạng/419. Yêu cầu trước khi rời trang/đổi tài khoản không được điền lại dữ liệu vào màn hình mới. Dữ liệu giấy tờ và mật khẩu không được lưu vào browser storage.

Điện nước/hóa đơn từ chi tiết đợt thuê đã liên kết tới CT4–CT5. CT1–CT8 chủ trọ và NT1–NT4 người thuê đã có màn hình/API. [Tự kiểm tra chủ trọ](owner-self-test.md), [tự kiểm tra người thuê](tenant-self-test.md) dùng dữ liệu riêng, không thay đổi MySQL của ứng dụng.

Hóa đơn đã phát hành giữ nguyên các dòng tiền; cấp quyền và thu tiền là các thao tác riêng. Gửi lại thu tiền/thu chi chỉ do người dùng chủ động xác nhận, giữ nguyên mã và nội dung đã gửi để nhận lại kết quả cũ. Tiền hiển thị giữ chính xác chuỗi thập phân, không cộng tiền bằng số thực trong trình duyệt. Công nợ không tự chia thành nợ từng thành viên. Sổ tiền dùng ngày thực thu/chi; tổng quan phân biệt kỳ hóa đơn với hiện trạng phòng và công nợ mọi kỳ.

Ảnh sửa chữa lấy từ API riêng tư theo ID, thu hồi URL ảnh khi rời trang/đổi phạm vi. Xung đột phiên bản khi sửa thu chi/sửa chữa yêu cầu tải dữ liệu mới và xem lại. Quyền xem hóa đơn không mặc định truyền sang đại diện mới; tác giả báo hỏng vẫn theo dõi theo quyền backend sau khi rời phòng.

## Kiểm tra

Kết quả ngày 23/10/2026 sau hoàn thiện hai vai trò: **110 kiểm thử frontend**, **62 kiểm thử trình duyệt Chrome** (9,4 phút), TypeScript và build đạt. Backend đạt **309 kiểm thử / 2.980 assertions**; 7 ca ảnh được chạy lại sau bổ sung thông báo lỗi tiếng Việt. E2E dùng Laravel/SQLite riêng và đã kiểm tra luồng hai vai trò; vẫn cần nghiệm thu thủ công và cài mới trên môi trường MySQL thực tế.

- `npm test`: kiểm thử frontend và định dạng đơn giá/thời gian.
- `npm run build`: TypeScript và bản đóng gói.
- `npm run test:e2e -- owner-management.spec.ts`: sáu luồng với Laravel/SQLite riêng: phòng/giá chung; dịch vụ; hồ sơ/tài khoản; nhận/đổi đại diện/chuyển/trả; một người/giới hạn thành viên/đổi-ngừng dịch vụ; lỗi CSRF không tự gửi lại.
- `owner-billing.spec.ts`: chỉ số/khóa, xem trước/phát hành/xung đột, đại diện lịch sử, quyền xem/nhắc, thu từng phần/đủ, mất phản hồi/hết phiên/gửi lại và bộ lọc liên kết sâu.
- `owner-cash.spec.ts`: sổ tiền, tạo/chống trùng/khôi phục yêu cầu chờ, sửa/xóa/nhật ký/hoàn tác và xung đột phiên bản.
- `owner-maintenance.spec.ts`: danh sách/lọc, ảnh riêng tư, tiếp nhận/hoàn tất, thông báo và xung đột.
- `owner-reporting.spec.ts`: đối chiếu số liệu tổng quan/công nợ bằng API, các phạm vi tháng, liên kết gộp và chặn người thuê.
- `npm run test:e2e`: gồm cả hồi quy xác thực, thiết kế và thông báo.
- `php artisan test --filter="RoomServiceManagementTest|TenancyManagementTest"` trong backend: 49 kiểm thử, 555 assertions.

E2E không dùng `.env` hoặc MySQL `rental_management`; dữ liệu và ảnh minh họa trong `.cache/e2e/` nằm ngoài Git. Khi không có trình duyệt Playwright, dùng Chrome đã cài bằng `PLAYWRIGHT_CHANNEL=chrome`.

Cập nhật nghiệm thu 23/10/2026: 319 backend, 115 frontend và 66 kịch bản Chrome đạt; đã bổ sung hạn thanh toán, công nợ quá hạn và điều chỉnh/hủy/khôi phục hóa đơn. Xem [biên bản mới nhất](business-acceptance.md) và [tự thử hóa đơn](invoice-controls-self-test.md). Các số kiểm thử phía trên là của đợt hoàn thiện NT trước đó.
