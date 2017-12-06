<?php
require ( "sphinxapi.php" );
error_reporting(E_ERROR | E_PARSE | E_NOTICE);
header("Content-Type: application/json;charset=utf-8");
if(isset($_GET['query']))
	$query = $_GET['query'];
else{
	die("Specify query");
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

$mysqli = mysqli_connect("127.0.0.1", "", "", "", 10306);
if(!$mysqli){
	die("Sphinx is not running");
}
$mysqli_result = mysqli_query($mysqli, "SHOW TABLES");
$mysqli_result = mysqli_fetch_all($mysqli_result, MYSQLI_ASSOC);
$indexes = array();

foreach($mysqli_result as $row){
	$indexes[] = $row["Index"];
}
mysqli_close($mysqli);
$indexes = implode(",", $indexes);

$cl = new SphinxClient();
$cl->SetServer("localhost", 10312);
if(isset($providers)){
	$cl->SetFilter("provider_id", $providers); 
}
$cl->SetSortMode(SPH_SORT_ATTR_DESC, "keyword_value");
$cl->SetMatchMode(SPH_MATCH_ANY); 
$cl->SetRankingMode(SPH_RANK_SPH04);
$cl->SetLimits((int)$offset, (int)$limit);
$result = $cl->Query($query, $indexes);
if(isset($_GET['debug'])){
	echo "Last error: ";
	var_dump($cl->GetLastError());
	echo "</br>";
	echo "Last warning: ";
	var_dump($cl->GetLastWarning());
	echo "</br>";
	echo "Query: $query";
	echo "</br></br>";
	echo "Results: ";
	print_r($result);
	echo "</br></br>";
}

if(isset($result["matches"])){
	$match_array = array();
	$hosts_list = array();
	foreach($result["matches"] as $match){
		$host = $match['attrs']['host'];
		$position = array_search($host, $hosts_list);
		if ($position === FALSE) {
			$hosts_list[] = $host;
			$match_array[]['host'] = $host;
			$match_array[count($match_array)-1]['total-weight'] = $match['attrs']['keyword_value'];
			$match_array[count($match_array)-1]['total-spider-items'] = $match['attrs']['items_count'];
		} else{
			$match_array[$position]['total-weight'] += $match['attrs']['keyword_value'];
		}
		
	}
	$final_answer = $match_array;
}
$answer = json_encode($final_answer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
echo $answer;
?>