<?php
require 'vendor/autoload.php';
use Google\Cloud\Datastore\DatastoreClient;
putenv('GOOGLE_APPLICATION_CREDENTIALS=jebaxxMonitor-be540bce3cfb.json');
$projectId = 'jebaxxmonitor';
$datastore = new DataStoreClient(['projectId' => $projectId]);

date_default_timezone_set('Asia/Tokyo');

$d1 = new DateTime("today");
$d2 = new DateTime("tomorrow");

/* create SENSOR LIST */
$query = $datastore->query()
  ->kind('sensDat3')
  ->projection(['sensor'])
  ->distinctOn('sensor');

$result = $datastore->runQuery($query);
/**************************
$result = array(
		array('sensor' => 'T-ADT7410-01'),
		array('sensor' => 'T-BMP180-01'),
		array('sensor' => 'I-TSL2561v-01'),
		array('sensor' => 'P-BMP180-01'),
		array('sensor' => 'T-ADT7410-02'),
		array('sensor' => 'T-cpu-mavis')
		);
***************************/

// sensorList['sensorName'][0] = sensorType
// sensorList['sensorName'][1] = enumeration of sensors in same kind
// sensorList['sensorName'][2] = enumeration of sensors 
$sensorList = array();
$sensorEnums = array();
$numSensor = $numTsensor = 0;

foreach ($result as $sensorName) {

	$sensorEnums[$numSensor] = $sensorName['sensor'];

	if (preg_match("/^([A-Z])-/", $sensorName['sensor'], $result)) {
		if ($result[1] == 'T') {
			$sensorList[$sensorName['sensor']][0] = 1;		// sensorType = 1
			$sensorList[$sensorName['sensor']][1] = $numTsensor++;	// enumeration number of sensor
			$sensorList[$sensorName['sensor']][2] = $numSensor++;	// sensorId
		}
		else {
			$sensorList[$sensorName['sensor']][0] = 0;		// sensorType = 0(out of scope)
			$sensorList[$sensorName['sensor']][1] = -1;		// enumeration number of sensor
			$sensorList[$sensorName['sensor']][2] = -1;		// sensorId
		}
	}
}

/* initialize tabulation data */
$tbl_T = '';
$e_cnt = array();
$e_sum = array();
$e_max = array();
$e_min = array();

for ($sensor_id = 0; $sensor_id < $numSensor; $sensor_id++) {
	$e_cnt[$sensor_id] = 0;
	$e_sum[$sensor_id] = 0.0;
	$e_max[$sensor_id] = -10000.0;
	$e_min[$sensor_id] = +10000.0;
}

/* query measured record */
$query = $datastore->query()
  ->kind('sensDat3')
  ->filter('timestamp', '>', $d1->getTimeStamp())
  ->filter('timestamp', '<', $d2->getTimeStamp())
  ->order('timestamp')
  ->projection(['timestamp', 'sensor', 'value']);

$result = $datastore->runQuery($query);

/**************************
$result = array(
		array('timestamp' => 1507728303, 'sensor' => 'T-ADT7410-01', 'value' => 27.6),
		array('timestamp' => 1507728303, 'sensor' => 'T-BMP180-01', 'value' => 28.4),
		array('timestamp' => 1507728303, 'sensor' => 'P-BMP180-01', 'value' => 999.4),
		array('timestamp' => 1507728303, 'sensor' => 'T-ADT7410-02', 'value' => 27.6),
		array('timestamp' => 1507728303, 'sensor' => 'T-cpu-mavis', 'value' => 39.6),

		array('timestamp' => 1507728363, 'sensor' => 'T-ADT7410-01', 'value' => 27.7),
		array('timestamp' => 1507728363, 'sensor' => 'T-BMP180-01', 'value' => 28.5),
		array('timestamp' => 1507728363, 'sensor' => 'P-BMP180-01', 'value' => 999.8),
		array('timestamp' => 1507728363, 'sensor' => 'I-TSL2561v-01', 'value' => 1295.8),
		array('timestamp' => 1507728363, 'sensor' => 'T-ADT7410-02', 'value' => 27.7),
		array('timestamp' => 1507728363, 'sensor' => 'T-cpu-mavis', 'value' => 40.7),

		array('timestamp' => 1507728393, 'sensor' => 'T-ADT7410-01', 'value' => 27.0),
		array('timestamp' => 1507728393, 'sensor' => 'P-BMP180-01', 'value' => 999.0),
		array('timestamp' => 1507728393, 'sensor' => 'T-ADT7410-02', 'value' => 27.0),
		array('timestamp' => 1507728393, 'sensor' => 'T-cpu-mavis', 'value' => 39.0),
		array('timestamp' => 1507728363, 'sensor' => 'I-TSL2561v-01', 'value' => 1295.0),
		);

**************************/

$date = $d1->format('Y/m/d');

/* create javascript dataset */
$timestamp = 0;
foreach ($result as $sensData) {
	$sensor_info = $sensorList[$sensData['sensor']];
	if ($sensor_info[0] == 0) continue;
	if ($timestamp < $sensData['timestamp']) {
		if ($timestamp != 0) {
			// sweep out 1 line
			for ($n = 0; $n < $numTsensor; $n++) {
				if (array_key_exists($n, $line_T))
					$tbl_T .= $line_T[$n] . ", ";
				else
					$tbl_T .= ", ";
			}
			$tbl_T .= '],'.PHP_EOL;

			unset($line_T);
		}

		$timestamp = $sensData['timestamp'];
		$logTime = new DateTime();
		$logTime->setTimeStamp($timestamp);
		$timeofday = '['.$logTime->format('H').','.$logTime->format('i').',0]';
		$tbl_T .= '['.$timeofday.', ';
	}

	if ($sensor_info[0] == 1) {
		$line_T[$sensor_info[1]] = ($val = $sensData['value']);
	}

	$e_sum[$sensor_info[2]] += $val;
	$e_cnt[$sensor_info[2]] ++;
	$e_max[$sensor_info[2]] = max($e_max[$sensor_info[2]], $val);
	$e_min[$sensor_info[2]] = min($e_min[$sensor_info[2]], $val);
}

