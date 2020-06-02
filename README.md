# VK homework bot

PHP VKontakte bot that manages homework. It can listen to direct messages, as well as group chats. This bot only works with Russian language, therefore documentation is only available in Russian.

## Доступные команды

<p style="font-size:.8em;color:#aaa">Параметры обозначены угловыми скобками. В квадратных скобках указаны необязательные части команд. Вместо [*] может идти любое количество любых символов, кроме пробелов</p>

- `Добав[*] <предмет> <[дата]> <задание>`
  Устанавливает домашнее задание по данному предмету на данную дату.

  Дата и предмет могут идти в обратном порядке. Если дата опущена, будет выбран следующий ближайший день, в которые есть указанный предмет. Всё сообщение после даты и предмета будет считаться заданием. Допустимые форматы даты описаны ниже.

  Примеры:

  - Добавь по русскому ...
  - Добавь на завтра по геометрии ...
  - Добавить на 9 ноября по английскому у 2гр ...
  - Добавить по литре на 7.12 ...

- `Что* [задали] <[предмет]> <[дата]>`
  <p style="font-size:.8em;color:#aaa">*Команда определяется регулярным выражением <code>/^[чш]т?[оёе]/</code>, соответственно она узнает такие вариации как <em>што, шо, чё</em> и др.</p>
  Запрашивает домашнее задание по данному предмету на данную дату.

  Если указан только предмет, будет выбрана следующая ближайшая дата, когда есть урок по этому предмету.
  Если указана только дата, будет показано задание по всем предметам, уроки по которым есть в данный день.
  Если не указаны ни предмет, ни дата, будет показано задание по всем предметам на ближайший рабочий день (с учётом выходных и каникул).
  Если указанный предмет разделён на группы и не указана конкретная группа, будет показано задание для обеих групп.
  Если в указанную дату нет урока по данному предмету, бот найдёт следующую дату после данного дня и напишет задание на эту дату.

  Примеры:

  - Что задали?
  - Что по английскому (покажет задание для двух групп)
  - Что на завтра по литературе
  - Что задали по математике на 7 марта (покажет задание по алгебре и геометрии)
  - Что на 9.12?

- `Добав[*] выходной <дата>`
  Помечает, что данная дата является выходным днём

  Примеры:

  - Добавь выходной на 8 марта
  - Добавить выходной на 23.2

- `Удал[*] выходной <дата>`
  Помечает, что данная дата не является выходным днём

  Примеры:

  - Удали выходной с 7 марта
  - Удалить выходной со вторника

- `Когда каникулы` или `Сколько до каникул`
  Подсчитывает оставшееся количество дней до следующих каникул.

- `Измен[*] каникулы <каникулы> <начало> <[конец]>`
  Устанавливает новую дату начала и конца каникул.

  Если конечная дата не указана, вместо неё будет взята текущая дата окончания данных каникул

  Примеры:

  - Измени каникулы летние с 25 мая
  - Измени каникулы весенние со вторника до 1.04

## Формат даты

- Дата определяется по предлогу, стоящему перед ней: _на_, _с(о)_, _по_ или _до_. Этого набора достаточно для естественного звучания всех существующих команд. Далее в примерах используется предлог _на_, но на его месте может быть любой из трёх других
  - `на <число>.<месяц от 1 до 12>`, например `на 7.12`, `на 02.3`, `на 30.04`
  - `на <число> <месяц прописью>`, например `на 6 ноября`, `на 1 февраля`
  - `на <день недели>`, например `на вторник`, `на четверг`
  - `на <число>`, например `на 7`, `на 02`, `на 24`
  - `на завтра`, `на сегодня`, `на вчера`

## Настройка

Данные, необходимые для авторизации в базе данных и ВК хранятся в файле [`bot.json`](bot.json). Названия ключей описывают их назначение. Ключ доступа ВК нужно получить в настройках сообщества.

Расписание хранится в файле [`timeTable.json](config/timeTable.json). Каникулы - в файле [`vacations.json`](config/vacations.json). Изменять даты каникул можно с помощью команды. Новые каникулы бот добавлять не умеет, как и не умеет удалять существующие. Это нужно делать вручную. в этом файле.

### Новые предметы

Настройки предметов находятся в файле [Subject.php](util/Subject.php) в поле `Subject::DESC`.
Продемонстрирую, как добавлять предмет на примере астрономии и немецкого языка:

1. Добавить константу с названием предмета к остальным константам. Если предмет делится на группы, добавить две константы

```php
// Химия уже настроена
public const CHEMISTRY     = 'химия';
// Например, добавить предметы после химии
public const ASTRONOMY = 'астрономия';
public const GERMAN_F = 'немецкий 1гр';
public const GERMAN_S = 'немецкий 2гр';
```

2. В поле `Subject::REGEX` добавить в конце черту (`|`) и написать регулярное выражение, которое будет распознавать предмет в строке:

```php
"...|рус|физ|хим|астр|нем";
//                 ^^    ^
```

3. В поле `Subject::DESC` добавить описание предмета. Оно должно включать 3 поля: 'gen' - название предмета в родительном падеже, 'dat' - название предмета в дательном падеже, 'regex' - регулярное выражение для определения предмета. Если предмет делится на группы надо добавить два описания и для каждого установить поле `'divided' => true`

```php
// ...
self::CHEMISTRY => [
  'dat' => 'химии',
  'gen' => 'химии',
  'regex' => 'хим',
],
self::ASTRONOMY => [
  'dat' => 'астрономии',
  'gen' => 'астрономии',
  'regex' => 'астр',
],
self::GERMAN_F => [
  'dat' => 'немецкому у 1гр.',
  'gen' => 'немецкого у 1гр.',
  'regex' => 'нем',
  'divided' => true,
],
self::GERMAN_S => [
  'dat' => 'немецкому у 2гр.',
  'gen' => 'немецкого у 2гр.',
  'regex' => 'нем',
  'divided' => true,
],
```

Теперь предмет можно использовать в расписании (с названием, указанным в константе их 1 пункта).

## Установка

После загрузки бота на хостинг, в настройках собщества ВК (Настройки -> Работа с API -> Callback API) нужно указать версию API 5.101 и адрес к файлу `launchBot.php`, который будет принимать события от ВКонтакте и запускать бота. С этой страницы также нужно скопировать строку подтверждения и записать в поле `vk_confirm` в файле [`bot.json`](bot.json). Токен доступа также можно получить в настройках собщества (Настройки -> Работа с API -> Токены доступа). Токен должен иметь доступ к сообщениям сообщества.

На сервере рядом с файлом `launchBot.php` нужно создать папку `temp`.

## Замечания

- Бот работает только с базой данных MySQL. Однако строку для подключения можно изменить в файле [Utility.php](util/Utility.php) (в методе `Utility::dbConnect`).
- Бот сохраняет временные файлы (состояние между сообщениями) в файловой системе (Папке `temp`), а не в базе данных. Это не должно выхывать проблемы для маленьких сообществ.