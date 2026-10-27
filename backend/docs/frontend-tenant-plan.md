# Kế hoạch frontend riêng cho người thuê

Cập nhật ngày **23/10/2026**. NT1–NT4 đã có giao diện và kết nối API: phòng/hồ sơ/lịch sử, hóa đơn/biên nhận, báo hỏng/ảnh/tiến độ, thông báo/tài khoản. Ưu tiên điện thoại, đã có thanh điều hướng bốn mục. [Hướng dẫn tự kiểm tra](../../frontend/docs/tenant-self-test.md). Phần dưới lưu phạm vi và checklist nghiệm thu.

## 1. Phạm vi và bố cục

Người dùng trong tài liệu này là **người thuê có tài khoản**. `/tenant` đã hiển thị phòng/dịch vụ thật; `/tenant/profile`, `/tenant/stays`, `/tenant/invoices` và chi tiết, `/tenant/maintenance` và tạo/chi tiết đều có màn hình nghiệp vụ. Từ thông báo mở được bản tóm tắt tải mới và liên kết tới trang đầy đủ theo vai trò. Đăng nhập/đăng xuất/đổi mật khẩu dùng lại nền tảng hiện có.

- Ưu tiên điện thoại: thông tin dễ đọc, nút thao tác rõ, chi tiết tiền và ảnh xem được trên màn hình hẹp.
- Điều hướng: Phòng của tôi, Hóa đơn, Báo hỏng, Lịch sử cư trú; tài khoản/hồ sơ và thông báo luôn dễ truy cập.
- Trang vào sau đăng nhập là Phòng của tôi, ghép từ thông tin hồ sơ/phòng/dịch vụ API đã cung cấp.
- Khi chưa có phòng hoặc đã trả phòng, hiển thị đúng trạng thái và vẫn cho xem dữ liệu lịch sử còn quyền.
- Không yêu cầu người thuê tự đăng ký; tài khoản được quản lý bằng nghiệp vụ chủ trọ hiện có.

Nền tảng P0 và bản đầu TK0 đã có: mẫu Phòng của tôi/giá toàn phòng/dịch vụ ở `/design?view=tenant` với dữ liệu minh họa, chưa gọi API. [Quy chuẩn thiết kế](../../frontend/docs/design-system.md). Có thể nối NT1 bằng dữ liệu thử vì backend đã có API. Nghiệm thu cùng CT3 để kiểm tra thay đổi nơi ở, NT2 cùng CT4–CT5 và NT3–NT4 cùng CT7. Đây là mốc kiểm tra cả hai phía, không phải điều kiện phải chờ để bắt đầu thiết kế người thuê.

## Bản thiết kế màn hình cần chuẩn bị

| Gói | Bố cục ưu tiên điện thoại | Thao tác và trạng thái cần rõ |
| --- | --- | --- |
| NT1 Phòng của tôi | Thẻ thông tin phòng, giá thuê toàn phòng, dịch vụ, thời gian ở; liên kết hóa đơn/báo hỏng theo quyền | Có phòng, chưa có phòng, đã rời phòng; không chia tiền phòng thành khoản cá nhân nếu backend không có nghiệp vụ. |
| NT1 Thông tin cá nhân | Các nhóm thông tin đọc được trên màn hình hẹp, liên kết tài khoản/đổi mật khẩu | Xem hồ sơ bản thân; thông tin nhân thân do chủ trọ cập nhật. |
| NT1 Lịch sử cư trú | Danh sách/thẻ theo thời gian, phòng, ngày nhận/trả và thông tin API cho phép | Xem các lần cư trú của chính mình, có trạng thái chưa có lịch sử. |
| NT2 Hóa đơn của tôi | Bộ lọc kỳ/trạng thái; thẻ hóa đơn có tổng/đã thu/còn thiếu; trang chi tiết từng dòng và lịch sử thanh toán | Mở hóa đơn được cấp quyền; trạng thái bị thu hồi quyền; chưa có thanh toán trực tuyến. |
| NT3 Báo hỏng | Danh sách yêu cầu của mình; biểu mẫu nội dung + nơi đủ quyền + ảnh; chi tiết tiến độ/kết quả | Gửi khi API cho phép; tải ảnh/thử lại ảnh; giải thích khi không đủ quyền tạo nhưng vẫn có lịch sử được xem. |
| NT4 Thông báo — đã có bản dùng chung | Biểu tượng và số chưa đọc; danh sách đọc/chưa đọc; mở nội dung liên quan chỉ đọc | Đã có đọc một/tất cả, bộ lọc/phân trang và kiểm tra lại quyền; tiếp tục nghiệm thu luồng sau trả phòng cùng NT3. |
| Tài khoản dùng chung | Thông tin đăng nhập, đổi mật khẩu, đăng xuất dễ tìm | Sử dụng lại phần đã có; kiểm tra sau khi thay đổi bố cục người thuê. |

