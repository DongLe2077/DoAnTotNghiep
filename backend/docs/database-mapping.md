# Ánh xạ CSDL rental_management

[Kết quả nghiệm thu thông tin nhà trọ, PDF và nhập nhiều phòng ngày 30/10](../../frontend/docs/property-pdf-bulk-acceptance.md).

Cập nhật 30/10/2026: **28 bảng nghiệp vụ/28 model, 100 quan hệ và 3 view**. Bảng `rental_properties` → `RentalProperty` giữ hồ sơ chung một nhà trọ, người cập nhật và phiên bản chống ghi đè. Bảng `meter_reading_requests` → `MeterReadingRequestRecord` giữ mã/nội dung gốc để đối chiếu gửi lại chỉ số nhiều phòng; xóa chỉ số hợp lệ không xóa danh tính yêu cầu cũ. Các bảng nghiệp vụ có trước được giữ nguyên khi nâng cấp. [Thông tin nhà trọ](rental-property.md), [PDF](pdf-documents.md), [nhập nhiều phòng](bulk-meter-readings.md). Các mốc bên dưới mô tả lịch sử nâng cấp.

Cập nhật 27/10/2026: **26 bảng nghiệp vụ/26 model, 95 quan hệ và 3 view**. Hai bảng `maintenance_expense_links`/`maintenance_expense_histories` lưu liên kết khoản chi và lịch sử; `payment_corrections` lưu đối chiếu phiếu sai/thay thế. View `invoice_balances` chỉ cộng phiếu còn hiệu lực. [Chi phí sửa chữa](maintenance-expenses.md), [sửa sai phiếu thu](payment-corrections.md). Dữ liệu 23 bảng có trước được giữ nguyên sau nâng cấp; các số liệu theo ngày bên dưới mô tả lịch sử.

Cập nhật 25/10/2026: **23 bảng nghiệp vụ/23 model, 81 quan hệ và 3 view**. Bảng `maintenance_request_histories` → `MaintenanceRequestHistory` giữ nhật ký cập nhật, FK đến yêu cầu và tài khoản thực hiện. [Quy tắc lịch sử sửa chữa](maintenance-history.md). Các số liệu theo ngày bên dưới mô tả các đợt trước.

Cập nhật 24/10/2026: sau kiểm soát hóa đơn và thông báo chung, hiện có **22 bảng nghiệp vụ/22 model, 78 quan hệ và 3 view**. `announcements` → `Announcement` lưu nội dung/phạm vi gửi; `app_notifications.announcement_id` liên kết thông báo cá nhân và thời điểm đọc. [Chi tiết thông báo chung](announcements.md). Các số liệu theo ngày bên dưới mô tả lịch sử nâng cấp.

Ban đầu kiểm tra MySQL 8.0.43 ngày 18/10/2026 với 16 bảng InnoDB và 3 view. Ngày 20/10/2026 đã bổ sung 4 bảng nghiệp vụ, hiện có 20 model/72 quan hệ và 3 view; bảng `migrations` theo dõi nâng cấp. Các bảng nghiệp vụ dùng khóa chính `id` BIGINT UNSIGNED AUTO_INCREMENT. Bản chụp schema đã cập nhật cấu trúc hiện tại.

Phần bảng dưới đây mô tả 16 bảng gốc. Sau nâng cấp, `cash_transactions` thêm `revision` và `deleted_at`, `maintenance_requests` thêm `revision`; ba model/bảng mới là `CashTransactionHistory`/`cash_transaction_histories`, `MaintenancePhoto`/`maintenance_photos`, `AppNotification`/`app_notifications`. Chi tiết hành vi, migration và dữ liệu thêm tại [nhật ký/ảnh/thông báo](audit-photos-notifications.md).

Bảng thứ tư bổ sung là `cash_creation_requests` → `CashCreationRequest`, lưu yêu cầu gốc và khóa chống trùng, liên kết `cash_transactions` và `users` bằng hai quan hệ belongsTo. Xem [chống gửi trùng thu chi](cash-idempotency.md).

## Model và thời gian

