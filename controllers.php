<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/* Define constants for app */
define("API_KEY", "c157bbaf096c7957e8e7df4e61a257f4");
define("API_SECRET", "");
define("APP_URL", "https://shopifyxmp31.a2hosted.com/");
/*** ***********/
/* Message displayed when Order is stopped when T_Reserve fails (due to licenses out of stock, bad product config on backend, bad network, etc.)  Add list of XCHANGE product names with %s. */
define('XCHANGE_RESERVE_FAILED_BROWSER_ERROR_MESSAGE', "We are sorry. One or more of the following items in your cart currently cannot be reserved: %s | Please contact the store owner.");
/*** ***********/
/* Sent to Customer in Order Update email in lieu of product license info when T_Finalize call fails. Add list of XCHANGE product names with %s */
define('XCHANGE_EMAIL_ERROR_MESSAGE', "Product license information currently unavailable for %s. Please contact the store owner.");
/*** ***********/
/* Log all XCHANGE behavior or only errors (errors are *always* emailed to the XCHANGE_EMAIL_ERRORS_TO address(es)). */
define('XCHANGE_LOG_ONLY_ERRORS', false);
/* Emails to send copies of XCHANGE errors to. (Separate multiple email addresses with commas, no spaces) */
define('XCHANGE_EMAIL_ERRORS_TO', 'xmp.dev011@gmail.com,s.senathira@xchangemarket.com');
/*** ***********/
/* Who the alert email appears to be sent from. Typically, your site support email */
define('XCHANGE_ADMIN_EMAIL', 'xmp.dev011@gmail.com');

#! If logging fails, it should not matter. The webhook function should still go to the next line of code...
function xchangeLog($intext, $is_error = false, $shopdomain = "")
{

	if ($intext == '') /** Skip empty entries **/
		return;

	$outtext = $intext."\n****************\n";

	if (XCHANGE_LOG_ONLY_ERRORS == false || $is_error)
	{



		/*** ***********/

		$xchshopdomain = $shopdomain;
		$servername = "localhost";
		$username = "xchangem_sank31";
		$password = "";
		$dbname = "xchangem_shopifyxmp31";

		$sql = "";
		$dberrtxt = "";
		try {

		    $conn = new PDO("mysql:host=$servername;dbname=xchangem_shopifyxmp31", $username, $password);
		    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // set the PDO error mode to exception
		    $sql = "INSERT INTO xchlog (shopdomain, logtxt) VALUES ('$xchshopdomain', '$outtext')";
		    $conn->exec($sql); // use exec() because no results are returned

		}
		catch(PDOException $e)
		{

		    $dberrtxt = $sql . "<br>" . $e->getMessage();
		    $mailBody = "XCHANGE DB Conn Error.\n".$dberrtxt."\n\n";
		    $header = "Reply-To: ".XCHANGE_ADMIN_EMAIL."\r\n"."From: ".XCHANGE_ADMIN_EMAIL."\r\n";
		    $result = mail(XCHANGE_EMAIL_ERRORS_TO, "XCHANGE DB Conn Error", $mailBody, $header);

		}

		$conn = null;

		/*** ***********/

    #! Implement this later! Still works in some what of a sense.
		if (XCHANGE_EMAIL_ERRORS_TO != '' && $is_error)
		{
			$mailBody = "XCHANGE Error.\n".$intext."\n\n";
			$header = "Reply-To: ".XCHANGE_ADMIN_EMAIL."\r\n"."From: ".XCHANGE_ADMIN_EMAIL."\r\n";
			$result = mail(XCHANGE_EMAIL_ERRORS_TO, "XCHANGE Error", $mailBody, $header);
		}



	}
}

$app->post('/giftbasket/install2', function () use ($app) {

  $resellerid = $_REQUEST['resellerid'];
  $resellerpw = $_REQUEST['resellerpw'];
  $shop = $_REQUEST['shop'];
  $scopes = "read_orders,write_orders,read_products,write_products,read_customers,write_customers,read_checkouts,write_checkouts";

	#! Insert variables into MySQL DB!
	$servername = "localhost";
	$username = "xchangem_sank31";
	$password = "";

	$sql = "";
	try {
	    $conn = new PDO("mysql:host=$servername;dbname=xchangem_shopifyxmp31", $username, $password);
	    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // set the PDO error mode to exception
	    $sql = "INSERT INTO xmpconfig (shopdomain, token, resellerid, resellerpw) VALUES ('$shop', '', '$resellerid', '$resellerpw')";
	    $conn->exec($sql);
	    echo "New record created successfully!";
	}
	catch(PDOException $e)
	{
	    echo $sql . "<br>" . $e->getMessage();
	    die("Oops! DB Error. Please try again later. If the problem persists, please contact XCHANGE.");
	}

	$conn = null;

  /* Construct the installation URL and redirect the merchant... */
  $installUrl = "http://$shop/admin/oauth/authorize?client_id=" . API_KEY . "&scope=$scopes&redirect_uri=" . APP_URL . "index.php/giftbasket/auth";
  header("Location: " . $installUrl);
  exit();

});

