# Sửa và nghiệm thu giao diện điện thoại/máy tính — 04/11/2026

## Những điểm đã sửa

1. **Giữ thanh trên cùng khi cuộn:** tên trang, menu, thông báo và đăng xuất tiếp tục truy cập được trên điện thoại và máy tính. Thanh nằm dưới thông báo kết nối/cập nhật PWA, tự dịch theo chiều cao thông báo; menu phủ phía trên thanh và vẫn mở/đóng được khi đang cuộn sâu.
2. **Bộ chọn tháng Tổng quan:** trên điện thoại, ô tháng và nút Cập nhật xếp dọc, rộng bằng nhau; ở màn hình lớn chúng căn đáy và cao bằng nhau. Không còn căn nút theo chiều cao của cả nhãn lẫn ô nhập như ảnh người dùng gửi.
3. **Bộ lọc Công nợ:** tách dòng “Để trống kỳ hóa đơn…” khỏi nhãn ô tháng để các ô chọn và nút cùng hàng. Liên kết hướng dẫn bằng `aria-describedby`, vẫn đọc được qua công cụ hỗ trợ.
4. **Các bộ lọc dùng chung:** đồng bộ chiều cao ô nhập/ô chọn/nút và ô tìm trong bộ chọn phòng/người thuê. Tên trang dài được co/ẩn phần thừa hợp lý ở thanh trên; phần tiêu đề nội dung được xuống dòng. Các nhóm thao tác đầu trang được phép xuống hàng khi thiếu chỗ.
5. **Nội dung khi điều hướng:** thêm khoảng chừa trên khi đưa ô nhập/nội dung chính vào tầm nhìn để giảm nguy cơ nằm dưới thanh vừa ghim.

Thay đổi nằm ở Vue/CSS và kiểm thử giao diện; không thêm nghiệp vụ hoặc thay đổi schema CSDL.

## Phạm vi kiểm tra cục bộ

Chạy Chrome với Laravel và SQLite thử riêng trong `frontend/.cache/e2e`. Không ghi vào MySQL phát triển hoặc CSDL online khi chạy bộ này.

| Kiểm tra | Kết quả |
| --- | --- |
| Frontend unit | **134 kiểm thử đạt** |
| TypeScript và build hosting/PWA | Đạt, tạo được gói triển khai |
| Nhóm nghiệm thu mở rộng | **13 kịch bản đạt**, gồm biểu đồ, công nợ, quyền, cuộn/menu, bộ chọn tháng và rà các trang |
| Kiểm tra lại phần bị ảnh hưởng sau sửa cuối | 6 kịch bản đạt; ca căn hàng còn lại đạt khi chạy lại sau sửa bộ chọn phần tử của chính kiểm thử. Tổng 7 kịch bản này là tập con của nhóm trên, không cộng thành 20 kịch bản độc lập |

Đã rà **18 đường dẫn chủ trọ** và **8 đường dẫn người thuê/dùng chung** ở **768, 1024, 1440 px**: các trang phòng, dịch vụ, người thuê, nhóm/đợt thuê, nhận phòng, điện nước/hàng loạt, hóa đơn/lập hóa đơn, thu tiền, công nợ, thu chi, sửa chữa, thông báo và thông tin nhà trọ; phía người thuê gồm phòng, hóa đơn, báo hỏng/biểu mẫu, hồ sơ, lịch sử, tài khoản và thông báo.

Bộ nghiệm thu màn hình hẹp rà 15 đường dẫn chủ trọ và 8 đường dẫn người thuê ở **320 px**. Các ca riêng kiểm tra thanh ghim, cuộn sâu, mở menu và vùng thông báo PWA thay đổi ở **320, 390, 768, 1024, 1440 px**; bộ chọn tháng còn được đối chiếu ở **600 px**. Không phát hiện tràn ngang hoặc chồng lấn tiêu đề/nút thanh trên trong các ca này.

Đã xem ảnh trực quan Tổng quan điện thoại/máy tính, Công nợ, Nhập chỉ số nhiều phòng và Thông tin nhà trọ; kiểm tra trực tiếp giao diện bằng trình duyệt tích hợp. Các kích thước điện thoại trong lần này là mô phỏng khung nhìn trình duyệt, không gọi là kiểm thử Android vật lý.

## Bản đóng gói

```text
Release: 2026-11-04T02-11-03-425Z-ff7c83
Package SHA256: 7f0887f8d05f317fe48cbc58fe2275807787fa112163171bce4588ab3f50938b
```

Nhật ký kiểm thử, gói triển khai và ảnh bằng chứng được giữ trong `.cache`, ngoài Git.

## Đã triển khai và kiểm tra lại online

- Kích hoạt lúc **09:15:52 ngày 04/11/2026, giờ Việt Nam**, tại `https://rentbase.alwaysdata.net`.
- Giữ bản trước `2026-11-03-mariadb-015504` để có thể quay lại; dữ liệu, cấu hình riêng, ảnh và phiên đăng nhập dùng lại thư mục lưu bền vững.
- **75 điểm kiểm tra HTTPS sau triển khai đạt:** hai vai trò, các mô-đun, công nợ, phân quyền, ảnh, PDF, đăng nhập và đăng xuất.
- Đối chiếu CSDL chỉ đọc sau cập nhật: các bảng tiền không thay đổi so với lúc chuyển CSDL; công nợ **4.660.000đ**, cả 3 ảnh còn đúng; không có lỗi ứng dụng mới trong nhật ký đã đọc.
- Đã thử cập nhật từ bản PWA cũ bằng nút **Cập nhật ngay**. Bản mới mở lại đúng trang và giữ phiên đăng nhập; kiểm tra DOM xác nhận thanh trên dùng `sticky` và có bộ chọn tháng mới.
- Trên website thật: ở **1440 px**, ô tháng và nút Cập nhật cùng cao 50px và cùng đáy; cả 5 ô/nút bộ lọc Công nợ cùng cao 50px, cùng tọa độ trên. Ở **390 px**, sau cuộn hơn 3.000px, thanh trên vẫn nằm ở đầu màn hình; mở menu rồi chuyển sang Công nợ thành công.

Ảnh trên website thật:

```text
D:\KLTN\.cache\responsive-live-mobile-20261104.jpg
D:\KLTN\.cache\responsive-live-mobile-scrolled-20261104.jpg
D:\KLTN\.cache\responsive-live-desktop-20261104.jpg
D:\KLTN\.cache\responsive-live-receivables-20261104.jpg
```

Hồ sơ kiểm tra HTTPS và CSDL: `D:\KLTN\.cache\deployment\rentbase\review\responsive-20261104`. Đã cập nhật mã trong workspace và bản online; chưa commit/push GitHub trong lượt sửa giao diện này.

## Cách xem bản mới trên Android

Mở ứng dụng Nhà Trọ có mạng. Nếu hiện “Có phiên bản mới”, hoàn tất biểu mẫu đang nhập rồi chọn **Cập nhật ngay**. Kiểm tra thanh menu/tên trang khi cuộn xuống và bộ chọn tháng trên Tổng quan. Giữ nguyên địa chỉ `https://rentbase.alwaysdata.net`; không cần cài lại chỉ vì thay đổi giao diện này.

Nghiệm thu thiết bị Android thật về camera, PDF, mạng 4G khi Windows tắt tiếp tục là bước riêng. Các kiểm tra bố cục ở đây không thay thế phần đó.