| Bảng | Model | Cột thời gian tạo | Cột thời gian cập nhật |
| --- | --- | --- | --- |
| `cash_transactions` | `CashTransaction` | `recorded_at` | `—` |
| `invoice_access_grants` | `InvoiceAccessGrant` | `granted_at` | `—` |
| `invoice_items` | `InvoiceItem` | `—` | `—` |
| `invoices` | `Invoice` | `issued_at` | `—` |
| `maintenance_requests` | `MaintenanceRequest` | `submitted_at` | `updated_at` |
| `meter_readings` | `MeterReading` | `recorded_at` | `—` |
| `occupancies` | `Occupancy` | `created_at` | `updated_at` |
| `occupancy_services` | `OccupancyService` | `created_at` | `—` |
| `payments` | `Payment` | `recorded_at` | `—` |
| `rental_groups` | `RentalGroup` | `created_at` | `—` |
| `representative_assignments` | `RepresentativeAssignment` | `created_at` | `—` |
| `rooms` | `Room` | `created_at` | `updated_at` |
| `services` | `Service` | `created_at` | `updated_at` |
| `tenant_stays` | `TenantStay` | `created_at` | `updated_at` |
| `tenants` | `Tenant` | `created_at` | `updated_at` |
| `users` | `User` | `created_at` | `updated_at` |

## Quy tắc ánh xạ

- Mỗi model khai báo tên bảng và danh sách `$fillable` tường minh.
- Tiền và chỉ số dùng cast `decimal` theo đúng số chữ số thập phân; boolean và ngày/giờ có cast tương ứng. Giá trị tiền được trả dưới dạng chuỗi thập phân để giữ độ chính xác.
- Các cột STORED GENERATED do MySQL tính. Các cột này không nằm trong `$fillable`; không tự gán trực tiếp hoặc gửi chúng trong các câu INSERT/UPDATE thủ công.
- Quan hệ Eloquent dùng `id` duy nhất toàn bảng. Với khóa ngoại ghép, MySQL tiếp tục kiểm tra các cột phạm vi bổ sung như `occupancy_id`, `rental_group_id`, `occupancy_service_id`. Giữ các ràng buộc CSDL này khi phát triển nghiệp vụ.
- `users` dùng `username`, `password_hash`, `role`, `is_active`; không có các cột `name`, `email`, `email_verified_at`, `password` của scaffold Laravel. Model đã chỉ định `getAuthPasswordName()` và ẩn hash/token khi chuyển sang JSON. API đăng nhập và phân quyền đã được triển khai; xem [authentication.md](authentication.md).
- Factory User đã khớp schema; seeder mặc định không thêm bản ghi.

## Chi tiết từng bảng

### `cash_transactions` → `CashTransaction`

Other income and expenses, excluding invoice payment receipts

| Cột | Kiểu MySQL | Cho phép NULL | Cast | Tự tính |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | Không | `integer` | — |
| `transaction_type` | `varchar(20)` | Không | `string` | — |
| `amount` | `decimal(18,2)` | Không | `decimal:2` | — |
| `occurred_at` | `datetime` | Không | `datetime` | — |
| `description` | `varchar(255)` | Không | `string` | — |
| `recorded_by` | `bigint unsigned` | Không | `integer` | — |
| `recorded_at` | `datetime` | Không | `datetime` | — |

Quan hệ:

- `recordedBy()` → `User` (BelongsTo)

Khóa ngoại thực tế:

- `fk_cash_transactions_recorder`: `recorded_by` → `users.id`

### `invoice_access_grants` → `InvoiceAccessGrant`

Explicit per-invoice tenant permissions with revocation history

| Cột | Kiểu MySQL | Cho phép NULL | Cast | Tự tính |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | Không | `integer` | — |
| `invoice_id` | `bigint unsigned` | Không | `integer` | — |
| `occupancy_id` | `bigint unsigned` | Không | `integer` | — |
| `user_id` | `bigint unsigned` | Không | `integer` | — |
| `representative_assignment_id` | `bigint unsigned` | Không | `integer` | — |
| `granted_by` | `bigint unsigned` | Không | `integer` | — |
| `granted_at` | `datetime` | Không | `datetime` | — |
| `revoked_at` | `datetime` | Có | `datetime` | — |
| `grant_reason` | `varchar(255)` | Không | `string` | — |
| `active_invoice_id` | `bigint unsigned` | Có | `integer` | Có |

Quan hệ:

- `invoice()` → `Invoice` (BelongsTo)
- `occupancy()` → `Occupancy` (BelongsTo)
- `user()` → `User` (BelongsTo)
- `representativeAssignment()` → `RepresentativeAssignment` (BelongsTo)
- `grantedBy()` → `User` (BelongsTo)

Khóa ngoại thực tế:

