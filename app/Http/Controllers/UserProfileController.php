<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserProfileRequest;
use App\Http\Requests\UpdateUserAvatarRequest;
use App\Http\Requests\UpdateUserProfileRequest;
use App\Http\Resources\UserProfileResource;
use App\Models\UserProfile;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    public function updateAvatar(UpdateUserAvatarRequest $request, UserProfile $model): UserProfileResource
    {
        if (CurrentUser::id() !== $model->id) {
            abort(403, 'Можно изменить только свой аватар');
        }

        $file = $request->file('file');
        $sha256 = hash_file('sha256', $file->getRealPath());
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension === '') {
            $extension = strtolower((string) Str::after($file->getMimeType(), '/'));
        }
        $path = 'avatar/' . $sha256 . '.' . $extension;

        Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()));

        $oldAvatar = $model->avatar;
        if ($oldAvatar !== null && $oldAvatar !== $path) {
            Storage::disk('s3')->delete($oldAvatar);
        }

        $model->update(['avatar' => $path]);

        return new UserProfileResource($model);
    }

    public function delete(UserProfile $model): JsonResponse
    {
        $model->delete();

        return response()->json(null, 204);
    }
}