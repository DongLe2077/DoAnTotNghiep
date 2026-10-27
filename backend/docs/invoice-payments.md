# Thu tiền hóa đơn

API cho chủ trọ ghi nhận tiền thực tế đã nhận bằng tiền mặt hoặc chuyển khoản, thu một phần hoặc toàn bộ số còn nợ. Mỗi lần thu được lưu trong `payments`; tổng đã thu, còn nợ và trạng thái hóa đơn được đọc từ `invoice_balances`. Đây là xác nhận thủ công, không thực hiện chuyển tiền hoặc kết nối ngân hàng.

## API và phân quyền

Đăng nhập bằng tài khoản `owner` đang hoạt động, giữ cookie session và gửi CSRF cho POST theo [hướng dẫn đăng nhập](authentication.md). Người thuê chỉ đọc thanh toán/công nợ qua hóa đơn đã được cấp quyền, không xác nhận thu tiền.

| Phương thức | Đường dẫn | Chức năng |
| --- | --- | --- |
| POST | `/api/owner/invoices/{invoice}/payments` | Ghi nhận lần thu hoặc gửi lại yêu cầu cũ |
| GET | `/api/owner/invoices/{invoice}/payments` | Lịch sử thu của hóa đơn |
| GET | `/api/owner/payments` | Danh sách các lần thu, lọc/phân trang |
| GET | `/api/owner/payments/{payment}` | Chi tiết lần thu |

Ví dụ chủ trọ đã nhận 1 triệu tiền mặt cho hóa đơn 42 còn nợ 3 triệu:

```json
{
  "amount": "1000000.00",
  "paid_at": "2026-10-19 10:00:00",
  "payment_method": "cash",
  "idempotency_key": "d8a1554f-815e-42aa-a838-5d9887e79f45"
}
```

Thay ID/thời gian bằng dữ liệu thực tế:

- `amount`: lớn hơn 0, tối đa 16 chữ số nguyên và 2 chữ số thập phân. Nên gửi chuỗi, không dùng dấu phân cách hàng nghìn.
- `paid_at`: đúng `Y-m-d H:i:s` theo `Asia/Ho_Chi_Minh`, từ thời điểm phát hành hóa đơn trở đi và không ở tương lai.
- `payment_method`: chỉ `cash` hoặc `bank_transfer`.
- `idempotency_key`: mã riêng của lần thu, nên dùng UUID. Cần 8–64 ký tự ASCII, bắt đầu bằng chữ/số, phần còn lại gồm chữ, số, `.`, `_`, `:`, `-`. Có phân biệt hoa/thường và dùng chung toàn hệ thống.

Người/thời điểm ghi nhận lấy từ phiên đăng nhập/máy chủ. Không gửi `invoice_id`, `recorded_by`, `recorded_at`, `payment_status`, `outstanding_amount` để ghi đè. Hóa đơn được lấy từ URL.

## Kết quả và số còn nợ

Lần đầu thành công trả **201**. Ví dụ response rút gọn:

```json
{
  "replayed": false,
  "data": {
    "id": 15,
    "receipt_number": "PT-15",
    "invoice_id": 42,
    "amount": "1000000.00",
    "payment_method": "cash"
  },
  "invoice": {
    "id": 42,
    "total_amount": "3000000.00",
    "paid_amount": "1000000.00",
    "outstanding_amount": "2000000.00",
    "payment_status": "partially_paid"
  }
}
```

`data` thực tế còn có mã hóa đơn, tháng, phòng/nhóm, `paid_at`, `idempotency_key`, `recorded_by`, `recorded_at`. `invoice` chứa chi tiết hóa đơn và các lần thu. `PT-{id}` là mã hiển thị từ ID, không phải tệp phiếu thu/PDF.

Thu nốt 2 triệu bằng `bank_transfer` với mã mới đưa còn nợ về `0.00`, trạng thái `paid`. Chủ trọ và người thuê được cấp quyền thấy công nợ mới khi đọc hóa đơn. Người thuê không nhận mã chống trùng và người ghi nhận trong lịch sử thanh toán.

Hóa đơn sau trả/chuyển phòng vẫn thu nợ được. Thu tiền không mở lại đợt thuê, sửa dòng hóa đơn hoặc tự ghi thêm khoản thu trong `cash_transactions`, tránh ghi doanh thu hai lần. Tổng hóa đơn và tổng thu được cộng riêng, tránh nhân tiền khi có nhiều dòng.

Các lần thu được tổng hợp cùng khoản thu khác/chi phí trong [sổ thu chi](cash-book.md). Sổ lấy ngày thu thực tế `paid_at`, không lấy tháng tính tiền của hóa đơn và không cộng hóa đơn chưa thu.

## Gửi lại yêu cầu và bảo vệ số dư

