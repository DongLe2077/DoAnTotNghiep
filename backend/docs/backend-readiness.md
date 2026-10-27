# Backend đã có và việc còn lại

Cập nhật ngày 25/10/2026.

Lịch sử xử lý sửa chữa đã có cho hai vai trò: từng lần đổi trạng thái/ghi chú trước sau, người thực hiện/thời điểm, phân trang, mốc dữ liệu cũ và ghi cùng giao dịch với yêu cầu/thông báo. Backend đạt 336 kiểm thử/3.428 assertions; frontend 119 kiểm thử, 25 kịch bản Chrome liên quan và bản đóng gói đạt. Kiểm tra MySQL mới đạt 14 bước và 23 bảng giữ nguyên sau rollback; ánh xạ 23 bảng/81 quan hệ/3 view hợp lệ. [Quy tắc và API](maintenance-history.md), [tự kiểm tra](../../frontend/docs/maintenance-history-self-test.md).

Đợt nghiệm thu giao diện trước khi bổ sung lịch sử (25/10/2026): backend đạt 328 kiểm thử/3.286 assertions, ánh xạ MySQL khi đó 22 bảng/78 quan hệ hợp lệ. Các sửa giao diện và kết quả trình duyệt được ghi trong [biên bản riêng](../../frontend/docs/ui-acceptance.md). Lượt đó kiểm tra nghiệp vụ bằng SQLite riêng; MySQL chỉ đọc ánh xạ.

Bổ sung mới: thông báo chung đã có API/giao diện chủ trọ và người thuê, xem trước người nhận, gửi toàn bộ/theo phòng, chống gửi trùng và theo dõi đã đọc. Kiểm tra hiện tại: 328 kiểm thử backend (3.286 assertions), 119 kiểm thử frontend và 27 kịch bản Chrome cho thông báo chung/hộp thông báo/đăng nhập đạt; đóng gói TypeScript/Vite và quy chuẩn PHP đạt. Kiểm tra thông báo chung trên MySQL đạt 15 bước và dữ liệu 22 bảng giữ nguyên sau rollback; ánh xạ 22 bảng/78 quan hệ/3 view hợp lệ. [Quy tắc/API](announcements.md), [tự kiểm tra](../../frontend/docs/announcements-self-test.md).

Kiểm tra tự động ngày 23/10/2026 sau bổ sung điều chỉnh/hủy và hạn đóng: 319 kiểm thử đạt (3.191 assertions). Nghiệm thu API xuyên suốt nhận phòng → điện nước → hóa đơn/điều chỉnh/thu tiền → ảnh báo hỏng/sửa chữa → chuyển/trả phòng → thu hết nợ đạt 57 assertions. Kiểm tra mới trên MySQL đạt 17 bước cho điều chỉnh/hủy/hạn đóng, 24 bước báo cáo/người thuê, 17 bước lập hóa đơn và 16 bước thu tiền; dữ liệu 21 bảng giữ nguyên sau rollback. Lượt kiểm tra tích hợp MySQL trước đó có 14 bước thành công và dữ liệu 20 bảng không đổi sau rollback. Kiểm tra đồng thời trên CSDL MySQL thử riêng đạt 3 lượt cùng mã/cùng nội dung, 3 lượt cùng mã/khác số tiền và mã khác hoa/thường; CSDL thử đã dọn. Ánh xạ hiện tại 21 bảng nghiệp vụ/75 quan hệ hợp lệ; dữ liệu 19 bảng có trước được đối chiếu giữ nguyên khi thêm migration chống trùng.

## Đã có