- `fk_invoice_access_granter`: `granted_by` → `users.id`
- `fk_invoice_access_invoice`: `invoice_id` → `invoices.id`, `occupancy_id` → `invoices.occupancy_id`
- `fk_invoice_access_representative`: `representative_assignment_id` → `representative_assignments.id`, `occupancy_id` → `representative_assignments.occupancy_id`
- `fk_invoice_access_user`: `user_id` → `users.id`

### `invoice_items` → `InvoiceItem`

Immutable invoice lines after publication; rent always quantity one

| Cột | Kiểu MySQL | Cho phép NULL | Cast | Tự tính |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | Không | `integer` | — |
| `invoice_id` | `bigint unsigned` | Không | `integer` | — |
| `occupancy_id` | `bigint unsigned` | Không | `integer` | — |
| `item_type` | `varchar(20)` | Không | `string` | — |
| `description` | `varchar(255)` | Không | `string` | — |
| `unit` | `varchar(30)` | Không | `string` | — |
| `quantity` | `decimal(15,3)` | Không | `decimal:3` | — |
| `unit_price` | `decimal(18,4)` | Không | `decimal:4` | — |
| `line_total` | `decimal(18,2)` | Có | `decimal:2` | Có |
| `occupancy_service_id` | `bigint unsigned` | Có | `integer` | — |
| `meter_reading_id` | `bigint unsigned` | Có | `integer` | — |
| `charge_period_start` | `datetime` | Có | `datetime` | — |
| `charge_period_end` | `datetime` | Có | `datetime` | — |
| `rent_invoice_id` | `bigint unsigned` | Có | `integer` | Có |

Quan hệ:

- `invoice()` → `Invoice` (BelongsTo)
- `occupancy()` → `Occupancy` (BelongsTo)
- `occupancyService()` → `OccupancyService` (BelongsTo)
- `meterReading()` → `MeterReading` (BelongsTo)

Khóa ngoại thực tế:

- `fk_invoice_items_invoice`: `invoice_id` → `invoices.id`, `occupancy_id` → `invoices.occupancy_id`
- `fk_invoice_items_reading`: `meter_reading_id` → `meter_readings.id`, `occupancy_service_id` → `meter_readings.occupancy_service_id`
- `fk_invoice_items_service`: `occupancy_service_id` → `occupancy_services.id`, `occupancy_id` → `occupancy_services.occupancy_id`

### `invoices` → `Invoice`

Issued invoices only; preview is not persisted

| Cột | Kiểu MySQL | Cho phép NULL | Cast | Tự tính |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | Không | `integer` | — |
| `invoice_number` | `varchar(50)` | Không | `string` | — |
| `occupancy_id` | `bigint unsigned` | Không | `integer` | — |
| `billing_month` | `date` | Không | `date` | — |
| `representative_assignment_id` | `bigint unsigned` | Không | `integer` | — |
| `issued_by` | `bigint unsigned` | Không | `integer` | — |
| `issued_at` | `datetime` | Không | `datetime` | — |

Quan hệ:

- `occupancy()` → `Occupancy` (BelongsTo)
- `representativeAssignment()` → `RepresentativeAssignment` (BelongsTo)
- `issuedBy()` → `User` (BelongsTo)
- `items()` → `InvoiceItem` (HasMany)
- `payments()` → `Payment` (HasMany)
- `accessGrants()` → `InvoiceAccessGrant` (HasMany)

Khóa ngoại thực tế:

- `fk_invoices_issuer`: `issued_by` → `users.id`
- `fk_invoices_occupancy`: `occupancy_id` → `occupancies.id`
- `fk_invoices_representative`: `representative_assignment_id` → `representative_assignments.id`, `occupancy_id` → `representative_assignments.occupancy_id`

### `maintenance_requests` → `MaintenanceRequest`

Representative reports and owner processing results

| Cột | Kiểu MySQL | Cho phép NULL | Cast | Tự tính |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | Không | `integer` | — |
| `occupancy_id` | `bigint unsigned` | Không | `integer` | — |
| `submitted_by` | `bigint unsigned` | Không | `integer` | — |
| `representative_assignment_id` | `bigint unsigned` | Không | `integer` | — |
| `title` | `varchar(150)` | Không | `string` | — |
| `description` | `text` | Không | `string` | — |
| `status` | `varchar(20)` | Không | `string` | — |
| `resolution` | `text` | Có | `string` | — |
| `submitted_at` | `datetime` | Không | `datetime` | — |
| `updated_at` | `datetime` | Không | `datetime` | — |

Quan hệ:

- `occupancy()` → `Occupancy` (BelongsTo)
- `submittedBy()` → `User` (BelongsTo)
- `representativeAssignment()` → `RepresentativeAssignment` (BelongsTo)

