<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_validations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('attendance_locations')->cascadeOnDelete();
            $table->foreignId('employee_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ticket', 64)->unique();
            $table->string('status', 20)->default('issued');
            $table->dateTime('issued_at');
            $table->dateTime('expires_at');
            $table->dateTime('consumed_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('distance', 8, 2)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_validations');
    }
};
