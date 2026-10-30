# Kế hoạch chuẩn bị sản phẩm để giảng viên đánh giá — đầu tháng 11/2026

**Đã chuẩn bị bản trình riêng:** `npm run presentation` mở cổng 5175 với CSDL/ảnh/phiên riêng; có dữ liệu một/nhiều thành viên, thu đủ/một phần/quá hạn, kỳ sẵn sàng lập hóa đơn và sự cố có ảnh/lịch sử/chi phí. Có lệnh tạo mốc mã nguồn/CSDL/ảnh và kiểm tra khôi phục, hướng dẫn trả dữ liệu về mốc sau tập demo. [Hướng dẫn thực hành và kịch bản 10–12 phút](../frontend/docs/presentation-guide.md). Khâu người dùng tập trình, giảng viên xem và thử trực tiếp điện thoại/máy khác tiếp tục thực hiện trước buổi báo cáo.

[Kết quả nghiệm thu thông tin nhà trọ, PDF và nhập nhiều phòng ngày 30/10](../frontend/docs/property-pdf-bulk-acceptance.md).

**Trạng thái sản phẩm ngày 30/10/2026:** theo yêu cầu bổ sung, đã hoàn thành thông tin nhà trọ, PDF hóa đơn/phiếu thu cho hai vai trò và nhập chỉ số nhiều phòng. Hồ sơ chống ghi đè theo phiên bản; PDF tiếng Việt thể hiện đúng hủy/thay thế, chỉ hiện thông tin chuyển khoản khi hóa đơn chưa thu/thu một phần. Bảng chỉ số xác nhận tối đa 100 dòng theo từng khoảng giá, có kết quả/giao dịch/chống trùng riêng từng dòng, phục hồi thủ công sau mất phản hồi và chặn bỏ qua khoảng giá liền trước. [Tự thử thông tin/PDF](../frontend/docs/property-pdf-self-test.md), [tự thử nhập nhiều phòng](../frontend/docs/bulk-meters-self-test.md).

Kết quả đã xác nhận đến 30/10: backend **399 ca/4.145 assertions**, frontend **131 ca**, bản đóng gói đạt; Chrome cuối **14/14** (4,1 phút), PDF sau chỉnh font cuối **10/10** (97 assertions). MySQL **28 bảng nghiệp vụ/100 quan hệ/3 view**; **53 kiểm tra đồng thời/6 tình huống** trên CSDL riêng đạt, CSDL tạm đã dọn, không ghi nghiệp vụ thử vào dữ liệu thật. Năm PDF giả gồm **31 trang** đã được kiểm tra hình ảnh, không có chữ tràn trang hoặc ký tự thay thế. Các mốc cũ bên dưới được giữ theo thời điểm lập; Excel, ảnh chỉ số, hợp đồng/cọc/quyết toán, hoàn tiền thực tế và hosting chưa triển khai. Việc chuẩn bị bản trình/dữ liệu, tự thử và tập demo vẫn cần hoàn tất theo lịch thực tế.

**Cập nhật sản phẩm 27/10/2026:** theo lựa chọn tiếp theo của người dùng, đã bổ sung [chi phí sửa chữa](../frontend/docs/repair-expenses-self-test.md) và [sửa sai phiếu thu](../frontend/docs/payment-corrections-self-test.md). Tại mốc 27/10, PDF/Excel và hoàn tiền thực tế còn là phần chọn riêng; trạng thái PDF mới nhất đã cập nhật ở trên. Những mốc/kết quả ban đầu bên dưới được giữ theo thời điểm lập kế hoạch.

Lập ngày 25/10/2026. Người dùng dự kiến trình sản phẩm trong tuần đầu hoặc đầu tuần thứ hai tháng 11; chưa có ngày cụ thể. Giảng viên yêu cầu xem sản phẩm và đánh giá trước, sau đó mới cân nhắc chức năng bổ sung, cuối cùng mới làm báo cáo Word.

## Mục tiêu và thứ tự

**Sản phẩm ổn định → giảng viên xem và góp ý → chốt phạm vi bổ sung → hoàn thiện/kiểm tra lại → làm Word.**

Mốc nội bộ đề xuất: sẵn sàng trước khi kết thúc **31/10**, dự phòng **01/11**. Khi lịch trình chính thức được chốt, lấy ngày đó làm mốc buổi xem sản phẩm; không tự suy ra yêu cầu nộp Word/slide của trường.

