# Frontend quản lý nhà trọ

Vue 3 + TypeScript + Vite, Vue Router, Pinia và Axios. **CT1–CT8 chủ trọ và NT1–NT4 người thuê** đã kết nối API thật: phòng/dịch vụ, hồ sơ/quá trình thuê, điện nước/hóa đơn/thu tiền, công nợ, thu chi/hoàn tác, báo hỏng/ảnh, thông báo và tổng quan có biểu đồ. [Các màn hình chủ trọ](docs/owner-management.md), [tự kiểm tra CT4–CT8](docs/owner-self-test.md), [tự kiểm tra người thuê NT1–NT4](docs/tenant-self-test.md).

Đã bổ sung **Thông báo chung**: chủ trọ gửi toàn bộ/theo phòng, xem trước người nhận, chống gửi trùng và xem đã đọc/chưa đọc. Người thuê nhận trong hộp thông báo hiện có. [Hướng dẫn tự kiểm tra](docs/announcements-self-test.md).

Đã có **Lịch sử xử lý sửa chữa** trên chi tiết yêu cầu của hai vai trò: trạng thái/ghi chú trước sau, người thực hiện, thời điểm, phân trang và mốc cho dữ liệu cũ. [Tự kiểm tra](docs/maintenance-history-self-test.md).

Nghiệm thu giao diện 25/10/2026: sửa ô ngày giờ trên màn hình nhỏ, giải thích vai trò và đổi tài khoản ở trang không có quyền, loại nhãn chọn trùng và cập nhật hướng dẫn demo. [Biên bản nghiệm thu](docs/ui-acceptance.md) ghi rõ phạm vi trình duyệt, kết quả từng lượt và việc bàn giao còn lại.

Đã có bộ thiết kế chung TK0: xanh trầm/trắng ấm, font Be Vietnam Pro tự lưu trong dự án, token màu/khoảng cách, nút/ô nhập/trạng thái/hộp thoại và hai mẫu phòng. Đăng nhập, tài khoản và bố cục hai vai trò đã được đồng bộ. Dùng CSS/SVG; chưa cài Element Plus.

## Xem thiết kế chung

- `http://127.0.0.1:5173/design`: màu sắc, kiểu chữ và các thành phần.
- `http://127.0.0.1:5173/design?view=owner`: mẫu quản lý phòng chủ trọ.
- `http://127.0.0.1:5173/design?view=tenant`: mẫu Phòng của tôi của người thuê.

Các bản mẫu mở được không cần đăng nhập/backend, ghi rõ dữ liệu minh họa và không gọi API hoặc lưu thông tin. CT1–CT8 dùng API thật ở khu vực chủ trọ. [Quy chuẩn thiết kế và nguồn font](docs/design-system.md).

## Bản dùng thử riêng để tự thao tác