Điện thoại đã có bốn mục **Phòng · Hóa đơn · Báo hỏng · Cá nhân**; Cá nhân dẫn tới hồ sơ, lịch sử cư trú và tài khoản; thông báo ở thanh trên. Menu thu gọn vẫn chứa đủ chức năng. Máy tính dùng menu riêng của người thuê, không hiển thị các mục quản trị.

Mỗi mẫu cần trạng thái đang tải, không có dữ liệu, lỗi mạng/thử lại, hết phiên và mất quyền. Nút chính dễ chạm, trường nhập có nhãn và lỗi ngay gần trường; ảnh xem được mà không cần rời phiên đăng nhập.

## 2. Thứ tự xây dựng

### NT1. Thông tin cá nhân, phòng và lịch sử cư trú

Màn hình cần làm:

- **Thông tin cá nhân:** xem hồ sơ của chính tài khoản. Thông tin nhân thân do chủ trọ cập nhật; không thêm nút tự sửa khi API chưa có.
- **Phòng của tôi:** phòng/nhóm đang thuê, giá toàn phòng đã chốt, dịch vụ và thời gian sử dụng trong phạm vi API cho phép.
- **Lịch sử cư trú:** các đợt ở của bản thân, ngày nhận/trả và thông tin lịch sử được cấp.
- Trạng thái chưa có phòng/đã rời phòng; nút đổi mật khẩu và đăng xuất dùng chức năng chung P0.

Hoàn thành khi: chủ trọ nhận/chuyển/trả phòng ở CT3 thì người thuê thấy thông tin tương ứng; tải lại vẫn giữ phiên; không lộ hồ sơ/giấy tờ/lịch sử của người cùng phòng. Giá toàn phòng không được trình bày thành tiền riêng của một người.

### NT2. Hóa đơn và lịch sử thanh toán

Màn hình cần làm:

- **Hóa đơn của tôi:** danh sách, lọc theo kỳ/trạng thái trong khả năng API, chỉ các hóa đơn được cấp quyền.
- **Chi tiết hóa đơn:** kỳ tính, tiền phòng, từng dịch vụ/chỉ số nếu API trả, tổng tiền, đã thu và còn thiếu.
- **Lịch sử thanh toán:** các khoản đã được chủ trọ ghi nhận và thông tin biên nhận được phép xem.
- Sau chủ trọ thu tiền, tải lại số dư và lịch sử; thông báo rõ khi quyền bị thu hồi hoặc phiên hết hạn.

Hoàn thành khi: CT4 phát hành/cấp quyền → người thuê xem → CT5 thu một phần/đủ → người thuê thấy đúng kết quả. Thử tài khoản không có quyền, đã bị thu hồi và đại diện mới để kiểm tra dữ liệu riêng tư.

Phạm vi hiện tại là xem thanh toán đã được ghi nhận. Chưa có luồng thanh toán trực tuyến hoặc tự xác nhận đã trả tiền. Có tài khoản hay là đại diện không tự cấp quyền xem mọi hóa đơn.

### NT3. Báo hỏng, ảnh và theo dõi sửa chữa

Màn hình cần làm:

