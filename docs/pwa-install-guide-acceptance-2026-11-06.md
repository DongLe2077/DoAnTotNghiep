# Hướng dẫn cài ứng dụng — 06/11/2026

Đã sửa và triển khai hướng dẫn cài trên https://rentbase.alwaysdata.net/login, bản `2026-11-06T06-32-03-115Z-00017d`.

## Điều chỉnh

- Hướng dẫn Android tách thành năm thao tác: đóng hướng dẫn, mở menu Chrome, chọn mục cài, xác nhận, mở biểu tượng Nhà Trọ.
- Minh họa cả nút ba chấm và nút tròn đỏ có mũi tên như ảnh BlueStacks người dùng gửi. Tên mục tiếng Anh và trường hợp có menu con được giải thích riêng.
- Bổ sung vị trí nút Home trên thanh BlueStacks màu tím.
- Bỏ phần giới thiệu dài trước các bước cài; nút đóng luôn nằm ngoài vùng nội dung cuộn.
- Sửa lỗi mở lại hướng dẫn vẫn giữ vị trí cuộn cũ. Chỉ hộp hướng dẫn cài bật tùy chọn đặt lại vị trí cuộn; các hộp thoại khác giữ cách hoạt động hiện có.
- Cập nhật [hướng dẫn Android](../frontend/docs/mobile-android-guide.md) và ảnh minh họa.

## Kiểm tra

| Nội dung | Kết quả |
| --- | --- |
| TypeScript, build frontend và gói hosting | Đạt |
| Kiểm thử frontend | 134/134 đạt |
| Bộ kiểm tra PWA | 39/39 đạt |
| Android mô phỏng 320×568, 390×844, 430×932, 960×480, 1280×720 | Không tràn ngang; nút đóng dùng được; mở lại bắt đầu từ đầu |
| iPhone mô phỏng 320, 390, 430 px | Hướng dẫn hiển thị trong màn hình |
| Chrome thông thường, manifest và biểu tượng | Không có lỗi chặn cài theo chẩn đoán Chrome |
| Bản online sau cập nhật | Đã quan sát nội dung mới; kiểm tra màn hình 390×844, nội dung cuộn được và bắt đầu ở vị trí 0 |
| Cập nhật từ bản PWA cũ | Tải lại, chọn Cập nhật ngay rồi thấy nội dung mới |

Kết quả chi tiết tự động: `frontend/.cache/pwa-install-review/verification.json`. Ảnh online: `.cache/android-install-guide-online-20261106.jpg`.

## Giới hạn

Công cụ lấy ảnh cửa sổ BlueStacks báo `FrameArrived timed out`; cây truy cập chỉ có các nút Windows không tên, không có menu Chrome Android. Vì vậy chưa điều khiển được menu cài hoặc xác nhận biểu tượng đã mở trên BlueStacks trong lượt này. Kiểm tra kích thước Android trong Chrome trên máy tính và các sự kiện lời mời cài mô phỏng không thay thế việc cài thật trên Android.

Menu Chrome thay đổi theo phiên bản. Đối chiếu [hướng dẫn Google Chrome cho Android](https://support.google.com/chrome/answer/9658361?co=GENIE.Platform%3DAndroid&hl=vi).

## Đối chiếu BlueStacks sau phản hồi của người dùng

Người dùng gửi đủ menu Chrome: không có mục Install app, Add to Home screen hoặc Install and create shortcut. Người dùng xác nhận CH Play chỉ hiện Mở, không hiện Cập nhật.

Đã đọc riêng dòng tên máy giả lập trong cấu hình BlueStacks của máy: `bst.instance.Pie64.display_name="BlueStacks App Player"`, phù hợp ký hiệu P64 trong ảnh. Đây là bản Pie 64-bit/Android 9. Google yêu cầu Android 10 trở lên cho Chrome mới; Chrome 138 là bản cuối hỗ trợ Android 9. Vì vậy hướng yêu cầu cập nhật Chrome mới ngay trong máy Pie64 này không phù hợp.

Bước tiếp theo được hướng dẫn là tạo máy Android 11 hoặc Android 13 qua Multi-instance Manager chính thức, rồi cài Chrome từ CH Play trên máy mới. Chưa tạo máy mới, chưa cập nhật Chrome và chưa xác nhận cài PWA trên BlueStacks. Thay Android giải quyết giới hạn phiên bản Chrome, chưa chứng minh sẽ giải quyết mục cài PWA bị thiếu.

Nguồn: [Google — cập nhật Chrome và Android được hỗ trợ](https://support.google.com/chrome/answer/95414?co=GENIE.Platform%3DAndroid&hl=vi), [BlueStacks — các phiên bản Android](https://support.bluestacks.com/hc/en-us/articles/360058931031-How-to-utilize-the-different-Android-versions-available-on-BlueStacks-5). Lưu ý từ BlueStacks 5.22.288, máy mới Android 11 không còn được cung cấp; có thể chọn Android 13 nếu không có Android 11 trong danh sách.

Triển khai dùng lại CSDL, ảnh và phiên hiện có; không chạy tạo dữ liệu mẫu hoặc migration. Chưa commit/push các thay đổi trong lượt này.
