<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            }
            if (! Schema::hasColumn('messages', 'verification_status')) {
                $table->string('verification_status')->nullable()->index();
            }
            if (! Schema::hasColumn('messages', 'verification_notes')) {
                $table->text('verification_notes')->nullable();
            }
            if (! Schema::hasColumn('messages', 'verified_at')) {
                $table->timestamp('verified_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'verified_by')) {
                $table->dropForeign(['verified_by']);
                $table->dropColumn('verified_by');
            }
            if (Schema::hasColumn('messages', 'verification_status')) {
                $table->dropIndex(['verification_status']);
                $table->dropColumn('verification_status');
            }
            if (Schema::hasColumn('messages', 'verification_notes')) {
                $table->dropColumn('verification_notes');
            }
            if (Schema::hasColumn('messages', 'verified_at')) {
                $table->dropColumn('verified_at');
            }
        });
    }
};
