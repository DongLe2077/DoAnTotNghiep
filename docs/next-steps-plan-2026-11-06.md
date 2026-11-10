# Kế hoạch chốt sản phẩm để giảng viên đánh giá — 06/11/2026

## 1. Mục tiêu và mốc chuẩn bị

Chốt một bản web/Android có thể tự trình và tự thao tác, đủ dữ liệu minh họa, có mã nguồn tương ứng trên GitHub và bản dự phòng. Sau khi giảng viên xem sản phẩm, phân loại góp ý, hoàn thiện phần được yêu cầu rồi mới làm Word theo mẫu của trường.

**Mốc chuẩn bị đề xuất: hoàn tất trước ngày 09/11/2026.** Đây là lịch nội bộ dựa trên dự kiến báo cáo tuần đầu hoặc đầu tuần hai tháng 11; chưa phải ngày báo cáo đã được giảng viên xác nhận. Nếu được yêu cầu xem sớm, dùng phương án rút gọn ở cuối tài liệu.

Tài liệu này lập kế hoạch; không thực hiện các giao dịch nghiệp vụ, cập nhật GitHub, tạo dữ liệu hoặc mua/nâng gói dịch vụ.

## 2. Hiện trạng làm căn cứ

Các kết quả dưới đây được xác nhận gần nhất ngày **04/11**, không phải một lượt kiểm tra hosting mới ngày 06/11.

| Phần | Đã có/đã nghiệm thu | Phần còn cần chốt |
| --- | --- | --- |
| Website online | Vue/PWA và Laravel tại `https://rentbase.alwaysdata.net`; một CSDL MariaDB trên Alwaysdata, đã tách khỏi phụ thuộc Aiven | Kiểm tra nhanh lại trước buổi trình |
| Android | Người dùng đã xác nhận cài được PWA; có hướng dẫn cài/cập nhật, ảnh và PDF | Nghiệm thu thiết bị thật: camera, PDF, bàn phím, 4G khi Windows tắt |
| Vận hành | Đã xác nhận lượt sao lưu 02:15 và nhắc quá hạn 08:00 ngày 04/11; sao lưu có bản ngoài hosting, đã có ca khôi phục MariaDB trước khi mở website | Đối chiếu các lượt gần ngày trình, lấy bản sao mới |
| Giao diện | Đã sửa thanh ghim khi cuộn, chọn tháng và bộ lọc công nợ; rà các trang chính ở 320–1440px | Lỗi chỉ xuất hiện trên điện thoại thật, nếu có |
| Kiểm thử | Backend 410 kiểm thử/4.201 assertions ở đợt chuyển CSDL; frontend 134; nhóm trình duyệt 13 kịch bản và 75 điểm HTTPS trong đợt sửa giao diện | Chạy lại phần liên quan khi sửa lỗi mới; số kiểm thử không thay thế nghiệm thu thiết bị |
| Dữ liệu | Có các tình huống phòng một người/nhóm, phòng trống/bảo trì, hóa đơn thu đủ/thu một phần/quá hạn, sửa chữa có ảnh/chi phí | Chuẩn bị tình huống để thao tác trực tiếp và các tình huống mở rộng khi giảng viên hỏi |
| Mã nguồn | Bản online mới nhất đã ghi nhận: `2026-11-04T02-11-03-425Z-ff7c83` | Workspace còn nhiều thay đổi chưa commit; cần chốt mã tương ứng bản trình trên GitHub |

Giữ phạm vi đã thống nhất: không quản lý hợp đồng; quên mật khẩu tiếp tục hoãn. Phát triển cục bộ vẫn có MySQL riêng; bản trình SQLite và MariaDB online là những bộ dữ liệu riêng, không tự đồng bộ.

## 3. Thứ tự thực hiện

### Bước 1 — Nghiệm thu Android thật, sửa lỗi cản trở sử dụng

**Ưu tiên cao nhất. Ước lượng:** 45–60 phút thao tác, thêm thời gian sửa theo lỗi tìm được.

**Phân công:** người dùng thao tác trên điện thoại; phần đối chiếu dữ liệu, tái hiện và sửa mã thực hiện trên máy phát triển/hosting khi có kết quả.

Ghi tên máy, phiên bản Android/Chrome và ngày thử. Mở ứng dụng đã cài từ biểu tượng, cập nhật nếu có bản mới. Dùng `admin` trên máy tính và `user` trên điện thoại; hai tab cùng hồ sơ trình duyệt dùng chung phiên nên thử đồng thời bằng hai thiết bị/hồ sơ riêng.