for ($n = 0; $n < $numTsensor; $n++) {
	if (array_key_exists($n, $line_T))
		$tbl_T .= $line_T[$n] . ", ";
	else
		$tbl_T .= ", ";
}

$tbl_T .= '],'.PHP_EOL;

?>

<html>
<head>
	<script type = "text/javascript" src="https://www.google.com/jsapi"></script>
	<script type = "text/javascript">
		google.load('visualization', '1.1', {packages: ['corechart']});
		google.setOnLoadCallback(drawCharts);

	var data_T, opt_T, chart_T, view_T;

	function drawCharts() {
		drawChart_T();
	}

	function drawChart_T() {
		data_T = new google.visualization.DataTable();
		data_T.addColumn('timeofday', 'Time');
		<?php
		foreach ($sensorEnums as $sensor_id => $sensor_name) {
			if ($sensorList[$sensor_name][0] == 1)
				echo "		data_T.addColumn('number', '" .$sensor_name. "');".PHP_EOL;
		}
		?>
		data_T.addRows([
			<?php echo $tbl_T; ?>
		]);

		view_T = new google.visualization.DataView(data_T);

		opt_T = { 
			title: 'Temperature (C)',
			legend: { position: 'in' },
			vAxis: { minorGridlines: { count: 4, color: '#E6E6FA' }},
			hAxis: { minorGridlines: { count: 3, color: '#E6E6FA' },
				 viewWindow: {min: [0,0,0], max: [23,59,59] } },
			height: '350',
			width:  '100%'
		};

		chart_T = new google.visualization.LineChart(
					document.getElementById('linechart_temp'));

		var setC = new Array();
		setC[0] = 0;
		for (i = j = 0; i < <?php echo $numTsensor ?> ;  i++) {
			if (document.visibleData[i].checked == true) {
				setC[++j] = i+1;
			}
		}
		view_T.setColumns(setC);

		chart_T.draw(view_T, opt_T);
	}

	function changeColumn(index, checked) {
		var setC = new Array();
		setC[0] = 0;
		for (i = j = 0; i < <?php echo $numTsensor?> ;  i++) {
			if (document.visibleData[i].checked == true) {
				setC[++j] = i+1;
			}
		}

		view_T.setColumns(setC);
		chart_T.draw(view_T, opt_T);
	}

	function changeSize_T(mode) {
		if (mode == 'small') {
			opt_T['height'] = 150;
		}
		else if (mode == 'medium') {
			opt_T['height'] = 350;
		}
		else {
			opt_T['height'] = 700;
		}
		chart_T.draw(view_T, opt_T);
	}

	</script>

</head>

<body onresize="chart_T.draw(view_T, opt_T);">
        <h2>Today's Temberature of my Room (<?php echo $date?>)</h2>

        <span style="padding-left: 20pt">
	<A href="index.html">Home</A></span>

        <span style="padding-left: 20pt">
	<A href="TodaysView.php">Temperature & Barometer</A></span><BR>

	<form name="visibleData" style="padding-left:2px; border:1px solid gray; background-color: buttonface;">
	  <span style="padding-right: 10pt">*Data Select:</span>
	<?php
	$k = 1;
	foreach ($sensorEnums as $sensor_id => $sensor_name) {
		if ($sensorList[$sensor_name][0] == 1) {
			echo '	<input type="checkbox" name="tmpr" value="'.$k++.'" ';
			if ($sensor_name == 'T-ADT7410-02') echo 'checked="checked" ';
			if ($sensor_name == 'T-ADT7410-01') echo 'checked="checked" ';
			if ($sensor_name == 'T-BMP180-01') echo 'checked="checked" ';
			echo 'onclick="changeColumn(this.value, this.checked)">'.PHP_EOL;
			echo '	  <span style="padding-right: 10pt">'.$sensor_name.'</span>'.PHP_EOL;
		}
	}
	?>

	  <span style="padding-right: 8pt">*SIZE :</span>
	  <input type="radio" name="t_size" onclick="changeSize_T('small')">SMALL
	  <input type="radio" name="t_size" onclick="changeSize_T('medium')" checked="checked">MEDIUM
	  <input type="radio" name="t_size" onclick="changeSize_T('large')">LARGE
	</form>

	<div id="linechart_temp"></div>

	<hr>
	//////////////////////////
	<?php echo "<h3>*** Record of the day (".$date.") ***</h3>\n"; ?>
	<table border>
	<thead>
	<tr>
	<th width=170> temperature </span> </th>
	<th width=100> min </span> </th>
	<th width=100> max </span> </th>
	<th width=100> average </span> </th>
	</tr>
	</thead>
	<tbody>
	<?php
	foreach ($sensorEnums as $sensor_id => $sensor_name) {
		if ($sensorList[$sensor_name][0] == 1) {
			echo '<tr><td>'.$sensor_name.'</td><td>';
			printf("%7.2f", $e_min[$sensor_id]);
			echo '</td><td>';
			printf("%7.2f", $e_max[$sensor_id]);
			echo '</td><td>';
			printf("%7.2f", $e_sum[$sensor_id] / $e_cnt[$sensor_id]);
			echo '</td></tr>'.PHP_EOL;
		}
	}
	?>
	</tbody>
	</table>
	//////////////////////////
</body>
</html>

