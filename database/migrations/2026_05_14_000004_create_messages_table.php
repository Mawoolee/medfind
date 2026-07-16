<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('pharmacy_id')->constrained('pharmacies')->onDelete('cascade');
            $table->text('content');
            $table->enum('sender', ['consumer', 'pharmacy']);
            $table->boolean('is_read')->default(false);
            $table->string('prescription_file')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
