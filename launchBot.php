<?php

use Utility\Utility as Util;
use HwBot\Bot;

require_once 'util/Utility.php';

Util::set_timezone();

// $request = [
//     'type' => 'message_new',
//     'object' => [
//         'text' => 'удали выходной на 28 января',
//         'peer_id' => 111111111,
//         'from_id' => 111111111,
//         'id' => 1234
//     ]
// ];
$request = util::get_vk_request();
if (!isset($request['type'])) {
    exit('unavailable');
}

require_once 'modules/Bot.php';
require_once 'modules/Parser.php';

$bot = new Bot($request['object']);
$bot->handle_event($request);
echo 'ok';
