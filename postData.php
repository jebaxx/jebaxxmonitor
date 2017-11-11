<html lang="ja">
<head>
<meta charset="UTF-8">
<title>POSTed file accepter</title>
</head>
<body>

<h1>acquire data!</h1>
<br>
<?php

  require 'vendor/autoload.php';

  use Google\Cloud\Datastore\DatastoreClient;

  putenv('GOOGLE_APPLICATION_CREDENTIALS=jebaxxMonitor-be540bce3cfb.json');

  $projectId = 'jebaxxmonitor';

  $datastore = new DataStoreClient(['projectId' => $projectId]);

  $data = $_POST['sensData'];

///////////////////////////////////////////////////////////////////////////
//  foreach ($data as $sensorName => $value) {
//    if ($sensorName == "timestamp") {
//    	$dt = new DateTime($value);
//    	$timeStamp = intval($dt->getTimeStamp()/10)*10; 
//    }
//  }
//
//  foreach ($data as $sensorName => $value) {
//    echo $sensorName."=>".$value."<br>";
//
//    if ($sensorName != "timestamp") {
//	$sensData = $datastore->entity('sensDat3');
//	$sensData["timestamp"] = $timeStamp;
//	$sensData["sensor"] = $sensorName;
//	$sensData["value"] = floatval($value);
//	$datastore->insert($sensData);
//    }
//  }
///////////////////////////////////////////////////////////////////////////
  $IndexedItems = array( "T-ADT7410-02",  "P-BMP180-01",  "I-TSL2561v-01",  "I-BH1750FV1-01");
///////////////////////////////////////////////////////////////////////////

  require_once 'google/appengine/api/cloud_storage/CloudStorageTools.php';
  use google\appengine\api\cloud_storage\CloudStorageTools;

  // get posted entities infomation from cloud storage
  //
  $gs_file = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() . "/postTime";
  $packedData = unserialize(file_get_contents($gs_file));

  if (is_array($packedData)) {
  	$ts_p = array_shift($packedData);
  	$key = array_shift($packedData);
  	$props = array_shift($packedData);
  	$notIndexedItems = array_shift($packedData);
  }

  // get timestamp of posted sensData
  //
  foreach ($data as $sensorName => $value) {
    if ($sensorName == "timestamp") {
    	$dt = new DateTime($value);
    	$timestamp = $dt->getTimeStamp(); 
    	break;
    }
  }

  if (intval($timestamp / 60) != intval($ts_p / 60)) {

  	// create new entity
  	//
  	unset($key, $props, $notIndexedItems);
  	$key = $datastore->key('sensDat2');
  	$id = $datastore->allocateId($key);
  	$props['timestamp'] = $timestamp;
  	$props['datetime']  = $dt;
  	$notIndexedItems[] = 'datetime';

	foreach ($data as $sensName => $value) {
		if ($sensName == 'timestamp') continue;
		if (!in_array($sensName, $IndexedItems)) $notIndexedItems[] = $sensName;

		$props[$sensName] = floatval($value);
	}

	syslog(LOG_INFO, "CreteNew props_num = ". count($props));
//	$list = "";
//	foreach ($notIndexedItems as $sensor_name) {
//		$list .= $sensor_name . ",";
//	}
//	syslog(LOG_INFO, "notIndexed: ".$list);
	$sensData = $datastore->entity($key, $props, ['excludeFromIndexes'=> $notIndexedItems]);
	$datastore->insert($sensData);

//	file_put_contents($gs_file, $timestamp.','.$key->path()[0]["id"]);
  }
  else {

  	// Update entity
  	//
  	// $key and $props inherit from packaged gs file
  	//
	foreach ($data as $sensName => $value) {
		if ($sensName == 'timestamp') continue;
		if (!in_array($sensName, $IndexedItems)) $notIndexedItems[] = $sensName;

		$props[$sensName] = floatval($value);
	}

	syslog(LOG_INFO, "Upsert props_num = ". count($props));
//	$list = "";
//	foreach ($notIndexedItems as $sensor_name) {
//		$list .= $sensor_name . ",";
//	}
//	syslog(LOG_INFO, "notIndexed: ".$list);
	$sensData = $datastore->entity($key, $props, ['excludeFromIndexes'=> $notIndexedItems]);
	$datastore->upsert($sensData);
  }

  //  serialize these data and store to cloudstorage
  //
  //	timestamp
  //	id
  //	props[]
  //	notIndexedItems[]
  unset($packedData);
  $packedData[] = $timestamp;
  $packedData[] = $key;
  $packedData[] = $props;
  $packedData[] = $notIndexedItems;
  file_put_contents($gs_file, serialize($packedData));

///////////////////////////////////////////////////////////////////////////

 ?>

</body>
</html>

