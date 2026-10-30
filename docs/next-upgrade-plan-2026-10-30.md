# Kế hoạch hoàn thiện và nâng cấp tiếp theo — 30/10/2026

**Cập nhật sau khi chọn mục 1 và 2:** đã tạo bản trình riêng với dữ liệu và công cụ sao lưu/khôi phục; đã nâng hiệu ứng/thành phần chờ/thông báo, cảnh báo bản nháp và tiến độ nhập chỉ số. [Cách chạy và tập trình](../frontend/docs/presentation-guide.md). Phần người dùng tập trình và thử điện thoại thật vẫn cần thực hiện; các mục Excel/ảnh đồng hồ/nghiệp vụ lớn bên dưới giữ ở mức đề xuất.

Kế hoạch dựa trên sản phẩm hiện có và lịch giảng viên xem trong tuần đầu hoặc đầu tuần 2 tháng 11. Thứ tự đề xuất: **chốt bản trình → nâng giao diện → Excel báo cáo → ảnh đồng hồ → chọn nghiệp vụ lớn theo góp ý**. Trạng thái mục 1–2 đã được cập nhật ở trên; các phần Excel, ảnh đồng hồ và nghiệp vụ lớn tiếp tục là đề xuất.

## Điểm xuất phát

- Đã có CT1–CT8 chủ trọ, NT1–NT4 người thuê, đăng nhập/phân quyền và thông báo chung.
- Đã có quản lý phòng/dịch vụ/người thuê/quá trình thuê, điện nước, hóa đơn/công nợ, thu tiền/sửa sai phiếu, thu chi/hoàn tác, sửa chữa/ảnh/lịch sử/chi phí.
- Tổng quan đã có biểu đồ hóa đơn và thu chi theo tháng. Một số nút, menu và cột biểu đồ đã có chuyển động nhẹ; CSS chung đã hỗ trợ giảm chuyển động theo thiết lập hệ thống.
- Mới hoàn thiện thông tin nhà trọ, PDF hóa đơn/phiếu thu theo quyền và nhập chỉ số nhiều phòng có chống gửi trùng/phục hồi thủ công.
- Kết quả đã chạy gần nhất: backend 399 kiểm thử, frontend 131 kiểm thử, đóng gói đạt, 14 kịch bản Chrome liên quan đạt. Đây là kết quả các lượt đã chạy; điện thoại thật, máy cài mới và hosting cần nghiệm thu riêng. [Biên bản](../frontend/docs/property-pdf-bulk-acceptance.md).

## Thứ tự ưu tiên

| Mức | Công việc | Kết quả mong đợi | Thời điểm |
| --- | --- | --- | --- |
| P0 | Chốt bản trình và thử hai vai trò | Khởi động được, dữ liệu giả đủ tình huống, demo và đối chiếu tiền trôi chảy | Trước buổi giảng viên xem |
| P1 | Hoàn thiện thao tác và hiệu ứng giao diện | Nhận biết rõ đang tải/đã lưu/lỗi; màn hình ngăn nắp, chuyển động nhẹ | Một đợt nhỏ sau khi chốt bản trình; có thể chọn trước buổi xem nếu còn đủ thời gian kiểm tra |
| P2 | Xuất Excel báo cáo | Chủ trọ tải công nợ, sổ thu chi và danh sách hóa đơn khớp bộ lọc | Sau nghiệm thu P1 hoặc theo góp ý giảng viên |
| P3 | Ảnh đồng hồ điện/nước | Có căn cứ đối chiếu từng kỳ chỉ số, xem được theo đúng quyền | Sau Excel; có thể đổi lên trước nếu người dùng cần đối chiếu chỉ số thường xuyên |
| P4 | Hợp đồng → tiền cọc → quyết toán | Hoàn thiện vòng đời thuê, giữ được lịch sử và đối chiếu dòng tiền | Chọn sau đánh giá, tách ba đợt |
| Điều kiện sử dụng từ xa | Hosting, lưu ảnh và khôi phục dữ liệu | Có bản chạy trực tuyến, ảnh bền vững và bản sao khôi phục được | Đưa lên ưu tiên trước P2–P4 nếu giảng viên cần truy cập từ xa |
| Tùy chọn | QR chuyển khoản, trợ giúp theo màn hình, tiện ích tra cứu | Thuận tiện hơn trong thao tác hàng ngày | Sau các phần đã chọn ở trên |

## P0 — Chốt bản sản phẩm để trình