Khóa ngoại thực tế:

- `fk_maintenance_requests_occupancy`: `occupancy_id` → `occupancies.id`
- `fk_maintenance_requests_representative`: `representative_assignment_id` → `representative_assignments.id`, `occupancy_id` → `representative_assignments.occupancy_id`
- `fk_maintenance_requests_sender`: `submitted_by` → `users.id`

### `meter_readings` → `MeterReading`

Metered consumption over a non-overlapping service interval

| Cột | Kiểu MySQL | Cho phép NULL | Cast | Tự tính |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | Không | `integer` | — |
| `occupancy_service_id` | `bigint unsigned` | Không | `integer` | — |
| `period_start` | `datetime` | Không | `datetime` | — |
| `period_end` | `datetime` | Không | `datetime` | — |
| `previous_reading` | `decimal(15,3)` | Không | `decimal:3` | — |
| `current_reading` | `decimal(15,3)` | Không | `decimal:3` | — |
| `consumption` | `decimal(15,3)` | Có | `decimal:3` | Có |
| `recorded_by` | `bigint unsigned` | Không | `integer` | — |
| `recorded_at` | `datetime` | Không | `datetime` | — |

Quan hệ:

- `occupancyService()` → `OccupancyService` (BelongsTo)
- `recordedBy()` → `User` (BelongsTo)
- `invoiceItem()` → `InvoiceItem` (HasOne)

Khóa ngoại thực tế:

- `fk_meter_readings_recorder`: `recorded_by` → `users.id`
- `fk_meter_readings_service`: `occupancy_service_id` → `occupancy_services.id`

### `occupancies` → `Occupancy`

Group residence episode; not a rental contract

| Cột | Kiểu MySQL | Cho phép NULL | Cast | Tự tính |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | Không | `integer` | — |
| `rental_group_id` | `bigint unsigned` | Không | `integer` | — |
| `previous_occupancy_id` | `bigint unsigned` | Có | `integer` | — |
| `room_id` | `bigint unsigned` | Không | `integer` | — |
| `started_at` | `datetime` | Không | `datetime` | — |
| `ended_at` | `datetime` | Có | `datetime` | — |
| `monthly_rent` | `decimal(18,2)` | Không | `decimal:2` | — |
| `active_room_id` | `bigint unsigned` | Có | `integer` | Có |
| `created_at` | `datetime` | Không | `datetime` | — |
| `updated_at` | `datetime` | Không | `datetime` | — |
| `active_rental_group_id` | `bigint unsigned` | Có | `integer` | Có |

Quan hệ:

- `rentalGroup()` → `RentalGroup` (BelongsTo)
- `previousOccupancy()` → `Occupancy` (BelongsTo)
- `nextOccupancy()` → `Occupancy` (HasOne)
- `room()` → `Room` (BelongsTo)
- `occupancyServices()` → `OccupancyService` (HasMany)
- `tenantStays()` → `TenantStay` (HasMany)
- `representativeAssignments()` → `RepresentativeAssignment` (HasMany)
- `invoices()` → `Invoice` (HasMany)
- `invoiceItems()` → `InvoiceItem` (HasMany)
- `invoiceAccessGrants()` → `InvoiceAccessGrant` (HasMany)
- `maintenanceRequests()` → `MaintenanceRequest` (HasMany)

Khóa ngoại thực tế:

- `fk_occupancies_group`: `rental_group_id` → `rental_groups.id`
- `fk_occupancies_previous_group`: `previous_occupancy_id` → `occupancies.id`, `rental_group_id` → `occupancies.rental_group_id`
- `fk_occupancies_room`: `room_id` → `rooms.id`

### `occupancy_services` → `OccupancyService`

Time-dependent service configuration and price snapshots

| Cột | Kiểu MySQL | Cho phép NULL | Cast | Tự tính |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | Không | `integer` | — |
| `occupancy_id` | `bigint unsigned` | Không | `integer` | — |
| `service_id` | `bigint unsigned` | Không | `integer` | — |
| `effective_from` | `datetime` | Không | `datetime` | — |
| `effective_until` | `datetime` | Có | `datetime` | — |
| `unit_price` | `decimal(18,4)` | Không | `decimal:4` | — |
| `billing_method` | `varchar(20)` | Không | `string` | — |
| `unit` | `varchar(30)` | Không | `string` | — |
| `active_service_id` | `bigint unsigned` | Có | `integer` | Có |
| `created_at` | `datetime` | Không | `datetime` | — |