- **Yêu cầu của tôi:** danh sách do chính tài khoản tạo, trạng thái và thời gian cập nhật.
- **Gửi báo hỏng:** chọn nơi đủ quyền, nhập nội dung; chỉ cho tạo nếu API cho phép, hiện lý do khi không đủ quyền.
- **Ảnh:** tải tối đa 5 ảnh/yêu cầu, mỗi ảnh tối đa 5 MB và kiểm tra định dạng/kích thước theo backend. Tạo yêu cầu xong mới tải ảnh; ảnh lỗi được thử lại riêng.
- **Chi tiết và tiến độ:** nội dung/ảnh, trạng thái, ghi chú/kết quả được phép xem và cập nhật từ chủ trọ.

Hoàn thành khi: đại diện hiện tại đủ quyền gửi được, thành viên thường không gửi được; chỉ tác giả xem yêu cầu/ảnh của mình kể cả sau rời phòng; đổi đại diện không chuyển quyền sở hữu lịch sử. Ảnh được mở qua API có phiên đăng nhập.

Làm nối tiếp CT7 để kiểm tra: gửi → tiếp nhận → đang xử lý → hoàn tất; lỗi upload không tạo lại yêu cầu.

### NT4. Thông báo, tài khoản và hoàn thiện trải nghiệm

Phần dùng chung và liên kết nghiệp vụ NT1–NT3 đã có:

- **Thông báo:** đã có số chưa đọc, danh sách nhắc hóa đơn/cập nhật sửa chữa, bộ lọc/phân trang, đọc một/tất cả và mở tóm tắt đối tượng theo quyền. Dữ liệu riêng tư được xóa khi đổi phiên/tài khoản.
- **Tài khoản:** hoàn thiện liên kết hồ sơ, đổi mật khẩu, đăng xuất từ menu người thuê; dùng lại chức năng P0.
- Hoàn thiện trạng thái tải/rỗng/lỗi, thử lại có kiểm soát, điều hướng khi hết phiên và thao tác trên điện thoại.
- Khi nhận thông báo hoặc mở liên kết cũ, API kiểm tra lại quyền; thông báo hóa đơn đã thu đủ/thu hồi quyền tuân thủ backend.

Hoàn thành khi: tác giả nhận cập nhật sửa chữa kể cả sau rời phòng, số chưa đọc đúng, mở hóa đơn còn quyền; đổi mật khẩu yêu cầu đăng nhập lại. Không thực hiện lại giao dịch tiền từ khu vực người thuê.

## 3. Nghiệm thu riêng cho người thuê

| Tài khoản/tình huống | Kết quả cần có |
| --- | --- |
| Chưa có phòng | Thông báo trạng thái rõ, không lỗi trang. |
| Phòng một người | Xem đúng thông tin/dịch vụ; quyền hóa đơn theo cấp quyền, quyền báo hỏng theo API. |
| Đại diện phòng nhiều người | Tạo báo hỏng khi đủ quyền; không tự nhìn thấy mọi hóa đơn hay giấy tờ thành viên. |
| Thành viên thường | Xem hồ sơ/lịch sử của mình và hóa đơn đã cấp quyền; không có thao tác tạo báo hỏng khi API không cho. |
| Đổi đại diện | Quyền hiện tại được cập nhật; không tự có yêu cầu báo hỏng/hóa đơn cũ của người khác. |
| Đã rời phòng | Lịch sử bản thân, hóa đơn còn quyền và báo hỏng do mình tạo vẫn xem được theo API. |
| Mở URL người khác/chủ trọ | Bị chặn đúng quyền ở API và giao diện. |
| Điện thoại/mạng lỗi/hết phiên | Có trạng thái rõ, thao tác được, tải ảnh lại không tạo yêu cầu trùng. |

Quên mật khẩu, tự đăng ký, tự sửa thông tin nhân thân, thanh toán trực tuyến và trò chuyện trực tiếp không nằm trong kế hoạch frontend bản đầu. Chỉ bổ sung khi đã thống nhất nghiệp vụ và backend tương ứng.

Tham chiếu: [kế hoạch chung và checklist toàn hệ thống](frontend-plan.md), [kế hoạch chủ trọ](frontend-owner-plan.md), [API người thuê](tenant-portal.md), [ảnh và thông báo](audit-photos-notifications.md).
