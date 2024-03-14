<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php'; // Путь к файлу с PHPMailer

// https://site.ru/API-VK/market_order_new.php - callback
if (!isset($_REQUEST)) {
    return;
}

//Строка для подтверждения адреса сервера из настроек Callback API
$confirmation_token = '111111111';

//Ключ доступа сообщества
$token = '111111111111111111111111111111111111111111111111111111';

//Получаем и декодируем уведомление
$data = json_decode(file_get_contents('php://input'));

//Проверяем, что находится в поле "type"
switch ($data->type) {
//Если это уведомление для подтверждения адреса...
    case 'confirmation':
//...отправляем строку для подтверждения
        echo $confirmation_token;
        break;

// ---Событие покупки товара---
    case 'market_order_new':
        $new_order_comment = $data->object->comment;
        $new_order_total_price_text = $data->object->total_price->text;
        $new_order_items_count = $data->object->items_count;
        $new_order_status = $data->object->status;
        $new_order_delivery_address = $data->object->delivery->address;
        $new_order_delivery_type = $data->object->delivery->type;
        $new_order_recipient_name = $data->object->recipient->name;
        $new_order_recipient_phone = $data->object->recipient->phone;
        $new_order_id = $data->object->id;

//---С помощью market.getOrderItems получаем данные о заказе (json_decode преобразует в Объект)---
        $items_info = json_decode(file_get_contents("https://api.vk.com/method/market.getOrderItems?order_id={$new_order_id}&access_token={$token}&v=5.199"));


// ---Получаем значение свойства "title"---
//      $title = $items_info->response->items[0]->item->title; // Сохраняет заголовок одного товара
//      $title = $items_info['response']['items'][0]['item']['title']; // Применяется в случае, когда переменная $items_info представляет собой ассоциативный массив, а не объект.

// Пустой массив для хранения всех значений свойства "title"
        //$all_titles = [];

// Обходим массив товаров и извлекаем значение свойства "title"
        //foreach ($items_info->response->items as $item) {
        //    $all_titles[] = $item->item->title;
        //}
// Здесь будут собраны все заголовки товаров
//        $all_titles_string = '';

// Обходим массив всех значений
//        foreach ($all_titles as $title) {
//            // Добавляем текущее значение к строке с переносом строки
//            $all_titles_string .= $title . "\n";
//        }


// ---Считаем количество товаров с добавлением id товара---
        // Пустой массив для хранения количества каждого вида товара
        /*        $item_counts = [];

        // Обходим массив товаров и считаем количество каждого вида товара
                foreach ($items_info->response->items as $item) {
                    $quantity = $item->quantity;
                    $item_id = $item->item->id;

                    // Если товар уже присутствует в массиве, увеличиваем его счетчик
                    if (isset($item_counts[$item_id])) {
                        $item_counts[$item_id] += $quantity;
                    } else {
                        // Если товар встречается впервые, устанавливаем его счетчик в количество из заказа
                        $item_counts[$item_id] = $quantity;
                    }
                }

        // Выводим количество каждого вида товара
                $count_one_item = '';
                foreach ($item_counts as $item_id => $count) {
                    $count_one_item .= "Товар ID $item_id: $count шт.\n";
                }
        */

// ---Считаем количество товаров с добавлением title товара в строку---
        // Пустой массив для хранения количества каждого вида товара
        $item_counts = [];

// Обходим массив товаров и считаем количество каждого вида товара
        foreach ($items_info->response->items as $item) {
            $quantity = $item->quantity;
            $title = $item->item->title;

            // Если товар уже присутствует в массиве, увеличиваем его счетчик
            if (isset($item_counts[$title])) {
                $item_counts[$title] += $quantity;
            } else {
                // Если товар встречается впервые, устанавливаем его счетчик в количество из заказа
                $item_counts[$title] = $quantity;
            }
        }

// Выводим товары и количество каждого вида товара
// Цикл foreach проходит по каждому элементу массива $item_counts. На каждой итерации переменная $title принимает значение ключа (название товара), а переменная $count принимает значение этого ключа (количество товара).
        $all_items = '';
        foreach ($item_counts as $title => $count) {
            $all_items .= " - \"$title\": $count шт.\n";
        }


// ---Формирование тела сообщения---
        $text = "
Количество товаров в заказе: $new_order_items_count
Товары: \n$all_items
Адрес доставки: $new_order_delivery_address
Тип доставки: $new_order_delivery_type
Покупатель: $new_order_recipient_name
Телефон покупателя: $new_order_recipient_phone
Комментарий: $new_order_comment
Цена: $new_order_total_price_text
Статус: $new_order_status
        ";


// ---Запись сообщения в файл на сервере---
        try {
            $file_name_order = "log_from_vk_orders_list.log";
            $f = fopen($file_name_order, "a");
            flock($f, 2);
            fwrite($f, "Заказ№ $new_order_id: $text\n");
            fclose($f);

// Запись полученного объекта с помощью переменной в файл на сервере (выводит результат объект)
            $file_name_order = "log_from_vk_orders_var_list.log";
            $f = fopen($file_name_order, "a");
            flock($f, 2);
            fwrite($f, "Заказ: " . var_export($items_info, true) . "\n");
            fclose($f);

        } catch (Exception $e) {
            $file_name_error = "log_from_vk_errors.log";
            $f = fopen($file_name_error, "a");
            flock($f, 2);
            fwrite($f, "Ошибка: $e\n");
            fclose($f);
        }

// ---Отправка на почту---
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';

        try {
            $mail->setFrom('info@site.ru', 'From Name');
            $mail->addAddress('my@mail.ru', 'To Name');
            $mail->isHTML(false);
            $mail->Subject = 'Уведомление из ВКонтакте';
            $mail->Body = $text;
            $mail->send();

            if ($mail->send()) {

                // Пишется лог на сервере с созданием файла
                $file_name_mail_send_log = "from_vk_message_reply_phpmailer_send.log";
                $f = fopen($file_name_mail_send_log, "a");
                flock($f, 2);
                fwrite($f, "Успешно отправлено!\n");
                fclose($f);

            }
        } catch (Exception $e) {

            // Пишется лог на сервере с созданием файла
            $file_name_mail_send_log = "from_vk_message_reply_phpmailer_error.log";
            $f = fopen($file_name_mail_send_log, "a");
            flock($f, 2);
            fwrite($f, "Отправка не удалась!\n Ошибка: $mail->ErrorInfo");
            fclose($f);
        }

// ---Возвращаем "ok" серверу Callback API---
        echo('ok');
        break;
}
?>