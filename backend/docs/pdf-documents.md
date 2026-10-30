# PDF hóa đơn và phiếu thu

PDF được tạo tại thời điểm người dùng yêu cầu, bằng Dompdf và font DejaVu Sans cục bộ có tiếng Việt. Tệp được trả trực tiếp về trình duyệt, không ghi vào thư mục công khai hoặc tạo đường dẫn tải bỏ qua đăng nhập.

## API và quyền xem

| Vai trò | GET sau `/api` | Nội dung |
| --- | --- | --- |
| Chủ trọ | `/owner/invoices/{invoice}/pdf` | Hóa đơn và số dư/lịch sử hiện tại |
| Chủ trọ | `/owner/payments/{payment}/pdf` | Một phiếu thu, gồm trạng thái sửa sai |
| Người thuê | `/tenant/invoices/{invoice}/pdf` | Hóa đơn đang có quyền xem |
| Người thuê | `/tenant/invoices/{invoice}/payments/{payment}/pdf` | Phiếu thuộc đúng hóa đơn đang có quyền xem |

Phải đăng nhập tài khoản hoạt động và đúng vai trò. Quyền người thuê được kiểm tra bằng truy vấn `active_invoice_access` hiện tại, gồm trường hợp đã chuyển/trả phòng; không suy ra quyền từ việc từng tải tệp. Phiếu thuộc hóa đơn khác trả 404. Quyền bị thu hồi chặn các lần tải tiếp theo.

Phản hồi là `application/pdf`, tên tệp ASCII theo ID, `Content-Disposition: attachment`, `Cache-Control: private, no-store`, `nosniff`. Tệp đã tải trước đó nằm trên thiết bị người dùng và không tự thay đổi khi dữ liệu hoặc quyền xem thay đổi.

## Nội dung

- Thông tin nhà trọ/liên hệ lấy từ hồ sơ hiện tại; khi chưa cấu hình, ghi rõ chưa có thông tin, không tự đặt tên/địa chỉ giả. Hướng dẫn tài khoản nhận chuyển khoản chỉ nằm trên hóa đơn chưa trả/trả một phần còn khoản được phép thu. Hóa đơn đã trả đủ, đã hủy hoặc cần đối chiếu không có hướng dẫn thu mới; phiếu thu cũng không kèm yêu cầu chuyển khoản mới.
- Hóa đơn: phòng/nhóm/kỳ, phát hành/hạn thanh toán, dòng gốc, số lượng/đơn giá chính xác, điều chỉnh, tổng có hiệu lực, đã thu/còn nợ, lịch sử thay đổi và các phiếu.
- Hóa đơn hủy có nhãn nổi bật, không dùng để yêu cầu thanh toán; dòng gốc vẫn giữ để đối chiếu.
- Phiếu: đúng một lần thu, số tiền/thời điểm/hình thức, trạng thái có hiệu lực hoặc **ĐÃ HỦY GHI NHẬN**, lý do và mã phiếu thay thế/phiếu được thay. Không biến phiếu gốc bị sửa sai thành phiếu hợp lệ.
- Số dư và thông tin nhà trọ là dữ liệu tại thời điểm xuất, được ghi rõ cùng thời điểm xuất; không coi thông tin nhà trọ mới là snapshot pháp nhân tại thời điểm phát hành cũ.
- Không đưa giấy tờ người thuê, mật khẩu, mã yêu cầu, token hoặc ID/tên tài khoản thao tác nội bộ vào PDF. Thông tin liên hệ/ghi chú cấu hình để in là nội dung chia sẻ trong chứng từ.

Snapshot dữ liệu được đọc trong giao dịch rồi chuyển thành mảng whitelist; quá trình kết xuất không đọc model trễ ngoài giao dịch. Tiền định dạng từ chuỗi thập phân, không qua số thực nhị phân. PDF có đánh số trang, header bảng lặp và ngắt dòng cho nội dung dài.

Khối tổng tiền được giữ cùng một trang. Phiếu thông thường đủ ngắn nằm trên một trang; phiếu có lý do/ghi chú rất dài có thể nhiều trang để giữ nội dung đầy đủ và chữ dễ đọc.

## An toàn kết xuất và vận hành

Dompdf không được tải tài nguyên từ xa, chạy PHP hoặc JavaScript. HTML từ người dùng được escape bằng Blade; `chroot` chỉ gồm thư mục mẫu và font thư viện. Chỉ bộ đệm/font tạm ở `storage/framework/cache/pdf`; không giữ bản PDF thành phẩm phía máy chủ. Hosting cần quyền ghi `storage` như Laravel và có các extension yêu cầu bởi Composer.

Đây là chứng từ quản lý nội bộ của ứng dụng. Chức năng không thực hiện hoàn tiền, gửi email hoặc tích hợp hóa đơn điện tử.

## Kiểm tra

Các ca tự động gồm PDF thật/header/font nhúng, quyền hiện tại/thu hồi/phiếu khác hóa đơn, tài khoản khóa/khách, hủy và chuỗi thay thế, số tiền chính xác, escape nội dung, snapshot không truy vấn muộn và hóa đơn nhiều trang tiếng Việt. Kiểm tra hình ảnh PDF mẫu được thực hiện riêng để đối chiếu font/bố cục ngoài việc đọc nội dung HTML.
