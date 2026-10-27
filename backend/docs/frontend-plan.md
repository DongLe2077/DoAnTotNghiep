# Kế hoạch thiết kế và hoàn thiện frontend quản lý nhà trọ

Cập nhật ngày **23/10/2026**, dựa trên mã nguồn hiện tại. CT1–CT8 chủ trọ và NT1–NT4 người thuê đã có màn hình/API; tiếp tục nghiệm thu dữ liệu thực và chuẩn bị bàn giao. [Bản dùng thử và tự kiểm tra chủ trọ](../../frontend/docs/owner-self-test.md).

## 1. Phạm vi bản dùng thử

Xây dựng frontend tại `D:\KLTN\frontend` cho **chủ trọ** và **người thuê**, dùng Vue 3 + TypeScript + Vite, Vue Router, Pinia, Axios và các API Laravel hiện có. [Hướng dẫn chạy và kiểm thử](../../frontend/README.md).

| Hạng mục | Hiện trạng kiểm tra từ mã nguồn | Cách xử lý trong kế hoạch mới |
| --- | --- | --- |
| Đăng nhập, đăng xuất, đổi mật khẩu, khôi phục phiên | Đã có giao diện và gọi API | Sử dụng lại, kiểm tra hồi quy khi thay đổi bố cục. |
| Hai khu vực `/owner` và `/tenant` | Đã có menu, bố cục và kiểm tra vai trò | Hoàn thiện theo hai nhu cầu sử dụng riêng. |
| CT1–CT3: phòng, dịch vụ, hồ sơ/tài khoản, quá trình thuê | Đã có giao diện, API thật, tìm/lọc/phân trang, chi tiết và thao tác nhận/chuyển/trả | Dùng tiếp; nghiệm thu hai phía với NT1 khi triển khai. [Hướng dẫn](../../frontend/docs/owner-management.md). |
| CT4–CT7: điện nước, hóa đơn, thu tiền, công nợ, thu chi, sửa chữa | Đã có màn hình/API; xem trước/phát hành, chống trùng, nhật ký/hoàn tác và ảnh riêng tư | Dùng thử và nghiệm thu với giao diện người thuê khi NT2/NT3 hoàn thiện. |
| CT8 Tổng quan chủ trọ | Đã có API số liệu, kỳ hóa đơn, công nợ mọi kỳ và thực thu/chi riêng; thẻ mở danh sách | Đối chiếu dữ liệu và nghiệm thu theo hướng dẫn dùng thử. |
| NT1–NT4: phòng/hồ sơ/lịch sử, hóa đơn, báo hỏng/ảnh, thông báo | Đã có màn hình/API; tạo yêu cầu và tải ảnh là hai bước, ảnh lỗi thử lại riêng; có menu điện thoại bốn mục | [Tự kiểm tra người thuê](../../frontend/docs/tenant-self-test.md), nghiệm thu quyền và dữ liệu thực. |
| Hộp thông báo dùng chung | Đã có `/notifications`, chuông/số chưa đọc, bộ lọc/phân trang, đọc một/tất cả và API thật | Dùng lại; kiểm tra luồng tạo/cập nhật thông báo khi hoàn thiện CT7/NT3, không làm lại hộp thông báo. |
| Trạng thái dùng chung | Đã có đang tải, rỗng, lỗi/thử lại, không có quyền và không tìm thấy | Áp dụng tiếp cho từng màn hình nghiệp vụ. |
| Thiết kế chung TK0 | Đã có token màu/chữ/khoảng cách, thành phần nút/nhãn/ô nhập/thông báo/hộp thoại, định dạng tiền/ngày và hai mẫu phòng | Áp dụng vào CT1/NT1; bảng phân trang API, upload và thành phần nghiệp vụ phức tạp bổ sung theo module. |

CT1–CT8 sử dụng thành phần Vue/CSS chung, chưa cài Element Plus. Có menu hoặc trang chờ chưa được tính là hoàn thành màn hình nghiệp vụ.

Hai kế hoạch giao diện được tách riêng:

- [Giao diện chủ trọ: CT1–CT8](frontend-owner-plan.md).
- [Giao diện người thuê: NT1–NT4](frontend-tenant-plan.md).

Hai khu vực có menu và màn hình riêng trong cùng ứng dụng frontend, dùng chung nền tảng đăng nhập, kết nối API và bộ thành phần giao diện.

