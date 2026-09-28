<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE emergency_requests MODIFY address LONGTEXT NULL');
        DB::statement('ALTER TABLE emergency_requests MODIFY suspected_condition LONGTEXT NULL');
        DB::statement('ALTER TABLE emergency_requests MODIFY first_aid_steps LONGTEXT NULL');

        DB::table('emergency_requests')
            ->orderBy('id')
            ->chunkById(100, function ($requests): void {
                foreach ($requests as $request) {
                    $updates = [];

                    foreach (['address', 'description', 'suspected_condition', 'cancel_reason'] as $field) {
                        if ($request->{$field} !== null && $this->needsEncryption($request->{$field})) {
                            $updates[$field] = Crypt::encryptString((string) $request->{$field});
                        }
                    }

                    if ($request->first_aid_steps !== null && $this->needsEncryption($request->first_aid_steps)) {
                        $updates['first_aid_steps'] = Crypt::encryptString((string) $request->first_aid_steps);
                    }

                    if ($updates !== []) {
                        DB::table('emergency_requests')
                            ->where('id', $request->id)
                            ->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE emergency_requests MODIFY address VARCHAR(255) NULL');
        DB::statement('ALTER TABLE emergency_requests MODIFY suspected_condition VARCHAR(255) NULL');
        DB::statement('ALTER TABLE emergency_requests MODIFY first_aid_steps JSON NULL');
    }

    private function needsEncryption(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return false;
        } catch (\Throwable) {
            return true;
        }
    }
};