# Kế hoạch các bước tiếp theo — RentBase, 02/11/2026

**Kế hoạch hiện hành 06/11:** [chốt Android, dữ liệu trình, GitHub, bản dự phòng và lịch chuẩn bị trước 09/11](next-steps-plan-2026-11-06.md). Lịch và hiện trạng bên dưới giữ làm lịch sử; vận hành và giao diện đã được nghiệm thu bổ sung ngày 04/11.

**Cập nhật 04/11:** bước kiểm tra vận hành sau một đêm đã đạt: sao lưu 02:15, nhắc quá hạn 08:00, 75 điểm kiểm tra HTTPS, ảnh và số dư khớp. [Biên bản mới](overnight-operation-acceptance-2026-11-04.md). Tiếp theo nghiệm thu Android thật, chốt dữ liệu/mã nguồn và tập trình giảng viên.

**Cập nhật 03/11:** đã chuyển CSDL online từ Aiven sang MariaDB Alwaysdata và thêm sao lưu 02:15; nhắc hóa đơn giữ 08:00. [Nghiệm thu mới và các kiểm tra thiết bị/lịch còn chờ](mariadb-migration-acceptance-2026-11-03.md). Những mô tả Aiven hoặc chưa có sao lưu định kỳ bên dưới là trạng thái lúc lập kế hoạch.

## 1. Mục tiêu và phạm vi

Ưu tiên hoàn tất một bản sản phẩm có thể tự dùng trên web/Android và trình giảng viên trong tuần đầu hoặc đầu tuần hai tháng 11. Sau khi giảng viên xem sản phẩm mới chọn nghiệp vụ bổ sung; làm Word sau khi chốt sản phẩm theo góp ý.

**Cập nhật thực hiện 02/11:** lịch nhắc đã được sửa và nghiệm thu chạy tự động; đã kiểm tra lại dữ liệu mẫu. Xem [biên bản và các tình huống có/chưa có mẫu](scheduler-and-demo-audit-2026-11-02.md). Tiếp theo ưu tiên nghiệm thu Android thật.

- Giữ kiến trúc Vue/PWA + Laravel + MySQL hiện tại.
- Tiếp tục dùng bản dữ liệu giả trên hosting miễn phí cho đợt đánh giá.
- Không thực hiện quản lý hợp đồng. Quên mật khẩu vẫn hoãn theo lựa chọn trước.
- Bản thử có tài khoản chủ trọ và người thuê được cấp riêng. Tài khoản vận hành dữ liệu thật sẽ được chuẩn bị khi chuyển sang sử dụng thực tế.
- Đây là kế hoạch; chưa thực hiện các nâng cấp, chưa đẩy GitHub hoặc mua dịch vụ trong lượt lập kế hoạch này.

## 2. Hiện trạng làm căn cứ

| Phần | Đã xác nhận | Còn phải đối chiếu |
| --- | --- | --- |
| Hosting | Vue/Laravel tại https://rentbase.alwaysdata.net/login, MySQL Aiven, ảnh riêng tư bền vững | Tài nguyên khi dùng lâu, nhật ký và vận hành định kỳ |
| Nghiệp vụ | Các mô-đun chủ trọ/người thuê; PDF, ảnh, thu chi, sửa chữa, thông báo và công nợ đã có | Trải nghiệm xuyên suốt trên Android thật |
| Kiểm thử gần nhất | 406 backend / 4.193 assertions; 134 frontend; 58 bước HTTPS ngày 01/11 | Đây là kết quả đã chạy, không thay thế kiểm tra lịch tự động hoặc thao tác thiết bị thật |
| PWA | Manifest, icon, khung offline, cập nhật có xác nhận; người dùng xác nhận cài Android thành công ngày 02/11 | Mẫu máy/Chrome, mở từ biểu tượng, camera, trình xem PDF, bàn phím, mất mạng và cập nhật thật |
| Sao lưu | Đã sao lưu/khôi phục thử MySQL riêng: 29 bảng gồm migrations, số dư và 3 ảnh khớp; có bản ngoài hosting | Chưa có quy trình sao lưu định kỳ được nghiệm thu |
| Lịch nhắc | Scheduled Task 37205 chạy mỗi phút, đã xác nhận hai lượt tự động mã 0 và thử nhắc không trùng | Tiếp tục theo dõi lượt đồng hồ thực tế 08:00 sau sửa |
| Mã nguồn | Mã mới có trong workspace và bản đóng gói đã lên hosting | Còn nhiều thay đổi chưa commit/push; cần chốt mốc phát hành |

