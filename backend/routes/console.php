<?php

use App\Console\Commands\DatabaseBackupCommand;
use App\Console\Commands\PruneSelfiesCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(PruneSelfiesCommand::class)
    ->dailyAt('02:10')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command(DatabaseBackupCommand::class)
    ->dailyAt('02:40')
    ->onOneServer()
    ->withoutOverlapping();
