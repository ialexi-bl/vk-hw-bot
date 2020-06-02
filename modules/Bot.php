<?php

namespace HwBot;

use DateTime;
use const HwBot\DataBase\DB_HW_TABLE;
use const HwBot\VkApi\CALLBACK_API_CONFIRMATION_TOKEN;
use const HwBot\VkApi\EVENT_CONFIRMATION;
use const HwBot\VkApi\EVENT_MESSAGE_NEW;
use HwBot\VkApi\VkApi as Vk;
use HwBot\Parser\Parser;
use HwBot\VkApi\VkApi;
use InvalidArgumentException;
use Utility\BotWorkException;
use Utility\InvalidDateException;
use Utility\MissingDataException;
use Utility\NoDateException;
use Utility\NoHomeworkException;
use Utility\NoSubjectException;
use const Utility\SCHEDULE;
use Utility\SchoolDay;
use Utility\Subject;
use Utility\SubjectNotOnDateException;
use Utility\Utility;
use Utility\Weekends;

require_once __DIR__ . '/../config/vkApi.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../util/Exceptions.php';
require_once __DIR__ . '/../util/Weekends.php';
require_once __DIR__ . '/../util/Utility.php';
require_once __DIR__ . '/VkApi.php';
require_once __DIR__ . '/Parser.php';

class Bot
{

    private const POSITIVE_ANSWERS = ['да', 'ес', 'yes', 'oui', 'ja', 'ок', 'ok', 'tak', 'shi', '是'];
    private const NEGATIVE_ANSWERS = ['нет', 'no', 'ноу', 'non', 'nein', 'найн', 'nie', 'не'];

    protected $peer_id;
    protected $from_id;
    protected $messge_id;
    protected $is_conv;
    protected $is_admin;
    protected $debug_mode = false;

    protected $temp_data = [];

    private const RESPONSES = [
        '/(пр[ие]в|з?дра|хел+о|хей)/' => [
            'Привет', 'Здравствуй', 'Добрый день', 'Хелло',
        ],
        '/(т[иы]\s+)?[хк]то(?(1)|\s+т[иы])/' => [
            'Я Коннор, прислан из Киберлайф',
            'Я Маркус, но друзья зовут меня устраивать реловюцию',
        ],
    ];

    /**
     * @param mixed $object - Event object from VK API
     */
    public function __construct($object)
    {
        $this->peer_id = $object['peer_id'];
        $this->from_id = $object['from_id'];
        $this->messge_id = $object['id'];
        $this->is_conv = $this->peer_id !== $this->from_id;
        $this->is_admin = false;

        register_shutdown_function(function () {
            $last_error = error_get_last();
            if ($last_error !== null) {
                Utility::log_err($last_error['message']);

                if ($this->debug_mode) {
                    $this->send_message($last_error['message']);
                }
            }
        });
    }

    public function handle_event($event)
    {
        switch ($event['type']) {
            case EVENT_CONFIRMATION:
                exit(CALLBACK_API_CONFIRMATION_TOKEN);
            case EVENT_MESSAGE_NEW:
                $this->handle_new_message($event['object']);
        }
    }

