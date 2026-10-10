<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('media:cleanup')->hourly();
