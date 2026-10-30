# Kế hoạch bổ sung và nâng cấp chức năng — 27/10/2026

**Cập nhật sản phẩm 27/10/2026:** theo lựa chọn tiếp theo của người dùng, đã bổ sung [chi phí sửa chữa](../frontend/docs/repair-expenses-self-test.md) và [sửa sai phiếu thu](../frontend/docs/payment-corrections-self-test.md). Các đề xuất chưa làm như PDF/Excel và hoàn tiền thực tế vẫn là phần chọn riêng. Những mốc/kết quả ban đầu bên dưới được giữ theo thời điểm lập kế hoạch.

Đề xuất dựa trên mã nguồn hiện tại. Lần này chỉ lập kế hoạch, chưa triển khai chức năng mới. Bản nền đã lên GitHub tại commit `fca6bff`; lượt kiểm tra ngày 27/10 đạt 336 kiểm thử backend, 119 frontend và đóng gói giao diện thành công.

## 1. Mục tiêu và mốc thực hiện

Giữ thứ tự đã thống nhất: **sản phẩm ổn định → giảng viên xem trong tuần đầu hoặc đầu tuần 2 tháng 11 → chọn phần bổ sung → phát triển và nghiệm thu → Word**.

Có thể chốt danh sách nâng cấp ngay để trao đổi với giảng viên. Các đợt phát triển dưới đây tính từ lúc chọn phạm vi, không phải cam kết hoàn thành toàn bộ trước buổi xem. [Kế hoạch chuẩn bị sản phẩm và kịch bản trình](initial-report-plan-2026-11.md) vẫn là kế hoạch trước mắt.

Từ 27–31/10: tự thử hai vai trò, sửa lỗi có thể tái hiện, chuẩn bị dữ liệu giả/môi trường MySQL demo riêng, kiểm tra ảnh sau khởi động lại, tập demo và sao lưu. Ngày 01/11 dự phòng. Ngày giảng viên xem chưa được chốt chính xác.

## 2. Những phần đã có, không cần lập lại thành chức năng mới

- CT1–CT8 chủ trọ và NT1–NT4 người thuê; đăng nhập, đăng xuất và phân quyền.
- Phòng, dịch vụ, hồ sơ/tài khoản người thuê, nhóm/đợt thuê, nhận/chuyển/trả phòng và lịch sử cư trú.
- Điện nước, xem trước/phát hành hóa đơn, thu từng phần/đủ và biên nhận trên giao diện; chống gửi trùng thu tiền và thu khác/chi phí.
- Điều chỉnh, hủy/khôi phục hóa đơn có lịch sử; hạn thanh toán, công nợ quá hạn và tổng quan/biểu đồ.
- Thu chi có nhật ký/hoàn tác; báo hỏng có ảnh riêng tư và lịch sử xử lý.
- Thông báo chung, người nhận/đã đọc và nhắc hóa đơn trong ứng dụng. Lịch nhắc 08:00 đã có trong mã; máy chủ vẫn cần chạy bộ lập lịch để tự gửi.

Backend hiện dùng Laravel/PHP/MySQL; frontend Vue/TypeScript/Vite. Các đề xuất này mở rộng trên nền hiện tại.

## 3. Danh sách ưu tiên

