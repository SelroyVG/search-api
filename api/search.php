<?php
if(isset($_GET['debug'])){
	$start = microtime(true); 
}
require ( "sphinxapi.php" );
error_reporting(E_ERROR | E_PARSE | E_NOTICE);
header("Content-Type: application/json;charset=utf-8");
if(isset($_GET['query']))
	$query = $_GET['query'];
else{
	die("Specify query");
}
if(isset($_GET['user_id']))
	$user_id = $_GET['user_id'];
else
	$user_id = 0;
if(isset($_GET['providers']))
	$providers = explode(',', $_GET['providers']);

if(isset($_GET['category_id']))
	$category_id = explode(',', $_GET['category_id']);

$weights = array();
if(isset($_GET['name']))
	$weights['name'] = $_GET['name'];
else{
	$weights['name'] = 10;
}
if(isset($_GET['categories']))
	$weights['categories'] = $_GET['categories'];
else{
	$weights['categories'] = 6;
}
if(isset($_GET['properties']))
	$weights['properties'] = $_GET['properties'];
else{
	$weights['properties'] = 3;
}
if(isset($_GET['description']))
	$weights['description'] = $_GET['description'];
else{
	$weights['description'] = 1;
}
if(isset($_GET['offset']))
	$offset = $_GET['offset'];
else{
	$offset = 0;
}
if(isset($_GET['limit']))
	$limit = $_GET['limit'];
else{
	$limit = 20;
}

/////////////////////////////////////////
//addLogsEntry();


$mysqli_sphinx = mysqli_connect("127.0.0.1", "", "", "", 9306);
if(!$mysqli_sphinx)
	die("Error: sphinx is not running");

$mysqli_result = mysqli_fetch_all(mysqli_query($mysqli_sphinx, "SHOW TABLES"), MYSQLI_ASSOC);
$indexes = array();
foreach($mysqli_result as $row){
	if(($row["Index"] != "spider_rt") && ($row["Index"] != "blank_index"))
		$indexes[] = $row["Index"];
}
$indexes = implode(",", $indexes);
mysqli_close($mysqli_sphinx);
$cl = new SphinxClient();
$cl->SetServer("localhost", 9312);

$mysqli = mysqli_connect("localhost", "scc", "ZQLf-0.4");
if(!$mysqli){
	die("Error: can't connect to MySQL");
}
$providersFilter = array();
$serversFilter = array();
if(!isset($providers)){
	$mysql_result = mysqli_fetch_all(mysqli_query($mysqli, "SELECT s.local_id FROM scc.global_spiders AS s JOIN scc.providers AS p WHERE s.id=p.spider_id"));
	foreach($mysql_result as $mysql_row){
		$providersFilter[] = $mysql_row[0];
	} 
} else{
	$mysql_result = mysqli_fetch_all(mysqli_query($mysqli, "SELECT s.local_id FROM scc.global_spiders AS s JOIN scc.providers AS p WHERE s.id=p.spider_id AND p.id IN(".implode(",", $providers).")"));
	foreach($mysql_result as $mysql_row){
		$providersFilter[] = $mysql_row[0];
	} 
	$mysql_result = mysqli_fetch_all(mysqli_query($mysqli, "SELECT s.server_id FROM scc.global_spiders AS s JOIN scc.providers AS p WHERE s.id=p.spider_id AND p.id IN(".implode(",", $providers).") GROUP BY s.server_id"));
	foreach($mysql_result as $mysql_row){
		$serversFilter[] = $mysql_row[0];
	} 
}

$cl->SetFilter("provider_id", $providersFilter); 
if(isset($_GET['providers']))
	$cl->SetFilter("server_id", $serversFilter); 
if(isset($_GET['category_id'])){
	$cl->SetFilter("category_id", $category_id); 
}

$cl->SetSortMode(SPH_SORT_RELEVANCE);
$cl->SetMatchMode(SPH_MATCH_ALL); 
$cl->SetRankingMode(SPH_RANK_SPH04);
$cl->SetFieldWeights($weights);
$cl->SetLimits((int)$offset, (int)$limit);
$result = $cl->Query($query, $indexes); 

$answer = array();
if(isset($result["matches"]))
	foreach($result["matches"] as $match){
		$match_array['id'] = $match['attrs']['item_id'];
		if(isset($match['attrs']['server_id']))
			$match_array['provider_id'] = (int)(getProviderId($mysqli, $match['attrs']['provider_id'], $match['attrs']['server_id']));
		else
			$match_array['provider_id'] = (int)($match['attrs']['provider_id']);
	/*	if(isset($match['attrs']['category_id']))
			$match_array['category_id'] = $match['attrs']['category_id'];*/
		$match_array['name'] = $match['attrs']['attr_name'];
		$match_array['preview'] = $match['attrs']['preview'];
		$match_array['price'] = $match['attrs']['price'];
		$answer[] = $match_array;
	}
$final_answer["items"] = $answer;
mysqli_close($mysqli);
	
$answer = json_encode($final_answer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
echo $answer;

if(isset($_GET['debug'])){
	echo "Last error: ";
	var_dump($cl->GetLastError());
	echo "Last warning: ";
	var_dump($cl->GetLastWarning());
	echo "Query: $query";
	echo "\nResults: ";
	print_r($result);
	echo "\n\n".(microtime(true) - $start)." sec";
}


function getProviderId(&$mysqli, $local_id, $server_id){
	$mysql_output = mysqli_fetch_row(mysqli_query($mysqli, "SELECT p.id FROM scc.providers AS p JOIN scc.global_spiders AS s WHERE s.id=p.spider_id AND s.local_id='".$local_id."' AND s.server_id='".$server_id."'"));
	if(!$mysql_output)
		return 0;
	return $mysql_output[0];
}


function addLogsEntry(){
	$mysql_host = "";
	$mysql_user = "";
	$mysql_password = "";
	$mysql_dbname = "";
	$file_handle = fopen("/var/www/sphinx/configs/sphinx_logs.config", "r");
	if($file_handle){
		while (!feof($file_handle)) {
			$fileString = fgets($file_handle);
			if(preg_match('/mysql_host\s*=\s*"(.*)"/', $fileString, $matches)){
				$mysql_host = $matches[1];
			}
			if(preg_match('/mysql_user\s*=\s*"(.*)"/', $fileString, $matches)){
				$mysql_user = $matches[1];
			}
			if(preg_match('/mysql_password\s*=\s*"(.*)"/', $fileString, $matches)){
				$mysql_password = $matches[1];
			}
			if(preg_match('/mysql_dbname\s*=\s*"(.*)"/', $fileString, $matches)){
				$mysql_dbname = $matches[1];
			}
		}
		fclose($file_handle);
	}
	$mysqli = mysqli_connect($mysql_host, $mysql_user, $mysql_password, $mysql_dbname);
	if($mysqli){
		mysqli_query($mysqli, "SET NAMES 'utf8'");
		mysqli_query($mysqli, "SET CHARACTER SET 'utf8'");
		mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS query_logs ( `user_id` int(10) unsigned DEFAULT 0, `date` datetime NOT NULL, `query` char(255) CHARACTER SET utf8, `extended` text(2000), FULLTEXT idx (`extended`), KEY(`user_id`))");
		mysqli_query($mysqli, 'INSERT INTO query_logs (`user_id`,`date`,`query`) VALUES('.$user_id.', NOW(), "'.$query.'")');
		mysqli_close($mysqli);
	}

}
?>