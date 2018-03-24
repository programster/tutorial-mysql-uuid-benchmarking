<?php

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/settings.php');

date_default_timezone_set('UTC');


/**
 * 
 * @param float $percentage - the percentage completed.
 * @param int $numDecimalPlaces - the number of decimal places to show for percentage output string
 */
function showProgressBar($percentage, int $numDecimalPlaces)
{
    $percentageStringLength = 4;
    if ($numDecimalPlaces > 0)
    {
        $percentageStringLength += ($numDecimalPlaces + 1);
    }
    
    $percentageString = number_format($percentage, $numDecimalPlaces) . '%';
    $percentageString = str_pad($percentageString, $percentageStringLength, " ", STR_PAD_LEFT);
    
    $percentageStringLength += 3; // add 2 for () and a space before bar starts.

    $terminalWidth = `tput cols`;
    $barWidth = $terminalWidth - ($percentageStringLength) - 2; // subtract 2 for [] around bar
    $numBars = round(($percentage) / 100 * ($barWidth));
    $numEmptyBars = $barWidth - $numBars;
    
    $barsString = '[' . str_repeat("=", ($numBars)) . str_repeat(" ", ($numEmptyBars)) . ']';

    echo "($percentageString) " . $barsString . "\r";
}




function runner($testName, $createTableQuery, callable $logCreator, mysqli $db, int $numLogsToInsert)
{
    $timeTestStart = microtime(true);
    $logFilepath = __DIR__ . "/results/{$testName}.csv";
    file_put_contents($logFilepath, '');

    $dropTableQuery = "DROP TABLE IF EXISTS `log`";
    $db->query($dropTableQuery) or die("Failed to drop log table if exists");
    $db->query($createTableQuery) or die("Failed to create the log table " . $db->error);

    $logs = array();
    print  "{$testName} inserting logs: " . PHP_EOL;
    
    for ($i=0; $i<$numLogsToInsert; $i++)
    {
        $logs[] = $logCreator($i);

        if (count($logs) % BATCH_SIZE == 0)
        {
            $batchInsertQuery = iRAP\CoreLibs\MysqliLib::generateBatchInsertQuery($logs, 'log', $db);
            
            $start = microtime(TRUE);
            $db->query($batchInsertQuery);
            $end = microtime(TRUE);
            
            $insertTimeTaken = $end - $start;
            file_put_contents($logFilepath, $insertTimeTaken . PHP_EOL, FILE_APPEND);
            $logs = array();
            $percentage = $i / $numLogsToInsert * 100;
            showProgressBar($percentage, 0);
        }
    }
    
    print "" . PHP_EOL;
    $timeTestEnd = microtime(true);
    return $timeTestEnd - $timeTestStart;
}

function generateUuidType4() : string
{
    static $factory = null;

    if ($factory == null)
    {
        $factory = new \Ramsey\Uuid\UuidFactory();
    }
    
    \Ramsey\Uuid\Uuid::setFactory($factory);
    return \Ramsey\Uuid\Uuid::uuid4()->toString();
}


function generateUuidType4Sequential() : string
{
    static $factory = null;

    if ($factory == null)
    {
        $factory = new \Ramsey\Uuid\UuidFactory();

        $generator = new \Ramsey\Uuid\Generator\CombGenerator(
            $factory->getRandomGenerator(), 
            $factory->getNumberConverter()
        );

        $codec = new \Ramsey\Uuid\Codec\TimestampFirstCombCodec($factory->getUuidBuilder());

        $factory->setRandomGenerator($generator);
        $factory->setCodec($codec);
    }

    \Ramsey\Uuid\Uuid::setFactory($factory);
    $uuidString = \Ramsey\Uuid\Uuid::uuid4()->toString();
    return $uuidString;
}


function generateUuidType1() : string
{
    $uuidString = \Ramsey\Uuid\Uuid::uuid1()->toString();
    return $uuidString;
}




function main()
{
    $db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    $numLogsToInsert = ONE_MILLION * 5;
    $results = array();
    
    $createIntTableQuery = 
        "CREATE TABLE `log` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `message` varchar(255) NOT NULL,
            `when` int unsigned NOT NULL,
            PRIMARY KEY (`id`),
            INDEX (`when`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        
    $intBasedLogCreator = function(int $i){
        $startTime = 1200000000;
        $logInterval = 60; // 60 seconds between logs;
        $logTime = $startTime + ($logInterval * $i) - rand(0, 1000);
        
        return array(
            'message' => 'hello world',
            'when' => $logTime,
        );
    };
    
    $testName = "using-auto-increment";
    $results[$testName] = runner($testName, $createIntTableQuery, $intBasedLogCreator, $db, $numLogsToInsert);
    
    
    $uuidLogType4RandomGenerator = function(int $i){
        $startTime = 1200000000;
        $logInterval = 60; // 60 seconds between logs;
        $logTime = $startTime + ($logInterval * $i) - rand(0, 1000);
        
        return array(
            'uuid' => generateUuidType4(),
            'message' => 'hello world',
            'when' => $logTime,
        );
    };
    
    $uuidLogType4SequentialGenerator = function(int $i){
        $startTime = 1200000000;
        $logInterval = 60; // 60 seconds between logs;
        $logTime = $startTime + ($logInterval * $i) - rand(0, 1000);
        
        return array(
            'uuid' => generateUuidType4Sequential(),
            'message' => 'hello world',
            'when' => $logTime,
        );
    };
    
    $createUuidTableQuery = 
        "CREATE TABLE `log` (
            `uuid` binary(16) NOT NULL,
            `message` varchar(255) NOT NULL,
            `when` int unsigned NOT NULL,
            PRIMARY KEY (`uuid`),
            INDEX (`when`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    
    $testName = "uuid-test-type-4-sequential";
    $results[$testName] = runner($testName, $createUuidTableQuery, $uuidLogType4SequentialGenerator, $db, $numLogsToInsert);
    
    $testName = "uuid-test-type-4-random";
    $results[$testName] = runner($testName, $createUuidTableQuery, $uuidLogType4RandomGenerator, $db, $numLogsToInsert);
    
    
    $uuidType1LogGenerator = function(int $i){
        $startTime = 1200000000;
        $logInterval = 60; // 60 seconds between logs;
        $logTime = $startTime + ($logInterval * $i) - rand(0, 1000);
        
        return array(
            'uuid' => generateUuidType1(),
            'message' => 'hello world',
            'when' => $logTime,
        );
    };
    
    $testName = "uuid-test-type-1";
    $results[$testName] = runner($testName, $createUuidTableQuery, $uuidType1LogGenerator, $db, $numLogsToInsert);
    
    
    # Write the test durations to a file.
    $resultsFileHandle = fopen(__DIR__ . '/results/test-results.csv', 'w');
    
    foreach ($results as $testName => $timeTaken) 
    {
        $row = array($testName, $timeTaken);
        fputcsv($resultsFileHandle, $row);
    }
    
    fclose($resultsFileHandle);
}

main();
