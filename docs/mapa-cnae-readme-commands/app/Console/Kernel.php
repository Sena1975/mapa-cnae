<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\MapaWarm::class,
        \App\Console\Commands\GeocodeCount::class,
        \App\Console\Commands\GeocodeStats::class,
        \App\Console\Commands\GeocodeCityStats::class,
        \App\Console\Commands\PlacesStats::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('mapa:warm --cidade="Lauro de Freitas" --limit=4000 --retry=2 --sleep=150')->dailyAt('02:00');
        // $schedule->command('mapa:warm --cidade="Salvador"        --limit=8000 --retry=2 --sleep=150')->dailyAt('03:00');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
