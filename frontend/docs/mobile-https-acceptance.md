# Nghiệm thu bước 1 mobile: HTTPS tạm — 30/10/2026

Kết quả bên dưới ghi mốc bước 1. Đợt tiếp theo đã hoàn thành phần mã bước 2: manifest/icon/nút và hướng dẫn cài, 29 bước bản build và 54 bước HTTPS mới đạt, 4 nhóm gateway đạt. [Nghiệm thu/cách cài bước 2](pwa-install-self-test.md). Cài trên điện thoại thật còn chờ người dùng thực hiện.

## Phạm vi đã hoàn thành

- Phục vụ bản Vue đã đóng gói qua một cổng loopback riêng và Cloudflare HTTPS tạm; Laravel dùng SQLite, ảnh, cookie và bộ đệm riêng dưới `.cache/mobile-https`.
- Dùng dữ liệu giả được tạo mới bằng các fixture trình bày; không sao chép MySQL hoặc dữ liệu do người dùng nhập vào các bản trình trước.
- Cổng chia sẻ chỉ phục vụ giao diện, tài nguyên công khai đã định và API. Tệp cấu hình, thư mục tạm, mã nguồn và CSDL không có đường tải công khai.
- API dùng cùng nguồn HTTPS; gateway thay header chuyển tiếp, Laravel chỉ tin proxy loopback trong chế độ mobile. Cookie phiên Secure/HttpOnly/SameSite=Lax, CSRF và quyền hiện tại được giữ.
- Bản sao CSDL/ảnh được khôi phục thử vào thư mục riêng và đối chiếu toàn bộ bản ghi, khóa ngoại, số dư, SHA-256 của ba ảnh mẫu.
- Theo lựa chọn tiếp theo của người dùng, chỉ tài khoản mẫu `admin` và `user` của bản mobile dùng mật khẩu `123`. Helper đổi mật khẩu kiểm tra đúng CSDL/metadata và lưu bản CSDL trước khi đổi; các bản MySQL/demo khác được giữ nguyên.

## Kết quả

| Kiểm tra | Kết quả |
| --- | --- |
| Công cụ HTTPS | Cloudflared 2026.10.6 từ bản phát hành chính thức, khớp SHA-256 đã xác minh |
| Kiểm tra kiểu dữ liệu/build riêng | Đạt |
| Cổng chia sẻ cục bộ | 3/3 nhóm kiểm thử HTTP đạt |
| Chrome qua HTTPS công khai, sau đổi mật khẩu thành 123 | 39 bước đạt, hai ảnh màn hình 390px |
| Đăng nhập/phân quyền/PDF ở môi trường E2E cũ | 14/14 kịch bản Chrome, 2,4 phút |
| PHP helpers | Cú pháp/Pint đạt |
| Sao lưu/khôi phục mẫu | Bản ghi/số dư/khóa ngoại và ba ảnh khớp |
| Mở thêm khi cổng bận | Bị từ chối; trạng thái bản đang chạy giữ nguyên |

Kiểm thử HTTPS bao gồm đăng nhập/đăng xuất hai vai trò, secure context/cookie, hóa đơn và số dư mẫu, ảnh/PDF đúng quyền, người không có quyền/khách bị chặn, thiếu CSRF bị từ chối, API 404 vẫn là JSON và đường dẫn nhạy cảm trả 404. Các nhóm thử cổng còn đối chiếu header giả mạo bị thay, chuyển nguyên yêu cầu API, cookie được giữ và lỗi backend không biến thành HTML giao diện.

Người dùng đã xác nhận mở được trang đăng nhập từ điện thoại. Phần kiểm thử 39 bước do Chrome chạy ở kích thước điện thoại; không coi đó là chứng nhận đã thử đầy đủ mọi thao tác trên thiết bị Android/iPhone thật. Sau phản hồi đăng nhập bằng mật khẩu cũ, tài khoản được đổi sang 123 theo yêu cầu và kiểm tra lại thành công qua HTTPS.

Nhật ký/kết quả chỉ nằm ở thư mục tạm: `.cache/mobile-https/review/`, `.cache/mobile-https/backup-latest.json`, `.cache/mobile-https/build.log`, `.cache/e2e/mobile-https-regression.log`. URL và thông tin truy cập riêng nằm trong `.cache/mobile-https/access.txt`, không ghi URL tạm hoặc mật khẩu phát sinh vào Git.

## Giới hạn của bước này

Đây là [Quick Tunnel phục vụ thử nghiệm](https://developers.cloudflare.com/tunnel/get-started/quick-tunnels/), cần máy tính/tiến trình đang chạy và có tên miền tạm đổi ở lần chạy tiếp theo. Chưa triển khai hosting cố định hoặc tiến trình nhắc nền. Manifest/icon/nút cài và service worker/mất mạng/cập nhật đã bổ sung ở các bước sau; [nghiệm thu ngày 01/11](pwa-mobile-acceptance-2026-11-01.md).

[Hướng dẫn CMD và tự thử điện thoại](mobile-https-self-test.md), [kế hoạch PWA](../../docs/pwa-mobile-plan-2026-10-30.md).
