
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

  foreach ($data as $sensorName => $value) {
    if ($sensorName == "timestamp") {
    	$dt = new DateTime($value);
    	$timeStamp = $dt->getTimeStamp(); 
    }
  }

  foreach ($data as $sensorName => $value) {
    echo $sensorName."=>".$value."<br>";

    if ($sensorName != "timestamp") {
	$sensData = $datastore->entity('sensDat3');
	$sensData["timestamp"] = $timeStamp;
	$sensData["sensor"] = $sensorName;
	$sensData["value"] = floatval($value);
	$datastore->insert($sensData);
    }
  }

 ?>

</body>
</html>

