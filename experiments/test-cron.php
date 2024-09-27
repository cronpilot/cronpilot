<?php

require 'vendor/autoload.php';

use Carbon\Carbon;
use Poliander\Cron\CronExpression;

$expression = new CronExpression('0 0 13 * fri');
$when = now();

for ($i = 0; $i < 5; $i++) {
    $when = Carbon::createFromTimestamp($expression->getNext($when));
    echo $when->toDateTimeString() . PHP_EOL;
}
