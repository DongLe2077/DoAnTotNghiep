# Quản lý thu chi

Đã có API quản lý khoản thu khác/chi phí và sổ thu chi tổng hợp cho chủ trọ. Dữ liệu dùng hai nguồn hiện có:

- `payments`: tiền hóa đơn đã được chủ trọ xác nhận nhận, mỗi lần thu xuất hiện một lần.
- `cash_transactions`: khoản `other_income` (thu khác) hoặc `expense` (chi phí).

Phát hành hóa đơn chưa thu không làm tăng tổng thu. Tiền hóa đơn được lấy trực tiếp từ các lần thanh toán, không tự sao chép vào thu khác. Tổng hợp dựa vào ngày thu/ngày phát sinh, không dựa vào tháng ghi trên hóa đơn.

## Phân quyền và đường dẫn

Tất cả API bên dưới có tiền tố `/api/owner`, chỉ cho tài khoản `owner` đang hoạt động. Giữ cookie và gửi CSRF khi POST, PUT/PATCH, DELETE theo [hướng dẫn đăng nhập](authentication.md). Người thuê không có quyền truy cập sổ thu chi của chủ trọ.

| Phương thức | Đường dẫn | Chức năng |
| --- | --- | --- |
| GET | `/cash-transactions` | Danh sách thu khác/chi phí |
| POST | `/cash-transactions` | Tạo khoản thu khác hoặc chi phí |
| GET | `/cash-transactions/{cashTransaction}` | Chi tiết, kèm phiên bản hiện tại |
| PUT/PATCH | `/cash-transactions/{cashTransaction}` | Sửa thông tin khoản thu khác/chi phí |
| DELETE | `/cash-transactions/{cashTransaction}` | Xóa khoản thu khác/chi phí |
| GET | `/cash-flow` | Sổ tổng hợp tiền hóa đơn, thu khác và chi phí |
| GET | `/cash-flow/summary` | Tổng thu, tổng chi, chênh lệch và nhóm ngày/tháng |

## Thêm khoản thu hoặc chi

Ví dụ chi sửa khóa phòng:

```json
{
  "transaction_type": "expense",
  "amount": "150000.00",
  "occurred_at": "2026-10-19 09:00:00",
  "description": "Chi thay khóa phòng A101"
}
```

Thay thời điểm bằng thực tế. Dùng `other_income` cho thu khác. `amount` luôn là số dương, tối đa 16 chữ số nguyên và 2 chữ số thập phân; nên gửi chuỗi và không dùng dấu phân cách hàng nghìn. Loại khoản thu chi quyết định chiều thu/chi, không nhập số âm cho chi phí.

`occurred_at` đúng `Y-m-d H:i:s` theo `Asia/Ho_Chi_Minh`, không ở tương lai. `description` bắt buộc, tối đa 255 ký tự. Người/thời điểm ghi nhận lấy từ phiên đăng nhập/máy chủ. Không gửi `recorded_by`, `recorded_at`, `invoice_id` hoặc `payment_id` để ghi đè/liên kết tiền hóa đơn.

POST thành công trả 201 với `data`: `id`, `transaction_type`, `amount`, `occurred_at`, `description`, `recorded_by`, `recorded_at`, `version_token`. Mỗi POST hợp lệ tạo một khoản mới; bảng hiện có không lưu mã chống gửi trùng cho khoản thu chi. Giao diện gọi API cần tránh gửi POST lặp khi người dùng đang chờ phản hồi và tra cứu lại trước khi tạo lại khoản chưa rõ kết quả.

Thu tiền hóa đơn dùng [API thu tiền hóa đơn](invoice-payments.md), có mã chống gửi trùng riêng; không nhập lại lần thu đó như một khoản `other_income`.

## Sửa và xóa

Lấy `version_token` từ GET hoặc response tạo/sửa gần nhất. Ví dụ PATCH:

```json
{
  "version_token": "<64 ký tự nhận được từ API>",
  "amount": "160000.00",
  "description": "Chi thay khóa và làm chìa phòng A101"
}
```

PUT/PATCH chỉ cần gửi trường muốn thay đổi cùng token. Sửa thành công trả 200 và token của dữ liệu mới; người/thời điểm ghi nhận gốc được giữ lại. Có thể đổi giữa `other_income` và `expense` khi sửa một khoản đã nhập sai.

DELETE gửi JSON:

```json
{"version_token":"<token hiện tại>"}
```

Thành công trả 204. Thiếu/sai định dạng token trả 422. Token thuộc khoản khác hoặc dữ liệu đã thay đổi trả 409; cần đọc lại trước khi sửa/xóa. Backend kiểm tra phiên bản trong transaction có khóa bản ghi để tránh hai phiên làm việc ghi đè nhau.

