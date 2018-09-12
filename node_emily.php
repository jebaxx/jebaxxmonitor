<?php
require 'vendor/autoload.php';
use Google\Cloud\Datastore\DatastoreClient;
putenv('GOOGLE_APPLICATION_CREDENTIALS=jebaxxMonitor-be540bce3cfb.json');
$projectId = 'jebaxxmonitor';
$datastore = new DataStoreClient(['projectId' => $projectId]);

date_default_timezone_set('Asia/Tokyo');

if (isset($_GET['_start'])) {
	$d1 = new DateTime($_GET['_start']);
	$d2 = clone $d1;
	$d2->add(new DateInterval('P0'.$_GET['_span'].'D'));
	$d2->sub(new DateInterval('PT1S'));
	$_span = intval($_GET['_span']);
}
else {
	$d1 = new DateTime("today");
	$d2 = new DateTime("tomorrow");
	$d2->sub(new DateInterval('PT1S'));
	$_span = 1;
}

// initialize tabulation data
//
$sensor_name = array( 'T-SHT-31', 'T-cpu_emily', 'H-SHT-31', 'C-HC501');

$tbl_T = $tbl_H = $tbl_C = '';
$e_cnt = array();
$e_sum = array();
$e_max = array();
$e_min = array();

foreach ($sensor_name as $sensor) {
	$e_cnt[$sensor] = 0;
	$e_sum[$sensor] = 0.0;
	$e_max[$sensor] = -10000.0;
	$e_min[$sensor] = +10000.0;
}


// query measured Temperature record
//
$query = $datastore->query()
  ->kind('sensDat2')
  ->filter('timestamp', '>', $d1->getTimeStamp())
  ->filter('timestamp', '<', $d2->getTimeStamp())
  ->order('timestamp') ;

$result = $datastore->runQuery($query);

$g0 = 24*60*$_span;
$g = 0;

// create javascript dataset
foreach ($result as $sensData) {
	if (($g -= 1000) > 0) continue;
	$g += $g0;

	$timestamp = $sensData['timestamp'];
	$logTime = new DateTime();
	$logTime->setTimeStamp($timestamp);
	$_time = 'new Date('.$logTime->format('Y,m,d,H,i'). ',0)';

	if (($value = $sensData[$sensor_name[0]]) != NULL) {
	    $e_sum[$sensor_name[0]] += $value;
	    $e_cnt[$sensor_name[0]] ++;
	    $e_max[$sensor_name[0]] = max($e_max[$sensor_name[0]], $value);
	    $e_min[$sensor_name[0]] = min($e_min[$sensor_name[0]], $value);

	    $tbl_T .= '	[ ' . $_time . ' , ' . $value.' , ';
	}
	else
	    $tbl_T .= '	[ ' . $_time . ' , null , ';

	if (($value = $sensData[$sensor_name[1]]) != NULL) {
	    $e_sum[$sensor_name[1]] += $value;
	    $e_cnt[$sensor_name[1]] ++;
	    $e_max[$sensor_name[1]] = max($e_max[$sensor_name[1]], $value);
	    $e_min[$sensor_name[1]] = min($e_min[$sensor_name[1]], $value);

	    $tbl_T .= $value.' ],' . PHP_EOL;
	}
	else
	    $tbl_T .= 'null ],' . PHP_EOL;

	if (($value = $sensData[$sensor_name[2]]) != NULL) {
	    $e_sum[$sensor_name[2]] += $value;
	    $e_cnt[$sensor_name[2]] ++;
	    $e_max[$sensor_name[2]] = max($e_max[$sensor_name[2]], $value);
	    $e_min[$sensor_name[2]] = min($e_min[$sensor_name[2]], $value);
	    $tbl_H .= '	[ ' . $_time . ' , ' . $value . ' ],' . PHP_EOL;
	}

	if (($value = $sensData[$sensor_name[3]]) != NULL) {
	    $e_sum[$sensor_name[3]] += $value;
	    $e_cnt[$sensor_name[3]] ++;
	    $e_max[$sensor_name[3]] = max($e_max[$sensor_name[3]], $value);
	    $e_min[$sensor_name[3]] = min($e_min[$sensor_name[3]], $value);
	    $tbl_C .= '	[ ' . $_time . ' , ' . $value . ' ],' . PHP_EOL;
	}
}

