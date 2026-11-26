<?php

namespace App\Http\Controllers;

use App\Models\RentalProperty;
use App\Services\Tenancy\PropertyAccess;
use App\Services\Tenancy\PropertyContext;
use App\Services\Tenancy\PropertyWorkspaces;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PropertyWorkspaceController extends Controller
{
    public function index(Request $request, PropertyWorkspaces $workspaces)
    {
        abort_unless(app(PropertyContext::class)->enabled(), 404);

        return response()->json(['data' => $workspaces->visibleTo($request->user('web')), 'selected_id' => $request->session()->get('property_id')]);
    }

    public function store(Request $request, PropertyWorkspaces $workspaces)
    {
        abort_unless(app(PropertyContext::class)->enabled(), 404);
        abort_unless($request->user('web')->canCreateRentalProperty(), 403, 'Chỉ chủ trọ được tạo khu trọ.');
        $request->validate([
            'photo' => ['nullable', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:3072', 'dimensions:max_width=4096,max_height=4096'],
        ]);
        $path = $request->file('photo')?->store('property-photos', 'local');
        abort_if($path === false, 503, 'Chưa lưu được ảnh khu trọ.');

        try {
            $property = $workspaces->create($request->user('web'), $request->except('photo'), $path);
        } catch (Throwable $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return response()->json(['data' => ['id' => $property->id, 'name' => $property->name, 'address' => $property->address, 'role' => 'owner']], 201);
    }

    public function photo(PropertyContext $context)
    {
        abort_unless($context->enabled(), 404);
        $property = RentalProperty::query()->findOrFail($context->requireId());
        $path = $property->photo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function select(Request $request, PropertyAccess $access)
    {
        abort_unless(app(PropertyContext::class)->enabled(), 404);
        $id = $request->validate(['property_id' => 'required|integer|min:1'])['property_id'];
        $access->member($request->user('web'), $id);
        $request->session()->put('property_id', (int) $id);

        return response()->json(['data' => ['property_id' => (int) $id]]);
    }

    public function members(Request $request, PropertyAccess $access)
    {
        $context = app(PropertyContext::class);
        abort_unless($context->enabled(), 404);
        $access->owner($request->user('web'), $context->requireId());
        $members = DB::table('property_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.property_id', $context->requireId())->where('m.user_id', '!=', $request->user('web')->id)->where('m.status', 'approved')->whereNull('m.revoked_at')->where('u.is_active', true)
            ->orderBy('u.username')->get(['u.id', 'u.username', 'u.full_name', 'u.contact_phone', 'm.tenant_id', 'm.approved_at']);

        return response()->json(['data' => $members]);
    }

    public function link(Request $request, PropertyWorkspaces $workspaces)
    {
        abort_unless(app(PropertyContext::class)->enabled(), 404);
        $data = $request->validate(['user_id' => 'required|integer|min:1', 'tenant_id' => 'required|integer|min:1']);
        $workspaces->linkTenant($request->user('web'), app(PropertyContext::class)->requireId(), $data['user_id'], $data['tenant_id']);

        return response()->noContent();
    }
}