| Thứ tự | Phần cần làm | Giá trị với sản phẩm | Quy mô tương đối / thời điểm |
| --- | --- | --- | --- |
| 0 | Chốt bản trình, dữ liệu demo và sao lưu/khôi phục | Giảng viên mở và thử được, có tình huống đối chiếu tiền và hai vai trò | Làm trước buổi xem; hosting chỉ khi cần truy cập từ xa |
| 1 | PDF hóa đơn và phiếu thu | Chủ trọ có chứng từ để in/gửi; người thuê tải bản thuộc quyền xem | Vừa; lựa chọn đầu tiên sau đánh giá |
| 2 | Excel hóa đơn, công nợ và sổ thu chi | Chủ trọ tổng hợp theo bộ lọc, đối chiếu được số liệu | Vừa; làm sau PDF nếu chọn cả hai |
| 3 | Liên kết chi phí sửa chữa với khoản chi | Theo dõi sửa gì, tốn bao nhiêu, tiền được tính một lần | Vừa; tăng chiều sâu cho nghiệp vụ đã có |
| 4 | Sửa sai phiếu thu và hoàn tiền có đối chiếu | Xử lý trường hợp nhập nhầm/hoàn tiền mà vẫn giữ lịch sử | Lớn; đưa lên trước PDF/Excel nếu bắt đầu dùng tiền thật thường xuyên |
| 5 | Ảnh đồng hồ và bảng nhập chỉ số nhiều phòng | Có căn cứ chỉ số, giảm thao tác cuối tháng | Vừa đến lớn; ảnh trước, nhập nhiều phòng sau |
| 6 | Hợp đồng, tiền cọc và quyết toán trả phòng | Hoàn thiện vòng đời thuê và bàn giao | Rất lớn; tách thành nhiều đợt, chỉ chọn khi thuộc phạm vi đề tài |
| 7 | QR chuyển khoản, hỗ trợ sử dụng và tiện ích nhỏ | Thuận tiện cho người dùng sau khi các luồng chính ổn định | Tùy chọn; QR không tự xác nhận đã nhận tiền |

**Gói nên chọn đầu tiên: PDF → Excel → liên kết chi phí sửa chữa.** Nếu chỉ chọn một chức năng, chọn PDF. Nếu giảng viên yêu cầu chiều sâu nghiệp vụ hơn chứng từ, ưu tiên liên kết chi phí sửa chữa sau PDF và dời Excel lại.

## 4. Đợt A — PDF hóa đơn và phiếu thu

### Chủ trọ và người thuê làm được gì

- Chủ trọ bấm **Tải PDF / In** ở chi tiết hóa đơn; ở biên nhận có **Tải phiếu thu**.
- Người thuê tải hóa đơn và phiếu thu khi đang có quyền xem hóa đơn đó.
- Phiếu thu gắn với một lần thu cụ thể; hóa đơn thể hiện tổng, điều chỉnh, đã thu và còn nợ tại thời điểm xuất.

### Các bước thực hiện

1. Chốt mẫu gồm tên nhà trọ/thông tin liên hệ cần thiết, phòng/nhóm, kỳ, dòng tiền, hạn, trạng thái và thời điểm xuất. Không đưa giấy tờ tùy thân vào mẫu mặc định.
2. Backend tạo tệp từ dữ liệu và quy tắc quyền hiện có; quyết định thư viện/font khi triển khai.
3. Frontend thêm nút tải/in vào màn hình đang có, giữ bố cục tối giản và trạng thái đang tải/lỗi rõ ràng.
4. Kiểm tra tiếng Việt, nội dung dài/nhiều trang, thu một phần, điều chỉnh/hủy và quyền truy cập bằng URL trực tiếp.

**Tiêu chí xong:** PDF mở/in được; số tiền khớp API/giao diện tại thời điểm xuất; phiếu thu không nhầm lần thu; tài khoản khác không tải được. Chứng từ là bản quản lý nội bộ của ứng dụng.

## 5. Đợt B — Excel báo cáo

Phạm vi ban đầu dành cho chủ trọ, gồm ba loại: **hóa đơn theo kỳ, công nợ tại thời điểm xuất, sổ thu chi theo ngày thực thu/chi**.

1. Chốt cột và bộ lọc; mặc định không xuất dữ liệu giấy tờ cá nhân.
2. Dùng chung điều kiện lọc/tính tổng với màn hình, xuất đủ các trang dữ liệu phù hợp.
3. Ghi rõ bộ lọc và thời điểm xuất; tiền là ô số, mã phòng/mã phiếu giữ đúng dạng văn bản.
4. Thêm nút **Xuất Excel**, hướng dẫn khi không có dữ liệu hoặc khối lượng lớn.
5. Kiểm tra tổng trên tệp, tiếng Việt, ngày tháng và nội dung người dùng nhập không trở thành công thức ngoài ý muốn.

