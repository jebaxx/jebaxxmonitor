<?php
require 'vendor/autoload.php';
use Google\Cloud\Datastore\DatastoreClient;
putenv('GOOGLE_APPLICATION_CREDENTIALS=jebaxxMonitor-be540bce3cfb.json');
$projectId = 'jebaxxmonitor';
$datastore = new DataStoreClient(['projectId' => $projectId]);

$sensorList = array("T-ADT7410-01", "T-ADT7410-02", "T-BMP180-01", "T-cpu-maivs", "T-cpu-boco",  "P-BMP180-01", 
			"I-TSL2461v-01", "I-TSL2561ir-01", "I-TSL2561f-01", "I-BH1750FV1-01");

$IndexedItems = array( "T-ADT7410-02",  "P-BMP180-01",  "I-TSL2561v-01",  "I-BH1750FV1-01");

date_default_timezone_set('Asia/Tokyo');

$d1 = new DateTime($_GET['start_date']);
$d2 = new DateTime($_GET['end_date']);

$query = $datastore->query()
  ->kind('sensDat3')
  ->filter('timestamp', '>', $d1->getTimeStamp())
  ->filter('timestamp', '<', $d2->getTimeStamp())
  ->order('timestamp')
  ->projection(['timestamp', 'sensor', 'value']);

$result = $datastore->runQuery($query);

echo 'get query result<br>'.PHP_EOL;

$timestamp = 0;

foreach ($result as $sensDat3) {
	if ($timestamp < $sensDat3['timestamp']) {
		echo 'timestaamp:'.$timestamp.PHP_EOL;
		if (isset($props)) {
			// create and insert entity
			$sensDat2 = $datastore->entity(
						'sensDat2', 
						$props, 
						['excludeFromIndexes' => $notIndexedItems]);
			$datastore->insert($sensDat2);
			unset($porps, $notIndexedItems);
		}
		// create new entity
		$timestamp = $sensDat3['timestamp'];
		$dr = new DateTime();
		$dr->setTimestamp($timestamp);
		$props = array('timestamp' => $timestamp);
		$props['datetime'] = $dr;
		$notIndexedItems[] = 'datetime';
	}
	$sensorName = $sensDat3['sensor'];
	$props[$sensorName] = $sensDat3['value'];
	if (!in_array($sensorName, $IndexedItems)) $notIndexedItems[] = $sensorName;
}

?>