Không tạo lại các chức năng đã có như PDF, lịch sử sửa chữa, điều chỉnh hóa đơn hay thông báo chung. Bộ MySQL online `defaultdb`, MySQL phát triển `rental_management` và SQLite trình diễn là các dữ liệu riêng, không tự đồng bộ.

## 3. Các việc cần làm trước khi trình sản phẩm

### P0.1 — Sửa và nghiệm thu lịch nhắc hóa đơn

**Đã thực hiện ngày 02/11.** Khi lập kế hoạch, tác vụ liên tục trả exit code 255 vì đường dẫn PHP phiên bản cũ không nạp `php.ini` của tài khoản. Hiện đã sửa theo các bước dưới đây và quan sát các lượt tự động thành công; xem biên bản mới ở đầu tài liệu.

**Các bước:**

1. Sửa tác vụ hiện có 37205; không tạo thêm tác vụ trùng.
2. Đổi **Value** thành:

```sh
/usr/bin/php /home/rentbase/current/artisan schedule:run
```

3. Giữ Working directory là `current` sau tiền tố `/home/rentbase/`, SSH user `rentbase`, Every `1` minute, tác vụ hoạt động.
4. Kiểm tra ít nhất hai lượt tự động liên tiếp có exit code 0 trong log của Alwaysdata.
5. Đối chiếu `schedule:list`: nhắc quá hạn lúc 08:00 theo `Asia/Ho_Chi_Minh`. Theo dõi lượt 08:00 kế tiếp hoặc dùng tình huống kiểm thử có kiểm soát; kiểm tra đúng người nhận và không tạo nhắc trùng.

**Đã xác nhận:** `/usr/bin/php` nạp cấu hình đúng; `schedule:list` hiện lịch 08:00 Việt Nam; các lượt tự động sau sửa mã 0. Thử nhắc hóa đơn mẫu hai lần: lần đầu tạo 1 thông báo, lần sau trả lại thông báo cũ, không đổi số dư và không lộ cho người thuê khác. Lượt đồng hồ thực tế 08:00 sau sửa chưa đến thời điểm quan sát.

**Đạt khi:** tác vụ tự động dùng đúng PHP, log thành công, múi giờ đúng và kiểm tra nhắc đúng hạn/chống trùng có bằng chứng. Người dùng sửa form quản trị; phần kiểm tra log và nghiệp vụ có thể thực hiện qua SSH.

**Ước lượng:** 30–60 phút xử lý cấu hình/kiểm tra; mốc 08:00 thực tế phụ thuộc thời gian chạy tiếp theo.

### P0.2 — Nghiệm thu Android thật và sửa lỗi ảnh hưởng sử dụng

Ghi mẫu máy, Android, Chrome và ngày kiểm tra. Thực hiện bằng dữ liệu giả; cài thành công không đồng nghĩa đã đạt toàn bộ các ca dưới đây.

