# Tự thử nhập chỉ số nhiều phòng

Phần dành cho chủ trọ, mở từ **Điện nước → Nhập nhiều phòng**. Mỗi dòng là một dịch vụ trong một khoảng áp dụng giá của một đợt thuê; một phòng có thể xuất hiện nhiều dòng khi đổi giá hoặc đổi người thuê.

## Ghi chỉ số

1. Chọn tháng, tìm số phòng nếu cần, bấm **Tải danh sách phòng**.
2. Chọn các dòng cần ghi. Dòng đã khóa hoặc thiếu kỳ trước có thông báo và liên kết mở màn hình từng phòng.
3. Kiểm tra đầu/cuối kỳ theo giờ Việt Nam. Nếu đã có kỳ trước, chỉ số đầu được lấy từ lịch sử và giữ liên tục; nếu chưa có, tự nhập chỉ số đầu thực tế, không mặc định bằng 0.
4. Nhập chỉ số cuối. Tiêu thụ hiển thị chính xác đến ba chữ số thập phân; chỉ số cuối không được thấp hơn đầu.
5. Bấm **Xem lại các chỉ số đã chọn**, kiểm tra từng dòng và đánh dấu xác nhận, rồi **Xác nhận lưu các chỉ số**.
6. Xem kết quả riêng từng dòng. Dòng đã lưu không tự gửi lại; dòng lỗi không làm mất những dòng đã lưu thành công. Việc ghi chỉ số không tự phát hành hóa đơn.

Các bản nháp chưa xác nhận chỉ ở trên trang. Khi đổi bộ lọc/tải lại có bước xác nhận bỏ nháp; có thể chọn dòng qua nhiều trang rồi xem lại, tối đa 100 dòng mỗi lượt.

## Lỗi mạng hoặc hết phiên

- Sau khi xác nhận, từng dòng có mã yêu cầu và bản chờ riêng theo tài khoản. Lỗi mạng, mất phản hồi, hết phiên hoặc lỗi bảo mật sẽ dừng gửi các dòng còn lại.
- Sau tải lại/đăng nhập lại cùng tài khoản, mở **Xem lại các dòng đang chờ**, kiểm tra và **Gửi lại các dòng đang chờ**.
- Mỗi lượt đối chiếu tối đa 100 dòng; nếu có nhiều hơn, các dòng còn lại được giữ cho lượt sau.
- Dòng đã lưu trước khi mất phản hồi được đối chiếu, không tạo thêm kỳ trùng. Nếu kỳ đó đã được sửa hoặc xóa sau này, kết quả nói rõ và không tự tạo lại.
- Dòng chưa được gửi trong lượt trước vẫn chờ xác nhận, không tự chạy khi mở trang.
- Bản chờ hỏng không được âm thầm bỏ để sinh mã mới; kiểm tra lịch sử chỉ số trước khi chọn bỏ bản chờ không đọc được.

## Dữ liệu thay đổi và nhập lại

- Khi kỳ/giá/hóa đơn đã thay đổi, dòng báo xung đột. Bấm **Tải lại riêng dòng này**, xác nhận bỏ nháp riêng dòng đó, rồi nhập lại từ dữ liệu mới.
- Việc tải lại một dòng giữ bản nháp/kết quả các dòng bên cạnh.
- Kỳ đã phát hành hóa đơn không được thêm chỉ số. Dùng liên kết **Xem hóa đơn đã khóa** để đối chiếu.
- Trường hợp đổi giá, chuyển/trả phòng, thiếu kỳ tháng trước hoặc cần sửa kỳ cuối: mở **Xem lịch sử từng phòng** để xử lý đúng khoảng thời gian.
- Người thuê không có màn hình nhập nhiều phòng và không được dùng API ghi chỉ số.

Đối chiếu mẫu: chỉ số đầu `100.125`, cuối `110.375` phải cho tiêu thụ `10.250`; lưu hai phòng phải tạo đúng một kỳ cho mỗi dòng đã xác nhận. Khi mất phản hồi sau phòng đầu, gửi lại phải giữ phòng đầu một kỳ và chỉ ghi tiếp phòng còn chờ.
