<html lang="ja">
<head>
<meta charset="UTF-8">
<title>POST test No.2</title>
</head>
<body>

<h1>Data Post test</h1>

<?php
//
//  複数のセンサーデータを配列として受信するテスト
//
//require_once 'google/appengine/api/cloud_storage/CloudStorageTools.php';
//use google\appengine\api\cloud_storage\CloudStorageTools;
//$option = [ 'gs_bucket_name' => $def_bucket ];
//$uploadUrl = CloudStorageTools::createUploadUrl('/postTarget', []);
$uploadUrl = "'postData'";
?>
  
<div>
<form method="POST" action=<?php echo $uploadUrl ?> enctype="multipart/form-data">
  ADT7410-01<input type="text" name="sensData[T-ADT7410-01]"><br>
  BMP180-01(barometor)<input type="text" name="sensData[P-BMP180-01]"><br>
  BMP180-01(temperature)<input type="text" name="sensData[T-BMP180-01]"><br>
  TSL2561v-01<input type="text" name="sensData[P-TSL2561v-01]"><br>
  TSL2561f-01<input type="text" name="sensData[P-TSL2561f-01]"><br>
  TSL2561ir-01<input type="text" name="sensData[P-TSL2561ir-01]"><br>
  CPU(mavis)<input type="text" name="sensData[T-cpu-mavis]"><br>
  <input type="text" name="sensData[timestamp]" value="<?php echo date(DATE_ATOM) ?>"><br>
  <input type="submit" name="btn" value="送信">
</form>
</div>

</body>
</html>