- Laravel/MySQL, 23 bảng nghiệp vụ/81 quan hệ sau nâng cấp; đăng nhập cookie session, CSRF, hai vai trò và kiểm tra tài khoản khóa.
- Phòng/dịch vụ; hồ sơ/tài khoản người thuê; nhóm thuê, nhận phòng, thành viên, đại diện, dịch vụ theo kỳ, chuyển/trả phòng.
- Chỉ số điện nước, xem trước/phát hành hóa đơn, quyền xem riêng từng hóa đơn.
- Thu tiền một phần/toàn bộ bằng tiền mặt/chuyển khoản xác nhận thủ công, chống thu vượt/gửi trùng.
- Thu khác/chi phí, sổ thu chi, tổng hợp ngày/tháng; báo hỏng và cập nhật xử lý.
- Nhật ký từng lần xử lý sửa chữa, trước/sau và tài khoản thực hiện; người gửi xem lịch sử của mình kể cả sau trả phòng. Không tự ghi chi phí. [Chi tiết](maintenance-history.md).
- Tổng quan và công nợ theo hóa đơn/phòng/nhóm, phân biệt công nợ với thực thu/chi.
- Người thuê xem hồ sơ/phòng/dịch vụ hiện tại/lịch sử của mình; đổi mật khẩu có xác minh và hết hiệu lực các phiên cũ.
- Nhật ký/hoàn tác thu chi và khôi phục khoản xóa; ảnh báo hỏng riêng tư; hộp thông báo cá nhân, nhắc hóa đơn và cập nhật sửa chữa. [Chi tiết](audit-photos-notifications.md).
- Chống gửi trùng thu khác/chi phí bằng mã yêu cầu bền vững; giữ dữ liệu gốc và không tạo lại khoản sau sửa/xóa/hoàn tác. [Chi tiết](cash-idempotency.md).
- Điều chỉnh tăng/giảm, hủy/khôi phục hóa đơn có lý do và lịch sử; hạn thanh toán, công nợ quá hạn và thông báo cập nhật. Dòng gốc và phiếu thu được giữ nguyên. [Chi tiết](invoice-controls.md).
- Thông báo chung toàn bộ/theo phòng: chốt người nhận là tài khoản hoạt động của người đang ở, gồm thành viên thường; lưu lịch sử gửi và trạng thái đọc, giữ quyền riêng tư sau chuyển/trả phòng. [Chi tiết](announcements.md).
- Frontend Vue: xác thực/tài khoản/thông báo và CT1–CT8 chủ trọ đã có màn hình/API, giữ cookie/CSRF và phân quyền; có bộ kiểm thử trình duyệt và dữ liệu mẫu riêng. [Chạy frontend](../../frontend/README.md), [CT1–CT8](../../frontend/docs/owner-management.md), [tự kiểm tra](../../frontend/docs/owner-self-test.md). NT1–NT4 người thuê đã có phòng/hồ sơ/lịch sử, hóa đơn, báo hỏng/ảnh và thông báo; [tự kiểm tra](../../frontend/docs/tenant-self-test.md).

## Cần hoàn thiện trước bàn giao

1. **Nghiệm thu và cài mới:** CT1–CT8 và NT1–NT4 đã có luồng trình duyệt với Laravel/SQLite riêng, gồm chủ trọ thu tiền/cấp/thu hồi quyền và người thuê gửi ảnh/theo dõi sửa chữa. Tiếp tục nghiệm thu thủ công trên dữ liệu thực và kiểm tra cài mới với MySQL trên máy khác.
2. **Frontend:** hai vai trò đã có màn hình/API; rà soát góp ý sử dụng, khả năng truy cập và thiết bị thực trước bàn giao. [Phạm vi](frontend-plan.md). Quên mật khẩu vẫn để sau theo quyết định trước đó.
3. **Tài liệu và demo:** đã có `npm run demo` cùng [hướng dẫn chủ trọ](../../frontend/docs/owner-self-test.md) và [người thuê](../../frontend/docs/tenant-self-test.md), dữ liệu SQLite tách MySQL. Tiếp tục hoàn thiện tài liệu API/bộ request và báo cáo đề tài khi bàn giao.
4. **Mã nguồn và môi trường:** đã khởi tạo Git, liên kết kho `DongLe2077/DoAnTotNghiep` và bổ sung hướng dẫn cài đặt ở README gốc. Giữ `.env`, dữ liệu tải lên và tệp chạy tạm ngoài Git; tiếp tục quản lý thay đổi và kiểm tra quy trình cài trên máy khác.
5. **Vận hành:** cấu hình tên miền, HTTPS, cookie/CORS, tắt debug, quyền thư mục, theo dõi lỗi, sao lưu định kỳ và thử khôi phục; đánh giá hiệu năng báo cáo theo lượng dữ liệu thực tế. Chưa triển khai hoặc lập lịch sao lưu.

Không cần chờ thêm nghiệp vụ mới để bắt đầu frontend. Sau khi giao diện dùng được, hoàn thiện các mục bàn giao và tài liệu đề tài.

## Mở rộng khi đặc tả yêu cầu

- Hoàn tiền có đối chiếu và chứng từ; hiện điều chỉnh hóa đơn không được giảm tổng xuống dưới số đã thu.
- Xuất hóa đơn/biên nhận PDF và báo cáo Excel.
- Phân công thợ, thông báo ngoài ứng dụng qua email/SMS/Zalo hoặc đẩy thời gian thực. Nhắc hóa đơn đã có lịch 08:00, cần tiến trình lập lịch Laravel hoạt động để chạy tự động.
- Đối soát ngân hàng/thanh toán trực tuyến; hiện chuyển khoản do chủ trọ xác nhận thủ công.
- Quên mật khẩu tự phục vụ: hiện tự đổi khi biết mật khẩu cũ; chủ trọ đặt lại tài khoản người thuê qua chức năng quản lý tài khoản. Chưa có kênh email/SMS khôi phục, bảng users hiện không có email.
- Hợp đồng và tiền cọc nếu đề tài yêu cầu; hạn thanh toán đã có.
