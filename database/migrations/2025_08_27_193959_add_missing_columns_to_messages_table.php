<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('messages', 'subject')) {
                $table->string('subject')->nullable()->after('recipient');
            }
            
            if (!Schema::hasColumn('messages', 'status')) {
                $table->enum('status', ['pending', 'queued', 'sending', 'sent', 'delivered', 'failed', 'cancelled'])
                      ->default('pending')->index()->after('message');
            }
            
            if (!Schema::hasColumn('messages', 'priority')) {
                $table->enum('priority', ['low', 'normal', 'high', 'urgent'])
                      ->default('normal')->index()->after('status');
            }
            
            if (!Schema::hasColumn('messages', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->index()->after('priority');
            }
            
            if (!Schema::hasColumn('messages', 'metadata')) {
                $table->json('metadata')->nullable()->after('scheduled_at');
            }
            
            if (!Schema::hasColumn('messages', 'tags')) {
                $table->json('tags')->nullable()->after('metadata');
            }
            
            if (!Schema::hasColumn('messages', 'webhook_url')) {
                $table->string('webhook_url')->nullable()->after('tags');
            }
            
            if (!Schema::hasColumn('messages', 'api_key')) {
                $table->string('api_key')->index()->after('webhook_url');
            }
            
            if (!Schema::hasColumn('messages', 'ip_address')) {
                $table->ipAddress('ip_address')->after('api_key');
            }
            
            if (!Schema::hasColumn('messages', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
            
            // Add provider if missing
            if (!Schema::hasColumn('messages', 'provider')) {
                $table->string('provider')->nullable()->index()->after('priority');
            }
            
            // Add external_id if missing
            if (!Schema::hasColumn('messages', 'external_id')) {
                $table->string('external_id')->nullable()->index()->after('provider');
            }
            
            // Add delivery timestamps if missing
            if (!Schema::hasColumn('messages', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('scheduled_at');
            }
            
            if (!Schema::hasColumn('messages', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('sent_at');
            }
            
            if (!Schema::hasColumn('messages', 'failed_at')) {
                $table->timestamp('failed_at')->nullable()->after('delivered_at');
            }
            
            if (!Schema::hasColumn('messages', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('failed_at');
            }
            
            // Add error handling columns if missing
            if (!Schema::hasColumn('messages', 'error_message')) {
                $table->text('error_message')->nullable()->after('cancelled_at');
            }
            
            if (!Schema::hasColumn('messages', 'retry_count')) {
                $table->integer('retry_count')->default(0)->after('error_message');
            }
            
            // Add webhook tracking columns if missing
            if (!Schema::hasColumn('messages', 'webhook_delivered')) {
                $table->boolean('webhook_delivered')->default(false)->after('webhook_url');
            }
            
            if (!Schema::hasColumn('messages', 'webhook_attempts')) {
                $table->integer('webhook_attempts')->default(0)->after('webhook_delivered');
            }
            
            if (!Schema::hasColumn('messages', 'webhook_error')) {
                $table->text('webhook_error')->nullable()->after('webhook_attempts');
            }
            
            if (!Schema::hasColumn('messages', 'webhook_failed_at')) {
                $table->timestamp('webhook_failed_at')->nullable()->after('webhook_error');
            }
            
            // Add cost tracking columns if missing
            if (!Schema::hasColumn('messages', 'cost_amount')) {
                $table->decimal('cost_amount', 10, 4)->nullable()->after('user_agent');
            }
            
            if (!Schema::hasColumn('messages', 'cost_currency')) {
                $table->string('cost_currency', 3)->nullable()->after('cost_amount');
            }
            
            if (!Schema::hasColumn('messages', 'duration_ms')) {
                $table->integer('duration_ms')->nullable()->after('cost_currency');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $columnsToRemove = [
                'subject', 'status', 'priority', 'provider', 'external_id',
                'scheduled_at', 'sent_at', 'delivered_at', 'failed_at', 'cancelled_at',
                'error_message', 'retry_count', 'metadata', 'tags', 'webhook_url',
                'webhook_delivered', 'webhook_attempts', 'webhook_error', 'webhook_failed_at',
                'api_key', 'ip_address', 'user_agent', 'cost_amount', 'cost_currency', 'duration_ms'
            ];
            
            foreach ($columnsToRemove as $column) {
                if (Schema::hasColumn('messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
