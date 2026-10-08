<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('device_token');
            $table->string('device_name', 190)->nullable();
            $table->string('platform', 20)->nullable();
            $table->string('app_version', 30)->nullable();
            $table->string('status', 20)->default('active');
            $table->dateTime('registered_at')->nullable();
            $table->dateTime('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'device_token']);
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_devices');
    }
};
