<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The controllers write status 'no_credit' for SMS messages with no balance, but the
 * original enum doesn't include it. On PostgreSQL Laravel enums are a CHECK constraint,
 * so on a fresh database those inserts are rejected.
 *
 * Some existing databases already had this constraint removed by hand, so this only
 * drops it when it is still present and does not add a new one (which could fail on
 * existing rows).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $constraints = DB::select("
            SELECT con.conname
            FROM pg_constraint con
            WHERE con.conrelid = 'messages'::regclass
              AND con.contype = 'c'
              AND pg_get_constraintdef(con.oid) LIKE '%(status)%'
        ");

        foreach ($constraints as $constraint) {
            DB::statement('ALTER TABLE messages DROP CONSTRAINT "' . $constraint->conname . '"');
        }
    }

    public function down(): void
    {
        // Not restored: rows with status 'no_credit' would violate the original constraint.
    }
};