Backend đã có nghiệp vụ cốt lõi, chống trùng thu chi, nhật ký/hoàn tác, ảnh báo hỏng và thông báo. Mục tiêu là thao tác từ nhận phòng đến hóa đơn, thu tiền và sửa chữa qua trình duyệt.

**Quên/đặt lại mật khẩu, đăng ký công khai và gắn/xác minh email để sau**, không là điều kiện nghiệm thu. Tài khoản hiện tại gồm đăng nhập, đăng xuất, đọc phiên và đổi mật khẩu khi đã đăng nhập.

## 2. Quy trình thiết kế trước khi xây dựng màn hình

### TK0. Chuẩn hóa thiết kế chung — đã có bản đầu

Đã triển khai phong cách tối giản, thân thiện, ngăn nắp và an tâm theo yêu cầu: xanh trầm `#235C4B`, nền trắng ấm `#F7F8F5`, font Be Vietnam Pro tự lưu, chữ và điều khiển dễ đọc/dễ bấm. Mở `/design` để xem bộ thành phần, `/design?view=owner` và `/design?view=tenant` để thử hai mẫu với dữ liệu minh họa; các mẫu không gọi API và không thay đổi dữ liệu. [Tài liệu thiết kế](../../frontend/docs/design-system.md). Đăng nhập/tài khoản/bố cục hai vai trò đã được áp dụng màu và chữ chung.

- Dùng tông xanh trầm/nền sáng hiện có làm nền thiết kế; thống nhất màu chính, màu cảnh báo, chữ, khoảng cách, nút, biểu tượng và trạng thái.
- Chủ trọ ưu tiên màn hình máy tính: menu bên trái, thanh công cụ, bảng/bộ lọc và vùng chi tiết rõ ràng. Người thuê ưu tiên điện thoại: thẻ thông tin, nội dung ngắn, nút dễ chạm; vẫn có bố cục máy tính.
- Nội dung chính khoảng 14–16 px, tiêu đề rõ cấp bậc, chữ phụ đọc được; trạng thái có nhãn chữ, không chỉ phân biệt bằng màu.
- Chuẩn bị mẫu danh sách, chi tiết, biểu mẫu thêm/sửa, xác nhận thao tác và ảnh; bảng trên điện thoại có thông tin chính hoặc thẻ thay thế.
- Bổ sung quy ước tiền VNĐ/ngày giờ, lỗi trường, đang tải, không có dữ liệu, không có kết quả lọc, mất kết nối, hết phiên và không có quyền.
- Thiết kế trước hai màn hình mẫu: **Phòng của chủ trọ** và **Phòng của tôi của người thuê**, mỗi màn hình có bố cục máy tính/điện thoại. Bản mẫu minh họa phải ghi rõ dữ liệu mẫu.

Đầu ra TK0: quy ước giao diện chung, hai bố cục mẫu xem được và mẫu các trạng thái chính. Sau đó áp dụng vào CT1 và NT1; không cần vẽ chi tiết toàn hệ thống trước khi triển khai gói đầu tiên.

### Quy trình cho từng CT/NT

| Bước | Công việc | Đầu ra cần có |
| --- | --- | --- |
| 1. Luồng thao tác | Xác định ai dùng, vào từ đâu, muốn làm gì, cần quyền nào | Luồng ngắn và hành động chính của màn hình. |
| 2. Bố cục | Sắp tiêu đề, bộ lọc, nội dung, nút chính, chi tiết/biểu mẫu | Bố cục mẫu máy tính và điện thoại cho gói đang làm. |
| 3. Dữ liệu và trạng thái | Đối chiếu API, trường nhập, trạng thái khóa/xung đột/rỗng/lỗi | Danh sách trường, trạng thái và phản hồi sau thao tác. |
| 4. Xây dựng giao diện | Dựng màn hình theo mẫu, dùng thành phần chung khi phù hợp | Màn hình thao tác được; dữ liệu minh họa nếu có phải ghi rõ. |
| 5. Nối API | Đọc/ghi bằng API thật, quyền, phân trang, cập nhật sau lưu | Giao diện hiển thị và thay đổi dữ liệu từ backend. |
| 6. Nghiệm thu | Kiểm tra luồng chính, quyền, lỗi mạng và bố cục | Gói dùng được với dữ liệu thử; báo rõ phần đã thiết kế/đã tích hợp. |