1. Chuẩn bị bộ dữ liệu giả riêng: phòng trống, một người thuê, nhiều thành viên, hai kỳ hóa đơn, thu một phần/đủ, một hóa đơn quá hạn, một sự cố có ảnh và lịch sử. Dùng bản sao có thể khôi phục để tập nhiều lượt.
2. Kiểm tra khởi động/dừng từ hướng dẫn, đúng bản mã/đúng CSDL, đăng nhập hai vai trò bằng hai trình duyệt hoặc hồ sơ. Kiểm tra tải lại trang, ảnh cũ và PDF sau khởi động lại.
3. Chạy thử nhận phòng → dịch vụ → nhập chỉ số nhiều phòng → phát hành hóa đơn → thu một phần → người thuê xem/tải PDF; đối chiếu số dư với công nợ, tổng quan và sổ thu chi.
4. Chạy thử báo hỏng/ảnh → chủ trọ xử lý/gắn chi phí → người thuê xem lịch sử/thông báo; thử một người dùng không có quyền và một yêu cầu bị gửi lại.
5. Kiểm tra điện thoại thật hoặc máy trình bày khác. Ghi rõ màn hình, bước thao tác, kết quả mong đợi và kết quả thực tế cho lỗi tìm được.
6. Sao lưu CSDL kèm ảnh, khôi phục trên môi trường riêng và mở lại ảnh/chứng từ tương ứng. Chuẩn bị vài ảnh hoặc video demo dự phòng và danh sách chức năng đã có.
7. Giữ một mốc mã nguồn/dữ liệu đã thử. Cập nhật GitHub khi có yêu cầu đẩy mã; ghi lại góp ý giảng viên trước khi chọn đợt mở rộng tiếp theo. Word bắt đầu sau giai đoạn giảng viên đánh giá/chốt phạm vi như đã thống nhất.

**Đạt khi:** trình được khoảng 10–12 phút, người xem tự thử được; số tiền/quyền xem đúng; các lỗi nghiêm trọng tìm được đã xử lý; có cách phục hồi bản demo. [Kịch bản chuẩn bị chi tiết](initial-report-plan-2026-11.md).

## P1 — Nâng giao diện và hiệu ứng

Giữ màu xanh, nền sáng, font và cấu trúc hiện có. Mục tiêu là giúp người dùng biết hành động đã xảy ra và dễ tìm việc cần làm. Có thể dùng CSS/Vue hiện tại cho gói này.

| Phần | Đề xuất cụ thể | Tiêu chí nghiệm thu |
| --- | --- | --- |
| Hộp thoại | Mờ hiện nhẹ và dịch chuyển ngắn khi mở/đóng, khoảng 150–200 ms | Không mất vị trí bàn phím, đóng xong trả về nút đã mở; nút lưu dùng được ngay |
| Nút/thẻ/menu | Đồng bộ phản hồi khi rê chuột, chọn và lấy nét; thay đổi màu/viền nhẹ | Trạng thái chọn rõ trên cả cảm ứng/bàn phím; không nhảy bố cục |
| Đang tải | Khung chờ đúng hình dạng thẻ/bảng ở các trang cần thiết; giữ kích thước vùng nội dung | Không hiển thị số tiền 0 như dữ liệu thật khi chưa tải xong; xóa nội dung riêng tư cũ khi đổi tài khoản |
| Đã lưu/lỗi | Thông báo cùng vị trí, câu ngắn rõ kết quả; lỗi gắn với ô cần sửa, có liên kết tới bản ghi đã tạo khi phù hợp | Người dùng nhận biết được thành công/thất bại; thao tác liên quan tiền còn bước xem lại/xác nhận |
| Biểu đồ có sẵn | Đồng bộ chuyển động ngắn khi đổi chế độ/kỳ, giữ nhãn, số chính xác và liên kết đối chiếu | Số tiền trên biểu đồ/chi tiết khớp API; kiểm tra cả trạng thái rỗng/lỗi |
| Danh sách dài | Rà bộ lọc, đường quay lại và vị trí cuộn; ghim đầu bảng ở màn hình phù hợp, tối ưu thẻ trên điện thoại | Tìm lại bản ghi thuận tiện, bộ lọc giữ đúng, không tràn ngang ở 320–390px |
| Biểu mẫu | Nhắc trước khi rời trang có thay đổi chưa lưu tại màn hình còn thiếu; báo tiến độ nhập nhiều phòng | Không đánh mất bản nháp mà không báo; phân biệt dòng đã lưu, đang chờ, lỗi |

**Hai vai trò:** chủ trọ ưu tiên bảng danh sách, biểu mẫu tiền và nhập nhiều phòng; người thuê ưu tiên hóa đơn, ảnh sự cố và tiến độ sửa chữa. Làm thành phần dùng chung trước, rồi rà từng vai trò.

