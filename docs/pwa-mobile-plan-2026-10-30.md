# Kế hoạch đưa ứng dụng lên điện thoại bằng PWA — 30/10/2026

**Cập nhật 01/11:** đã hoàn thiện mã bước 1–4, bộ nghiệm thu tự động bước 5 và tài liệu/công cụ bàn giao bước 6: HTTPS riêng, cài từ website, mở lại khung công khai khi mất mạng, cập nhật có xác nhận giữa các tab và cải thiện thao tác điện thoại. [Nghiệm thu hiện tại](../frontend/docs/pwa-mobile-acceptance-2026-11-01.md), [cài Android](../frontend/docs/mobile-android-guide.md). Người dùng chọn Android/Chrome; cài/mở biểu tượng, camera và bàn phím trên thiết bị thật còn chờ người dùng đối chiếu. Tài khoản mẫu admin / 123, user / 123 giữ theo lựa chọn trước.

**Lựa chọn của người dùng:** cài từ website, có biểu tượng trên màn hình chính. Manifest tĩnh, sự kiện cài của trình duyệt và service worker Workbox đã được triển khai; chưa có hosting cố định. Các mục dưới giữ tiêu chí/thứ tự của kế hoạch để đối chiếu. Quản lý hợp đồng tiếp tục nằm ngoài phạm vi khóa luận.

**Yêu cầu tiếp theo ngày 01/11:** chạy độc lập với máy Windows, ưu tiên miễn phí và hoàn thiện chi tiết cài trên điện thoại. [Kế hoạch hosting miễn phí + MySQL/ảnh bền vững + trang cài/QR + nghiệm thu tắt Windows](free-hosting-mobile-plan-2026-11-01.md). Chưa triển khai tài khoản cloud hoặc thay URL tạm hiện tại.

## Kết quả cần đạt

- Chủ trọ và người thuê mở cùng một địa chỉ HTTPS, cài lên màn hình chính và khởi động trong cửa sổ ứng dụng.
- Giữ Vue 3/TypeScript/Vite ở frontend và Laravel/MySQL ở backend; dùng tiếp API, phiên đăng nhập và phân quyền hiện có.
- Cả hai vai trò sử dụng nghiệp vụ đã hoàn thành; nhấn biểu tượng sẽ kiểm tra phiên rồi vào đúng khu vực hoặc yêu cầu đăng nhập.
- Giao diện trên điện thoại phù hợp bàn phím cảm ứng, vùng tai thỏ/thanh điều hướng, chọn ảnh sự cố và tải PDF.
- Khi mất mạng, thông báo rõ trạng thái và cách thử lại; dữ liệu đã xác nhận vẫn dựa vào phản hồi máy chủ.

## Điểm xuất phát đã kiểm tra

| Phần | Hiện trạng |
| --- | --- |
| Giao diện | Đã có bố cục màn hình hẹp, menu chủ trọ, thanh điều hướng dưới của người thuê và vùng đệm an toàn phía dưới |
| Đăng nhập | Axios gọi `/api` cùng nguồn, cookie session/CSRF; Pinia giữ trạng thái, hai vai trò có kiểm tra API/router |
| Chống gửi trùng | Thu tiền, thu chi và nhập nhiều phòng đã có các cơ chế đối chiếu/bản chờ tương ứng; giữ cách gửi lại thủ công |
| Ảnh/PDF | Ảnh sự cố riêng tư; JPG/PNG/WebP, tối đa 5 MB/ảnh, 5 ảnh/yêu cầu; PDF tải qua API có quyền |
| PWA | Đã có manifest/icon/cài, precache công khai, trạng thái mạng và cập nhật có xác nhận; không cache API hoặc tự gửi lại giao dịch |
| Môi trường | Các bản đang có hướng dẫn chạy cục bộ; Vite proxy là cấu hình phát triển, chưa thay thế cấu hình API của hosting |
| Kiểm thử đã có | Lượt toàn bộ gần nhất: 94 kịch bản Chrome, 131 kiểm thử frontend và đóng gói đạt; chưa chứng minh cài PWA trên điện thoại thật |

## Thứ tự thực hiện