Trong `D:\KLTN\frontend`, chạy `npm run demo`, rồi mở [trang chủ trọ dùng thử](http://127.0.0.1:5174/owner). Đăng nhập `admin` / `123`. Yêu cầu PHP và Composer dependencies của backend đã sẵn sàng, cổng 5174/18001 trống.

[Giao diện người thuê](http://127.0.0.1:5174/tenant): `user` / `123`, gồm phòng/dịch vụ/hồ sơ/lịch sử, hóa đơn và các lần thanh toán, gửi báo hỏng/ảnh và theo dõi kết quả. Điện thoại có thanh điều hướng bốn mục. Các tab cùng trình duyệt dùng chung phiên; dùng hai trình duyệt/hồ sơ nếu muốn kiểm tra hai vai trò cùng lúc.

Bản này dùng Laravel thật với SQLite riêng tại `.cache/e2e/owner-demo.sqlite`; có phòng B202, người thuê, dịch vụ, chỉ số tháng trước, yêu cầu sửa chữa và thu chi mẫu. Khởi động lại giữ dữ liệu bạn đã thử; tài khoản user có phòng TEST-USER và hóa đơn mẫu. Không thay đổi `.env` hoặc dữ liệu MySQL `rental_management`. [Thứ tự tự kiểm tra và kết quả mong đợi](docs/owner-self-test.md).

## Chạy trên máy cá nhân

Yêu cầu: Node 22.12+; backend đã cài Composer, cấu hình MySQL và APP_KEY. Máy hiện tại dùng Node 22.20.0.

Mở terminal thứ nhất:

```powershell
cd D:\KLTN\backend
php artisan serve --host=127.0.0.1 --port=8000
```

Mở terminal thứ hai:

```powershell
cd D:\KLTN\frontend
npm ci
npm run dev
```

Mở http://127.0.0.1:5173/login. Dùng tài khoản ứng dụng đã có trong bảng `users`; đây không phải tài khoản/mật khẩu MySQL. Bản frontend không tạo tài khoản thật hoặc tự đổi mật khẩu của tài khoản có sẵn.

Nếu backend chạy địa chỉ khác, tạo `.env` từ `.env.example` rồi đổi `API_PROXY_TARGET` và khởi động lại Vite. Biến này chỉ dùng ở máy chủ phát triển. Không đưa mật khẩu MySQL hay APP_KEY vào biến frontend.

## Kết nối và xác thực

- Trình duyệt gọi đường dẫn tương đối `/api`; Vite proxy đến `http://127.0.0.1:8000`, giữ nguyên `/api`.
- Trước thao tác xác thực, lấy `/api/auth/csrf-cookie`. Axios gửi cookie và header XSRF hiện tại.
- Đăng nhập qua POST `/api/auth/login`; khi tải lại gọi GET `/api/auth/me` để khôi phục user/role trong bộ nhớ Pinia.
- Cookie phiên HttpOnly do trình duyệt giữ; không lưu mật khẩu hoặc token đăng nhập vào localStorage/sessionStorage.
- Chủ trọ vào `/owner`, người thuê vào `/tenant`. `/account` cho cả hai vai trò. Laravel tiếp tục kiểm tra quyền từng API.
- Đăng xuất thành công xóa trạng thái giao diện; lỗi mạng báo chưa hoàn tất và giữ trạng thái để người dùng thử lại. Đổi mật khẩu thành công yêu cầu đăng nhập lại.
- Đồng bộ thay đổi phiên giữa các tab bằng BroadcastChannel khi trình duyệt hỗ trợ; kiểm tra phiên khi quay lại tab/cửa sổ. Phản hồi 401 của phiên cũ không được xóa tài khoản vừa đăng nhập mới.
- 401 quay lại đăng nhập; 419 yêu cầu thử lại thao tác; 422 hiển thị lỗi trường; 429 có thời gian chờ. Không tự động gửi lại POST/PUT sau lỗi mạng/CSRF.
- Backend ngừng phản hồi lúc khởi động hiển thị trang thử kết nối lại.

Quên mật khẩu và đăng ký công khai chưa nằm trong giai đoạn này.

## Màn hình dùng chung đã có

- `/login`: đăng nhập bằng tài khoản ứng dụng hiện có; `/account`: thông tin tài khoản và đổi mật khẩu. Luồng đăng nhập, đăng xuất và đổi mật khẩu hiện có được sử dụng tiếp.
- `/notifications`: hộp thông báo riêng cho tài khoản đang đăng nhập, dùng chung cả hai vai trò. Có chuông/số chưa đọc, bộ lọc loại/chưa đọc, phân trang 15 mục, làm mới, đánh dấu một hoặc tất cả đã đọc. Bộ lọc và trang được giữ trong URL.
- Khi chọn **Xem nội dung**, giao diện tải mới hóa đơn, yêu cầu sửa chữa hoặc thông báo chung bằng API đúng vai trò rồi mở hộp thoại chỉ đọc và liên kết tới trang đầy đủ. Quyền hiện tại luôn do Laravel kiểm tra; nội dung mất quyền/không còn tồn tại có trạng thái riêng.
- Số chưa đọc được cập nhật mỗi 60 giây khi tab đang hiển thị và khi quay lại tab/cửa sổ; danh sách được làm mới khi đang mở hộp thông báo. Không cần WebSocket cho bản hiện tại.
- Đăng xuất, hết phiên hoặc đổi tài khoản xóa danh sách/số chưa đọc và bỏ phản hồi đang chờ của phiên trước. Khi tải lỗi, các thẻ riêng tư cũ không tiếp tục hiển thị như dữ liệu mới. Lỗi ghi không tự gửi lại; người dùng có thể chủ động thử lại.
- Đã có thành phần trạng thái dùng chung: đang tải, rỗng, lỗi/thử lại, không có quyền và không tìm thấy. Truy cập sai khu vực theo vai trò có màn hình không có quyền; URL không tồn tại có màn hình không tìm thấy.

## Kiểm tra và đóng gói

```powershell
npm test
npm run build
```

`build` kiểm tra TypeScript trước khi tạo `dist/`. Bộ kiểm thử bao gồm trạng thái/điều hướng, định dạng tiền/ngày, tương phản bảng màu và thông báo/riêng tư giữa các phiên. Kiểm thử trình duyệt dùng Laravel thật với SQLite và session/cache riêng, không dùng MySQL của ứng dụng:

```powershell
npx playwright install chromium
npm run test:e2e
```

Hoặc dùng Chrome đã cài trên máy:

```powershell
$env:PLAYWRIGHT_CHANNEL = 'chrome'
npm run test:e2e
```

Các nhóm trình duyệt: xác thực/phiên và hai vai trò; thiết kế chung/font nội bộ/bàn phím/bố cục nhiều kích thước; thông báo riêng tư, lọc/phân trang, đọc một/tất cả, nội dung tải mới từ API theo quyền, lỗi mạng/CSRF, hết phiên/đổi tài khoản, liên kết sâu qua đăng nhập và trang không có quyền/không tìm thấy. Fixture thông báo có hóa đơn được cấp quyền và yêu cầu sửa chữa thật trong SQLite riêng. Luồng chính dùng backend thật; phản hồi chỉ được thay có chủ đích để kiểm tra lỗi và ca biên trang cuối hết mục chưa đọc. Chi tiết môi trường cô lập ở [tests/e2e/README.md](tests/e2e/README.md).

## Triển khai

Phục vụ `dist/` trên cùng tên miền HTTPS với Laravel; `/api/*` phải được chuyển đến Laravel trước luật fallback của Vue. Chỉ đường dẫn giao diện mới fallback về `index.html`; API lỗi vẫn trả JSON. `API_PROXY_TARGET` không cấu hình máy chủ production.

Thiết lập APP_URL, cookie Secure/HTTPS và proxy tin cậy ở backend theo môi trường. Vite preview chỉ để kiểm tra bản build tại máy, không có cấu hình proxy production. Không cần máy chủ Node thường trực để phục vụ bản Vue tĩnh này.

Kế hoạch tiếp theo: [chủ trọ CT1–CT8](../backend/docs/frontend-owner-plan.md), [người thuê NT1–NT4](../backend/docs/frontend-tenant-plan.md), [kế hoạch phối hợp](../backend/docs/frontend-plan.md).

Hạn thanh toán và điều chỉnh/hủy hóa đơn đã nối cho hai vai trò. Xem [hướng dẫn tự kiểm tra](docs/invoice-controls-self-test.md) và [biên bản nghiệm thu](docs/business-acceptance.md).

Ngày 27/10/2026 bổ sung [chi phí sửa chữa](docs/repair-expenses-self-test.md) và [sửa sai phiếu thu](docs/payment-corrections-self-test.md). Chi phí liên kết chính khoản trong sổ; phiếu thu gốc được giữ, có thể hủy ghi nhận nhầm/thay thế và tính lại số dư. Không thực hiện hoàn tiền thực tế.

Tài khoản tự test ngày 24/10/2026: **admin / 123** (chủ trọ) và **user / 123** (người thuê, phòng TEST-USER). [Thứ tự hoàn thiện và nâng cấp](docs/next-steps.md).

Hoàn thiện ngày 30/10/2026: chủ trọ có **Thông tin nhà trọ** và **Điện nước → Nhập nhiều phòng**; hai vai trò tải PDF hóa đơn/phiếu thu theo quyền. [Tự thử thông tin/PDF](docs/property-pdf-self-test.md), [tự thử nhập nhiều phòng](docs/bulk-meters-self-test.md).

Đã bổ sung bản trình riêng qua `npm run presentation`, cổng **5175** (Laravel 18002), CSDL/ảnh/phiên tách MySQL và bản demo cũ. Có dữ liệu một/nhiều người, hóa đơn/thu tiền/quá hạn, ảnh/lịch sử/chi phí sửa chữa; giữ trạng thái sau khởi động lại. `npm run presentation:checkpoint` lưu mã nguồn và dữ liệu, kiểm tra khôi phục riêng; `npm run presentation:restore -- MA_BAN_SAO` đưa dữ liệu trình về mốc đã lưu khi đã dừng máy chủ. [Cách chạy CMD, tài khoản và kịch bản trình](docs/presentation-guide.md).

Giao diện chung có hiệu ứng hộp thoại/nút/thông báo nhẹ, khung chờ cho tổng quan/danh sách chính, tiến độ nhập nhiều phòng và cảnh báo rời trang có bản nháp ở thông tin nhà trọ, nhận phòng và gửi báo hỏng. Tôn trọng lựa chọn giảm chuyển động; đăng xuất/hết phiên không bị giữ lại bởi cảnh báo bản nháp.

Nghiệm thu sau đợt hoàn thiện này: **94/94 kịch bản Chrome toàn bộ** trong một lượt 27,0 phút, frontend **131/131**, TypeScript/Vite và PHP style của các helper đạt. Bản trình riêng còn được đối chiếu 30 bước/10 ảnh, tập nghiệp vụ 15 bước, sao lưu và khôi phục dữ liệu/ảnh đạt. [Phạm vi và kết quả](docs/presentation-polish-acceptance.md).
