<?php

namespace HwBot\BotJson;

$config = json_decode(file_get_contents(__DIR__ . "/../bot.json"), true);

const DB_NAME = $config['db_name'];
const DB_USER = $config['db_user'];
const DB_PASSWORD = $config['db_password'];
const DB_HW_TABLE = $config['db_hw_table'];
const VK_CONFIRM = $config['vk_confirm'];
const VK_ACCESS_TOKEN = $config['vk_access_token'];
const VK_COMMUNITY_ID = $config['vk_community_id'];
