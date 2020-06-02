<?php
namespace Utility;

use Exception;

abstract class BotWorkException extends Exception {};

abstract class InvalidDateException extends BotWorkException {};
class InvalidMonthException extends InvalidDateException {};
class InvalidDayException extends InvalidDateException {};

abstract class MissingDataException extends BotWorkException {};
class NoSubjectException extends MissingDataException {};
class NoDateException extends MissingDataException {};
class NoHomeworkException extends MissingDataException {};

class InvalidArgumentException extends Exception {};
class SubjectNotOnDateException extends BotWorkException {};