Phạm vi ban đầu ngày 25/10 tập trung sửa lỗi, chuẩn bị môi trường/dữ liệu và tập demo. Sau đó người dùng chọn bổ sung chi phí sửa chữa, sửa sai phiếu thu, thông tin nhà trọ/PDF và nhập nhiều phòng; các phần này nay đã có. Trước buổi xem tiếp tục chốt bản chạy và tự thử. Excel, hợp đồng/cọc, QR, chat, thanh toán trực tuyến và quên mật khẩu vẫn là phần chọn riêng sau đánh giá.

## Điểm xuất phát tại thời điểm lập ngày 25/10

- Hai vai trò chủ trọ/người thuê, đăng nhập/đăng xuất, tài khoản và phân quyền.
- CT1–CT8: phòng/dịch vụ, người thuê/quá trình thuê, điện nước/hóa đơn/thu tiền, công nợ, thu chi và lịch sử, sửa chữa/ảnh, tổng quan/biểu đồ.
- NT1–NT4: phòng/hồ sơ/lịch sử cư trú, hóa đơn/biên nhận theo quyền, báo hỏng/ảnh và thông báo.
- Thông báo chung, trạng thái đã đọc; điều chỉnh/hủy hóa đơn, hạn thanh toán; lịch sử từng lần xử lý sửa chữa.
- Ghi nhận gần nhất: 336 kiểm thử backend, 119 frontend, 25 kịch bản Chrome liên quan lịch sử/sửa chữa/người thuê/thông báo đạt; bản đóng gói và quy chuẩn PHP đạt. MySQL có 23 bảng nghiệp vụ, 81 quan hệ, 3 view.
- Các con số trên là kết quả những lượt đã chạy, không phải chứng nhận toàn bộ sản phẩm không còn lỗi. Điện thoại thật, cài mới trên máy khác và hosting vẫn cần đối chiếu theo nhu cầu buổi đánh giá.

Tại lần lập kế hoạch ngày 25/10, việc tạo dữ liệu demo mới, cài bản sao MySQL, quay video và tổ chức buổi đánh giá chưa được thực hiện. Bản trình SQLite riêng và ảnh dự phòng đã được chuẩn bị sau đó như cập nhật ở đầu tài liệu; việc trình trực tiếp và thử máy khác vẫn cần thực hiện.

## Lịch thực hiện đề xuất

| Ngày/mốc | Công việc | Đầu ra phải có |
| --- | --- | --- |
| **25–26/10** | Chốt bản sản phẩm sẽ trình; lập danh sách chức năng đã có/chưa có; chuẩn bị dữ liệu giả nhất quán | Một bản sản phẩm xác định rõ phạm vi, danh sách tài khoản/phòng/đợt thuê/hóa đơn/sự cố dùng để demo |
| **27/10** | Người dùng tự thao tác hai vai trò theo kịch bản; ghi lỗi và điểm khó hiểu | Danh sách lỗi có màn hình, bước tái hiện, kết quả mong đợi và mức ảnh hưởng; chọn lỗi cần sửa trước |
| **28/10** | Sửa lỗi đã ghi nhận; kiểm tra lại các luồng có thay đổi; kiểm tra trên máy/trình duyệt sẽ dùng để trình | Không còn lỗi chặn demo, sai tiền, sai quyền xem hoặc mất dữ liệu trong các luồng đã chọn |
| **28–29/10** | Chuẩn bị bản chạy độc lập, ưu tiên CSDL MySQL demo riêng; kiểm tra khởi động/dừng, dữ liệu và ảnh | Bản chạy đúng hướng dẫn trên máy trình bày; MySQL demo tách dữ liệu thật; ảnh mở được sau khởi động lại; bản dự phòng đã xác định |
| **30/10** | Tập trình sản phẩm 10–12 phút, đổi vai trò đúng cách; chụp màn hình/quay video dự phòng | Thao tác đủ kịch bản không phải sửa mã giữa chừng; biết giải thích các con số và quy tắc chính |
| **31/10** | Chốt mốc sản phẩm; sao lưu mã nguồn, CSDL và ảnh; kiểm tra từ trạng thái máy chủ đã tắt | Bản dùng để trình và bản sao đi cùng dữ liệu; mở lại được bằng hướng dẫn đã chốt |
| **01/11** | Ngày dự phòng sửa lỗi chặn, rà lại thiết bị/mạng/màn hình và đường dẫn | Bản trình sẵn sàng; giữ nguyên phạm vi, chỉ xử lý sự cố cần thiết |
| **Tuần đầu tháng 11 hoặc đầu tuần 2** | Giảng viên xem, thử sản phẩm và đánh giá | Danh sách góp ý được ghi rõ: lỗi bắt buộc sửa, nghiệp vụ cần điều chỉnh, chức năng gợi ý thêm |
| **Nếu lịch vào khoảng 09–10/11** | Dùng những ngày thêm để tập thao tác, xử lý góp ý đã có và giữ bản chạy ổn định | Không tự phát sinh chức năng mới chỉ vì còn ngày; kiểm tra lại bản sẽ trình trước buổi gặp |
| **Sau khi giảng viên xem** | Xếp ưu tiên, chốt việc bổ sung và kiểm tra lại sau sửa | Phạm vi tiếp theo rõ ràng, chức năng phát triển thêm đúng nhu cầu đã thống nhất |
| **Sau khi chốt/hoàn thiện sản phẩm theo đánh giá** | Bắt đầu file Word theo mẫu được giảng viên/trường yêu cầu | Nội dung và ảnh chụp khớp sản phẩm thực tế; sơ đồ và kết quả kiểm thử được cập nhật đúng bản |

