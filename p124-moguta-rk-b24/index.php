<?php
/*
    Plugin Name: ROBOKASSA24
    Description: Оплата заказа в ROBOKASSA, оплаченный заказ передаётся в Битрикс24, второй чек для Честного Знака формируется Битрикс24 при отгрузке заказа
    Author: all2crm.ru
    Version: 0.5.3
    Edition: PAYMENT
*/
///////////////// util's
function curlyc($queryUrl, $params)
{
 // appendtolog($queryUrl, $params);
 $curl = curl_init();
 curl_setopt_array($curl, array( CURLOPT_SSL_VERIFYPEER => 0, CURLOPT_POST => 1, CURLOPT_HEADER => 0, CURLOPT_RETURNTRANSFER => 1, CURLOPT_URL => $queryUrl, CURLOPT_POSTFIELDS => $params ));
 $result = json_decode(curl_exec($curl), 1); curl_close($curl);
 // appendtolog('result ', $result);
 return $result;
}
function appendtolog($label, $arr = array() ){
	return; // no log
	file_put_contents(__DIR__.'/'.date("Ymd").'_log.txt', $label . (empty($arr) ? '' : ': ' . serialize($arr)) . PHP_EOL, FILE_APPEND);
}
////////////////// 

new PaymentRobokassaB24;

class PaymentRobokassaB24 {
    public static $pluginName = 'p124-moguta-rk-b24';

    public function __construct() {
        // Действие при активации плагина
        mgActivateThisPlugin(__FILE__, [__CLASS__, 'activate']);
        mgAddAction(self::$pluginName, [__CLASS__, 'getOrderForm'], 1);
        mgAddAction('Models_Payment_handleRequest', [__CLASS__, 'returnIntercept'], 1);
    }

