<?php

use App\Actions\RunAllReadyTasks;
use Illuminate\Support\Facades\Schedule;

Schedule::call(new RunAllReadyTasks())->everyMinute();
