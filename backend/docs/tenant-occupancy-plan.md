# Quản lý người thuê và quá trình thuê

Đã triển khai API backend cho các nghiệp vụ dưới đây. Hướng dẫn đường dẫn, dữ liệu gửi lên và ví dụ phòng một người: [tenant-occupancy.md](tenant-occupancy.md).

## Cần làm những việc gì?

| Nhóm chức năng | Chủ trọ thực hiện | Kết quả cần lưu |
| --- | --- | --- |
| Hồ sơ người thuê | Thêm, sửa, tìm họ tên, số điện thoại, CCCD nếu có | Một hồ sơ riêng cho từng người trong `tenants` |
| Nhóm thuê | Tạo một nhóm gồm những người thuê chung; nhóm có mã ổn định | `rental_groups`, giữ nguyên nhóm khi cả nhóm chuyển phòng |
| Nhận phòng | Chọn phòng, ngày bắt đầu, giá thuê đã chốt; kiểm tra phòng sẵn sàng và sức chứa | Một đợt ở phòng trong `occupancies` |
| Thành viên cư trú | Thêm người vào nhóm đang ở; ghi ngày vào/ra của từng người | `tenant_stays`, mỗi người chỉ có một lần cư trú đang mở |
| Người đại diện | Chọn người đang ở làm đại diện; kiểm tra hồ sơ và tài khoản đăng nhập hợp lệ | `representative_assignments`, giữ lịch sử thay đổi |
| Dịch vụ theo đợt thuê | Chọn dịch vụ, ngày áp dụng, đơn vị, cách tính, đơn giá | `occupancy_services`, mỗi cấu hình có khoảng hiệu lực riêng |
| Chuyển phòng | Chuyển nhóm sang phòng mới, kiểm tra sức chứa và phòng đang mở, giữ liên kết lịch sử | Đóng đợt cũ, tạo đợt mới cùng nhóm, liên kết `previous_occupancy_id` và giữ mức thuê theo thiết kế hiện có |
| Một thành viên rời đi | Ghi ngày ra; xử lý đổi đại diện nếu người ra là đại diện | Đóng `tenant_stays` tương ứng; đợt thuê có thể tiếp tục |
| Cả nhóm trả phòng | Ghi ngày kết thúc, đóng cư trú, đại diện và cấu hình dịch vụ còn mở | Kết thúc đợt thuê, phòng có thể tiếp nhận nhóm khác; dữ liệu cũ vẫn tra cứu được |
| Tra cứu lịch sử | Xem một người đã ở phòng nào, một phòng từng có những nhóm nào, ai đại diện từng thời điểm | Đọc lịch sử, không ghi đè hồ sơ thuê cũ |

## Ví dụ dễ hình dung

Nam và Lan thuê chung phòng A101 từ ngày 01/11, mức thuê đã chốt 3 triệu/tháng:

1. Tạo hồ sơ Nam, Lan; tạo nhóm thuê và một đợt ở A101.
2. Ghi ngày vào của cả hai; chọn Nam làm đại diện và cấu hình điện/nước cho đợt thuê.
3. Nếu Lan rời đi trước, ghi ngày ra của Lan; Nam vẫn tiếp tục đợt thuê.
4. Nếu cả nhóm chuyển sang A102, đóng đợt A101 và tạo đợt A102 có liên kết với đợt trước; lịch sử A101 vẫn còn.
5. Khi cả nhóm trả phòng, đóng các bản ghi đang có hiệu lực; hóa đơn và thanh toán cũ vẫn gắn với đúng đợt thuê.

Mỗi người cư trú có hồ sơ riêng, nhưng không bắt buộc ai cũng có tài khoản đăng nhập. Tài khoản, hồ sơ người thuê, nhóm thuê và đợt ở phòng là các dữ liệu có mục đích khác nhau.

## Quy tắc nghiệp vụ

- Không nhận nhóm mới vào phòng đang bảo trì hoặc đã có đợt thuê chưa kết thúc, kể cả khi chưa có thành viên.
- Tổng người đang ở không vượt sức chứa; một người không đồng thời có hai lần cư trú chưa kết thúc.
- Các mốc vào/ra và khoảng hiệu lực phải hợp lệ; chuyển phòng không tạo khoảng cư trú chồng chéo.
- Giá thuê và giá dịch vụ đã áp dụng phải giữ lịch sử; đổi giá danh mục không tự tính lại thời gian thuê cũ.
- Người đại diện phải thuộc đúng đợt thuê và đủ điều kiện tài khoản. Cấp/thu hồi quyền xem hóa đơn cần gắn với đúng đại diện và đúng hóa đơn.
- Thêm/bớt người, đổi đại diện, chuyển/trả phòng phải chạy trong transaction và kiểm tra lại dữ liệu sau khi khóa bản ghi liên quan.
- Xóa dữ liệu cũ không được làm mất lịch sử cư trú, hóa đơn hoặc thanh toán.

Luồng sử dụng: hồ sơ người thuê → nhóm thuê/nhận phòng → thành viên và đại diện → dịch vụ theo đợt thuê → chuyển/trả phòng và tra cứu lịch sử.
