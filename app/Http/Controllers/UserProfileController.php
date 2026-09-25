<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserProfileRequest;
use App\Http\Requests\UpdateUserAvatarRequest;
use App\Http\Requests\UpdateUserProfileRequest;
use App\Http\Resources\UserProfileResource;
use App\Models\Image;
use App\Models\UserProfile;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Storage;

class UserProfileController extends Controller
{
    public function index(): ResourceCollection
    {
        return UserProfileResource::collection(
            UserProfile::with('avatarImage')->orderByDesc('id')->paginate()
        );
    }

    public function all(): ResourceCollection
    {
        return UserProfileResource::collection(
            UserProfile::with('avatarImage')->orderByDesc('id')->get()
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

        $image = Image::fromUploadedFile($request->file('file'));

        $oldAvatarId = $model->avatar_id;

        $model->update(['avatar_id' => $image->id]);

        if ($oldAvatarId !== null && $oldAvatarId !== $image->id) {
            $this->deleteUnusedImage($oldAvatarId);
        }

        return new UserProfileResource($model->load('avatarImage'));
    }

    private function deleteUnusedImage(int $imageId): void
    {
        $image = Image::query()->find($imageId);

        if ($image === null) {
            return;
        }

        $inUse = $image->items()->exists()
            || $image->stores()->exists()
            || $image->labelPresets()->exists()
            || UserProfile::query()->where('avatar_id', $imageId)->exists();

        if ($inUse) {
            return;
        }

        $path = $image->path;

        $image->delete();

        Storage::disk('s3')->delete($path);
    }

    public function delete(UserProfile $model): JsonResponse
    {
        $model->delete();

        return response()->json(null, 204);
    }
}
