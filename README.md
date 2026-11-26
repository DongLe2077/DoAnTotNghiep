# RentBase — Hệ thống Quản lý Nhà trọ & Sàn Tìm phòng Trực tuyến

Hệ thống quản lý nhà trọ hiện đại, hỗ trợ mô hình **Đa khu trọ (Multi-Tenancy)**, **Sàn tìm phòng công khai (Room Marketplace)**, **Tự đăng ký & Mã mời (Onboarding & Invites)**, **Thanh toán trực tuyến payOS theo khu**, **Xóa an toàn chờ 3 ngày (Scheduled Deletions)**, **Báo cáo cư trú & tài chính theo tháng (Monthly Snapshots)**, **Đồng bộ thời gian thực (Live Updates / Pusher & Polling fallback)** và ứng dụng di động **PWA (Progressive Web App)**.

> [!IMPORTANT]
> **Phạm vi khóa luận tốt nghiệp:** Dự án tập trung vào quản lý vận hành, cư trú, dịch vụ, hóa đơn, thanh toán trực tuyến, thu chi, sửa chữa, thông báo và trải nghiệm tìm phòng công khai; **không thực hiện quản lý hợp đồng pháp lý** (theo phạm vi đã thống nhất). Chi tiết xem tại [Các đề xuất nghiệp vụ](docs/additional-feature-ideas-2026-10-30.md).

---

## Mục lục

