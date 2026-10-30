# Nhập chỉ số nhiều phòng

Chức năng chủ trọ xem nhiều đợt thuê/khoảng áp dụng dịch vụ điện nước trong cùng một tháng, nhập chỉ số thực tế và xác nhận lưu từng dòng. Không phát hành hóa đơn tự động. Các quy tắc của màn hình một phòng vẫn được dùng nguyên vẹn.

## Danh sách

`GET /api/owner/meter-readings/bulk?billing_month=2026-01` nhận thêm `room_id`, từ khóa `q` theo số phòng, `occupancy_service_id`, `page`, `per_page` (1–50). Chọn tháng bắt buộc. Mỗi dòng là một khoảng dịch vụ tính theo tiêu thụ chồng lên thời gian thuê trong tháng, gồm cả đợt đã trả phòng nếu còn kỳ cần bổ sung.

Dữ liệu dòng gồm phòng/nhóm/dịch vụ/đơn giá và khoảng áp dụng, thời gian thuê, kỳ đầu/cuối gợi ý, chỉ số trước, lịch sử kỳ trong tháng, hóa đơn khóa kỳ và `context_token`. `previous_reading: null`, `baseline_known: false` nghĩa là phải nhập chỉ số đầu thực tế; hệ thống không tự giả định bằng 0. Nếu có kỳ liền trước thì dùng chỉ số cuối đã ghi và đánh dấu khóa ô đầu kỳ.

Khoảng gợi ý cắt theo tháng, lúc nhận/trả phòng, áp dụng/ngừng dịch vụ và hiện tại. Kỳ tháng đã phát hành hóa đơn bị khóa. Khi còn thiếu kỳ trước tháng đã chọn hoặc đã có kỳ mới hơn, trả `can_record: false`, `blocked_reason`/`blockers` để hướng dẫn đối chiếu; không tự nhảy qua phần lịch sử thiếu.

Đổi giá giữa tháng hoặc đổi đợt thuê có thể tạo nhiều dòng cho cùng số phòng. Phải xử lý đúng thứ tự các khoảng của cùng đồng hồ; sau khi lưu khoảng trước cần tải lại dòng sau để chốt chỉ số đầu và phiên bản mới.

Với hai khoảng giá liền nhau, khoảng mới bị chặn khi khoảng ngay trước chưa có chỉ số kết thúc đúng thời điểm đổi giá, kể cả khi chưa ghi chỉ số nào. Cần ghi đủ khoảng trước rồi tải lại. Khoảng ngừng dịch vụ thực sự có thời gian gián đoạn vẫn giữ quy tắc nhập mốc đầu thực tế của màn hình cũ.

## Ghi từng dòng

`POST /api/owner/meter-readings/bulk/rows` yêu cầu chủ trọ đang hoạt động, cookie và CSRF. Nội dung:

```json
{
  "billing_month": "2026-01",
  "occupancy_id": 12,
  "occupancy_service_id": 34,
  "period_start": "2026-01-01 00:00:00",
  "period_end": "2026-02-01 00:00:00",
  "previous_reading": "100.125",
  "current_reading": "140.250",
  "context_token": "<64 ký tự từ dòng vừa xem>",
  "idempotency_key": "<mã mới cho đúng dòng/thao tác này>"
}
```

Mỗi dòng có giao dịch riêng. Một dòng sai không hủy các dòng khác đã lưu; giao diện phải báo rõ kết quả từng dòng. API vẫn chặn chỉ số giảm/âm/vượt độ chính xác, kỳ sai thứ tự/trùng, khoảng chưa mở hoặc vượt thời gian thuê/áp dụng giá và kỳ đã đưa vào hóa đơn.

Phiên bản bao gồm trạng thái đợt thuê, các khoảng giá, chỉ số trước và hóa đơn trong tháng. Sau khi dữ liệu nền đổi, 409 yêu cầu tải lại đúng dòng để xem xét trước khi gửi yêu cầu mới. Token không phụ thuộc mỗi giây đồng hồ nên biểu mẫu của tháng hiện tại không tự hết hạn chỉ vì thời gian trôi qua.

## Gửi lại sau lỗi mạng

Lưu bản ghi chỉ số và `meter_reading_requests` cùng giao dịch. Mã yêu cầu gắn tài khoản và toàn bộ nội dung ban đầu đã chuẩn hóa; gửi lại chính xác được đối chiếu trước khi kiểm tra token cũ, trả 200 với `replayed: true`. Cùng mã nhưng nội dung/tài khoản khác trả 409.

Phản hồi gồm `data` (chỉ số hiện tại hoặc null), `reading_id` gốc, `reading_deleted`, `reading_changed`, `replayed` và `message`. Nếu chỉ số về sau được sửa/xóa hợp lệ qua màn hình cũ, gửi lại yêu cầu cũ chỉ trả trạng thái hiện tại, không ghi đè hoặc tạo lại chỉ số đã xóa. ID gốc và nội dung yêu cầu vẫn được giữ. Lần ghi mới thành công trả 201.

## Dữ liệu và khóa

Migration `2026_10_28_000002_create_meter_reading_requests.php` thêm bảng chống trùng, không chỉnh sửa các chỉ số/hóa đơn hiện có. FK chỉ số được đặt null khi xóa hợp lệ; `original_reading_id` giữ danh tính gốc. Không rollback migration khi có dữ liệu yêu cầu.

Thứ tự khóa dùng chung với ghi chỉ số/hóa đơn: phòng → nhóm → đợt thuê → các khoảng dịch vụ/chỉ số → hóa đơn tháng. Không đổi quy tắc tính tiêu thụ, khóa kỳ hoặc dữ liệu dòng hóa đơn cũ. Người thuê không có quyền vào API nhập nhiều phòng.
