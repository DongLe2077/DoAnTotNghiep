# Kế hoạch hoàn thiện, nâng cấp và tài khoản tự kiểm tra — 25/10/2026

Đề xuất dựa trên chức năng và tài liệu hiện có. Đây là kế hoạch cho các đợt tiếp theo; chưa triển khai các chức năng mới trong lần lập kế hoạch này. Mục tiêu là hoàn thiện nghiệp vụ đang dùng, thêm chứng từ/báo cáo và chuẩn bị đưa lên hosting.

**Ưu tiên mới theo yêu cầu giảng viên, chốt ngày 25/10/2026:** chuẩn bị sản phẩm để giảng viên xem trong tuần đầu hoặc đầu tuần 2 tháng 11; sau đánh giá mới quyết định chức năng thêm, rồi mới làm Word. Mốc nội bộ đề xuất: sẵn sàng 31/10, dự phòng 01/11. [Kế hoạch chi tiết theo ngày và kịch bản trình sản phẩm](../../docs/initial-report-plan-2026-11.md). Các phần PDF/Excel và mở rộng dưới đây thuộc kế hoạch sau đánh giá.

## Chạy và đăng nhập

Trong thư mục frontend, chạy `npm run demo`, mở http://127.0.0.1:5174/login.

| Tài khoản | Mật khẩu ban đầu | Vai trò | Trang sau đăng nhập |
| --- | --- | --- | --- |
| admin | 123 | Chủ trọ | /owner |
| user | 123 | Người thuê | /tenant |

Tài khoản user được tạo cùng phòng TEST-USER, một người đại diện, điện/nước/internet, hóa đơn tháng trước còn quyền xem và yêu cầu sửa chữa mẫu. Tên admin là tên đăng nhập của vai trò chủ trọ; hệ thống hiện có hai vai trò owner/tenant. Truy cập trang/API của vai trò khác bị chặn.

Bản chạy thử dùng Laravel thật, SQLite riêng, ảnh và phiên riêng. Khi khởi động lại giữ dữ liệu đã thử và thư mục ảnh; không tự tạo lại phòng/hóa đơn của tài khoản user đã có, không đặt lại mật khẩu đã đổi. Các tài khoản demo cũ vẫn được giữ. Các kiểm thử tự động tiếp tục dùng SQLite khác và khởi tạo fixture riêng.

## Tự kiểm tra trước khi mở rộng

1. Đăng nhập admin, thử phòng/dịch vụ/người thuê, hóa đơn/thu tiền/công nợ, thu chi và sửa chữa. Ghi lại màn hình, thao tác và kết quả chưa đúng mong muốn.
2. Đăng xuất rồi đăng nhập user, kiểm tra phòng/hóa đơn và lịch sử thay đổi; tạo báo hỏng, gửi ảnh, xem thông báo. Thử gõ /owner để xác nhận không được truy cập.
3. Mở admin ở trình duyệt/hồ sơ khác, cập nhật sửa chữa hoặc thu một phần hóa đơn của TEST-USER. Quay lại user và làm mới để đối chiếu kết quả.
4. Thử trên điện thoại hoặc cửa sổ hẹp. Các tab cùng trình duyệt dùng chung phiên; muốn thử hai vai trò đồng thời thì dùng hai trình duyệt/hồ sơ.

## Thứ tự hoàn thiện và nâng cấp đề xuất

| Ưu tiên | Việc làm | Đầu ra / tiêu chí hoàn thành |
| --- | --- | --- |
| 1 — Cần chốt | Nghiệm thu hai vai trò và sửa điểm khó dùng | Thử đầy đủ trên MySQL, Chrome/Edge và điện thoại thật; không còn lỗi nghiêm trọng về tiền, quyền xem hoặc mất dữ liệu |
| 2 — Cân nhắc sau giảng viên đánh giá | Xuất PDF hóa đơn và phiếu thu | Tải/in được tiếng Việt, đúng quyền; số tiền, các lần thu, điều chỉnh, hạn và trạng thái khớp giao diện |
| 3 — Cân nhắc sau giảng viên đánh giá | Xuất Excel hóa đơn, công nợ và sổ thu chi | Chủ trọ xuất đúng bộ lọc; tổng khớp giao diện, phân biệt tiền đã thu với khoản còn nợ |
| 4 — Bắt buộc trước chạy trực tuyến | Hosting, lưu ảnh bền vững, sao lưu/khôi phục và tài liệu bàn giao | Cài mới được; ảnh giữ qua cập nhật; khôi phục thử cả CSDL và ảnh; HTTPS/cookie, lịch nhắc và theo dõi lỗi hoạt động |
| 5 — Nâng cấp hữu ích | Liên kết chi phí sửa chữa | Lịch sử từng lần xử lý đã có ngày 25/10/2026; còn phần liên kết khoản chi với yêu cầu, không cộng tiền hai lần |
| 6 — Nên có trước dùng tiền thật thường xuyên | Sửa sai lần thu tiền hóa đơn có kiểm soát | Có lý do và bản ghi đối chiếu, giữ phiếu gốc; số dư hóa đơn và sổ tiền khớp sau xử lý; chốt quy tắc trước khi code |
| 7 — Tùy phạm vi đề tài | Hợp đồng, tiền cọc và quyết toán trả phòng | Quản lý kỳ hạn/gia hạn, thu/khấu trừ/hoàn cọc và bàn giao; hai vai trò xem đúng quyền |
| 8 — Có thêm sẽ thuận tiện | QR chuyển khoản và ảnh đồng hồ điện/nước | QR đúng tài khoản/số tiền/nội dung; ảnh gắn đúng kỳ chỉ số và có quyền xem; QR không tự xác nhận đã nhận tiền |
| 9 — Không bắt buộc | Chat, phân công thợ, email/SMS, thông báo tức thời, đối soát ngân hàng | Chỉ mở rộng sau khi chốt các phần trên và nhu cầu sử dụng |

