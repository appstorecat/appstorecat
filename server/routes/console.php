<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('appstorecat:apps:sync-discovery --ios')->daily();
Schedule::command('appstorecat:apps:sync-discovery --android')->daily();
Schedule::command('appstorecat:apps:sync-tracked --ios')->daily();
Schedule::command('appstorecat:apps:sync-tracked --android')->daily();

Schedule::command('appstorecat:charts:sync-daily --ios')->dailyAt('00:30');
Schedule::command('appstorecat:charts:sync-daily --android')->dailyAt('00:30');
