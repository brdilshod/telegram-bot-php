<?php

include('TelegramBotAPI.php');
$tl = new TelegramBotAPI('194881124:-mdM');
$chat_id = $tl->getChatId();
$message = $tl->getMessage();
if($message == 'qalesan') {
	$tl->sendMessage($chat_id, 'Yaxshiman o\'zin qalesan?');
}
//$tl->sendMessage($chat_id, 'test');
//$tl->sendChatAction($chat_id, 'upload_photo');
//$tl->sendPhoto($chat_id, 'i.jpg');