Nếu buổi xem được chốt sớm hơn dự kiến, giữ ba việc cốt lõi: bản chạy ổn định, dữ liệu demo đúng và tập luồng hai vai trò. Dời những việc không chặn buổi xem sang sau.

## Bước 1 — Chốt phạm vi bản sản phẩm

1. Lưu một danh sách ngắn các chức năng sẽ cho giảng viên thử, dựa trên mục Điểm xuất phát.
2. Đánh dấu rõ các phần chưa có như Excel, ảnh đồng hồ, hợp đồng/cọc/quyết toán và hoàn tiền thực tế; PDF và nhập nhiều phòng đã thuộc bản sản phẩm hiện tại.
3. Xác nhận hai vai trò và quy tắc quan trọng: người thuê chỉ xem dữ liệu được cấp quyền; báo hỏng mới do đại diện đủ điều kiện gửi; thành viên thường vẫn nhận thông báo chung khi đủ điều kiện.
4. Giữ màu sắc/kiểu chữ và cách điều hướng hiện tại; chỉ sửa điểm khó dùng thực sự tìm được.

Tiêu chí xong: có thể nói ngắn gọn sản phẩm giải quyết vấn đề gì, ai sử dụng, chức năng nào đang chạy và phần nào đang chờ đánh giá.

## Bước 2 — Chuẩn bị dữ liệu giả để trình

- Có ít nhất ba tình huống: **phòng trống**, **phòng một người**, **phòng nhiều người có đại diện/thành viên**.
- Phòng đang ở có người thuê, nhóm/đợt thuê, dịch vụ và đơn giá theo thời gian nhất quán.
- Chuẩn bị hóa đơn chưa thu, thu một phần và đã thu đủ; có trường hợp quá hạn hợp lệ nếu trình công nợ quá hạn. Không gán lùi hạn để tạo trạng thái giả trong dữ liệu thật.
- Chốt kỳ demo đã kết thúc và có đủ chỉ số. Trong tháng 10 có thể dùng kỳ tháng 9; sau 01/11 có thể dùng kỳ tháng 10 nếu dữ liệu đủ điều kiện. Không dựa vào việc tự phát hành hóa đơn cả tháng đang diễn ra.
- Tính trước một hóa đơn mẫu: tiền phòng, lượng điện/nước, đơn giá, dịch vụ theo thời gian, tổng, lần thu và dư nợ. Các số phải khớp ở hóa đơn, công nợ và tổng quan/sổ tiền theo đúng phạm vi.
- Có yêu cầu sửa chữa kèm ảnh giả, các lần cập nhật để xem lịch sử và một yêu cầu đang mở để giảng viên có thể thử thao tác.
- Có thông báo chung cùng người nhận hợp lệ. Khi cần thể hiện trạng thái đọc, thao tác bằng đúng tài khoản nhận.
- Chừa một phòng/người/yêu cầu để thử thêm, không dùng hết dữ liệu còn mở trong buổi tập. Bản demo giữ thay đổi sau khi khởi động lại; muốn lặp lại kịch bản phải dùng bản sao dữ liệu đã chuẩn bị.

Tiêu chí xong: mỗi màn hình định mở đều có dữ liệu hợp lý và có trường hợp rỗng; không dùng thông tin/ảnh người thuê thật để minh họa.

## Bước 3 — Tự thử và sửa lỗi có chọn lọc

Người dùng thao tác bằng trình duyệt của mình. Mỗi lỗi ghi theo bốn ý: mở trang nào → làm gì → thực tế xảy ra gì → mong muốn gì. Ảnh chụp chỉ cần khi giúp tái hiện.