Chỉ khoản trong `cash_transactions` được sửa/xóa qua chức năng này; bản ghi thu hóa đơn giữ nguyên theo quy tắc [thu tiền hóa đơn](invoice-payments.md). Đã bổ sung xóa có thể khôi phục, nhật ký trước/sau, người thao tác và API hoàn tác; xem [nhật ký và hoàn tác](audit-photos-notifications.md#nhật-ký-và-hoàn-tác-thu-chi). Khoản đã xóa không tính vào sổ/tổng hợp; khôi phục sẽ được tính lại.

## Lọc danh sách

`GET /cash-transactions` hỗ trợ:

| Tham số | Ý nghĩa |
| --- | --- |
| `transaction_type` | `other_income` hoặc `expense` |
| `month` | Tháng `YYYY-MM` |
| `from`, `until` | Mốc phát sinh `Y-m-d H:i:s`, bao gồm cả hai đầu |
| `q` | Tìm trong nội dung |
| `page`, `per_page` | Trang và số bản ghi, mặc định 15, tối đa 100 |

Chọn tháng **hoặc** khoảng từ/đến; gửi cả hai cách bị trả 422. Có thể chỉ gửi một mốc của khoảng thời gian. Nếu gửi cả hai mốc, cuối phải từ đầu trở đi. Danh sách khoản thu chi không gửi mốc thời gian sẽ đọc toàn bộ thời gian và phân trang.

Kết quả sắp theo thời điểm phát sinh mới nhất, rồi ID mới nhất, có `data`, `links`, `meta`. Tìm kiếm coi `%`, `_`, `!` là ký tự nội dung, không phải ký tự đại diện.

## Sổ thu chi tổng hợp

`GET /cash-flow` hỗ trợ `month`, `from`, `until`, `q`, `page`, `per_page`, và `entry_type`:

- `invoice_payment`: tiền đã thu của hóa đơn.
- `other_income`: khoản thu khác.
- `expense`: chi phí.

Nếu không gửi bộ lọc thời gian, sổ tổng hợp và API summary mặc định **tháng hiện tại**. `q` tìm nội dung khoản thu chi hoặc mã hóa đơn. Các bộ lọc được áp dụng trước khi phân trang/tổng hợp.

Một dòng sổ có `id` dạng `payment:15` hoặc `cash_transaction:15` để phân biệt hai nguồn trùng ID, cùng `source`, `source_id`, `entry_type`, `direction`, `amount`, `signed_amount`, `occurred_at`, `description`, thông tin ghi nhận. `signed_amount` âm cho chi phí, dương cho tiền thu; `amount` vẫn dương.

Dòng từ thanh toán có thêm `invoice_id`, `invoice_number`, `payment_method`, với `can_edit=false`, `can_delete=false`. Dòng thu khác/chi phí có hai cờ này bằng true; muốn sửa/xóa phải đọc chi tiết khoản tương ứng để lấy token. Sổ không trả khóa chống trùng của thanh toán hoặc thông tin tài khoản nhạy cảm.

Sổ và summary chỉ đọc hai nguồn. Không tạo bản ghi thu chi khi đọc báo cáo; mỗi lần thu vẫn chỉ tính một lần dù hóa đơn có nhiều dòng.

## Tổng hợp theo ngày/tháng

Ví dụ:

```http
GET /api/owner/cash-flow/summary?month=2026-10&group_by=day
```

`group_by` nhận `day` hoặc `month` (mặc định `month`). Các bộ lọc thời gian, `entry_type`, `q` giống sổ tổng hợp. Kết quả:

| Trường | Ý nghĩa |
| --- | --- |
| `from`, `until`, `group_by`, `entry_type`, `q` | Phạm vi đang áp dụng |
| `invoice_payment_total` | Tiền hóa đơn thực tế đã thu |
| `other_income_total` | Tổng thu khác |
| `expense_total` | Tổng chi phí |
| `total_income` | Tiền hóa đơn đã thu + thu khác |
| `net_cash_flow` | Tổng thu − tổng chi |
| `invoice_payment_count`, `other_income_count`, `expense_count`, `entry_count` | Số khoản theo nguồn và tổng số |
| `periods` | Các nhóm thời gian có phát sinh, sắp từ cũ đến mới, với cùng trường tổng/count |

Ví dụ đã thu hóa đơn 1.500.000, thu khác 200.000 và chi 300.000 thì tổng thu `1700000.00`, chênh lệch `1400000.00`. Hóa đơn còn nợ không được tính thêm vào thu thực tế. Nếu chỉ lọc chi phí thì tổng thu bằng 0 và chênh lệch âm. Không có phát sinh thì các tổng là `0.00`, các count là 0 và `periods=[]`.

`net_cash_flow` là chênh lệch thu chi trong phạm vi lọc, không phải số dư quỹ có số dư đầu kỳ hoặc báo cáo lợi nhuận kế toán. Backend cộng bằng số thập phân chính xác; response tiền luôn là chuỗi có 2 số thập phân. Nhóm theo ngày/tháng phát sinh, không tạo thêm các nhóm trống.

## Kiểm thử

```powershell
php artisan test --filter=CashBookManagementTest
php artisan test
php artisan rental:verify-database
```

PHPUnit dùng SQLite trong bộ nhớ. Các ca kiểm tra thêm/sửa/xóa, dữ liệu sai, xung đột phiên bản, quyền/CSRF, lọc ngày/tháng, tiền lẻ, chênh lệch âm, tiền thu thực tế và chống nhân tổng khi hóa đơn có nhiều dòng. Phần nhật ký/hoàn tác dùng migration mở rộng được nêu trong tài liệu liên kết phía trên.
