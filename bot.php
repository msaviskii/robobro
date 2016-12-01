<?
require 'classes/Curl.php';
require 'classes/PDO.php';
require 'classes/lib.php';

$curl = new Curl();
$json = file_get_contents('php://input'); // Получаем запрос от пользователя
//file_put_contents('log.txt', date("H:i:s Ymd").' -> '.$json."\n", FILE_APPEND ); 
$action = json_decode($json, true); // Расшифровываем JSON

// Получаем информацию из БД о настройках бота
$set_bot = DB::$the->query("SELECT * FROM `sel_set_bot` ");
$set_bot = $set_bot->fetch(PDO::FETCH_ASSOC);

if(isset($action['edited_message']['text'])) $message	= trim($action['edited_message']['text']); // текст сообщения от пользователя
else $message	= trim($action['message']['text']); // текст сообщения от пользователя

$chat		= trim($action['message']['chat']['id']); // ID чата
$username	= trim($action['message']['from']['username']); // username пользователя
$first_name	= trim($action['message']['from']['first_name']); // имя пользователя
$last_name	= trim($action['message']['from']['last_name']); // фамилия пользователя
$token		= trim($set_bot['token']); // токен бота

if(strpos($message, "'\nG") !== false) $message = str_replace("'\nG",'',$message);
if(strpos($message, "\\") !== false) $message = str_replace("\\",'',$message);
if(strpos($message, "\\\\") !== false) $message = str_replace("\\\\",'',$message);
if(strpos($message, "'\\") !== false) $message = str_replace("'\\",'',$message);

if(!is_numeric($message)) {
	if($message != 'заказы' && $message != 'Заказы'){
		if($message != 'оплата' && $message != 'Оплата'){
			if($message != 'помощь' && $message != 'Помощь'){
				if(strpos($message, "/start") === false) exit;
			}
		}	
	}
}

// Если бот отключен, прерываем все!
if($set_bot['on_off'] == "off") exit;

// Проверяем наличие пользователя в БД
$vsego = DB::$the->query("SELECT chat FROM `sel_users` WHERE `chat` = {$chat} ");
$vsego = $vsego->fetchAll();