| Mức | Ví dụ | Cách xử lý trước buổi xem |
| --- | --- | --- |
| Chặn hoặc sai dữ liệu | Không đăng nhập được, sai tổng/dư nợ, ghi trùng khoản thu, xem được dữ liệu tài khoản khác, ảnh mất | Sửa trước, kiểm tra lại ca tái hiện và luồng liên quan |
| Khó thao tác | Nút bị che, tràn ngang, bộ lọc không rõ, báo lỗi không dễ hiểu | Sửa những điểm có trong kịch bản, đối chiếu máy tính/điện thoại |
| Góp ý hình thức hoặc mở rộng | Đổi bố cục lớn, thêm biểu đồ/chức năng mới | Ghi lại và chờ giảng viên đánh giá, không để ảnh hưởng việc chốt bản |

Ưu tiên kiểm tra số tiền, quyền xem, trạng thái và khả năng khởi động lại. Chạy lại kiểm thử phù hợp với thay đổi; nếu sửa tính tiền, quyền hoặc cấu trúc dữ liệu thì kiểm tra rộng hơn nhóm giao diện.

## Bước 4 — Chốt cách chạy cho giảng viên xem

**Phương án ưu tiên:** chạy trực tiếp trên máy trình bày, dùng Laravel + Vue và **MySQL demo riêng với dữ liệu giả** để đối chiếu đúng công nghệ của dự án. Đây là công việc chuẩn bị môi trường, không phải thêm nghiệp vụ mới. Cần kiểm tra bảng/view và kết nối đều trỏ vào CSDL demo, không đọc nhầm dữ liệu thật.

**Phương án dự phòng:** bản `npm run demo` hiện có sử dụng Laravel thật với SQLite riêng. Khi dùng phương án này phải nói rõ đây là dữ liệu/môi trường demo, còn backend của dự án đã có cấu hình và kiểm tra MySQL. Hai phương án không được gọi là cùng một CSDL.

