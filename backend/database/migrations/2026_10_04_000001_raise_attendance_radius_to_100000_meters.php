<?php

use App\Models\AttendanceLocation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PREVIOUS_RADIUS_METERS = 200;

    public function up(): void
    {
        Schema::table('attendance_locations', function (Blueprint $table): void {
            $table->unsignedInteger('radius')->default(AttendanceLocation::DEFAULT_RADIUS_METERS)->change();
        });

        DB::table('attendance_locations')->update(['radius' => AttendanceLocation::DEFAULT_RADIUS_METERS]);

        Schema::table('attendance_settings', function (Blueprint $table): void {
            $table->unsignedInteger('radius_meter')->default(AttendanceLocation::DEFAULT_RADIUS_METERS)->change();
        });

        DB::table('attendance_settings')->update(['radius_meter' => AttendanceLocation::DEFAULT_RADIUS_METERS]);
    }

    public function down(): void
    {
        Schema::table('attendance_locations', function (Blueprint $table): void {
            $table->unsignedInteger('radius')->default(self::PREVIOUS_RADIUS_METERS)->change();
        });

        DB::table('attendance_locations')->update(['radius' => self::PREVIOUS_RADIUS_METERS]);

        Schema::table('attendance_settings', function (Blueprint $table): void {
            $table->unsignedInteger('radius_meter')->default(self::PREVIOUS_RADIUS_METERS)->change();
        });

        DB::table('attendance_settings')->update(['radius_meter' => self::PREVIOUS_RADIUS_METERS]);
    }
};