?>

<!--
/////////////////////////////////////////////////////////////////////////////////////////
//	HTML
/////////////////////////////////////////////////////////////////////////////////////////
-->
<html>
<head>
	<script type = "text/javascript" src="https://www.google.com/jsapi"></script>
	<script type = "text/javascript">
		google.load('visualization', '1.1', {packages: ['corechart']});
		google.setOnLoadCallback(drawCharts);

	var data_T, opt_T, chart_T, view_T;
	var data_H, opt_H, chart_H, viwe_H;
	var data_C, opt_C, chart_C, viwe_C;
	var data_P, opt_P, chart_P, viwe_P;     // dummy

	function drawCharts() {
		drawChart_T();
		drawChart_H();
		drawChart_C();
	}

	function drawChart_T() {
		data_T = new google.visualization.DataTable();
		data_T.addColumn('datetime', 'Time');
		<?php
		    echo "data_T.addColumn('number', '" .$sensor_name[0]. "');".PHP_EOL;
		    echo "data_T.addColumn('number', '" .$sensor_name[1]. "');".PHP_EOL;
		?>
		data_T.addRows([
			<?php echo $tbl_T; ?>
		]);

		view_T = new google.visualization.DataView(data_T);

		opt_T = { 
			title: 'Temperature (C)',
			legend: { position: 'in' },
			vAxis: { viewWindow: {min: null, max: null, },
				 minorGridlines: { count: 3, color: '#E6E6FA' } },
			hAxis: { minorGridlines: { count: 3, color: '#E6E6FA' },
				 viewWindow: {min: new Date(<?php echo $d1->format('Y,m,d,H,i,s') ?>),
				 	      max: new Date(<?php echo $d2->format('Y,m,d,H,i,s') ?>), } },
			height: '150',
			width:  '100%'
		};

		chart_T = new google.visualization.LineChart(
					document.getElementById('linechart_T'));

		var sel = document.form_T.sensor_T
		var setC = new Array();
		setC[0] = 0;
		for (var i = 0; i < sel.length ;  i++) {
			if (sel[i].selected) setC.push( i+1 );
		}
		view_T.setColumns(setC);
		chart_T.draw(view_T, opt_T);
	}

	function drawChart_H() {
		data_H = new google.visualization.DataTable();
		data_H.addColumn('datetime', 'Time');
		<?php
		  echo "data_H.addColumn('number', '" .$sensor_name[2]. "');".PHP_EOL;
		?>
		data_H.addRows([
			<?php echo $tbl_H; ?>
		]);

		view_H = new google.visualization.DataView(data_H);

		opt_H = {
 			title: 'Humidity (%)',
			legend: { position: 'in' },
			vAxis: { viewWindow: {min: null, max: null, },
				 minorGridlines: { count: 3, color: '#E6E6FA' } },
			hAxis: { minorGridlines: { count: 3, color: '#E6E6FA' },
				 viewWindow: {min: new Date(<?php echo $d1->format('Y,m,d,H,i,s') ?>),
				 	      max: new Date(<?php echo $d2->format('Y,m,d,H,i,s') ?>), } },
			height: '150',
			width:  '100%'
		};

		chart_H = new google.visualization.LineChart(
					document.getElementById('linechart_H'));

		chart_H.draw(view_H, opt_H);
	}

	function drawChart_C() {
		data_C = new google.visualization.DataTable();
		data_C.addColumn('datetime', 'Time');
		<?php
		    echo "data_C.addColumn('number', '" .$sensor_name[3]. "');".PHP_EOL;
		?>
		data_C.addRows([
			<?php echo $tbl_C; ?>
		]);

		view_C = new google.visualization.DataView(data_C);

		opt_C = { 
			title: 'Heat Source detection count',
			legend: { position: 'in' },
			vAxis: { viewWindow: {min: null, max: null, },
				 minorGridlines: { count: 3, color: '#E6E6FA' } },
			hAxis: { minorGridlines: { count: 3, color: '#E6E6FA' },
				 viewWindow: {min: new Date(<?php echo $d1->format('Y,m,d,H,i,s') ?>),
				 	      max: new Date(<?php echo $d2->format('Y,m,d,H,i,s') ?>), } },
			height: '150',
			width:  '100%'
		};

		chart_C = new google.visualization.LineChart(
					document.getElementById('linechart_C'));

		chart_C.draw(view_C, opt_C);
	}

	function changeColumn(_sel, _chart, _view, _opt) {
		var setC = new Array();
		setC[0] = 0;
		for (var i = 0; i < _sel.length; i++) {
			if (_sel[i].selected) setC.push( i+1 );
		}
		_view.setColumns(setC);
		_chart.draw(_view, _opt);
	}

	function changeScale(mode, _chart, _view, _opt) {
		if (mode == 'fixed') {
		    if (_chart == chart_T) {
			_opt['vAxis']['viewWindow']['min'] = 0;
			_opt['vAxis']['viewWindow']['max'] = 48;
		    }
		    else if (_chart == chart_P) {
			_opt['vAxis']['viewWindow']['min'] = 970;
			_opt['vAxis']['viewWindow']['max'] = 1020;
		    }
		    else if (_chart == chart_H) {
			_opt['vAxis']['viewWindow']['min'] = 0;
			_opt['vAxis']['viewWindow']['max'] = 100;
		    }
		    else {
			_opt['vAxis']['viewWindow']['min'] = null;
			_opt['vAxis']['viewWindow']['max'] = null;
		    }
		}
		else {
			_opt['vAxis']['viewWindow']['min'] = null;
			_opt['vAxis']['viewWindow']['max'] = null;
		}
		_chart.draw(_view, _opt);
	}

	function changeSize(mode, _chart, _view, _opt) {
		if (mode == 'small') {
			_opt['height'] = 150;
		}
		else if (mode == 'medium') {
			_opt['height'] = 350;
		}
		else {
			_opt['height'] = 700;
		}
		_chart.draw(_view, _opt);
	}


	function goto_other_page(page, date, span) {
		if (page == '_Home') {
			nexturl = "index.html";
		}
		else {
			nexturl = page+"?_start="+date+"&_span="+span;
		}
		location.href = nexturl;
	}

	</script>

	<style type="text/css">
	    span._static {
		padding-left:12pt;
	    }
	    .header_item {
		height: 18pt;
	    }
	</style>