Hiệu ứng bổ sung tuân theo lựa chọn giảm chuyển động của thiết bị, kế thừa hỗ trợ đã có; đối chiếu [hướng dẫn W3C về giảm chuyển động](https://www.w3.org/WAI/WCAG22/Techniques/css/C39). Thông báo trạng thái cần được công cụ hỗ trợ nhận biết mà không tự chuyển vị trí nhập liệu, theo [W3C về thông báo trạng thái](https://www.w3.org/WAI/WCAG22/Understanding/status-messages.html). Đây là tiêu chí cho đợt nâng cấp, chưa phải kết luận sản phẩm đạt toàn bộ WCAG.

**Thứ tự làm:** thành phần thông báo/hộp thoại/nút → trang tổng quan và danh sách chủ trọ → các biểu mẫu chủ trọ → hóa đơn/sự cố người thuê → thử bàn phím, giảm chuyển động, màn hình hẹp và đổi tài khoản. Chỉ sửa các điểm rà soát xác nhận còn thiếu; tận dụng hiệu ứng đã có.

## P2 — Excel báo cáo cho chủ trọ

1. **Công nợ trước:** xuất theo kỳ, phòng/nhóm, trạng thái và quá hạn; tổng hóa đơn, đã thu, còn nợ, hạn thanh toán. Ghi rõ bộ lọc và thời điểm xuất.
2. **Sổ thu chi:** theo khoảng thời gian thực nhận/thực chi, nguồn hóa đơn/thu khác/chi phí, tổng thu, chi và chênh lệch. Giữ quy tắc phiếu hủy/thay thế và khoản xóa/khôi phục hiện tại.
3. **Danh sách hóa đơn:** đủ các bản ghi qua mọi trang theo bộ lọc; thể hiện hóa đơn hủy/cần đối chiếu rõ ràng. Tệp là báo cáo tại thời điểm xuất.
4. Số tài khoản/mã dạng chuỗi giữ số 0 đầu; ngày và tiền dùng kiểu/định dạng phù hợp. Nội dung người dùng nhập được ghi dưới dạng văn bản để tránh trở thành công thức ngoài ý muốn; nguy cơ này được mô tả trong [hướng dẫn OWASP về Formula/CSV Injection](https://community.owasp.org/attacks/CSV_Injection).
5. Kiểm tra tài khoản chủ trọ ở API tải; người thuê không có quyền xuất sổ tiền. Đối chiếu tệp rỗng, nhiều trang dữ liệu, nội dung dài và tổng tiền với giao diện/API.

**Đạt khi:** có ba nút xuất tại đúng màn hình, tải đủ dữ liệu theo bộ lọc, tổng khớp; thử mở bằng phần mềm bảng tính. Phương án triển khai tạo tệp `.xlsx` thật sẽ được chọn khi bắt đầu đợt này.

## P3 — Ảnh đồng hồ theo kỳ chỉ số

- Chủ trọ đính kèm/xem ảnh điện hoặc nước cho đúng kỳ, kể cả khi ghi từ bảng nhiều phòng. Ảnh ban đầu là tùy chọn; dữ liệu cũ vẫn hợp lệ.
- Xem trước, trạng thái tải riêng từng ảnh, thử lại khi lỗi; gửi lại không thêm ảnh trùng. Lưu chỉ số thành công rồi tải ảnh thất bại phải nói rõ hai kết quả.
- Lưu ảnh riêng tư, kiểm tra loại ảnh/dung lượng/kích thước và sao lưu cùng CSDL. Sau phát hành hóa đơn, việc bổ sung/đổi ảnh phải giữ dấu vết và lý do; không làm ảnh căn cứ cũ biến mất âm thầm.
- Người thuê mở ảnh từ hóa đơn khi có quyền hiện tại và dòng hóa đơn liên kết đúng kỳ chỉ số. Chốt chi tiết quyền trước khi làm; thử thu hồi quyền, chuyển/trả phòng và tài khoản khác.
- Cung cấp màn hình đối chiếu đầu/cuối, tiêu thụ và ảnh, dùng được trên điện thoại. Nhận dạng chữ/chỉ số tự động là phạm vi mở rộng riêng.

**Đạt khi:** ảnh gắn đúng kỳ, không lẫn phòng, còn sau khởi động/cập nhật và được bảo vệ bằng quyền xem; tải lại sau lỗi không nhân bản ảnh.

## P4 — Hợp đồng, tiền cọc và quyết toán

Đây là gói nghiệp vụ lớn; cần thống nhất quy tắc bằng ví dụ trước khi thay đổi CSDL/dòng tiền.

| Đợt | Chủ trọ | Người thuê | Điều kiện nghiệm thu |
| --- | --- | --- | --- |
| Hợp đồng | Gắn nhóm/đợt thuê, thời hạn, người đại diện, tệp/nội dung đã thống nhất; gia hạn có lịch sử | Xem/tải bản được cấp quyền, biết thời hạn | Không gán nhầm người/đợt thuê; giữ phiên bản cũ; nhắc hết hạn theo lịch thực chạy |
| Tiền cọc | Ghi nhận từng lần nhận cọc, khấu trừ, hoàn và lý do; đối chiếu với dòng tiền | Xem số đã nộp, các thay đổi và số còn giữ | Cọc được theo dõi riêng; gửi lại không trùng; sửa sai giữ chứng từ gốc; tổng tiền khớp |
| Quyết toán trả phòng | Xem trước tiền phòng/dịch vụ/chỉ số cuối/công nợ/cọc; xác nhận và bàn giao | Xem chi tiết quyết toán theo quyền | Thử nhận → gia hạn/chuyển → trả; nợ còn lại được giữ; tiền hoàn/khấu trừ chỉ ghi khi xác nhận đúng quy tắc |

Các trường hợp cần chốt: một người hay nhiều thành viên; đổi đại diện; chuyển phòng; trả giữa tháng; còn nợ nhưng cọc chưa đủ; khấu trừ phí sửa chữa; hoàn một phần; sửa sai sau quyết toán. Mẫu điều khoản và cách tính phải được người dùng/giảng viên thống nhất trước.

## Hosting khi cần truy cập từ xa

1. Chọn môi trường chạy được ứng dụng hiện tại; cài thử bằng dữ liệu giả và cấu hình riêng.
2. Bố trí lưu ảnh bền vững qua các lần cập nhật; API ảnh giữ kiểm tra quyền. Chuyển cả CSDL và ảnh tương ứng.
3. Kiểm tra HTTPS/phiên đăng nhập, các đường tải PDF, giới hạn tải ảnh, lịch nhắc công nợ và khả năng theo dõi lỗi.
4. Sao lưu tự động và thử khôi phục ở môi trường riêng; ghi rõ người thực hiện/cách phục hồi. Tài khoản demo có mật khẩu đơn giản chỉ dùng cho bản thử riêng.
5. Cho người dùng thử lại hai vai trò từ thiết bị khác trước khi dùng dữ liệu thật.

Đây là kế hoạch chuẩn bị; việc chọn nhà cung cấp, mua dịch vụ và đưa bản chạy công khai thực hiện khi được yêu cầu.

## Những phần có thể làm sau

- **QR chuyển khoản:** dùng thông tin ngân hàng đã có, hiện số tiền/nội dung để đối chiếu; chủ trọ vẫn xác nhận thực nhận. Chốt cách tạo QR và dữ liệu gửi ra ngoài trước khi triển khai.
- **Trợ giúp theo màn hình:** giải thích đại diện/thành viên, tháng hóa đơn so với ngày thực thu, phiếu hủy/thay thế, cách xử lý bản chờ mất mạng.
- **Tiện ích tra cứu:** sao chép mã phiếu/hóa đơn, mở đúng bản ghi từ thông báo, cải thiện tìm kiếm sau khi xác nhận vị trí hiện tại chưa thuận tiện.
- Chat, phân công thợ, thông báo ngoài ứng dụng, nhiều khu trọ/nhiều chủ sở hữu, ứng dụng điện thoại riêng và đối soát ngân hàng là các gói chọn riêng sau đó.
- Quên mật khẩu tiếp tục hoãn theo quyết định trước của người dùng.

## Quy trình cho từng đợt

Chọn phạm vi và ví dụ đầu vào/kết quả → kiểm tra thành phần hiện có → làm dữ liệu/API nếu cần → giao diện chủ trọ → giao diện người thuê liên quan → kiểm tra quyền/tiền/gửi lại/lỗi/bố cục → người dùng tự thử → ghi kết quả và chốt bản. Khi nâng cấp CSDL, có bản sao và kiểm tra bảo toàn dữ liệu cũ.

**Gói nên chọn ngay:** P0 và một đợt P1 nhỏ. Sau buổi đánh giá, chọn P2 hoặc P3; P4 phù hợp khi cần tăng chiều sâu nghiệp vụ và có thời gian đối chiếu đầy đủ.
