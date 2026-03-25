<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('appstorecat:apps:sync-discovery --ios')->everyMinute();
Schedule::command('appstorecat:apps:sync-discovery --android')->everyMinute();
Schedule::command('appstorecat:apps:sync-tracked --ios')->everyMinute();
Schedule::command('appstorecat:apps:sync-tracked --android')->everyMinute();

Schedule::command('appstorecat:charts:sync-daily --ios')->everyMinute();
Schedule::command('appstorecat:charts:sync-daily --android')->everyMinute();