Quên mật khẩu tiếp tục để sau theo quyết định của người dùng. Hiện có đổi mật khẩu khi biết mật khẩu cũ và chủ trọ đặt lại tài khoản người thuê. Hạn đóng, công nợ quá hạn, điều chỉnh/hủy/khôi phục và nhật ký hóa đơn đã có. Thông báo chung toàn bộ/theo phòng, xem trước người nhận và theo dõi đã đọc cũng đã hoàn thành; [tự kiểm tra](announcements-self-test.md).

## Đợt 1: chốt chất lượng hiện tại

Ngày 25/10/2026 đã nghiệm thu cục bộ hai vai trò và sửa các điểm giao diện tìm được. Xem [biên bản, các lượt kiểm tra và giới hạn](ui-acceptance.md). Phần còn cần đối chiếu trước bàn giao là điện thoại thật, cài mới trên máy khác và môi trường hosting.

- Thử xuyên suốt nhận phòng → dịch vụ → chỉ số → hóa đơn → thu từng phần/đủ → chuyển/trả phòng; đối chiếu tổng quan/công nợ/sổ thu chi.
- Thử ảnh sự cố, cập nhật sửa chữa và thông báo chung ở hai trình duyệt/hồ sơ; kiểm tra đúng người nhận, trạng thái đọc và quyền sau chuyển/trả phòng.
- Rà soát màn hình hẹp, chữ/nút dễ đọc, thông báo lỗi và bộ lọc. Giữ thiết kế tối giản hiện có.
- Làm rõ hướng dẫn đăng nhập theo vai trò, dùng chung phiên giữa các tab, khởi động/dừng và cổng đang chạy. Trang không có quyền phải có đường về đúng khu vực; không bỏ kiểm tra quyền để né lỗi.
- Sửa tài liệu cũ còn ghi frontend chưa làm hoặc demo bị tạo lại mỗi lần chạy. Kiểm thử cài mới trên máy khác dùng MySQL.

Đầu ra: danh sách lỗi/điểm khó dùng và kết quả đối chiếu; lỗi nghiêm trọng được xử lý, các hạn chế còn lại được ghi rõ. Các con số kiểm thử trong README là của những đợt đã chạy, không thay thế nghiệm thu thiết bị/môi trường mới.

## Đợt 2: chứng từ và báo cáo, sau khi được chọn trong phạm vi bổ sung

Nếu chọn cả hai sau buổi đánh giá: **PDF trước, Excel sau.** Backend tạo tệp từ dữ liệu đã được kiểm tra quyền; frontend bổ sung nút tải/in trong màn hình đang có.

- PDF hóa đơn: phòng/nhóm/kỳ, các dòng tiền gốc, điều chỉnh, hạn thanh toán, đã thu/còn nợ và trạng thái hủy. Phiếu thu PDF gắn đúng một lần nhận tiền và mã phiếu. Hiển thị rõ thời điểm xuất; đối chiếu dữ liệu hiện tại với lịch sử. Đây là chứng từ quản lý nội bộ của ứng dụng.
- Người thuê chỉ tải hóa đơn và phiếu thu thuộc hóa đơn đang có quyền xem; chủ trọ tải theo quyền quản lý. Tệp không để ở đường dẫn công khai bỏ qua đăng nhập.
- Kiểm tra font tiếng Việt, in nhiều trang, dòng dài, số tiền thập phân và trạng thái hủy/điều chỉnh.
- Excel dành cho chủ trọ: bộ lọc kỳ/thời gian, phòng/nhóm, trạng thái giống màn hình; xuất đủ các trang dữ liệu, có tổng và thời điểm xuất. Ngày thực thu/chi được tách khỏi tháng hóa đơn; nội dung nhập từ người dùng không được trở thành công thức ngoài ý muốn.

Đầu ra: tải được tệp mẫu và đối chiếu tổng với API/giao diện; người không có quyền không tải được bằng URL trực tiếp.

## Đợt 3: chuẩn bị hosting và bàn giao

