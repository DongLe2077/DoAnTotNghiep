# Chống gửi trùng thu khác/chi phí

POST `/api/owner/cash-transactions` yêu cầu `idempotency_key` cùng dữ liệu thu chi. Chủ trọ cần đăng nhập, tài khoản hoạt động và CSRF như trước.

```json
{
  "idempotency_key": "c73c2207-432f-4f53-adbc-85df72ae2010",
  "transaction_type": "expense",
  "amount": "250000.00",
  "occurred_at": "2026-10-20 09:00:00",
  "description": "Chi sửa đường ống",
  "reason": "Ghi nhận chi phí mua vật tư"
}
```

Thời gian trong ví dụ cần thay bằng thời điểm thực tế, không ở tương lai. Mỗi khoản mới phải có mã mới, nên dùng UUID. Mã cần 8–64 ký tự ASCII gồm chữ, số hoặc `. _ : -`, bắt đầu bằng chữ/số; phân biệt chữ hoa và chữ thường. Thiếu/sai mã trả 422. PUT/PATCH sửa khoản không nhận mã này, vẫn dùng `version_token`.

| Tình huống | Kết quả |
| --- | --- |
| Mã chưa dùng, dữ liệu hợp lệ | 201, `replayed=false`, tạo một khoản và một nhật ký tạo |
| Cùng mã, người tạo và dữ liệu gốc | 200, `replayed=true`, trả đúng khoản đã lưu |
| Cùng mã nhưng đổi người tạo hoặc nội dung | 409, không tạo thêm khoản/nhật ký |
| Hai khoản cố ý có nội dung giống nhau nhưng mã khác | Tạo được hai khoản riêng |
| Gửi lại sau sửa/xóa/hoàn tác | Trả trạng thái hiện tại của khoản gốc, không tạo lại hoặc tự khôi phục |

So sánh dữ liệu gốc gồm `transaction_type`, `amount`, `occurred_at`, `description`, `reason` và người tạo do phiên đăng nhập xác định. Số tiền được chuẩn hóa thành chuỗi 2 chữ số thập phân: `10.1`, `10.10` và `00010.10` có cùng giá trị. `reason` bỏ qua hoặc null tương đương; nội dung đi qua quy tắc chuẩn hóa chuỗi của Laravel. Mã yêu cầu có phạm vi duy nhất toàn hệ thống; chủ trọ khác không dùng lại mã để lấy khoản của người tạo trước.

Ví dụ khoản được tạo với số tiền 250.000 đồng rồi sửa thành 260.000 đồng: gửi lại POST ban đầu với mã cũ trả cùng ID và số tiền hiện tại 260.000 đồng. Gửi mã cũ kèm số tiền 260.000 đồng trả 409 vì đó không phải yêu cầu tạo ban đầu. Nếu khoản đã xóa, response vẫn có `is_deleted=true`; muốn khôi phục cần thao tác hoàn tác rõ ràng qua [nhật ký thu chi](audit-photos-notifications.md#nhật-ký-và-hoàn-tác-thu-chi).

## CSDL và tính nhất quán

Migration `2026_10_20_000004_create_cash_creation_requests` thêm bảng `cash_creation_requests`, không thay đổi bản ghi cũ. Bảng giữ mã, ID khoản, người tạo, dữ liệu yêu cầu gốc, dấu kiểm tra và thời gian tạo. Mã dùng ASCII binary trên MySQL và unique index; mỗi khoản liên kết tối đa một yêu cầu tạo.

Khoản thu chi, nhật ký tạo và mã yêu cầu ghi trong cùng transaction. Khi một phần thất bại, cả ba được rollback; gửi lại có thể thực hiện lại an toàn. Khi có tranh chấp, unique index, khóa đọc và xử lý retry bảo đảm không giữ lại khoản/nhật ký của giao dịch thua. Gửi lại không sinh nhật ký mới và không làm tăng sổ thu chi.

Yêu cầu gốc giữ nguyên sau sửa/xóa/hoàn tác. Không xóa mã chống trùng khi xóa khoản: việc xóa khoản là xóa có thể khôi phục và mã vẫn phải nhận diện được lần gửi trước. Không trả mã, nội dung kỹ thuật của yêu cầu hoặc dấu kiểm tra qua API danh sách/chi tiết/nhật ký/sổ thu chi.

Các khoản có từ trước nâng cấp vẫn sử dụng được và không bị gán mã tùy ý. Không suy đoán hai khoản là trùng chỉ vì cùng số tiền/nội dung. Các API tạo mới từ phiên bản này cần được cập nhật để gửi mã.

## Quy tắc cho frontend

1. Tạo mã một lần khi chuẩn bị gửi một khoản mới; giữ cả mã và nội dung ban đầu trong lúc chưa xác định kết quả.
2. Khóa nút khi đang gửi. Nếu mất mạng hoặc hết thời gian chờ, gửi lại **cùng mã và cùng dữ liệu**, kể cả thời điểm phát sinh; không tự tạo mã hoặc cập nhật thời gian sang lúc gửi lại.
3. Xử lý cả 201 và 200 là đã xác định được khoản; dùng `data.id` để cập nhật danh sách, không thêm một dòng mới chỉ vì nhận response lần nữa.
4. Khi 409, đối chiếu và giải thích dữ liệu đã thay đổi; không tự đổi sang mã mới để cố gửi thành công. Khi khoản gốc đã xóa, hiển thị trạng thái đã xóa thay vì tự tạo lại.
5. Với một khoản khác được người dùng xác nhận tạo mới, dùng mã mới. Không áp dụng cơ chế retry tự động tùy ý lên các thao tác hoàn tác/xóa/sửa khác.

## Kiểm tra

`CashIdempotencyTest` kiểm tra gửi lặp, chuẩn hóa tiền/lý do, thay từng trường, đổi người tạo, mã phân biệt hoa thường, dữ liệu cũ, gửi lại sau sửa/xóa/hoàn tác, quyền truy cập và lỗi ghi nhật ký/mã yêu cầu. Các kiểm thử API chạy trên SQLite trong bộ nhớ. Kiểm tra MySQL riêng bao gồm unique ASCII binary và tranh chấp giữa các tiến trình, tách khỏi dữ liệu đang sử dụng.

Kết quả: toàn bộ 306 kiểm thử/2.901 assertions đạt; 14 kiểm tra MySQL trên dữ liệu thử có rollback đạt và 20 bảng giữ nguyên. Kiểm tra đồng thời dùng CSDL MySQL thử riêng: 3 lượt cùng khóa/cùng nội dung cho một 201 và một 200 với cùng ID; 3 lượt cùng khóa/khác số tiền cho một 201 và một 409; hai khóa khác chữ hoa/thường tạo hai khoản. Mỗi tình huống chỉ giữ đúng số khoản/nhật ký/yêu cầu tương ứng; CSDL thử đã được dọn.
