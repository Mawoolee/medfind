<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Users table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('consumer');
            $table->foreignId('pharmacy_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // 2. Pharmacies table (ADD status column here)
        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->string('pharmacy_name');
            $table->string('pharmacyAddress');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('contactNumber');
            $table->string('status')->default('approved'); // ADD THIS LINE
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // 3. Add foreign key to users
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('pharmacy_id')->references('id')->on('pharmacies')->onDelete('set null');
        });

        // 4. Medicines table
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('medicine_name');
            $table->string('dosage');
            $table->string('manufacturer');
            $table->boolean('requiresPrescription')->default(false);
            $table->string('category')->nullable();
            $table->timestamps();
        });

        // 5. Inventory items table
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained('pharmacies')->onDelete('cascade');
            $table->foreignId('medicine_id')->constrained('medicines')->onDelete('cascade');
            $table->integer('stockQuantity')->default(0);
            $table->decimal('price', 10, 2);
            $table->string('status')->default('available');
            $table->timestamps();
            $table->unique(['pharmacy_id', 'medicine_id']);
        });

        // 6. Messages table
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('pharmacy_id')->constrained('pharmacies')->onDelete('cascade');
            $table->text('message');
            $table->string('prescription_image')->nullable();
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        // 7. Sessions table
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // 8. Cache table
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('medicines');
        Schema::dropIfExists('pharmacies');
        Schema::dropIfExists('users');
    }
};