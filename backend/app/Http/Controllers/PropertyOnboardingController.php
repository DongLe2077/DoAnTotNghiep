<?php

namespace App\Http\Controllers;

use App\Services\Tenancy\PropertyContext;
use App\Services\Tenancy\PropertyOnboarding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class PropertyOnboardingController extends Controller
{
    public function __construct(private readonly PropertyOnboarding $onboarding, private readonly PropertyContext $context) {}

    public function preview(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->onboarding->preview($this->code($request))]);
    }

    public function join(Request $request): JsonResponse
    {
        $code = $this->code($request);
        $result = $this->onboarding->requestJoin($request->user('web'), $code);

        return response()->json(['data' => $result['data']], $result['created'] ? 201 : 200);
    }

    public function ownRequests(Request $request): JsonResponse
    {
        return response()->json($this->onboarding->ownRequests($request->user('web'), $this->page($request)));
    }

    public function withdraw(Request $request, int $id): JsonResponse
    {
        abort_unless($this->context->enabled(), 404);

        return response()->json(['data' => $this->onboarding->withdraw($request->user('web'), $id)]);
    }

    public function invitations(Request $request): JsonResponse
    {
        $page = $this->page($request);

        return response()->json($this->onboarding->invitations($request->user('web'), $this->context->requireId(), $page));
    }

    public function issue(Request $request, ?int $id = null): JsonResponse
    {
        abort_unless($this->context->enabled(), 404);
        $fields = $request->validate([
            'days' => ['sometimes', 'required', 'integer', 'between:1,30'],
            'max_approvals' => ['sometimes', 'required', 'integer', 'between:1,100'],
            'property_id' => ['prohibited'],
        ]);

        return response()->json(['data' => $this->onboarding->issue(
            $request->user('web'), $this->context->requireId(), (int) ($fields['days'] ?? 7), (int) ($fields['max_approvals'] ?? 10), $id,
        )], 201);
    }

    public function revokeInvite(Request $request, int $id): Response
    {
        abort_unless($this->context->enabled(), 404);
        $this->onboarding->revokeInvite($request->user('web'), $this->context->requireId(), $id);

        return response()->noContent();
    }

    public function ownerRequests(Request $request): JsonResponse
    {
        $page = $this->page($request);
        $data = $request->validate(['status' => ['sometimes', Rule::in(['all', 'pending', 'approved', 'rejected', 'withdrawn'])]]);

        return response()->json($this->onboarding->ownerRequests(
            $request->user('web'), $this->context->requireId(), $data['status'] ?? 'pending', $page,
        ));
    }

    public function decide(Request $request, int $id): JsonResponse
    {
        abort_unless($this->context->enabled(), 404);
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])]]);

        return response()->json(['data' => $this->onboarding->decide($request->user('web'), $this->context->requireId(), $id, $data['decision'])]);
    }

    public function revokeMember(Request $request, int $id): Response
    {
        abort_unless($this->context->enabled(), 404);
        $this->onboarding->revokeMember($request->user('web'), $this->context->requireId(), $id);

        return response()->noContent();
    }

    private function code(Request $request): string
    {
        abort_unless($this->context->enabled(), 404);

        return $request->validate(['code' => ['required', 'string', 'max:64'], 'property_id' => ['prohibited']])['code'];
    }

    private function page(Request $request): int
    {
        abort_unless($this->context->enabled(), 404);

        return (int) ($request->validate(['page' => ['sometimes', 'integer', 'between:1,10000']])['page'] ?? 1);
    }
}
