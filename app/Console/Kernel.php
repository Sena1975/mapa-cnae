<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Registre comandos Artisan customizados.
     */
    protected $commands = [
        \App\Console\Commands\MapaWarm::class,
        \App\Console\Commands\GeocodeCount::class, 
        \App\Console\Commands\GeocodeStats::class,
        \App\Console\Commands\GeocodeCityStats::class,
        \App\Console\Commands\PlacesStats::class,        
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('mapa:warm --cidade="Salvador"           --limit=5000 --retry=2 --sleep=150')->dailyAt('01:00');
        $schedule->command('mapa:warm --cidade="Lauro de Freit"     --limit=5000 --retry=2 --sleep=150')->dailyAt('02:00');
        $schedule->command('mapa:warm --cidade="Camacari"           --limit=5000 --retry=2 --sleep=150')->dailyAt('03:00');
        $schedule->command('mapa:warm --cidade="Porto Seguro"       --limit=5000 --retry=2 --sleep=150')->dailyAt('04:00');
        $schedule->command('mapa:warm --cidade="Feira de Santa"     --limit=5000 --retry=2 --sleep=150')->dailyAt('05:00');
        $schedule->command('mapa:warm --cidade="Aracaju"            --limit=5000 --retry=2 --sleep=150')->dailyAt('06:00');
        $schedule->command('mapa:warm --cidade="Vitoria da Conq"    --limit=5000 --retry=2 --sleep=150')->dailyAt('07:00');
        $schedule->command('mapa:warm --cidade="Simoes Filh"       --limit=5000 --retry=2 --sleep=150')->dailyAt('08:00');
        $schedule->command('mapa:warm --cidade="Itabuna"            --limit=5000 --retry=2 --sleep=150')->dailyAt('09:00');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        // require base_path('routes/console.php'); // se você usar
    }

    
}
