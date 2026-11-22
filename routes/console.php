<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// RNF13: encerramento automático dos leilões (rode `php artisan schedule:work`)
Schedule::command('leiloes:encerrar')->everyMinute();
