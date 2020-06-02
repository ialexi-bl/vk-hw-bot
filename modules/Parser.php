<?php

namespace HwBot\Parser;

use Utility\DataBundle;
use Utility\InvalidDayException;
use Utility\InvalidMonthException;
use Utility\SchoolDay;
use Utility\Subject;

require_once __DIR__ . '/../util/Subject.php';
require_once __DIR__ . '/../util/SchoolDay.php';
require_once __DIR__ . '/../util/DataBundle.php';
require_once __DIR__ . '/../util/Exceptions.php';

class Parser
{

    /**
     * Parses string into date, subject and homework (all the remaining symbols)
     * @param string $string 
     * @return DataBundle
     * @throws InvalidDayException
     * @throws InvalidMonthException
     * @throws \Exception
     */
    public static function parse(string $string): DataBundle
    {
        $dataBundle = new DataBundle();

        $subject = Subject::from_string($string);
        $day = SchoolDay::from_string($string);
        $string = preg_replace('/\s{2,}/ui', ' ', $string);

        $dataBundle
            ->set_subject($subject)
            ->set_day($day)
            ->set_homework($string);
        return $dataBundle;
    }

    /**
     * Returns given word, declined according to given numeral
     * @param $number - Numeral
     * @param string $word - Word
     * @param array $endings - Words endings:
     *      [nominative singular, genitive singular, genitive plural]
     * @return string
     */
    public static function decline($number, string $word, array $endings): string
    {
        $number = (string) (int) $number;
        $last = (int) substr($number, -1);
        $prelast = strlen($number) >= 2 ? substr($number, -2, 1) : '';
        if ($prelast !== '1') {
            if ($last === 1)
                return $word . $endings[0];
            if ($last >= 2 && $last <= 4)
                return $word . $endings[1];
        }
        return $word . $endings[2];
    }
}