#! After installing the XCHANGE App and getting the Auth Token, we will need to setup the reseller on our app site.
#! --- Config.xml + Login feature + Storing into MySQL DB + saving Auth Token + Setting up XCHANGE products via Shopify API
$app->get('/giftbasket/auth', function() use ($app) {

	  /* Remove HMAC & signature parameters from the hash
	   * sort keys in hash lexicographically
	   * each key/value pair joined by &
	   * hash resulting string with SHA256 using API_SECRET
	   * compare result with HMAC parameter for successful match
	  */
	  foreach ($_REQUEST as $key => $value) {
	    if ($key !== "hmac" && $key != "signature") {
	      $hashArray[] = $key . "=" . $value;
	    }
	  }

    $params = $_GET; // Retrieve all request parameters
    $params = array_diff_key($params, array('hmac' => '')); // Remove hmac from params
    ksort($params); // Sort params lexographically
    // Compute SHA256 digest
    $computed_hmac = hash_hmac('sha256', http_build_query($params), API_SECRET);

	  /* compare resulting hashed string with hmac parameter */
	  if ($_REQUEST['hmac'] !== $computed_hmac) return 403;

	  /**** Curl to retrieve access token******/
	  $ch = curl_init();
	  curl_setopt($ch, CURLOPT_URL, "https://" . $_REQUEST["shop"] . "/admin/oauth/access_token.json");
	  curl_setopt($ch, CURLOPT_POST, 3);

	  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(
	      array(
	        "client_id" => API_KEY,
	        "client_secret" => API_SECRET,
	        "code" => $_REQUEST["code"]
	      )
	  ));
	  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); // Return the transfer as a string

	  /* pass to the browser to return results of the request */
	  $result = curl_exec($ch);

	  /* closes cURL resource */
	  curl_close($ch);



  	$tokenResponse = json_decode($result, true); // Get the returned access token data

  	createOrdersWebhook($tokenResponse["access_token"]);



		$shopdomain = $_REQUEST["shop"];
		$oautht = $tokenResponse["access_token"];
		$servername = "localhost";
		$username = "xchangem_sank31";
		$password = "";
		$dbname = "xchangem_shopifyxmp31";

		try {
		    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
		    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		    $sql = "UPDATE xmpconfig SET token='$oautht' WHERE shopdomain='$shopdomain'";
		    $stmt = $conn->prepare($sql);
		    $stmt->execute();
		}
		catch(PDOException $e)
		{
		    echo $sql . "<br>" . $e->getMessage();
		    die("Oops! DB Error. We were unable to save your data! Please try again later. If the problem persists, please contact XCHANGE.");
		}

		$conn = null;



		$resellerid = "";
		$resellerpw = "";
		try {
		    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
		    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		    $stmt = $conn->prepare("SELECT resellerid, resellerpw FROM xmpconfig WHERE shopdomain='$shopdomain'");
		    $stmt->execute();
		    $row = $stmt->fetch();

		    $resellerid = $row['resellerid'];
		    $resellerpw = $row['resellerpw'];
		}
		catch(PDOException $e) {
		    echo "Error: " . $e->getMessage();
		}
		$conn = null;



		$url = "https://".$shopdomain."/admin/metafields.json";

		/*** Setup fields, this is the metat data you want set ***********/
		$data = array(
			'metafield' => array(
				'namespace' => 'xchconfig',
				'key' => 'resellerid',
				'value' => $resellerid,
				'value_type' => 'string',
				'description' => 'This is a XCHANGE reseller ID.'
		  )
		);

		$ch = curl_init();

		curl_setopt($ch,CURLOPT_URL, $url);

		curl_setopt($ch,CURLOPT_POST, 1);

		curl_setopt($ch,CURLOPT_POSTFIELDS, http_build_query($data));

		curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Shopify-Access-Token: ' . $oautht));

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		//--------------Debug Info - set log to view variables -------------------------------------
		/***
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$verbose = fopen('php://temp', 'rw+');
		curl_setopt($ch, CURLOPT_STDERR, $verbose); ***********/
		//-------------End Debug ---------------------------------------

		/*** Execute post ***********/
		$result = curl_exec($ch);
		curl_close($ch);



		/*** Setup fields, this is the metat data you want set ***********/
		$datapw = array(
			'metafield' => array(
				'namespace' => 'xchconfig',
				'key' => 'resellerpw',
				'value' => $resellerpw,
				'value_type' => 'string',
				'description' => 'This is a XCHANGE reseller PW.'
		  )
		);

		$ch = curl_init();

		curl_setopt($ch,CURLOPT_URL, $url);

		curl_setopt($ch,CURLOPT_POST, 1);

		curl_setopt($ch,CURLOPT_POSTFIELDS, http_build_query($datapw));

		curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Shopify-Access-Token: ' . $oautht));

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		//--------------Debug Info - set log to view variables -------------------------------------
		/***
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$verbose = fopen('php://temp', 'rw+');
		curl_setopt($ch, CURLOPT_STDERR, $verbose); ***********/
		//-------------End Debug ---------------------------------------

		/*** Execute post ***********/
		$result = curl_exec($ch);
		curl_close($ch);



		header("Location: http://shopifyxmp31.a2hosted.com/index.php/login/");
		exit();

});
