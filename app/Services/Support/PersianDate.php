<?php

namespace App\Services\Support;

use Carbon\CarbonInterface;
use Hekmatinasser\Verta\Verta;

final class PersianDate
{
	public static function jalali(CarbonInterface $date): Verta
	{
		return Verta::instance($date);
	}

	public static function dateOnly(CarbonInterface $date): string
	{
		return self::jalali($date)->format('Y/m/d');
	}

	public static function dateTime(CarbonInterface $date): string
	{
		return $date->format('Y-m-d\TH:i:s');
	}

	public static function timeOnly(CarbonInterface $date): string
	{
		return $date->format('H:i');
	}

	public static function dateTimeObject(CarbonInterface $date): array
	{
		$v = self::jalali($date);

		return [
			'TodayDate'          => $v->format('Y/m/d'),
			'PersianWeekDayText' => $v->format('l'),
			'WeekDayText'        => $date->format('l'),
			'Full'               => $v->format('Y/m/d') . '-' . $v->format('l'),
			'PersianMonth'       => (int) $v->month,
			'PersianYear'        => (int) $v->year,
			'PersianDay'         => (int) $v->day,
			'Month'              => (int) $date->format('n'),
			'Year'               => (int) $date->format('Y'),
			'Day'                => (int) $date->format('j'),
		];
	}
}
