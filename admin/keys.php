<?php 
ob_start();
require '../style/head.php';
require '../classes/PDO.php';
require '../classes/My_Class.php';

$My_Class->title("Все адреса");

if (!isset($_COOKIE['secretkey']) or $_COOKIE['secretkey'] != $secretkey) {
header("Location: /admin");		
exit;
}

if(isset($_GET['cmd'])){$cmd = htmlspecialchars($_GET['cmd']);}else{$cmd = '0';}

?>
<script type="text/javascript">  
 $(function() { 
    $(".btn").click(function(){
        $(this).button('loading').delay(3000).queue(function() {
            $(this).button('reset');
            $(this).dequeue();
        });        
    });
});  
</script>
<?

switch ($cmd){
case 'sale_null':

?>
<ol class="breadcrumb">
  <li><a href="/admin">Админ-панель</a></li>
  <li class="active">Списки адресов</li>
</ol>


<div class="list-group">
  <a href="keys.php" class="list-group-item">
    Проданные адреса
  </a>
  <a class="list-group-item active">Не проданные адреса</a>
</div>

<div class="table table-hover" > 
<table class="table table-bordered">
    <thead>
        <tr>
            <th  style="text-align:center;">№</th>
            <th  style="text-align:center;">Код</th>
            <th  style="text-align:center;">Город</th>
            <th  style="text-align:center;">Товар</th>
			<th  style="text-align:center;">Резерв</th>
        </tr>
    </thead>
<tbody>
<?

$total = DB::$the->query("SELECT * FROM `sel_keys` where `sale` = '0' ");
$total = $total->fetchAll();
$max = 10;
$pages = $My_Class->k_page(count($total),$max);
$page = $My_Class->page($pages);
$start=($max*$page)-$max;

$query = DB::$the->query("SELECT * FROM `sel_keys` where `sale` = '0' order by `id` DESC LIMIT $start, $max");
while($key = $query->fetch()) {
$cat = DB::$the->query("SELECT name FROM `sel_category` WHERE `id` = {$key['id_cat']} ");
$cat = $cat->fetch(PDO::FETCH_ASSOC);

$subcat = DB::$the->query("SELECT name FROM `sel_subcategory` WHERE `id` = {$key['id_subcat']} ");
$subcat = $subcat->fetch(PDO::FETCH_ASSOC);

$id_user = DB::$the->query("SELECT block_user FROM `sel_keys` WHERE `block` = '1' and `id` = {$key['id']} "); //DB::$the->query("SELECT chat FROM `sel_orders` WHERE `id_key` = {$key['id']} ");
$id_user = $id_user->fetch(PDO::FETCH_ASSOC);	
file_put_contents('../log.txt', "SELECT block_user FROM `sel_keys` WHERE `block` = '1' and `id` = {$key['id']} "); 	
?>
<tr>
            <td  align="center"><?=$key['id'];?></td>
            <td  align="center"><?=$key['code'];?></td>
            <td  align="center"><?=$cat['name'];?></td>
            <td  align="center"><?=$subcat['name'];?></td>
			<td  align="center"><? 
			if($id_user['block_user']) 
			echo '<a href="users.php?cmd=edit&chat='.$id_user['block_user'].'">Резерв</a>';
			else echo 'Нет резерва';
			?></td>
</tr>
<?	


}

?>
</tbody>
</table>
</div> 

<?
if ($pages>1) $My_Class->str('?cmd=sale_null&',$pages,$page); 

break;
	
default:

?>
<ol class="breadcrumb">
  <li><a href="/admin">Админ-панель</a></li>
  <li class="active">Списки адресов</li>
</ol>

<div class="list-group">
  <a  class="list-group-item active">
    Проданные адреса
  </a>
  <a href="?cmd=sale_null" class="list-group-item">Не проданные адреса</a>
</div>

<div class="table table-hover" > 
<table class="table table-bordered">
    <thead>
        <tr>
            <th  style="text-align:center;">№</th>
            <th  style="text-align:center;">Адрес</th>
            <th  style="text-align:center;">Город</th>
            <th  style="text-align:center;">Товар</th>
            <th  style="text-align:center;">Покупатель</th>
        </tr>
    </thead>
<tbody>
<?

$total = DB::$the->query("SELECT * FROM `sel_keys` where `sale` = '1' ");
$total = $total->fetchAll();
$max = 10;
$pages = $My_Class->k_page(count($total),$max);
$page = $My_Class->page($pages);
$start=($max*$page)-$max;

$query = DB::$the->query("SELECT * FROM `sel_keys` where `sale` = '1' order by `id` DESC LIMIT $start, $max");
while($key = $query->fetch()) {
$cat = DB::$the->query("SELECT name FROM `sel_category` WHERE `id` = {$key['id_cat']} ");
$cat = $cat->fetch(PDO::FETCH_ASSOC);

$subcat = DB::$the->query("SELECT name FROM `sel_subcategory` WHERE `id` = {$key['id_subcat']} ");
$subcat = $subcat->fetch(PDO::FETCH_ASSOC);

$id_user = DB::$the->query("SELECT chat FROM `sel_orders` WHERE `id_key` = {$key['id_key']} ");
$id_user = $id_user->fetch(PDO::FETCH_ASSOC);		
?>
<tr>
            <td  align="center"><?=$key['id'];?></td>
            <td  align="center"><?=$key['code'];?></td>
            <td  align="center"><?=$cat['name'];?></td>
            <td  align="center"><?=$subcat['name'];?></td>
            <td  align="center"><a href="users.php?cmd=edit&chat=<?=$id_user['chat'];?>">Покупатель</a></td>
</tr>
<?	


}

?>
</tbody>
</table>
</div> 

<?
if ($pages>1) $My_Class->str('?',$pages,$page); 

}

$My_Class->foot();
?>