| Ca kiểm tra | Cách thử | Tiêu chí đạt |
| --- | --- | --- |
| Mở ứng dụng | Mở từ biểu tượng, đóng rồi mở lại | Vào được ứng dụng; ghi rõ chế độ mở thực tế |
| Hai vai trò | `admin` trên máy tính, `user` trên Android; sau đó đổi vai trò bằng đăng xuất | Vào đúng khu vực, không còn dữ liệu tài khoản trước |
| Phòng/hóa đơn | Xem phòng và kỳ 09/10 năm 2026 | Chỉ thấy dữ liệu được cấp quyền, tiền/trạng thái khớp chủ trọ |
| Camera và ảnh | Chụp/chọn ảnh, xem trước, gửi, đóng rồi mở lại yêu cầu | Ảnh còn trên server và xem đúng quyền; hủy chọn không làm mất nội dung nhập |
| PDF | Tải hóa đơn/phiếu thu, mở bằng trình xem tệp Android | Tiếng Việt, tổng tiền và trạng thái đúng |
| Bàn phím và điều hướng | Nhập biểu mẫu, xoay ngang, nút quay lại | Thấy ô nhập/nút xác nhận, không tràn ngang hoặc mất bản nhập ngoài ý muốn |
| Mạng chập chờn | Mất mạng khi xem và khi gửi dữ liệu mẫu | Báo trạng thái rõ; giữ yêu cầu cần đối chiếu; có mạng lại không tự gửi thêm giao dịch |
| Cập nhật PWA | Mở bản cài cũ khi có một bản phát hành kiểm soát | Có thể để sau; bảo vệ biểu mẫu/ảnh/giao dịch đang chờ trước khi cập nhật |
| Độc lập Windows | Tắt hoặc ngắt mạng Windows, dùng điện thoại 4G; đăng nhập mới và lưu báo hỏng mẫu | Đọc/ghi được trên cloud, không chỉ mở khung đã lưu trên máy |

Ghi lỗi theo mẫu: thiết bị → màn hình → bước tái hiện → mong đợi → thực tế → ảnh/bằng chứng → mức ảnh hưởng. Ưu tiên lỗi quyền xem, ghi trùng tiền, sai số dư, mất dữ liệu, không đăng nhập hoặc không lưu được. Các lỗi chữ/khoảng cách nhỏ xử lý sau các lỗi này.

**Đạt khi:** các luồng cần trình chạy được trên Android thật, lỗi nghiêm trọng đã sửa, hạn chế còn lại có ghi nhận cụ thể. Chạy kiểm thử phù hợp với thay đổi; mở rộng kiểm thử khi còn rủi ro liên quan, không chỉ tăng số lượng ca.

**Ước lượng:** 0,5–1 ngày kiểm tra; thời gian sửa phụ thuộc lỗi tìm được. Người dùng thao tác trên điện thoại; phần tái hiện/sửa mã và đối chiếu dữ liệu thực hiện từ workspace/hosting.

### P0.3 — Chốt dữ liệu trình, mã nguồn và khả năng quay lại

1. Chọn bộ dữ liệu mẫu dùng cho buổi trình, ghi kỳ hóa đơn, phòng sẵn sàng lập hóa đơn và số dư trước từng bước.
2. Tập quy trình: nhận phòng/dịch vụ → điện nước → hóa đơn → thu một phần/đủ → công nợ/sổ tiền → báo hỏng/ảnh/lịch sử/chi phí → thông báo/người thuê.
3. Tạo bản sao CSDL và ảnh trước khi tập; chỉ khôi phục theo đúng môi trường. Không dùng công cụ khôi phục SQLite cục bộ để thay MySQL cloud.
4. Rà toàn bộ thay đổi mã nguồn; loại cấu hình riêng, khóa, dữ liệu, log và thư mục `.cache` khỏi commit.
5. Commit/push và đặt mốc bản trình khi chuyển sang thực hiện bước này; ghi mã commit, phiên bản schema và bản phát hành hosting tương ứng.
6. Giữ bản mã trước và hướng dẫn quay lại. Với thay đổi schema, kiểm tra tương thích/khôi phục dữ liệu riêng; đổi liên kết mã nguồn không tự hoàn tác được schema.
7. Giữ bản trình cục bộ dự phòng đã chuẩn bị để sử dụng nếu mạng tại buổi đánh giá không thuận lợi.