Khi bàn giao từng gói, kèm ảnh hoặc bản xem trước, luồng thao tác và kết quả tích hợp. Việc người dùng góp ý bố cục có thể thực hiện theo từng gói; không đặt bước xin phép bắt buộc cho mọi màn hình.

### Dữ liệu và môi trường phục vụ thiết kế

- Có tài khoản thử hai vai trò, phòng một/nhiều người, người chưa có phòng/đã rời phòng và hóa đơn còn nợ; tách dữ liệu thử khỏi dữ liệu thật.
- Giữ cách kết nối `/api` qua proxy khi phát triển và cookie session/CSRF hiện có; triển khai cùng tên miền HTTPS.
- Trước bàn giao, chuẩn bị schema gốc, migration mở rộng và hướng dẫn. Migration hiện tại chưa tạo được toàn bộ CSDL từ đầu.

Không cần thiết lập email cho bản này. Không đưa mật khẩu, giấy tờ thật hoặc ảnh riêng tư vào Git/bundle.

## 3. Màn hình cần làm

### Dùng chung — đã có bản kết nối backend

- **Đăng nhập:** username/mật khẩu, hiện/ẩn mật khẩu, lỗi xác thực và giới hạn thử. Chưa hiển thị luồng quên mật khẩu khi chưa có chức năng tương ứng.
- **Tài khoản:** đổi mật khẩu bằng mật khẩu hiện tại; thành công yêu cầu đăng nhập lại; đăng xuất.
- **Thông báo:** `/notifications` dùng chung cả hai vai trò, chuông/số chưa đọc, bộ lọc loại/chưa đọc, phân trang 15 mục, làm mới, đọc một/tất cả. URL giữ bộ lọc/trang. Mở nội dung tải mới hóa đơn hoặc yêu cầu sửa chữa theo API đúng vai trò và hiển thị hộp thoại chỉ đọc; đây chưa phải các màn hình nghiệp vụ CT4/CT7/NT2/NT3 đầy đủ.
- **Trạng thái trang:** đang tải, rỗng, lỗi/thử lại, không có quyền, không tìm thấy và hết phiên. Truy cập URL sai vai trò vào trang không có quyền; đường dẫn không tồn tại vào trang không tìm thấy.
- **Cập nhật và riêng tư:** cập nhật số chưa đọc mỗi 60 giây khi tab hiển thị và khi quay lại; làm mới danh sách nếu đang mở. Xóa dữ liệu thông báo khi đăng xuất/hết phiên/đổi tài khoản, bỏ phản hồi cũ đang chờ; không hiển thị thẻ riêng tư đã lưu khi tải mới thất bại, không tự gửi lại thao tác ghi lỗi.

### Chủ trọ

Thứ tự và tiêu chí hoàn thành từng phần: [kế hoạch chủ trọ](frontend-owner-plan.md).

Điều hướng: Tổng quan · Phòng · Dịch vụ · Người thuê · Quá trình thuê · Điện nước · Hóa đơn · Công nợ · Thu chi · Sửa chữa. Thông báo/tài khoản đặt ở thanh trên cùng.

| Màn hình | Thao tác chính |
| --- | --- |
| Tổng quan | Chọn tháng, phòng/người ở hiện tại, sửa chữa chờ xử lý, hóa đơn tháng, công nợ mọi tháng và thực thu/chi; mở danh sách chi tiết từ chỉ số. |
| Phòng | Tìm/lọc, số người/sức chứa/trạng thái, thêm/sửa, giá mặc định chung, xóa khi backend cho phép; liên kết đợt thuê. |
| Dịch vụ | Danh mục, cách tính/đơn vị/giá mặc định, bật/tắt; phân biệt giá danh mục với giá đã chốt cho đợt thuê. |
| Người thuê | Hồ sơ/giấy tờ, tài khoản khi cần, lịch sử cư trú. Không bắt buộc mỗi người thuê phải có tài khoản. |
| Quá trình thuê | Nhận phòng, thành viên/đại diện, gán/kết thúc dịch vụ, chuyển/trả phòng; xem đợt đang mở và đã đóng. |
| Điện nước | Đợt thuê/dịch vụ/khoảng ghi, chỉ số/tiêu thụ, sửa/xóa theo API; giải thích dữ liệu khóa sau lập hóa đơn. |
| Hóa đơn và thu tiền | Lọc tháng/phòng/trạng thái, xem trước/phát hành, cấp/thu hồi quyền, thu từng phần/đủ, biên nhận, nhắc thanh toán. |
| Công nợ | Theo hóa đơn/phòng/nhóm, mọi tháng hoặc tháng chọn; mở hóa đơn để thu, giữ nợ sau chuyển/trả phòng. |
| Thu chi | Sổ gộp thu hóa đơn và thu khác/chi phí; tổng hợp ngày/tháng; tạo/sửa/xóa, mục đã xóa, nhật ký và hoàn tác sự kiện mới nhất. |
| Sửa chữa | Lọc trạng thái/phòng/người gửi, nội dung/ảnh, cập nhật tiến độ/ghi chú/kết quả; xử lý xung đột phiên bản. |

