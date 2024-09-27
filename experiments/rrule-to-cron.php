<?php

require 'vendor/autoload.php';

class RRuleConverter
{
    private $FREQ = '';
    private $DTSTART = '';
    private $INTERVAL = -1;
    private $BYMONTHDAY = -1;
    private $BYMONTH = -1;
    private $BYDAY = '';
    private $BYSETPOS = 0;
    private $BYHOUR = 0;
    private $BYMINUTE = 0;
    private $tzid;

    private const C_DAYS_OF_WEEK_RRULE = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];
    private const C_DAYS_WEEKDAYS_RRULE = ['MO', 'TU', 'WE', 'TH', 'FR'];
    private const C_DAYS_OF_WEEK_CRONE = ['2', '3', '4', '5', '6', '7', '1'];
    private const C_DAYS_OF_WEEK_CRONE_NAMED = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];
    private const C_MONTHS = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

    public function __construct($r)
    {
        $this->parseRRule($r);
    }

    private function untilStringToDate($until)
    {
        $re = '/^(\d{4})(\d{2})(\d{2})(T(\d{2})(\d{2})(\d{2})Z?)?$/';
        preg_match($re, $until, $bits);

        if (!$bits) {
            throw new Exception("Invalid UNTIL value: $until");
        }

        return new DateTime(
            sprintf('%s-%s-%s %s:%s:%s',
                $bits[1], $bits[2], $bits[3],
                $bits[5] ?? 0, $bits[6] ?? 0, $bits[7] ?? 0
            ),
            new DateTimeZone('UTC')
        );
    }

    private function parseRRule($r)
    {
        if (strpos($r, 'DTSTART') !== false) {
            $r = preg_replace('/\n.*RRULE:/', ';', $r);
        } else {
            $r = str_replace('RRULE:', '', $r);
        }

        if (strpos($r, 'TZID') !== false) {
            preg_match('/TZID=(.*?);/', $r, $matches);
            $this->tzid = $matches[1];
            $r = preg_replace('/TZID=(.*?);/', '', $r);
        }

        $rarr = explode(';', $r);

        foreach ($rarr as $rule) {
            [$param, $value] = explode('=', $rule);
            $this->assignParam($param, $value);
        }

        if (!empty($this->DTSTART)) {
            $this->DTSTART = $this->untilStringToDate($this->DTSTART);
        }
    }

    private function assignParam($param, $value)
    {
        switch ($param) {
            case 'FREQ':
                $this->FREQ = $value;
                break;
            case 'DTSTART':
                $this->DTSTART = $value;
                break;
            case 'INTERVAL':
                $this->INTERVAL = (int)$value;
                break;
            case 'BYMONTHDAY':
                $this->BYMONTHDAY = (int)$value;
                break;
            case 'BYDAY':
                $this->BYDAY = $value;
                break;
            case 'BYSETPOS':
                $this->BYSETPOS = (int)$value;
                break;
            case 'BYMONTH':
                $this->BYMONTH = (int)$value;
                break;
            case 'BYHOUR':
                $this->BYHOUR = (int)$value;
                break;
            case 'BYMINUTE':
                $this->BYMINUTE = (int)$value;
                break;
        }
    }

    public function convertToCron()
    {
        $dayTime = '0 0 0';
        $dayOfMonth = '?';
        $month = '*';
        $dayOfWeek = '?';

        if ($this->FREQ === 'MONTHLY') {
            $month = $this->INTERVAL === 1 ? '*' : '1/' . $this->INTERVAL;
            $dayOfMonth = $this->BYMONTHDAY !== -1 ? (string)$this->BYMONTHDAY : $this->DTSTART->format('j');
        }

        return "$dayTime $dayOfMonth $month $dayOfWeek";
    }
}

// Example usage
$rrule = "RRULE:FREQ=MONTHLY;INTERVAL=1;BYMONTHDAY=15";
$converter = new RRuleConverter($rrule);
echo $converter->convertToCron();
