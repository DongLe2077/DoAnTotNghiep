# Chi phí sửa chữa

Chức năng dành cho chủ trọ, mở rộng trên sổ thu chi hiện có. Người thuê vẫn xem nội dung, ảnh và lịch sử xử lý sửa chữa theo quyền cũ; không được đọc chi phí nội bộ hoặc API liên kết khoản chi.

## Quy tắc

- Một yêu cầu sửa chữa có thể liên kết nhiều khoản chi; mỗi khoản chi chỉ có một liên kết đang hoạt động với một yêu cầu.
- Chỉ liên kết khoản `expense` đang còn hiệu lực. Chọn khoản đã có hoặc tạo khoản chi qua API thu chi hiện tại rồi liên kết; hoàn tất yêu cầu không tự tạo chi phí.
- Liên kết không sao chép số tiền, không tạo thêm dòng tiền. Tổng chi sửa chữa đọc từ chính các khoản chi gốc còn hiệu lực.
- Sửa tiền/nội dung khoản chi làm tổng trong sửa chữa cập nhật theo. Xóa mềm khoản chi vẫn giữ liên kết để đối chiếu nhưng loại số tiền khỏi tổng. Khôi phục khoản chi đưa số tiền vào tổng trở lại.
- Khi còn liên kết, không đổi loại khoản chi thành khoản thu, kể cả thông qua hoàn tác. Cần gỡ liên kết có lý do trước.
- Gỡ liên kết giữ nguyên khoản chi và các lịch sử đã ghi. Có thể gắn lại với cùng hoặc yêu cầu khác, nhưng cần phiên bản liên kết mới nhất.
- Mọi thao tác gắn/gỡ có lý do, người thực hiện, thời điểm, trước/sau và ảnh chụp thông tin khoản chi lúc thao tác. Thay đổi liên kết không tự ghi tiến độ sửa chữa hoặc gửi thông báo cho người thuê.

## API

Các đường dẫn dưới đây nằm sau `/api/owner`, yêu cầu tài khoản chủ trọ hoạt động, cookie phiên và CSRF cho POST.

| Phương thức | Đường dẫn | Kết quả |
| --- | --- | --- |
| GET | `/maintenance-requests/{id}/expenses` | Liên kết đang hoạt động, gồm khoản đã xóa mềm để đối chiếu; phân trang và tổng toàn bộ liên kết |
| POST | `/maintenance-requests/{id}/expenses` | Gắn khoản chi có sẵn |
| POST | `/maintenance-requests/{id}/expenses/{cashId}/unlink` | Gỡ liên kết, không xóa khoản chi |
| GET | `/maintenance-requests/{id}/expenses/history` | Lịch sử liên kết của yêu cầu, mới nhất trước |
| GET | `/cash-transactions/{cashId}/maintenance-link` | Liên kết hiện tại hoặc liên kết cũ chưa hoạt động, kèm phiên bản; `data: null` khi chưa từng liên kết |

Ví dụ gắn khoản chi:

```json
{
  "cash_transaction_id": 12,
  "cash_version_token": "<64 ký tự từ khoản chi vừa đọc>",
  "reason": "Chi phí thay van nước thực tế",
  "idempotency_key": "<mã mới cho thao tác này>"
}
```

Nếu khoản đã có liên kết cũ được gỡ, truyền thêm `version_token` từ `/cash-transactions/{cashId}/maintenance-link`. Để gỡ, truyền `version_token` của liên kết, `reason` và `idempotency_key`; không gửi số tiền. Lý do dài 3–1.000 ký tự sau khi bỏ khoảng trắng đầu/cuối.

Đọc danh sách trả `data`, `meta`, `links` và `summary: {total_amount, active_count, deleted_count}`. `total_amount` là chuỗi thập phân hai chữ số, tính toàn bộ danh sách theo cùng ảnh chụp CSDL; không chỉ trang đang xem. Từng liên kết có `cash_transaction`, `maintenance_request`, `included_in_total`, trạng thái và phiên bản.

Lịch sử có `action: linked|unlinked`, `before`, `after`, `reason`, `actor` và `created_at`. Ảnh chụp trước/sau gồm trạng thái liên kết và `cash_snapshot` chứa số tiền/nội dung/thời điểm/loại/phiên bản/trạng thái xóa được biết ở thời điểm thao tác. Chi tiết thay đổi khoản chi vẫn có trong nhật ký thu chi riêng.

## Chống trùng và xung đột

- Lưu liên kết và lịch sử cùng giao dịch. Lỗi ghi lịch sử hủy toàn bộ thay đổi liên kết, không lưu một phần.
- Mã yêu cầu thuộc nội dung, tài khoản, yêu cầu sửa chữa và thao tác cụ thể. Gửi lại chính xác trả `replayed: true` cùng ID lịch sử, không thêm sự kiện hoặc khoản tiền.
- Kiểm tra mã đã ghi nhận trước khi kiểm tra phiên bản hiện tại, nên thử lại sau mất phản hồi vẫn nhận diện được thao tác đã thành công dù khoản chi đã sửa/xóa/gỡ sau đó.
- Lượt gửi lại trả **trạng thái hiện tại** của liên kết, không tự khôi phục hành động cũ. Sau phản hồi luôn tải lại danh sách của yêu cầu đang xem.
- Đổi nội dung với cùng mã hoặc dùng phiên bản cũ trả 409. Cần đọc lại và kiểm tra trước khi tạo thao tác mới.
- Thứ tự khóa là khoản thu chi → bản ghi liên kết, dùng chung với kiểm soát đổi loại khoản thu chi.

## Nâng cấp và dữ liệu

Migration `2026_10_27_000001_create_maintenance_expense_links.php` chỉ thêm `maintenance_expense_links` và `maintenance_expense_histories`; không tự liên kết dữ liệu cũ dựa trên nội dung mô tả. Một hàng liên kết tồn tại xuyên suốt các lần gỡ/gắn lại, mỗi thay đổi tăng phiên bản và thêm lịch sử. Lịch sử cũng là bản ghi chống gửi trùng.

Không dùng `migrate:fresh`, reset hoặc rollback trên dữ liệu đang sử dụng. Migration từ chối rollback khi đã có liên kết/lịch sử để tránh mất dấu vết. Khi sao lưu cần giữ CSDL và ảnh tương ứng của sản phẩm.