| Bước | Công việc | Đầu ra / điều kiện hoàn thành |
| --- | --- | --- |
| 1 | Chuẩn bị địa chỉ HTTPS và môi trường thử | Điện thoại mở được web/API, đăng nhập và xem ảnh/PDF bằng dữ liệu giả |
| 2 | Thêm nhận diện và khả năng cài | Tên/icon/màu ứng dụng, manifest hợp lệ, hướng dẫn cài theo nền tảng |
| 3 | Xử lý mất mạng và cập nhật phiên bản | Khung giao diện có thể mở lại; báo cần mạng; bản mới chờ người dùng xác nhận cập nhật |
| 4 | Hoàn thiện thao tác điện thoại | Hai vai trò dùng được bàn phím, menu, nút quay lại, ảnh sự cố và PDF |
| 5 | Nghiệm thu PWA | Kiểm tra bản đóng gói, cache/quyền/phiên và cài thật trên Android/iPhone được chọn |
| 6 | Bàn giao cho người dùng/giảng viên | URL HTTPS, hướng dẫn cài, tài khoản thử, kết quả và bản sao khôi phục |

Có thể bắt đầu bước 2–3 tại máy trong lúc chuẩn bị bước 1; nghiệm thu cài từ điện thoại cần địa chỉ phù hợp và thiết bị thật.

## Bước 1 — Chuẩn bị môi trường cho điện thoại

1. Chốt tên miền/địa chỉ thử và nơi chạy được Laravel/PHP/MySQL, lưu ảnh bền vững. Dùng dữ liệu giả riêng trong đợt thử đầu.
2. Đề xuất phục vụ Vue và `/api/*` cùng một nguồn HTTPS, tận dụng cách xác thực hiện tại. API và đường ảnh/PDF phải đến Laravel trước luật mở trang Vue; trang giao diện tải lại được bằng URL trực tiếp.
3. Cấu hình địa chỉ ứng dụng, cookie HTTPS, proxy tin cậy, quyền thư mục và tiến trình nhắc thanh toán phù hợp môi trường. Rà header chống lưu đệm đối với nội dung riêng tư.
4. Giữ cấu hình bí mật/CSDL/ảnh ngoài kho mã nguồn. Tạo bản sao và kiểm tra khôi phục cả CSDL lẫn ảnh trước khi cho thử rộng hơn.
5. Điện thoại thử đăng nhập hai vai trò, xem hóa đơn, tải PDF và xem ảnh sự cố ngay trên bản web trước khi thêm khả năng cài.

`127.0.0.1` trên điện thoại trỏ về chính điện thoại, không phải máy Windows đang chạy dự án. HTTP qua IP Wi-Fi có thể dùng kiểm tra bố cục ở môi trường phù hợp, nhưng không được coi là nghiệm thu PWA qua HTTPS. Điều kiện cài của trình duyệt được mô tả trong [hướng dẫn MDN](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Making_PWAs_installable). GitHub hiện giữ mã nguồn; phần Laravel/MySQL cần môi trường chạy ứng dụng.

**Thông tin cần có trước khi triển khai trực tuyến:** nơi triển khai, tên miền/URL thử và điện thoại dùng để nghiệm thu. Việc mua dịch vụ hoặc công khai bản chạy thực hiện ở bước triển khai được chọn sau kế hoạch này.

## Bước 2 — Nhận diện và cài đặt PWA

**Đã triển khai ngày 30/10:** manifest tĩnh được phục vụ đúng MIME và `no-cache`; icon thường/maskable 192/512 và iPhone 180 từ biểu tượng ngôi nhà. Nút cài dùng chung ở đăng nhập và thanh trên của hai vai trò, có hướng dẫn theo thiết bị, xử lý hủy/lỗi và ẩn khi mở dạng ứng dụng. Chrome thông thường không báo lỗi chặn cài; 29 bước bản build, 54 bước HTTPS đạt. Cài thật trên điện thoại còn cần người dùng thực hiện. [Chi tiết và cách thử](../frontend/docs/pwa-install-self-test.md).

