<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->safeIndex('users', 'users_status_index', ['status']);
        $this->safeIndex('users', 'users_is_verified_index', ['is_verified']);
        $this->safeIndex('users', 'users_created_at_index', ['created_at']);

        $this->safeIndex('orders', 'orders_pharmacy_id_status_index', ['pharmacy_id', 'status']);
        $this->safeIndex('orders', 'orders_payment_status_created_at_index', ['payment_status', 'created_at']);
        $this->safeIndex('orders', 'orders_status_updated_at_index', ['status', 'updated_at']);
        $this->safeIndex('orders', 'orders_status_index', ['status']);

        $this->safeIndex('ambulance_units', 'ambulance_units_company_status_index', ['ambulance_company_id', 'status']);
        $this->safeIndex('ambulance_units', 'ambulance_units_status_is_active_index', ['status', 'is_active']);
        $this->safeIndex('ambulance_units', 'ambulance_units_is_active_index', ['is_active']);

        $this->safeIndex('wallet_transactions', 'wallet_tx_type_status_created_at_index', ['type', 'status', 'created_at']);

        $this->safeIndex('audit_logs', 'audit_logs_created_at_index', ['created_at']);
        $this->safeIndex('audit_logs', 'audit_logs_action_index', ['action']);
        $this->safeIndex('audit_logs', 'audit_logs_user_id_index', ['user_id']);
    }

    public function down(): void
    {
        $this->safeDropIndex('users', 'users_status_index');
        $this->safeDropIndex('users', 'users_is_verified_index');
        $this->safeDropIndex('users', 'users_created_at_index');

        $this->safeDropIndex('orders', 'orders_pharmacy_id_status_index');
        $this->safeDropIndex('orders', 'orders_payment_status_created_at_index');
        $this->safeDropIndex('orders', 'orders_status_updated_at_index');
        $this->safeDropIndex('orders', 'orders_status_index');

        $this->safeDropIndex('ambulance_units', 'ambulance_units_company_status_index');
        $this->safeDropIndex('ambulance_units', 'ambulance_units_status_is_active_index');
        $this->safeDropIndex('ambulance_units', 'ambulance_units_is_active_index');

        $this->safeDropIndex('wallet_transactions', 'wallet_tx_type_status_created_at_index');

        $this->safeDropIndex('audit_logs', 'audit_logs_created_at_index');
        $this->safeDropIndex('audit_logs', 'audit_logs_action_index');
        $this->safeDropIndex('audit_logs', 'audit_logs_user_id_index');
    }

    /**
     * @param  list<string>  $columns
     */
    private function safeIndex(string $table, string $indexName, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        if ($this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
            $blueprint->index($columns, $indexName);
        });
    }

    private function safeDropIndex(string $table, string $indexName): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = Schema::getConnection()->getDatabaseName();

        $row = DB::selectOne(
            'SELECT 1 AS present
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND index_name = ?
             LIMIT 1',
            [$database, $table, $indexName]
        );

        return $row !== null;
    }
};