| Ca | Thao tác | Đạt khi |
| --- | --- | --- |
| Mở lại ứng dụng | Đóng hẳn rồi mở từ biểu tượng, đăng nhập mới | Vào đúng vai trò, tải được dữ liệu mới |
| Thanh trên và menu | Cuộn sâu ở Tổng quan, mở menu, đổi trang | Thanh vẫn hiện, menu và nút thao tác dùng được |
| Biểu mẫu/bàn phím | Nhập nội dung báo hỏng, xoay máy, dùng nút quay lại | Ô nhập/nút gửi còn nhìn thấy; xử lý bản nhập rõ ràng |
| Ảnh | Chụp/chọn ảnh, xem trước, gửi yêu cầu mẫu rồi mở lại | Ảnh còn trên hosting, gắn đúng yêu cầu và đúng quyền |
| PDF | Tải hóa đơn và mở bằng trình xem tệp trên Android | Mở được, đúng tiếng Việt/số tiền |
| Hai vai trò | Đăng xuất rồi đổi tài khoản | Vào đúng khu vực; không còn dữ liệu tài khoản trước |
| Mạng gián đoạn | Ngắt mạng khi đang xem; thử gửi yêu cầu mẫu trong tình huống kiểm soát rồi đối chiếu | Có thông báo rõ, khi kết nối lại không tự gửi thêm giao dịch |
| Độc lập máy tính | Dùng 4G khi Windows ngắt mạng/tắt; đăng nhập mới và gửi một báo hỏng mẫu | Đọc và ghi được trên cloud, không chỉ mở khung đã lưu trên máy |

Mỗi thao tác tạo dữ liệu dùng nội dung dễ nhận diện như “NGHIỆM THU ANDROID 06/11”; ghi ID/kết quả để đối chiếu. Ca 4G khi Windows tắt do người dùng thực hiện; sau khi mở máy lại mới đối chiếu bằng công cụ trên máy.

**Đầu ra:** bảng đạt/chưa đạt và danh sách lỗi có bước tái hiện. Ưu tiên sửa lỗi đăng nhập, sai quyền, sai tiền, ghi trùng, mất nội dung/ảnh trước các lỗi khoảng cách nhỏ. Chốt bước khi các ca cần dùng để trình đạt hoặc có giới hạn cụ thể được ghi nhận.

### Bước 2 — Chốt bộ dữ liệu và kịch bản thao tác

**Ước lượng:** 1–2 giờ. Chọn bản online làm bản trình chính; dùng bản trình cục bộ hiện có để tập các thao tác thay đổi nhiều dữ liệu.

Trước khi tập hoặc bổ sung dữ liệu, lưu bản sao đúng môi trường. Ghi một thẻ cho mỗi tình huống: tài khoản/vai trò → phòng/nhóm → kỳ hóa đơn → trạng thái/số tiền trước → thao tác → kết quả/số tiền sau.

| Tình huống trình chính | Chuẩn bị và đối chiếu |
| --- | --- |
| Tổng quan | Chọn đúng kỳ có dữ liệu; đối chiếu số phòng, công nợ và biểu đồ |
| Một người và nhóm thuê | Mở phòng một người và nhóm hai người; giải thích vai trò đại diện và quyền xem hóa đơn |
| Điện nước → hóa đơn | Có phòng/kỳ đủ chỉ số, chưa phát hành; xem trước rồi phát hành đúng một lần |
| Thu tiền → công nợ | Chọn hóa đơn chưa thu đủ, ghi số nợ ban đầu; thu một phần rồi đối chiếu phiếu thu, PDF và số nợ còn lại |
| Sửa chữa | Có yêu cầu chờ với ảnh; cập nhật xử lý, xem lịch sử và minh họa khoản chi sửa chữa đã có |
| Thông báo | Chuẩn bị nội dung mẫu và phạm vi nhận; người thuê nhận đúng, chủ trọ xem trạng thái đọc |

Số liệu tham chiếu ngày 04/11: 5 phòng, 4 người thuê, 3 hóa đơn, 3 phiếu thu, 2 yêu cầu sửa chữa, 3 ảnh; công nợ 4.660.000đ. Hóa đơn online mẫu ở kỳ **09–10/2026**; chọn kỳ **10/2026** để trình biểu đồ có dữ liệu. Các số này cần đọc lại trước khi dùng vì người dùng có thể đã thao tác sau mốc nghiệm thu.

**Tình huống mở thêm khi giảng viên hỏi:** chuyển/trả phòng, đổi đại diện, đổi giá dịch vụ giữa kỳ và chia phí theo thời gian, điều chỉnh/hủy hóa đơn, sửa sai phiếu thu, sửa/hoàn tác thu chi, nhập chỉ số nhiều phòng. Các chức năng này đã có; phần còn thiếu là bản ghi minh họa cho một số tình huống. Ưu tiên chuẩn bị trong bản diễn tập, chọn tình huống thật sự cần trình.