Quan hệ:

- `occupancy()` → `Occupancy` (BelongsTo)
- `service()` → `Service` (BelongsTo)
- `meterReadings()` → `MeterReading` (HasMany)
- `invoiceItems()` → `InvoiceItem` (HasMany)

Khóa ngoại thực tế:

- `fk_occupancy_services_occupancy`: `occupancy_id` → `occupancies.id`
- `fk_occupancy_services_service`: `service_id` → `services.id`

### `payments` → `Payment`

Confirmed receipts; overpayment checked with an invoice row lock

| Cột | Kiểu MySQL | Cho phép NULL | Cast | Tự tính |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | Không | `integer` | — |
| `invoice_id` | `bigint unsigned` | Không | `integer` | — |
| `amount` | `decimal(18,2)` | Không | `decimal:2` | — |
| `paid_at` | `datetime` | Không | `datetime` | — |
| `payment_method` | `varchar(20)` | Không | `string` | — |
| `idempotency_key` | `varchar(64)` | Không | `string` | — |
| `recorded_by` | `bigint unsigned` | Không | `integer` | — |
| `recorded_at` | `datetime` | Không | `datetime` | — |

Quan hệ:

- `invoice()` → `Invoice` (BelongsTo)
- `recordedBy()` → `User` (BelongsTo)

Khóa ngoại thực tế:

- `fk_payments_invoice`: `invoice_id` → `invoices.id`
- `fk_payments_recorder`: `recorded_by` → `users.id`

### `rental_groups` → `RentalGroup`

Group identity across internal whole-group room transfers

| Cột | Kiểu MySQL | Cho phép NULL | Cast | Tự tính |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | Không | `integer` | — |
| `group_code` | `varchar(40)` | Không | `string` | — |
| `created_at` | `datetime` | Không | `datetime` | — |

Quan hệ:

- `occupancies()` → `Occupancy` (HasMany)

### `representative_assignments` → `RepresentativeAssignment`

Historical representative assignments; account eligibility checked by service

| Cột | Kiểu MySQL | Cho phép NULL | Cast | Tự tính |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | Không | `integer` | — |
| `occupancy_id` | `bigint unsigned` | Không | `integer` | — |
| `tenant_stay_id` | `bigint unsigned` | Không | `integer` | — |
| `started_at` | `datetime` | Không | `datetime` | — |
| `ended_at` | `datetime` | Có | `datetime` | — |
| `active_occupancy_id` | `bigint unsigned` | Có | `integer` | Có |
| `created_at` | `datetime` | Không | `datetime` | — |

Quan hệ:

- `occupancy()` → `Occupancy` (BelongsTo)
- `tenantStay()` → `TenantStay` (BelongsTo)
- `invoices()` → `Invoice` (HasMany)
- `maintenanceRequests()` → `MaintenanceRequest` (HasMany)
- `invoiceAccessGrants()` → `InvoiceAccessGrant` (HasMany)

Khóa ngoại thực tế:

- `fk_representatives_stay`: `tenant_stay_id` → `tenant_stays.id`, `occupancy_id` → `tenant_stays.occupancy_id`

### `rooms` → `Room`

Physical rooms; vacant/occupied is calculated

| Cột | Kiểu MySQL | Cho phép NULL | Cast | Tự tính |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | Không | `integer` | — |
| `room_number` | `varchar(30)` | Không | `string` | — |
| `capacity` | `smallint unsigned` | Không | `integer` | — |
| `default_monthly_rent` | `decimal(18,2)` | Không | `decimal:2` | — |
| `operational_status` | `varchar(20)` | Không | `string` | — |
| `created_at` | `datetime` | Không | `datetime` | — |
| `updated_at` | `datetime` | Không | `datetime` | — |

Quan hệ:

- `occupancies()` → `Occupancy` (HasMany)

### `services` → `Service`

Service catalog and default prices

| Cột | Kiểu MySQL | Cho phép NULL | Cast | Tự tính |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | Không | `integer` | — |
| `service_code` | `varchar(40)` | Không | `string` | — |
| `name` | `varchar(100)` | Không | `string` | — |
| `unit` | `varchar(30)` | Không | `string` | — |
| `billing_method` | `varchar(20)` | Không | `string` | — |
| `default_unit_price` | `decimal(18,4)` | Không | `decimal:4` | — |
| `is_active` | `tinyint(1)` | Không | `boolean` | — |
| `created_at` | `datetime` | Không | `datetime` | — |
| `updated_at` | `datetime` | Không | `datetime` | — |