**Tiêu chí xong:** ba báo cáo khớp màn hình theo cùng phạm vi/thời điểm; doanh thu hóa đơn, tiền thực thu và công nợ được phân biệt; chỉ chủ trọ xuất được.

## 6. Đợt C — Chi phí sửa chữa

Phạm vi đầu tiên đề xuất: một yêu cầu có thể liên kết nhiều khoản chi; mỗi khoản chi chỉ gắn với một yêu cầu. Chủ trọ tạo khoản chi qua cơ chế thu chi hiện có hoặc chọn khoản chi đã ghi, sau đó liên kết với yêu cầu.

1. Chốt thông tin liên kết, lý do thay đổi, người thực hiện và quyền xem.
2. Bổ sung liên kết CSDL/API; giữ dữ liệu yêu cầu, ảnh và thu chi cũ.
3. Trong chi tiết sửa chữa của chủ trọ thêm **Chi phí đã ghi nhận**, danh sách khoản chi và tổng còn hiệu lực.
4. Trong sổ thu chi có liên kết ngược tới yêu cầu; ghi nhận lịch sử khi gắn/bỏ gắn. Hoàn tất sửa chữa không tự tạo chi phí.
5. Khi khoản chi sửa/xóa/khôi phục, tổng sửa chữa cập nhật từ cùng khoản gốc; không chép tiền thành khoản chi thứ hai.
6. Người thuê tiếp tục xem tiến độ/ảnh/lịch sử được phép; phần sổ tiền và chi phí nội bộ mặc định chỉ dành cho chủ trọ.

**Tiêu chí xong:** liên kết được khoản có sẵn và khoản mới; gửi lại không tạo trùng; sửa/xóa/hoàn tác khoản chi cập nhật đúng cả hai màn hình; không lộ sổ tiền cho người thuê.

## 7. Các đợt lớn cần chốt nghiệp vụ riêng

### Sửa sai phiếu thu và hoàn tiền

Hai trường hợp khác nhau: **ghi nhầm dữ liệu** và **thực tế trả lại tiền**. Hiện điều chỉnh hóa đơn đã có, nhưng chưa phải cơ chế sửa sai phiếu thu/hoàn tiền.

- Giữ phiếu gốc, thêm bản ghi xử lý có số tiền, lý do, thời điểm và người thực hiện; không sửa/xóa tiền cũ trực tiếp.
- Xác định ngày đối chiếu, số tiền còn được xử lý và quy tắc báo cáo; chặn xử lý vượt hoặc xử lý cùng phần tiền hai lần.
- Cập nhật đồng bộ dư nợ, sổ tiền, tổng quan, chứng từ và lịch sử hai vai trò; kiểm thử mất phản hồi/gửi trùng/xung đột.
- Cần mở lại nhóm PDF/Excel để phản ánh bản ghi xử lý nếu hai phần này đã triển khai. Vì có phụ thuộc này, nếu chọn sửa sai tiền cho đợt tới thì chốt quy tắc ngay từ đầu.

### Hợp đồng, tiền cọc, quyết toán

Tách thành ba đợt thay vì một màn hình lớn:

1. **Hợp đồng:** gắn nhóm/đợt thuê, ngày bắt đầu/kết thúc, thông tin và phiên bản; gia hạn và nhắc sắp hết hạn. Mẫu nội dung cần được thống nhất riêng, không tự sinh điều khoản.
2. **Tiền cọc:** sổ các lần nhận/khấu trừ/hoàn, số dư và lý do; xác định rõ cách đối chiếu với dòng tiền, tách khỏi doanh thu tiền thuê.
3. **Quyết toán:** chốt chỉ số cuối, công nợ còn lại, khoản khấu trừ/hoàn và bàn giao; xem trước/xác nhận, giữ lịch sử. Trả phòng không tự xóa nợ.

Chỉ triển khai khi có đủ thời gian kiểm thử toàn bộ nhận → ở → trả phòng và người dùng/giảng viên chọn vào phạm vi.

## 8. Nâng cấp thao tác điện nước và giao diện

