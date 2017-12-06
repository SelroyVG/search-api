<?php
if(isset($_GET['debug'])){
	$start = microtime(true); 
}
require ( "/var/www/sphinx/sphinxapi.php" );
error_reporting(E_ERROR | E_PARSE | E_NOTICE);
header("Content-Type: application/json;charset=utf-8");
if(isset($_GET['query']))
	$query = $_GET['query'];
else{
	die("Specify query");
}

$weights = array();
$weights['key1'] = 10;
$weights['key2'] = 6;
$weights['key3'] = 3;
$weights['key4'] = 1;


if(isset($_GET['weights'])){
	$tempWeights = explode(",", $_GET['weights']);
	if(count($tempWeights) == 4){
		$weights['key1'] = $tempWeights[0];
		$weights['key2'] = $tempWeights[1];
		$weights['key3'] = $tempWeights[2];
		$weights['key4'] = $tempWeights[3];
	}
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

$cl = new SphinxClient();
$cl->SetServer("localhost", 9312);


if(isset($_GET['hosts']))
	$cl->SetGroupBy("host", SPH_GROUPBY_ATTR, "@count desc");

$cl->SetFilterRange("expiration_date", time(), PHP_INT_MAX); // Фильтр результатов, чтобы отдавались только те, у которых expiration_date - некоторый момент в будущем
$cl->SetSortMode(SPH_SORT_RELEVANCE);
$cl->SetMatchMode(SPH_MATCH_ALL); 
$cl->SetRankingMode(SPH_RANK_SPH04);
$cl->SetFieldWeights($weights);
$cl->SetLimits((int)$offset, (int)$limit);
$result = $cl->Query($query, "spider_rt"); 

$answer = array();

if(!isset($_GET['hosts'])){
	if(isset($result["matches"]))
		foreach($result["matches"] as $match){
			$match_array['name'] = $match['attrs']['name'];
			$match_array['item_url'] = $match['attrs']['item_url'];
			$match_array['site_id'] = $match['attrs']['site_id'];
			$match_array['pic'] = $match['attrs']['pic'];
			$match_array['price'] = $match['attrs']['price'];
			$match_array['currency'] = $match['attrs']['currency'];
			
			$answer[] = $match_array;
		}
	$final_answer["items"] = $answer;
} else{
	if(isset($result["matches"]))
		foreach($result["matches"] as $match){
			$match_array['host'] = $match['attrs']['host'];
			$match_array['total-weight'] = $match['weight'];
			$match_array['total-spider-items'] = $match['attrs']['@count'];
			$answer[] = $match_array;
		}
	$final_answer["hosts"] = $answer;
}
	
	

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

?>