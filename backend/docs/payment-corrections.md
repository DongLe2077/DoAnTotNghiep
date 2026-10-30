# Sửa sai phiếu thu

Chủ trọ sửa thông tin ghi nhận nhầm bằng một nghiệp vụ riêng, không PUT/DELETE phiếu gốc. Đây là điều chỉnh sổ ghi nhận, không thực hiện chuyển tiền hoặc hoàn tiền thực tế.

## Quy tắc

- **Chỉ hủy ghi nhận nhầm (`void`):** giữ phiếu gốc và đánh dấu không còn hiệu lực bằng bản ghi đối chiếu; số tiền không còn tính vào đã thu/sổ tiền.
- **Thay bằng phiếu đúng (`replace`):** hủy hiệu lực toàn bộ phiếu sai và tạo phiếu mới đúng số tiền, thời điểm nhận và hình thức. Hai bước cùng một giao dịch: lỗi ở bước nào thì hủy cả thao tác.
- Có lý do, người thực hiện, tên đăng nhập tại thời điểm xử lý, thời điểm sửa và số dư trước/sau. Không sửa/xóa phiếu gốc hoặc lịch sử đối chiếu.
- Một phiếu chỉ được xử lý một lần. Phiếu thay thế có thể được sửa tiếp bằng bản ghi mới; chuỗi lịch sử vẫn được giữ.
- Phiếu mới phải có số tiền dương, không vượt số còn được thu sau khi loại phiếu sai; ngày nhận tiền từ lúc phát hành hóa đơn đến hiện tại. Không tạo thay thế nếu số tiền/ngày/hình thức không thay đổi.
- Các lần thu khác giữ nguyên. Hủy hết phiếu còn hiệu lực đưa hóa đơn về chưa thu; chủ trọ có thể thu đúng hoặc hủy hóa đơn theo quy tắc hiện có.
- Phiếu gốc bị hủy không còn góp vào sổ tiền ở ngày đã ghi; phiếu thay thế góp vào ngày nhận tiền đúng. Các báo cáo quá khứ được tính lại theo dữ liệu đã đối chiếu, còn thời điểm sửa được lưu riêng trong lịch sử. Không sinh một khoản chi/hoàn tiền giả.
- Điều chỉnh giảm hóa đơn vẫn bị chặn nếu thấp hơn số tiền của các phiếu còn hiệu lực.

## API

`POST /api/owner/payments/{payment}/corrections` dành cho tài khoản chủ trọ hoạt động, yêu cầu cookie/CSRF.

```json
{
  "action": "replace",
  "reason": "Đối chiếu thực nhận, nhập dư số tiền phiếu cũ",
  "version_token": "<64 ký tự từ phiếu vừa đọc>",
  "idempotency_key": "<mã mới cho thao tác này>",
  "replacement": {
    "amount": "800000.25",
    "paid_at": "2026-10-27 09:00:00",
    "payment_method": "bank_transfer"
  }
}
```

Với `action: void`, không gửi `replacement`. Lý do bắt buộc, tối đa 2.000 ký tự. Các trường actor, hóa đơn, phiếu thay thế, số dư và thời điểm tạo do máy chủ quyết định; không nhận dữ liệu giả mạo từ biểu mẫu.

Phản hồi gồm `data` (phiếu gốc ở trạng thái hiện tại), `replacement` (phiếu thay thế hoặc null), `invoice` (hóa đơn/số dư mới), `replayed` và `message`. Lần đầu 201, gửi lại chính xác 200. GET danh sách phiếu nhận thêm `status: active|voided`; bỏ bộ lọc để xem cả lịch sử.

Phiếu bổ sung `status`, `correction`, `replaces_payment_id`. Chủ trọ còn có `version_token` và `can_correct`; bản đối chiếu có người xử lý và số dư trước/sau. Hóa đơn của người thuê giữ các phiếu đã hủy/thay thế cùng lý do/thời điểm nhưng không lộ mã gửi lại, token, ID/tên tài khoản xử lý hoặc snapshot quản trị. Quyền xem hóa đơn vẫn kiểm tra như cũ.

## Chống trùng, xung đột và thông báo

- Mã yêu cầu gắn phiếu gốc, tài khoản, hành động, lý do, phiên bản và toàn bộ thông tin thay thế đã chuẩn hóa số tiền.
- Lưu bản chờ trên frontend; sau mất phản hồi/tải lại/đăng nhập lại cùng tài khoản, chủ động gửi lại nguyên yêu cầu. Không tự gửi lại thao tác ghi.
- Máy chủ kiểm tra mã đã ghi nhận trước khi kiểm tra token cũ. Gửi lại sau thành công không hủy thêm lần nữa hoặc tạo thêm phiếu.
- Đổi nội dung/phiếu/tài khoản với cùng mã, token cũ hoặc xử lý một phiếu lần thứ hai trả 409. Đọc lại để đối chiếu trước khi bắt đầu thao tác mới.
- Khóa hóa đơn cha dùng chung với thu tiền và điều chỉnh hóa đơn; khóa và ràng buộc duy nhất bảo vệ khi gửi đồng thời.
- Sửa sai, phiếu thay thế, tăng phiên bản hóa đơn và thông báo cùng giao dịch. Người đang được cấp quyền nhận thông báo `invoice_updated`; gửi lại không thêm thông báo.

## CSDL và nâng cấp

Migration `2026_10_27_000002_create_payment_corrections.php` thêm bảng `payment_corrections`, cập nhật view `invoice_balances` chỉ cộng phiếu còn hiệu lực. Không cập nhật các hàng phiếu thu gốc. Sổ tiền và kiểm tra thu vượt dùng cùng quy tắc loại phiếu đã đối chiếu.

Không rollback khi đã có lịch sử. Sao lưu trước nâng cấp; không chạy `migrate:fresh`, refresh/reset hoặc xóa dữ liệu gốc.

Kiểm thử mới bao gồm hủy/thay thế, số tiền thập phân, chuyển ngày báo cáo, chuỗi thay thế, thu thêm sau hủy, truy cập hai vai trò, chống giả mạo/CSRF, gửi lại, xung đột, vượt tiền và rollback khi lỗi thông báo. Kiểm tra MySQL riêng bổ sung thao tác đồng thời với thu thêm/điều chỉnh; không ghi nghiệp vụ vào CSDL sử dụng.
