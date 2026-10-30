# Tự kiểm tra chi phí sửa chữa

Phần này dành cho chủ trọ. Người thuê tiếp tục xem ảnh, tiến độ và lịch sử xử lý của mình; không xem sổ tiền hoặc chi phí nội bộ.

## Chọn khoản chi đã ghi

1. Đăng nhập chủ trọ, mở **Sửa chữa → chi tiết yêu cầu → Chi phí sửa chữa**.
2. Bấm **Liên kết khoản chi**, tìm nội dung khoản đã ghi trong sổ thu chi và chọn khoản đó.
3. Đối chiếu mã khoản, nội dung và số tiền. Nếu khoản đã liên kết với yêu cầu khác, mở yêu cầu đó để xem/gỡ liên kết trước khi chuyển.
4. Nhập lý do từ 3 đến 1.000 ký tự, bấm **Xem lại liên kết → Xác nhận liên kết**.
5. Khoản xuất hiện trong danh sách, tổng sửa chữa tăng đúng số tiền. Sổ thu chi vẫn có một khoản gốc; thao tác liên kết không tạo dòng tiền mới.

Một yêu cầu có thể có nhiều khoản chi. Một khoản chi chỉ liên kết với một yêu cầu tại cùng thời điểm.

## Tạo khoản chi mới rồi liên kết

1. Trong hộp chọn khoản, bấm **Tạo khoản chi mới**.
2. Giữ loại **Chi phí**, nhập tiền, thời điểm và nội dung; xem lại và xác nhận ghi nhận.
3. Khoản mới được ghi vào sổ trước. Giao diện quay lại phần liên kết, chọn sẵn khoản vừa tạo.
4. Nhập lý do và xác nhận liên kết riêng. Nếu dừng tại đây, khoản vẫn có trong sổ nhưng chưa tính vào chi phí của yêu cầu này.

Hoàn tất một yêu cầu sửa chữa không tự sinh khoản chi; chỉ ghi tiền khi có chi phí thực tế.

## Sửa, xóa, khôi phục và gỡ liên kết

- **Xem khoản chi** mở chi tiết/nhật ký gốc. Sửa tiền sẽ cập nhật tổng sửa chữa từ chính khoản đó.
- Khi đang liên kết, khoản giữ loại **Chi phí**; muốn đổi thành thu khác phải gỡ liên kết trước.
- Xóa khoản khiến khoản không tính vào tổng sửa chữa hoặc sổ tiền; danh sách liên kết vẫn hiển thị rõ trạng thái đã xóa.
- Hoàn tác để khôi phục khoản chi sẽ tính lại vào tổng tương ứng.
- **Bỏ liên kết** có bước xem lại và lý do; khoản chi vẫn còn trong sổ tiền. Có thể liên kết lại sau khi đối chiếu phiên bản mới.
- Mở **Lịch sử liên kết chi phí** để xem người thực hiện, thời điểm, lý do và số tiền quan sát lúc liên kết/gỡ liên kết. Giá trị hiện tại ở danh sách có thể khác ảnh chụp lịch sử khi khoản gốc đã được sửa.

## Khi mất kết nối hoặc có thay đổi từ nơi khác

Yêu cầu liên kết/gỡ liên kết được giữ theo tài khoản và yêu cầu sửa chữa trên trình duyệt. Sau lỗi mạng, đóng/mở lại hoặc tải lại trang, bấm **Mở yêu cầu đang chờ → Gửi lại yêu cầu đang chờ** để đối chiếu đúng thao tác cũ.

Nếu máy chủ đã lưu trước khi mất phản hồi, gửi lại không tạo thêm sự kiện hoặc kích hoạt lại liên kết đã thay đổi về sau. Danh sách được tải lại theo trạng thái hiện tại.

Khi phiên bản khoản hoặc liên kết đã thay đổi, xem danh sách/nhật ký mới nhất. Chỉ dùng **Đối chiếu và bỏ bản chờ** sau khi xác định kết quả; thao tác đó xóa bản chờ trên trình duyệt, không hoàn tác dữ liệu máy chủ. Sau đó chọn lại khoản và xem lại dữ liệu mới nếu vẫn cần thao tác.

Đổi tài khoản sẽ xóa thông tin đang hiển thị; bản chờ của tài khoản trước không được đưa sang tài khoản mới.

## Kết quả mong đợi để đối chiếu

- Liên kết khoản 100.000 đồng: tổng sửa chữa tăng 100.000 đồng; sổ thu chi vẫn chỉ có khoản gốc.
- Sửa khoản thành 120.000 đồng: tổng sửa chữa thành 120.000 đồng.
- Xóa khoản: tổng về 0 đồng, khoản giữ nhãn đã xóa; khôi phục: tổng trở lại 120.000 đồng.
- Gỡ liên kết: tổng yêu cầu về 0 đồng, sổ thu chi vẫn có 120.000 đồng tiền chi.
- Người thuê không có phần chi phí và không truy cập được API quản lý khoản chi.

Các ví dụ nên thực hiện trong bản dữ liệu thử riêng. Hướng dẫn này mô tả chức năng và cách tự thử; kết quả kiểm thử tự động được ghi riêng theo lượt thực tế.
