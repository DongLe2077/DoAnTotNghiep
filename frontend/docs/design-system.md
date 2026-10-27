# Thiết kế chung: tối giản, thân thiện, ngăn nắp và an tâm

Cập nhật ngày **22/10/2026**. Đây là hướng thiết kế đã áp dụng vào giao diện xác thực, tài khoản, thông báo, trạng thái trang, bố cục hai vai trò và các bản mẫu. Các nghiệp vụ phòng/hóa đơn/người thuê tiếp tục triển khai theo kế hoạch riêng.

## Xem trực tiếp

Chạy `npm run dev` trong frontend và mở:

- [Bộ thiết kế chung](http://127.0.0.1:5173/design).
- [Mẫu quản lý phòng của chủ trọ](http://127.0.0.1:5173/design?view=owner).
- [Mẫu Phòng của tôi của người thuê](http://127.0.0.1:5173/design?view=tenant).

Trang `/design` mở được khi chưa đăng nhập và khi backend tắt. Toàn bộ phòng, giá, ngày tháng trong bản mẫu là dữ liệu tổng hợp; tìm kiếm, mở chi tiết và thử biểu mẫu không gọi API hoặc lưu dữ liệu. Trang thiết kế không cấp quyền vào các khu vực `/owner`/`/tenant`.

## Nguyên tắc

1. **Rõ ràng:** mỗi màn hình có tiêu đề, nội dung và hành động chính; số tiền/trạng thái có nhãn cụ thể.
2. **Vừa đủ:** dùng nền sáng, viền nhẹ, ít bóng đổ và chỉ vài màu có ý nghĩa. Hạn chế trang trí hoặc chỉ số không phục vụ công việc.
3. **Ngăn nắp:** căn hàng thống nhất, khoảng cách theo đơn vị 4/8 px; nhóm thông tin liên quan trong cùng một thẻ.
4. **An tâm:** thông báo ngắn, nói rõ kết quả và cách xử lý; thao tác quan trọng có bước xem lại. Không dùng số liệu giả trong các màn hình nghiệp vụ thật.
5. **Dễ tiếp cận:** chữ dễ đọc, nhãn luôn hiện, nút đủ lớn, dùng được bàn phím và không dùng riêng màu để truyền đạt trạng thái.

## Màu sắc

| Vai trò | Giá trị | Cách dùng |
| --- | --- | --- |
| Xanh chính | `#235C4B` | Nút chính, liên kết và điểm nhấn |
| Xanh hover | `#194A3C` | Phản hồi khi trỏ vào nút chính |
| Nền trắng ấm | `#F7F8F5` | Nền toàn trang |
| Thẻ/nội dung | `#FFFFFF` | Vùng đọc và biểu mẫu |
| Xanh rất nhạt | `#EDF4EE` | Mục được chọn, nền giới thiệu nhẹ |
| Chữ chính | `#233B32` | Tiêu đề và nội dung quan trọng |
| Chữ phụ | `#53665C` | Mô tả và hướng dẫn |
| Chữ ghi chú | `#66766B` | Chú thích, không dùng chữ mờ quá mức |
| Viền bố cục | `#DCE4DC` | Thẻ, đường phân nhóm |
| Viền ô nhập | `#85958A` | Ranh giới điều khiển dễ nhận biết |
| Focus bàn phím | `#25745A` | Viền ngoài khi điều hướng bằng phím |
| Thành công | `#286342` trên `#EDF6EF` | Kết quả đã hoàn thành |
| Cần chú ý | `#835D1E` trên `#FCF5E7` | Nhắc kiểm tra hoặc chờ xử lý |
| Lỗi/nguy hiểm | `#A43C32` trên `#FDF1EF` | Lỗi hoặc thao tác cần cân nhắc |
| Thông tin | `#375D7C` trên `#EEF4F8` | Hướng dẫn và chú thích bản mẫu |

Các cặp chữ/nền chính được kiểm tra tự động ở mức tương phản từ 4,5:1; viền input và focus trên nền trắng từ 3:1. Đây là kiểm tra bảng màu, không phải tuyên bố đã đánh giá toàn bộ ứng dụng theo WCAG. Tham chiếu: [tương phản chữ](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html), [focus nhìn thấy được](https://www.w3.org/WAI/WCAG22/Understanding/focus-visible.html).

## Kiểu chữ và khoảng cách

- **Be Vietnam Pro**, lưu trong `public/fonts/be-vietnam-pro/`, gồm trọng lượng 400/500/600/700 và bộ ký tự Latin/tiếng Việt. Tổng font WOFF2 khoảng 136 KB, không gọi Google Fonts khi người dùng mở trang.
- Dự phòng Segoe UI/Arial khi font chưa tải; `font-display: swap` để nội dung vẫn đọc được.
- Tiêu đề trang 28–36 px, nội dung 14–16 px; ghi chú từ 12 px. Trang giới thiệu bộ thiết kế có tiêu đề lớn hơn.
- Chiều cao dòng khoảng 1,6; nhãn 14 px, hạn chế chữ in hoa và giãn chữ quá mức.
- Ô nhập trên điện thoại 16 px; nút/ô nhập cao tối thiểu 44 px, ưu tiên 48 px trên điện thoại.
- Khoảng cách 4/8/12/16/24/32/40/48 px. Thẻ bo 16 px; điều khiển bo 10 px. Bóng đổ tập trung ở hộp thoại.

Nguồn font: [Google Fonts — Be Vietnam Pro](https://github.com/google/fonts/tree/main/ofl/bevietnampro), giấy phép [SIL OFL](../public/fonts/be-vietnam-pro/OFL.txt) được lưu cùng font, không chỉnh sửa thiết kế font.

## Thành phần đã có

Trong `src/components/ui/`:

- `UiButton`: nút chính/phụ/nhẹ/nguy hiểm; hỗ trợ disabled/loading.
- `UiBadge`: trạng thái có nhãn và màu đi kèm.
- `UiField`: nhãn, hướng dẫn và lỗi; liên kết mô tả với ô nhập.
- `UiNotice`: thông tin/thành công/cần chú ý/lỗi, có ngữ nghĩa đọc màn hình.
- `UiEmptyState`: mẫu rỗng/lỗi/đang tải.
- `UiPageState`: trạng thái trang đang tải/rỗng/lỗi/không có quyền/không tìm thấy, có vùng hành động và cấp tiêu đề phù hợp.
- `UiDialog`: hộp thoại native, mô tả rõ, Escape, giới hạn focus và trả focus.

`src/styles/main.css` giữ token và kiểu ô nhập/thẻ dùng chung. `src/lib/format.ts` định dạng chuỗi tiền và ngày để hiển thị; tổng tiền và quy tắc nghiệp vụ vẫn do backend quyết định. Ngày chỉ có ngày không bị đổi theo múi giờ; timestamp phải có múi giờ rõ ràng.

`src/lib/notifications.ts` đọc timestamp thông báo/sửa chữa dạng `Y-m-d H:i:s` theo quy ước giờ Việt Nam của Laravel và hiển thị tại `Asia/Ho_Chi_Minh`. Màn hình `/notifications` dùng danh sách thẻ phân trang qua API, số chưa đọc và hộp thoại tóm tắt chỉ đọc, lấy lại nội dung theo quyền hiện tại khi mở. Không đưa dữ liệu thông báo vào gallery `/design` công khai.

Mẫu bảng/tìm kiếm/lọc/chi tiết và biểu mẫu thêm phòng nằm ở `src/design/OwnerRoomsPreview.vue`; mẫu phòng/dịch vụ của người thuê ở `TenantRoomPreview.vue`. Đây là mẫu bố cục, chưa phải thành phần bảng phân trang dùng API. Gallery/upload ảnh và các thành phần nghiệp vụ phức tạp sẽ bổ sung khi làm module tương ứng.

## Bố cục theo vai trò

- **Chủ trọ:** sidebar riêng, nội dung rộng cho bảng và bộ lọc; khi màn hình nhỏ dùng thẻ hoặc sắp xếp lại thông tin. Thao tác chính ở đầu vùng nội dung.
- **Người thuê:** thông tin phòng và giá **toàn phòng** rõ ngay đầu; dịch vụ thành danh sách; hành động dễ chạm. Quyền xem hóa đơn và gửi báo hỏng vẫn theo backend khi tích hợp.
- Giữ nguyên xác thực cookie/CSRF, đăng xuất và đổi mật khẩu. Đổi thiết kế không thay đổi dữ liệu hoặc quyền tài khoản.

## Kiểm tra

- `npm test`: tương phản token, tiền/ngày/giờ, trạng thái xác thực/điều hướng và thông báo/riêng tư giữa các phiên.
- `npm run build`: TypeScript và bản đóng gói.
- `npm run test:e2e`: xác thực, thông báo/trạng thái dùng chung với Laravel thử riêng và các bản mẫu. Dùng `PLAYWRIGHT_CHANNEL=chrome` nếu chạy Chrome đã cài.
- Kiểm tra bản mẫu tại 360, 390, 768 và 1280 px; kiểm tra bàn phím, dialog, lỗi trường và không có API/tài nguyên bên ngoài trên `/design`.
- Ảnh xem lại nằm trong `.cache/design/screenshots/`; ảnh xác thực/thông báo trong `.cache/e2e/screenshots/`, đều ngoài Git.

Theo dõi tiếp: [kế hoạch chung](../../backend/docs/frontend-plan.md), [chủ trọ](../../backend/docs/frontend-owner-plan.md), [người thuê](../../backend/docs/frontend-tenant-plan.md).
