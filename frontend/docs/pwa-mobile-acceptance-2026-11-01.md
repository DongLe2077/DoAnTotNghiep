# Nghiệm thu PWA và bàn giao điện thoại — 01/11/2026

## Kết quả và phạm vi

Đã triển khai phần mã bước 3–4, bộ nghiệm thu tự động bước 5 và công cụ/tài liệu bàn giao bước 6. Người dùng chọn **Android/Chrome** và sẽ tự cài theo hướng dẫn khi quay lại. Không đánh dấu đã cài trên điện thoại thật thay cho người dùng. MySQL, dữ liệu người dùng thật và chức năng hợp đồng nằm ngoài đợt này.

### Mất mạng và dữ liệu

- Service worker dùng `vite-plugin-pwa` **2.0.0**, Workbox **7.4.1**, tương thích Vite 8 và Node hiện có; khóa phiên bản trong lockfile. Chỉ bật ở bản đóng gói, không đăng ký khi chạy Vite dev.
- Precache gồm `index.html`, JS/CSS, font và icon công khai được lọc đường dẫn; bản cuối có 123 tệp, khoảng 914 KiB. Có thể mở lại giao diện sau lần chuẩn bị thành công khi có mạng.
- Không có runtime cache API, ảnh riêng tư, PDF, đăng nhập hoặc hàng đợi tự gửi lại POST/PUT/PATCH/DELETE. Đường API không nhận HTML fallback khi mất mạng.
- API thực tế và session/CSRF vẫn xác nhận dữ liệu/quyền. Trang mở lại ngoại tuyến không dựng lại tài khoản/hóa đơn cũ từ cache; báo cần kết nối. Màn hình còn mở khi mất mạng được cảnh báo rằng nội dung có thể đã cũ.
- Tín hiệu online của thiết bị không được coi là kết quả giao dịch. Khôi phục mạng không tự gửi bản chờ; người dùng đối chiếu/gửi lại bằng cơ chế hiện có.

### Cập nhật có kiểm soát

- Không tự `skipWaiting` hoặc tự tải lại giữa thao tác. Hiện **Có phiên bản mới**, có **Để sau** và **Cập nhật ngay**.
- Chặn khi có yêu cầu API đang chạy, hộp thoại/menu đang mở, biểu mẫu đã nhập, ảnh chưa gửi, bản nháp có guard hoặc bản chờ tài chính thuộc tài khoản chủ trọ hiện tại.
- Worker chờ hỏi toàn bộ tab cùng nguồn; tất cả phải phản hồi an toàn. Tab không phản hồi hoặc còn thao tác sẽ hủy chuyển phiên bản và mở khóa các tab đã chuẩn bị.
- Khi mọi tab an toàn, tạm khóa thao tác rồi chuyển worker và mở lại các tab. Kiểm tra cả tab khác còn yêu cầu API, bản nháp và khóa bản chờ; không thay mã/nội dung của bản chờ hoặc xóa bản chờ thuộc tài khoản khác.
- Biểu mẫu vừa lưu mà vẫn được nhận diện đang nhập có thể cần trở về tổng quan trước khi cập nhật. Đây là cách xử lý thận trọng; không tự xóa nội dung để cho phép cập nhật.

### Thao tác điện thoại

- Bổ sung `viewport-fit=cover`, vùng an toàn và theo dõi chiều cao hiển thị khi bàn phím thu hẹp; hộp thoại thích ứng chiều cao, điều hướng dưới nhường vùng nhập khi phát hiện bàn phím.
- Bổ sung **Chụp ảnh sự cố** với lựa chọn camera sau khi thiết bị hỗ trợ, giữ chọn nhiều ảnh từ thiết bị/xem trước/giới hạn ảnh. Ảnh đã chọn chặn cập nhật cho tới khi gửi xong hoặc bỏ chọn.
- Luồng tải PDF giữ quyền backend; kéo dài thời gian tồn tại URL tạm để trình tải trên điện thoại tiếp nhận tệp.
- Không bỏ kiểm tra phiên khi mở từ biểu tượng. Các cơ chế kiểm tra lúc quay lại ứng dụng, đổi tài khoản và thông báo phiên giữa tab tiếp tục hoạt động.

## Kiểm tra đã thực hiện

| Nhóm | Kết quả |
| --- | --- |
| TypeScript/Vite và build service worker | Đạt, 123 tài nguyên công khai |
| Kiểm thử frontend | 131/131 đạt |
| HTTP gateway/manifest/worker/no-cache/giữ API | 4/4 nhóm đạt |
| Manifest/icon/vòng đời lời mời cài/bố cục | 29 bước đạt, Chrome hồ sơ thông thường không báo lỗi chặn cài |
| PWA runtime với Laravel và SQLite thật riêng | 31 bước đạt |
| Chrome các luồng hiện có | 94/94 đạt trong 20,6 phút; sau đó kiểm tra riêng PWA/lề an toàn và màn hình lập hóa đơn |
| HTTPS mới trên bản mobile, gồm worker/offline/ảnh/PDF/quyền và vùng an toàn | 69 bước đạt |
| Quy chuẩn PHP helper snapshot | Pint và cú pháp đạt |
| Phụ thuộc production | `npm audit --omit=dev`: 0 cảnh báo tại thời điểm kiểm tra |

