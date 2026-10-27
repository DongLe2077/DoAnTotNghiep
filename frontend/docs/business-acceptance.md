# Biên bản nghiệm thu nghiệp vụ — 23/10/2026

## Kết quả

| Kiểm tra | Kết quả |
| --- | --- |
| Backend Laravel | 319/319 bài, 3.191 assertions |
| Frontend | 115/115 bài |
| Trình duyệt Chrome với backend thật và SQLite riêng | 66/66 kịch bản, 10,8 phút |
| TypeScript và bản dựng frontend | Đạt |
| Quy chuẩn PHP (Pint) | Đạt |
| MySQL: điều chỉnh/hủy/khôi phục/hạn đóng | 17 bước đạt; 21 bảng giữ nguyên sau rollback |
| MySQL: báo cáo và cổng người thuê | 24 bước đạt; 21 bảng giữ nguyên sau rollback |
| MySQL: lập hóa đơn / thu tiền | 17 / 16 bước đạt, dữ liệu gốc giữ nguyên sau rollback |
| Ánh xạ MySQL | 21 bảng ứng dụng, 75 quan hệ, 3 view |

Kiểm tra backend xuyên suốt riêng `BusinessAcceptanceTest` đạt 57 assertions: tạo hồ sơ/tài khoản → nhận phòng một người → áp dụng dịch vụ → ghi chỉ số → phát hành có hạn → giảm tiền có lý do → gửi lại điều chỉnh/phiếu thu không trùng → người thuê đọc → báo hỏng/ảnh → xử lý sửa chữa/chi phí → chuyển phòng → trả phòng → xem công nợ quá hạn → thu hết nợ → người đã trả phòng vẫn xem lịch sử đúng quyền.

## Ma trận nghiệm thu

| Phạm vi | Nội dung đã kiểm tra |
| --- | --- |
| Chung | Đăng nhập/đăng xuất, khóa tài khoản, đổi mật khẩu, CSRF, hết phiên, chặn sai vai trò, không lộ dữ liệu tài khoản khác |
| CT1 | Phòng/dịch vụ, tìm lọc, giá chung và giá áp dụng lịch sử, giá có phần lẻ |
| CT2 | Hồ sơ người thuê, tài khoản tùy chọn, tạo/khóa, quyền cá nhân |
| CT3 | Nhóm thuê, một/nhiều người, đại diện, dịch vụ theo kỳ, chuyển/trả phòng, giữ lịch sử |
| CT4 | Chỉ số điện nước, thiếu/sai kỳ, xem trước bị thay đổi, phát hành, khóa nguồn, cấp/thu hồi quyền |
| CT5 | Thu một phần/đủ, chống vượt và gửi trùng sau mất phản hồi/hết phiên, lịch sử phiếu thu, công nợ theo hóa đơn/phòng/nhóm |
| CT6 | Thu khác/chi phí, sổ và tổng hợp, chỉnh sửa/xóa/hoàn tác, xung đột phiên bản, chống gửi trùng/nhiều tab |
| CT7 | Báo hỏng, ảnh riêng tư, trạng thái xử lý, xung đột cập nhật, thông báo và điện thoại |
| CT8 | Tổng quan, biểu đồ hóa đơn/thu chi/phòng, dữ liệu rỗng, liên kết đúng bộ lọc, phân biệt tháng hóa đơn với tháng thực thu |
| NT1 | Phòng/dịch vụ đúng giá đã áp dụng, hồ sơ/lịch sử của mình, trường hợp chưa liên kết/không còn thuê |
| NT2 | Hóa đơn còn quyền, thanh toán một phần/đủ, quyền bị thu hồi, đọc lịch sử, không có nút quản trị |
| NT3 | Tạo báo hỏng đúng đại diện, chọn ảnh bằng nút thực, kiểm tra định dạng/kích thước, mất phản hồi rồi chỉ gửi lại ảnh, riêng tư sau trả phòng |
| NT4 | Thông báo, đánh dấu đã đọc, mở nội dung, hết phiên và đổi tài khoản |
| Hóa đơn mới | Điều chỉnh tăng/giảm có audit; không giảm dưới số đã thu; hủy khi chưa có phiếu thu; khôi phục; giữ nguồn và quyền xem; không cộng điều chỉnh vào thực thu |
| Hạn đóng | Đặt khi phát hành, đổi có lý do; ngày hạn vẫn chưa quá hạn; quá hạn theo số còn thiếu; không gán lùi cho dữ liệu cũ; bộ lọc, tổng hợp và nhắc |

## Dữ liệu và môi trường

- Bộ trình duyệt chạy tại cổng 15173/18000 với CSDL SQLite và thư mục ảnh/phiên riêng; không chạy vào MySQL hoặc bản demo đang mở.
- Kiểm tra MySQL tạo dữ liệu tạm trong transaction rồi rollback, đối chiếu dấu vân tay các bản ghi trước/sau. Các phép kiểm tra này không để lại hóa đơn hoặc lần thu thử trong dữ liệu người dùng.
- Khi nâng cấp MySQL đã lưu bản sao trong `.cache/backups/`, đối chiếu các cột/dòng của 20 bảng cũ trước/sau: giữ nguyên. Có thêm bảng invoice_changes và ba cột điều khiển invoices.
- Bản demo 5174/18001 cũng được nâng cấp cấu trúc tại chỗ, sao lưu SQLite trước khi nâng cấp; không tạo lại dữ liệu demo đã có.
- Không sửa `.env`, không triển khai lên máy chủ và không đẩy mã nguồn lên GitHub trong đợt này.

## Quy tắc cần nhớ khi tự thử

1. Hóa đơn cũ chưa có hạn không được coi là quá hạn. Hạn mới phải từ hôm nay trở đi; quá hạn chỉ bắt đầu vào ngày kế tiếp.
2. Điều chỉnh giữ nguyên dòng gốc và phiếu thu; tổng mới không thấp hơn số đã thu.
3. Hóa đơn đã có phiếu thu không được hủy. Hóa đơn hủy không có công nợ; khôi phục dùng lại tổng đã điều chỉnh và hạn cũ.
4. Hủy không mở khóa điện nước hoặc cho phát hành trùng kỳ; điều chỉnh/khôi phục trên chính hóa đơn để giữ lịch sử.
5. Nhắc tự động lúc 08:00 chỉ nhắc công nợ quá hạn khi tiến trình Laravel scheduler đang hoạt động. Không tự tính phạt hoặc tự xác nhận chuyển khoản.
6. Trình duyệt có bộ thử kích thước điện thoại; vẫn nên lấy phản hồi sử dụng trên thiết bị thật, kiểm tra cài mới và cấu hình vận hành trước bàn giao.

Xem [cách tự thử hóa đơn](invoice-controls-self-test.md), [chủ trọ](owner-self-test.md), [người thuê](tenant-self-test.md) và [quy tắc backend](../../backend/docs/invoice-controls.md).
