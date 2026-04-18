<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('appstorecat:apps:sync-discovery --ios')->cron('*/20 * * * *');
Schedule::command('appstorecat:apps:sync-discovery --android')->cron('*/20 * * * *');
Schedule::command('appstorecat:apps:sync-tracked --ios')->cron('*/20 * * * *');
Schedule::command('appstorecat:apps:sync-tracked --android')->cron('*/20 * * * *');

Schedule::command('appstorecat:charts:sync-daily --ios')->dailyAt('00:30');
Schedule::command('appstorecat:charts:sync-daily --android')->dailyAt('00:30');
