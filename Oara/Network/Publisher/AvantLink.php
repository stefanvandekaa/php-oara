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
 * @category   AvantLink
 * @copyright  Fubra Limited
 * @version    Release: 01.00
 *
 */
class AvantLink extends \Oara\Network
{

    protected $_domain = 'avantlink.com';
    private $_id = null;
    private $_apikey = null;
    private $_website_id = null;
    private $_transactions = null;

    /**
     * Constructor and Login
     * @param $credentials
     * @return ShareASale
     */
    public function login($credentials)
    {
        $this->_apikey = $credentials['apikey'];
        $this->_id = $credentials['aff_id'];
        $this->_website_id = $credentials['website_id'];
    }

    /**
     * @return array
     */
    public function getNeededCredentials()
    {
        $credentials = array();

        $parameter = array();
        $parameter["description"] = "api_key";
        $parameter["required"] = true;
        $parameter["name"] = "api_key";
        $credentials["api_key"] = $parameter;

        $parameter = array();
        $parameter["description"] = "aff_id";
        $parameter["required"] = true;
        $parameter["name"] = "aff_id";
        $credentials["aff_id"] = $parameter;

        $parameter = array();
        $parameter["description"] = "website_id";
        $parameter["required"] = true;
        $parameter["name"] = "website_id";
        $credentials["website_id"] = $parameter;

        return $credentials;
    }

    /**
     * Check the connection
     */
    public function checkConnection()
    {
        return true;
    }

    /**
     * @return array
     */
    public function getMerchantList()
    {
        $merchants = array();

        $strUrl = "https://classic.".$this->_domain .'/api.php';
        $strUrl .= "?affiliate_id=$this->_id";
        $strUrl .= "&auth_key=$this->_apikey";
        $strUrl .= "&module=AffiliateReport";
        $strUrl .= "&output=" . \urlencode('csv');
        $strUrl .= "&report_id=1";

        $returnResult = self::makeCall($strUrl);
        $exportData = \str_getcsv($returnResult, "\r\n");

        $num = \count($exportData);
        for ($i = 1; $i < $num; $i++) {
            $merchantExportArray = \str_getcsv($exportData[$i], ",");
            if (\count($merchantExportArray) > 1) {
                $merchant = array();
                $merchant['cid'] = $merchantExportArray[1];
                $merchant['name'] = $merchantExportArray[0];
                $merchants[] = $merchant;
            }
        }
        return $merchants;

    }

    /**
     * @param null $merchantList
     * @param \DateTime|null $dStartDate
     * @param \DateTime|null $dEndDate
     * @return array
     */
    public function getTransactionList($merchantList = null, \DateTime $dStartDate = null, \DateTime $dEndDate = null)
    {
        $totalTransactions = array();

        $strUrl = "https://classic.".$this->_domain .'/api.php';
        $strUrl .= "?affiliate_id=$this->_id";
        $strUrl .= "&auth_key=$this->_apikey";
        $strUrl .= "&module=AffiliateReport";
        $strUrl .= "&output=" . \urlencode('csv');
        $strUrl .= "&report_id=8";
        $strUrl .= "&date_begin=" . \urlencode($dStartDate->format("Y-m-d H:i:s"));
        $strUrl .= "&date_end=" . \urlencode($dEndDate->format("Y-m-d H:i:s"));
        $strUrl .= "&include_inactive_merchants=1";
        $strUrl .= "&search_results_include_cpc=0";
        $strUrl .= "&website_id=".$this->_website_id;

        $returnResult = self::makeCall($strUrl);
        $exportData = \str_getcsv($returnResult, "\r\n");

        $num = \count($exportData);
        for ($i = 1; $i < $num; $i++) {
            $transactionExportArray = \str_getcsv($exportData[$i], ",");
            if (\count($transactionExportArray) > 1) {
                $transaction = Array();
                $merchantId = (int)$transactionExportArray[19];
                $transaction['merchantId'] = $merchantId;
                $transaction['date'] = $transactionExportArray[13];
                $transaction['unique_id'] = (int)$transactionExportArray[7];

                if ($transactionExportArray[6] != null) {
                    $transaction['custom_id'] = $transactionExportArray[6];
                }
                $transaction['currency'] = 'USD';
                $transaction['status'] = \Oara\Utilities::STATUS_CONFIRMED;
                $transaction['amount'] = \Oara\Utilities::parseDouble($transactionExportArray[8]);
                $transaction['commission'] = \Oara\Utilities::parseDouble($transactionExportArray[9]);
                $totalTransactions[] = $transaction;
            }
        }
        return $totalTransactions;
    }

    /**
     * @param $strUrl
     * @return mixed
     */
    private function makeCall($strUrl)
    {

        $ch = \curl_init();
        \curl_setopt($ch, CURLOPT_URL, $strUrl);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $returnResult = \curl_exec($ch);
        \curl_close($ch);
        return $returnResult;
    }
}
