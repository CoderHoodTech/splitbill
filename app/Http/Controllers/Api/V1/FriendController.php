<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Friends\AcceptFriendRequest;
use App\Actions\Friends\DeclineFriendRequest;
use App\Actions\Friends\SendFriendRequest;
use App\Enums\FriendRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\FriendRequestResource;
use App\Http\Resources\V1\UserResource;
use App\Models\FriendRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class FriendController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $friends = $request->user()->friends()->orderBy('name')->get();

        return UserResource::collection($friends);
    }

    public function requests(Request $request): JsonResponse
    {
        $user = $request->user();

        $incoming = $user->receivedFriendRequests()
            ->with(['sender', 'receiver'])
            ->where('status', FriendRequestStatus::Pending)
            ->latest()
            ->get();

        $sent = $user->sentFriendRequests()
            ->with(['sender', 'receiver'])
            ->where('status', FriendRequestStatus::Pending)
            ->latest()
            ->get();

        return response()->json([
            'incoming' => FriendRequestResource::collection($incoming)->toArray($request),
            'sent' => FriendRequestResource::collection($sent)->toArray($request),
        ]);
    }

    public function store(Request $request, SendFriendRequest $action): JsonResponse
    {
        $data = $request->validate([
            'friend_code' => ['required', 'string', 'max:32'],
        ]);

        $friendRequest = $action->execute($request->user(), $data['friend_code']);
        $friendRequest->load(['sender', 'receiver']);

        return FriendRequestResource::make($friendRequest)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function accept(Request $request, FriendRequest $friendRequest, AcceptFriendRequest $action): FriendRequestResource
    {
        $this->authorize('accept', $friendRequest);

        $accepted = $action->execute($request->user(), $friendRequest);
        $accepted->load(['sender', 'receiver']);

        return FriendRequestResource::make($accepted);
    }

    public function decline(Request $request, FriendRequest $friendRequest, DeclineFriendRequest $action): FriendRequestResource
    {
        $this->authorize('decline', $friendRequest);

        $declined = $action->execute($request->user(), $friendRequest);
        $declined->load(['sender', 'receiver']);

        return FriendRequestResource::make($declined);
    }
}
