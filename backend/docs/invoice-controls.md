# Hạn thanh toán, điều chỉnh và hủy hóa đơn

Hoàn thành ngày 23/10/2026, có giao diện chủ trọ và người thuê.

## Quy tắc nghiệp vụ

- Khi phát hành có thể chọn `due_date` (ngày Việt Nam, YYYY-MM-DD), từ hôm nay trở đi. Không chọn thì chưa đặt hạn; không tự gán hạn cho dữ liệu cũ.
- Một hóa đơn chỉ quá hạn khi còn nợ hợp lệ, chưa hủy và `due_date` nhỏ hơn ngày hiện tại. Ngày hạn đóng vẫn chưa quá hạn. Thu đủ thì hết công nợ quá hạn. Không tự tính tiền phạt.
- Chủ trọ đặt/đổi hạn sau phát hành bằng thao tác có lý do, xác nhận và lịch sử. Hạn mới từ hôm nay trở đi; gửi lại chính yêu cầu đã ghi nhận vẫn được chấp nhận sau khi qua ngày.
- Điều chỉnh là khoản tăng/giảm riêng, lưu số tiền và lý do; không sửa/xóa dòng phòng, dịch vụ hoặc chỉ số đã phát hành. Tổng mới không âm, không vượt 16 chữ số nguyên/2 số lẻ và không thấp hơn số đã thu. Tổng sau điều chỉnh dùng cho công nợ, thu tiền, báo cáo và biểu đồ; không cộng điều chỉnh thành tiền thực thu/chi.
- Chỉ hủy hóa đơn không còn phiếu thu có hiệu lực. Phiếu đã hủy ghi nhận qua nghiệp vụ sửa sai vẫn giữ trong lịch sử nhưng không chặn hủy hóa đơn. Sau hủy tổng có hiệu lực và công nợ bằng 0, trạng thái `cancelled`, không thể thu tiền/nhắc nợ. Hóa đơn, dòng gốc, điều chỉnh và quyền xem được giữ nguyên.
- Hủy không mở khóa nguồn điện nước hoặc cho phát hành trùng cùng đợt thuê/tháng. Có thể khôi phục bằng thao tác có lý do; tổng điều chỉnh và hạn đã lưu có hiệu lực trở lại. Cần sửa số tiền thì điều chỉnh trên chính hóa đơn đó.
- Điều chỉnh hóa đơn không xóa/hủy phiếu thu và không tự hoàn tiền. Ghi nhận phiếu sai được xử lý riêng bằng [sửa sai phiếu thu](payment-corrections.md), giữ phiếu gốc và tính lại số đã thu còn hiệu lực.
- Người thuê chỉ đọc hóa đơn còn quyền, hạn đóng, lịch sử và lý do; không có thao tác quản trị. Thông báo cập nhật hóa đơn giữ quyền theo cấp/thu hồi hiện tại, kể cả hóa đơn đã trả đủ hoặc đã hủy.

## API

`POST /api/owner/invoices/{invoice}/changes`

Các trường chung: `action`, `reason` (1–1000 ký tự sau chuẩn hóa), `version_token`, `idempotency_key`.

| action | Trường thêm |
| --- | --- |
| adjustment | amount: chuỗi thập phân có dấu, khác 0 |
| due_date | due_date: YYYY-MM-DD |
| cancel | Không có |
| restore | Không có |

Lấy `version_token` từ GET hóa đơn mới nhất. Token gồm phiên bản, hạn, trạng thái, tổng và số đã thu. Khi hóa đơn/thu tiền đã thay đổi: HTTP 409 và tải lại trước khi tạo yêu cầu mới. Thao tác và phiếu thu cùng khóa hàng cha `invoices` trong transaction.

Mã yêu cầu có UNIQUE phân biệt hoa thường trên MySQL. Replay cùng toàn bộ nội dung, hóa đơn và người thực hiện trả `replayed: true`; mã cũ với nội dung khác bị 409. Replay được đối chiếu trước phiên bản để không áp dụng khoản điều chỉnh lần hai sau lỗi mạng.

Danh sách hóa đơn và công nợ nhận thêm `due_status=overdue|upcoming|not_set`. Hóa đơn nhận `payment_status=cancelled`. Tổng hợp công nợ có `overdue_count`, `overdue_amount`, `cancelled_count`. Số quá hạn tính theo ngày hiện tại, không theo tháng đang xem.

## CSDL và vận hành

Migration `2026_10_23_000001_add_invoice_controls` thêm 3 cột vào invoices, bảng `invoice_changes`, cập nhật view `invoice_balances`. Các cột/dòng dữ liệu cũ giữ nguyên. CSDL hiện có 21 bảng ứng dụng, 75 quan hệ và 3 view.

Migration down từ chối khi đã có lịch sử thay đổi hoặc hạn đóng, để tránh mất dấu vết và làm sai số tiền. Khi cần quay lại phiên bản cũ phải lập phương án khôi phục bản sao đã kiểm tra.

Lịch nhắc lúc 08:00 giờ Việt Nam dùng `rental:remind-invoices --overdue`; cần chạy Laravel scheduler trên máy vận hành. Chạy `--dry-run` chỉ đếm ứng viên. Nhắc thủ công vẫn có thể dùng cho công nợ hợp lệ chưa đến hạn. Mỗi hóa đơn/người nhận/ngày tối đa một nhắc.

Giao diện lưu yêu cầu thay đổi chưa rõ kết quả trong sessionStorage theo chủ trọ/hóa đơn, giữ nguyên khi gửi lại. Không tự gửi lại khi lỗi mạng/CSRF; kiểm tra thủ công bằng nút gửi lại. Khi trình duyệt không lưu được, cảnh báo giữ hộp thoại mở. Nếu xóa dữ liệu trình duyệt hoặc đóng cả phiên, cần đối chiếu lịch sử trước khi tạo thao tác mới.

Kiểm thử bổ sung: InvoiceControlsTest, BusinessAcceptanceTest, frontend invoice-controls.test.ts và invoice-controls.spec.ts. Kết quả nghiệm thu nằm ở `frontend/docs/business-acceptance.md`.
