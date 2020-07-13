<?php


require 'vendor/autoload.php';

header('Content-Type: application/json');
$order_id = $_GET['id'];
if (!$order_id) {
    echo sendAnswer('Укажите ID', 400);
}

$data_order = json_decode(file_get_contents('php://input'), true);
if (!$data_order && (!isset($data_order['result']))) {
    echo sendAnswer('Укажите информацию о заказе', 400);
}

$data_order = $data_order['result'];

$client = new \RetailCrm\ApiClient(
    'https://demo.retailcrm.ru',
    '3BTalw7P8rDwTGJ3Uq8NpCXc8NJ5EpOW',
    \RetailCrm\ApiClient::V5
);

try {
    $response = $client->request->customersList(['email' => $data_order['email']]);
} catch (\RetailCrm\Exception\CurlException $e) {
    sendAnswer($e->getMessage(), 500);
}

$customer_id = false;

if ($response->isSuccessful() && 200 === $response->getStatusCode()) {
    $answer = json_decode($response->getResponseBody(), true);
    if (isset($answer['customers']) && count($answer['customers']) > 0 && $answer['customers'][0]['id']) {
        $customer_id = $answer['customers'][0]['id'];
        $site = 'b12-skillum-ru';
    } else {
        $response = $client->request->customersCreate([
            "firstName" => $data_order['first_name'] ?: '',
            "patronymic" => $data_order['middle_name'] ?: '',
            "lastName" => $data_order['last_name'] ?: '',
            "email" => $data_order['email'],
        ]);

        if ($response->isSuccessful() && $response->getStatusCode() == 201) {
            $customer_id = $response->id;
        } else {
            sendAnswer('Не удалось создать пользователя.', 500);
        }
    }
} else {
    sendAnswer($response->getErrorMsg(), $response->getStatusCode());
}

if ($customer_id === false) {
    sendAnswer('Во время получения пользователя возникла ошибка.', 500);
}

$items = [];
if ($data_order['items']) {
    foreach ($data_order['items'] as $item) {
        $items[] = ['productName' => $item['title'], 'quantity' => $item['amount'], 'initialPrice' => $item['price']];
    }
} else {
    sendAnswer('Не указаны позиции заказа', 400);
}

$create_order = [
    'id' => $data_order['id'],
    'email' => $data_order['email'],
    'customer' => ['email' => $data_order['email'], 'id' => $customer_id],
    'items' => $items
];

try {
    $response = $client->request->ordersCreate($create_order);
} catch (\RetailCrm\Exception\CurlException $e) {
    sendAnswer($e->getMessage(), 500);
}

if ($response->isSuccessful() && 201 === $response->getStatusCode()) {
    $order = json_decode($response->getResponseBody(), true);
    sendAnswer('Success', 200, ['order' => ['id' => $order['id']], 'customer' => ['id' => $order['order']['customer']['id']]]);
} else {
    sendAnswer($response->getErrorMsg(), $response->getStatusCode());
}

/**
 * Ответ сервера
 * @param $message
 * @param int $status
 * @param bool $result
 */
function sendAnswer($message, $status = 200, $result = false)
{
    http_response_code($status);
    $answer = ['status' => $status, 'message' => $message];
    if ($result) $answer['result'] = $result;
    echo json_encode($answer, JSON_UNESCAPED_UNICODE);
    die();
}