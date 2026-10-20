<?php

namespace App\Http\Requests\Tenant;

class UploadMaintenancePhotosRequest extends TenantRequest
{
    public function rules(): array
    {
        return ['photos' => ['required', 'array', 'min:1', 'max:5'],
            'photos.*' => ['bail', 'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp',
                'extensions:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
            'uploaded_by' => ['prohibited'], 'path' => ['prohibited'], 'maintenance_request_id' => ['prohibited']];
    }
}
