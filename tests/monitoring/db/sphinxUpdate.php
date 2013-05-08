<?php
require_once realpath(__DIR__ . '/../../') . '/lib/KalturaEnums.php';
require_once realpath(__DIR__ . '/../') . '/KalturaMonitorResult.php';

$options = getopt('', array(
	'debug',
	'sphinx-index:',
	'timeout:',
));

if(!isset($options['sphinx-index']))
{
	echo "Argument sphinx-index is required";
	exit(-1);
}
$sphinxIndex = $options['sphinx-index'];

if(!isset($options['timeout']))
{
	echo "Argument timeout is required";
	exit(-1);
}
$timeout = $options['timeout'];


// start
$start = microtime(true);
$monitorResult = new KalturaMonitorResult();

$config = parse_ini_file(__DIR__ . '/../config.ini', true);
try
{
	// connect to the db
	$logPdo = new PDO($config['sphinx-log']['dsn'], $config['sphinx-log']['username'], $config['sphinx-log']['password']);
	
	$updatedEntryId = uniqid();
	
	// insert or update sphinx log
	$replaceQuery = "replace into kaltura_entry (id, entry_id, str_entry_id) values(1, \'MONITOR_TEST\', \'$updatedEntryId\')";
	$insertLogQuery = "INSERT INTO sphinx_log (object_type, object_id, partner_id, dc, `sql`, created_at) VALUES ('entry', '$updatedEntryId', -4, 0, '$replaceQuery', NOW())";
	$affectedRows = $logPdo->exec($insertLogQuery);
	if(!$affectedRows)
		throw new Exception("Unable to insert log: $insertLogQuery");
		
	$timeoutTime = time() + $timeout;
	$sphinxPdo = new PDO($config["sphinx-$sphinxIndex"]['dsn']);
	$selectQuery = "select str_entry_id from kaltura_entry where id = 1";
	$selectedEntryId = null;
	
	while($selectedEntryId != $updatedEntryId && $timeoutTime > time())
	{
		$selectStatement = $sphinxPdo->query($selectQuery);
		if($selectStatement === false)
			throw new Exception("Query failed: $selectQuery");
		$selectedEntryId = $selectStatement->fetchColumn(2);
		usleep(100);
	}
	
	$end = microtime(true);
	$monitorResult->executionTime = $end - $start;
		
	if($selectedEntryId == $updatedEntryId)
	{
		$monitorResult->value = 1;
		$monitorResult->description = "Shpinx populated from log";
	}
	else
	{
		$error = new KalturaMonitorError();
		$error->description = "Sphinx not populated from log";
		$error->level = KalturaMonitorError::CRIT;
		
		$monitorResult->errors[] = $error;
		$monitorResult->value = 0;
		$monitorResult->description = "Sphinx not populated from log";
	}
	
	echo "$monitorResult";
	exit(0);
}
catch(PDOException $pdoe)
{
	$end = microtime(true);
	$monitorResult->executionTime = $end - $start;
	
	$error = new KalturaMonitorError();
	$error->code = $pdoe->getCode();
	$error->description = $pdoe->getMessage();
	$error->level = KalturaMonitorError::CRIT;
	
	$monitorResult->errors[] = $error;
	$monitorResult->description = get_class($pdoe) . ": " . $pdoe->getMessage();
	
	echo "$monitorResult";
	exit(0);
}
catch(Exception $e)
{
	$end = microtime(true);
	$monitorResult->executionTime = $end - $start;
	
	$error = new KalturaMonitorError();
	$error->code = $e->getCode();
	$error->description = $e->getMessage();
	$error->level = KalturaMonitorError::ERR;
	
	$monitorResult->errors[] = $error;
	$monitorResult->description = $e->getMessage();
	
	echo "$monitorResult";
	exit(0);
}