- Dùng Chrome cho chủ trọ, Edge hoặc hồ sơ/cửa sổ ẩn danh cho người thuê; các tab cùng hồ sơ trình duyệt dùng chung phiên.
- Kiểm tra tắt rồi bật lại được, biết cửa sổ nào đang giữ cổng, không mở nhiều máy chủ cùng cổng. Giữ hướng dẫn chạy ngay cạnh bản nguồn.
- Sao lưu cùng nhau mã nguồn, bản dữ liệu demo và ảnh riêng tư; đối chiếu mở được ảnh sau phục hồi bản sao trên môi trường riêng. Giữ `.env` và bản sao dữ liệu ngoài kho công khai.
- Kiểm tra font/ảnh/đường dẫn trên đúng máy trình bày. Nếu có điều kiện, thử bản cài ở máy khác hoặc môi trường mới và ghi lại giới hạn khi chưa thử được.
- Chỉ đưa lên hosting nếu cần để giảng viên truy cập từ xa hoặc người dùng chọn cách trình này. Khi đó cần phục vụ cả frontend đã đóng gói và Laravel/API, giữ ảnh bền vững, kiểm tra HTTPS/phiên và quyền ghi thư mục theo [Vite](https://vite.dev/guide/build.html) và [Laravel 12](https://github.com/laravel/docs/blob/12.x/deployment.md). Đây là nhánh triển khai có kế hoạch riêng, chưa thực hiện trong lần lập kế hoạch.

Tiêu chí xong: có bản chạy chính, bản dự phòng, tài khoản dùng thử và hướng dẫn khởi động/dừng rõ ràng; không cần sửa mã ngay trước mặt giảng viên.

## Bước 5 — Kịch bản xem sản phẩm 10–12 phút

| Thời lượng gợi ý | Thao tác | Điều cần làm rõ |
| --- | --- | --- |
| 0–1 phút | Đăng nhập chủ trọ, mở tổng quan | Hai vai trò; tổng quan và biểu đồ lấy dữ liệu theo kỳ/phạm vi |
| 1–3 phút | Mở phòng/người thuê/đợt thuê đã chuẩn bị | Phòng một hoặc nhiều người, nhóm thuê/đại diện, dịch vụ và giá đã chốt |
| 3–6 phút | Mở chỉ số/kỳ đủ điều kiện, xem trước hóa đơn, thu một phần; chuyển sang người thuê để đối chiếu | Cách tính tiền, lần thu và số còn nợ; quyền xem của người thuê |
| 6–9 phút | Người thuê gửi một báo hỏng kèm ảnh; chủ trọ cập nhật; người thuê xem lại | Ảnh riêng tư, thay đổi trạng thái/ghi chú, lịch sử xử lý và thông báo |
| 9–10 phút | Chủ trọ xem trước/gửi thông báo chung, người thuê mở đọc | Chọn đúng người nhận; trạng thái đã đọc |
| 10–12 phút | Để giảng viên thử một thao tác và ghi góp ý | Phân biệt phần đã có và phần đề xuất thêm sau buổi đánh giá |

Không cần đi hết mọi màn hình trong một lượt. Thu chi/hoàn tác, điều chỉnh/hủy hóa đơn, chuyển/trả phòng và xử lý sai quyền là các luồng bổ sung khi được hỏi. Tránh nhập lại nhiều hồ sơ ngay trong buổi xem; sử dụng dữ liệu đã chuẩn bị và thực hiện vài thao tác ghi có kết quả dễ đối chiếu.

Chuẩn bị cách giải thích ngắn: Vue gọi Laravel qua API, phiên đăng nhập/CSRF; quan hệ phòng–nhóm–đợt thuê–thành viên; tháng hóa đơn khác ngày thực thu; ảnh được lưu riêng và kiểm tra quyền. Chưa cần chuyển các ghi chú thao tác này thành Word hoặc tự làm slide khi chưa được yêu cầu.

## Bước 6 — Chốt bản và dự phòng

- Giữ một mốc mã nguồn và bộ dữ liệu demo đã thử; ghi rõ ngày/bản để tránh mở nhầm bản cũ.
- Tập toàn bộ kịch bản ít nhất hai lượt; mỗi lượt bắt đầu từ trạng thái khởi động đã biết. Các thao tác ghi phải có dữ liệu đủ để thử lại bằng bản sao, không dựa vào việc khởi động demo sẽ tự xóa dữ liệu.
- Chuẩn bị một video ngắn và vài ảnh màn hình dự phòng; các tệp này hỗ trợ khi máy/mạng gặp sự cố, không thay thế sản phẩm đang chạy để giảng viên thử.
- Trước buổi xem: kiểm tra đúng bản, đúng CSDL, đúng tài khoản/vai trò, ảnh mở được, máy có nguồn và trình duyệt sẵn sàng.

Đây là danh sách chuẩn bị, không phải yêu cầu tự triển khai hosting hoặc gửi dữ liệu/tài khoản cho người khác ngay bây giờ.

## Sau buổi giảng viên đánh giá

1. Ghi góp ý thành ba nhóm: lỗi bắt buộc sửa; nghiệp vụ cần điều chỉnh; chức năng gợi ý thêm.
2. Ưu tiên hai nhóm đầu, xác định kết quả mong đợi và kiểm tra lại sau sửa.
3. Chọn chức năng bổ sung theo góp ý và thời gian thực tế. Các ứng viên còn lại gồm Excel, ảnh đồng hồ và hợp đồng/cọc/quyết toán; PDF và liên kết chi phí sửa chữa đã được bổ sung, không lập lại thành việc chưa làm.
4. Chốt lại bản sản phẩm để dùng cho báo cáo. Sau khi các thay đổi đã thống nhất được hoàn thiện, mới bắt đầu Word theo mẫu/yêu cầu cụ thể của giảng viên/trường.
5. Khi làm Word, dùng đúng phạm vi/chức năng, sơ đồ CSDL và ảnh của bản đã chốt; trình bày trung thực việc đã làm, kiểm thử và giới hạn còn lại.

## Phân chia việc chuẩn bị

| Phần | Người dùng | Codex khi được yêu cầu thực hiện bước tương ứng |
| --- | --- | --- |
| Phạm vi/ngày xem | Chốt lịch và cách giảng viên muốn xem | Cập nhật kế hoạch theo thông tin mới |
| Tự thử | Thao tác hai vai trò, ghi điểm chưa đúng mong muốn | Tái hiện, sửa lỗi và kiểm tra lại |
| Bản chạy/dữ liệu | Chọn máy trình bày và tình huống muốn giới thiệu | Chuẩn bị dữ liệu giả, bản chạy tách biệt, hướng dẫn và kiểm tra khởi động |
| Demo | Tập trình bày và tự giải thích quy tắc | Soạn kịch bản thao tác, hỗ trợ rà soát các con số/tình huống |
| Đánh giá | Cho giảng viên thử và ghi nhận góp ý | Phân loại góp ý, lập đợt sửa/bổ sung tiếp theo |
| Word | Lấy mẫu và yêu cầu sau khi đến giai đoạn này | Hỗ trợ soạn theo sản phẩm đã chốt và mẫu được cung cấp |

Việc nên làm ngay tiếp theo: **chuẩn bị bộ dữ liệu và bản sản phẩm dùng để trình, rồi người dùng tự thử theo kịch bản**, tiếp tục kiểm tra các bổ sung đã có, giữ Excel và Word cho phạm vi/giai đoạn đã chốt.