- Mỗi lần nhận tiền thực tế dùng một khóa riêng. Khi gửi lại do lỗi mạng/chưa có phản hồi, giữ **nguyên khóa và toàn bộ payload**, gồm thời điểm thu; không tạo khóa mới cho cùng một lần thu đang chờ kết quả.
- Cùng khóa, cùng hóa đơn, số tiền, thời điểm, hình thức và người ghi nhận: **200**, `replayed=true`, cùng ID cũ, không cộng thêm. `1` và `1.00` được coi là cùng số tiền. Vẫn gửi lại được sau khi hóa đơn đã thanh toán đủ.
- Cùng khóa nhưng khác thông tin/người ghi nhận: **409**. Hai khóa khác nhau được coi là hai lần thu riêng, kể cả cùng số tiền.
- Thu vượt còn nợ, hóa đơn đã đủ tiền/tổng bằng 0, không có dòng tính tiền hoặc đang có lịch sử thu vượt: **409**, không lưu thêm.
- Trường hoặc thời gian không hợp lệ: **422**. Chưa đăng nhập/tài khoản bị khóa: **401**; sai vai trò: **403**; ID không tồn tại: **404**; thiếu/sai CSRF khi ghi: **419**.

Backend khóa bản ghi hóa đơn trong transaction, đọc các dòng tiền/lần thu hiện tại, tính chính xác bằng số thập phân rồi ghi. Các lần thu cùng hóa đơn được xử lý lần lượt và kiểm tra lại số dư; khóa duy nhất cùng kiểm tra payload xử lý tranh chấp cùng mã yêu cầu.

Các lần thu là bản ghi đã xác nhận. API không sửa/xóa hoặc hoàn tiền phiếu thu. Đã có điều chỉnh tăng/giảm và hủy/khôi phục có lịch sử; chỉ hủy hóa đơn chưa có phiếu thu, tổng sau điều chỉnh không thấp hơn số đã thu. Xem `invoice-controls.md`. Không có tích hợp thanh toán trực tuyến.

## Lọc lịch sử

GET hỗ trợ `invoice_id`, `room_id`, `rental_group_id`, `payment_method`, `paid_from`, `paid_until`, `page`, `per_page` (mặc định 15, tối đa 100). Hai mốc thu dùng `Y-m-d H:i:s`, bao gồm cả đầu/cuối; nếu gửi cả hai thì mốc cuối từ mốc đầu trở đi.

Sắp theo thời điểm thu mới nhất rồi ID mới nhất, response có `data`, `links`, `meta`. Đường dẫn lịch sử theo hóa đơn luôn giữ phạm vi trên URL, không thể đổi hóa đơn bằng query `invoice_id`. Lọc hóa đơn theo `unpaid`, `partially_paid`, `paid` dùng [API hóa đơn](meter-readings-invoices.md).

## Bổ sung đại diện cho đợt thuê đã đóng

Đợt đã trả phòng mà chưa từng chọn đại diện có thể được chủ trọ xác nhận bổ sung từ lịch sử cư trú:

```http
POST /api/owner/occupancies/{occupancy}/billing-representative
```

```json
{"tenant_stay_id": 88}
```

Lấy ID lần cư trú từ `members` trong chi tiết đợt thuê; đây không phải ID tài khoản/hồ sơ. Chỉ chọn người còn cư trú tại thời điểm kết thúc. Backend lấy khoảng từ mốc muộn nhất giữa nhận phòng và vào ở của người được chọn, đến lúc kết thúc thuê; không nhận ngày tùy ý.

Chỉ áp dụng khi đợt thuê đã đóng, chưa có hóa đơn và chưa từng có lịch sử đại diện. Có lịch sử/hóa đơn rồi hoặc đợt còn mở thì trả 409; sai thành viên hoặc đã rời trước lúc kết thúc thì trả 422. Đợt còn mở dùng chức năng chọn/đổi đại diện hiện có.

Thành công trả 201 với khoảng đại diện đã kết thúc, giữ nguyên lịch sử cư trú và đợt thuê. Chủ trọ chọn rõ người cần bổ sung; backend không tự chọn hoặc tạo tài khoản/quyền xem. Sau đó xem trước/phát hành hóa đơn. Nếu chưa có tài khoản hợp lệ, hóa đơn vẫn lưu cho chủ trọ kèm cảnh báo và chưa cấp quyền người thuê. Có thể tạo tài khoản và cấp quyền hóa đơn riêng sau này.

Luồng: nhận phòng → chuyển/trả phòng nếu có → bổ sung đại diện khi cần → ghi đủ chỉ số → xem trước/phát hành → ghi nhận các lần thu → kiểm tra công nợ.

## Kiểm thử

```powershell
php artisan test --filter=InvoicePaymentTest
php artisan test --filter=BillingRepresentativeTest
php artisan test
php artisan rental:verify-database
```

PHPUnit dùng SQLite trong bộ nhớ. Các ca gồm thu một phần/thu đủ, cộng tiền lẻ, gửi lại/xung đột khóa, thu vượt, dữ liệu sai, CSRF/phân quyền, công nợ người thuê và luồng hoàn tất hóa đơn cho phòng một người không có tài khoản.