**Đầu ra:** bảng dữ liệu trước/sau và kịch bản theo màn hình. Đạt khi tự thao tác được theo bảng, giải thích được các số tiền, biết trạng thái sau mỗi lần tập. ID/kỳ hóa đơn/tài khoản phụ của bản cục bộ có thể khác online; ghi riêng cho từng bản.

### Bước 3 — Chốt mã nguồn và cập nhật GitHub

**Ước lượng:** 60–90 phút sau khi sửa xong lỗi ảnh hưởng bản trình.

1. Rà các thay đổi chưa commit, chọn đúng mã nguồn, file khóa thư viện và tài liệu cần đưa lên.
2. Kiểm tra cấu hình mẫu; loại mật khẩu, `.env`, `.cache`, khóa SSH, bản sao CSDL/ảnh riêng, log và các tệp sinh không cần thiết khỏi phần đưa lên.
3. Chạy kiểm tra phù hợp với phần thay đổi kể từ đợt nghiệm thu; chạy kiểm tra kiểu và build frontend. Chạy lại nghiệp vụ liên quan nếu có sửa cách tính tiền/quyền/API.
4. Commit/push, đặt mốc bản trình, ghi commit và release hosting tương ứng. Chốt lại gói triển khai nếu nguồn có thay đổi sau bản online hiện có.
5. Kiểm tra GitHub có đủ mã/tài liệu cần thiết và các tệp riêng vẫn được loại trừ.

**Đầu ra:** một mốc mã trên GitHub có thể đối chiếu với bản chạy. Bước này chưa được thực hiện chỉ bởi việc lập kế hoạch.

### Bước 4 — Chuẩn bị bản sao và phương án dự phòng

**Ước lượng:** 45–60 phút. Có thể chuẩn bị phần đọc tài liệu trong lúc người dùng thử Android.

1. Trước buổi trình, lấy bản sao cloud mới gồm CSDL, ảnh và cấu hình cần khôi phục; đối chiếu mã kiểm tra, giữ thêm bản ngoài hosting ở nơi riêng.
2. Giữ bản mã trước và thông tin release để quay lại nếu cần. Quy trình khôi phục MariaDB đã có bằng chứng ngày 03/11; khi cần thử lại phải thực hiện ở nơi riêng, không ghi đè bản đang phục vụ chỉ để nghiệm thu.
3. Khởi động bản trình cục bộ có sẵn, kiểm tra đăng nhập hai vai trò, ảnh/PDF và tạo checkpoint bằng quy trình của bản trình.
4. Chuẩn bị ảnh chụp hoặc video ngắn của các luồng chính để giải thích khi mạng tại buổi báo cáo gặp sự cố.
5. Ghi rõ bản nào đang mở: online, bản trình SQLite hoặc môi trường phát triển. Gói sao lưu MariaDB cloud và checkpoint SQLite dùng quy trình khôi phục khác nhau.

**Đầu ra:** bản sao mới có kiểm tra, bản trình cục bộ mở được và thư mục bằng chứng. Bản trình cục bộ cần giữ máy chạy trong lúc sử dụng; website cloud tiếp tục hoạt động độc lập với Windows.

### Bước 5 — Chuẩn bị phần giải thích sản phẩm và tập trình

**Ước lượng:** 1–2 giờ, ít nhất hai lượt tập.

| Thời lượng đề xuất | Nội dung |
| --- | --- |
| 1 phút | Bài toán, hai vai trò và phạm vi đề tài |
| 1 phút | Đăng nhập, Tổng quan và công nợ |
| 2 phút | Phòng một người/nhóm, người thuê và đại diện |
| 2 phút | Chỉ số điện nước, xem trước/lập hóa đơn |
| 2 phút | Thu tiền, số dư sau thu và PDF |
| 2 phút | Báo hỏng có ảnh, xử lý, lịch sử và chi phí |
| 1 phút | Thông báo chung và giao diện người thuê trên Android |
| 1 phút | Kiến trúc, kiểm thử, giới hạn và phần dành cho góp ý |

Chuẩn bị ghi chú ngắn để trả lời: Vue 3/TypeScript ở frontend; Laravel 12 ở backend; MariaDB online; PWA trên Android dùng cùng API và quyền; phân biệt tháng hóa đơn/ngày thực thu; một người/đại diện nhóm; chống ghi trùng và sửa sai có lịch sử; cách lưu ảnh, sao lưu và hoạt động khi mất mạng.

Mở sẵn hai thiết bị/hồ sơ cho hai vai trò. Tập từ một mốc dữ liệu đã biết; giữa các lượt, dùng quy trình khôi phục của bản diễn tập hoặc cập nhật bảng số tiền theo thao tác vừa thực hiện.

**Đạt khi:** trình trọn luồng trong khoảng 10–12 phút, tự giải thích được số trước/sau và đổi sang bản dự phòng được. Ghi chú, ảnh và kịch bản phục vụ trình sản phẩm; file Word khóa luận thực hiện sau góp ý theo yêu cầu đã chốt.