- Dùng tên hiển thị **Nhà Trọ**, giữ bảng màu xanh hiện có; đồng bộ màu trong HTML/manifest với thiết kế.
- Tạo icon PNG 192/512, icon có vùng an toàn cho maskable và icon màn hình chính iPhone từ biểu tượng tòa nhà hiện có. Kiểm tra không bị cắt hình hoặc mờ.
- Thêm manifest với định danh ứng dụng ổn định, `start_url` và `scope` dự kiến `/`, `display: standalone`, tên và màu nền.
- Bổ sung chức năng **Cài ứng dụng** ở nơi dễ tìm. Nút mở lời mời cài chỉ khi trình duyệt cung cấp khả năng tương ứng; các nền tảng khác có hướng dẫn thao tác.
- Trên iPhone: hướng dẫn mở bằng Safari, dùng mục thêm vào màn hình chính và bật mở dạng ứng dụng khi có tùy chọn. Đối chiếu [hướng dẫn Apple](https://support.apple.com/en-lamr/guide/iphone/iphea86e5236/ios) trên đúng thiết bị.
- Khi ứng dụng đã chạy ở chế độ độc lập, giao diện nhận biết trạng thái cài và không tiếp tục nhắc cài trong cửa sổ đó. Người dùng vẫn dùng được bản web thông thường.

**Kỹ thuật đã dùng:** manifest tĩnh, `beforeinstallprompt` khi trình duyệt cung cấp; `vite-plugin-pwa` 2.0.0/Workbox 7.4.1 đã kiểm tra peer dependency khớp Vite 8, khóa phiên bản và build đạt. [MDN xác nhận service worker không bắt buộc để cài](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Making_PWAs_installable). Worker bổ sung khả năng mở lại/cập nhật; không bổ sung lưu đệm API hoặc hàng đợi yêu cầu ghi.

## Bước 3 — Mất mạng, lưu đệm và cập nhật

**Đã triển khai và nghiệm thu tự động 01/11:** chỉ precache khung/tài nguyên công khai, không cache dữ liệu riêng tư; giữ mã chờ, gửi lại thủ công; phiên bản mới chờ xác nhận và tất cả tab an toàn. Các tiêu chí dưới được đối chiếu trong bộ 31 bước PWA runtime.

### Phạm vi lưu đệm của bản đầu

| Nội dung | Cách xử lý đề xuất |
| --- | --- |
| Khung HTML tĩnh, JS/CSS, font và icon công khai | Cho phép lưu đệm theo phiên bản ứng dụng |
| API đăng nhập/tài khoản, hóa đơn, thu chi, hồ sơ, thông báo và nghiệp vụ | Luôn lấy từ mạng, không đưa vào Cache Storage của service worker |
| Ảnh riêng tư và PDF qua API | Lấy từ mạng và kiểm tra quyền như hiện tại; rà header `no-store` |
| Yêu cầu ghi dữ liệu | Người dùng xác nhận và gửi qua cơ chế hiện có; không tạo hàng đợi tự gửi lại nền |
| Bản chờ đã có của thu tiền/thu chi/chỉ số | Giữ mã/nội dung và phạm vi tài khoản; đối chiếu/gửi lại thủ công sau khi kết nối/đăng nhập phù hợp |

Khung được lưu đệm là giao diện công khai, không chứa kết quả tài khoản đã đăng nhập. Đường `/api/*` không được nhận HTML của Vue khi mạng/API lỗi. Kết nối mất hoặc tài khoản thay đổi phải giữ việc xóa dữ liệu cũ trên màn hình của ứng dụng hiện tại.

### Trải nghiệm mất mạng

- Mở ứng dụng khi không có mạng: thấy hướng dẫn cần kết nối và nút thử lại phù hợp; không hiển thị dữ liệu tài chính giả như đã tải thành công.
- Đang thao tác mà mất phản hồi: nói rõ kết quả chưa xác định khi phù hợp, giữ nguyên mã yêu cầu đang chờ và hướng dẫn đối chiếu.
- Mạng trở lại: người dùng chủ động tải lại/đối chiếu; trạng thái mạng của thiết bị chỉ là tín hiệu, kết quả API mới xác nhận máy chủ xử lý được.
- Bản PWA đầu cần mạng để xem dữ liệu nghiệp vụ mới và ghi nhận giao dịch. Khả năng mở khung khi mất mạng không đồng nghĩa đã có chế độ quản lý thu tiền ngoại tuyến.

### Cập nhật phiên bản

- Dùng thông báo **Có phiên bản mới**, cho chọn cập nhật hoặc để sau. Cách nhắc cập nhật được hỗ trợ trong [tài liệu Vite PWA](https://vite-pwa-org.netlify.app/guide/prompt-for-update).
- Kiểm tra thao tác đang gửi/bản nháp trước khi tải lại; phối hợp với cảnh báo chưa lưu và cơ chế bản chờ hiện có.
- Không đổi khóa bản chờ theo mỗi bản giao diện; bảo toàn khả năng đối chiếu yêu cầu cũ.
- Dọn cache giao diện cũ theo namespace của ứng dụng sau khi chuyển phiên bản phù hợp. Giữ kế hoạch quay lại bản phát hành trước và khả năng phục vụ các tệp của phiên bản đang dùng trong giai đoạn chuyển tiếp.

## Bước 4 — Hoàn thiện hai vai trò trên điện thoại

**Đã triển khai 01/11:** vùng an toàn/chiều cao bàn phím, camera/chọn ảnh, thời gian URL PDF và guard ảnh chưa gửi. Kiểm tra tự động 320–851px, xoay ngang và vùng bàn phím mô phỏng đạt; thao tác hệ điều hành/camera thật tiếp tục cần người dùng thử.

| Vai trò | Việc cần rà |
| --- | --- |
| Chủ trọ | Menu, tổng quan, bộ lọc, biểu mẫu nhận phòng, nhập nhiều chỉ số, xác nhận thu tiền và hộp thoại sửa chữa |
| Người thuê | Thanh điều hướng dưới, đọc hóa đơn, xem lần thu/PDF, gửi ảnh sự cố, lịch sử và hộp thông báo |
| Dùng chung | Vùng an toàn trên/dưới, bàn phím không che nút/ô nhập, xoay màn hình, nút quay lại, đăng nhập/đăng xuất/đổi mật khẩu |

- Rà `viewport-fit` và vùng đệm để biểu mẫu/thông báo cập nhật không che nội dung hoặc thanh điều hướng.
- Thử ảnh chụp/chọn từ máy trên Android và iPhone; đối chiếu định dạng/dung lượng thực tế với giới hạn ảnh hiện có. Nếu cần chuyển đổi/nén, làm bước xem lại rõ ràng trước khi gửi và vẫn kiểm tra tại backend.
- Thử tải/mở/chia sẻ PDF theo khả năng trình duyệt; giữ quyền truy cập trước khi nhận tệp.
- Khi quay lại ứng dụng sau thời gian ở nền, kiểm tra phiên và làm mới phù hợp trước thao tác quan trọng. Không dựa vào việc đã cài để bỏ kiểm tra quyền.
- Kiểm tra đăng xuất ở bản web/cửa sổ PWA. Không giả định Safari, Chrome và cửa sổ cài đặt luôn dùng chung phiên trên mọi thiết bị.

## Bước 5 — Bộ nghiệm thu

**Đã có nghiệm thu tự động trên bản build với worker và Laravel thật;** còn cài/gỡ/mở từ biểu tượng trên Android thật và các mục thiết bị trong [hướng dẫn bàn giao](../frontend/docs/mobile-android-guide.md).

| Nhóm | Điều kiện đạt |
| --- | --- |
| Manifest/icon | Bản build có các tệp đúng đường dẫn, nhận diện rõ và mở đúng nguồn/đường khởi động |
| Cài thật | Cài từ web trên Chrome Android; thêm và mở dạng ứng dụng trên Safari iPhone được chọn; ghi phiên bản hệ điều hành/trình duyệt thực tế |
| Vai trò/phiên | Chủ trọ/người thuê vào đúng khu vực; đăng xuất/hết phiên/thu hồi quyền vẫn chặn dữ liệu và thao tác cũ |
| Nghiệp vụ | Thử chỉ số → hóa đơn → thu tiền → người thuê đối chiếu; báo hỏng/ảnh → xử lý → lịch sử/thông báo; tải PDF |
| Mất mạng | Mở lại, chuyển trang, đang gửi rồi mất phản hồi và mạng trở lại đều có trạng thái thật; gửi lại không ghi trùng |
| Cache | Sau truy cập nghiệp vụ không thấy API, ảnh riêng tư, PDF hoặc phản hồi người dùng trong cache của service worker |
| Phiên bản mới | Có thể để sau; không bị tự tải lại giữa thao tác; cập nhật xong bản chờ vẫn đối chiếu được |
| Gỡ/cài lại | Mở và đăng nhập lại được; kiểm tra hành vi dữ liệu trình duyệt thay vì giả định gỡ biểu tượng sẽ xóa mọi dữ liệu |
| Bố cục | 320–430px, bàn phím và vùng an toàn không che nút; kiểm tra bàn phím/cảm ứng và giảm chuyển động |

Kiểm thử tự động chạy trên **bản đóng gói có service worker** và môi trường dữ liệu riêng. Bổ sung ca manifest/cache/offline/update vào kiểm thử trình duyệt, sau đó chạy các kiểm tra phù hợp với phần thay đổi. Chrome mô phỏng hoặc kiểm tra manifest không thay thế thao tác cài trên điện thoại thật; các con số 94/131 hiện có thuộc bản web trước đợt PWA.

## Bước 6 — Bàn giao

**Đã chuẩn bị:** hướng dẫn Android/iPhone, kết quả/giới hạn và `npm run mobile:checkpoint` tạo mốc mã nguồn/build/CSDL/ảnh, kiểm tra khôi phục vào thư mục riêng. URL hiện hành được cung cấp khi chạy; địa chỉ lâu dài cần hosting/tên miền cố định.

1. Cung cấp URL HTTPS và tài khoản thử riêng; người dùng cài từ địa chỉ đó.
2. Hướng dẫn bằng ảnh: cài Android/iPhone, cập nhật phiên bản, xử lý mất mạng, mở ảnh/PDF và cách đăng nhập hai vai trò.
3. Ghi mẫu điện thoại/phiên bản đã thử, các luồng đạt và giới hạn còn lại.
4. Chốt mốc mã nguồn/cấu hình triển khai, bản sao CSDL/ảnh và cách khôi phục trước buổi giảng viên xem.

## Thông báo khi đã đóng ứng dụng

Thông báo hiện tại là hộp thông báo và tải lại khi ứng dụng hoạt động. Thông báo đẩy khi đóng ứng dụng cần một đợt Web Push riêng: đăng ký thiết bị, xin quyền từ thao tác người dùng, gửi theo tài khoản/quyền hiện tại và hủy đăng ký phù hợp. [WebKit mô tả Web Push cho ứng dụng màn hình chính](https://webkit.org/blog/13878/) từ iOS/iPadOS 16.4; vẫn cần kiểm tra trên nền tảng mục tiêu. Đợt PWA đầu chưa bổ sung cơ chế này.

## Các tệp dự kiến liên quan khi bắt đầu lập trình

- `frontend/package.json` và lockfile: thư viện PWA tương thích.
- `frontend/vite.config.ts`: manifest, build/đăng ký service worker và quy tắc lưu đệm.
- `frontend/index.html`, `frontend/public/`: nhận diện, icon và metadata điện thoại.
- Thành phần cài/cập nhật/trạng thái mạng dùng chung; phần đăng ký PWA ở điểm khởi động giao diện.
- Điều phối cập nhật với trạng thái ghi và cảnh báo bản nháp; rà đường tải ảnh/PDF trên thiết bị thật.
- Kiểm thử bản build, hướng dẫn triển khai cùng nguồn và header nội dung riêng tư ở backend nếu rà soát thấy cần bổ sung.

**Gói triển khai đầu tiên:** môi trường HTTPS thử + cài PWA + trạng thái mất mạng + cập nhật có xác nhận + nghiệm thu hai vai trò. Các đề xuất nghiệp vụ mới khác được giữ trong kế hoạch riêng.