    public static function activate() {
        $currentPayment = Models_Payment::getPaymentByPlugin(self::$pluginName);
        if (!$currentPayment) {
            $name = 'ROBOKASSA24';

            $icon = '/mg-plugins/'.self::$pluginName.'/src/icon.svg';
            $OpFieldsProduct = Models_OpFieldsProduct::getFields();
            $options_field_gtin = [];
            $options_field_gtin[] = [
                'value' => '',
                'title' => 'Не выбрано',
                ];
            foreach ($OpFieldsProduct as $product) {
            $options_field_gtin[] = [
                'value' => $product['id'],
                'title' => $product['name'],
                ];
            }

            $defaultParams = [
// RK
                [ 'name' => 'login','title' => 'Логин<a class="tool-tip-top fa fa-question-circle fl-right" title="Логин магазина из ЛК Робокасса"></a>','type' => 'text', 'value' => '', ],
                [ 'name' => 'pass1','title' => 'Пароль 1', 'type' => 'crypt','value' => '', ],
                [ 'name' => 'pass2','title' => 'Пароль 2','type' => 'crypt','value' => '',  ],
                [ 'name' => 'taxSystem', 'title' => 'Система налогообложения', 'type' => 'select', 'value' => 'osn', 'options' => [
                        ['value' => 'osn', 'title' => 'Общая СН - если Робочеки', ],
                        ['value' => 'usn_income','title' => 'Упрощенная СН (доходы)', ],
                        ['value' => 'usn_income_outcome', 'title' => 'Упрощенная СН (доходы минус расходы)', ],
                        ['value' => 'esn', 'title' => 'Единый сельскохозяйственный налог', ],
                        ['value' => 'patent', 'title' => 'Патентная СН',],  ], ],
                [ 'name' => 'PaymentMethod', 'title' => 'Вариант платежа', 'type' => 'select', 'value' => 'full_payment', 'options' => [
                        ['value' => 'full_payment', 'title' => 'Полный рассчёт(один чек на заказ)', ],
                        ['value' => 'full_prepayment','title' => '100% предоплата(первый чек из двух)', ], ], ],
                [ 'name' => 'GTIN_FIELD_ID', 'title' => 'GTIN в доп поле товара', 'type' => 'select', 'value' => '', 'options' => $options_field_gtin, ],
                [ 'name' => 'tax', 'title' => 'НДС, включенный в цену', 'type' => 'select', 'value' => 'Без НДС', 'options' => [
                        [ 'value' => 'none', 'title' => 'Без НДС', ],
                        [ 'value' => 'vat0', 'title' => 'НДС по ставке 0%', ],
                        [ 'value' => 'vat10', 'title' => 'НДС чека по ставке 10%', ],
                        [ 'value' => 'vat110', 'title' => 'НДС чека по расчетной ставке 10/110', ],
                        [ 'value' => 'vat20', 'title' => 'НДС чека по ставке 20%', ],
                        [ 'value' => 'vat120', 'title' => 'НДС чека по расчетной ставке 20/120', ],
                        [ 'value' => 'vat5', 'title' => 'НДС чека по ставке 5%', ],
                        [ 'value' => 'vat7', 'title' => 'НДС чека по ставке 7%', ],
                        [ 'value' => 'vat105', 'title' => 'НДС чека по расчетной ставке 5/105', ],
                        [ 'value' => 'vat107', 'title' => 'НДС чека по расчетной ставке 7/107', ],
                    ], ],
                [ 'title' => 'Метод шифрования', 'name' => 'cryptMethod', 'type' => 'select', 'value' => 'sha256', 'options' => [
                        [ 'value' => 'md5', 'title' => 'md5', ],
                        [ 'value' => 'sha256', 'title' => 'sha256', ],
                        [ 'value' => 'sha1', 'title' => 'sha1', ],
                    ], ],
                [ 'name' => 'testMode', 'title' => 'Тестовый режим Робокасса', 'type' => 'checkbox', 'value' => false, ],
// B24   
            [ 'name' => 'B24_wh', 'title' => 'Битрикс24 WebHook<a class="tool-tip-top fa fa-question-circle fl-right" title="Полный url вебхука, вида https://адрес портала/rest/N/n3g508kxw8z340ts/ с закрывающим слешем"></a>', 'type' => 'text', 'value' => '', ],
            [ 'name' => 'B24_UserId', 'title' => 'Битрикс24 Ответственный<a class="tool-tip-top fa fa-question-circle fl-right" title="ID пользователя Битрикс24, будет назначен Ответственным за Сделку, созданную из заказа"></a>', 'type' => 'text', 'value' => '', ],
            [ 'name' => 'B24_SOURCE_ID', 'title' => 'Битрикс24 ID Источник<a class="tool-tip-top fa fa-question-circle fl-right" title="ID Источника Битрикс24 для созданной Сделки"></a>', 'type' => 'text', 'value' => '', ],
            [ 'name' => 'B24_CATEGORY_ID', 'title' => 'Битрикс24 ID Категории Сделки<a class="tool-tip-top fa fa-question-circle fl-right" title="ID Направления Сделок, в котором будет создана Сделка из заказа сайта"></a>', 'type' => 'text', 'value' => '', ],
            [ 'name' => 'B24_DealField_SiteOrderId', 'title' => 'Битрикс24 ID поля Сделки для ID заказа сайта<a class="tool-tip-top fa fa-question-circle fl-right" title="Вида UF_CRM_NNNN, строка"></a>', 'type' => 'text', 'value' => '', ],
            [ 'name' => 'B24_DealField_RkOrderId', 'title' => 'Битрикс24 ID поля Сделки для ID заказа Робокассы<a class="tool-tip-top fa fa-question-circle fl-right" title="Вида UF_CRM_NNNN, строка"></a>', 'type' => 'text', 'value' => '', ],
            [ 'name' => 'B24_DealField_RkOpKey', 'title' => 'Битрикс24 ID поля Сделки для Робокасса OpKey<a class="tool-tip-top fa fa-question-circle fl-right" title="Вида UF_CRM_NNNN, строка"></a>', 'type' => 'text', 'value' => '', ],
            [ 'name' => 'B24_DealField_DeliveryInfo', 'title' => 'Битрикс24 ID поля Сделки для сведений о доставке<a class="tool-tip-top fa fa-question-circle fl-right" title="Вида UF_CRM_NNNN, строка, множественное"></a>', 'type' => 'text', 'value' => '', ],
            [ 'name' => 'B24_iblock_ID', 'title' => 'Битрикс24 ID Инфоблока Товарного каталога<a class="tool-tip-top fa fa-question-circle fl-right" title="ID Товарного каталога Битрикс24"></a>', 'type' => 'text', 'value' => '', ],
            [ 'name' => 'B24_Catalog_Section_ID', 'title' => 'Битрикс24 ID раздела Товарного каталога<a class="tool-tip-top fa fa-question-circle fl-right" title="Раздел Товарного каталога в котором будут созданы новые для Битрикс24 Товары"></a>', 'type' => 'text', 'value' => '', ],
            [ 'name' => 'B24_ProductField_GTIN', 'title' => 'Битрикс24 ID поля Товара для GTIN<a class="tool-tip-top fa fa-question-circle fl-right" title="Вида PropertyNN, строка"></a>', 'type' => 'text', 'value' => '', ],
            [ 'name' => 'B24_ContactField_ID', 'title' => 'Битрикс24 ID поля Контакта для id покупателя магазина<a class="tool-tip-top fa fa-question-circle fl-right" title="Вида PropertyNN, строка"></a>', 'type' => 'text', 'value' => '', ],
            
            ];

            $urls = [
                [ 'type' => 'info', 'title' => 'Result URL', 'link' => '/payment?payment=robokassa24&pay=result', ],
                [ 'type' => 'success', 'title' => 'Success URL', 'link' => '/payment?payment=robokassa24&pay=success', ],
                [ 'type' => 'fail', 'title' => 'Fail URL', 'link' => '/payment?payment=robokassa24&pay=fail', ],
            ];

            Models_Payment::addPayment(
                self::$pluginName,
                $name,
                $name,
                self::$pluginName,
                $defaultParams,
                $icon,
                1,
                $urls
            );
        }
    }