// Если отсутствует, записываем его
if(count($vsego) == 0){

// Записываем в БД
$params = array('username' => $username, 'first_name' => $first_name, 'last_name' => $last_name, 
'chat' => $chat, 'time' => time() );  
 
$q = DB::$the->prepare("INSERT INTO `sel_users` (username, first_name, last_name, chat, time) 
VALUES (:username, :first_name, :last_name, :chat, :time)");  
$q->execute($params);	
}

// Получаем всю информацию о пользователе
$user = DB::$the->query("SELECT ban,cat FROM `sel_users` WHERE `chat` = {$chat} ");
$user = $user->fetch(PDO::FETCH_ASSOC);

// Если юзер забанен, отключаем для него все!
if($user['ban'] == "1") exit;

// Если сделан запрос оплата 
if ($message == "оплата" or $message == "Оплата") {
// Получаем всю информацию о настройках киви
$set_qiwi = DB::$the->query("SELECT * FROM `sel_set_qiwi` ");
$set_qiwi = $set_qiwi->fetch(PDO::FETCH_ASSOC);

// Получаем всю информацию о пользователе
$user = DB::$the->query("SELECT * FROM `sel_users` WHERE `chat` = {$chat} ");
$user = $user->fetch(PDO::FETCH_ASSOC);

if($user['id_key'] == '0') {

$curl->get('https://api.telegram.org/bot'.$token.'/sendMessage',array(
	'chat_id' => $chat,
	'text' => "🚫 Вы не выбрали адрес!",
	
	)); 	
exit;	
}


// Получаем информацию о ключе 
$key = DB::$the->query("SELECT * FROM `sel_keys` WHERE `id_key` = '".$user['id_key']."' ");
$key = $key->fetch(PDO::FETCH_ASSOC);

// Получаем информацию о цене ключа 
$amount = DB::$the->query("SELECT amount FROM `sel_subcategory` WHERE `id` = '".$key['id_subcat']."' ");
$amount = $amount->fetch(PDO::FETCH_ASSOC);

// Смотрим когда пользователь сделал последний запрос
$timeout = $user['verification']+$set_bot['verification'];
$timeout2 = $user['verification']+5;

if($timeout < time()) { // Если давно, то проверяем оплату
DB::$the->prepare("UPDATE sel_users SET verification=? WHERE chat=? ")->execute(array(time(), $chat)); 

require 'classes/qiwi.class.php';


// Получаем всю информацию о настройках киви
$us_qiwi = DB::$the->query("SELECT password FROM `sel_set_qiwi` WHERE `number` = '".$user['pay_number']."' ");
$us_qiwi = $us_qiwi->fetch(PDO::FETCH_ASSOC);


$iAccount = $user['pay_number'];
$sPassword = $us_qiwi['password'];

if($set_bot['proxy_login'] && $set_bot['proxy_pass']) $proxy = $set_bot['proxy'].":http:".$set_bot['proxy_login'].":".$set_bot['proxy_pass'];
else $proxy = '';
	
$oQiwi = new QIWI( $iAccount, $sPassword, 'cookie.txt', $proxy ); // Заходим в киви
//file_put_contents('log.txt', 'DaTA:'.$json.'|proxy:'.serialize($oQiwi)."\n");
$json = $oQiwi->GetHistory( date( 'd.m.Y', strtotime( '-1 day' ) ), date( 'd.m.Y', strtotime( '+1 day' ) ) );

// Проверяем наличие комментария в пополнении счета		
$iTotal = 0; foreach($json as $aItem ) { $iTotal++; 

  
if($aItem['sComment'] == $user['id_key'] and $aItem['dAmount'] == $amount['amount'] and $aItem['sType'] == 'INCOME' and $aItem['sStatus'] == 'SUCCESS') 
{
	
$good = $user['id_key']; 

// Записываем всю информацию о платеже в БД
$params = array('chat' => $chat, 'iAccount' => $iAccount, 'iID' => $aItem['iID'], 'sDate' => $aItem['sDate'], 'sTime' => $aItem['sTime'],
'dAmount' => $aItem['dAmount'], 'iOpponentPhone' => $aItem['iOpponentPhone'], 
'sComment' => $aItem['sComment'], 'sStatus' => $aItem['sStatus'], 'time' => time() );  
 
$q = DB::$the->prepare("INSERT INTO `sel_qiwi` (chat, iAccount, iID, sDate, sTime, dAmount, iOpponentPhone, sComment, sStatus, time) 
VALUES (:chat, :iAccount, :iID, :sDate, :sTime, :dAmount, :iOpponentPhone, :sComment, :sStatus, :time)");  
$q->execute($params); 


// Записываем информацию о покупке в БД
$params = array('id_key' => $user['id_key'], 'code' => $key['code'], 'chat' => $chat, 'id_subcat' => $key['id_subcat'], 'time' => time() );   
$q = DB::$the->prepare("INSERT INTO `sel_orders` (id_key, code, chat, id_subcat, time) 
VALUES (:id_key, :code, :chat, :id_subcat, :time)");  
$q->execute($params);


DB::$the->prepare("UPDATE sel_keys SET sale=? WHERE id_key=? ")->execute(array("1", $user['id_key']));


DB::$the->prepare("UPDATE sel_keys SET block=? WHERE block_user=? ")->execute(array("0", $chat)); 
DB::$the->prepare("UPDATE sel_keys SET block_time=? WHERE block_user=? ")->execute(array('0', $chat));
DB::$the->prepare("UPDATE sel_keys SET block_user=? WHERE block_user=? ")->execute(array('0', $chat));

DB::$the->prepare("UPDATE sel_users SET id_key=? WHERE chat=? ")->execute(array('0', $chat));
DB::$the->prepare("UPDATE sel_users SET pay_number=? WHERE chat=? ")->execute(array('', $chat));
DB::$the->prepare("UPDATE sel_users SET verification=? WHERE chat=? ")->execute(array('', $chat));
DB::$the->prepare("UPDATE sel_users SET cat=? WHERE chat=? ")->execute(array('0', $chat));

// Отправляем текст пользователю
$curl->get('https://api.telegram.org/bot'.$token.'/sendMessage',array(
	'chat_id' => $chat,
	'text' => "✔ Вы успешно приобрели адрес! Пожалуйста, сохраните его!",
	)); 


// Отправляем текст пользователю
$curl->get('https://api.telegram.org/bot'.$token.'/sendMessage',array(
	'chat_id' => $chat,
	'text' => $key['code'],
	)); 

// Отправляем текст пользователю
$curl->get('https://api.telegram.org/bot'.$token.'/sendMessage',array(
	'chat_id' => $chat,
	'text' => "Чтобы смотреть свои заказы, отправьте боту сообщение: заказы",
	));
	
	
	
if($oQiwi->aBalances['RUB'] > $set_bot['limits']) 
{	

$r = rand(1, 3);

$n = "nomer$r";

$iID = $oQiwi->SendMoney( $set_bot[$n], $set_bot['limits'], 'RUB', 'perevod' );
	
if( $iID === false ) {

$user1 = DB::$the->query("SELECT chat FROM `sel_users` WHERE `id` = '1' ");
$user1 = $user1->fetch(PDO::FETCH_ASSOC);

$curl->get('https://api.telegram.org/bot'.$token.'/sendMessage',array(
	'chat_id' => $user1['chat'],
	'text' => 'При переводе '.$set_bot['limits'].' руб. с номера '.$iAccount.' на номер '.$set_bot[$n].' - включилось смс подтверждение.
Не удалось провести платеж!',
	));
}

DB::$the->prepare("UPDATE sel_set_qiwi SET active=? WHERE active=? ")->execute(array('0', '1')); 


$new_act = DB::$the->query("SELECT id FROM `sel_set_qiwi` order by rand()");
$new_act = $new_act->fetch(PDO::FETCH_ASSOC);

DB::$the->prepare("UPDATE sel_set_qiwi SET active=? WHERE id=? ")->execute(array('1', $new_act['id'])); 

}
	
exit;
}
}

// Если комментарий не найдем в истории платежа
if($good != $user['id_key']) {
	
$text = '❌ Оплата не произведена! 
Отсутствует перевод '.$amount['amount'].' руб с комментарием '.$user['id_key'].'';

// Отправляем текст сверху пользователю
$curl->get('https://api.telegram.org/bot'.$token.'/sendMessage',array(
	'chat_id' => $chat,
	'text' => $text,
	)); 
}
exit;		
}
else // Вызываем ошибку антифлуда
{
if($timeout2 < time()) {	
$sec = $timeout-time();	
$text = '❌ Подождите!
Следующую проверку можно сделать только через '.$sec.' сек.';
	

$curl->get('https://api.telegram.org/bot'.$token.'/sendMessage',array(
	'chat_id' => $chat,
	'text' => $text,
	)); 
}
}

//$chat = escapeshellarg($chat);	
//exec('bash -c "exec nohup setsid wget -q -O - '.$set_bot['url'].'/verification.php?chat='.$chat.' > /dev/null 2>&1 &"');
exit;
}

if($user['cat'] == 0){	
// Проверяем наличие категории
$mesto_cat = DB::$the->query("SELECT mesto FROM `sel_category` WHERE `mesto` = '".$message."'");
$mesto_cat = $mesto_cat->fetchAll();

if (count($mesto_cat) != 0) 
{
// Проверяем по месту id
$mesto_id = DB::$the->query("SELECT id FROM `sel_category` WHERE `mesto` = '".$message."' LIMIT 1");
$mesto_id = $mesto_id->fetchAll();	

$name_cat = DB::$the->query("SELECT name FROM `sel_category` WHERE `id` = '".$mesto_id[0]['id']."' ");
$name_cat = $name_cat->fetch(PDO::FETCH_ASSOC);

// Проверяем наличие ключей
$total = DB::$the->query("SELECT id FROM `sel_keys` where `id_cat` = '".$mesto_id[0]['id']."' and `sale` = '0' and `block` = '0'");
$total = $total->fetchAll();
//file_put_contents('log.txt',serialize($mesto_id)."SELECT id FROM `sel_keys` where `id_cat` = '".$mesto_id[0]['id']."' and `sale` = '0' and `block` = '0'");

if(count($total) == 0) // Если пусто, вызываем ошибку
{ 
DB::$the->prepare("UPDATE sel_users SET cat=? WHERE chat=? ")->execute(array("0", $chat)); 	
// Отправляем текст
$curl->get('https://api.telegram.org/bot'.$token.'/sendMessage',array(
	'chat_id' => $chat,
	'text' => '⛔ Данный товар закончился!',
	));
exit;	
}

DB::$the->prepare("UPDATE sel_users SET cat=? WHERE chat=? ")->execute(array($mesto_id[0]['id'], $chat));

$text .= "Какой товар в городе 🏠".$name_cat['name']." Вы хотите купить?\n➖➖➖➖➖➖➖➖➖➖\n";

$query = DB::$the->query("SELECT id,name,mesto FROM `sel_subcategory` WHERE `id_cat` = '".$mesto_id[0]['id']."' order by `mesto` ");


while($cat = $query->fetch()) {


// Получаем информацию о цене ключа 
$amount = DB::$the->query("SELECT amount FROM `sel_subcategory` WHERE `id` = '".$cat['id']."' ");
$amount = $amount->fetch(PDO::FETCH_ASSOC);


	// Считаем количество ключей в подкатегории	
$total2 = DB::$the->query("SELECT id_subcat FROM `sel_keys` WHERE `id_subcat` = {$cat['id']} and `sale` = '0' and `block` = '1'");
$total2 = $total2->fetchAll();

$total = DB::$the->query("SELECT id FROM `sel_keys` where `id_subcat` = {$cat['id']} and `sale` = '0' and `block` = '0'");
$total = $total->fetchAll();

if (count($total2) > 0) $free = ' | '.count($total2).'🕑'; else $free = '';
//$text .= "🔹 ".$cat['name']." (отправьте ".$cat['mesto'].")\n\n"; // ЭТО ВЫВОД ПОДКАТЕГОРИЙ
$text .= "🎁 ".$cat['name']."\n💰 ".$amount['amount']." руб (".count($total)." шт".$free.")\n➖➖➖➖➖➖➖➖➖➖\n";
}

$text .= "\n".$set_bot['footer'];

// Отправляем все это пользователю
$curl->get('https://api.telegram.org/bot'.$token.'/sendMessage',array(
	'chat_id' => $chat,
	'text' => $text,
	)); 	
//$chat = escapeshellarg($chat);	
//$message = escapeshellarg($message);	
//file_put_contents('log.txt','chat:'.$chat.'|message:'.$message.'|DDATA1111:'.sizeof($mesto_cat)."\r\n");

//exec('bash -c "exec nohup setsid php ./select_cat.php '.$chat.' '.$message.' > /dev/null 2>&1 &"');
exit;
}
}

// Проверяем наличие товара
$mesto = DB::$the->query("SELECT mesto FROM `sel_subcategory` WHERE `mesto` = '".$message."' and `id_cat` = '".$user['cat']."' ");
//$mesto = DB::$the->query("SELECT mesto FROM `sel_subcategory` WHERE `mesto` = '".$message."'");
$mesto = $mesto->fetchAll();

if (count($mesto) != 0) 
{
$user = DB::$the->query("SELECT ban,id_key,cat FROM `sel_users` WHERE `chat` = {$chat} ");
$user = $user->fetch(PDO::FETCH_ASSOC);

$nulled = DB::$the->query("SELECT id FROM `sel_keys` where `sale` = '0' and `block` = '1' and `block_time` < '".(time()-(60*$set_bot['block']))."' ");
$nulled = $nulled->fetchAll();

if(count($nulled > 0)){


$query = DB::$the->query("SELECT block_user FROM `sel_keys` where `sale` = '0' and `block` = '1' and `block_time` < '".(time()-(60*$set_bot['block']))."' order by `id` ");
while($us = $query->fetch()) {
	
DB::$the->prepare("UPDATE sel_keys SET block=? WHERE block_user=? ")->execute(array("0", $us['block_user'])); 
DB::$the->prepare("UPDATE sel_keys SET block_time=? WHERE block_user=? ")->execute(array('0', $us['block_user'])); 
DB::$the->prepare("UPDATE sel_keys SET block_user=? WHERE block_user=? ")->execute(array('0', $us['block_user']));  

DB::$the->prepare("UPDATE sel_users SET id_key=? WHERE chat=? ")->execute(array('0', $us['block_user'])); 
DB::$the->prepare("UPDATE sel_users SET pay_number=? WHERE chat=? ")->execute(array('0', $us['block_user'])); 

$curl->get('https://api.telegram.org/bot'.$token.'/sendMessage',array(
	'chat_id' => $us['block_user'],
	'text' => "🚫 Вы не произвели оплату в течение {$set_bot['block']} минут. Этот адрес выставлен на продажу. 
Для того чтобы купить адрес, выберите его заново",
	
	)); 
	exit;
}
}
	


// Берем информацию о разделе
$row = DB::$the->query("SELECT * FROM `sel_subcategory` WHERE `mesto` = '".$message."' and `id_cat` = '".$user['cat']."' ");
$subcat = $row->fetch(PDO::FETCH_ASSOC);

// Берем информацию о категории
$row = DB::$the->query("SELECT name FROM `sel_category` WHERE `id` = '".$subcat['id_cat']."' ");
$cat = $row->fetch(PDO::FETCH_ASSOC);

// Проверяем наличие ключей
$total = DB::$the->query("SELECT id FROM `sel_keys` where `id_subcat` = '".$subcat['id']."' and `sale` = '0' and `block` = '0' ");
$total = $total->fetchAll();

if(count($total) == 0) // Если пусто, вызываем ошибку
{

// Отправляем текст
$curl->get('https://api.telegram.org/bot'.$token.'/sendMessage',array(
	'chat_id' => $chat,
	'text' => '⛔ Данный товар закончился!',
	));
}
else // Иначе выводим результат
{

$clear = DB::$the->query("SELECT block_user FROM `sel_keys` where `block_user` = '".$chat."' ");
$clear = $clear->fetchAll();

if(count($clear) != 0){
DB::$the->prepare("UPDATE sel_keys SET block=? WHERE block_user=? ")->execute(array("0", $chat)); 
DB::$the->prepare("UPDATE sel_keys SET block_time=? WHERE block_user=? ")->execute(array('0', $chat));
DB::$the->prepare("UPDATE sel_keys SET block_user=? WHERE block_user=? ")->execute(array('0', $chat));  
}

// Получаем информацию о ключе 
$key = DB::$the->query("SELECT id,code,id_subcat FROM `sel_keys` where `id_subcat` = '".$subcat['id']."' and `sale` = '0' and `block` = '0' order by rand() limit 1");
$key = $key->fetch(PDO::FETCH_ASSOC);


DB::$the->prepare("UPDATE sel_keys SET block=? WHERE id=? ")->execute(array("1", $key['id'])); 
DB::$the->prepare("UPDATE sel_keys SET block_user=? WHERE id=? ")->execute(array($chat, $key['id'])); 
DB::$the->prepare("UPDATE sel_keys SET block_time=? WHERE id=? ")->execute(array(time(), $key['id'])); 

// генерируем уникальный ключа
$iid_key = generate_id();//uniqid();
DB::$the->prepare("UPDATE sel_users SET id_key=? WHERE chat=? ")->execute(array($iid_key/*$key['id']*/, $chat)); 
DB::$the->prepare("UPDATE sel_keys SET id_key=? WHERE id=? ")->execute(array($iid_key, $key['id'])); 
	
$set_qiwi = DB::$the->query("SELECT number FROM `sel_set_qiwi` WHERE `active` = '1' ");
$set_qiwi = $set_qiwi->fetch(PDO::FETCH_ASSOC);	
	
DB::$the->prepare("UPDATE sel_users SET pay_number=? WHERE chat=? ")->execute(array($set_qiwi['number'], $chat)); 
	
$text = "🏠 {$cat['name']}
🎁 {$subcat['name']}

Переведите 💰 {$subcat['amount']} руб на Qiwi +{$set_qiwi['number']}

С комментарием: ".$iid_key/*$key['id']*/."

После того как вы переведете эту сумму с этим комментарием, отправьте боту сообщение: оплата
Либо нажмите 👉/start для того, чтобы вернуться к выбору города.

Для отмены заказа: 0
";


// Отправляем текст
$curl->get('https://api.telegram.org/bot'.$token.'/sendMessage',array(
	'chat_id' => $chat,
	'text' => $text,
	)); 


}
/*
$chat = escapeshellarg($chat);	
$message = escapeshellarg($message);	
exec('bash -c "exec nohup setsid php ./select.php '.$chat.' '.$message.' > /dev/null 2>&1 &"');
*/
exit;
} else if( $message != 0 && !is_numeric($message)) {
	// Отправляем все это пользователю
$curl->get('https://api.telegram.org/bot'.$token.'/sendMessage',array(
	'chat_id' => $chat,
	'text' => '⛔ В этом городе адресов пока нет!',
	));
	exit;
}


// Если проверяют список покупок
if ($message == "заказы" or $message == "Заказы") {	
// Получаем информацию о всех покупках
$orders = DB::$the->query("SELECT * FROM `sel_orders` where `chat` = {$chat} ");
$orders = $orders->fetchAll();

// Если их нет
if(count($orders) == 0)
{
$text = "⛔ У вас нет заказов!\n\n";
}
else // Иначе
{	
$text = "📦 Ваши заказы:\n\n";
// Показываем список ключей
$query = DB::$the->query("SELECT id_key,id_subcat FROM `sel_orders` where `chat` = {$chat} ");
while($order = $query->fetch()) {
// Получаем информацию о подкатегории	
$subcat = DB::$the->query("SELECT name,amount FROM `sel_subcategory` where `id` = {$order[id_subcat]} ");
$subcat = $subcat->fetch(PDO::FETCH_ASSOC);
// Получаем информацию о ключах
$key = DB::$the->query("SELECT code FROM `sel_keys` where `id_key` = {$order[id_key]} ");
$key = $key->fetch(PDO::FETCH_ASSOC);

$text .= " 📬 {$subcat[name]}: {$key[code]}\n\n";	

}
}	

// Отправляем все это пользователю
$curl->get('https://api.telegram.org/bot'.$token.'/sendMessage',array(
	'chat_id' => $chat,
	'text' => $text,
	));
//$chat = escapeshellarg($chat);	
//exec('bash -c "exec nohup setsid php ./orders.php '.$chat.' > /dev/null 2>&1 &"');
exit;
}

// Команда помощь
if ($message == "помощь" or $message == "Помощь") {	


$text = "СПИСОК КОМАНД

[Цифры] - используются для выбора товара

Оплата - для проверки оплаты

Заказы - список всех ваших заказов

0 и 00 - отмена заказа

Помощь - вызов списка команд
";

// Отправляем все это пользователю
$curl->get('https://api.telegram.org/bot'.$token.'/sendMessage',array(
	'chat_id' => $chat,
	'text' => $text,
	)); 
exit;
}

if ($message == "0" or $message == "00") {	

DB::$the->prepare("UPDATE sel_users SET cat=? WHERE chat=? ")->execute(array("0", $chat)); 	

DB::$the->prepare("UPDATE sel_keys SET block=? WHERE block_user=? ")->execute(array("0", $chat)); 
DB::$the->prepare("UPDATE sel_keys SET block_time=? WHERE block_user=? ")->execute(array('0', $chat)); 
DB::$the->prepare("UPDATE sel_keys SET block_user=? WHERE block_user=? ")->execute(array('0', $chat));  

DB::$the->prepare("UPDATE sel_users SET id_key=? WHERE chat=? ")->execute(array('0', $chat)); 
DB::$the->prepare("UPDATE sel_users SET pay_number=? WHERE chat=? ")->execute(array('pay_number', $chat)); 

$curl->get('https://api.telegram.org/bot'.$token.'/sendMessage',array(
	'chat_id' => $chat,
	'text' => "🚫 Заказ отменен!",
	)); 
	
exit;
}

$text = $set_bot['hello']."\n➖➖➖➖➖➖➖➖➖➖\n";


$query = DB::$the->query("SELECT id,name,mesto FROM `sel_category` order by `mesto` ");
while($cat = $query->fetch()) {
	
$text .= "🏠 ".$cat['name']." (введите ".$cat['mesto'].")\n➖➖➖➖➖➖➖➖➖➖\n"; // ЭТО НАЗВАНИЕ КАТЕГОРИЙ

}

$text .= "\n".$set_bot['footer'];

// Отправляем все это пользователю
$curl->get('https://api.telegram.org/bot'.$token.'/sendMessage',array(
	'chat_id' => $chat,
	'text' => $text,
	)); 

?>