Quan hệ:

- `occupancyServices()` → `OccupancyService` (HasMany)

### `tenant_stays` → `TenantStay`

Individual residence history inside a group occupancy

| Cột | Kiểu MySQL | Cho phép NULL | Cast | Tự tính |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | Không | `integer` | — |
| `occupancy_id` | `bigint unsigned` | Không | `integer` | — |
| `tenant_id` | `bigint unsigned` | Không | `integer` | — |
| `moved_in_at` | `datetime` | Không | `datetime` | — |
| `moved_out_at` | `datetime` | Có | `datetime` | — |
| `active_tenant_id` | `bigint unsigned` | Có | `integer` | Có |
| `created_at` | `datetime` | Không | `datetime` | — |
| `updated_at` | `datetime` | Không | `datetime` | — |

Quan hệ:

- `occupancy()` → `Occupancy` (BelongsTo)
- `tenant()` → `Tenant` (BelongsTo)
- `representativeAssignments()` → `RepresentativeAssignment` (HasMany)

Khóa ngoại thực tế:

- `fk_tenant_stays_occupancy`: `occupancy_id` → `occupancies.id`
- `fk_tenant_stays_tenant`: `tenant_id` → `tenants.id`

### `tenants` → `Tenant`

All residents, including members without an account

| Cột | Kiểu MySQL | Cho phép NULL | Cast | Tự tính |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | Không | `integer` | — |
| `full_name` | `varchar(150)` | Không | `string` | — |
| `phone_number` | `varchar(20)` | Không | `string` | — |
| `identity_number` | `varchar(30)` | Có | `string` | — |
| `created_at` | `datetime` | Không | `datetime` | — |
| `updated_at` | `datetime` | Không | `datetime` | — |

Quan hệ:

- `user()` → `User` (HasOne)
- `stays()` → `TenantStay` (HasMany)

### `users` → `User`

Owner and tenant representative accounts

| Cột | Kiểu MySQL | Cho phép NULL | Cast | Tự tính |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | Không | `integer` | — |
| `tenant_id` | `bigint unsigned` | Có | `integer` | — |
| `username` | `varchar(80)` | Không | `string` | — |
| `password_hash` | `varchar(255)` | Không | `hashed` | — |
| `role` | `varchar(20)` | Không | `string` | — |
| `is_active` | `tinyint(1)` | Không | `boolean` | — |
| `remember_token` | `varchar(100)` | Có | `string` | — |
| `created_at` | `datetime` | Không | `datetime` | — |
| `updated_at` | `datetime` | Không | `datetime` | — |

Quan hệ:

- `tenant()` → `Tenant` (BelongsTo)
- `cashTransactions()` → `CashTransaction` (HasMany)
- `invoiceAccessGrants()` → `InvoiceAccessGrant` (HasMany)
- `grantedInvoiceAccessGrants()` → `InvoiceAccessGrant` (HasMany)
- `issuedInvoices()` → `Invoice` (HasMany)
- `submittedMaintenanceRequests()` → `MaintenanceRequest` (HasMany)
- `meterReadings()` → `MeterReading` (HasMany)
- `payments()` → `Payment` (HasMany)

Khóa ngoại thực tế:

- `fk_users_tenant`: `tenant_id` → `tenants.id`

## View hiện có

Các view được ghi nhận trong bản chụp cấu trúc; chưa tạo model ghi cho view:

- `active_invoice_access`: quyền truy cập hóa đơn hiện còn hiệu lực.
- `invoice_balances`: tổng tiền, tiền đã trả, dư nợ và trạng thái thanh toán hóa đơn.
- `room_occupancy_summary`: tổng hợp tình trạng cư trú theo phòng.

## Kiểm tra lại

```powershell
cd D:\KLTN\backend
php artisan rental:verify-database
php artisan test
```

Lệnh xác minh đọc metadata từ MySQL, đối chiếu cột/khóa với bản chụp, kiểm tra timestamp và cột tự tính, kiểm tra quan hệ khóa ngoại, thực thi truy vấn quan hệ và tải thử dữ liệu bằng Eloquent. Chỉ in kết quả kiểm tra, không in bản ghi hoặc mật khẩu, không sửa dữ liệu.

PHPUnit dùng SQLite trong bộ nhớ để kiểm tra xác thực qua `username`/`password_hash`, quan hệ người phát hành hóa đơn và hành vi các cột thời gian tùy chỉnh.
