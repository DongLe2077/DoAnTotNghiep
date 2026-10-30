<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\SaveRentalPropertyRequest;
use App\Services\RentalPropertyProfile;
use Illuminate\Http\JsonResponse;

class RentalPropertyController extends Controller
{
    public function __construct(private readonly RentalPropertyProfile $profile) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->profile->data()]);
    }

    public function update(SaveRentalPropertyRequest $request): JsonResponse
    {
        $this->profile->save($request->validated(), $request->user('web')->id);
        return response()->json(['message' => 'Đã lưu thông tin nhà trọ. Chứng từ xuất tiếp theo dùng thông tin này.', 'data' => $this->profile->data()]);
    }
}