Chi tiết đợt thuê liên kết thành viên/dịch vụ/chỉ số/hóa đơn; chọn theo tên phòng/người thay vì nhập ID. Thao tác tiền, chuyển/trả phòng có bước xem lại.

### Người thuê

Thứ tự và tiêu chí hoàn thành từng phần: [kế hoạch người thuê](frontend-tenant-plan.md). Người dùng trong kế hoạch này là người thuê có tài khoản; hồ sơ người thuê không bắt buộc có tài khoản.

Điều hướng: Phòng của tôi · Hóa đơn · Báo hỏng · Lịch sử cư trú; thông báo và tài khoản dùng màn hình chung.

| Màn hình | Nội dung và giới hạn |
| --- | --- |
| Phòng của tôi | Hồ sơ, phòng/nhóm, giá **toàn phòng** đã chốt, dịch vụ, trạng thái chưa có phòng. Chủ trọ sửa thông tin nhân thân. |
| Hóa đơn của tôi | Chỉ các hóa đơn được cấp quyền, từng dòng tính, đã đóng/còn thiếu và lịch sử thanh toán được phép xem. |
| Báo hỏng | Yêu cầu/ảnh/tiến độ của chính mình, kể cả sau trả phòng. Chỉ đại diện đủ quyền tạo mới. |
| Lịch sử cư trú | Lịch sử của chính tài khoản; không lộ danh sách hoặc giấy tờ người cùng phòng. |

Quyền tạo báo hỏng theo `can_submit_maintenance` và danh sách đủ quyền từ API; backend kiểm tra lại. Đại diện mới không tự có quyền hóa đơn/báo hỏng cũ.

## 4. Thứ tự triển khai và tiêu chí hoàn thành

### P0. Nền tảng đã có — sử dụng lại

- Đã khởi tạo frontend, có hướng dẫn chạy, lớp API và xác thực cookie/CSRF.
- Đã có đăng nhập/đăng xuất/đổi mật khẩu, đọc phiên, khung `/owner`/`/tenant`, menu và kiểm tra vai trò.
- Khi thay đổi thiết kế, kiểm tra lại tải lại giữ phiên, chuyển đúng khu vực, hết phiên, đăng xuất, đổi mật khẩu và truy cập sai vai trò.
- TK0 đã bổ sung bộ màu/chữ/thành phần và định dạng tiền/ngày. Màn hình dùng chung tiếp theo đã có hộp thông báo thật, chuông/số chưa đọc, bộ lọc/phân trang, đọc một/tất cả, xem đối tượng chỉ đọc theo quyền và các trạng thái trang; sử dụng tiếp nền tảng xác thực hiện có.
- CT1–CT8 đã có giao diện/API: phòng, dịch vụ, người thuê, đợt thuê, chỉ số/hóa đơn/thu tiền, sổ thu chi, sửa chữa và tổng quan. NT1–NT4 cũng đã nối API; trang nghiệp vụ hai vai trò không còn dùng trang chờ.

### A. Kế hoạch riêng cho chủ trọ

Đã hoàn thiện giao diện/API theo thứ tự **CT1 Phòng/dịch vụ → CT2 Người thuê → CT3 Quá trình thuê → CT4 Điện nước/hóa đơn → CT5 Thu tiền/công nợ → CT6 Thu chi → CT7 Sửa chữa/thông báo → CT8 Tổng quan**. Còn nghiệm thu liên thông khi xây giao diện người thuê.

