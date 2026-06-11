<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Groups\AddGroupMember;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\RemoveGroupMember;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\GroupResource;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class GroupController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $groups = $request->user()
            ->groups()
            ->withCount('members')
            ->orderByDesc('groups.created_at')
            ->get();

        return GroupResource::collection($groups);
    }

    public function store(Request $request, CreateGroup $action): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer'],
        ]);

        $group = $action->execute(
            owner: $request->user(),
            name: $data['name'],
            memberIds: $data['member_ids'] ?? [],
            description: $data['description'] ?? null,
        );

        $group->load('members');

        return GroupResource::make($group)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Group $group): GroupResource
    {
        $this->authorize('view', $group);

        $group->load('members');

        return GroupResource::make($group);
    }

    public function addMember(Request $request, Group $group, AddGroupMember $action): JsonResponse
    {
        $this->authorize('manageMembers', $group);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $newMember = User::query()->findOrFail($data['user_id']);

        $action->execute($request->user(), $group, $newMember);

        $group->load('members');

        return GroupResource::make($group->refresh()->load('members'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function removeMember(Request $request, Group $group, User $user, RemoveGroupMember $action): Response
    {
        $this->authorize('manageMembers', $group);

        $action->execute($request->user(), $group, $user);

        return response()->noContent();
    }
}
