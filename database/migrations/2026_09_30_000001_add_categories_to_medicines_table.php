<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->json('categories')->nullable();
        });

        DB::table('medicines')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($medicines): void {
                foreach ($medicines as $medicine) {
                    DB::table('medicines')
                        ->where('id', $medicine->id)
                        ->update(['categories' => json_encode([$medicine->category])]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn('categories');
        });
    }
};
