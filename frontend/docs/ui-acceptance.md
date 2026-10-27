# Nghiệm thu giao diện và luồng nghiệp vụ — 25/10/2026

## Phạm vi

Nghiệm thu cục bộ giao diện Vue với Laravel thật, dữ liệu và ảnh thử trong SQLite riêng. Phạm vi gồm CT1–CT8 chủ trọ, NT1–NT4 người thuê, xác thực/phân quyền, ảnh sự cố, thông báo chung, tình huống lỗi mạng/phiên và màn hình nhỏ. Không thay đổi `.env` hoặc bản ghi MySQL của người dùng, không triển khai hosting.

MySQL chỉ được kiểm tra bằng lệnh đọc ánh xạ: 22 bảng nghiệp vụ, 78 quan hệ, 3 view hợp lệ. Kết quả này không thay thế việc kiểm thử cài mới trên máy khác hoặc nghiệm thu môi trường hosting.

## Những điểm đã sửa

| Vấn đề | Thay đổi | Đối chiếu |
| --- | --- | --- |
| Ô ngày giờ của lịch sử thu tiền tràn khỏi khung ở 320px | Cho phép ô nhập thu nhỏ đúng chiều rộng; giới hạn cột nhãn bộ lọc và giảm khoảng đệm riêng của ô ngày giờ | Các trang chủ trọ, đặc biệt `/owner/payments`, không cần cuộn ngang ở 320px; ngày giờ đã nhập hiển thị đủ khi đối chiếu ảnh chụp |
| Trang không có quyền chỉ giải thích chung, dễ nhầm đăng nhập sai vai trò với lỗi hệ thống | Hiển thị tài khoản/vai trò hiện tại, giữ liên kết về đúng khu vực, thêm đăng xuất để đổi tài khoản và hướng dẫn dùng chung phiên giữa các tab | Thử cả owner/tenant; lỗi đăng xuất giữ phiên, chủ động thử lại thành công rồi đăng nhập vai trò khác |
| Bộ chọn có thể lặp tên phòng sau khi bỏ chọn rồi chọn lại | Đồng bộ danh sách nhãn với lựa chọn và không nhân đôi cùng ID | Bỏ chọn/chọn lại phòng trong luồng soạn thông báo, kiểm tra danh sách và người nhận |
| Banner demo và tài liệu cũ còn nói khởi động lại sẽ tạo lại dữ liệu | Sửa nội dung theo cơ chế giữ dữ liệu hiện tại; chỉ dẫn cổng, phiên và tài khoản theo vai trò | Đối chiếu với trình khởi động demo; giao diện đăng nhập demo có hướng dẫn tài khoản riêng |
| Tài liệu điện nước/hóa đơn còn ghi frontend chưa xây dựng hoặc chưa có điều chỉnh/hủy | Cập nhật mô tả, dẫn tới quy tắc kiểm soát hóa đơn hiện có | Đối chiếu API và màn hình đã có |

Giao diện đăng nhập chỉ hiển thị tài khoản mẫu khi chạy với chế độ demo. Giao diện thông thường không hiển thị các thông tin này.

## Nội dung được kiểm tra

- Đăng nhập hai vai trò, lỗi mật khẩu/tài khoản khóa, CSRF, hết phiên, đổi mật khẩu, đăng xuất và đồng bộ các tab.
- Phòng/dịch vụ, giá chung so với giá đã chốt, hồ sơ/tài khoản người thuê, nhận/chuyển/trả phòng, đại diện, thành viên và khoảng dịch vụ.
- Chỉ số điện nước, xem trước/phát hành, khóa kỳ, điều chỉnh/hủy/khôi phục, hạn đóng, quyền xem hóa đơn, thu một phần/đủ và gửi lại lượt chưa rõ kết quả.
- Đối chiếu tổng quan/biểu đồ, công nợ và sổ tiền; phân biệt tháng hóa đơn với ngày thực nhận tiền.
- Thu khác/chi phí, chống trùng, sửa/xóa/lịch sử/hoàn tác và xung đột phiên bản.
- Báo hỏng, ảnh thật trong môi trường thử, định dạng/dung lượng/kích thước ảnh không hợp lệ, bổ sung ảnh và thử lại sau mất phản hồi; xử lý và theo dõi sau trả phòng.
- Thông báo cá nhân/chung, đúng người nhận, quyền sau chuyển/trả phòng, đã đọc/chưa đọc, bộ lọc và phân trang.
- Rà soát mọi trang chính của hai vai trò ở 320px, thêm các kích thước 360/390/768/1280px trong bộ hiện có; menu chủ trọ ở chiều cao thấp, menu người thuê, font tiếng Việt và điều khiển bàn phím.

