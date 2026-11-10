# Đề xuất nghiệp vụ mới theo phạm vi khóa luận — 30/10/2026

**Phạm vi đã chốt:** khóa luận không thực hiện quản lý hợp đồng. Các ý dưới đây là đề xuất mới sau khi rà chức năng hiện có và các đề xuất trước, chưa được chọn triển khai. Thứ tự đánh giá dựa trên mức gắn với bài toán nhà trọ, khả năng trình diễn và lượng quy tắc cần kiểm tra; không phải xếp hạng theo khảo sát thị trường.

## 1. Quản lý tài sản, thiết bị theo phòng — nên ưu tiên

**Bài toán:** biết máy lạnh, quạt, giường, bàn hoặc thiết bị của nhà trọ đang ở phòng nào, tình trạng ra sao và khi nào cần bảo dưỡng.

- Chủ trọ tạo mã thiết bị, loại, ngày đưa vào dùng, tình trạng và phòng đang đặt.
- Ghi nhận chuyển thiết bị sang phòng khác, thay thế hoặc ngừng sử dụng; giữ lịch sử vị trí/tình trạng.
- Lập mốc bảo dưỡng theo thiết bị và ghi kết quả kiểm tra. Khi báo hỏng, có thể chọn thiết bị liên quan trong phòng.
- Người thuê xem danh sách thiết bị thuộc nhà trọ đang trang bị cho phòng của mình; thiết bị cá nhân không mặc định thuộc sổ tài sản.

**Ví dụ trình:** máy lạnh TS-001 chuyển TB-201 sang TB-202; hệ thống thể hiện vị trí hiện tại và vị trí trước, chặn gán thiết bị vào hai phòng cùng lúc. Mốc bảo dưỡng đã làm được giữ trong lịch sử.

**Mức tương đối:** vừa đến khá lớn nếu chọn cả bảo dưỡng. Giai đoạn đầu làm danh mục → vị trí/tình trạng/lịch sử, sau đó bổ sung lịch bảo dưỡng. Phần này tạo mô hình tài sản và luồng điều chuyển mới; lịch sử sửa chữa hiện có có thể liên kết tới thiết bị.

## 2. Cảnh báo điện/nước tăng bất thường — nên ưu tiên

**Bài toán:** chỉ số nhập hợp lệ nhưng mức dùng tăng mạnh so với lịch sử cần được kiểm tra sớm.

- Lấy mức tiêu thụ các kỳ trước, quy đổi theo số ngày; cộng các đoạn trong cùng kỳ khi đổi giá để tránh so sánh tiền thay cho lượng dùng.
- Chủ trọ chọn ngưỡng cảnh báo và số kỳ nền tối thiểu. Ngưỡng ví dụ +50% là cấu hình minh họa, không phải quy tắc mặc định đã được xác nhận cho nhà trọ.
- Hiện phòng/kỳ, mức dùng bình quân/ngày, kỳ được dùng làm mốc và lý do cảnh báo. Thay người thuê, ngừng dịch vụ hoặc thiếu dữ liệu cần được thể hiện rõ trong phép so sánh.
- Chủ trọ kiểm tra rồi ghi kết quả: số đúng, cần xem lại hoặc chưa xác định. Chỉ số và hóa đơn vẫn xử lý qua các chức năng hiện có.

**Ví dụ trình:** kỳ trước 90 kWh/30 ngày = 3 kWh/ngày; kỳ mới 60 kWh/10 ngày = 6 kWh/ngày. Lượng dùng bình quân tăng 100% dù tổng kỳ mới thấp hơn kỳ trước. Kỳ nền bằng 0 hoặc thiếu lịch sử hiển thị chưa đủ căn cứ, không chia cho 0.