    ///////////////////////////////////////////////// Order Form
    public static function getOrderForm($args) {
        $orderId = $args['args'][1];
        $orderModel = new Models_Order();
        $orders = $orderModel->getOrder('`id` = '.DB::quoteInt($orderId));
        $order = $orders[$orderId];
        $orderContent = $orderModel->getCorrectOrderContent($order);
        $options = Models_Payment::getPaymentParams(self::$pluginName, true);
        $orderSumm = round($order['summ'] + $order['delivery_cost'], 2);
        $tax = $options['tax'];
        $taxSystem = $options['taxSystem'];
        $testMode = isset($options['testMode']) ? !!$options['testMode'] : false;
        $items = [];
        $orderSumm = 0.00;
        foreach ($orderContent as $item) {
            $var_id = $item['variant_id'];
            $opFieldsM = new Models_OpFieldsProduct($item['id']);
            $fieldId = $options['GTIN_FIELD_ID'];
            $gtin_field = $opFieldsM->get($fieldId,'variant');
            $gtin_value = null; if (isset($gtin_field[$item['variant_id']])) {  $gtin_value = $gtin_field[$item['variant_id']]['value']; }
            else{ $gtin_value = $opFieldsM->get($fieldId,'value'); }
            $orderSumm += $item['price'] * $item['count'];
            $items[] = [
                'name' => mb_substr(trim(htmlspecialchars($item['name'])), 0, 63, 'utf-8'),
                'sum' => $item['price'] * $item['count'],
                'quantity' => $item['count'],
                'payment_method' => $options['PaymentMethod'],
                'payment_object' => 'commodity',
                'tax' => $tax,
            ];
        }

        if ($order['delivery_cost'] > 0) {
            $orderSumm += $order['delivery_cost'];
            $items[] = [
                'name' => 'Доставка',
                'sum' => $order['delivery_cost'],
                'quantity' => 1,
                'tax' => $tax,
                'payment_method' => $options['PaymentMethod'],
                'payment_object' => 'service',
            ];
        }
        
        $receipt = json_encode(array(
            'sno' => $taxSystem,
            'items' => $items,  ));

        $receipt = urlencode($receipt);
        $orderId = $order['id'];
        $fakeUniqOrderId = $order['id']*10;
        $orderIdKey = 'Shp_orderid';
        $orderIdSignPart = $orderIdKey.'='.$orderId;
        $farsignreq[] = $options['login'];
        $farsignreq[] = $orderSumm;
        $farsignreq[] = $order['id'];
        $farsignreq[] = $receipt;
        $farsignreq[] = $options['pass1'];
        $farsignreq[] = 'Shp_orderid='.$order['id'];
        $farsignreq[] = $options['cryptMethod'];
        $farsign = curlyc('https://apps.all2crm.ru/mogutacmsplugin-rk-b24/mgt-rk-b24-plugin-sign.php', $farsignreq);
        $sign = $farsign['result'];
        
        ob_start();
        require('views'.DS.'form.php');
        $form = ob_get_contents();
        ob_end_clean();

        $logerData = [
            'orderId' => $orderId,
            'options' => $options,
            'order' => $order,
            'form' => $form, ];

        Models_Payment::loger(self::$pluginName, __CLASS__.'_'.__FUNCTION__, $logerData);
        self::secureSession();
        return $form;
    }

///////////////////////////////////////////////////////////////////////////// RK result
    public static function returnIntercept($args) {
        $result = $args['result'];

        if (($_REQUEST['shp_interface'] =='invoice') && ($_GET['pay'] === 'result')) { echo "OK" . (int)$_REQUEST['InvId']; exit;} // invoice for RK
        if (($_REQUEST['shp_interface'] =='invoice') && ($_GET['pay'] === 'success')) // invoice for user page
            { $result = [
                'status' => 'success',
                'message' => 'Спасибо за оплату счёта №' . (int)$_REQUEST['InvId'] . ' на ' . number_format((float)$_REQUEST['OutSum'], 2, ',', ' ') . ' Руб' , ];
                return $result; }

        if (
            $_GET['payment'] !== 'robokassa24' ||
            empty($_REQUEST['InvId']) ||
            empty($_REQUEST['Shp_orderid']) ||
            empty($_REQUEST['OutSum'])
        ) {
            Models_Payment::loger(self::$pluginName, __CLASS__.'_'.__FUNCTION__, ['error' => 'Missing required parameters', 'request' => $_REQUEST]);
            return $result;
        }
    
        Models_Payment::loger(self::$pluginName, __CLASS__.'_'.__FUNCTION__, $_REQUEST);
    
        $orderId = $_REQUEST['Shp_orderid'];
        $orderSumm = $_REQUEST['OutSum'];
        $invId = $_REQUEST['InvId'];
        $options = Models_Payment::getPaymentParams(self::$pluginName, true);
        $payment = Models_Payment::getPaymentByCode(self::$pluginName);
        $orderModel = new Models_Order();
        $orders = $orderModel->getOrder('`id` = ' . DB::quoteInt($orderId) . ' AND ROUND((`summ` + `delivery_cost`), 2) = ' . DB::quoteFloat($orderSumm));
        Models_Payment::loger(self::$pluginName, __CLASS__.'_'.__FUNCTION__, $orders);

        if (empty($orders)) {
            $result = [
                'status' => 'fail',
                'message' => 'Заказ с таким номером не найден в системе!',
            ];
            return $result;
        }
        $order = array_shift($orders);
    
        if (intval($order['payment_id']) !== intval($payment['id'])) {
            $result = [
                'status' => 'fail',
                'message' => 'В заказе выбран другой способ оплаты!',
            ];
            return $result;
        }
    
        if ($order['status_id'] == 2) {
            Models_Payment::loger(self::$pluginName, __CLASS__.'_'.__FUNCTION__, ['info' => 'Order already processed', 'order_id' => $orderId, 'inv_id' => $invId]);
            if ($_GET['pay'] === 'result') { echo "OK" . $invId; exit;
            }
            return [
                'status' => 'success',
                'message' => 'Заказ №' . $order['number'] . ' уже оплачен', ];
        }

        if ($_GET['pay'] === 'success') {  ///////////////// success
            $signtestreq = curlyc('https://apps.all2crm.ru/mogutacmsplugin-rk-b24/mgt-rk-b24-plugin-signtestreq.php', http_build_query(array(
     	    'request' => $_REQUEST, 'cr' => $options['cryptMethod'], 'cp' => $options['pass1'], )));
    
            if (strtoupper($_REQUEST['SignatureValue']) !== strtoupper($signtestreq['result'])) {
                Models_Payment::loger(self::$pluginName, __CLASS__.'_'.__FUNCTION__, ['signature_error' => 'Invalid signature']);
                Models_Payment::loger(self::$pluginName, __CLASS__.'_'.__FUNCTION__, $_REQUEST['SignatureValue']);
                Models_Payment::loger(self::$pluginName, __CLASS__.'_'.__FUNCTION__, $signtestreq['result'] );
                exit; }    
            $result = [ 'status' => 'success','message' => 'Вы успешно оплатили заказ №' . $order['number'], ];
        } elseif ($_GET['pay'] === 'result') {    ///////////// result & b24
            
            $signtestreq = curlyc('https://apps.all2crm.ru/mogutacmsplugin-rk-b24/mgt-rk-b24-plugin-signtestreq.php', http_build_query(array(
     	    'request' => $_REQUEST, 'cr' => $options['cryptMethod'], 'cp' => $options['pass2'], )));
            if (strtoupper($_REQUEST['SignatureValue']) !== strtoupper($signtestreq['result'])) {
                Models_Payment::loger(self::$pluginName, __CLASS__.'_'.__FUNCTION__, ['signature_error' => 'Invalid signature']);
                Models_Payment::loger(self::$pluginName, __CLASS__.'_'.__FUNCTION__, $_REQUEST['SignatureValue']);
                Models_Payment::loger(self::$pluginName, __CLASS__.'_'.__FUNCTION__, $signtestreq['result'] );
                } else {
                $allowed = ['185.59.216.65', '185.59.217.65']; 
                if (!in_array($_SERVER['REMOTE_ADDR'], $allowed, true)) { Models_Payment::loger(self::$pluginName, __CLASS__.'_'.__FUNCTION__, 'deny remote: '. $_SERVER['REMOTE_ADDR']); exit; }
                Controllers_Payment::actionWhenPayment([
                'paymentOrderId' => $order['id'],
                'paymentAmount' => $orderSumm,
                'paymentID' => $payment['id'],
                ]); 
            

            /////////////////////////////////////////////////////////////// bitrix24 sender
       
        //$orderId = $args['args'][1];
        $orderModel = new Models_Order();
        $orders = $orderModel->getOrder('`id` = '.DB::quoteInt($orderId));
        $order = $orders[$orderId];
        $orderContent = $orderModel->getCorrectOrderContent($order);
        $options = Models_Payment::getPaymentParams(self::$pluginName, true);
        // $orderSumm = round($order['summ'] + $order['delivery_cost'], 2);
        $tax = $options['tax'];
        $taxSystem = $options['taxSystem'];
        $items = [];
        foreach ($orderContent as $item) {
            $var_id = $item['variant_id'];
            $opFieldsM = new Models_OpFieldsProduct($item['id']);
            $fieldId = $options['GTIN_FIELD_ID'];
            $gtin_field = $opFieldsM->get($fieldId,'variant');
            $gtin_value = null; if (isset($gtin_field[$item['variant_id']])) {  $gtin_value = $gtin_field[$item['variant_id']]['value']; }
            else{ $gtin_value = $opFieldsM->get($fieldId,'value'); }
            $items[] = [
                'name' => mb_substr(trim(htmlspecialchars($item['name'])), 0, 63, 'utf-8'),
                'sum' => $item['price'] * $item['count'],
                'price' => $item['price'],
                'quantity' => $item['count'],
                'payment_method' => $options['PaymentMethod'],
                'payment_object' => 'commodity',
                'tax' => $tax,
                'code' => $item['code'],
                'weight' => $item['weight'],
                'currency_iso' => $item['currency_iso'],
                'unit' => $item['unit'],
            ];
            if (!is_null($gtin_value) && $gtin_value !== '') { $items[count($items) - 1]['GTIN'] = $gtin_value; }
        }
        if ($order['delivery_cost'] > 0) {
            $items[] = [
                'name' => 'Доставка',
                'sum' => $order['delivery_cost'],
                'quantity' => 1,
                'price' => $order['delivery_cost'],
                'tax' => $tax,
                'payment_method' => $options['PaymentMethod'],
                'payment_object' => 'service',
            ];
        }

        $usermail = User::getUserInfoByEmail($order['user_email']);
        $b24_send_result = curlyc('https://apps.all2crm.ru/mogutacmsplugin-rk-b24/mgt-rk-b24-plugin-sender.php',  http_build_query(array(
     	'options' => $options, 'order' => $order, 'items' => $items, 'request' => $_REQUEST, 'contact' => $usermail, 'site' => URL::getUrl(), )));
        }
            echo "OK" . $invId;
            exit;
        } else {
            $result = [
                'status' => 'fail',
                'message' => 'Оплата не удалась',
            ];
        }
    
        return $result;
    }

    // rememberme for restore user session when rk redirect user on site
    private static function secureSession() {
        if (MG::getSetting('rememberLogins') !== 'true') { return false; }
        $result = false;
        $remembermeCookieOptions = [
            'expires' => (time()+(intval(MG::getSetting('rememberLoginsDays'))*86400)),
            'path' => '/',
            'secure' => true,
            'httponly' => true,
        ];
        if (PHP_VERSION_ID < 70300) {
            $remembermeCookieOptions['path'] = '/; samesite=None';
        } else {
            $remembermeCookieOptions['samesite'] = 'None';
        }
        $result = setcookie('rememberme', $_COOKIE['rememberme'], $remembermeCookieOptions);
        return $result;
    }
}
