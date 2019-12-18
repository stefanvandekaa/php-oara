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
 * Api Class
 *
 * @author     Carlos Morillo Merino
 * @category   Wg
 * @copyright  Fubra Limited
 * @version    Release: 01.00
 *
 */
class WebGains extends \Oara\Network
{

    private $_soapClient = null;
    private $_server = null;
    private $_credentials = null;
    protected $_sitesAllowed = array();
    private $_campaign = false;


    /**
     * @param $credentials
     */
    public function login($credentials)
    {

        $this->_credentials = $credentials;
        $this->_server = 'www.webgains.'.$credentials['platform'];
        if ($credentials['platform'] == 'us') {
            $this->_server = 'us.webgains.com';
        }

        $this->_user = $credentials['user'];
        $this->_password = $credentials['password'];
        $this->_client = new \Oara\Curl\Access($credentials);
        $this->_campaign = $credentials['campaign'];

        $wsdlUrl = 'http://ws.webgains.com/aws.php';
        $this->_soapClient = new \SoapClient($wsdlUrl, array('login' => $this->_user,
            'encoding' => 'UTF-8',
            'password' => $this->_password,
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
            'soap_version' => SOAP_1_1));

        $loginUrl = 'https://'.$this->_server.'/loginform.html?action=login';

        $postValues = array(
            // Get user/password from credentials
            'username' => $this->_user,
            'password' => $this->_password,
            'user_type' => 'affiliateuser'
        );

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $loginUrl);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postValues));
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_COOKIEJAR, 'webgains_cookie.txt');
        curl_setopt($curl, CURLOPT_COOKIEFILE, 'webgains_cookie.txt');
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.2309.372 Safari/537.36');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_REFERER, $loginUrl);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($curl);
    }

    /**
     * @return array
     */
    public function getNeededCredentials()
    {
        $credentials = array();

        $parameter = array();
        $parameter["description"] = "User Log in";
        $parameter["required"] = true;
        $parameter["name"] = "User";
        $credentials["user"] = $parameter;

        $parameter = array();
        $parameter["description"] = "Password to Log in";
        $parameter["required"] = true;
        $parameter["name"] = "Password";
        $credentials["password"] = $parameter;

        return $credentials;
    }

    /**
     * @return bool
     */
    public function checkConnection()
    {
        $connection = false;
        if ($this->_server != null) {
            $connection = true;
        }
        return $connection;
    }

    /**
     * @return array
     */
    public function getMerchantList()
    {
        /**
         * Webgains Programs API
         * https://api.webgains.com/2.0/programs
         */
        $statisticsActions = "https://api.webgains.com/2.0/programs";
        $merchants = Array();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $statisticsActions . '?key=' . $this->_credentials['api-key'] .'&programsjoined=1&campaignId='. $this->_campaign);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        $curl_results = curl_exec($ch);
        curl_close($ch);
        $a_merchants = json_decode($curl_results, true);
        if (isset($a_merchants['code']) && $a_merchants['code'] == 401) {
            echo "[error] Webgains Authentication Failed in get merchants";
            return $merchants;
        }
        foreach ($a_merchants as $merchantJson) {
            if (isset($merchantJson["id"])) {
                $obj = Array();
                $obj['cid'] = $merchantJson["id"];
                $obj['name'] = $merchantJson["name"];
                $obj['status'] = $merchantJson["status"];
                $obj['url'] = $merchantJson["homepageURL"];
                $merchants[] = $obj;
            }
        }

        return $merchants;
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
        $totalTransactions = Array();

        //$merchantListIdList = \Oara\Utilities::getMerchantIdMapFromMerchantList($merchantList);

        try {
            $transactionList = $this->_soapClient->getFullEarningsWithCurrency($dStartDate->format("Y-m-d\TH:i:s"), $dEndDate->format("Y-m-d\TH:i:s"), $this->_campaign, $this->_user, $this->_password);
        } catch (\Exception $e) {
            if (\preg_match("/60 requests/", $e->getMessage())) {
                \sleep(60);
                $transactionList = $this->_soapClient->getFullEarningsWithCurrency($dStartDate->format("Y-m-d\TH:i:s"), $dEndDate->format("Y-m-d\TH:i:s"), $this->_campaign, $this->_user, $this->_password);
            }
        }
        foreach ($transactionList as $transactionObject) {
            // Dont'check for a valid program - <PN> 2017-07-05
            // if (isset($merchantListIdList[$transactionObject->programID])) {

            $transaction = array();
            $transaction['merchantId'] = $transactionObject->programID;
            $transactionDate = \DateTime::createFromFormat("Y-m-d\TH:i:s", $transactionObject->date);
            $transaction["date"] = $transactionDate->format("Y-m-d H:i:s");
            $transaction['unique_id'] = $transactionObject->transactionID;
            if ($transactionObject->clickRef != null) {
                $transaction['custom_id'] = $transactionObject->clickRef;
            }
            $transaction['status'] = null;
            $transaction['amount'] = $transactionObject->saleValue;
            $transaction['commission'] = $transactionObject->commission;
            // Check both for status + paymentStatus
            if ($transactionObject->status == 'confirmed') {
                $transaction['status'] = \Oara\Utilities::STATUS_CONFIRMED;
            }
            elseif ($transactionObject->status == 'delayed') {
                $transaction['status'] = \Oara\Utilities::STATUS_PENDING;
            }
            elseif ($transactionObject->status == 'cancelled') {
                $transaction['status'] = \Oara\Utilities::STATUS_DECLINED;
            }
            if ($transactionObject->paymentStatus == 'paid') {
                $transaction['paid'] = true;
            }
            else {
                $transaction['paid'] = false;
            }
            $transaction['currency'] = $transactionObject->currency;
            $totalTransactions[] = $transaction;
            // }
        }
        return $totalTransactions;
    }


    /**
     * Get list of Vouchers / Coupons / Offers
     * @param $id_site   account ID needed to access data feed
     * @return array
     */
    public function getVouchers($id_site)
    {
        $vouchers = array();

        try {

            $url = $this->_server . '/publisher/' . $id_site . '/ad/vouchercodes/downloadcsv?';

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_COOKIEJAR, 'webgains_cookie.txt');
            curl_setopt($curl, CURLOPT_COOKIEFILE, 'webgains_cookie.txt');
            curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.2309.372 Safari/537.36');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_REFERER, $url);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            $result = curl_exec($curl);

            if ($result === false)
            {
                throw new \Exception("php-oara WebGains getVouchers - http error");
            } else {
                $vouchers = \str_getcsv($result, "\n");
            }
        } catch (\Exception $e) {
            echo "WebGains getVouchers error:".$e->getMessage()."\n ";
            throw new \Exception($e);
        }
        return $vouchers;
    }

}
