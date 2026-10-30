# Nghiệm thu thông tin nhà trọ, PDF và nhập nhiều phòng — 30/10/2026

## Phạm vi

- Chủ trọ cấu hình tên/địa chỉ/liên hệ/ngân hàng/ghi chú của một nhà trọ. Có xem lại, xác nhận và phát hiện phiên bản cũ khi sửa ở nhiều nơi.
- Hai vai trò tải PDF hóa đơn và phiếu thu theo quyền hiện tại. PDF giữ trạng thái hủy/thay thế, số tiền chính xác, font tiếng Việt cục bộ và thời điểm xuất. Chỉ hóa đơn còn khoản được phép thu mới có hướng dẫn chuyển khoản.
- Chủ trọ nhập điện/nước nhiều phòng theo tháng và khoảng giá. Chọn qua nhiều trang, tối đa 100 dòng mỗi lượt xác nhận, xem tiêu thụ và kết quả riêng từng dòng. Không tự phát hành hóa đơn.
- Yêu cầu đã xác nhận có mã chống trùng và bản chờ riêng theo tài khoản; lỗi mạng/hết phiên dừng gửi. Người dùng chủ động đối chiếu và gửi lại nguyên yêu cầu sau khi đăng nhập lại.

## Kết quả xác nhận

| Kiểm tra | Kết quả |
| --- | --- |
| Toàn bộ backend | 399/399, 4.145 assertions |
| PDF sau chỉnh bố cục số lớn cuối cùng | 10/10, 97 assertions |
| Toàn bộ frontend unit | 131/131 |
| TypeScript/Vite đóng gói | Đạt |
| PHP style các tệp thay đổi | Đạt |
| MySQL mapping chỉ đọc | 28 bảng nghiệp vụ, 100 quan hệ; schema ghi nhận 3 view |
| MySQL chỉ số đồng thời riêng | 53 kiểm tra, 6 tình huống đồng thời và đối chiếu trình tự giá/gửi lại đạt |
| Chrome lượt cuối | 14/14, 4,1 phút |
| PDF mẫu giả | 5 mẫu/31 trang; đã xem ảnh, không ký tự thay thế hoặc chữ vượt trang |

Lượt Chrome cuối chạy `owner-billing.spec.ts`, `ui-acceptance.spec.ts`, `zz-bulk-meters.spec.ts` và `zz-property-pdf.spec.ts`. Đây là lượt kiểm tra các luồng liên quan, không phải chạy lại toàn bộ bộ trình duyệt của dự án.

Lượt sáu ca mới đầu tiên có bốn ca chưa đạt do fixture nhận phòng thiếu tài khoản đại diện hoạt động và cách đối chiếu URL đăng xuất thiếu tham số. Sau sửa bộ kiểm tra, lượt kế tiếp đạt 5/6; ca còn lại giả định thứ tự gửi lại bằng vị trí mảng, trong khi bản chờ sắp theo mã. Đã sửa đối chiếu bằng đúng mã yêu cầu và toàn bộ 14 ca cuối đạt. Không bỏ điều kiện nghiệp vụ để làm kiểm thử đạt.

## Các tình huống đã đối chiếu

- Số tài khoản giữ số 0 đầu, nhóm ngân hàng đủ ba trường, xung đột chỉnh sửa, bố cục 320px.
- Tải PDF thật bằng hai vai trò, phiếu hủy/thay thế, thu hồi quyền người thuê, lỗi tải có thể thử lại và hủy lượt tải khi đăng xuất.
- Hai phòng với chỉ số thập phân; mất phản hồi sau khi máy chủ đã ghi; đăng nhập lại/gửi cùng mã và nội dung chỉ tạo một kỳ cho mỗi dòng.
- Dữ liệu một dòng thay đổi không làm mất bản nháp dòng khác; khóa sau phát hành hóa đơn và chặn quyền ghi của người thuê.
- MySQL: cùng mã/cùng hoặc khác nội dung, hai mã cho cùng kỳ, nhập nhiều phòng đồng thời với màn hình một phòng, phân biệt mã hoa/thường, gửi lại sau sửa/xóa kỳ. Khoảng giá liền kề phải hoàn tất khoảng trước trước khi ghi khoảng sau.
- PDF mẫu gồm hóa đơn thường, hóa đơn 24 trang tiếng Việt/nội dung dài, phiếu hủy, phiếu thay thế, hóa đơn có số lượng/đơn giá/số tiền lớn. Đã điều chỉnh cỡ chữ số dài để tránh ngắt số tùy ý.

## Dữ liệu và giới hạn

Backend dùng SQLite riêng trong kiểm thử; Chrome dùng Laravel thật với `frontend/.cache/e2e/auth.sqlite` và phiên/tệp riêng. MySQL đồng thời dùng CSDL tạm chỉ sao chép cấu trúc, tạo dữ liệu giả và dọn sau kiểm tra; không ghi nghiệp vụ thử vào `rental_management`. Nâng cấp hai bảng mới đã có bản sao cục bộ và đối chiếu dữ liệu cũ. Schema trong Git chỉ chứa cấu trúc.

Kết quả này kiểm tra tại máy phát triển. Điện thoại thật, cài đặt máy mới và hosting cần nghiệm thu riêng. PDF là chứng từ quản lý nội bộ, chưa tích hợp hóa đơn điện tử; tệp đã tải về không tự thay đổi khi cập nhật thông tin/quyền. Nhập nhiều phòng không bao gồm ảnh đồng hồ hoặc nhập Excel.

## Hướng dẫn tự thử

- [Thông tin nhà trọ và PDF](property-pdf-self-test.md)
- [Nhập chỉ số nhiều phòng](bulk-meters-self-test.md)
- [Quy tắc thông tin nhà trọ](../../backend/docs/rental-property.md), [PDF](../../backend/docs/pdf-documents.md), [chỉ số nhiều phòng](../../backend/docs/bulk-meter-readings.md)
