<?php

namespace Oara\Network\Publisher;
/**
 * The goal of the Open Affiliate Report Aggregator (OARA) is to develop a set
 * of PHP classes that can download affiliate reports from a number of affiliate networks, and store the data in a common format.
 *
 * Copyright (C) 2016  Fubra Limited
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Contact
 * ------------
 * Fubra Limited <support@fubra.com> , +44 (0)1252 367 200
 **/

/**
 * Export Class
 *
 * @author     Gert-Jaap Hertgers
 * @category   Financeads
 * @copyright  Reviews International
 * @version    Release: 01.00
 *
 */
class Financeads extends \Oara\Network
{

	private $_credentials = null;

	/**
	 * @param $credentials
	 */
	public function login($credentials)
	{
		$this->_credentials = $credentials;
	}

	/**
	 * Check the connection
	 */
	public function checkConnection()
	{
		//If not login properly the construct launch an exception
		$connection = true;

		try {
			$key = $this->_credentials['key'];

			$url = "https://data.financeads.net/api/statistics.php?site=pr&user=19516&key=".$key;
			// initialize curl resource
			$ch = curl_init();
			// set curl options
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			// execute curl
			$response = curl_exec($ch);

            $csv = $this->import_csv_to_array($response);

			if (!count($csv)) return false;

		} catch (\Exception $e) {
			$connection = false;
		}
		return $connection;
	}

	/**
	 * @return array
	 */
	public function getNeededCredentials()
	{
		$credentials = array();

        $parameter = array();
        $parameter["description"] = "API key";
        $parameter["required"] = true;
        $parameter["name"] = "Key";
        $credentials["key"] = $parameter;

        $parameter = array();
        $parameter["description"] = "Site ID";
        $parameter["required"] = true;
        $parameter["name"] = "idSite";
        $credentials["idSite"] = $parameter;

		return $credentials;
	}

	/**
	 * (non-PHPdoc)
	 * @see library/Oara/Network/Interface#getMerchantList()
	 */
	public function getMerchantList()
	{
		$merchants = array();

        $key = $this->_credentials['key'];
		$id_site = $this->_credentials['idSite'];      // publisher id to retrieve (empty = all publishers)

        $url = "https://data.financeads.net/api/statistics.php?site=pr&user=19516&key=".$key."&w=".$id_site;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        $list = $this->import_csv_to_array($response);
        foreach ($list as $transaction) {
            $obj = Array();
            $obj['cid'] = $transaction['prid'];
            $obj['name'] = $transaction['Programm'];
            $merchants[] = $obj;
        }
		return $merchants;
	}

	/**
	 * @param null $merchantList array of merchants id to retrieve transactions (empty array or null = all merchants)
	 * @param \DateTime|null $dStartDate
	 * @param \DateTime|null $dEndDate
	 * @return array
	 * @throws \Exception
	 */
	public function getTransactionList($merchantList = null, \DateTime $dStartDate = null, \DateTime $dEndDate = null)
	{
        $key = $this->_credentials['key'];
        $id_site = $this->_credentials['idSite'];      // publisher id to retrieve (empty = all publishers)

        $cacheFile = getcwd() . '/data/cache/stats/'.$_SERVER['HTTP_HOST'].'_financeadsRAW_2014-01-01_'.date('Y-m-d').'.txt';
        if (!isset($_GET['refresh']) && is_file($cacheFile)) {
            $response = unserialize(file_get_contents($cacheFile));
        } else {
            $url = "https://data.financeads.net/api/statistics.php?site=l_all&user=19516&key=" . $key . "&w=" . $id_site . "&time_from=2014-01-01&time_to=" . date('Y-m-d');
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            file_put_contents($cacheFile, serialize($response));
        }

        $totalTransactions = array();
        $transactions = $this->import_csv_to_array($response);
        foreach ($transactions as $transaction) {
            if (strtotime($transaction['l_datum']) < $dStartDate->getTimestamp() || strtotime($transaction['l_datum']) > $dEndDate->getTimestamp()) {
                continue;
            }
            $transactionArray = Array();
            $transactionArray['unique_id'] = $transaction['l_id'];
            $transactionArray['merchantId'] = $transaction['l_prid'];
            $id = array_search($transaction['l_prid'], array_column($merchantList, 'cid'));
            if (isset($merchantList[$id]['name'])) {
                $transactionArray['merchantName'] = $merchantList[$id]['name'];
            } else {
                $transactionArray['merchantName'] = 'Unknown merchant';
            }
            try {
                $transactionDate = new \DateTime($transaction['l_datum']);
                $transactionArray['date'] = $transactionDate->format("Y-m-d H:i:s");
            } catch (\Exception $e) {}
            try {
                $transactionDateClick = new \DateTime($transaction['klick_timestamp']);
                $transactionArray['click_date'] = $transactionDateClick->format("Y-m-d H:i:s");
            } catch (\Exception $e) {}
            try {
                $transactionDateUpdate = new \DateTime($transaction['l_datum_eintrag']);
                $transactionArray['update_date'] = $transactionDateUpdate->format("Y-m-d H:i:s");
            } catch (\Exception $e) {}

            if ($transaction['l_status'] == '2') {
                $transactionArray['status'] = \Oara\Utilities::STATUS_CONFIRMED;
            } elseif ($transaction['l_status'] == '1') {
                $transactionArray['status'] = \Oara\Utilities::STATUS_PENDING;
            } elseif ($transaction['l_status'] == '0') {
                $transactionArray['status'] = \Oara\Utilities::STATUS_DECLINED;
            } else {
                throw new \Exception("Unexpected transaction status {$transaction['status']}");
            }
            $transactionArray['currency'] = $transaction['l_currency'];
            $transactionArray['amount'] = \Oara\Utilities::parseDouble($transaction['l_value']);
            $transactionArray['commission'] = \Oara\Utilities::parseDouble($transaction['l_provision']);
            $transactionArray['IP'] = '';

            $totalTransactions[] = $transactionArray;
        }

        return $totalTransactions;

	}

	/**
	 * See: https://developers.daisycon.com/api/resources/publisher-resources/
	 * @return array
	 */
	public function getVouchers()
	{

		return [];
	}


    private function import_csv_to_array($csv_string,$enclosure = '"')
    {
        $delimiter = $this->detect_delimiter($csv_string);
        $lines = explode("\n", $csv_string);
        $head = str_getcsv(array_shift($lines),$delimiter,$enclosure);
        $array = array();
        foreach ($lines as $line) {
            if(empty($line)) {
                continue;
            }
            $csv = str_getcsv($line,$delimiter,$enclosure);
            $array[] = array_combine( $head, $csv );
        }
        return $array;
    }

    private function detect_delimiter($csv_string)
    {
        $delimiters = array(';' => 0,',' => 0,"\t" => 0,"|" => 0);
        // For every delimiter, we count the number of time it can be found within the csv string
        foreach ($delimiters as $delimiter => &$count) {
            $count = substr_count($csv_string,$delimiter);
        }
        // The delimiter used is probably the one that has the more occurrence in the file
        return array_search(max($delimiters), $delimiters);
    }

}