    protected function handle_new_message($object)
    {
        // Check if there is temporary data about this user
        $temp_file_name = __DIR__ . "/../temp/message_{$this->peer_id}-{$this->from_id}.json";
        if (file_exists($temp_file_name)) {
            $temp_data = json_decode(file_get_contents($temp_file_name), true);

            // If data is older than 30min, then it has expires
            if (time() - $temp_data['time'] > 60 * 30) {
                $temp_data = [];
            }
        } else {
            $temp_data = [];
        }

        // Delete community ID
        $text = preg_replace('/^.*?\[club.+?\][\s,]*/uim', '', $object['text']);
        // Check debug mode
        if (preg_match('/^--debug/ui', $text)) {
            $text = preg_replace('/^--debug\s*/ui', '', $text);
            $this->debug_mode = true;
        }

        $text = trim($text);
        $msg = '';

        if ($this->debug_mode && isset($temp_data['func'])) {
            $msg .= "Temp data function: {$temp_data['func']}\n";
        }

        // Delete messages if scheduled
        if (isset($temp_data['delete_msg'])) {
            VkApi::delete_message($temp_data['delete_msg']);
            unset($temp_data['delete_msg']);
        }

        // Handle previous request
        if (
            isset($temp_data['func']) &&
            isset($temp_data['expected']) &&
            in_array(mb_strtolower($text), $temp_data['expected'])
        ) {
            $msg = call_user_func([$this, $temp_data['func']], $text, $temp_data);
        } else {
            if (preg_match('/^добав\p{L}*\s+выходн/ui', $text)) {
                $msg .= $this->add_weekend($text);
            } else if (preg_match('/^удал\p{L}*\s+выходн/ui', $text)) {
                $msg .= $this->delete_weekend($text);
            } else if (preg_match('/^добав/ui', $text)) {
                $msg .= $this->set_homework($text);
            } else if (preg_match('/^[чш]т?[оёе]\s*(по|на|зад)/ui', $text)) {
                $msg .= $this->get_homework($text);
            } else if (preg_match('/^удал/ui', $text)) {
                $msg .= $this->delete_homework($text);
            } else if (preg_match('/^(к[оа]гда|сколь\p{L}*\s+д\p{L}+)\s+к[уа]ник/ui', $text)) {
                $msg .= $this->get_vacations();
            } else if (preg_match('/^измен\p{L}*\s+к[ауо]ник/ui', $text)) {
                $msg .= $this->change_vacations($text);
            } else {
                $found = false;
                foreach (self::RESPONSES as $regex => $answers) {
                    if (preg_match($regex . 'ui', $text)) {
                        $msg .= $answers[mt_rand(0, count($answers) - 1)];
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $msg .= 'Не понел';
                }
            }
        }

        echo $msg;

        file_put_contents(
            $temp_file_name,
            empty($this->temp_data) ? '' : json_encode(array_merge($this->temp_data, [
                'time' => time(),
            ]), JSON_UNESCAPED_UNICODE)
        );
    }

    private function change_vacations(string $text, $last_request = []): string
    {
        $msg = '';
        if (!isset($last_request['status'])) {
            $period = Weekends::find_vacations_in($text);

            if ($period === null) {
                return $msg . "Я не нашёль каникулы";
            }
            if ($this->debug_mode) {
                $msg .= "Found: {$period['name']}\n";
            }

            try {
                $first_date = SchoolDay::from_string($text);
                $second_date = SchoolDay::from_string($text);
            } catch (InvalidDateException $e) {
                return $msg . ($this->debug_mode ? $e : $e->getMessage());
            }

            if ($first_date->is_null()) {
                return $msg . "Я не нашёль дату";
            }

            $msg .= "Изменить {$period['name']} каникулы?\n";
            $msg .= "Новый период: с $first_date по " .
                ($second_date->is_null() ? SchoolDay::from($period['end']) : $second_date);

            $this->temp_data = [
                'func' => __FUNCTION__,
                'status' => 'acceptation',
                'expected' => array_merge(self::POSITIVE_ANSWERS, self::NEGATIVE_ANSWERS),
                'name' => $period['name'],
                'start' => $first_date->format(),
                'end' => $second_date->is_null() ? $period['end'] : $second_date->format(),
            ];
        } else if ($last_request['status'] === 'acceptation') {
            Weekends::modify_vacations(
                $last_request['name'],
                $last_request['start'],
                $last_request['end']
            );
            $msg .= 'Каникулы изменены';
            $this->temp_data = $last_request;
        }

        return $msg;
    }

    private function add_weekend($text): string
    {
        $msg = '';
        try {
            $day = SchoolDay::from_string($text);
        } catch (InvalidDateException $e) {
            return $msg . ($this->debug_mode ? $e : $e->getMessage());
        }

        if ($this->debug_mode) {
            $msg .= "Date found: " . $day->format() . "\n";
        }

        if (Weekends::add_weekend($day->format()) === false) {
            $msg .= "$day уже отмечен как выходной";
        } else {
            $msg .= "Добавлен выходной на $day";
        }

        return $msg;
    }

    private function delete_weekend($text): string
    {
        $msg = '';
        try {
            $day = SchoolDay::from_string($text);
        } catch (InvalidDateException $e) {
            return $msg . ($this->debug_mode ? $e : $e->getMessage());
        }

        if ($this->debug_mode) {
            $msg .= "Date found: " . $day->format() . "\n";
        }

        if (Weekends::delete_weekend($day->format()) === false) {
            $msg .= "$day это не выходной";
        } else {
            $msg .= "Удалён выходной с $day";
        }

        return $msg;
    }

    private function get_vacations()
    {
        $msg = '';
        $temp = Weekends::get_vacation_for(date('d.m'));
        if ($temp !== null) {
            $msg .= "Сейчас уже каникулы :3\n";
        }

        $from_date = $temp === null ? new DateTime('-1 day') :
            DateTime::createFromFormat('d.m.Y', $temp['end']);
        $from_date->modify('+1 day');
        $next_vacations = Weekends::get_vacation_after($from_date);

        if ($next_vacations === null) {
            return 'Я не нашёль информацию про каникулы';
        }

        $msg .= "Следующие каникулы - {$next_vacations['name']} (" .
            SchoolDay::from($next_vacations['start']) . " - " .
            SchoolDay::from($next_vacations['end']) . ")\nДо них осталось";

        $interval = $from_date->diff(
            DateTime::createFromFormat('d.m', $next_vacations['start'])
        );
        $months = $interval->format('%m');
        $days = $interval->format('%d');
        $weeks = floor($days / 7);

        if ($months !== '0') {
            $msg .= " $months " .
                Parser::decline($months, 'месяц', ['', 'а', 'ев']) . " и";
        }
        $msg .= " $days " . Parser::decline($days, '', ['день', 'дня', 'дней']) . " (";

        if ($months !== '0') {
            $total = $interval->format('%a');
            $weeks = floor($total / 7);
            $msg .= "$total " . Parser::decline($total, '', ['день', 'дня', 'дней']) . ", ";
        }
        $msg .= ($weeks === ($total ?? $days) / 7 ? '' : '~') . "$weeks " .
            Parser::decline($weeks, 'недел', ['я', 'и', 'ь']) . ")";

        return $msg;
    }

    private function get_homework($text): string
    {
        if (mt_rand(0, 550) === 284) {
            $this->temp_data = ['delete_msg' => '' . $this->send_message('нет')];
            return '';
        }

        $msg = '';
        try {
            $bundle = Parser::parse($text);
            $subjects = $bundle->get_subjects();
            $date = $bundle->get_day();
            $date_implicit = $date->is_null();
            $for_day = empty($subjects);
            $has_lessons = $date->has_lessons();

            if ($this->debug_mode) {
                $msg .= $bundle;
            }

            if (!$has_lessons) {
                $date = SchoolDay::next_after($date);
                if (!$date_implicit) {
                    $msg .= "Это нерабочий день. ";
                }
            }
            $msg .= "Вот, что я нашёль";
            if ($date_implicit || $for_day || !$has_lessons) {
                $msg .= " на $date";
            }

            if ($for_day) {
                $subjects = SCHEDULE[$date->format('D')];
            }


            $msg .= "\n";

            $homework = [];
            $no_homework = [];

            $conn = Utility::dbConnect();
            $stmt = $conn->prepare(
                "SELECT homework FROM " . DB_HW_TABLE .
                    " WHERE subject = :subject AND date = :date"
            );

            foreach ($subjects as $subject) {
                if (!($subject instanceof Subject)) {
                    $subject = new Subject($subject);
                }

                $sp_date = $subject->get_next_day_from($date);
                $text = '';

                $stmt->execute([
                    ':subject' => (string) $subject,
                    ':date' => $sp_date->format()
                ]);
                $data = $stmt->fetch();

                if ($this->debug_mode) {
                    $msg .= "$subject, {$sp_date->format()}, db: " . print_r($data, true) . "\n";
                }

                $has_homework = !empty($data);
                if (!$date_implicit && !$date->has_subject($subject)) {
                    $text = "• $date нет {$subject->get_genitive()}. " .
                        "После этого будет $sp_date. Задание:";
                } else {
                    $text .= "• По " . $subject->get_dative() .
                        ($date_implicit && !$for_day ? " на $sp_date" : '');
                }

                if ($has_homework) {
                    $homework[] = $text . "\n― " . Utility::mb_ucfirst($data['homework']);
                } else {
                    $no_homework[] = $text;
                }
            }
            $conn = $stmt = null;

            $msg .= implode("\n", $homework);
            if (!empty($homework)) {
                $msg .= "\n";
            }
            if (!empty($no_homework)) {
                $msg .= implode("\n", $no_homework) . "\n― Нет в базе";
            }
        } catch (InvalidDateException $e) {
            $msg .= $this->debug_mode ? (string) $e : $e->getMessage();
        } catch (InvalidArgumentException $e) {
            $msg .= $this->debug_mode ? (string) $e : $e->getMessage();
        } catch (\Exception $e) {
            Utility::log_err($e);
            $msg .= $this->debug_mode ? (string) $e :
                "У мене не получилось достать информацию :(";
        }
        return $msg;
    }

    private function set_homework($text, $last_demand = []): string
    {
        $text = preg_replace('/^добав\p{L}*\s*/ui', '', $text);
        $msg = '';
        // First demand
        if (!isset($last_demand['status'])) try {
            $bundle = Parser::parse($text);
            $date = $bundle->get_day();
            $subject = $bundle->get_subject();
            $homework = Utility::mb_ucfirst($bundle->get_homework());

            $next_day = $subject->get_next_day_from($date);

            if ($date->is_null()) $date = $next_day;
            if ((string) $subject === Subject::NONE) throw new NoSubjectException("Я не нашёль предмет");
            if ($homework === '') throw new NoHomeworkException("Я не нашёль задание");

            $conn = Utility::dbConnect();
            $date_right = $date->has_subject($subject);

            if ($date_right) {
                $existing = $conn
                    ->query("SELECT homework FROM " . DB_HW_TABLE . " WHERE subject = '$subject' AND date = '{$date->format()}'")
                    ->fetch();
                $msg .= empty($existing) ? "Записать на $date по {$subject->get_dative()} следующее?\n$homework" :
                    "На $date уже записано задание по {$subject->get_dative()}:\n{$existing['homework']}\nЗаменить на следующее?\n$homework";
            } else if ($next_day === null)
                return Utility::mb_ucfirst($subject->get_genitive() . " нет в расписании");
            else {
                $existing = $conn
                    ->query("SELECT homework FROM " . DB_HW_TABLE . " WHERE subject = '$subject' AND date = '{$next_day->format()}'")
                    ->fetch();
                $msg .= "$date нет {$subject->get_genitive()}. После этого будет $next_day. ";
                $msg .= empty($existing) ? "Записать на этот день следующее?\n" :
                    "На этот день уже записано задание:\n{$existing['homework']}\nЗаменить на следующее?\n";
                $msg .= $homework;
            }
            $conn = null;
            if ($this->debug_mode)
                $msg .= "\n" . $bundle . "Next day: $next_day\nStatus: acceptation\n";

            $this->temp_data = [
                'func' => __FUNCTION__,
                'status' => 'acceptation',
                'date' => $date_right ? $date->format() : $next_day->format(),
                'homework' => $homework,
                'subject' => (string) $subject,
                'expected' => array_merge(self::POSITIVE_ANSWERS, self::NEGATIVE_ANSWERS),
                'replace' => !empty($existing),
            ];
        } catch (BotWorkException $e) {
            $msg .= $this->debug_mode ? (string) $e : $e->getMessage();
        } catch (\Exception $e) {
            Utility::log_err($e);
            $msg .= $this->debug_mode ? (string) $e : "У мене не получилось разобрать задание :(";
        }
        // If needed acceptation or refusal
        else if ($last_demand['status'] === 'acceptation') {
            if ($this->debug_mode) $msg = "Caught status acceptation\n";
            if (!in_array(mb_strtolower($text), self::POSITIVE_ANSWERS))
                return "Ок, отмена";

            $conn = Utility::dbConnect();
            $conn->query($last_demand['replace'] ?
                "UPDATE " . DB_HW_TABLE . " SET homework = {$last_demand['homework']} " .
                "WHERE subject = '{$last_demand['subject']}' AND date = '{$last_demand['date']}'" :

                "INSERT INTO " . DB_HW_TABLE . " (date, subject, homework) " .
                "VALUES ('{$last_demand['date']}', '{$last_demand['subject']}', '{$last_demand['homework']}')");
            if ($this->debug_mode)
                $msg .= "Date: {$last_demand['date']}\nSubject: {$last_demand['subject']}\nHomework: {$last_demand['homework']}\n" .
                    "Replacing: " . ($last_demand['replace'] ? 'true' : 'false') . "\nSuccess; Temp empt\n";

            $msg .= 'Добавлено в БД';
        }
        return $msg;
    }

    private function delete_homework($text, $last_demand = []): string
    {
        $msg = '';
        if (!isset($last_demand['status'])) try {
            $bundle = Parser::parse($text);
            $date = $bundle->get_day();
            $subject = $bundle->get_subject();

            if ($date->is_null())
                throw new NoDateException('Я не нашёль дату');
            if ((string) $subject === Subject::NONE)
                throw new NoSubjectException('Я не нашёль предмет');
            if (!$date->has_subject($subject))
                throw new SubjectNotOnDateException("$date нет {$subject->get_genitive()}");

            $conn = Utility::dbConnect();
            $data = $conn
                ->query("SELECT homework FROM " . DB_HW_TABLE . " WHERE subject = '$subject' AND date = '{$date->format()}'")
                ->fetch();
            $conn = null;
            if (empty($data))
                $msg .= "На $date не записано задание по {$subject->get_dative()}";
            else {
                $msg .= "На $date записано задание по {$subject->get_dative()}:\n{$data['homework']}\nУдалить?";
                $this->temp_data = [
                    'func' => __FUNCTION__,
                    'status' => 'acceptation',
                    'expected' => array_merge(self::POSITIVE_ANSWERS, self::NEGATIVE_ANSWERS),
                    'date' => $date->format(),
                    'subject' => (string) $subject,
                ];
            }
            if ($this->debug_mode)
                $msg .= "\n" . $bundle . "Query: " . print_r($data, true) . "\n";
        } catch (InvalidDateException $e) {
            $msg .= $this->debug_mode ? (string) $e : $e->getMessage();
        } catch (MissingDataException $e) {
            $msg .= $this->debug_mode ? (string) $e : $e->getMessage();
        } catch (SubjectNotOnDateException $e) {
            $msg .= $this->debug_mode ? (string) $e : $e->getMessage();
        } catch (\Exception $e) {
            Utility::log_err($e);
            $msg .= $this->debug_mode ? (string) $e : "У мене не получилось разобрать задание :(";
        } else if ($last_demand['status'] === 'acceptation') {
            if ($this->debug_mode)
                $msg .= "Caught status: acceptation\n";
            if (!in_array(mb_strtolower($text), self::POSITIVE_ANSWERS))
                return "Ок, отмена";

            $conn = Utility::dbConnect();
            $conn->query("DELETE FROM " . DB_HW_TABLE .
                " WHERE subject = '{$last_demand['subject']}' AND date = '{$last_demand['date']}'");
            if ($this->debug_mode)
                $msg .= "Success; temp empty\n";
            $msg .= 'Удалено';
        }
        return $msg;
    }

    protected function send_message($msg)
    {
        return Vk::send_message($this->peer_id, $msg, $this->is_conv ? $this->messge_id : '');
    }
}
