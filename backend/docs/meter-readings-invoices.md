# Chỉ số điện nước và hóa đơn

Đã triển khai API backend trên các bảng và view hiện có. Chủ trọ ghi chỉ số theo từng đợt thuê, xem trước tiền phòng/dịch vụ rồi phát hành hóa đơn tháng. Người thuê chỉ xem hóa đơn được cấp quyền riêng. Frontend chưa được xây dựng.

## Chuẩn bị và thứ tự thao tác

1. Đăng nhập bằng tài khoản `owner` đang hoạt động. Giữ cookie session và gửi header CSRF hiện tại cho POST, PUT, DELETE theo [hướng dẫn đăng nhập](authentication.md). Các API nhận/trả JSON.
2. Tạo hồ sơ, nhóm thuê, nhận phòng và áp dụng các dịch vụ thực tế theo [quản lý người thuê và quá trình thuê](tenant-occupancy.md). Mỗi khoảng áp dụng dịch vụ có ID riêng (`occupancy_service_id`), đơn giá, đơn vị và cách tính đã chốt.
3. Chọn người đại diện có tài khoản `tenant` đang hoạt động trước khi trả hoặc chuyển phòng. Hóa đơn cần liên kết tới một khoảng đại diện của đúng đợt thuê. Nếu đợt đã đóng mà chưa từng có đại diện, chủ trọ có thể bổ sung từ thành viên còn cư trú lúc kết thúc bằng [API bổ sung đại diện lập hóa đơn](invoice-payments.md#bổ-sung-đại-diện-cho-đợt-thuê-đã-đóng), rồi xem trước lại; không tự tạo tài khoản đăng nhập.
4. Ghi đủ chỉ số điện/nước đến cuối tháng, hoặc đến đúng thời điểm trả/chuyển phòng nếu kết thúc giữa tháng. Nếu trả/chuyển phòng, ghi mốc kết thúc thuê rồi bổ sung kỳ chỉ số cuối trong khoảng thuê đã đóng cũng được. Thao tác trả/chuyển phòng không tự tạo hóa đơn.
5. Xem trước hóa đơn, xử lý hết `blockers`, kiểm tra số tiền và phát hành với `preview_token` vừa nhận.

Các ID và thời gian trong ví dụ dưới đây chỉ minh họa, không phải dữ liệu/tài khoản đã được tạo. Dùng ID thực tế từ API đợt thuê. Thông tin danh mục và giá chung nằm trong [quản lý phòng và dịch vụ](rooms-services.md).

## Thời gian, tháng và độ chính xác

- Thời điểm dùng đúng dạng `Y-m-d H:i:s`, ví dụ `2026-01-01 00:00:00`, theo múi giờ `Asia/Ho_Chi_Minh`; không gửi thời điểm tương lai cho kỳ chỉ số.
- Khoảng thời gian có dạng `[bắt đầu, kết thúc)`: bao gồm đầu kỳ, không bao gồm cuối kỳ. Hai kỳ có thể tiếp giáp tại cùng một mốc.
- `billing_month` trong API dùng `YYYY-MM`, ví dụ `2026-01`. CSDL lưu ngày đầu tháng, còn response trả lại `YYYY-MM`.
- Chỉ số và lượng tiêu thụ có tối đa 12 chữ số nguyên, 3 chữ số thập phân. Chỉ số không âm và cuối kỳ không nhỏ hơn đầu kỳ. Gửi số thập phân dạng chuỗi để giữ đúng giá trị.
- Đơn giá có 4 chữ số thập phân; thành tiền từng dòng được làm tròn HALF_UP tới 2 chữ số. Backend tính thập phân chính xác và cộng các thành tiền đã làm tròn; các giá trị tiền/số lượng được trả dạng chuỗi.

## Ghi và tra cứu chỉ số

Các đường dẫn trong bảng sau có tiền tố `/api/owner`.

| Phương thức | Đường dẫn | Chức năng |
| --- | --- | --- |
| GET | `/occupancies/{occupancy}/meter-readings` | Danh sách chỉ số của đợt thuê |
| POST | `/occupancies/{occupancy}/meter-readings` | Ghi kỳ mới |
| PUT | `/occupancies/{occupancy}/meter-readings/{reading}` | Sửa toàn bộ dữ liệu kỳ cuối được phép sửa |
| DELETE | `/occupancies/{occupancy}/meter-readings/{reading}` | Xóa kỳ cuối được phép xóa, trả 204 |

Ví dụ `POST /api/owner/occupancies/42/meter-readings`, giả sử khoảng dịch vụ điện có ID 88, áp dụng từ đầu tháng 1:

```json
{
  "occupancy_service_id": 88,
  "period_start": "2026-01-01 00:00:00",
  "period_end": "2026-02-01 00:00:00",
  "previous_reading": "100.000",
  "current_reading": "140.125"
}
```

Response 201 gồm `id`, `occupancy_service_id`, snapshot `occupancy_service`, `billing_month`, đầu/cuối kỳ, chỉ số đầu/cuối, `consumption: "40.125"`, người/thời điểm ghi và `is_billed`, `invoice_id`. Hệ thống tự tính tiêu thụ và lấy người ghi từ phiên đăng nhập. Không gửi `consumption`, `recorded_by` hoặc `recorded_at` trong payload. API PUT yêu cầu đủ các trường như POST và không cho đổi `occupancy_service_id`; người/thời điểm ghi gốc được giữ lại.

Chỉ nhận dịch vụ có `billing_method=metered` thuộc đúng đợt thuê. Cả kỳ phải nằm trong thời gian thuê và khoảng áp dụng dịch vụ đã chọn. Một kỳ chỉ nằm trong một tháng; kết thúc đúng `00:00:00` ngày đầu tháng sau được chấp nhận. Nếu cần ghi qua nhiều tháng, tách tại từng mốc đầu tháng và dùng chỉ số thực tế ở từng mốc; backend không tự chia lượng tiêu thụ theo ngày.

Kỳ đầu cho phép nhập chỉ số nền thực tế, kể cả bắt đầu sau ngày áp dụng dịch vụ. Tuy nhiên, để phát hành hóa đơn, các kỳ phải phủ kín phần dịch vụ cần tính trong tháng: bắt đầu tại mốc muộn nhất giữa đầu tháng, ngày nhận phòng và ngày áp dụng khoảng giá; kết thúc tại mốc sớm nhất giữa đầu tháng sau, ngày trả/chuyển phòng và ngày hết khoảng giá. Nếu thiếu chỉ số nền hoặc thiếu đoạn đầu/cuối, xem trước hóa đơn trả `can_issue=false` thay vì tự ước lượng.

Kỳ tiếp theo của cùng khoảng dịch vụ phải có `period_start` bằng `period_end` kỳ trước và `previous_reading` bằng `current_reading` trước đó. Không chấp nhận kỳ trùng, ghi chen vào quá khứ hoặc để hở giữa hai kỳ. Nếu đổi phiên bản giá trong cùng đợt thuê và hai khoảng tiếp giáp, chỉ số được nối tiếp trên cùng đồng hồ. Khi chuyển sang phòng/đợt thuê mới, nhập chỉ số nền của đồng hồ phòng mới; không sao chép số của phòng cũ.

Chỉ sửa/xóa kỳ cuối cùng của cùng dịch vụ trong đợt thuê, bao gồm cả các phiên bản giá. Kỳ đã được dòng hóa đơn tham chiếu không được sửa/xóa. Khi một tháng của đợt thuê đã có hóa đơn, toàn bộ thao tác thêm/sửa/xóa chỉ số thuộc tháng đó bị khóa, kể cả chỉ số chưa xuất hiện trên dòng hóa đơn. Sửa kỳ từ tháng khác sang tháng đã phát hành cũng bị chặn.

GET hỗ trợ `occupancy_service_id`, `billing_month`, `page`, `per_page` (mặc định 15, tối đa 100), sắp xếp kỳ mới nhất trước. Ví dụ:

```http
GET /api/owner/occupancies/42/meter-readings?billing_month=2026-01&per_page=15
```

## Xem trước và phát hành hóa đơn

| Phương thức | Đường dẫn | Chức năng |
| --- | --- | --- |
| POST | `/api/owner/occupancies/{occupancy}/invoices/preview` | Tính bản xem trước, không lưu hóa đơn |
| POST | `/api/owner/occupancies/{occupancy}/invoices` | Phát hành hóa đơn dựa trên bản xem trước |

Gửi xem trước:

```json
{"billing_month":"2026-01"}
```

Response 200 có đối tượng `data` với các trường chính:

| Trường | Ý nghĩa |
| --- | --- |
| `occupancy_id`, `billing_month`, `period_start`, `period_end` | Đợt thuê, tháng và phần thời gian tính trong bản xem trước |
| `items` | Dòng tiền phòng và dịch vụ, nguồn giá/chỉ số/khoảng tính tiền |
| `total_amount` | Tổng thành tiền đã tính chính xác |
| `can_issue`, `blockers`, `warnings` | Được phát hành hay chưa, lý do chặn và thông tin cần lưu ý |
| `is_final_period` | Đã hết khoảng cần tính, có thể chốt kỳ về mặt thời gian |
| `existing_invoice_id` | Hóa đơn cùng đợt/tháng đã có, nếu có |
| `rent_source_occupancy_id` | Đợt thuê làm nguồn giá phòng tháng của nhóm |
| `rent_already_billed_invoice_id` | Hóa đơn đã tính tiền phòng tháng đó của nhóm, nếu có |
| `representative_assignment_id`, `recipient_user_id` | Khoảng đại diện gắn trên hóa đơn và tài khoản dự kiến nhận quyền xem |
| `preview_token` | Chuỗi 64 ký tự cần gửi nguyên vẹn khi phát hành |

`can_issue=false` thường do chưa hết kỳ, thiếu/hở/trùng chỉ số, không có đại diện hợp lệ, tháng đã có hóa đơn hoặc nguồn tính tiền đã dùng. Dữ liệu tiền vượt giới hạn CSDL cũng bị chặn. Chỉ xem phần tổng tiền chưa đủ để xác định có thể phát hành; phải kiểm tra `can_issue` và `blockers`.

Nếu đợt thuê còn mở trong tháng hiện tại, chỉ xem trước phần đã diễn ra và chưa được chốt hóa đơn cả tháng. Chờ hết tháng hoặc ghi nhận trả/chuyển phòng trước khi phát hành hóa đơn cuối của đợt đó. Không lập hóa đơn cho tháng tương lai hoặc tháng không giao với thời gian thuê.

Khi `can_issue=true`, gửi lại tháng và token thực nhận từ response trước:

```json
{
  "billing_month":"2026-01",
  "preview_token":"<thay bằng data.preview_token nhận được>"
}
```

Không gửi danh sách dòng tiền, tổng tiền, giá thuê, người/thời điểm phát hành hoặc đại diện để ghi đè kết quả. Backend tính lại trong transaction; nếu dữ liệu hoặc hóa đơn khác của cùng nhóm thay đổi kể từ lần xem trước, trả 409 và yêu cầu xem trước lại. Token không phải token đăng nhập, không thay cookie/CSRF.

Phát hành thành công trả 201 cùng chi tiết hóa đơn, mã dạng `HD-202601-42`, các dòng và công nợ. Mỗi đợt thuê chỉ có một hóa đơn cho một tháng; nguồn chỉ số không được tính lại ở hóa đơn khác. API hiện không sửa, xóa hoặc hủy hóa đơn đã phát hành. Không có hóa đơn nháp lưu trong CSDL; bản xem trước chỉ là kết quả tính toán.

## Cách tính tiền

**Tiền phòng:** tính nguyên giá thuê tháng đã chốt, một lần cho mỗi nhóm/tháng, kể cả nhận/trả phòng giữa tháng. Nếu cùng nhóm chuyển phòng trong tháng, giá lấy từ đợt thuê giao với tháng sớm nhất. Hóa đơn được phát hành đầu tiên cho nhóm/tháng nhận toàn bộ dòng tiền phòng; hóa đơn các đợt còn lại có dòng tiền phòng bằng 0 và ghi rõ hóa đơn đã tính trước. Điều này vẫn đúng khi lập hóa đơn phòng mới trước phòng cũ. Chính sách hiện tại không phân bổ tiền phòng theo ngày.

**Điện/nước (`metered`):** mỗi kỳ chỉ số tạo dòng với `quantity = current_reading - previous_reading`, đơn giá lấy từ snapshot của đúng `occupancy_service_id`, không lấy giá danh mục mới nhất. Ví dụ 40.125 kWh × 3,500.0000 = 140,437.50. Nếu đổi giá giữa tháng, cần ghi chỉ số tại mốc đổi giá và tách kỳ theo từng khoảng giá.

**Phí theo phòng (`per_room`):** phân bổ theo số giây thực tế áp dụng trong tháng:

```text
đơn giá dòng = giá tháng đã chốt × số giây áp dụng / số giây của cả tháng
```

Đơn giá dòng làm tròn HALF_UP tới 4 chữ số thập phân; `quantity` giữ `1.000`, sau đó thành tiền làm tròn tới 2 chữ số. Snapshot giá gốc được trả ở `source_unit_price`, khoảng sử dụng được lưu ở `charge_period_start/end`; bản xem trước có thêm `duration_seconds`, `month_seconds`. Cách này tránh làm tròn phần tháng xuống chỉ 3 chữ số trước khi nhân giá.

Tổng hóa đơn là tổng `line_total`. Ví dụ tiền phòng 3,000,000.00, điện 40.125 kWh × 3,500.0000 và nước 3.500 m³ × 18,000.0000 thì tổng là `3203437.50`. Không tự chia hóa đơn theo số thành viên; nhóm một người và nhóm nhiều người dùng cùng cách tính.

## Xem hóa đơn và công nợ

| Vai trò | Phương thức | Đường dẫn | Phạm vi |
| --- | --- | --- | --- |
| Chủ trọ | GET | `/api/owner/invoices` | Danh sách toàn bộ hóa đơn |
| Chủ trọ | GET | `/api/owner/invoices/{invoice}` | Chi tiết, dòng tiền, thanh toán và lịch sử cấp quyền |
| Người thuê | GET | `/api/tenant/invoices` | Chỉ hóa đơn có grant đang hiệu lực cho tài khoản |
| Người thuê | GET | `/api/tenant/invoices/{invoice}` | Chi tiết hóa đơn được cấp quyền |

Cả hai danh sách hỗ trợ `billing_month`, `room_id`, `occupancy_id`, `rental_group_id`, `payment_status`, `page`, `per_page` (mặc định 15, tối đa 100). Người thuê không thể mở rộng quyền bằng bộ lọc. Response danh sách có `data`, `links`, `meta`, sắp xếp tháng mới nhất trước.

Chi tiết có thông tin phòng/nhóm, `items`, `payments`, `total_amount`, `paid_amount`, `outstanding_amount` và `payment_status`, đọc từ view `invoice_balances`. Người thuê không nhận lịch sử cấp quyền. Tổng dòng tiền và tổng thanh toán được tổng hợp riêng, tránh nhân tổng thanh toán khi hóa đơn có nhiều dòng.

| `payment_status` | Ý nghĩa |
| --- | --- |
| `unpaid` | Chưa thanh toán và còn tiền phải thu |
| `partially_paid` | Đã thanh toán một phần |
| `paid` | Tổng thanh toán bằng tổng hóa đơn; hóa đơn có dòng nhưng tổng 0 cũng thuộc trạng thái này |
| `inconsistent` | Dữ liệu cần kiểm tra, ví dụ hóa đơn không có dòng hoặc thanh toán vượt tổng hóa đơn |

Đã có [API thu tiền hóa đơn](invoice-payments.md) bằng tiền mặt/chuyển khoản, thu một phần/toàn bộ, chống gửi trùng và thu vượt. Công nợ phản ánh các bản ghi đã xác nhận. Phát hành hóa đơn không tự ghi thu tiền; xác nhận thu là bước riêng. Chưa có xuất phiếu thu PDF, hoàn tiền hoặc thanh toán trực tuyến.

## Cấp và thu hồi quyền người thuê

Khi phát hành, backend tự cấp quyền cho tài khoản đại diện hợp lệ của đợt thuê. Với đợt đang mở, đại diện cần đang cư trú và có tài khoản `tenant` hoạt động. Với đợt đã đóng, chọn đại diện lịch sử tại thời điểm kết thúc; nếu tài khoản hiện bị khóa/không còn hợp lệ, vẫn có thể lưu hóa đơn cho chủ trọ nhưng response xem trước có cảnh báo và không cấp quyền tự động.

Đổi đại diện, chuyển phòng hoặc trả phòng không tự chuyển quyền các hóa đơn cũ. Grant còn hiệu lực được giữ qua việc kết thúc cư trú; chỉ tài khoản `tenant` đang hoạt động mới dùng được. Chủ trọ quản lý từng hóa đơn:

| Phương thức | Đường dẫn | Chức năng |
| --- | --- | --- |
| PUT | `/api/owner/invoices/{invoice}/access` | Cấp/thay thế người được xem và giữ lịch sử |
| DELETE | `/api/owner/invoices/{invoice}/access` | Thu hồi quyền hiện tại, trả 200 |

Ví dụ cấp quyền:

```json
{
  "representative_assignment_id": 17,
  "grant_reason": "Bàn giao hóa đơn tháng 1 cho người đại diện đã thống nhất."
}
```

ID trên là ID khoảng đại diện, không phải ID hồ sơ/người dùng. Khoảng đại diện phải thuộc đúng đợt thuê của hóa đơn, kể cả khoảng lịch sử đã kết thúc, và liên kết tới tài khoản `tenant` đang hoạt động. Lý do bắt buộc, tối đa 255 ký tự. Không nhập `user_id` hoặc `occupancy_id` trực tiếp.

Mỗi hóa đơn có tối đa một grant chưa thu hồi. Cấp mới đóng grant cũ và thêm bản ghi lịch sử; không đổi `representative_assignment_id` đã chốt trên chính hóa đơn. Thu hồi nhiều lần không xóa lịch sử hoặc tạo thêm grant. Người thuê không có quyền, đã bị thu hồi quyền hoặc hỏi ID không tồn tại đều nhận 404 khi xem chi tiết; danh sách chỉ trả hóa đơn được phép thấy.

## Mã phản hồi và kiểm thử

| HTTP | Ý nghĩa |
| --- | --- |
| 200 / 201 / 204 | Truy vấn hoặc cập nhật thành công / tạo chỉ số, phát hành hóa đơn / xóa chỉ số thành công |
| 401 | Chưa đăng nhập, phiên hết hiệu lực hoặc tài khoản bị khóa |
| 403 | Sai vai trò |
| 404 | Không tìm thấy, sai đợt thuê trên URL hoặc người thuê không có quyền xem hóa đơn |
| 405 | Phương thức chưa được hỗ trợ, ví dụ sửa/xóa hóa đơn đã phát hành |
| 409 | Kỳ/tháng đã khóa, không phải kỳ cuối, hóa đơn đã có, điều kiện phát hành chưa đủ hoặc token xem trước đã cũ |
| 419 | Thiếu/sai CSRF token khi ghi |
| 422 | Trường, chỉ số, khoảng thời gian, tháng hoặc người nhận quyền không hợp lệ |

Chạy trong `D:\KLTN\backend`:

```powershell
php artisan test --filter=MeterReadingManagementTest
php artisan test --filter=InvoiceBillingTest
php artisan test --filter=InvoiceAccessTest
php artisan test
php artisan rental:verify-database
```

PHPUnit dùng SQLite trong bộ nhớ, tách khỏi dữ liệu MySQL hiện có. Kiểm tra schema/model trên MySQL bằng `rental:verify-database` chỉ đọc. Tham khảo thêm [ánh xạ CSDL](database-mapping.md) và [quản lý người thuê/quá trình thuê](tenant-occupancy.md).
