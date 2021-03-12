# Bitcoin Exchange Rates by Block Height
A tool for generating a data set of bitcoin exchange rates by block height rather than date

# How it Works

This script takes historical bitcoin exchange rate data and matches it with bitcoin block timestamps to produce a CSV of the approximate exchange rate at a given block height.

There are several sources of historical data that are ingested by the script.

1. The Bitcoin exchange rate is presumed to be 0 from January 3 2009 to October 5 2009.
2. For October 5 2009 through April 24 2010, New Liberty Standard rates via http://newlibertystandard.wikifoundry.com/page/2009+Exchange+Rate
3. For April 25 2010 - July 31 2010, bitcoinmarket_history.csv courtesy of casebitcoin.com
4. For August 1 2010 - April 30 2013, MTGOX history via bitcoincharts.com
5. For May 1 2013 - present, Bitstamp history via bitcoincharts.com

# Requirements
1. A locally running Bitcoin node with RPC access enabled
2. PHP 7.2 or later
3. php-curl

# Installation

1. Install composer: https://getcomposer.org/download/
2. Install JSON-RPC client
```
	php composer.phar require denpa/php-bitcoinrpc
	php composer.phar install
```
3. Set your RPC credentials on line 7 of the generateBlockHeightExchangeRates script
4. Run the generation script
```
	php generateBlockHeightExchangeRates.php > block_height_exchange_rates.csv &
```