- **Ảnh đồng hồ:** chủ trọ gắn ảnh với đúng kỳ chỉ số; lưu riêng tư, kiểm tra định dạng/dung lượng, quyền xem và quy tắc giữ ảnh kỳ đã khóa. Người thuê chỉ xem khi quan hệ/quyền phù hợp đã được chốt.
- **Nhập nhiều phòng:** bảng chọn kỳ/phòng, chỉ số đầu lấy theo lịch sử, nhập chỉ số cuối, đánh dấu dòng lỗi và xem lại trước lưu. Có kết quả riêng từng dòng; không báo thành công cả bảng khi một phần chưa lưu. Giữ khóa kỳ và khoảng áp dụng giá hiện có.
- **Tiện ích nhỏ:** giữ bộ lọc khi quay lại danh sách, liên kết về đúng bản ghi và hướng dẫn tại thao tác khó. Chỉ làm sau khi kiểm tra cụ thể chỗ nào chưa có/khó dùng.
- **QR:** hiển thị tài khoản nhận, số tiền và nội dung để đối chiếu; người thuê không tự đánh dấu hóa đơn đã trả, chủ trọ vẫn xác nhận thực nhận.

Chat, ứng dụng điện thoại riêng, nhiều khu trọ/nhiều chủ sở hữu, phân công thợ và đối soát ngân hàng thuộc các hướng mở rộng sau. Quên mật khẩu vẫn hoãn theo yêu cầu trước đó.

## 9. Quy trình cho mỗi chức năng được chọn

1. Chốt quy tắc, đối tượng dùng và một vài ví dụ đầu vào/kết quả mong đợi.
2. Xác định dữ liệu mới, phụ thuộc với tiền/quyền/lịch sử và kế hoạch nâng cấp không mất dữ liệu.
3. Làm backend và kiểm tra nghiệp vụ; làm màn hình chủ trọ, rồi phần người thuê nếu có.
4. Kiểm tra toàn luồng với dữ liệu giả: quyền, số tiền, gửi lại sau lỗi mạng, bố cục và tệp/ảnh nếu liên quan.
5. Cho người dùng tự thử; sửa lỗi tìm được, cập nhật hướng dẫn, rồi tạo mốc GitHub mới khi được yêu cầu.

Các bước ghi nhiều bảng liên quan phải thành công hoặc hủy cùng nhau; Laravel có cơ chế giao dịch hỗ trợ việc này theo [tài liệu database](https://laravel.com/docs/12.x/database#database-transactions). Cơ chế này bổ sung cho kiểm tra quyền/chống trùng, không thay thế chúng.

Trước thay đổi CSDL/vận hành cần bản sao và lần khôi phục thử: [MySQL hướng dẫn sao lưu và phục hồi](https://dev.mysql.com/doc/refman/8.0/en/backup-and-recovery.html). Với dự án này, kế hoạch sao lưu còn phải bao gồm thư mục ảnh tương ứng. GitHub hiện chỉ giữ mã nguồn/cấu trúc đã đưa lên, không phải bản sao dữ liệu/ảnh.

## 10. Thứ tự nên thực hiện thực tế

**Ngay bây giờ:** chuẩn bị bản trình và ghi nhu cầu nâng cấp để giảng viên đánh giá.

**Sau đánh giá:** chọn một chức năng đầu tiên, hoàn thiện và tự thử rồi mới chuyển sang chức năng kế tiếp. Gói đề xuất mặc định là **PDF → Excel → chi phí sửa chữa → nghiệm thu lại**. Nếu cần dùng tiền thật thường xuyên, đưa sửa sai phiếu thu lên trước PDF/Excel và đối chiếu lại báo cáo; phần hoàn tiền thực tế là bước tiếp theo sau khi quy tắc đã được chốt.

**Sau khi chốt các bổ sung:** hoàn thiện hướng dẫn/cài đặt, chốt bản sản phẩm và bắt đầu Word theo yêu cầu của giảng viên. Việc triển khai hosting chỉ trở thành đầu việc riêng khi cần truy cập từ xa hoặc được yêu cầu đưa lên máy chủ.