Chi tiết màn hình, thao tác và điều kiện hoàn thành ở [kế hoạch chủ trọ](frontend-owner-plan.md).

### B. Kế hoạch riêng cho người thuê

Làm theo thứ tự **NT1 Thông tin cá nhân/phòng/lịch sử → NT2 Hóa đơn/lịch sử thanh toán → NT3 Báo hỏng/ảnh/tiến độ → NT4 Thông báo/tài khoản**. Giao diện người thuê ưu tiên điện thoại.

Chi tiết màn hình, quyền và điều kiện hoàn thành ở [kế hoạch người thuê](frontend-tenant-plan.md).

### Thứ tự thực hiện giữa hai khu vực

| Lượt | Phần chủ trọ | Phần người thuê | Đầu ra cần bàn giao |
| --- | --- | --- | --- |
| Đã có | P0: xác thực và bố cục chủ trọ | P0: xác thực và bố cục người thuê | Giữ lại nền tảng hiện tại; không tính menu là đã hoàn thành nghiệp vụ. |
| 1. Thiết kế chung (đã có bản đầu) | TK0: mẫu quản lý phòng, danh sách/chi tiết/biểu mẫu | TK0: mẫu Phòng của tôi, thẻ thông tin và bố cục điện thoại | Xem tại `/design`; hai mẫu minh họa riêng theo vai trò, bộ màu/chữ/thành phần chung. |
| Đã có — màn hình dùng chung | Tài khoản, hộp thông báo, trạng thái trang | Cùng chức năng dùng API theo tài khoản/vai trò | Chuông, số chưa đọc, lọc/phân trang/đọc, xem nội dung chỉ đọc theo quyền; dọn dữ liệu riêng tư khi phiên đổi. |
| 2. Phòng/dịch vụ — chủ trọ đã có | CT1: thiết kế, giao diện và API đã triển khai | NT1 đã hiển thị phòng/dịch vụ theo API | Phòng/dịch vụ dùng được; tiếp tục nghiệm thu liên kết với NT1. |
| 3. Người thuê/quá trình thuê — chủ trọ đã có | CT2–CT3: hồ sơ, tài khoản, nhận/chuyển/trả phòng đã triển khai | Đã có NT1: thông tin cá nhân, phòng, dịch vụ, lịch sử | Phía chủ trọ dùng được; còn nghiệm thu hai giao diện khi NT1 hoàn thiện. |
| 4. Hóa đơn/thu tiền — chủ trọ đã có | CT4–CT5: chỉ số, hóa đơn, thu tiền, công nợ | Đã có NT2: hóa đơn được cấp quyền, lịch sử thanh toán | Phía chủ trọ dùng được; nghiệm thu hai giao diện sau khi có NT2. |
| 5. Thu chi — đã có | CT6: thu khác/chi phí, nhật ký, hoàn tác | Đã kiểm tra chặn người thuê ở router/API | Gửi lại không nhân đôi, kể cả mất phản hồi; sửa/xóa/hoàn tác dùng phiên bản mới nhất. |
| 6. Báo hỏng/thông báo — chủ trọ đã có | CT7: tiếp nhận, ảnh riêng tư và cập nhật/hoàn tất; dùng hộp thông báo đã có | Đã có NT3; NT4 hộp thông báo/tài khoản dùng tiếp | Phía chủ trọ dùng được; tiếp tục luồng người thuê gửi ảnh → theo dõi cập nhật. |
| 7. Tổng quan — chủ trọ đã có | CT8: số liệu API và liên kết danh sách | Đã có trang vào và điều hướng người thuê theo nội dung thật | Hiện tại, kỳ hóa đơn, công nợ mọi tháng và dòng tiền thực tế được phân biệt rõ. |
| 8. Nghiệm thu/bàn giao | Toàn bộ luồng chủ trọ | Toàn bộ luồng người thuê | Qua checklist mục 6; điện thoại/máy tính, dữ liệu demo riêng và hướng dẫn chạy/build/triển khai. |

Mỗi gói tuân theo quy trình thiết kế → giao diện → API → nghiệm thu ở mục 2. Các API backend đã có, nên NT1/NT2 có thể thiết kế và nối trước bằng dữ liệu thử; CT3/CT5 là mốc để kiểm tra đầy đủ luồng thao tác từ cả hai giao diện. Kiểm tra quyền và trải nghiệm trong từng lượt. Chưa đặt thời hạn khi chưa chốt nguồn lực.