Ảnh chụp phục vụ rà soát nằm ở `frontend/.cache/e2e/screenshots/`, các ảnh mới có tiền tố `acceptance-`. Log và báo cáo thử nghiệm nằm trong `.cache/`, được giữ ngoài Git.

## Giới hạn và việc bàn giao còn lại

- Kiểm tra màn hình điện thoại trong trình duyệt là kiểm tra bố cục; chưa thay thế thao tác trên điện thoại thật.
- Chưa kiểm thử cài mới trên máy tính khác bằng bản sao MySQL, chưa triển khai và chưa nghiệm thu hosting.
- PDF/Excel, sửa sai phiếu thu và hợp đồng/cọc vẫn là phần nâng cấp theo [kế hoạch](next-steps.md), không nằm trong lần sửa giao diện này. Nhật ký từng lần sửa chữa đã được bổ sung ở đợt tiếp theo cùng ngày; xem [hướng dẫn riêng](maintenance-history-self-test.md).
- Khi tự thử hai vai trò đồng thời, dùng hai trình duyệt/hồ sơ hoặc cửa sổ ẩn danh. Trang không có quyền vẫn giữ nguyên kiểm tra phân quyền.

## Kết quả kiểm thử

| Phần kiểm tra | Kết quả |
| --- | --- |
| Backend Laravel, SQLite riêng | 328/328, 3.286 assertions |
| Frontend | 119/119 |
| TypeScript và bản đóng gói cuối | Đạt |
| Quy chuẩn PHP | Đạt |
| MySQL đọc ánh xạ | 22 bảng nghiệp vụ, 78 quan hệ, 3 view hợp lệ; không ghi dữ liệu |
| Chrome, lượt tổng thể 73 kịch bản | 72 đạt, 1 ca chờ đăng nhập lại hết thời gian; 16,6 phút |
| Chrome, chạy lại cả nhóm thông báo và giao diện sau hiệu chỉnh | 15/15 đạt; 2,3 phút |
| Microsoft Edge, ba luồng giao diện/đổi tài khoản hai vai trò | 3/3 đạt; 56,4 giây |
| Bản demo admin/user | Đăng nhập đúng vai trò, trang không có quyền, banner, lọc ngày giờ ở 320px đạt; không có lỗi JavaScript được ghi nhận |

Ca hết thời gian thuộc bài kiểm thử chuyển từ phiên đã hết hạn sang tài khoản khác. Trace ghi nhận máy chủ đã trả đăng nhập 200, nhưng quá trình CSRF/đăng nhập/điều hướng vượt mốc chờ URL 8 giây của bài thử. Đã sửa bài thử để chờ và kiểm tra phản hồi POST đăng nhập trước khi kiểm tra điều hướng. Ca này đã đạt trong lượt chạy lại 15 bài; các kiểm tra quyền xem và xóa dữ liệu phiên cũ vẫn được đối chiếu.

Sau bản CSS cuối và thay đổi cách chờ đăng nhập, phạm vi chạy lại trên Chrome là 15 ca liên quan. Kết quả 72/73 của lượt tổng thể được giữ rõ để phân biệt các lượt chạy, không gộp thành một lượt tổng thể mới.
