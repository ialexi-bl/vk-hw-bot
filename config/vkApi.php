<?php
namespace HwBot\VkApi;

const VK_API_VERSION = '5.92';
const VK_API_ENDPOINT = 'https://api.vk.com/method/';
const CALLBACK_API_CONFIRMATION_TOKEN = getenv("VK_CONFIRM_TOKEN");
const VK_API_ACCESS_TOKEN = getenv("VK_ACCESS_TOKEN");
const COMMUNITY_ID = getenv("VK_COMMUNITY_ID");

const EVENT_CONFIRMATION = 'confirmation';
const EVENT_MESSAGE_NEW = 'message_new';