## 5. Quy tắc kết nối và trải nghiệm phải giữ

### Phiên và quyền

- Lấy `/api/auth/csrf-cookie`, gửi cookie credentials và `X-XSRF-TOKEN` hiện tại khi ghi; đọc `/api/auth/me` khi khởi động. Không dùng token localStorage thay phiên.
- Dùng nhất quán `localhost` hoặc `127.0.0.1`. CORS cho origin cụ thể khi cần; triển khai cấu hình HTTPS/cookie theo tên miền thực tế.
- Ẩn menu không thay phân quyền; dùng API tenant, không lọc dữ liệu owner trên trình duyệt.
- 401 dọn phiên/về đăng nhập; 403 báo không có quyền; 404 đối tượng không xem được; 409 tải lại để đối chiếu; 419 lấy lại CSRF có kiểm soát; 422 báo lỗi trường; 429 hiện thời gian chờ. Không tự retry vô hạn hoặc gửi lại mọi POST.

### Tiền, retry và phiên bản

- Tiền/chỉ số là chuỗi thập phân chuẩn, không dấu hàng nghìn; tổng nghiệp vụ do backend tính. Ngày giờ theo `Asia/Ho_Chi_Minh`, đúng định dạng API, không đổi ngầm sang UTC.
- Thu tiền và tạo thu khác/chi phí: một nghiệp vụ có một `idempotency_key`. POST thu chi bắt buộc khóa 8–64 ký tự ASCII theo API: **201** tạo mới, **200** gửi lại thành công, **409** cùng khóa nhưng khác payload/người tạo.
- Giữ khóa và **toàn bộ payload gốc**, gồm thời điểm, đến khi chắc kết quả; không đổi khóa sau timeout. Backend đối chiếu ảnh chụp payload/người tạo bất biến, kể cả sau khi khoản đã sửa.
- Retry trả bản ghi **hiện tại**, kể cả đã xóa; không tạo lại/khôi phục. Hiện đúng trạng thái, không tính replay là tiền mới. Nghiệp vụ mới dùng khóa mới dù nội dung giống nhau.
- Tắt nút khi gửi; chống trùng chính ở backend. Đổi tài khoản không tự retry giao dịch của tài khoản trước.
- Sửa/xóa/hoàn tác thu chi và xử lý sửa chữa dùng `version_token` mới nhất. Gặp xung đột giữ bản nhập để đối chiếu, không tự ghi đè. Hoàn tác chỉ sự kiện mới nhất, hiển thị trước/sau và lý do.
- Phát hành hóa đơn dùng `preview_token`; dữ liệu đổi phải xem trước lại. Hóa đơn/biên nhận đã xác nhận không có nút sửa/xóa nếu backend chưa có nghiệp vụ đó.

### Số liệu và sử dụng

- Giá thuê là giá toàn phòng; tiền phòng một lần/nhóm/tháng, dịch vụ theo phòng chia theo thời gian. Không tự chia nợ theo thành viên hoặc tính lại bằng giá danh mục mới.
- Hóa đơn theo tháng tính tiền, sổ tiền theo ngày thực thu/chi; phòng/người/sửa chữa trên tổng quan là hiện tại. Công nợ gồm đợt đã đóng, chưa gọi là quá hạn khi chưa có ngày đến hạn. Chênh lệch thu chi không ghi nhãn lợi nhuận kế toán.
- Có tải/rỗng/lỗi/thử lại, lỗi gần trường, dùng bàn phím và cảnh báo rời bản nhập. Bộ lọc trong URL, phân trang API, bảng dễ đọc trên điện thoại.
- Ảnh báo hỏng: tối đa 5 ảnh/yêu cầu, 5 MB/ảnh, loại/kích thước theo backend. Tạo yêu cầu xong mới upload; upload lỗi thì thử lại ảnh, không tạo lại yêu cầu. Xem ảnh qua API có phiên, không chuyển sang URL public/dịch vụ ngoài.
- Nội dung hiển thị dạng văn bản. Hộp thông báo hiện phân trang 15 mục, cập nhật số mỗi 60 giây khi tab hiển thị và khi quay lại; mở đối tượng tải mới bằng API đúng vai trò. Dữ liệu thông báo được xóa khi phiên/tài khoản thay đổi, phản hồi cũ không được khôi phục dữ liệu riêng tư. Sau thao tác tải lại số tổng/danh sách liên quan.