- [Hiện trạng & Triển khai thực tế](#hiện-trạng--triển-khai-thực-tế)
- [Tiến độ nâng cấp RentBase Redesign (Lộ trình 8 đợt)](#tiến-độ-nâng-cấp-rentbase-redesign-lộ-trình-8-đợt)
- [Kiến trúc & Công nghệ](#kiến-trúc--công-nghệ)
- [Tính năng hệ thống](#tính-năng-hệ-thống)
- [Cấu trúc mã nguồn](#cấu-trúc-mã-nguồn)
- [Hướng dẫn cài đặt & Khởi chạy](#hướng-dẫn-cài-đặt--khởi-chạy)
- [Kiểm thử & Đảm bảo chất lượng](#kiểm-thử--đảm-bảo-chất-lượng)
- [Danh mục tài liệu chi tiết](#danh-mục-tài-liệu-chi-tiết)

---

## Hiện trạng & Triển khai thực tế

- **Bản trực tuyến đang chạy:** Triển khai tại **https://rentbase.alwaysdata.net/login** trên nền tảng Alwaysdata, kết nối CSDL **MariaDB 11.8.9** (`rentbase_rental_management`). Môi trường online hoạt động độc lập, không phụ thuộc vào máy trạm cục bộ.
- **Ứng dụng di động PWA:** Hỗ trợ cài đặt trực tiếp từ trình duyệt Chrome lên màn hình chính thiết bị Android/iOS mà không cần qua kho ứng dụng. Đã tích hợp Web App Manifest, Service Worker mở khung khi mất kết nối và cơ chế xác nhận cập nhật giữa các tab. Đã kiểm thử nghiệm thu cài đặt trên Android. Xem [Hướng dẫn cài trên Android](frontend/docs/mobile-android-guide.md), [Nghiệm thu PWA Mobile](frontend/docs/pwa-mobile-acceptance-2026-11-01.md), [Nghiệm thu chuyển CSDL Alwaysdata](docs/mariadb-migration-acceptance-2026-11-03.md).

---

## Tiến độ nâng cấp RentBase Redesign (Lộ trình 8 đợt)

Tháng 11/2026, dự án chuyển đổi mô hình từ quản lý đơn khu trọ sang nền tảng **Đa khu trọ (Multi-Property Workspace)** với sàn tìm phòng và thiết kế giao diện hoàn toàn mới theo [Kế hoạch tổng thể RentBase sau đợt 2](docs/rentbase-plan-after-phase-2-2026-11-14.md):

| Đợt                   | Nội dung thực hiện                                                                                                                                                                                                                                               | Trạng thái         | Tài liệu đối chiếu                                                                                                                                  |
| --------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Đợt 1**             | Khảo sát mã nguồn, chuẩn hóa vị trí nút Sửa/Xóa/Hủy, rà soát tính năng desktop & mobile.                                                                                                                                                                         | Hoàn thành         | [Báo cáo rà soát đợt 1](docs/phase-one-review-2026-11-14.md)                                                                                        |
| **Đợt 2 & 2 bổ sung** | Dựng 4 màn hình mẫu xem thử Redesign Preview (`/preview-v2`), mô phỏng luồng đăng ký, tạo khu, mã mời, duyệt thành viên và tổng quan tháng.                                                                                                                      | Hoàn thành         | [Báo cáo đợt 2](docs/phase-two-review-2026-11-14.md) · [Đợt 2 bổ sung](docs/phase-two-extra-review-2026-11-14.md)                                   |
| **Đợt 3**             | Xây dựng nền tảng Multi-Tenancy: quan hệ khu trọ (`rental_properties`), phân quyền thành viên (`property_memberships`), cách ly dữ liệu triệt để (phòng, người thuê, hóa đơn, PDF, ảnh, thông báo), cấu hình payOS riêng từng khu, migration chuyển đổi CSDL cũ. | Hoàn thành         | [Tiến độ & Kết quả đợt 3](docs/phase-3-progress-2026-11-15.md)                                                                                      |
| **Đợt 4**             | Tự đăng ký tài khoản (`/register`), Onboarding tạo khu trọ mới, cơ chế tạo/quản lý mã mời có thời hạn & giới hạn lượt, gửi yêu cầu tham gia, luồng duyệt/từ chối/thu hồi quyền thành viên, liên kết hồ sơ cư trú.                                                | Hoàn thành         | [Báo cáo nghiệm thu đợt 4](docs/phase-four-review-2026-11-16.md)                                                                                    |
| **Đợt 5**             | Sàn tìm phòng công khai (`/find-rooms`), bộ lọc khu vực/giá/sức chứa/ở ghép, tin đăng phòng có ảnh, đặt lịch xem phòng, gửi yêu cầu thuê, duyệt nhận phòng thời gian thực kèm duyệt thành viên.                                                                  | Hoàn thành         | [Báo cáo kết quả đợt 5](docs/phase-five-review-2026-11-16.md)                                                                                       |
| **Đợt 6**             | Báo cáo cư trú & vận hành theo tháng, lưu trữ snapshot tổng kết (`monthly_report_snapshots`), đồng bộ thời gian thực 2 tầng: Pusher WebSocket và Revision polling fallback khi tab hoạt động.                                                                    | Hoàn thành         | [Kiểm thử PhaseSixTest](backend/tests/Feature/PhaseSixTest.php)                                                                                     |
| **Đợt 7**             | Tái thiết kế toàn diện giao diện RentBase cho khách, chủ trọ và người thuê (Navigation, Design tokens, Forms, Tables, Mobile Drawer, Responsive 320–1366px).                                                                                                     | Đang hoàn thiện    | [Mục 7 Kế hoạch RentBase](docs/rentbase-plan-after-phase-2-2026-11-14.md#7-thiết-kế-lại-toàn-bộ-giao-diện-rentbase)                                 |
| **Đợt 8**             | Nghiệm thu trên môi trường MariaDB Alwaysdata & thiết bị Android thực tế, kiểm tra payOS theo khu, sao lưu/phục hồi và bàn giao tài liệu tự kiểm thử hai vai trò.                                                                                                | Kế hoạch tiếp theo | [Mục 12 Kịch bản kiểm thử](docs/rentbase-plan-after-phase-2-2026-11-14.md#12-bàn-giao-hướng-dẫn-tự-kiểm-thử-hai-vai-trò-sau-khi-hoàn-thành-các-đợt) |

---

## Kiến trúc & Công nghệ

### Backend

- **Framework:** Laravel 12, PHP 8.2+
- **CSDL:** MySQL 8.0 (phát triển local), MariaDB 11.8.9 (hosting Alwaysdata)
- **Kiến trúc dữ liệu:** Hơn 35 bảng nghiệp vụ, mô hình Multi-Tenancy cô lập theo `property_id` và middleware `SelectProperty`.
- **Bảo mật & Phiên:** Session Cookie HttpOnly, CSRF token, phân quyền RBAC theo khu trọ, bảo vệ truy cập ảnh/chứng từ riêng tư.
- **Thanh toán trực tuyến:** Tích hợp payOS API với webhook định tuyến riêng theo khu, mã hóa cấu hình bảo mật phía máy chủ.
- **Sự kiện & Đồng bộ:** Pusher Channels / Laravel Broadcasting, kết hợp Revision endpoint cho cơ chế Polling dự phòng.
- **Xuất chứng từ:** Snappy / DomPDF xuất PDF hóa đơn và phiếu thu chuyên nghiệp theo nhận diện khu trọ.

### Frontend

- **Công nghệ nền tảng:** Vue 3 (Composition API, `<script setup>`), TypeScript, Vite
- **Quản lý trạng thái & Định tuyến:** Pinia Store, Vue Router 4
- **Giao diện & Thành phần:** Hệ thống Design System tự xây dựng, Lucide Icons, tiện ích responsive Tailwind/PostCSS, bảng màu hiện đại (than chì, xanh ngọc, trắng ấm).
- **Ứng dụng di động PWA:** Service Worker, Web App Manifest, Cache First cho khung tĩnh, Network First cho dữ liệu API, điều hướng tối ưu cho màn hình cảm ứng (Mobile Navigation Drawer & Bottom Bar).

---

## Tính năng hệ thống

### 1. Khách vãng lai & Người tìm phòng

- **Sàn tìm phòng công khai (`/find-rooms`):** Khám phá danh sách phòng trọ cho thuê mà không cần đăng nhập; xem ảnh, diện tích, giá thuê, các khoản phí dịch vụ minh bạch, tiện nghi và quy định.
- **Bộ lọc linh hoạt:** Lọc theo khu vực, tầm giá, diện tích, sức chứa, số chỗ còn nhận và tùy chọn tìm phòng cho ở ghép.
- **Đặt lịch xem phòng (Viewing Appointments):** Chọn ngày, khung giờ hẹn, số người dự kiến và ghi chú liên hệ.
- **Gửi yêu cầu thuê phòng (Rental Applications):** Điền nguyện vọng thuê và ngày dọn vào dự kiến. Lưu biểu mẫu nháp qua xác thực (đăng ký/đăng nhập mà không bị mất thông tin đang điền).
- **Theo dõi yêu cầu của tôi (`/my-room-requests`):** Theo dõi phản hồi từ chủ trọ (xác nhận lịch, đề xuất đổi giờ hẹn, chấp thuận thuê, lý do từ chối), chủ động đồng ý giờ hẹn mới hoặc rút yêu cầu.
- **Tự đăng ký tài khoản (`/register`):** Đăng ký bằng họ tên, tên đăng nhập, số điện thoại và mật khẩu; lựa chọn "Quản lý nhà trọ" để tạo khu trọ mới hoặc "Nhập mã khu trọ" để xin vào khu đang thuê.

### 2. Chủ trọ (Owner Portal)

- **Đa khu trọ (Multi-Property Workspace):** Tạo nhiều khu trọ, chuyển đổi ngữ cảnh quản lý tức thì (`/properties`). Dữ liệu giữa các khu được cách ly tuyệt đối.
- **Tiếp nhận & Quản lý tin đăng (`/owner/marketplace/listings`):** Tạo và cập nhật tin đăng phòng, quản lý ảnh công khai, bật/tắt trạng thái đăng hoặc cho ở ghép.
- **Lịch xem & Yêu cầu thuê (`/owner/marketplace/requests`):** Tiếp nhận yêu cầu từ khách; xác nhận, đề xuất đổi giờ hoặc hủy lịch hẹn; chấp thuận hoặc từ chối yêu cầu thuê; ghi nhận khách đã đến hay vắng mặt.
- **Xác nhận nhận phòng tức thì:** Chuyển đổi trực tiếp từ yêu cầu thuê sang đợt cư trú thực tế; tự động kiểm tra sức chứa thời gian thực, hỗ trợ duyệt thành viên khu trọ ngay trong cùng thao tác.
- **Thành viên & Mã mời (`/owner/members`):** Tạo mã mời khu trọ có thời hạn (mặc định 7 ngày), giới hạn số lượt duyệt, hỗ trợ đổi hoặc thu hồi mã; phê duyệt hoặc từ chối yêu cầu tham gia; liên kết tài khoản thành viên với hồ sơ người thuê.
- **Quản lý danh mục:** Quản lý phòng, dịch vụ và đơn giá theo thời gian; theo dõi trạng thái vận hành (trống, đang ở, bảo trì, chờ xóa).
- **Quản lý cư trú:** Hồ sơ người thuê, nhóm thuê, đợt cư trú, chỉ định đại diện phòng, thêm/rời thành viên, chuyển phòng và trả phòng có lưu lịch sử.
- **Điện nước & Hóa đơn:** Ghi chỉ số đơn lẻ hoặc nhập chỉ số nhiều phòng (`/owner/meters/bulk`); xem trước, phát hành hóa đơn theo kỳ, xuất file PDF hóa đơn/phiếu thu; điều chỉnh hoặc hủy hóa đơn có ghi nhật ký kiểm toán.
- **Thanh toán & Công nợ:** Thu tiền mặt hoặc chuyển khoản nhiều lần, cấp biên nhận, sửa sai phiếu thu có đối chiếu, theo dõi công nợ quá hạn theo từng phòng và từng hóa đơn.
- **Sổ quỹ thu chi:** Quản lý khoản thu khác và chi phí vận hành; tự động liên kết chi phí sửa chữa với sổ quỹ; nhật ký chỉnh sửa, hoàn tác và khôi phục khoản chi.
- **Báo hỏng & Sửa chữa:** Tiếp nhận sự cố từ người thuê, xem ảnh chụp riêng tư, cập nhật trạng thái xử lý, lưu lịch sử từng mốc xử lý và liên kết chi phí khắc phục.
- **Thông báo chung:** Soạn và gửi thông báo toàn khu hoặc theo từng phòng cụ thể; xem trước danh sách người nhận, chống gửi trùng và thống kê số người đã đọc.
- **Chờ xóa an toàn 3 ngày (`/owner/pending-deletions`):** Cơ chế hoãn xóa an toàn (grace period) cho phòng, người thuê, nhóm, dịch vụ, hóa đơn, thu chi; hiển thị cảnh báo tác động liên đới; cho phép hủy xóa/khôi phục trong 3 ngày trước khi xóa vĩnh viễn.
- **Báo cáo & Tổng kết tháng:** Thống kê hiện tại và tổng kết lịch sử cư trú (số người vào/ra/chuyển, số người ở cuối tháng, số phòng trống cuối tháng); đối chiếu doanh thu, thực thu, chi phí và công nợ; lưu snapshot báo cáo cố định.

### 3. Người thuê (Tenant Portal)

- **Khu trọ của tôi (`/tenant/workspace`):** Xem thông tin liên hệ của chủ trọ, quy định chung và các thông báo nội bộ của khu.
- **Phòng của tôi (`/tenant`):** Xem thông tin phòng đang cư trú, danh sách bạn cùng phòng, người đại diện, các dịch vụ đăng ký sử dụng và lịch sử cư trú.
- **Hóa đơn & Thanh toán trực tuyến (`/tenant/invoices`):** Xem danh sách hóa đơn theo quyền truy cập, hiển thị hạn thanh toán, số dư còn nợ, chi tiết từng khoản mục phí, lịch sử thanh toán, tải PDF hóa đơn/biên nhận.
- **Thanh toán QR payOS:** Tạo mã VietQR động trực tiếp trên hóa đơn, chuyển khoản qua ứng dụng ngân hàng và hệ thống tự động gạch nợ sau khi nhận webhook từ payOS.
- **Báo hỏng sự cố (`/tenant/maintenance`):** Gửi yêu cầu sửa chữa kèm mô tả và ảnh chụp thực tế từ camera/thiết bị; theo dõi tiến độ xử lý và phản hồi từ chủ trọ.
- **Hộp thông báo cá nhân (`/notifications`):** Nhận thông báo nhắc hạn thanh toán, xác nhận biên nhận, cập nhật tiến độ sửa chữa và thông báo chung từ chủ trọ.
- **Quản lý tài khoản cá nhân (`/account`):** Xem thông tin cá nhân và đổi mật khẩu an toàn.

---

## Cấu trúc mã nguồn

```text
d:\KLTN-redesign\
├── backend/                         # Ứng dụng Backend Laravel 12
│   ├── app/
│   │   ├── Console/Commands/        # Lệnh Artisan (nhắc nợ, audit quyền, xóa 3 ngày, payOS)
│   │   ├── Http/Controllers/        # API Controllers (Auth, Onboarding, Marketplace, Live...)
│   │   ├── Http/Middleware/         # Middleware (SelectProperty, RBAC...)
│   │   ├── Models/                  # Eloquent Models (Room, Tenant, Invoice, ScheduledDeletion...)
│   │   └── Services/                # Nghiệp vụ lõi (Tenancy, Billing, LiveUpdates, Marketplace...)
│   ├── config/                      # Cấu hình hệ thống (tenancy.php, database.php...)
│   ├── database/migrations/         # Migrations CSDL (Multi-tenancy, Onboarding, Deletions...)
│   └── tests/                       # Bộ kiểm thử Unit & Feature (450+ tests)
│
├── frontend/                        # Ứng dụng Frontend Vue 3 + Vite + TypeScript
│   ├── public/                      # Static assets, PWA manifest, icons
│   ├── src/
│   │   ├── assets/ & styles/        # CSS variables, Design tokens, animations
│   │   ├── components/              # Vue components dùng chung (Owner, Tenant, Design, Modals...)
│   │   ├── composables/             # Vue Composables (useLiveRefresh, useTenantApi...)
│   │   ├── layouts/                 # AppLayout, AuthLayout, Navigation Drawers
│   │   ├── lib/                     # Utilities (API client, property-selection, date, formatters)
│   │   ├── router/                  # Vue Router (Phân quyền route, guard ngữ cảnh khu trọ)
│   │   ├── stores/                  # Pinia stores (Auth, PropertyContext...)
│   │   ├── types/                   # TypeScript interfaces & types
│   │   └── views/                   # Các màn hình chính (Marketplace, Owner, Tenant, Onboarding...)
│   └── tests/e2e/                   # Bộ kiểm thử Playwright trình duyệt E2E
│
└── docs/                            # Tài liệu phân tích, lộ trình & biên bản nghiệm thu
```

---

## Hướng dẫn cài đặt & Khởi chạy

### Yêu cầu môi trường

- **PHP:** 8.2 trở lên (yêu cầu extensions: `pdo_mysql`, `curl`, `mbstring`, `openssl`, `zip`)
- **Composer:** 2.2 trở lên
- **Node.js:** 20.x hoặc 22.x và **npm**
- **CSDL:** MySQL 8.0+ hoặc MariaDB 10.4+/11.8+

---

### 1. Cài đặt Backend

Di chuyển vào thư mục `backend/`:

```powershell
cd backend
composer install
Copy-Item .env.example .env    # Tạo tệp .env nếu chưa có
php artisan key:generate      # Tạo khóa ứng dụng
```

Cấu hình thông số kết nối CSDL trong tệp `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rental_management
DB_USERNAME=root
DB_PASSWORD="your_password"

# Bật tính năng đa khu trọ (Multi-Tenancy)
PROPERTY_TENANCY_ENABLED=true

# Cấu hình thanh toán trực tuyến payOS (tùy chọn)
PAYOS_ENABLED=false
PAYOS_CLIENT_ID=
PAYOS_API_KEY=
PAYOS_CHECKSUM_KEY=

# Cấu hình đồng bộ thời gian thực Pusher Channels (tùy chọn)
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=ap1
```

Chạy migration và khởi động máy chủ Laravel:

```powershell
php artisan migrate
php artisan rental:verify-database
php artisan serve
```

Máy chủ backend chạy tại địa chỉ: `http://127.0.0.1:8000`.

> [!TIP]
> **Các tiến trình nền tự động:**
>
> - Để chạy lịch nhắc nợ tự động lúc 08:00 và dọn dẹp các đối tượng quá hạn 3 ngày chờ xóa, mở một terminal riêng trong `backend/` và chạy:
>   ```powershell
>   php artisan schedule:work
>   ```
> - Kiểm tra và kiểm toán quyền sở hữu khu trọ:
>   ```powershell
>   php artisan rental:audit-property-ownership
>   ```

---

### 2. Cài đặt Frontend

Mở một cửa sổ terminal mới và di chuyển vào thư mục `frontend/`:

```powershell
cd frontend
npm ci
npm run dev
```

Ứng dụng frontend chạy tại: `http://127.0.0.1:5173`.
Vite tự động proxy các yêu cầu `/api` sang backend Laravel tại cổng 8000 và bảo toàn cookie phiên HttpOnly/CSRF.

---

### 3. Bản chạy thử độc lập (Offline Demo Mode)

Nếu muốn trải nghiệm nhanh toàn bộ giao diện và luồng nghiệp vụ trên CSDL SQLite cục bộ (không cần cài đặt MySQL):

```powershell
cd frontend
npm run demo
```

Mở trình duyệt tại:

- **Cổng chủ trọ dùng thử:** `http://127.0.0.1:5174/owner` (đăng nhập: `admin` / `123`)
- **Cổng người thuê dùng thử:** `http://127.0.0.1:5174/tenant` (đăng nhập: `user` / `123`)
- **Xem trước thiết kế mới (Redesign Preview):** `http://127.0.0.1:5173/preview-v2` hoặc `http://127.0.0.1:5174/preview-v2`

---

## Kiểm thử & Đảm bảo chất lượng

Dự án áp dụng quy trình kiểm thử nghiêm ngặt đa tầng để đảm bảo tính an toàn dữ liệu tài chính và sự ổn định của hệ thống:

### 1. Kiểm thử Backend (PHPUnit / Pest)

Bao phủ toàn bộ quy tắc tính toán hóa đơn, thanh toán, chống gửi trùng, cách ly đa khu trọ, lịch xem phòng, xóa an toàn 3 ngày và đồng bộ Live Updates:

```powershell
cd backend
php artisan test
php vendor/bin/pint --test    # Kiểm tra quy chuẩn mã nguồn PHP PSR-12
```

- **Kết quả:** Đạt **453 bài kiểm thử**, hơn **4.860+ lượt kiểm tra (assertions)** pass hoàn toàn.

### 2. Kiểm thử Frontend (Vitest)

Kiểm tra định dạng tiền tệ, xử lý ngày tháng, logic Pinia Store, bảo mật phiên và tính tương phản màu sắc giao diện:

```powershell
cd frontend
npm test
npm run build                 # Đóng gói và kiểm tra toàn bộ kiểu dữ liệu TypeScript
```

- **Kết quả:** Đạt **134 bài kiểm thử unit/integration**, bản build Vite/PWA thành công không có lỗi.

### 3. Kiểm thử Trình duyệt E2E (Playwright)

Kiểm thử tự động các luồng tương tác thực tế của người dùng trên cả desktop (1366px) và mobile (360px, 390px):

```powershell
cd frontend
npm run test:e2e
```

---

## Danh mục tài liệu chi tiết

### Tài liệu Lộ trình & Nghiệm thu Đợt nâng cấp RentBase

- [Kế hoạch tổng thể RentBase sau đợt 2 (Lộ trình 8 đợt)](docs/rentbase-plan-after-phase-2-2026-11-14.md)
- [Báo cáo rà soát đợt 1 (14/11/2026)](docs/phase-one-review-2026-11-14.md)
- [Báo cáo nghiệm thu đợt 2](docs/phase-two-review-2026-11-14.md) · [Nghiệm thu đợt 2 bổ sung](docs/phase-two-extra-review-2026-11-14.md)
- [Tiến độ & Kết quả đợt 3 — Đa khu trọ & Cách ly quyền (15/11/2026)](docs/phase-3-progress-2026-11-15.md)
- [Báo cáo nghiệm thu đợt 4 — Đăng ký, Tạo khu & Mã mời (16/11/2026)](docs/phase-four-review-2026-11-16.md)
- [Báo cáo kết quả đợt 5 — Sàn tìm phòng & Đặt lịch xem (16/11/2026)](docs/phase-five-review-2026-11-16.md)

### Tài liệu Nghiệp vụ & API Backend

- [Hướng dẫn Backend & Tổng quan API](backend/README.md)
- [Xác thực & Quản lý phiên](backend/docs/authentication.md)
- [Quy tắc phòng & Dịch vụ](backend/docs/rooms-services.md)
- [Quy trình cư trú & Nhận/trả phòng](backend/docs/tenant-occupancy.md)
- [Chỉ số điện nước & Lập hóa đơn](backend/docs/meter-readings-invoices.md)
- [Quy tắc điều chỉnh & Hủy hóa đơn](backend/docs/invoice-controls.md)
- [Thanh toán hóa đơn & Biên nhận](backend/docs/invoice-payments.md)
- [Sửa sai phiếu thu có đối chiếu](backend/docs/payment-corrections.md)
- [Sổ quỹ thu chi, Hoàn tác & Chống gửi trùng](backend/docs/cash-book.md) · [Chống gửi trùng](backend/docs/cash-idempotency.md)
- [Báo hỏng, Ảnh riêng tư & Lịch sử sửa chữa](backend/docs/maintenance-history.md) · [Chi phí sửa chữa](backend/docs/maintenance-expenses.md)
- [Thông báo chung toàn khu / theo phòng](backend/docs/announcements.md)
- [Cấu hình thông tin nhà trọ](backend/docs/rental-property.md) & [Xuất chứng từ PDF](backend/docs/pdf-documents.md)
- [Nhập chỉ số điện nước nhiều phòng](backend/docs/bulk-meter-readings.md)

### Tài liệu Giao diện & Hướng dẫn Tự kiểm tra Frontend

- [Hướng dẫn Frontend & Cấu hình Vite](frontend/README.md)
- [Quy chuẩn thiết kế giao diện (Design System)](frontend/docs/design-system.md)
- [Hướng dẫn màn hình chủ trọ](frontend/docs/owner-management.md) & [Tự kiểm tra CT4–CT8](frontend/docs/owner-self-test.md)
- [Hướng dẫn tự kiểm tra người thuê NT1–NT4](frontend/docs/tenant-self-test.md)
- [Tự kiểm tra Thông báo chung](frontend/docs/announcements-self-test.md)
- [Tự kiểm tra Lịch sử sửa chữa](frontend/docs/maintenance-history-self-test.md) & [Chi phí sửa chữa](frontend/docs/repair-expenses-self-test.md)
- [Tự kiểm tra Sửa sai phiếu thu](frontend/docs/payment-corrections-self-test.md)
- [Tự kiểm tra Thông tin nhà trọ & PDF](frontend/docs/property-pdf-self-test.md)
- [Tự kiểm tra Nhập chỉ số nhiều phòng](frontend/docs/bulk-meters-self-test.md)
- [Kịch bản trình diễn sản phẩm (Presentation Guide)](frontend/docs/presentation-guide.md)

### Tài liệu Triển khai Alwaysdata & Ứng dụng Di động PWA

- [Hướng dẫn cài đặt và sử dụng PWA trên Android](frontend/docs/mobile-android-guide.md)
- [Kế hoạch phát triển PWA Mobile](docs/pwa-mobile-plan-2026-10-30.md)
- [Nghiệm thu PWA Mobile trên Android](frontend/docs/pwa-mobile-acceptance-2026-11-01.md)
- [Nghiệm thu kiểm thử Chrome Mobile](docs/chrome-mobile-acceptance-2026-11-06.md)
- [Hướng dẫn Hosting Alwaysdata & CSDL MariaDB](docs/hosting-rentbase-2026-11-01.md)
- [Nghiệm thu chuyển đổi MariaDB Alwaysdata](docs/mariadb-migration-acceptance-2026-11-03.md)
- [Kế hoạch sao lưu và khôi phục CSDL](docs/alwaysdata-database-migration-plan-2026-11-03.md)
- [Kích hoạt thanh toán trực tuyến payOS](docs/payos-activation.md)

---

## Bảo mật & Giấy phép

- Tệp cấu hình môi trường `.env`, khóa bí mật `APP_KEY`, thông tin kết nối CSDL, tài liệu chứng từ PDF và tệp tải lên của người dùng được lưu trữ an toàn và cấu hình loại trừ khỏi kho mã nguồn Git (`.gitignore`).
- Dữ liệu người thuê thực tế và chứng từ ngân hàng không được lưu trữ trong mã nguồn hoặc tệp seed mẫu.
- Dự án được phát triển phục vụ mục đích Khóa luận tốt nghiệp ngành Công nghệ Thông tin. Mọi quyền được bảo lưu.
