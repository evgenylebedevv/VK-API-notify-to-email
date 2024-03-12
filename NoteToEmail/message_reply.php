<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php'; // Путь к файлу с PHPMailer

// https://site.ru/VK-API/message_reply.php - callback
if (!isset($_REQUEST)) {
    return;
}

//Строка для подтверждения адреса сервера из настроек Callback API
$confirmation_token = '1111111111';

//Ключ доступа сообщества
$token = '111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111';

//Получаем и декодируем уведомление
$data = json_decode(file_get_contents('php://input'));

//Проверяем, что находится в поле "type"
switch ($data->type) {
//Если это уведомление для подтверждения адреса...
    case 'confirmation':
//...отправляем строку для подтверждения
        echo $confirmation_token;
        break;

//Событие - Сообщение уведомление из ВК
    case 'message_reply':
        $text_from_message = $data->object->text;

//Пишем полученное сообщение в файл на сервере
        try {
            $file_name_message_text = "from_vk_message_reply_text.log";
            $f = fopen($file_name_message_text, "a");
            flock($f, 2);
            fwrite($f, "$text_from_message\n");
            fclose($f);
        } catch (Exception $e) {}

//Отправка сообщения на почту
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';

        try {
            $mail->setFrom('info@site.ru', 'From Name');
            $mail->addAddress('my@mail.ru', 'To Name');
            $mail->isHTML(true);
            $mail->Subject = 'Уведомление из ВКонтакте';
            $mail->Body = mb_convert_encoding($text_from_message, 'UTF-8');
//            $mail->Body = mb_convert_encoding($mail->Body, 'UTF-8');
            $mail->send();

            if ($mail->send()) {
                $file_name_mail_send_log = "from_vk_message_reply_phpmailer_send.log";
                $f = fopen($file_name_mail_send_log, "a");
                flock($f, 2);
                fwrite($f, "Успешно отправлено!\n");
                fclose($f);
            }
        } catch (Exception $e) {
            $file_name_mail_send_log = "from_vk_message_reply_phpmailer_error.log";
            $f = fopen($file_name_mail_send_log, "a");
            flock($f, 2);
            fwrite($f, "Отправка не удалась!\n Ошибка: $mail->ErrorInfo");
            fclose($f);
        }

//Возвращаем "ok" серверу Callback API
        echo('ok');

        break;
}
?>