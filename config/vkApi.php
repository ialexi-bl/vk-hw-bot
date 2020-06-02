<?php

namespace HwBot\VkApi;

require_once __DIR__ . "/botjson.php";

const VK_API_VERSION = '5.92';
const VK_API_ENDPOINT = 'https://api.vk.com/method/';
const CALLBACK_API_CONFIRMATION_TOKEN = \HwBot\BotJson\VK_CONFIRM;
const VK_API_ACCESS_TOKEN = \HwBot\BotJson\VK_ACCESS_TOKEN;
const COMMUNITY_ID = \HwBot\BotJson\VK_COMMUNITY_ID;

const EVENT_CONFIRMATION = 'confirmation';
const EVENT_MESSAGE_NEW = 'message_new';
