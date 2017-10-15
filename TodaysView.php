<?php
require 'vendor/autoload.php';
use Google\Cloud\Datastore\DatastoreClient;
putenv('GOOGLE_APPLICATION_CREDENTIALS=jebaxxMonitor-be540bce3cfb.json');
$projectId = 'jebaxxmonitor';
$datastore = new DataStoreClient(['projectId' => $projectId]);

$d1 = new DateTime("today");
$d2 = new DateTime("tomorrow");

/* create SENSOR LIST */
$query = $datastore->query()
->kind('sensDat3')
//->filter('timestamp', '>', $d1->getTimeStamp())
//->filter('timestamp', '<', $d2->getTimeStamp())
->projection(['sensor'])
->distinctOn('sensor');

$result = $datastore->runQuery($query);

$sensorList = array();

$numTsensor = 0;
foreach ($result as $sensorName) {
	$sensorList[] = $sensorName['sensor'];
	if (preg_match("/^T-/", $sensorName['sensor'])) $numTsensor++;
}

/* initialize tabulation data */
$tbl_T = $tbl_P = '';
$e_cnt = array();
$e_sum = array();
$e_max = array();
$e_min = array();

foreach ($sensorList as $sensorName) {
	$e_cnt[$sensorName] = 0;
	$e_sum[$sensorName] = 0.0;
	$e_max[$sensorName] = -10000.0;
	$e_min[$sensorName] = +10000.0;
}

/* query measured record */
$query = $datastore->query()
->kind('sensDat3')
->filter('timestamp', '>', $d1->getTimeStamp())
->filter('timestamp', '<', $d2->getTimeStamp())
->order('timestamp')
->projection(['timestamp', 'sensor', 'value']);

$result = $datastore->runQuery($query);

/* make record array from query result */
foreach ($result as $sensData) {
	$rec[$sensData['timestamp']][$sensData['sensor']] = $sensData['value'];
}

//echo "*** Time Stamp ***  ";
//echo $sensData['timestamp']."\n";
//echo "var_dump(rec[sensData[timestamp]])";
//var_dump($rec[$sensData['timestamp']]);

/* create javascript dataset */
foreach ($rec as $timestamp => $sens_val) {
	$logTime = new DateTime();
	$logTime->setTimeStamp($timestamp);
	$date = $logTime->format('Y/m/d');
	$timeofday = '['.$logTime->format('H').','.$logTime->format('i').',0]';
	$tbl_T .= '['.$timeofday.', ';
	$tbl_P .= '['.$timeofday.', ';

	foreach ($sensorList as $sensorName) {
		if (array_key_exists($sensorName, $sens_val)) {
			$e_sum[$sensorName] += $sens_val[$sensorName];
			$e_cnt[$sensorName] ++;
			$e_max[$sensorName] = max($e_max[$sensorName], $sens_val[$sensorName]);
			$e_min[$sensorName] = min($e_min[$sensorName], $sens_val[$sensorName]);

			if (preg_match("/^T-/", $sensorName)) {
				$tbl_T .= $sens_val[$sensorName]. ', ';
			}
			else if (preg_match("/^P-/", $sensorName)) {
				$tbl_P .= $sens_val[$sensorName]. ', ';
			}
		}
		else {
			if (preg_match("/^T-/", $sensorName)) {
				$tbl_T .= ', ';
			}
			else if (preg_match("/^P-/", $sensorName)) {
				$tbl_P .= ', ';
			}
		}
	}

	$tbl_T .= '],'.PHP_EOL;
	$tbl_P .= '],'.PHP_EOL;
}

?>