**Đạt khi:** dữ liệu dự đoán được khi trình, có bản sao khôi phục, mã trên GitHub tương ứng bản chạy và không chứa bí mật. Chưa push trong lượt lập kế hoạch này.

**Ước lượng:** 0,5 ngày sau khi chốt các sửa lỗi P0.

### P0.4 — Tập trình giảng viên và lấy góp ý

- Chuẩn bị kịch bản 10–12 phút, đường dẫn đăng nhập, tài khoản mẫu, kỳ hóa đơn và một tình huống sửa chữa có ảnh/PDF.
- Nói rõ kiến trúc Vue/PWA–Laravel–MySQL; cùng một backend kiểm tra quyền cho web và Android.
- Trình bày trung thực: PWA đã cài thật; hosting dùng gói miễn phí; phần đã nghiệm thu và phần còn giới hạn.
- Ghi góp ý thành ba nhóm: lỗi phải sửa, chức năng giảng viên yêu cầu và ý tưởng có thể để sau.
- Sau buổi xem sản phẩm mới chọn phạm vi bổ sung, chốt lại sản phẩm rồi làm Word theo mẫu được yêu cầu.

**Đạt khi:** người dùng tự trình được không cần mở công cụ lập trình, thử được hai vai trò và giải thích được các số tiền trước/sau thao tác.

**Ước lượng:** 1–2 lượt tập và 0,5 ngày chuẩn bị.

## 4. Các nâng cấp sau khi bản trình ổn định

| Ưu tiên | Việc triển khai | Nội dung/đầu ra | Tiêu chí chốt |
| --- | --- | --- | --- |
| P1 | Trang cài `/install` và QR | Dùng URL HTTPS cố định, hướng dẫn Android/iPhone, sao chép/chia sẻ liên kết; mở được từ đăng nhập và hai vai trò | QR chỉ chứa URL công khai, mở đúng trang; không yêu cầu đăng nhập để xem hướng dẫn; không tự nhận là đã cài chỉ vì bấm nút |
| P1 | Sao lưu định kỳ và quản lý dung lượng | Đóng gói quy trình sao lưu CSDL/ảnh/cấu hình cần khôi phục, lưu ngoài web root, giới hạn số bản theo dung lượng; giữ thêm bản ngoài host | Chạy theo lịch, ghi kết quả, thử khôi phục riêng và đối chiếu dòng/số dư/ảnh; không giữ vô hạn trên gói 1 GB |
| P1 | Quy trình cập nhật lặp lại được | Đưa các bước triển khai đã dùng thành công cụ/tài liệu có cấu hình mẫu; giữ APP_KEY, ảnh/session và asset cũ; có cách quay lại | Đổi bản không gieo lại dữ liệu, không mất ảnh hoặc làm bỏ bản nhập/giao dịch chờ của người dùng |
| P1 | Theo dõi lỗi và kiểm tra tài nguyên | Ghi lỗi, thời gian đăng nhập/tải trang/PDF/upload, dung lượng và kết quả tác vụ; thông báo lỗi dễ hiểu | Có cách phát hiện/tra lỗi thực tế; thử hai vai trò đồng thời không gặp lỗi hết bộ nhớ/timeout ở luồng trình |

Đây là phần tăng chất lượng vận hành, chưa được thực hiện bởi việc tạo file kế hoạch. Trang cài/QR không phải điều kiện để người đã cài tiếp tục dùng PWA. Mỗi mục chọn làm riêng, kiểm tra và đưa lên host theo một bản phát hành có thể quay lại.

## 5. Trước khi dùng dữ liệu thật lâu dài

Thực hiện sau khi chốt phạm vi vận hành thực tế:

