<?php

use Illuminate\Support\Facades\Schedule;

// Relance quotidienne des valideurs et bénéficiaires en retard (jours ouvrés, 8 h heure de Conakry).
Schedule::command('requests:send-reminders')->weekdays()->at('08:00')->withoutOverlapping();