<html>
<head>
	<script type = "text/javascript" src="https://www.google.com/jsapi"></script>
	<script type = "text/javascript">
		google.load('visualization', '1.1', {packages: ['corechart']});
		google.setOnLoadCallback(drawCharts);

	var data_T, opt_T, chart_T, view_T;
	var data_P, opt_P, chart_P, viwe_P;

	function drawCharts() {
		drawChart_T();
		drawChart_P();
	}

	function drawChart_T() {
		data_T = new google.visualization.DataTable();
		data_T.addColumn('timeofday', 'Time');
		<?php
		foreach ($sensorList as $sensorName) {
			if (preg_match("/^T-/", $sensorName)) {
				echo "		data_T.addColumn('number', '" .$sensorName."');".PHP_EOL;
			}
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

	function drawChart_P() {
		data_P = new google.visualization.DataTable();
		data_P.addColumn('timeofday', 'Time');
		<?php
		foreach ($sensorList as $sensorName) {
			if (preg_match("/^P-/", $sensorName)) {
				echo "		data_P.addColumn('number', '" .$sensorName."');".PHP_EOL;
			}
		}
		?>
		data_P.addRows([
			<?php echo $tbl_P; ?>
		]);

		view_P = new google.visualization.DataView(data_P);

		opt_P = {
 			title: 'Atomospheric Pressure (hP)',
			legend: { position: 'in' },
			vAxis: { viewWindow: {min: null, max: null, },
				 minorGridlines: { count: 3, color: '#E6E6FA' } },
			hAxis: { viewWindow: {min: [0,0,0], max: [23,59,59] },
				 minorGridlines: { count: 3, color: '#E6E6FA' } },
			height: '350',
			width:  '100%'
		};

		chart_P = new google.visualization.LineChart(
					document.getElementById('linechart_P'));


		chart_P.draw(view_P, opt_P);
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

	function changeScale(mode) {
		if (mode == 'FIXED') {
			opt_P['vAxis']['viewWindow']['min'] = 1020;
			opt_P['vAxis']['viewWindow']['max'] = 960;
		}
		else {
			opt_P['vAxis']['viewWindow']['min'] = null;
			opt_P['vAxis']['viewWindow']['max'] = null;
		}
		chart_P.draw(view_P, opt_P);
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

	function changeSize_P(mode) {
		if (mode == 'small') {
			opt_P['height'] = 150;
		}
		else if (mode == 'medium') {
			opt_P['height'] = 350;
		}
		else {
			opt_P['height'] = 700;
		}
		chart_P.draw(view_P, opt_P);
	}

	</script>

</head>

<body onresize="chart_T.draw(view_T, opt_T);chart_P.draw(view_P, opt_P);">
        <h2>Today's Atomosphere of the Room (<?php echo $date?>)</h2>

	<form name="visibleData" style="padding-left:2px; border:1px solid gray; background-color: buttonface;">
	  <span style="padding-right: 10pt">*Data Select:</span>

	<?PHP
	$k = 1;
	foreach ($sensorList as $sensorName) {
		if (preg_match("/^T-/", $sensorName)) {
			echo '	<input type="checkbox" name="tmpr" value="'.$k++.'" ';
			if ($sensorName == 'T-ADT7410-01') echo 'checked="checked" ';
			echo 'onclick="changeColumn(this.value, this.checked)">'.PHP_EOL;
			echo '	  <span style="padding-right: 10pt">'.$sensorName.'</span>'.PHP_EOL;
		}
	}
	?>

	  <span style="padding-right: 8pt">*SIZE :</span>
	  <input type="radio" name="t_size" onclick="changeSize_T('small')">SMALL
	  <input type="radio" name="t_size" onclick="changeSize_T('medium')" checked="checked">MEDIUM
	  <input type="radio" name="t_size" onclick="changeSize_T('large')">LARGE
	</form>

	<div id="linechart_temp"></div>

	<form name="scale" style="padding-left:2px; border:1px solid gray; background-color: buttonface;">
	  <span style="padding-right: 10pt">*SCALE:</span>
	  <input type="radio" name="s_temp" onclick="changeScale('AUTO')" checked="checked">AUTO
	  <input type="radio" name="s_temp" onclick="changeScale('FIXED')">FIXED
	  <span style="padding-left: 50pt; padding-right: 8pt">*SIZE:</span>
	  <input type="radio" name="p_size" onclick="changeSize_P('small')">SMALL
	  <input type="radio" name="p_size" onclick="changeSize_P('medium')" checked="checked">MEDIUM
	  <input type="radio" name="p_size" onclick="changeSize_P('large')">LARGE
	</form>

	<div id="linechart_P"></div>
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
	foreach ($sensorList as $sensorName) {
		if (preg_match("/^T-/", $sensorName)) {
			echo '	<tr><td>'.$sensorName.'</td><td>';
			printf("%7.2f", $e_min[$sensorName]);
			echo '</td><td>';
			printf("%7.2f", $e_max[$sensorName]);
			echo '</td><td>';
			printf("%7.2f", $e_sum[$sensorName] / $e_cnt[$sensorName]);
			echo '</td></tr>'.PHP_EOL;
		}
	}
	?>
	</tbody>

	</table>
	<br>
	<table border>
	<thead>
	<tr>
	<th width=170> Barometer </span> </th>
	<th width=100> min </span> </th>
	<th width=100> max </span> </th>
	<th width=100> average </span> </th>
	</tr>
	</thead>
	<tbody>
	<?php
	foreach ($sensorList as $sensorName) {
		if (preg_match("/^P-/", $sensorName)) {
			echo '	<tr><td>'.$sensorName.'</td><td>';
			printf("%7.2f", $e_min[$sensorName]);
			echo '</td><td>';
			printf("%7.2f", $e_max[$sensorName]);
			echo '</td><td>';
			printf("%7.2f", $e_sum[$sensorName] / $e_cnt[$sensorName]);
			echo '</td></tr>'.PHP_EOL;
		}
	}
	?>
	</tbody>
	</table>
	//////////////////////////
</body>
</html>

