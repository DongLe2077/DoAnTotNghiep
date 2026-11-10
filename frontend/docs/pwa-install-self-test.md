# Bước 2 mobile — cài Nhà Trọ từ website

**Cập nhật 06/11:** hướng dẫn Android/BlueStacks đã được viết lại, sửa vị trí cuộn khi mở lại và kiểm tra 39 bước. Xem [nghiệm thu mới](../../docs/pwa-install-guide-acceptance-2026-11-06.md) và [hướng dẫn từng bước](mobile-android-guide.md).

**Cập nhật 01/11:** đã triển khai tiếp mở lại khi mất mạng, cập nhật có xác nhận và cải thiện thao tác điện thoại. [Hướng dẫn Android](mobile-android-guide.md), [nghiệm thu bước 3–6](pwa-mobile-acceptance-2026-11-01.md). Các số ngày 30/10 phía dưới là mốc bước 2.

Đã thêm tên **Nhà Trọ**, màu xanh `#235c4b`, manifest và năm icon PNG: bản thường/maskable 192–512px, icon iPhone 180px. Nút **Cài ứng dụng** có ở trang đăng nhập và thanh trên cùng của cả hai vai trò; trên màn hình nhỏ, nút trong ứng dụng hiển thị bằng biểu tượng tải xuống.

## Mở bản thử

Đọc địa chỉ HTTPS đang chạy trong `D:\KLTN\frontend\.cache\mobile-https\access.txt`, hoặc dùng liên kết được cung cấp trong phiên hỗ trợ. [Cách tự chạy và dừng](mobile-https-self-test.md). Bản mẫu mobile dùng **admin / 123** và **user / 123**, giữ dữ liệu qua lần khởi động lại, tách MySQL.

Quick Tunnel cần máy tính/tiến trình hoạt động. Địa chỉ đổi khi chạy lại; biểu tượng đã cài từ địa chỉ cũ sẽ không tự đổi sang địa chỉ mới. Khi đó gỡ biểu tượng cũ và cài lại từ URL mới. Cài dùng lâu dài cần địa chỉ HTTPS cố định.

## Android

1. Dùng Chrome mở địa chỉ HTTPS bằng cửa sổ thông thường.
2. Nhấn **Cài ứng dụng**. Nếu có nút **Cài trên thiết bị này**, nhấn và xác nhận lời mời của Chrome.
3. Nếu chưa có lời mời, mở menu **⋮** của Chrome, tìm **Cài đặt ứng dụng** / **Thêm vào màn hình chính**. Tên và lựa chọn tùy trình duyệt.
4. Mở biểu tượng **Nhà Trọ** từ màn hình chính; kiểm tra mở trong cửa sổ ứng dụng và đăng nhập đúng vai trò.

## iPhone / iPad

1. Mở địa chỉ HTTPS bằng Safari.
2. Nhấn **Chia sẻ**; ở một số bố cục cần mở menu trang trước.
3. Chọn **Thêm vào MH chính**, bật **Mở dưới dạng ứng dụng web** nếu có rồi nhấn **Thêm**.
4. Mở biểu tượng Nhà Trọ, đăng nhập và thử giao diện. Nếu cửa sổ cài yêu cầu đăng nhập lại, sử dụng tài khoản mẫu như trên.

Các bước theo [hướng dẫn Apple](https://support.apple.com/en-vn/guide/iphone/iphea86e5236/ios). Trong hộp cài có thể chọn hướng dẫn Android, iPhone/iPad hoặc máy tính bằng các nút tương ứng.

## Kiểm tra sau cài

- `admin` vào khu vực chủ trọ; đăng xuất rồi đăng nhập `user` vào khu vực người thuê.
- Mở hóa đơn, PDF và ảnh báo hỏng được cấp quyền; thử làm mới hoặc đóng/mở lại ứng dụng khi máy chủ còn hoạt động.
- Khi mở dạng ứng dụng độc lập, nút cài được ẩn. Trong trình duyệt, hủy lời mời vẫn sử dụng website được; lời mời đã dùng không bị tự gọi lại.
- Ghi mẫu điện thoại, phiên bản hệ điều hành/trình duyệt, biểu tượng, cách mở và kết quả hai vai trò để nghiệm thu thiết bị thật.

## Phạm vi hiện tại

Ở mốc bước 2 chưa có service worker; nay bước 3 đã bổ sung mở lại khung công khai và cập nhật có xác nhận. Đăng nhập/dữ liệu/nghiệp vụ vẫn cần mạng. Theo [MDN](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Making_PWAs_installable), service worker không phải điều kiện bắt buộc để cài. Cơ chế thông báo khi đóng ứng dụng thuộc đợt Web Push riêng.

## Kiểm tra tự động và kết quả

Ngày 30/10/2026:

| Kiểm tra | Kết quả |
| --- | --- |
| TypeScript và Vite build | Đạt |
| Kiểm thử frontend hiện có | 131/131 đạt |
| HTTP gateway, gồm manifest đúng MIME/no-cache | 4/4 nhóm đạt |
| Bản build: manifest/icon, vùng maskable, hướng dẫn, bàn phím, vòng đời lời mời và 320–430px | 29 bước đạt |
| Chrome với hồ sơ thông thường | Không có lỗi chặn cài theo `Page.getInstallabilityErrors` |
| HTTPS công khai: hai vai trò/nút cài/icon/phiên/quyền/ảnh/PDF | 54 bước đạt |
| Cài thật từ màn hình chính trên Android/iPhone | Chờ người dùng thực hiện trên thiết bị |

Vòng đời chấp nhận/hủy/lỗi lời mời dùng sự kiện mô phỏng để đối chiếu giao diện, tách với kiểm tra manifest và khả năng cài của Chrome. Kiểm tra điện thoại ở Chrome dùng kích thước mô phỏng, chưa thay cho cài thật. Không coi việc nhấn chấp nhận là bằng chứng cài hoàn tất; giao diện chờ sự kiện `appinstalled` hoặc trạng thái mở độc lập.

Lệnh tại thư mục frontend: `npm run build`, `npm test`, `node --test scripts/mobile-gateway.test.mjs`, `node scripts/review-pwa-install.mjs`. Khi bản HTTPS đang chạy: `node scripts/review-mobile-https.mjs`. Kết quả/ảnh ở `.cache/pwa-install-review/` và `.cache/mobile-https/review/`, được bỏ qua trong Git. Chạy lại sinh icon từ SVG bằng `node scripts/generate-app-icons.mjs` với Chrome đã cài; PNG đã có trong mã nguồn nên build thông thường không cần bước này.
