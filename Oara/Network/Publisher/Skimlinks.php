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
 * @author     Carlos Morillo Merino
 * @category   Skimlinks
 * @copyright  Fubra Limited
 * @version    Release: 01.00
 *
 */
class Skimlinks extends \Oara\Network
{
    private $_client = null;
    private $_adspace = null;
    private $_token = null;

    /**
     * @param $credentials
     */
    public function login($credentials)
    {
        $this->_client = new \GuzzleHttp\Client();
        $this->_adspace = $credentials['adspace'];
        if (strpos($this->_adspace, 'X')) {
            $this->_adspace = explode('X', $this->_adspace)[1];
        }

        try {
            $response = $this->_client->post('https://authentication.skimapis.com/access_token', ['json' => array('client_id' => 'd59da7072f257a80dec61e5fa45ebbb5', 'client_secret' => '2e8d79db683d8bc29eb3b4eefdebce13', 'grant_type' => 'client_credentials')]);
        } catch(\Exception $e) {
            return false;
        }
        $this->_token = json_decode($response->getBody()->getContents());
    }

    /**
     * @return array
     */
    public function getNeededCredentials()
    {
        $credentials = array();


        $parameter = array();
        $parameter["description"] = "adspace";
        $parameter["required"] = true;
        $parameter["name"] = "adspace";
        $credentials["adspace"] = $parameter;

        return $credentials;
    }

    /**
     * @return bool
     */
    public function checkConnection()
    {
        return $this->_token;
    }

    /**
     * @return array
     */
    public function getMerchantList()
    {
        $merchantList = array();

        $offset = 0;
        do {
            try {
                $response = $this->_client->get("https://merchants.skimapis.com/v4/publisher/117850/merchants?access_token=".$this->_token->access_token."&offset=".$offset."&limit=2000");
            } catch(\Exception $e) {
                return $merchantList;
            }
            $data = json_decode($response->getBody()->getContents());

            foreach ($data->merchants as $merchant) {
                $obj = array();
                $obj["name"] = $merchant->name;
                $obj["cid"] = $merchant->merchant_id;
                $obj["url"] = $merchant->domain;
                $obj["status"] = 'active';
                $merchantList[] = $obj;
            }
            $offset += 2000;
        } while ($data->has_more);

        return $merchantList;
    }

    /**
     * @param null $merchantList
     * @param \DateTime|null $dStartDate
     * @param \DateTime|null $dEndDate
     * @return array
     * @throws Exception
     */
    public function getTransactionList($merchantList = null, \DateTime $dStartDate = null, \DateTime $dEndDate = null)
    {
        $totalTransactions = [];
        $offset = 0;

        $endTime = '23%3A59%3A59';
        if ($dEndDate->format("Y-m-d") == date('Y-m-d')) {
            $endTime = '00%3A00%3A00';
        }
        do {
            try {
                $response = $this->_client->get("https://reporting.skimapis.com/publisher/117850/commission-report?access_token=".$this->_token->access_token."&start_date=".$dStartDate->format("Y-m-d")."T00%3A00%3A00&end_date=".$dEndDate->format("Y-m-d")."T".$endTime."&limit=600&domain_id=".$this->_adspace."&offset=".$offset);
            } catch(\Exception $e) {
                return $totalTransactions;
            }
            $data = json_decode($response->getBody()->getContents());

            foreach ($data->commissions as $transaction) {
                $transactionArray['unique_id'] = $transaction->commission_id;
                $transactionArray['merchantId'] = $transaction->merchant_details->id;
                $transactionArray['merchantName'] = $transaction->merchant_details->name;
                $transactionDate = new \DateTime($transaction->transaction_details->transaction_date);
                $transactionArray['date'] = $transactionDate->format("Y-m-d H:i:s");
                $transactionDateClick = new \DateTime($transaction->click_details->date);
                $transactionArray['click_date'] = $transactionDateClick->format("Y-m-d H:i:s");
                $transactionDateUpdate = new \DateTime($transaction->transaction_details->last_updated);
                $transactionArray['update_date'] = $transactionDateUpdate->format("Y-m-d H:i:s");
                if ($transaction->transaction_details->status == 'active' && $transaction->transaction_details->payment_status == 'paid') {
                    $transactionArray['status'] = \Oara\Utilities::STATUS_CONFIRMED;
                } elseif ($transaction->transaction_details->status == 'active') {
                    $transactionArray['status'] = \Oara\Utilities::STATUS_PENDING;
                } elseif ($transaction->transaction_details->status == 'cancelled') {
                    $transactionArray['status'] = \Oara\Utilities::STATUS_DECLINED;
                } else {
                    $transactionArray['status'] = \Oara\Utilities::STATUS_PENDING;
                }
                $transactionArray['currency'] = $transaction->transaction_details->basket->currency;
                $transactionArray['amount'] = \Oara\Utilities::parseDouble($transaction->transaction_details->basket->order_amount);
                $transactionArray['commission'] = \Oara\Utilities::parseDouble($transaction->transaction_details->basket->publisher_amount);

                $totalTransactions[] = $transactionArray;
            }

            $offset += 600;
        } while ($data->pagination->has_next);

        return $totalTransactions;
    }

}