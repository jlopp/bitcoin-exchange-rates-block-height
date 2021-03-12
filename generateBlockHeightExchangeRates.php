<?php

require 'vendor/autoload.php';

use Denpa\Bitcoin\Client as BitcoinClient;

$bitcoind = new BitcoinClient('http://username:password@localhost:8332/');
$startHeight = $height = 0;
$maxBlockHeight = $bitcoind->getBlockchaininfo()->get('blocks') - 10;
$heightRange = $maxBlockHeight - $height;

$blockHeightExchangeRates = array();

function getMedianTimestamp($timestamps) {
	if (count($timestamps) != 11) {
		echo "ERROR: neighborBlockTimes does not have 11 values\n";
		print_r($timestamps);
		exit;
	}
	$values = array_values($timestamps);
	sort($values);
	return $values[5];
}

// Move the sliding window up by one block
function updateNeighborBlockTimes(&$neighborBlockTimes, $height) {
	global $bitcoind;
	unset($neighborBlockTimes[$height - 6]);
	$blockHash = $bitcoind->getBlockhash($height + 5)->get();
	$block = $bitcoind->getBlockheader($blockHash);
	$neighborBlockTimes[$height + 5] = $block->get('time');
}

// No exchange rate established during this time period
while ($height < 24365) {
	$blockHeightExchangeRates[$height] = 0;
	$height++;
}

// Maintain an array of surrounding block times to calculate median time
$neighborBlockTimes = array(
							24360 => 1254691722,
							24361 => 1254694038,
							24362 => 1254697748,
							24363 => 1254698394,
							24364 => 1254702114,
							24365 => 1254708044,
							24366 => 1254708402,
							24367 => 1254708449,
							24368 => 1254708212,
							24369 => 1254709630,
							24370 => 1254710087
						);
$nextExchangeRateTimestamp = 1254694038;
$currentExchangeRate = $nextExchangeRate = 0;

// Ingest the New Liberty Standard history
$handle = fopen("data/new_liberty_standard_history.csv", "r");
if ($handle == FALSE) {
	echo "Failed to open new liberty standard history CSV\n";
	exit;
}

while ($height < 52903) {
	updateNeighborBlockTimes($neighborBlockTimes, $height);
	$currentMedianTimestamp = getMedianTimestamp($neighborBlockTimes);

	// we need to scan forward in the exchange rate history to find the next close match
	while ($currentMedianTimestamp >= $nextExchangeRateTimestamp) { 
		if (($data = fgetcsv($handle, 100, ",")) !== FALSE) {
			$currentExchangeRate = $nextExchangeRate;
			$nextExchangeRateTimestamp = $data[0];
			$nextExchangeRate = number_format((float)$data[1], 8, '.', '');
		} else { // no more available data in this CSV file
			break;
		}
	}

	// use the exchange rate from the current position in the exchange rate history file
	$blockHeightExchangeRates[$height] = $currentExchangeRate;

	$height++;

	if ($height % 1000 == 0) {
		$complete = round(100*(($height - $startHeight) / $heightRange),2);
		echo "$complete%\n";
	}
}
fclose($handle);

// Ingest the bitcoinmarket history
$bitcoinMarketExchangeRates = array();
$handle = fopen("data/bitcoinmarket_history.csv", "r");
if ($handle == FALSE) {
	echo "Failed to open bitcoinmarket history CSV\n";
	exit;
}
// Bitcoin Market exchange rates
while ($height < 71437) {
	updateNeighborBlockTimes($neighborBlockTimes, $height);
	$currentMedianTimestamp = getMedianTimestamp($neighborBlockTimes);

	// we need to scan forward in the exchange rate history to find the next close match
	while ($currentMedianTimestamp >= $nextExchangeRateTimestamp) { 
		if (($data = fgetcsv($handle, 100, ",")) !== FALSE) {
			$currentExchangeRate = $nextExchangeRate;
			$nextExchangeRateTimestamp = $data[0];
			$nextExchangeRate = number_format((float)$data[1], 4, '.', '');
		} else { // no more available data in this CSV file
			break;
		}
	}

	// use the exchange rate from the current position in the exchange rate history file
	$blockHeightExchangeRates[$height] = $currentExchangeRate;

	$height++;

	if ($height % 1000 == 0) {
		$complete = round(100*(($height - $startHeight) / $heightRange),2);
		echo "$complete%\n";
	}
}
fclose($handle);

