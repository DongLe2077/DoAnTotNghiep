# Nghiệm thu chi phí sửa chữa và sửa sai phiếu thu — 27/10/2026

## Phạm vi đã làm

- Chi phí: chọn/tạo khoản chi gốc rồi liên kết, gỡ/liên kết lại có lý do, nhật ký người/thời điểm và snapshot khoản chi; tổng cập nhật khi sửa/xóa/khôi phục. Không tự ghi tiền khi hoàn tất sửa chữa, không cộng trùng, không lộ sổ tiền cho người thuê.
- Phiếu thu: hủy ghi nhận nhầm hoặc thay bằng phiếu đúng trong một giao dịch, giữ phiếu gốc và chuỗi thay thế, tính lại số dư/sổ tiền, thông báo người có quyền xem. Có chống gửi trùng và xử lý xung đột; không thực hiện hoàn tiền thực tế.
- Giao diện hai vai trò hiển thị phiếu có hiệu lực/đã hủy và quan hệ thay thế. Chủ trọ có thao tác xử lý, người thuê chỉ đọc. Sửa nhãn hạn thanh toán chưa đặt bị hiển thị lặp.

## Kết quả đã xác nhận

| Kiểm tra | Kết quả |
| --- | --- |
| Toàn bộ backend | 364/364, 3.756 assertions |
| Toàn bộ frontend unit | 127/127 |
| PHP style cho các tệp thay đổi | Đạt |
| TypeScript và đóng gói giao diện cuối | Đạt |
| MySQL mapping | 26 bảng nghiệp vụ, 95 quan hệ, 3 view |
| MySQL đồng thời riêng | 61 kiểm tra / 10 tình huống đạt; CSDL tạm đã dọn |
| Chrome hai nhóm mới | 6/6 trong lượt 38 ca liên quan |
| Chrome xác nhận cuối NT2 và sửa sai phiếu | 4/4 đạt, 1,2 phút |

Lượt Chrome 38 ca gồm hóa đơn/kiểm soát, thu chi, sửa chữa, báo cáo, người thuê và hai nhóm mới: 37 đạt, một ca NT2 cũ còn tìm heading chỉ có số tiền, trong khi giao diện mới hiển thị thêm mã phiếu. Số tiền trên màn hình/API đúng; đã cập nhật cách đối chiếu heading. Sau chỉnh nhãn hạn chưa đặt, chạy lại NT2 cùng ba ca sửa phiếu: **4/4 đạt**. Không gộp các kết quả thành một lượt 38/38 mới.

Lượt đầu sáu ca mới có ba ca chưa đạt do bộ kiểm tra tìm ô chọn bằng nhãn bao cả lựa chọn và do yêu cầu tổng hợp thiếu tháng của dữ liệu mẫu. Đã sửa cách định vị/bộ lọc; cả sáu ca đạt trong lượt liên quan nói trên.

## Những gì đã thử thực tế

- Liên kết khoản đã ghi, tạo khoản mới rồi xác nhận liên kết riêng; một dòng tiền gốc, không ghi trùng.
- Sửa tiền, xóa, hoàn tác khôi phục, gỡ/gắn lại: tổng yêu cầu/sổ tiền và nhật ký khớp.
- Mất phản hồi sau khi máy chủ đã lưu; tải lại/đăng nhập lại và gửi nguyên mã/nội dung: không tạo thêm phiếu hoặc sự kiện liên kết.
- Thay phiếu sai và hủy ghi nhận nhầm, chặn vượt tiền, chuỗi thay thế, giữ quyền/lịch sử hai vai trò.
- MySQL chạy đồng thời: cùng mã cùng/khác nội dung, mã dùng trên hóa đơn/khoản khác, một phiếu hai thao tác, sửa sai cùng lúc thu thêm/điều chỉnh, một khoản gắn hai sự cố và đổi chi thành thu khi đang liên kết.
- Bố cục 320px, quyền API người thuê và thông tin nội bộ không xuất hiện trong lịch sử thanh toán của người thuê.

## Dữ liệu và môi trường

Kiểm thử tự động backend dùng SQLite trong bộ nhớ; Chrome dùng Laravel thật với `frontend/.cache/e2e/auth.sqlite`, phiên/ảnh riêng. MySQL đồng thời dùng CSDL tên duy nhất với tiền tố `rental_financial_check_`, chỉ sao chép cấu trúc, tạo dữ liệu giả và xóa CSDL đó sau kiểm tra. Không ghi nghiệp vụ thử vào `rental_management`.

Nâng cấp MySQL đã tạo bản sao cục bộ trước khi thêm ba bảng mới và cập nhật view; đối chiếu dữ liệu gốc của 23 bảng giữ nguyên. Demo `owner-demo.sqlite` cũng được sao lưu/nâng cấp và giữ dữ liệu cũ. Tệp schema chỉ chứa metadata; bản sao, DB dùng thử, ảnh và nhật ký ở ngoài Git.

Các kết quả cục bộ này chưa thay thế nghiệm thu điện thoại thật, máy cài mới hoặc hosting. Dữ liệu demo không tự xóa khi khởi động lại.

## Tự thử

- [Chi phí sửa chữa](repair-expenses-self-test.md)
- [Sửa sai phiếu thu](payment-corrections-self-test.md)
- [Quy tắc/API chi phí](../../backend/docs/maintenance-expenses.md)
- [Quy tắc/API phiếu thu](../../backend/docs/payment-corrections.md)
