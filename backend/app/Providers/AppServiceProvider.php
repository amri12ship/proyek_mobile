<?php

namespace App\Providers;

use App\Models\AttendanceSetting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->applyApplicationTimezone();

        RateLimiter::for('api-login', function (Request $request): Limit {
            return Limit::perMinute(5)->by(
                Str::lower((string) $request->input('email')).'|'.$request->ip(),
            );
        });

        RateLimiter::for('web-login', function (Request $request): Limit {
            return Limit::perMinute(10)->by(Str::lower((string) $request->input('email')).'|'.$request->ip());
        });
    }

    /**
     * The attendance_settings row is the single source of truth for the
     * application timezone, so stored timestamps, work hour thresholds and the
     * offsets handed to the mobile app can never disagree. When no valid row is
     * readable the APP_TIMEZONE value stays in charge.
     */
    private function applyApplicationTimezone(): void
    {
        $timezone = (string) config('app.timezone');

        try {
            $stored = trim((string) AttendanceSetting::query()->value('timezone'));

            if ($this->isValidTimezone($stored)) {
                $timezone = $stored;
            }
        } catch (\Throwable $e) {
            // Ignore if table/settings not available (e.g. fresh install).
        }

        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);
    }

    /**
     * Mirrors the "timezone" validation rule used by the settings form so a
     * stale or hand edited row can never break date handling.
     */
    private function isValidTimezone(string $timezone): bool
    {
        return $timezone !== '' && in_array($timezone, timezone_identifiers_list(), true);
    }
}
