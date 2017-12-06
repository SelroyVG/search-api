<?php
header("Content-Type: application/json;charset=utf-8");
$mysqli_sphinx = mysqli_connect("127.0.0.1", "", "", "", 9306);
if(!$mysqli_sphinx)
	die('{"Error": "sphinx is not running"}');

$itemsArray = json_decode (stripslashes(file_get_contents("php://input")), true);
if(!$itemsArray)
	die('{"Error": "Can\'t found data"}');

/*
if(isset($_POST['data'])){
	$items = $_POST['data'];
}else{
	die('{"Error": "Can\'t found \'data\' argument"}');
}
*/


$itemsArray = json_decode($items, true);

$skippedItems = 0;
$addedItems = 0;

foreach($itemsArray as $item){
	
	if(!isset($item["item_url"])){
		$skippedItems++;
		continue;
	}
		
	$item_hash = crc32 ($item["item_url"]);
	$item_hash = $item_hash + $item["site_id"] * pow(10, 10);
	
	
	$values = array();
	$values[] = $item_hash;
	$values[] = $item["index_fields"]["key1"];
	$values[] = $item["index_fields"]["key2"];
	$values[] = $item["index_fields"]["key3"];
	$values[] = $item["index_fields"]["key4"];
	$values[] = $item["host"];
	$values[] = $item["item_url"];
	$values[] = isset($item["price"]) ? $item["price"] : 0.0;
	$values[] = isset($item["currency"]) ? $item["currency"] : "RUB";
	$values[] = $item["site_id"];
	$values[] = $item["name"];
	$values[] = $item["art"];
	$values[] = $item["pic"];
	$values[] = $item["item_data"];
	$values[] = isset($item["expired"]) ? (time() + $item["expired"]) : (time() + 259200);
	$sValues = "'".implode ("', '", $values)."'";
	
	$query = "REPLACE INTO spider_rt (id, key1, key2, key3, key4, host, item_url, price, currency, site_id, name, art, pic, item_data, expiration_date) VALUES( $sValues )";
	
	if(mysqli_query($mysqli_sphinx, $query)) // Не важно найден товар или не найден, нужно только удалять устаревшие
		$addedItems++;

}

echo '{"added" : '.$addedItems.', "ignored" : '.$skippedItems.'}';

?>
