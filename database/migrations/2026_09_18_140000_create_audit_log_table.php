<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->typeExists('audit_action')) {
            DB::statement("CREATE TYPE audit_action AS ENUM (
                'item.created','item.updated','item.deleted',
                'store.created','store.updated','store.deleted',
                'warehouse.created','warehouse.updated','warehouse.deleted',
                'label_preset.created','label_preset.updated','label_preset.deleted',
                'label_list.created','label_list.updated','label_list.deleted',
                'access_grant.created','access_grant.updated','access_grant.deleted',
                'user.created','user.updated','user.deleted',
                'operation.replenish','operation.writeoff',
                'image.attached','image.detached','image.alt_updated','image.reordered',
                'label.generate','auth.login','auth.logout'
            )");
        }

        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();

            $table->unsignedBigInteger('item_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('label_preset_id')->nullable();
            $table->unsignedBigInteger('label_list_id')->nullable();
            $table->unsignedBigInteger('access_grant_id')->nullable();
            $table->unsignedBigInteger('target_user_id')->nullable();

            $table->jsonb('payload')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['owner_id', 'created_at'], 'audit_log_owner_created_idx');

            foreach (['actor_id', 'item_id', 'store_id', 'warehouse_id',
                       'label_preset_id', 'label_list_id', 'access_grant_id',
                       'target_user_id'] as $col) {
                $table->index($col, "audit_log_{$col}_idx");
            }
        });

        // Нативные PG-типы (enum/inet) не поддерживаются Blueprint → сырым SQL.
        DB::statement('ALTER TABLE audit_log ADD COLUMN action audit_action NOT NULL');
        DB::statement('ALTER TABLE audit_log ADD COLUMN client_ip inet NULL');
        DB::statement('CREATE INDEX audit_log_action_created_idx ON audit_log (action, created_at)');

        // FK — nullOnDelete: лог переживает удаление сущности/пользователя.
        foreach (['actor_id', 'owner_id', 'target_user_id'] as $col) {
            Schema::table('audit_log', function (Blueprint $table) use ($col) {
                $table->foreign($col)->references('id')->on('user')->nullOnDelete();
            });
        }
        $fks = [
            'item_id'         => 'item',
            'store_id'        => 'store',
            'warehouse_id'    => 'warehouse',
            'label_preset_id' => 'label_preset',
            'label_list_id'   => 'label_list',
            'access_grant_id' => 'access_grant',
        ];
        foreach ($fks as $col => $tableName) {
            Schema::table('audit_log', function (Blueprint $table) use ($col, $tableName) {
                $table->foreign($col)->references('id')->on($tableName)->nullOnDelete();
            });
        }

        DB::statement('CREATE INDEX audit_log_payload_gin ON audit_log USING gin (payload)');
        DB::statement("ALTER TABLE audit_log ADD CONSTRAINT audit_log_single_target CHECK (
            num_nonnulls(item_id, store_id, warehouse_id, label_preset_id, label_list_id, access_grant_id, target_user_id) <= 1
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
        DB::statement('DROP TYPE IF EXISTS audit_action');
    }

    private function typeExists(string $type): bool
    {
        return (bool) DB::selectOne(
            "SELECT 1 FROM pg_type WHERE typname = ?",
            [$type]
        );
    }
};