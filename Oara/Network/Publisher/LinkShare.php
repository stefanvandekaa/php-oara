<?php
/**
 * The goal of the Open Affiliate Report Aggregator (OARA) is to develop a set
 * of PHP classes that can download affiliate reports from a number of affiliate networks, and store the data in a common format.
 *
 * Copyright (C) 2014  Fubra Limited
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
 * @category   Oara_Network_Publisher_Ls
 * @copyright  Fubra Limited
 * @version    Release: 01.00
 *
 */
class Oara_Network_Publisher_LinkShare extends Oara_Network
{

    protected $_sitesAllowed = array();
    /**
     * Client
     *
     * @var unknown_type
     */
    private $_client = null;
    /**
     * Site List
     *
     * @var unknown_type
     */
    private $_siteList = array();
    /**
     * Nid for this Linkshare instance
     *
     * @var string
     */
    private $_nid = null;

    /**
     * @param $credentials
     */
    public function __construct($credentials)
    {
        $user = $credentials ['user'];
        $password = $credentials ['password'];
        // Choosing the Linkshare network
        if ($credentials ['network'] == 'us') {
            $this->_nid = '1';
        } else if ($credentials ['network'] == 'uk') {
            $this->_nid = '3';
        } else if ($credentials ['network'] == 'ca') {
            $this->_nid = '5';
        } else if ($credentials ['network'] == 'fr') {
            $this->_nid = '7';
        } else if ($credentials ['network'] == 'br') {
            $this->_nid = '8';
        } else if ($credentials ['network'] == 'de') {
            $this->_nid = '9';
        } else if ($credentials ['network'] == 'eu') {
            $this->_nid = '31';
        } else if ($credentials ['network'] == 'au') {
            $this->_nid = '41';
        } else if ($credentials ['network'] == 'la') {
            $this->_nid = '54';
        }

        $loginUrl = 'https://cli.linksynergy.com/cli/common/authenticateUser.php';

        $valuesLogin = array(
            new Oara_Curl_Parameter ('front_url', ''),
            new Oara_Curl_Parameter ('postLoginDestination', ''),
            new Oara_Curl_Parameter ('cuserid', ''),
            new Oara_Curl_Parameter ('loginUsername', $user),
            new Oara_Curl_Parameter ('loginPassword', $password),
            new Oara_Curl_Parameter ('x', '28'),
            new Oara_Curl_Parameter ('y', '10')
        );
        // Login to the Linkshare Application
        $this->_client = new Oara_Curl_Access ($loginUrl, $valuesLogin, $credentials);
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function checkConnection()
    {
        $connection = false;

        $urls = array();
        $urls [] = new Oara_Curl_Request ('http://cli.linksynergy.com/cli/publisher/home.php', array());
        $result = $this->_client->get($urls);

        // Check if the credentials are right
        if (preg_match('/https:\/\/cli\.linksynergy\.com\/cli\/common\/logout\.php/', $result [0], $matches)) {

            $urls = array();
            $urls [] = new Oara_Curl_Request ('https://cli.linksynergy.com/cli/publisher/my_account/marketingChannels.php', array());
            $resultHtml = $this->_client->get($urls);

            $dom = new Zend_Dom_Query ($resultHtml [0]);
            $results = $dom->query('table');
            foreach ($results as $table) {
                $tableCsv = self::htmlToCsv(self::DOMinnerHTML($table));
            }

            $resultsSites = array();
            $num = count($tableCsv);
            for ($i = 1; $i < $num; $i++) {
                $payment = Array();
                $siteArray = str_getcsv($tableCsv [$i], ";");
                if (isset ($siteArray [2]) && is_numeric($siteArray [2])) {
                    $result = array();
                    $result ["id"] = $siteArray [2];
                    $result ["name"] = $siteArray [1];
                    $result ["url"] = "https://cli.linksynergy.com/cli/publisher/common/changeCurrentChannel.php?sid=" . $result ["id"];
                    $resultsSites [] = $result;
                }
            }

            $siteList = array();
            foreach ($resultsSites as $resultSite) {
                $site = new stdClass ();

                $site->website = $resultSite ["name"];

                $site->url = $resultSite ["url"];

                $parsedUrl = parse_url($site->url);
                $attributesArray = explode('&', $parsedUrl ['query']);
                $attributeMap = array();
                foreach ($attributesArray as $attribute) {
                    $attributeValue = explode('=', $attribute);
                    $attributeMap [$attributeValue [0]] = $attributeValue [1];
                }
                $site->id = $attributeMap ['sid'];
                // Login into the Site ID
                $urls = array();
                $urls [] = new Oara_Curl_Request ($site->url, array());
                $result = $this->_client->get($urls);

                $urls = array();
                $urls [] = new Oara_Curl_Request ('https://cli.linksynergy.com/cli/publisher/reports/reporting.php', array());
                $result = $this->_client->get($urls);


                if (preg_match("/\"token_one\": \"(.+)\"/", $result [0], $match)) {
                    $site->token = $match [1];
                }

                $siteList [] = $site;
            }
            $connection = true;

            $this->_siteList = $siteList;
        }
        return $connection;
    }

    /**
     * @param $html
     * @return array
     */
    private function htmlToCsv($html)
    {
        $html = str_replace(array(
            "\t",
            "\r",
            "\n"
        ), "", $html);
        $csv = "";
        $dom = new Zend_Dom_Query ($html);
        $results = $dom->query('tr');
        $count = count($results); // get number of matches: 4
        foreach ($results as $result) {
            $tdList = $result->childNodes;
            $tdNumber = $tdList->length;
            if ($tdNumber > 0) {
                for ($i = 0; $i < $tdNumber; $i++) {
                    $value = $tdList->item($i)->nodeValue;
                    if ($i != $tdNumber - 1) {
                        $csv .= trim($value) . ";";
                    } else {
                        $csv .= trim($value);
                    }
                }
                $csv .= "\n";
            }
        }
        $exportData = str_getcsv($csv, "\n");
        return $exportData;
    }

    /**
     * @param $element
     * @return string
     */
    private function DOMinnerHTML($element)
    {
        $innerHTML = "";
        $children = $element->childNodes;
        foreach ($children as $child) {
            $tmp_dom = new DOMDocument ();
            $tmp_dom->appendChild($tmp_dom->importNode($child, true));
            $innerHTML .= trim($tmp_dom->saveHTML());
        }
        return $innerHTML;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getMerchantList()
    {
        $merchants = array();
        $merchantIdMap = array();
        foreach ($this->_siteList as $site) {

            $urls = array();
            $urls [] = new Oara_Curl_Request ($site->url, array());
            $result = $this->_client->get($urls);

            $urls = array();
            $urls [] = new Oara_Curl_Request ('http://cli.linksynergy.com/cli/publisher/programs/carDownload.php', array());
            $result = $this->_client->get($urls);

            $result [0] = str_replace("Baseline TrueLock\"\n", "Baseline TrueLock\",\n", $result [0]);
            $exportData = explode(",\n", $result [0]);

            $num = count($exportData);
            for ($i = 1; $i < $num - 1; $i++) {
                $merchantArray = str_getcsv($exportData [$i], ",", '"');
                if (!in_array($merchantArray [2], $merchantIdMap)) {
                    $obj = Array();

                    if (!isset ($merchantArray [2])) {
                        throw new Exception ("Error getting merchants");
                    }

                    $obj ['cid'] = ( int )$merchantArray [2];
                    $obj ['name'] = $merchantArray [0];
                    $obj ['description'] = $merchantArray [3];
                    $obj ['url'] = $merchantArray [1];
                    $merchants [] = $obj;
                    $merchantIdMap [] = $obj ['cid'];
                }
            }
        }
        return $merchants;
    }

    /**
     * @param null $merchantList
     * @param Zend_Date|null $dStartDate
     * @param Zend_Date|null $dEndDate
     * @param null $merchantMap
     * @return array
     * @throws Exception
     */
    public function getTransactionList($merchantList = null, Zend_Date $dStartDate = null, Zend_Date $dEndDate = null, $merchantMap = null)
    {
        $totalTransactions = Array();

        $filter = new Zend_Filter_LocalizedToNormalized (array(
            'precision' => 2
        ));
        foreach ($this->_siteList as $site) {
            if (empty($this->_sitesAllowed) || in_array($site->id, $this->_sitesAllowed)) {
                echo "getting Transactions for site " . $site->id . "\n\n";
                $url = "https://ran-reporting.rakutenmarketing.com/en/reports/individual-item-report/filters?start_date=" . $dStartDate->toString("yyyy-MM-dd") . "&end_date=" . $dEndDate->toString("yyyy-MM-dd") . "&include_summary=N&tz=GMT&date_type=transaction&token=" . urlencode($site->token) . "&network=" . $this->_nid;
                $result = file_get_contents($url);

                $url = "https://ran-reporting.rakutenmarketing.com/en/reports/signature-orders-report/filters?start_date=" . $dStartDate->toString("yyyy-MM-dd") . "&end_date=" . $dEndDate->toString("yyyy-MM-dd") . "&include_summary=N&tz=GMT&date_type=transaction&token=" . urlencode($site->token) . "&network=" . $this->_nid;
                $resultSignature = file_get_contents($url);
                $signatureMap = array();
                $exportData = str_getcsv($resultSignature, "\n");
                $num = count($exportData);
                for ($j = 1; $j < $num; $j++) {
                    $signatureData = str_getcsv($exportData [$j], ",");
                    $signatureMap[$signatureData[3]] = $signatureData[0];
                }

                $exportData = str_getcsv($result, "\n");
                $num = count($exportData);
                for ($j = 1; $j < $num; $j++) {
                    $transactionData = str_getcsv($exportData [$j], ",");


                    if (in_array(( int )$transactionData [3], $merchantList) && count($transactionData) == 11) {
                        $transaction = Array();
                        $transaction ['merchantId'] = ( int )$transactionData [3];
                        $transactionDate = new Zend_Date ($transactionData [1] . " " . $transactionData [2], "MM/dd/yy HH:mm:ss");
                        $transaction ['date'] = $transactionDate->toString("yyyy-MM-dd HH:mm:ss");

                        if (isset($signatureMap[$transactionData [0]])) {
                            $transaction ['custom_id'] = $signatureMap[$transactionData [0]];
                        }
                        $transaction ['unique_id'] = $transactionData [10];

                        $sales = $filter->filter($transactionData [7]);

                        if ($sales != 0) {
                            $transaction ['status'] = Oara_Utilities::STATUS_CONFIRMED;
                        } else if ($sales == 0) {
                            $transaction ['status'] = Oara_Utilities::STATUS_PENDING;
                        }

                        $transaction ['amount'] = $filter->filter($transactionData [7]);

                        $transaction ['commission'] = $filter->filter($transactionData [9]);

                        if ($transaction ['commission'] < 0) {
                            $transaction ['amount'] = 0;
                            $transaction ['commission'] = 0;
                            $transaction ['status'] = Oara_Utilities::STATUS_DECLINED;
                        }

                        $totalTransactions [] = $transaction;
                    }
                }
            }
        }

        return $totalTransactions;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getPaymentHistory()
    {
        $paymentHistory = array();

        $filter = new Zend_Filter_LocalizedToNormalized (array(
            'precision' => 2
        ));
        $past = new Zend_Date ("01-01-2010", "dd-MM-yyyy");
        $now = new Zend_Date ();
        $dateList = Oara_Utilities::yearsOfDifference($past, $now);
        $dateList [] = $now;
        foreach ($this->_siteList as $site) {
            for ($i = 0; $i < count($dateList) - 1; $i++) {
                $bdate = clone $dateList [$i];
                $edate = clone $dateList [$i + 1];
                if ($i != (count($dateList) - 1)) {
                    $edate->subDay(1);
                }

                //echo "getting Payment for Site " . $site->id . " and year " . $bdate->toString("yyyy") . " \n\n";
                $url = "https://reportws.linksynergy.com/downloadreport.php?bdate=" . $bdate->toString("yyyyMMdd") . "&edate=" . $edate->toString("yyyyMMdd") . "&token=" . $site->secureToken . "&nid=" . $this->_nid . "&reportid=1";
                $result = file_get_contents($url);
                if (preg_match("/You cannot request/", $result)) {
                    throw new Exception ("Reached the limit");
                }

                $paymentLines = str_getcsv($result, "\n");
                $number = count($paymentLines);
                for ($j = 1; $j < $number; $j++) {
                    $paymentData = str_getcsv($paymentLines [$j], ",");
                    $obj = array();
                    $date = new Zend_Date ($paymentData [1], "yyyy-MM-dd");
                    $obj ['date'] = $date->toString("yyyy-MM-dd HH:mm:ss");

                    $obj ['value'] = $filter->filter($paymentData [5]);
                    $obj ['method'] = "BACS";
                    $obj ['pid'] = $paymentData [0];
                    $paymentHistory [] = $obj;
                }
            }
        }

        return $paymentHistory;
    }

    /**
     * @param string $paymentId
     * @param $merchantList
     * @param $startDate
     * @return array
     */
    public function paymentTransactions($paymentId, $merchantList, $startDate)
    {
        $transactionList = array();
        /*
        foreach ( $this->_siteList as $site ) {

            $url = "https://reportws.linksynergy.com/downloadreport.php?payid=$paymentId&token=" . $site->secureToken . "&reportid=2";
            $result = file_get_contents ( $url );
            if (preg_match ( "/You cannot request/", $result )) {
                throw new Exception ( "Reached the limit" );
            }
            $paymentLines = str_getcsv ( $result, "\n" );
            $number = count ( $paymentLines );
            for($j = 1; $j < $number; $j ++) {
                $paymentData = str_getcsv ( $paymentLines [$j], "," );

                $url = "https://reportws.linksynergy.com/downloadreport.php?invoiceid={$paymentData[2]}&token=" . $site->secureToken . "&reportid=3";
                $result = file_get_contents ( $url );
                if (preg_match ( "/You cannot request/", $result )) {
                    throw new Exception ( "Reached the limit" );
                }
                $transactionLines = str_getcsv ( $result, "\n" );
                $numbeTr = count ( $transactionLines );
                for($z = 1; $z < $numbeTr; $z ++) {
                    $transactionData = str_getcsv ( $transactionLines [$z], "," );
                    $transactionList [] = $transactionData [4];
                }
            }
        }
        */
        return $transactionList;
    }
}