</head>

<!--
/////////////////////////////////////////////////////////////////////////////////////////
//	BODY
/////////////////////////////////////////////////////////////////////////////////////////
-->
<body onresize="chart_T.draw(view_T, opt_T);chart_H.draw(view_H, opt_H);chart_C.draw(view_C, opt_C);">
	<h2>Node Emily (<?php 
	$date = $d1->format('Y/m/d');
	if ($_span > 1) $date .= " - ".$d2->format('Y/m/d');
	echo $date;
	?>)</h2>

	<!--
	/////////////////////////////////////////////////////////////////////////////////////////
	//	header form
	/////////////////////////////////////////////////////////////////////////////////////////
	-->
	<form name="HeaderForm">
	<table>
	<tr>
	  <td>
	    <select name='dispatch_page' class='header_item' id='dispatch_page' onchange='set_submit_target(this.value)'>
	      <option value='primary_sensors.php'>Primary sensors</option>
	      <option value='node_emily.php' selected >Node Emily</option>
	      <option value='s_temp.php'>Temperature Sensors</option>
	      <option value='s_ilm.php'>Illminance Sensors</option>
	      <option value='temp_barometer.php'>(old) temp & barometer</option>
	    </select>
	  </td>
	  <td width=100>
	      <input type='date' class='header_item' name='Goto_Date' value='<?php
	    	echo $d1->format('Y-m-d') ?>'>
	  </td>
	  <td>
	    <span style="padding-right: 25pt">
	      <button type = "button" class='header_item' onclick="goto_other_page(form.dispatch_page.value, form.Goto_Date.value, form.Span.value)">
	      GOTO
	      </button>
	    </span>
	  </td>
	  <td>
	    <button type = "button" class='header_item' onclick="goto_other_page(form.dispatch_page.value, '<?php
	    	$d3 = clone $d1;
	    	$d3->sub(new DateInterval('P01D'));
	    	echo $d3->format('Y-m-d H:i');  ?>', form.Span.value)" >Previous day</button>
	  </td>
	  <td>
	    <select class='header_item' name="Span">
	      <option value='1'<?php
	      	if ($_span == 1) echo " selected";
	      	?>>1 day</option>
	      <option value='2'<?php
	      	if ($_span == 2) echo " selected";
	      	?>>2 days</option>
	      <option value='3'<?php
	      	if ($_span == 3) echo " selected";
	      	?>>3 days</option>
	    </select>
	  </td>
	  <td>
	    <button type = "button" class='header_item' onclick="goto_other_page(form.dispatch_page.value, '<?php
	      $d3 = clone $d1;
	      $d3->add(new DateInterval('P01D'));
	      echo $d3->format('Y-m-d H:i');  ?>', form.Span.value)" >next day</button>
	  </td>
  	  <td>
	    <span style="padding-left: 30pt">
	    <A href="index.html">home</A>
	    </span>
	  </td>
	</tr>
	</table>
	</form>

	<!--
	/////////////////////////////////////////////////////////////////////////////////////////
	//	form T
	/////////////////////////////////////////////////////////////////////////////////////////
	-->
	<form name="form_T" style="padding-left:2px; border:1px solid gray; background-color: buttonface;">
	  <span class="_static">Sensor :</span>
	  <select name="sensor_T" onchange="changeColumn(this, chart_T, view_T, opt_T)">
	    <?php
		echo "<option value='".$sensor_name[0]."' selected>".$sensor_name[0]."</option>";
		echo "<option value='".$sensor_name[1]."' >".$sensor_name[1]."</option>";
	     ?>
	  </select>
	  <span class="_static">Scaling :</span>
	  <select name="scale_T" onchange="changeScale(this.value, chart_T, view_T, opt_T)">
	    <option value="auto" selected>AUTO</option>
	    <option value="fixed">FIXED</option>
	  </select>
	  <span class="_static">Size :</span>
	  <select name="size_T" onchange="changeSize(this.value, chart_T, view_T, opt_T)">
	    <option value="small" selected >small</option>
	    <option value="medium">medium</option>
	    <option value="large">large</option>
	  </select>
	</form>

	<div id="linechart_T"></div>

	<!--
	/////////////////////////////////////////////////////////////////////////////////////////
	//	form H
	/////////////////////////////////////////////////////////////////////////////////////////
	-->
	<form name="form_H" style="padding-left:2px; border:1px solid gray; background-color: buttonface;">
	  <span class="_static">Sensor :</span>
  	  <select name="sensor_H" onchange="changeColumn(this, chart_H, view_H, opt_H)">
	    <?php
		echo "<option value='".$sensor_name[2]."' selected>".$sensor_name[2]."</option>";
	     ?>
	  </select>
	  <span class="_static">Scaling :</span>
	  <select name="scale_H" onchange="changeScale(this.value, chart_H, view_H, opt_H)">
	    <option value="auto" selected>AUTO</option>
	    <option value="fixed">FIXED</option>
	  </select>
	  <span class="_static">Size :</span>
	  <select name="size_H" onchange="changeSize(this.value, chart_H, view_H, opt_H)">
	    <option value="small" selected >small</option>
	    <option value="medium">medium</option>
	    <option value="large">large</option>
	  </select>
	</form>

	<div id="linechart_H"></div>
	<hr>
	<!--
	/////////////////////////////////////////////////////////////////////////////////////////
	//	form C
	/////////////////////////////////////////////////////////////////////////////////////////
	-->
	<form name="form_C" style="padding-left:2px; border:1px solid gray; background-color: buttonface;">
	  <span class="_static">Sensor :</span>
	  <select name="sensor_C" onchange="changeColumn(this, chart_C, view_C, opt_C)">
	    <?php
		echo "<option value='".$sensor_name[3]."' selected>".$sensor_name[3]."</option>";
	     ?>
	  </select>
	  <span class="_static">Size :</span>
	  <select name="size_C" onchange="changeSize(this.value, chart_C, view_C, opt_C)">
	    <option value="small" selected >small</option>
	    <option value="medium">medium</option>
	    <option value="large">large</option>
	  </select>
	</form>

	<div id="linechart_C"></div>
	<hr>
	<!--
	/////////////////////////////////////////////////////////////////////////////////////////
	//	Summary table
	/////////////////////////////////////////////////////////////////////////////////////////
	-->
	//////////////////////////
	<?php echo "<h3>*** Record of the day (".$date.") ***</h3>\n"; ?>
	<table border>
	<thead>
	<tr>
	<th width=170>temperature</th>
	<th width=100>min</th>
	<th width=100>average</th>
	<th width=100>max</th>
	</tr>
	</thead>
	<tbody>
	<?php
	  echo '<tr><td>'.$sensor_name[0].'</td><td>';
	  printf("%7.2f", $e_min[$sensor_name[0]]);
	  echo '</td><td>';
	  printf("%7.2f", $e_sum[$sensor_name[0]] / $e_cnt[$sensor_name[0]]);
	  echo '</td><td>';
	  printf("%7.2f", $e_max[$sensor_name[0]]);
	  echo '</td></tr>'.PHP_EOL;
	  echo '<tr><td>'.$sensor_name[1].'</td><td>';
	  printf("%7.2f", $e_min[$sensor_name[1]]);
	  echo '</td><td>';
	  printf("%7.2f", $e_sum[$sensor_name[1]] / $e_cnt[$sensor_name[1]]);
	  echo '</td><td>';
	  printf("%7.2f", $e_max[$sensor_name[1]]);
	  echo '</td></tr>'.PHP_EOL;
	?>
	</tbody>
	</table>
	<br>

	<table border>
	<thead>
	<tr>
	<th width=170> Humidity </span> </th>
	<th width=100> min </span> </th>
	<th width=100> average </span> </th>
	<th width=100> max </span> </th>
	</tr>
	</thead>
	<tbody>
	<?php
	  echo '<tr><td>'.$sensor_name[2].'</td><td>';
	  printf("%7.2f", $e_min[$sensor_name[2]]);
	  echo '</td><td>';
	  printf("%7.2f", $e_sum[$sensor_name[2]] / $e_cnt[$sensor_name[2]]);
	  echo '</td><td>';
	  printf("%7.2f", $e_max[$sensor_name[2]]);
	  echo '</td></tr>'.PHP_EOL;
	?>
	</tbody>
	</table>
	<br>

	<table border>
	<thead>
	<tr>
	<th width=170> counter </span> </th>
	<th width=100> min </span> </th>
	<th width=100> average </span> </th>
	<th width=100> max </span> </th>
	</tr>
	</thead>
	<tbody>
	<?php
	  echo '<tr><td>'.$sensor_name[3].'</td><td>';
	  printf("%5.0f", $e_min[$sensor_name[3]]);
	  echo '</td><td>';
	  printf("%6.1f", $e_sum[$sensor_name[3]] / $e_cnt[$sensor_name[3]]);
	  echo '</td><td>';
	  printf("%5.0f", $e_max[$sensor_name[3]]);
	  echo '</td></tr>'.PHP_EOL;
	?>
	</tbody>
	</table>
	//////////////////////////
</body>
</html>