## 6. Checklist nghiệm thu trình duyệt

Dùng tài khoản owner, đại diện, thành viên thường, người đã rời phòng và người không được cấp quyền trên dữ liệu demo riêng.

| Nhóm | Tình huống cần qua |
| --- | --- |
| Phiên và quyền | Đăng nhập đúng/sai, khóa tài khoản, tải lại/mở nhiều tab, hết phiên/đăng xuất, tenant mở URL owner, đổi mật khẩu rồi thử phiên cũ. |
| Phòng một người | Có/không tài khoản, nhận phòng/chọn đại diện, chỉ số, hóa đơn, thu từng phần/đủ, trả phòng, tiếp tục thu nợ cũ. |
| Phòng nhiều người | Thêm/rời/đổi đại diện, chặn vượt sức chứa, chuyển cả nhóm giữa tháng, đổi giá; không lặp tiền phòng và giữ đúng lịch sử dịch vụ. |
| Riêng tư | Chỉ hồ sơ/lịch sử bản thân; hóa đơn theo grant; thu hồi/chuyển grant mất quyền cũ; đại diện mới không tự có dữ liệu cũ; không mở được ảnh người khác. |
| Thu tiền | Mất phản hồi sau khi server ghi rồi retry nhận cùng biên nhận; payload đổi bị chặn; không thu vượt; hai tab thu đồng thời giữ số dư đúng. |
| Thu khác/chi phí | Retry không nhân đôi; sửa/xóa khoản rồi retry payload gốc trả trạng thái hiện tại, không khôi phục; người tạo khác/khác payload cùng khóa bị chặn; khóa mới là nghiệp vụ mới; token cũ/hoàn tác sự kiện cũ bị chặn. |
| Sửa chữa và ảnh | Thành viên thường không tạo; ảnh sai/quá giới hạn bị chặn, ảnh gửi lại không nhân bản; upload lỗi không tạo lại yêu cầu; token cũ bị chặn; hoàn tất cần kết quả, không nhận ảnh mới. |
| Thông báo | Báo hỏng đến owner, cập nhật đến tác giả kể cả đã rời; nhắc không trùng trong ngày; thu đủ/thu hồi grant không lộ thông báo cũ; đọc một/tất cả đúng số chưa đọc. |
| Số liệu | Hóa đơn tháng cũ thu tháng mới; tổng quan/công nợ/sổ tiền đúng phạm vi; xóa/khôi phục đúng số tổng; không cộng hóa đơn chưa thu thành thực thu. |
| Trải nghiệm/bàn giao | Điện thoại/bàn phím/mạng chậm/mất mạng; mã lỗi nêu trên; URL con tải lại được; build đúng môi trường, không lộ bí mật; cài từ bản mã nguồn mới. |

## 7. Bàn giao và vận hành

- Mã nguồn, khóa phụ thuộc, cấu hình mẫu, hướng dẫn cài/chạy/build và triển khai đường dẫn con.
- Dữ liệu demo riêng, hướng dẫn hai vai trò, kết quả kiểm thử/kịch bản demo toàn quy trình.
- HTTPS/cookie/CORS theo tên miền; upload và thư mục ảnh riêng tư phù hợp; sao lưu cả CSDL/ảnh và thử khôi phục. Bộ lập lịch Laravel phải chạy để thực hiện nhắc 08:00; chỉ `artisan serve` không chạy lịch.
- Mở rộng sau: đăng ký công khai, quên/đặt lại mật khẩu, gắn/xác minh email. Khi được yêu cầu mới chốt luồng tài khoản/kênh nhận, token hết hạn/dùng một lần và phản hồi không lộ tài khoản; không giả định tài khoản cũ có email.

Tài liệu API: [xác thực](authentication.md), [phòng/dịch vụ](rooms-services.md), [quá trình thuê](tenant-occupancy.md), [điện nước/hóa đơn](meter-readings-invoices.md), [thu tiền](invoice-payments.md), [thu chi](cash-book.md), [tổng quan/công nợ](dashboard-receivables.md), [cổng người thuê](tenant-portal.md), [nhật ký/ảnh/thông báo](audit-photos-notifications.md).
