# Backend đã có và việc còn lại

Cập nhật ngày 20/10/2026.

Kiểm tra mới nhất sau bổ sung nhật ký/ảnh/thông báo: toàn bộ 279 kiểm thử đạt (2.660 assertions), 21 bước tích hợp MySQL thành công, dữ liệu 19 bảng không đổi sau rollback và ảnh thử đã dọn. Ánh xạ 19 bảng nghiệp vụ/70 quan hệ hợp lệ; dữ liệu các cột cũ của 16 bảng gốc được đối chiếu giữ nguyên khi áp dụng migration.

## Đã có

- Laravel/MySQL, 19 bảng nghiệp vụ/70 quan hệ sau nâng cấp; đăng nhập cookie session, CSRF, hai vai trò và kiểm tra tài khoản khóa.
- Phòng/dịch vụ; hồ sơ/tài khoản người thuê; nhóm thuê, nhận phòng, thành viên, đại diện, dịch vụ theo kỳ, chuyển/trả phòng.
- Chỉ số điện nước, xem trước/phát hành hóa đơn, quyền xem riêng từng hóa đơn.
- Thu tiền một phần/toàn bộ bằng tiền mặt/chuyển khoản xác nhận thủ công, chống thu vượt/gửi trùng.
- Thu khác/chi phí, sổ thu chi, tổng hợp ngày/tháng; báo hỏng và cập nhật xử lý.
- Tổng quan và công nợ theo hóa đơn/phòng/nhóm, phân biệt công nợ với thực thu/chi.
- Người thuê xem hồ sơ/phòng/dịch vụ hiện tại/lịch sử của mình; đổi mật khẩu có xác minh và hết hiệu lực các phiên cũ.
- Nhật ký/hoàn tác thu chi và khôi phục khoản xóa; ảnh báo hỏng riêng tư; hộp thông báo cá nhân, nhắc hóa đơn và cập nhật sửa chữa. [Chi tiết](audit-photos-notifications.md).

## Cần hoàn thiện trước bàn giao

1. **Tích hợp và kiểm thử giao diện:** chức năng hiện tại là API. Cần kiểm tra cookie/CSRF với frontend thật, hết phiên, nhập liệu, lỗi mạng/gửi lại và luồng nhận phòng → chỉ số → hóa đơn → thu tiền → trả phòng, trên máy tính/điện thoại.
2. **Chống gửi trùng cho khoản thu khác/chi phí:** thanh toán hóa đơn đã có khóa chống trùng; POST thu chi vẫn tạo mỗi lần một bản ghi. Trước mắt frontend khóa nút khi gửi và đối chiếu khi mất kết nối; lưu khóa bền vững để tự động thử lại cần thay đổi schema có kiểm soát.
3. **Tài liệu API và dữ liệu demo:** các tài liệu module đã có; cần bộ request/luồng demo liên tục, dữ liệu demo riêng, đối chiếu quy ước response/lỗi với frontend.
4. **Mã nguồn và môi trường:** đã khởi tạo Git, liên kết kho `DongLe2077/DoAnTotNghiep` và bổ sung hướng dẫn cài đặt ở README gốc. Giữ `.env`, dữ liệu tải lên và tệp chạy tạm ngoài Git; tiếp tục quản lý thay đổi và kiểm tra quy trình cài trên máy khác.
5. **Vận hành:** cấu hình tên miền, HTTPS, cookie/CORS, tắt debug, quyền thư mục, theo dõi lỗi, sao lưu định kỳ và thử khôi phục; đánh giá hiệu năng báo cáo theo lượng dữ liệu thực tế. Chưa triển khai hoặc lập lịch sao lưu.

Không cần chờ thêm nghiệp vụ mới để bắt đầu frontend. Sau khi giao diện dùng được, hoàn thiện các mục bàn giao và tài liệu đề tài.

## Mở rộng khi đặc tả yêu cầu

- Điều chỉnh/hủy hóa đơn và hoàn tiền: hóa đơn/biên nhận đã phát hành hiện được giữ nguyên, chưa có quy trình bù trừ.
- Xuất hóa đơn/biên nhận PDF và báo cáo Excel.
- Phân công thợ, thông báo ngoài ứng dụng qua email/SMS/Zalo hoặc đẩy thời gian thực. Nhắc hóa đơn đã có lịch 08:00, cần tiến trình lập lịch Laravel hoạt động để chạy tự động.
- Đối soát ngân hàng/thanh toán trực tuyến; hiện chuyển khoản do chủ trọ xác nhận thủ công.
- Quên mật khẩu tự phục vụ: hiện tự đổi khi biết mật khẩu cũ; chủ trọ đặt lại tài khoản người thuê qua chức năng quản lý tài khoản. Chưa có kênh email/SMS khôi phục, bảng users hiện không có email.
- Hợp đồng, tiền cọc hoặc hạn thanh toán nếu đề tài yêu cầu; chưa mặc định thêm vào 16 bảng hiện có.