1. Chọn hosting/MySQL phù hợp mục đích sử dụng, tài nguyên và độ sẵn sàng cần có; quyết định ngân sách trước khi mua/nâng gói. Giữ kiến trúc PWA hiện tại.
2. Tạo tài khoản vận hành có mật khẩu mạnh, kiểm tra quyền và quy trình cấp/khóa/đặt lại tài khoản. Tài khoản thử ngắn tiếp tục chỉ dành cho bản dữ liệu mẫu.
3. Chuẩn bị môi trường dữ liệu thật riêng, kiểm tra/import có đối chiếu. Không trộn tùy tiện MySQL phát triển, demo online và dữ liệu vận hành.
4. Nghiệm thu lịch nhắc, sao lưu/khôi phục, nhật ký lỗi, giới hạn ảnh và cách xử lý khi dịch vụ/CSDL không sẵn sàng.
5. Thử tải theo số phòng/tài khoản thực tế, ghi kết quả rồi quyết định cấu hình. Các số kiểm thử hiện có không phải kết quả đo tải đông người.

## 6. Chọn nghiệp vụ bổ sung sau góp ý

Chỉ chọn một mục trong một đợt để còn thời gian kiểm tra:

| Ứng viên | Giá trị | Điều kiện nghiệm thu chính |
| --- | --- | --- |
| Xuất Excel hóa đơn/công nợ/sổ thu chi | Chủ trọ đối chiếu và lưu báo cáo | Đúng bộ lọc, đủ dữ liệu các trang, tổng khớp giao diện, phân quyền và an toàn với nội dung nhập vào ô |
| Ảnh chỉ số điện/nước | Có bằng chứng cho kỳ ghi số | Gắn đúng phòng/kỳ/chỉ số, ảnh riêng tư, hiển thị đúng quyền và giữ qua cập nhật |
| Một nghiệp vụ theo góp ý giảng viên | Tập trung vào yêu cầu đánh giá thật | Chốt quy tắc → CSDL/API → giao diện liên quan → kiểm thử đối chiếu; tránh làm lại chức năng đã có |

Thông báo hiện tại là thông báo trong ứng dụng. Web Push khi đóng app, tích hợp ngân hàng, chat hoặc app Android riêng chỉ cân nhắc khi có nhu cầu được chọn; không đưa vào điều kiện hoàn thành bản trình hiện tại.

## 7. Mốc nội bộ dự kiến

Đây là mốc chuẩn bị đề xuất, không phải lịch báo cáo đã được giảng viên xác nhận. Nếu giảng viên xem sớm hơn, dùng bản online hiện có và ưu tiên P0.1–P0.2; các bổ sung P1 không chặn việc trình sản phẩm.

| Mốc | Kết quả cần có |
| --- | --- |
| 02/11 | Sửa lịch, xem log tự động; ghi thông tin máy và bắt đầu checklist Android |
| 03/11 | Sửa lỗi nghiêm trọng tìm được; đối chiếu camera/PDF, tiền/quyền và ca dùng 4G khi Windows tắt |
| 04/11 | Chốt bộ dữ liệu, bản sao và mốc GitHub; tập kịch bản hai vai trò |
| 05–06/11 | Dự phòng sửa lỗi và tập lại; giữ bản trình ổn định |
| Nếu lịch sang đầu tuần hai | Tiếp tục duy trì bản đã chốt; chỉ nhận sửa lỗi cần thiết trước buổi xem |
| Sau giảng viên đánh giá | Chọn P1/nghiệp vụ bổ sung theo góp ý, chốt sản phẩm rồi mới làm Word |

**Lịch nhắc đã được sửa. Bước triển khai tiếp theo: chạy checklist Android thật và sửa lỗi tìm được, sau đó chốt dữ liệu/mã nguồn để trình giảng viên.**

Tài liệu liên quan: [bản online](hosting-rentbase-2026-11-01.md), [cài Android](../frontend/docs/mobile-android-guide.md), [kịch bản bản trình cục bộ](../frontend/docs/presentation-guide.md), [Scheduled Tasks Alwaysdata](https://help.alwaysdata.com/en/docs/web-hosting/tasks/).