Có thể chuẩn bị song song đợt 1–2, phải hoàn thành trước khi sử dụng trực tuyến. Chưa chọn nhà cung cấp hoặc mua dịch vụ trong kế hoạch này.

- Chọn nơi chạy Laravel/PHP/MySQL và nơi phục vụ frontend đã đóng gói; cấu hình đường dẫn API, HTTPS, tên miền và phiên đăng nhập. Backend phục vụ qua `public`, cấp quyền ghi đúng cho `storage`/`bootstrap/cache`, tắt debug trên môi trường thật theo [hướng dẫn triển khai Laravel 12](https://github.com/laravel/docs/blob/12.x/deployment.md).
- Ảnh sự cố đã có chức năng tải lên. Việc còn lại là cấu hình nơi giữ ảnh bền vững trên hosting, giới hạn upload và sao lưu. Mã hiện tại dùng disk riêng `maintenance` với thư mục ảnh riêng tư; dùng ổ đĩa bền vững hoặc bổ sung bộ lưu trữ S3 nếu môi trường yêu cầu. [Các lựa chọn lưu trữ Laravel](https://github.com/laravel/docs/blob/12.x/filesystem.md).
- Chuyển cả CSDL và ảnh khi chuyển máy; lưu bản sao ngoài máy chạy ứng dụng, hạn chế quyền truy cập bản sao. Thử khôi phục trên môi trường riêng và mở lại được ảnh tương ứng. Cập nhật mã không được xóa thư mục ảnh.
- Nhắc công nợ đã có quy tắc/lịch trong mã. Cần cấu hình tiến trình lịch của máy chủ, kiểm tra thực thi và kết quả theo [Laravel scheduler](https://github.com/laravel/docs/blob/12.x/scheduling.md).
- Đổi mật khẩu mẫu trước khi dùng dữ liệu thật, kiểm tra tài khoản/nhật ký lỗi, hoàn thiện hướng dẫn cài/chạy, API và kịch bản demo báo cáo đề tài.

Đầu ra: cài được từ tài liệu trên môi trường mới; tải/xem ảnh đúng quyền, cập nhật website không mất ảnh, khôi phục thử thành công, lịch nhắc thực sự chạy. Chỉ đưa lên hosting công khai khi người dùng yêu cầu triển khai.

## Đợt 4: bổ sung nghiệp vụ có giá trị

**Lịch sử sửa chữa:** đã hoàn thành ngày 25/10/2026: người thực hiện, thời điểm, trạng thái/ghi chú trước sau, quyền xem hai vai trò, phân trang và mốc dữ liệu cũ. [Tự kiểm tra](maintenance-history-self-test.md). Phần liên kết chi phí với sổ thu chi để tránh ghi nhận hai lần vẫn là nâng cấp riêng chưa thực hiện.

**Sửa sai lần thu tiền hóa đơn:** hiện phiếu thu đã xác nhận không sửa/xóa/hoàn tiền qua API. Đây là nâng cấp riêng với điều chỉnh hóa đơn và hoàn tác thu khác/chi phí đã có. Trước khi làm phải chốt ai được xử lý, lý do, trường hợp hủy nhầm hay hoàn tiền thực tế, quy tắc ngày ghi nhận và giới hạn số tiền. Giữ phiếu gốc, thêm bản ghi đối chiếu; kiểm tra số dư, sổ tiền, báo cáo và chống gửi trùng sau thao tác.

**Hợp đồng/cọc/quyết toán:** chỉ triển khai nếu thuộc phạm vi đề tài hoặc có nhu cầu thực tế. Cần chốt quan hệ với nhóm/đợt thuê, quy tắc gia hạn, các lần thu/khấu trừ/hoàn cọc, tiền điện nước cuối kỳ và các khoản còn nợ. Không tự gộp cọc vào tiền thuê hoặc tự xóa công nợ khi trả phòng.

Các mục này làm từng phần từ quy tắc → CSDL/API → màn hình chủ trọ → màn hình người thuê → kiểm thử đối chiếu. Có nâng cấp CSDL thì sao lưu, giữ dữ liệu hiện có và không tạo lại bảng gốc.

## Phạm vi nên chốt cho đợt tới

Nghiệm thu cục bộ/sửa giao diện và lịch sử sửa chữa đã thực hiện. Ưu tiên hiện tại: **bản sản phẩm để trình → giảng viên đánh giá → chốt bổ sung → hoàn thiện → Word**. PDF/Excel, liên kết chi phí và các nghiệp vụ khác được xem xét sau đánh giá. Hosting chỉ trở thành việc trước buổi xem nếu cần giảng viên truy cập từ xa; bản chạy tại máy vẫn là phương án chuẩn bị chính.

QR chuyển khoản, ảnh đồng hồ, chat và thông báo ngoài ứng dụng là phần mở rộng. Quên mật khẩu vẫn hoãn theo yêu cầu trước đó; chưa cần đổi công nghệ hoặc làm thêm biểu đồ để hoàn thành các mục trên.
