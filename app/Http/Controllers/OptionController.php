<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\UpdateOptionRequest;
use App\Models\Option;
use App\Services\AuditLogService;
use App\Services\OptionService;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;

class OptionController extends Controller
{
    public function __construct(
        private readonly OptionService $options,
        private readonly AuditLogService $logs,
    ) {
    }

    /**
     * Настройки текущего пользователя.
     *
     * Без id в адресе и без выбора строки: настроек у человека ровно одна, и
     * чужие ему не нужны. Ответ всегда полный — с умолчаниями, даже если
     * человек ничего не сохранял и строки в базе нет.
     */
    public function show(OptionService $options): JsonResponse
    {
        return response()->json(['data' => $options->forCurrentUser()]);
    }

    public function update(UpdateOptionRequest $request, OptionService $options): JsonResponse
    {
        $before = $options->forCurrentUser();
        $after = $options->saveForCurrentUser($request->validated());

        $changed = $this->changedKeys($before, $after);

        if ($changed !== []) {
            $userId = (int) CurrentUser::id();

            $this->logs->record(
                AuditAction::OptionUpdated,
                'target_user_id',
                $userId,
                $userId,
                ['changed' => $changed],
            );
        }

        return response()->json(['data' => $after]);
    }

    /**
     * Ключи, значения которых после сохранения отличаются от прежних.
     *
     * В журнал идёт только разница: писать целиком означало бы засорять его
     * одинаковыми записями при каждом нажатии «Сохранить» без изменений.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<int, string>
     */
    private function changedKeys(array $before, array $after): array
    {
        $changed = [];

        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                $changed[] = $key;
            }
        }

        return $changed;
    }
}
