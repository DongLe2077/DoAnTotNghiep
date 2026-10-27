<?php

namespace App\Http\Requests\Tenant;

class UploadMaintenancePhotosRequest extends TenantRequest
{
    public function messages(): array
    {
        return [
            'photos.required' => 'Hãy chọn ít nhất một ảnh sự cố.',
            'photos.array' => 'Danh sách ảnh chưa hợp lệ.',
            'photos.max' => 'Mỗi lần gửi tối đa 5 ảnh.',
            'photos.*.file' => 'Tệp ảnh chưa được nhận đầy đủ. Vui lòng chọn và gửi lại.',
            'photos.*.uploaded' => 'Tệp ảnh chưa được nhận đầy đủ. Vui lòng chọn và gửi lại.',
            'photos.*.image' => 'Tệp đã chọn không phải ảnh hợp lệ. Hãy chọn ảnh khác.',
            'photos.*.mimes' => 'Chỉ nhận ảnh JPG, PNG hoặc WebP.',
            'photos.*.extensions' => 'Tên tệp phải có đuôi JPG, JPEG, PNG hoặc WebP.',
            'photos.*.max' => 'Mỗi ảnh tối đa 5 MB.',
            'photos.*.dimensions' => 'Mỗi ảnh tối đa 4096 × 4096 px. Hãy chọn ảnh nhỏ hơn.',
        ];
    }

    public function rules(): array
    {
        return ['photos' => ['required', 'array', 'min:1', 'max:5'],
            'photos.*' => ['bail', 'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp',
                'extensions:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
            'uploaded_by' => ['prohibited'], 'path' => ['prohibited'], 'maintenance_request_id' => ['prohibited']];
    }
}