**Mức tương đối:** vừa. Đây là đề xuất phân tích theo quy tắc, không yêu cầu mô hình AI hoặc thiết bị đo tự động. Cảnh báo là tín hiệu kiểm tra, không phải kết luận có rò nước/hỏng thiết bị. Việc so sánh theo mức dùng bình quân ngày có thể tham khảo [hướng dẫn theo dõi điện năng của OAS](https://www.oas.org/en/sedi/dsd/GeneralDocs/Publications/EE_Guide.pdf); công thức/ngưỡng cụ thể bên trên là thiết kế đề xuất cho dự án.

## 3. Đặt lịch xem phòng và danh sách khách quan tâm

**Bài toán:** quản lý giai đoạn trước khi một người trở thành người thuê.

- Chủ trọ ghi khách quan tâm, cách liên hệ, phòng muốn xem và khung giờ.
- Xác nhận, đổi/hủy lịch; theo dõi đã đến, không đến, còn cân nhắc hoặc quyết định ở.
- Khi khách quyết định ở, chuyển thông tin đã xác nhận sang hồ sơ người thuê và tiếp tục quy trình nhận phòng hiện có.
- Lịch xem chỉ phản ánh cuộc hẹn; việc nhận phòng vẫn kiểm tra phòng trống ở thời điểm xác nhận.

**Ví dụ trình:** hai cuộc hẹn dùng cùng khung giờ phụ trách được báo xung đột; chuyển giờ một lịch và ghi kết quả buổi xem. Khách chưa đồng ý ở vẫn thuộc danh sách quan tâm.

**Mức tương đối:** vừa. Bản đầu dành cho chủ trọ nhập/quản lý lịch, dùng hai vai trò hiện có.

## 4. Quản lý phương tiện và chỗ để xe

**Bài toán:** biết xe của ai, thuộc phòng nào, đang đăng ký gửi và còn bao nhiêu chỗ.

- Người thuê đăng ký biển số/loại xe; chủ trọ duyệt, phân khu hoặc gán chỗ nếu nhà trọ có đánh số.
- Theo dõi đang gửi/ngừng gửi và lịch sử thay đổi. Khi người thuê chuyển/trả phòng, hệ thống hướng dẫn đối chiếu trạng thái đăng ký xe.
- Chặn hai đăng ký còn hiệu lực dùng cùng biển số hoặc cùng một chỗ được đánh số.
- Người thuê xem xe của mình; chủ trọ quản lý toàn bộ. Giới hạn số chỗ là cấu hình thực tế của nhà trọ.

**Ví dụ trình:** khu A còn một chỗ, duyệt một xe mới; yêu cầu gán xe khác vào chỗ đó bị từ chối cho tới khi kết thúc lượt sử dụng cũ.

**Mức tương đối:** vừa. Giai đoạn đầu quản lý đăng ký/chỗ và lịch sử; phù hợp khi nhà trọ thực sự có nhu cầu kiểm soát bãi xe.

## 5. Quản lý công tơ và lần thay công tơ

**Bài toán:** theo dõi danh tính công tơ và xử lý chuyển từ công tơ cũ sang công tơ mới có mốc chỉ số khác.

- Ghi mã công tơ, loại điện/nước, phòng, thời gian lắp/ngừng dùng.
- Khi thay, chủ trọ chốt chỉ số cuối công tơ cũ, ghi chỉ số đầu thực tế của công tơ mới và thời điểm chuyển.
- Lượng dùng của mỗi công tơ được tính riêng, ghép vào kỳ theo thời gian/đơn giá tương ứng; dữ liệu lịch sử vẫn gắn công tơ cũ.
- Số giảm ở cùng công tơ vẫn cần kiểm tra. Việc đổi thiết bị là thao tác có xác nhận, không tự suy ra từ một lần nhập số nhỏ hơn.

**Ví dụ trình:** công tơ cũ từ 1.250 lên 1.280 dùng 30 kWh; công tơ mới từ 7 lên 19 dùng 12 kWh; tổng lượng của hai đoạn là 42 kWh. Giá và thời gian tính phí tiếp tục dùng quy tắc kỳ hiện có.

**Mức tương đối:** khá lớn vì liên quan chỉ số một/nhiều phòng, hóa đơn và khóa dữ liệu. Cần kiểm tra trường hợp thay giữa tháng, đổi giá cùng ngày và kỳ đã phát hành hóa đơn. Việc phân biệt chu kỳ/công tơ là nguyên tắc cũng được mô tả trong [tài liệu sensor của Home Assistant](https://contributors.home-assistant.io/core/entities/sensor/); ứng dụng này đề xuất thao tác xác nhận thủ công theo dữ liệu của chủ trọ.

## 6. Khảo sát mức hài lòng người thuê — tùy chọn

**Bài toán:** thu phản hồi có cấu trúc để chủ trọ biết những mặt cần cải thiện.

- Tạo đợt khảo sát ngắn về vệ sinh, yên tĩnh, tiện ích hoặc trải nghiệm được hỗ trợ.
- Người thuê đủ điều kiện trả lời một lần/đợt; thông báo rõ phản hồi có gắn tài khoản hay không trước khi gửi.
- Tổng hợp số người đã trả lời, điểm từng tiêu chí và nội dung góp ý. Giữ phiên bản câu hỏi để kết quả các đợt được diễn giải đúng.

**Ví dụ trình:** năm người được mời, ba người trả lời; giao diện ghi rõ 3/5 thay vì diễn giải như toàn bộ người thuê đã đánh giá.

**Mức tương đối:** nhỏ đến vừa. Phần này tạo dữ liệu đánh giá mới, có thể trình diễn nhanh nhưng chiều sâu nghiệp vụ thấp hơn quản lý tài sản/công tơ.

## Gói nên chọn

**Ưu tiên: tài sản/thiết bị → cảnh báo tiêu thụ bất thường.** Hai phần mở rộng nghiệp vụ theo hướng khác nhau và có đầu vào/kết quả rõ để nghiệm thu. Nếu muốn thêm tương tác người thuê, chọn đăng ký xe; nếu muốn mở rộng hoạt động tìm khách, chọn lịch xem phòng. Chỉ chọn một đợt phù hợp góp ý và thời gian trước khi triển khai tiếp.
