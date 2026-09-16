<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserProfileRequest;
use App\Http\Requests\UpdateUserProfileRequest;
use App\Http\Resources\UserProfileResource;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UserProfileController extends Controller
{
    public function index(): ResourceCollection
    {
        return UserProfileResource::collection(
            UserProfile::orderBy('id')->paginate()
        );
    }

    public function all(): ResourceCollection
    {
        return UserProfileResource::collection(
            UserProfile::orderBy('id')->get()
        );
    }

    public function get(UserProfile $model): UserProfileResource
    {
        return new UserProfileResource($model);
    }

    public function post(StoreUserProfileRequest $request): JsonResponse
    {
        $user = UserProfile::create($request->validated());

        return (new UserProfileResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function put(UpdateUserProfileRequest $request, UserProfile $model): UserProfileResource
    {
        $model->update($request->validated());

        return new UserProfileResource($model);
    }

    public function delete(UserProfile $model): JsonResponse
    {
        $model->delete();

        return response()->json(null, 204);
    }
}