# Tổng quan và công nợ

Các API chỉ dành cho `owner` đang hoạt động, dùng cookie session hiện có. Chỉ đọc dữ liệu, không thêm bảng hoặc migration.

| Phương thức | Đường dẫn | Nội dung |
| --- | --- | --- |
| GET | `/api/owner/dashboard?month=2026-03` | Tổng quan phòng, người đang ở, sửa chữa, hóa đơn, công nợ và thực thu/chi |
| GET | `/api/owner/receivables` | Hóa đơn còn thiếu tiền, phân trang, tháng cũ trước |
| GET | `/api/owner/receivables/summary` | Tổng hợp trạng thái và tiền hóa đơn trong phạm vi lọc |
| GET | `/api/owner/receivables/groups?group_by=room` | Công nợ gộp theo phòng, số tiền thiếu nhiều trước |
| GET | `/api/owner/receivables/groups?group_by=rental_group` | Công nợ theo nhóm, kể cả sau khi chuyển phòng |

## Trang tổng quan

`month` là `YYYY-MM`, mặc định tháng hiện tại theo `Asia/Ho_Chi_Minh`. Response `data` gồm:

- `month`, `as_of`: tháng chọn và thời điểm đọc.
- `rooms`: tổng phòng, `occupied_count`, `vacant_count`, `maintenance_count`, số người ở, sức chứa, đợt thuê đang mở và `available_for_check_in_count`.
- `tenants`: tổng hồ sơ và số người đang cư trú.
- `maintenance`: tổng yêu cầu, mới gửi/đang xử lý/đã hoàn thành và `open_count`.
- `invoices`: hóa đơn có **tháng tính tiền** bằng tháng chọn, số đã thu/còn thiếu tính đến hiện tại.
- `receivables`: thống kê hóa đơn **mọi tháng**, giữ cả khoản nợ sau khi trả/chuyển phòng.
- `cash_flow`: dùng lại [báo cáo thu chi](cash-book.md), lọc theo **thời điểm thực thu/chi** trong tháng chọn.
- `monthly_series`: tối đa 6 tháng liên tiếp, kết thúc ở tháng chọn, sắp tăng dần; tháng không phát sinh vẫn có dòng 0. Ở giới hạn `1000-01`, chuỗi bắt đầu từ tháng hợp lệ đầu tiên. Mỗi dòng gồm `month`, `invoice_count`, `inconsistent_count`, `invoice_total`, `invoice_paid`, `invoice_outstanding`, `cash_income`, `cash_expense`, `cash_net`. Tiền luôn là chuỗi thập phân; phần hóa đơn dùng tháng tính tiền với số đã thu/còn thiếu tính đến hiện tại; dòng tiền dùng ngày phát sinh thật. Tiền hóa đơn `inconsistent` bị loại; khoản thu chi đã xóa không tham gia dòng tiền. Tổng hợp đọc cùng transaction với các số liệu tổng quan, không thay đổi bảng hoặc dữ liệu.

Giao diện chủ trọ có biểu đồ tròn trạng thái hóa đơn của kỳ chọn, biểu đồ cột 6 tháng chuyển giữa **Hóa đơn** và **Thu / chi**, và thanh phân bố tình trạng phòng hiện tại. Bấm trạng thái để lọc danh sách; bấm cột để xem tiền chính xác và mở danh sách đúng tháng. Biểu đồ rỗng thể hiện không có dữ liệu, không dựng số minh họa. Chiều cao chỉ là tỷ lệ hình học; số tiền hiển thị giữ chuỗi thập phân gốc, không cộng bằng số thực trong trình duyệt.

Ví dụ hóa đơn tháng 1 là 1.000.000 đồng, thu 400.000 đồng trong tháng 3: khi chọn tháng 3, hóa đơn tháng 3 không cộng hóa đơn này; thực thu tháng 3 tăng 400.000 đồng; tổng công nợ mọi tháng còn 600.000 đồng. Khi chọn tháng 1, số đã thu của hóa đơn vẫn tính đến hiện tại, không phải tại cuối tháng 1.

Số phòng, người ở, sửa chữa luôn là **hiện tại**, không đổi theo tháng chọn. View `room_occupancy_summary` xếp phòng bảo trì vào `maintenance` kể cả còn người ở; `occupied_count` không bao gồm các phòng đó, còn `resident_count` vẫn tính người của chúng. `vacant_count` có thể chứa phòng không còn người nhưng chưa đóng đợt thuê; dùng `available_for_check_in_count` để biết số phòng sẵn sàng nhận khách. API nhận phòng vẫn kiểm tra lại khi thao tác.

## Bộ lọc công nợ

Các API `receivables` nhận `billing_month=YYYY-MM`, `room_id`, `occupancy_id`, `rental_group_id`, `page`, `per_page` (1–100, mặc định 15). Không truyền tháng thì lấy mọi tháng. Bộ lọc kết hợp AND; ID không có dữ liệu trả báo cáo rỗng.

`group_by` quyết định cách gộp tại `/groups`: `room` (mặc định) hoặc `rental_group`. Mỗi dòng có `group_by`, `group_id`, `group_label`, `invoice_count`, `total_amount`, `paid_amount`, `outstanding_amount`.

Danh sách/nhóm chỉ lấy hóa đơn `unpaid` hoặc `partially_paid` có số thiếu dương. Tiền trong từng nhóm chỉ thuộc các hóa đơn đang nợ đó. Hóa đơn đã trả, tổng bằng 0 hoặc `inconsistent` không xuất hiện trong danh sách này. Chi tiết hóa đơn và các lần thu dùng `/api/owner/invoices/{id}`.

`/summary` thống kê **mọi hóa đơn trong phạm vi lọc**, gồm cả hóa đơn đã trả:

- `invoice_count`, `valid_invoice_count`, `unpaid_count`, `partially_paid_count`, `paid_count`, `inconsistent_count`, `open_invoice_count`.
- `outstanding_room_count`, `outstanding_group_count`: số phòng/nhóm có công nợ hợp lệ.
- `total_amount`, `paid_amount`, `outstanding_amount`: tiền của hóa đơn hợp lệ, chuỗi thập phân 2 chữ số.
- `filters`: phạm vi áp dụng; phân trang không giới hạn tổng hợp.

Hóa đơn thiếu dòng hoặc thu vượt được view đánh dấu `inconsistent`: đếm riêng và loại tiền khỏi tổng hợp để chủ trọ đối soát. Xem bằng `/api/owner/invoices?payment_status=inconsistent`. Vì summary gồm cả hóa đơn đã trả, `total_amount`/`paid_amount` có thể khác tổng tương ứng tại `/groups`; `outstanding_amount` khớp khi cùng bộ lọc và dữ liệu chưa đổi giữa các lần đọc.

Không phân bổ nợ của nhóm thành nợ riêng từng thành viên, không nhân tiền theo số người, không tính lặp khi có nhiều dòng/nhiều lần thu. Đã bổ sung hạn đóng và công nợ quá hạn (23/10/2026): chỉ hóa đơn còn nợ hợp lệ, có hạn trước hôm nay, chưa hủy. Hóa đơn cũ chưa đặt hạn không bị tính quá hạn. Xem `invoice-controls.md`. Hóa đơn đã phát hành không tự là tiền thực thu; chênh lệch thu chi không phải lợi nhuận kế toán.

Kiểm thử: `php artisan test --filter=OwnerReportingTest`.