Bộ 31 bước dùng **bản đóng gói có worker thật**, Laravel/SQLite/session riêng và kích thước 320/393/430/851px. Đã đối chiếu:

1. Hai vai trò/menu/điều hướng và xoay ngang; giả lập vùng hiển thị bị bàn phím thu hẹp.
2. Thu tiền khi offline giữ nguyên payload/mã; online không gửi lại; mở lại offline giữ bản chờ; gửi lại có xác nhận ghi đúng một khoản và xóa đúng bản chờ.
3. Mở lại ngoại tuyến có khung công khai; PDF không lấy từ cache hoặc HTML; đăng xuất rồi mở lại không lấy được hóa đơn cũ.
4. Nâng worker thật bằng bản phát hành thử: để sau; tab khác còn bản nháp hoặc API chưa xong chặn; màn hình lập hóa đơn có đợt thuê đã chọn cũng chặn dù không nằm trong thẻ form; bản chờ chặn; xác nhận khi an toàn thì cùng chuyển phiên bản.
5. Tải PDF bằng nút giao diện; người thuê gửi báo hỏng và ảnh PNG giả qua đầu vào camera, backend lưu riêng tư; quyền owner vẫn trả 403 với người thuê.
6. Kiểm tra từng URL trong Cache Storage sau hóa đơn, PDF và ảnh: chỉ tệp build công khai, không có API.

Đầu vào camera dùng tệp giả do bộ kiểm thử cung cấp; chưa chứng minh camera/phần cấp quyền Android thật. Giả lập visual viewport chưa thay cho bàn phím thật. Chưa xác nhận cài/gỡ/mở biểu tượng trên điện thoại, trình xem PDF của Android hoặc iPhone thật.

Lượt HTTPS cuối còn đặt vùng an toàn bằng Chrome CDP: trên 30px, dưới 34px; kiểm tra lề body, tài khoản trong menu, thanh điều hướng người thuê và hộp cài nằm trong vùng nhìn thấy. Đã tăng lề dưới cho body/menu và giới hạn chiều cao hộp thoại. Kết quả này là mô phỏng trình duyệt, không gắn với một mẫu điện thoại thật cụ thể.

Kết quả/ảnh tại `.cache/pwa-runtime-review/`, `.cache/pwa-install-review/`, `.cache/mobile-https/review/`. Tất cả dùng dữ liệu thử; bộ kiểm thử và các bản sao không mở kết nối MySQL.

Các phụ thuộc chạy ứng dụng đã được vá `follow-redirects`. Ghi đè `glob` trong `workbox-build` bằng bản vá 14.0.3 đã kiểm tra build. Một số cảnh báo thấp/vừa còn nằm trong cây công cụ build (như deepmerge/ejs và phụ thuộc của chúng); không áp dụng tự động phương án npm đề xuất hạ plugin về bản không khớp Vite 8. Không coi kết quả audit production là bằng chứng mọi công cụ build đều hết cảnh báo.

## Cách kiểm tra lại

Tại frontend:

```bat
npm run build
npm test
node --test scripts/mobile-gateway.test.mjs
node scripts/review-pwa-install.mjs
node scripts/review-pwa-runtime.mjs
```

Lệnh runtime cần Chrome/PHP SQLite và hai cổng thử 15173/18000 trống; dùng `.cache/e2e`, không chạy đồng thời với bộ Playwright hiện có trên cùng cổng. Khi mobile HTTPS đang chạy, dùng `node scripts/review-mobile-https.mjs`.

## Bàn giao và việc người dùng còn thực hiện

- [Hướng dẫn cài/sử dụng Android, tài khoản, PDF/ảnh/mất mạng/cập nhật](mobile-android-guide.md).
- [Chạy/dừng HTTPS tạm](mobile-https-self-test.md), [hướng dẫn cài iPhone](pwa-install-self-test.md).
- `npm run mobile:checkpoint` khi đã dừng cổng mobile: mã nguồn đang có/build/CSDL/ảnh/thông tin truy cập riêng, phục hồi thử vào thư mục mới và đối chiếu bản ghi/số dư/ảnh. Kết quả dưới `.cache/mobile-checkpoints`, giữ ngoài Git.
- Đã tạo mốc mã nguồn/build/CSDL/ảnh và kiểm tra khôi phục; bản ghi, số dư của 30 bảng SQLite và 3 ảnh khớp. Mốc cuối, mã kiểm tra và số tệp nguồn nằm trong `manifest.json` của thư mục checkpoint. Số 30 gồm bảng nghiệp vụ và metadata fixture; không phải số bảng mới trong MySQL.
- URL chạy và tài khoản mẫu nằm ở `.cache/mobile-https/access.txt`; không ghi URL tạm vào mã nguồn. Máy tính phải tiếp tục hoạt động. Địa chỉ thay đổi thì biểu tượng cũ cần cài lại; hosting/tên miền cố định là bước triển khai riêng.
- Người dùng cài trên Android, ghi mẫu máy/phiên bản và đánh dấu kết quả trong hướng dẫn. Ghi rõ các kết quả thiết bị thật còn trống trước khi trình giảng viên.
- Thông báo đẩy khi đã đóng ứng dụng, ứng dụng cửa hàng và vận hành offline nghiệp vụ tài chính chưa thuộc PWA đầu tiên.