// Check to see if we have the MTGOX history available locally
if (!file_exists("data/mtgox_history.csv")) {
	$ch = curl_init("https://api.bitcoincharts.com/v1/csv/inactive_exchanges/mtgoxUSD.csv.gz");
	$fp = fopen("data/mtgox_history.csv.gz", "w");

	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

	curl_exec($ch);
	if (curl_error($ch)) {
	    fwrite($fp, curl_error($ch));
	    echo "Failed to download MTGOX data set\n";
	    exit;
	}
	curl_close($ch);
	fclose($fp);

	// decompress the file
	$file = gzopen("data/mtgox_history.csv.gz", 'rb');
	$out_file = fopen("data/mtgox_history.csv", 'wb'); 

	while (!gzeof($file)) {
		fwrite($out_file, gzread($file, 4096));
	}

	fclose($out_file);
	gzclose($file);
}

$mtgoxExchangeRates = array();
$handle = fopen("data/mtgox_history.csv", "r");
if ($handle == FALSE) {
	echo "Failed to MTGOX exchange rate history CSV\n";
	exit;
}
while ($height < 234002) {
	updateNeighborBlockTimes($neighborBlockTimes, $height);
	$currentMedianTimestamp = getMedianTimestamp($neighborBlockTimes);

	// we need to scan forward in the exchange rate history to find the next close match
	while ($currentMedianTimestamp >= $nextExchangeRateTimestamp) { 
		if (($data = fgetcsv($handle, 100, ",")) !== FALSE) {
			$currentExchangeRate = $nextExchangeRate;
			$nextExchangeRateTimestamp = $data[0];
			$nextExchangeRate = number_format((float)$data[1], 2, '.', '');
		} else { // no more available data in this CSV file
			break;
		}
	}

	// use the exchange rate from the current position in the exchange rate history file
	$blockHeightExchangeRates[$height] = $currentExchangeRate;

	$height++;

	if ($height % 1000 == 0) {
		$complete = round(100*(($height - $startHeight) / $heightRange),2);
		echo "$complete%\n";
	}
}
fclose($handle);

// Check to see if we have recent Bitstamp history available locally
if (!file_exists("data/bitstamp_history.csv")) {
	$ch = curl_init("https://api.bitcoincharts.com/v1/csv/bitstampUSD.csv.gz");
	$fp = fopen("data/bitstamp_history.csv.gz", "w");

	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

	curl_exec($ch);
	if (curl_error($ch)) {
	    fwrite($fp, curl_error($ch));
	    echo "Failed to download Bitstamp data set\n";
	    exit;
	}
	curl_close($ch);
	fclose($fp);

	// decompress the file
	$file = gzopen("data/bitstamp_history.csv.gz", 'rb');
	$out_file = fopen("data/bitstamp_history.csv", 'wb'); 

	while (!gzeof($file)) {
		fwrite($out_file, gzread($file, 4096));
	}

	fclose($out_file);
	gzclose($file);
}

$bitstampExchangeRates = array();
$handle = fopen("data/bitstamp_history.csv", "r");
if ($handle == FALSE) {
	echo "Failed to Bitstamp exchange rate history CSV\n";
	exit;
}
while ($height < $maxBlockHeight) {
	updateNeighborBlockTimes($neighborBlockTimes, $height);
	$currentMedianTimestamp = getMedianTimestamp($neighborBlockTimes);

	// we need to scan forward in the exchange rate history to find the next close match
	while ($currentMedianTimestamp >= $nextExchangeRateTimestamp) { 
		if (($data = fgetcsv($handle, 100, ",")) !== FALSE) {
			$currentExchangeRate = $nextExchangeRate;
			$nextExchangeRateTimestamp = $data[0];
			$nextExchangeRate = number_format((float)$data[1], 2, '.', '');
		} else { // no more available data in this CSV file
			break 2;
		}
	}

	// use the exchange rate from the current position in the exchange rate history file
	$blockHeightExchangeRates[$height] = $currentExchangeRate;

	$height++;

	if ($height % 1000 == 0) {
		$complete = round(100*(($height - $startHeight) / $heightRange),2);
		echo "$complete%\n";
	}
}
fclose($handle);


// sort by block height ascending
ksort($blockHeightExchangeRates);

echo "Block Height,Exchange Rate\n";
foreach ($blockHeightExchangeRates as $height => $rate) {
	echo "$height,$rate\n";
}