<?php

namespace App\Http\Requests\Owner;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

abstract class OwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('web');

        return $user instanceof User && $user->is_active && $user->hasRole(User::ROLE_OWNER);
    }

    public function attributes(): array
    {
        return [
            'room_number' => 'Số phòng',
            'capacity' => 'Sức chứa',
            'default_monthly_rent' => 'Giá thuê mặc định',
            'operational_status' => 'Trạng thái vận hành',
            'occupancy_status' => 'Tình trạng phòng',
            'service_code' => 'Mã dịch vụ',
            'name' => 'Tên dịch vụ',
            'unit' => 'Đơn vị tính',
            'billing_method' => 'Cách tính phí',
            'default_unit_price' => 'Đơn giá mặc định',
            'is_active' => 'Trạng thái sử dụng',
            'q' => 'Từ khóa',
            'page' => 'Trang',
            'per_page' => 'Số bản ghi mỗi trang',
            'full_name' => 'Họ tên',
            'phone_number' => 'Số điện thoại',
            'identity_number' => 'Số giấy tờ tùy thân',
            'group_code' => 'Mã nhóm thuê',
            'tenant_ids' => 'Danh sách người thuê',
            'tenant_ids.*' => 'Người thuê',
            'tenant_id' => 'Người thuê',
            'room_id' => 'Phòng',
            'rental_group_id' => 'Nhóm thuê',
            'representative_tenant_id' => 'Người đại diện',
            'replacement_tenant_id' => 'Người đại diện thay thế',
            'started_at' => 'Thời điểm bắt đầu',
            'moved_in_at' => 'Thời điểm vào ở',
            'moved_out_at' => 'Thời điểm rời đi',
            'transferred_at' => 'Thời điểm chuyển phòng',
            'ended_at' => 'Thời điểm trả phòng',
            'effective_from' => 'Thời điểm áp dụng dịch vụ',
            'effective_until' => 'Thời điểm ngừng dịch vụ',
            'service_id' => 'Dịch vụ',
            'unit_price' => 'Đơn giá áp dụng',
            'username' => 'Tên đăng nhập',
            'password' => 'Mật khẩu',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là bắt buộc.',
            'string' => ':attribute phải là chuỗi.',
            'max.string' => ':attribute không được vượt quá :max ký tự.',
            'integer' => ':attribute phải là số nguyên.',
            'min.numeric' => ':attribute phải từ :min trở lên.',
            'between.numeric' => ':attribute phải nằm trong khoảng :min đến :max.',
            'boolean' => ':attribute phải là true hoặc false.',
            'in' => ':attribute không hợp lệ.',
            'unique' => ':attribute đã tồn tại.',
            'regex' => ':attribute phải là số thập phân không âm, đúng độ chính xác cho phép.',
            'exists' => ':attribute không tồn tại.',
            'distinct' => ':attribute không được trùng lặp.',
            'array' => ':attribute phải là danh sách.',
            'date_format' => ':attribute cần có định dạng năm-tháng-ngày giờ:phút:giây.',
            'before_or_equal' => ':attribute không được ở tương lai.',
            'min.array' => ':attribute cần có ít nhất :min phần tử.',
            'max.array' => ':attribute không được vượt quá :max phần tử.',
            'min.string' => ':attribute cần ít nhất :min ký tự.',
            'confirmed' => 'Xác nhận :attribute chưa khớp.',
            'prohibited' => ':attribute do hệ thống quyết định, không nhập trực tiếp.',
        ];
    }
}
