<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Support\CurrentUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * Пишет записи в журнал действий audit_log.
 *
 * - актор берётся из CurrentUser (auth-контекст запроса);
 * - владелец домена — из сущности-цели (видимость лога/графиков);
 * - клиентский IP/UA — из текущего запроса (если есть);
 * - если таблицы логов ещё нет (самая первая миграция) — тихо пропускает.
 */
class AuditLogService
{
    private static bool $available = true;
    private static bool $checked = false;

    /**
     * Записать событие.
     *
     * @param  AuditAction  $action
     * @param  string|null  $entityColumn одна из: item_id, store_id, warehouse_id,
     *                                    label_preset_id, label_list_id, access_grant_id,
     *                                    target_user_id, category_id, property_id,
     *                                    property_group_id, dictionary_id,
     *                                    dictionary_value_id, unit_id;
     *                                    null — событие без цели (label.generate, auth.*)
     * @param  int|null  $entityId
     * @param  int  $ownerId    владелец домена (для скоупа видимости)
     * @param  array  $payload   детальные данные события
     * @param  int|null  $actorId  явный актор (по умолчанию — текущий пользователь)
     */
    public function record(
        AuditAction $action,
        ?string $entityColumn,
        ?int $entityId,
        int $ownerId,
        array $payload = [],
        ?int $actorId = null,
    ): void {
        if (! $this->available()) {
            return;
        }

        $data = [
            'owner_id'   => $ownerId !== 0 ? $ownerId : null,
            'actor_id'   => $actorId ?? CurrentUser::id(),
            'action'     => $action,
            'payload'    => $payload,
            'client_ip'  => $this->clientIp(),
            'user_agent' => $this->userAgent(),
        ];

        if ($entityColumn !== null) {
            $allowed = [
                'item_id', 'store_id', 'warehouse_id', 'label_preset_id',
                'label_list_id', 'access_grant_id', 'target_user_id',
                'category_id', 'property_id', 'property_group_id',
                'dictionary_id', 'dictionary_value_id', 'unit_id',
            ];

            if (! in_array($entityColumn, $allowed, true)) {
                throw new \InvalidArgumentException("Недопустимая колонка-цель: {$entityColumn}");
            }

            $data[$entityColumn] = $entityId;

            // Дублируем ссылку в payload: при nullOnDelete FK-колонка обнулится,
            // а ссылка на удалённую сущность в журнале должна сохраниться.
            $data['payload'] = array_merge($payload, [
                'entity_type' => $entityColumn === 'target_user_id' ? 'user' : str_replace('_id', '', $entityColumn),
                'entity_id'   => (int) $entityId,
            ]);
        }

        AuditLog::create($data);
    }

    /**
     * Записать CRUD-событие по Eloquent-модели.
     * Owner и колонка-цель определяются автоматически по типу модели.
     *
     * @param  AuditAction  $action
     * @param  array  $payload
     */
    public function recordForModel(AuditAction $action, Model $model, array $payload = []): void
    {
        [$column, $id, $ownerId] = $this->resolveTarget($model);

        if ($column === null || $id === null) {
            return;
        }

        $this->record($action, $column, $id, $ownerId, $payload);
    }

    /**
     * Данные для события «элемент создан»: снапшот нового состояния.
     */
    public function payloadForCreated(Model $model): array
    {
        return [
            'snapshot' => $this->snapshot($model),
        ];
    }

    /**
     * Данные для события «элемент обновлён»: дифф изменённых полей (before → after).
     */
    public function payloadForUpdated(Model $model): array
    {
        $changes = [];
        $hidden = array_flip($model->getHidden());
        foreach ($model->getChanges() as $key => $after) {
            if (isset($hidden[$key]) || (str_ends_with($key, '_at') && $after === null)) {
                continue;
            }
            $changes[$key] = [$model->getOriginal($key), $after];
        }

        return ['changes' => $changes];
    }

    /**
     * Данные для события «элемент удалён»: полный снапшот, чтобы после
     * удаления записи данные всё равно остались доступны в журнале.
     */
    public function payloadForDeleted(Model $model): array
    {
        return [
            'snapshot' => $model->getAttributes(),
        ];
    }

    /**
     * Определяет колонку-цель, id и владельца для модели.
     */
    private function resolveTarget(Model $model): array
    {
        $table = $model->getTable();

        $map = [
            'item'          => ['item_id', 'user_id'],
            'store'         => ['store_id', 'user_id'],
            'warehouse'     => ['warehouse_id', 'user_id'],
            'label_preset'  => ['label_preset_id', 'user_id'],
            'label_list'    => ['label_list_id', 'user_id'],
            'access_grant'  => ['access_grant_id', 'owner_id'],
            'user'          => ['target_user_id', 'id'],
            'category'      => ['category_id', 'user_id'],
            'property'      => ['property_id', 'user_id'],
            'property_group' => ['property_group_id', 'user_id'],
            'dictionary'    => ['dictionary_id', 'user_id'],
            'dictionary_value' => ['dictionary_value_id', 'user_id'],
            'unit'          => ['unit_id', 'user_id'],
        ];

        if (! isset($map[$table])) {
            return [null, null, null];
        }

        [$column, $ownerSource] = $map[$table];

        // У значения справочника своего user_id нет — владение выводится из
        // справочника-родителя. Без этой ветки записи о значениях писались бы
        // с owner_id = null и не были бы видны ни в списке журнала, ни в
        // графиках: оба смотрят строго по владельцу домена.
        $ownerId = $table === 'dictionary_value'
            ? (int) ($model->dictionary?->user_id ?? 0)
            : (int) ($model->{$ownerSource} ?? 0);

        return [$column, (int) $model->getKey(), $ownerId];
    }

    private function snapshot(Model $model): array
    {
        $hidden = array_flip($model->getHidden());

        return array_filter(
            $model->getAttributes(),
            fn (string $key) => ! isset($hidden[$key]),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function clientIp(): ?string
    {
        $request = Request::instance();

        return $request !== null ? $request->ip() : null;
    }

    private function userAgent(): ?string
    {
        $request = Request::instance();

        return $request !== null ? substr((string) $request->userAgent(), 0, 512) : null;
    }

    private function available(): bool
    {
        if (! self::$checked) {
            self::$available = DB::getSchemaBuilder()->hasTable('audit_log');
            self::$checked = true;
        }

        return self::$available;
    }
}