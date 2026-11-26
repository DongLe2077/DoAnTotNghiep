<?php

namespace App\Http\Controllers;

use App\Services\Marketplace\RoomListings;
use App\Services\Marketplace\RoomRequests;
use App\Services\Tenancy\PropertyContext;
use Illuminate\Http\Request;

class RoomMarketplaceController extends Controller
{
    public function __construct(
        private readonly PropertyContext $context,
        private readonly RoomListings $listings,
        private readonly RoomRequests $requests,
    ) {}

    public function index(Request $request)
    {
        $this->enabled();
        $filters = $request->validate([
            'area' => ['sometimes', 'string', 'max:150'],
            'min_price' => ['sometimes', 'numeric', 'min:0', 'max:1000000000000'],
            'max_price' => ['sometimes', 'numeric', 'min:0', 'max:1000000000000'],
            'people' => ['sometimes', 'integer', 'between:1,50'],
            'page' => ['sometimes', 'integer', 'between:1,10000'],
        ]);
        abort_if(isset($filters['min_price'], $filters['max_price']) && $filters['min_price'] > $filters['max_price'],
            422, 'Giá thấp nhất phải nhỏ hơn hoặc bằng giá cao nhất.');

        return response()->json($this->listings->search($filters));
    }

    public function detail(int $id)
    {
        $this->enabled();

        return response()->json(['data' => $this->listings->find($id)]);
    }

    public function photo(int $id)
    {
        $this->enabled();
        $row = $this->listings->photo($id);
        $contents = base64_decode($row->photo_base64, true);
        abort_unless($contents && in_array($row->photo_mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return response($contents, 200, ['Content-Type' => $row->photo_mime]);
    }

    public function ownerListings(Request $request)
    {
        $propertyId = $this->property();
        $request->validate(['page' => ['sometimes', 'integer', 'between:1,10000']]);

        return response()->json($this->listings->forOwner($propertyId));
    }

    public function saveListing(Request $request, ?int $id = null)
    {
        $propertyId = $this->property();
        $data = $request->validate([
            'room_id' => $id === null ? ['required', 'integer', 'exists:rooms,id'] : ['prohibited'],
            'title' => ['required', 'string', 'max:120'],
            'area' => ['required', 'string', 'max:150'],
            'area_m2' => ['nullable', 'numeric', 'between:1,99999'],
            'description' => ['required', 'string', 'max:3000'],
            'fee_notes' => ['nullable', 'string', 'max:500'],
            'amenities' => ['nullable', 'string', 'max:500'],
            'sharing' => ['required', 'boolean'],
            'published' => ['required', 'boolean'],
            'photo' => ['nullable', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:1024', 'dimensions:max_width=4096,max_height=4096'],
            'remove_photo' => ['sometimes', 'boolean'],
        ]);
        if ($file = $request->file('photo')) {
            $data['photo_mime'] = $file->getMimeType();
            $data['photo_base64'] = base64_encode($file->get());
        } elseif (! empty($data['remove_photo'])) {
            $data['photo_mime'] = null;
            $data['photo_base64'] = null;
        }
        unset($data['photo'], $data['remove_photo']);

        return response()->json(['data' => ['id' => $this->listings->save($propertyId, $data, $id)]], $id === null ? 201 : 200);
    }

    public function hideListing(int $id)
    {
        $this->listings->hide($this->property(), $id);

        return response()->noContent();
    }

    public function createRequest(Request $request, int $id)
    {
        $this->enabled();
        $data = $request->validate([
            'kind' => ['required', 'in:viewing,rent'],
            'preferred_at' => ['required', 'date_format:Y-m-d H:i:s', 'after:now', 'before_or_equal:'.now()->addMonths(6)->toDateTimeString()],
            'people' => ['required', 'integer', 'between:1,50'],
            'contact_name' => ['required', 'string', 'max:150'],
            'contact_phone' => ['required', 'string', 'regex:/^\+?[0-9]{8,15}$/D'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['preferred_at.after' => 'Hãy chọn ngày giờ trong tương lai.', 'contact_phone.regex' => 'Nhập số điện thoại hợp lệ.']);

        return response()->json(['data' => $this->requests->create($request->user('web'), $id, $data)], 201);
    }

    public function ownRequests(Request $request)
    {
        $this->enabled();
        $request->validate(['page' => ['sometimes', 'integer', 'between:1,10000']]);

        return response()->json($this->requests->forUser($request->user('web')));
    }

    public function userAction(Request $request, int $id)
    {
        $this->enabled();
        $data = $request->validate(['action' => ['required', 'in:accept_time,cancel']]);

        return response()->json(['data' => $this->requests->userAction($request->user('web'), $id, $data['action'])]);
    }

    public function ownerRequests(Request $request)
    {
        $propertyId = $this->property();
        $filters = $request->validate([
            'kind' => ['sometimes', 'in:viewing,rent'],
            'status' => ['sometimes', 'in:pending,confirmed,proposed,viewed,cancelled,no_show,new,discussing,accepted,rejected,withdrawn,moved_in'],
            'page' => ['sometimes', 'integer', 'between:1,10000'],
        ]);

        return response()->json($this->requests->forOwner($propertyId, $filters));
    }

    public function ownerAction(Request $request, int $id)
    {
        $propertyId = $this->property();
        $data = $request->validate([
            'action' => ['required', 'in:confirm,propose,cancel,viewed,no_show,discuss,accept,reject'],
            'owner_note' => ['nullable', 'string', 'max:500'],
            'appointment_at' => ['required_if:action,propose', 'nullable', 'date_format:Y-m-d H:i:s',
                'after:now', 'before_or_equal:'.now()->addMonths(6)->toDateTimeString()],
        ]);

        return response()->json(['data' => $this->requests->ownerAction($request->user('web'), $propertyId, $id, $data)]);
    }

    public function moveIn(Request $request, int $id)
    {
        $propertyId = $this->property();
        $data = $request->validate([
            'started_at' => ['required', 'date_format:Y-m-d H:i:s', 'before_or_equal:now'],
            'approve_membership' => ['required', 'boolean'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'companion_ids' => ['sometimes', 'array', 'max:49'],
            'companion_ids.*' => ['required', 'integer', 'distinct', 'exists:tenants,id'],
        ]);

        return response()->json(['data' => $this->requests->moveIn($request->user('web'), $propertyId, $id, $data)]);
    }

    private function enabled(): void
    {
        abort_unless($this->context->enabled(), 404);
    }

    private function property(): int
    {
        $this->enabled();

        return $this->context->requireId();
    }
}
