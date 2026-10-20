<?php

namespace App\Http\Requests\Tenant;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

abstract class TenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('web');

        return $user instanceof User && $user->is_active && $user->hasRole(User::ROLE_TENANT);
    }

    public function attributes(): array
    {
        return ['title' => 'Tiêu đề', 'description' => 'Mô tả sự cố', 'status' => 'Trạng thái',
            'resolution' => 'Kết quả xử lý', 'q' => 'Từ khóa', 'page' => 'Trang', 'per_page' => 'Số bản ghi mỗi trang',
            'occupancy_id' => 'Đợt thuê', 'room_id' => 'Phòng', 'from' => 'Thời điểm từ', 'until' => 'Thời điểm đến'];
    }

    public function messages(): array
    {
        return ['required' => ':attribute là bắt buộc.', 'string' => ':attribute phải là chuỗi.',
            'max.string' => ':attribute không được vượt quá :max ký tự.', 'integer' => ':attribute phải là số nguyên.',
            'min.numeric' => ':attribute phải từ :min trở lên.', 'between.numeric' => ':attribute phải nằm trong khoảng :min đến :max.',
            'in' => ':attribute không hợp lệ.', 'prohibited' => ':attribute do hệ thống quyết định, không nhập trực tiếp.',
            'date_format' => ':attribute cần có định dạng năm-tháng-ngày giờ:phút:giây.'];
    }
}
