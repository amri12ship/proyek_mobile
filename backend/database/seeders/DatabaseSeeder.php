<?php

namespace Database\Seeders;

use App\Models\AttendanceLocation;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSchedule;
use App\Models\AttendanceSetting;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $settings = AttendanceSetting::current();
        $settings->fill([
            'work_start' => '08:00:00',
            'work_end' => '17:00:00',
            'tolerance_minutes' => 15,
            'radius_meter' => AttendanceLocation::DEFAULT_RADIUS_METERS,
            'max_accuracy_meter' => 50,
            'ticket_ttl_seconds' => 120,
            'max_ticket_per_day' => 10,
            'require_selfie' => true,
            'timezone' => 'Asia/Jakarta',
        ])->save();

        $departments = collect([
            ['name' => 'Operasional', 'description' => 'Unit lapangan dan operasional harian'],
            ['name' => 'Keuangan', 'description' => 'Keuangan dan akuntansi'],
            ['name' => 'IT', 'description' => 'Teknologi informasi'],
        ])->mapWithKeys(function (array $row) {
            $department = Department::create($row);

            return [$row['name'] => $department];
        });

        $positions = collect([
            ['name' => 'Karyawan', 'description' => 'Staf'],
            ['name' => 'Supervisor', 'description' => 'Pengawas lapangan'],
            ['name' => 'Manajer', 'description' => 'Kepala unit'],
        ])->mapWithKeys(function (array $row) {
            $position = Position::create($row);

            return [$row['name'] => $position];
        });

        $headquarters = AttendanceLocation::create([
            'name' => 'Kantor Pusat',
            'address' => 'Jl. Merdeka Utara No. 1, Jakarta Pusat',
            'latitude' => -6.1751111,
            'longitude' => 106.8271667,
            'radius' => 200,
            'status' => AttendanceLocation::STATUS_ACTIVE,
        ]);

        $warehouse = AttendanceLocation::create([
            'name' => 'Gudang Cikarang',
            'address' => 'Kawasan Industri Cikarang, Jawa Barat',
            'latitude' => -6.2694000,
            'longitude' => 107.1290000,
            'radius' => 300,
            'status' => AttendanceLocation::STATUS_ACTIVE,
        ]);

        $this->seedSchedules();
        $this->seedHolidays();

        $admin = User::create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@absensi.test',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $budi = $this->seedEmployee(
            user: ['name' => 'Budi Santoso', 'username' => 'budi', 'email' => 'budi@absensi.test'],
            employee: [
                'nik' => 'EMP001',
                'name' => 'Budi Santoso',
                'department_id' => $departments['Operasional']->id,
                'position_id' => $positions['Karyawan']->id,
                'phone' => '0812-1111-2222',
                'gender' => 'L',
                'hire_date' => '2023-01-16',
            ],
            locations: [$headquarters->id, $warehouse->id],
            primaryLocation: $headquarters->id,
        );

        $siti = $this->seedEmployee(
            user: ['name' => 'Siti Aminah', 'username' => 'siti', 'email' => 'siti@absensi.test'],
            employee: [
                'nik' => 'EMP002',
                'name' => 'Siti Aminah',
                'department_id' => $departments['Keuangan']->id,
                'position_id' => $positions['Karyawan']->id,
                'phone' => '0813-3333-4444',
                'gender' => 'P',
                'hire_date' => '2023-06-01',
            ],
            locations: [$headquarters->id],
            primaryLocation: $headquarters->id,
        );

        $this->seedAttendanceHistory($budi, $headquarters);
        $this->seedAttendanceHistory($siti, $headquarters);

        $this->command?->info('Seed selesai.');
        $this->command?->info('Admin   : admin@absensi.test / password123');
        $this->command?->info('Karyawan: budi@absensi.test / password123');
        $this->command?->info('Karyawan: siti@absensi.test / password123');
        $this->command?->info('Admin user id: '.$admin->id);
    }

    private function seedSchedules(): void
    {
        foreach (range(1, 5) as $day) {
            AttendanceSchedule::updateOrCreate(
                ['employee_id' => null, 'day_of_week' => $day],
                ['is_workday' => true, 'start_time' => '08:00:00', 'end_time' => '17:00:00'],
            );
        }

        foreach ([6, 7] as $day) {
            AttendanceSchedule::updateOrCreate(
                ['employee_id' => null, 'day_of_week' => $day],
                ['is_workday' => false, 'start_time' => null, 'end_time' => null],
            );
        }
    }

    private function seedHolidays(): void
    {
        $holidays = [
            ['date' => Carbon::today()->addMonths(2)->startOfMonth()->toDateString(), 'name' => 'Hari Raya Natal', 'description' => 'Libur nasional'],
            ['date' => Carbon::today()->addMonths(4)->startOfMonth()->toDateString(), 'name' => 'Tahun Baru Masehi', 'description' => 'Libur nasional'],
        ];

        foreach ($holidays as $holiday) {
            Holiday::firstOrCreate(['date' => $holiday['date']], $holiday);
        }
    }

    /**
     * @param  array<string, mixed>  $user
     * @param  array<string, mixed>  $employee
     * @param  array<int, int>  $locations
     */
    private function seedEmployee(array $user, array $employee, array $locations, int $primaryLocation): Employee
    {
        $created = User::create([
            'name' => $user['name'],
            'username' => $user['username'],
            'email' => $user['email'],
            'password' => Hash::make('password123'),
            'role' => User::ROLE_EMPLOYEE,
            'is_active' => true,
            'phone' => $employee['phone'] ?? null,
        ]);

        $model = Employee::create([
            'user_id' => $created->id,
            ...$employee,
            'status' => Employee::STATUS_ACTIVE,
        ]);

        $model->locations()->sync(array_map(
            static fn (int $id) => ['location_id' => $id, 'is_primary' => $id === $primaryLocation],
            $locations,
        ));

        return $model;
    }

    private function seedAttendanceHistory(Employee $employee, AttendanceLocation $location): void
    {
        for ($daysAgo = 1; $daysAgo <= 7; $daysAgo++) {
            $date = Carbon::today()->subDays($daysAgo);

            if ((int) $date->format('N') > 5) {
                continue;
            }

            $late = $daysAgo % 4 === 0;
            $checkIn = $date->copy()->setTime($late ? 8 : 7, $late ? 45 : 50);
            $checkOut = $date->copy()->setTime(17, 10);

            AttendanceRecord::updateOrCreate(
                ['employee_id' => $employee->id, 'attendance_date' => $date->toDateString()],
                [
                    'location_id' => $location->id,
                    'check_in_at' => $checkIn,
                    'check_out_at' => $checkOut,
                    'check_in_latitude' => $location->latitude,
                    'check_in_longitude' => $location->longitude,
                    'check_in_distance' => 12.5,
                    'check_in_accuracy' => 8,
                    'check_out_latitude' => $location->latitude,
                    'check_out_longitude' => $location->longitude,
                    'check_out_distance' => 18.2,
                    'check_out_accuracy' => 7,
                    'work_minutes' => $checkIn->diffInMinutes($checkOut),
                    'status' => $late ? AttendanceRecord::STATUS_TERLAMBAT : AttendanceRecord::STATUS_HADIR,
                ],
            );
        }
    }
}
