<?php
if (isset($_GET['_start'])) {
	$d1 = new DateTime($_GET['_start']);
	$d2 = clone $d1;
	$d2->add(new DateInterval('P0'.$_GET['_span'].'D'));
	$_span = intval($_GET['_span']);
}
else {
	$d1 = new DateTime("today");
	$d2 = new DateTime("tomorrow");
	$_span = 1;
}

/// initialize GAE ///
///
require 'vendor/autoload.php';
use Google\Cloud\Datastore\DatastoreClient;
putenv('GOOGLE_APPLICATION_CREDENTIALS=jebaxxMonitor-be540bce3cfb.json');
$projectId = 'jebaxxmonitor';
$datastore = new DataStoreClient(['projectId' => $projectId]);
date_default_timezone_set('Asia/Tokyo');

//**************************
$sensor_list = array( 'T-ADT7410-01', 'T-BMP180-01', 'T-ADT7410-02', 'T-cpu-mavis', 'T-cpu-boco' );
//**************************

/// initialize tabulation data ///
///
$tbl_T = '';
$e_cnt = array();
$e_sum = array();
$e_max = array();
$e_min = array();

foreach ($sensor_list as $sensor_name) {
	$e_cnt[$sensor_name] = 0;
	$e_sum[$sensor_name] = 0.0;
	$e_max[$sensor_name] = -10000.0;
	$e_min[$sensor_name] = +10000.0;
}

/// query measured record ///
///
$query = $datastore->query()
  ->kind('sensDat2')
  ->filter('timestamp', '>', $d1->getTimeStamp())
  ->filter('timestamp', '<', $d2->getTimeStamp())
  ->order('timestamp');

$result = $datastore->runQuery($query);

/**************************
$result = array(
		array('timestamp' => 1507728303, 'T-ADT7410-01' => 27.6, 'T-BMP180-01' => 28.4, 'P-BMP180-01' => 999.4, 
		'T-ADT7410-02' => 27.6, 'T-cpu-mavis' => 39.6),

		array('timestamp' => 1507728363, 'T-ADT7410-01' => 27.7, 'T-BMP180-01' => 28.5, 'P-BMP180-01' => 999.8,
		'I-TSL2561v-01' => 1295.8, 'T-ADT7410-02' => 27.7, 'T-cpu-mavis' => 40.7),

		array('timestamp' => 1507728393, 'T-ADT7410-01' => 27.0, 'P-BMP180-01' => 999.0, 'T-ADT7410-02' => 27.0,
		'T-cpu-mavis' => 39.0, 'I-TSL2561v-01' => 1295.0)
		);
**************************/

$date = $d1->format('Y/m/d');

/// create javascript dataset ///
///
foreach ($result as $sensData) {

	$timestamp = $sensData['timestamp'];
	$logTime = new DateTime();
	$logTime->setTimeStamp($timestamp);
	$_time = 'new Date('.$logTime->format('Y,m,d,H,i'). ',0)';
	$tbl_T .= '['.$_time.', ';

	foreach ($sensor_list as $sensor_name) {

		$value = $sensData[$sensor_name];
		$tbl_T .= $value. ',';
		$e_cnt[$sensor_name] ++;
		$e_sum[$sensor_name] += $value;
		$e_max[$sensor_name] = max($e_max[$sensor_name], $value);
		$e_min[$sensor_name] = min($e_min[$sensor_name], $value);
	}

	$tbl_T .= '],'.PHP_EOL;
}

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
		data_T.addColumn('datetime', 'Time');
		<?php
		foreach ($sensor_list as $sensor_name) {
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
				 viewWindow: {min: new Date(<?php echo $d1->format('Y,m,d,H,i') ?>,0),
				 	      max: new Date(<?php echo $d2->format('Y,m,d,H,i') ?>,0), } },

			height: '350',
			width:  '100%'
		};

		chart_T = new google.visualization.LineChart(
					document.getElementById('linechart_temp'));

		var setC = new Array();
		setC[0] = 0;
		for (i = j = 0; i < <?php echo count($sensor_list) ?> ;  i++) {
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
		for (i = j = 0; i < <?php echo count($sensor_list) ?> ;  i++) {
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
	<A href="temp_barometer.php">Temperature & Barometer</A></span><BR>

	<form name="visibleData" style="padding-left:2px; border:1px solid gray; background-color: buttonface;">
	  <span style="padding-right: 10pt">*Data Select:</span>
	  <?php
	  $k = 1;
	  foreach ($sensor_list as $sensor_name) {
		echo '	<input type="checkbox" name="tmpr" value="'.$k++.'" ';
		if (in_array($sensor_name, ['T-ADT7410-02', 'T-ADT7410-01','T-BMP180-01'])) echo 'checked="checked" ';
		echo 'onclick="changeColumn(this.value, this.checked)">'.PHP_EOL;
		echo '	  <span style="padding-right: 10pt">'.$sensor_name.'</span>'.PHP_EOL;
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
	foreach ($sensor_list as $sensor_name) {
		echo '<tr><td>'.$sensor_name.'</td><td>';
		if ($e_cnt[$sensor_name] > 0) {
			printf("%7.2f", $e_min[$sensor_name]);
			echo '</td><td>';
			printf("%7.2f", $e_max[$sensor_name]);
			echo '</td><td>';
			printf("%7.2f", $e_sum[$sensor_name] / $e_cnt[$sensor_name]);
			echo '</td></tr>'.PHP_EOL;
		}
		else {
			echo '-</td><td>';
			echo '-</td><td>';
			echo '-</td></tr>'.PHP_EOL;
		}
	}
	?>
	</tbody>
	</table>
	//////////////////////////
</body>
</html>