### Bước 6 — Kiểm tra ngay trước buổi xem sản phẩm

**Ước lượng:** 20–30 phút.

- Đăng nhập mới trên website và Android, kiểm tra phiên bản cần trình.
- Đọc một ảnh, tải một PDF, xem công nợ và kỳ hóa đơn đã chọn.
- Xem kết quả sao lưu/nhắc gần nhất và lỗi ứng dụng; xác nhận dữ liệu cần dùng chưa bị các lượt tập thay đổi ngoài dự kiến.
- Kiểm tra nguồn điện, Wi‑Fi/4G, hai tài khoản và bản trình dự phòng.
- Giữ bản đã chốt; dành khoảng dự phòng để xử lý lỗi phát hiện sát giờ.

**Điều kiện đưa ra trình:** không còn lỗi chặn đăng nhập, sai quyền, sai số dư/ghi trùng tiền hoặc mất ảnh ở các luồng trình; thiết bị Android đọc/ghi được bằng mạng riêng; dữ liệu và bản sao đã chuẩn bị; mã nguồn có mốc lưu rõ ràng.

### Bước 7 — Sau khi giảng viên đánh giá

1. Ghi góp ý theo màn hình, bước thao tác, kết quả mong muốn và mức cần thiết.
2. Ưu tiên lỗi bắt buộc sửa, sau đó chức năng giảng viên yêu cầu; với mỗi mục, chốt quy tắc → mã/API/giao diện → kiểm tra → cập nhật bản chạy.
3. Chọn thêm một nâng cấp nếu còn thời gian và phù hợp đề tài; đánh giá riêng thời gian, dữ liệu và ảnh hưởng bản đã nghiệm thu trước khi làm.
4. Khi sản phẩm và phạm vi đã chốt, tổng hợp sơ đồ/use case/CSDL, quy tắc nghiệp vụ và bằng chứng kiểm thử để làm Word theo mẫu trường.

## 4. Lịch đề xuất

| Ngày | Công việc ưu tiên | Kết quả cuối ngày |
| --- | --- | --- |
| **06/11** | Nghiệm thu Android; sửa lỗi cản trở; chọn dữ liệu/kịch bản | Biết rõ ca thiết bị thật đạt/chưa đạt, có danh sách tình huống trình |
| **07/11** | Hoàn tất dữ liệu diễn tập, chốt/push mã, mở bản dự phòng; tập lần một | Bản trình có mốc mã, bản sao và kịch bản chạy được |
| **08/11** | Tập lần hai, kiểm tra kết quả tiền/quyền, lấy bản sao mới và sửa lỗi còn lại | Sẵn sàng trình, có phương án khi mạng lỗi |
| **Từ 09/11 hoặc ngày giảng viên hẹn** | Kiểm tra nhanh trước giờ, trình sản phẩm, ghi góp ý | Danh sách điều chỉnh được thống nhất |
| **Sau buổi xem** | Sửa theo góp ý → chốt sản phẩm → làm Word | Nội dung báo cáo phản ánh sản phẩm đã được đánh giá |

Các khoảng ước lượng là thời gian làm việc tập trung, không phải cam kết khi chưa biết lỗi phát sinh. Chỉ chọn bổ sung chức năng sau khi có kết quả nghiệm thu và góp ý; các hạng mục đã xong như PDF, lịch sử sửa chữa, thông báo chung, PWA và sao lưu được dùng để trình, không lập lại thành chức năng mới.

## 5. Nếu giảng viên yêu cầu xem ngay

Ưu tiên kiểm tra đăng nhập Android và ảnh/PDF trong 10–15 phút, giữ một bản sao mới, chọn kỳ 10/2026 và mở sẵn hai vai trò. Trình luồng chính trên bản online ổn định hiện có; dùng tình huống mở rộng khi được hỏi. Ghi rõ các kiểm tra thiết bị còn thiếu, tiếp tục chốt mã/dữ liệu sau buổi xem theo lịch thực tế.

## 6. Tài liệu liên quan

- [Nghiệm thu giao diện mới 04/11](responsive-ui-acceptance-2026-11-04.md).
- [Nghiệm thu vận hành sau một đêm 04/11](overnight-operation-acceptance-2026-11-04.md).
- [Chuyển MariaDB và khôi phục đã kiểm tra](mariadb-migration-acceptance-2026-11-03.md).
- [Dữ liệu/tình huống đã và chưa có mẫu](scheduler-and-demo-audit-2026-11-02.md).
- [Hướng dẫn bản trình cục bộ, checkpoint và khôi phục](../frontend/docs/presentation-guide.md).
- [Hướng dẫn Android](../frontend/docs/mobile-android-guide.md).
