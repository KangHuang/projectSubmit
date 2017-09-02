<?php

namespace App\Http\Controllers;

use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Amount;
use PayPal\Api\Payee;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Api\PaymentExecution;
use PayPal\Api\Item,
    PayPal\Api\ItemList;
use App\Repositories\ServiceRepository;

class PaymentController extends Controller {

    private $apiContext;
    private $service_gestion;

    public function __construct(ServiceRepository $service_gestion) {
        $this->apiContext = new ApiContext(
                new OAuthTokenCredential(
                'ARGHv_IvTYqQemReiRpAroo8_v3SQu1XuXTNRoI3AvwC2-IaC6aLd4vq8BgOhGVXjT18w4ekTXaPlumw', // ClientID
                'EAftwcy0Fa7hscs6sVTpW38MzPVj0XC6POL5P1OnyRS7d3Z3hSW5W4W4PP8EpnoJvElPJ_vGHi5CM7nL'      // ClientSecret
                )
        );
        // Comment this line out and uncomment the PP_CONFIG_PATH
        // 'define' block if you want to use static file
        // based configuration
        $this->apiContext->setConfig(
                array(
                    'mode' => 'live',
                    'log.LogEnabled' => true,
                    'log.FileName' => public_path('../storage/logs/PayPal.log'),
                    'log.LogLevel' => 'INFO', 
                )
        );
        $this->service_gestion = $service_gestion;
    }

    /**
     * Make payment.
     *
     * @return 
     */
    public function createPayment($service_id) {

        //get service model from id
        $service = $this->service_gestion->getById($service_id);
        $price = $service->price;
        // ### Payer
// set a payer

        $payer = new Payer();
        $payer->setPaymentMethod("paypal");

        $item1 = new Item();
        $item1->setName($service->title)
                ->setCurrency('GBP')
                ->setQuantity(1)
                ->setSku($service_id) // Similar to `item_number` in Classic API
                ->setPrice($service->price);
        $itemList = new ItemList();
        $itemList->setItems(array($item1));
        
// specify a payment amount.

        $amount = new Amount();
        $amount->setCurrency("GBP")
                ->setTotal($price);

// Specify a payee with that user's email
        
        $payee = new Payee();
        $payee->setEmail($service->provider->email);

// A transaction defines the payment details
        
        $transaction = new Transaction();
        $transaction->setAmount($amount)
                ->setItemList($itemList)
                ->setDescription("Service Payment")
                ->setPayee($payee)
                ->setInvoiceNumber(uniqid())
                ->setCustom(auth()->guard('users')->id());

// Set the urls for buyers to be redirected

        $baseUrl = $this->getBaseUrl();
        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturnUrl("$baseUrl/service/executePayment?success=true")
                ->setCancelUrl("$baseUrl/service/executePayment?success=false");

// A Payment Resource

        $payment = new Payment();
        $payment->setIntent("sale")
                ->setPayer($payer)
                ->setRedirectUrls($redirectUrls)
                ->setTransactions(array($transaction));
        
// Create Payment

        try {
            $payment->create($this->apiContext);
        } catch (Exception $ex) {
            exit(1);
        }
        
// Get redirect url

        $approvalUrl = $payment->getApprovalLink();
        
        $comments = $service->comments;

        return view('front.service.payment', compact('approvalUrl', 'service','comments'));
    }

    /*
      function execute payment
     * @return payment
     *      */

    public function executePayment() {
        // ### Approval Status
// Determine if the user approved the payment or not
        if (isset($_GET['success']) && $_GET['success'] == 'true') {
            
            // Get the payment Object
            $paymentId = $_GET['paymentId'];
            $payment = Payment::get($paymentId, $this->apiContext);
            
            // Payment Execution object

            $execution = new PaymentExecution();
            $execution->setPayerId($_GET['PayerID']);

            try {
                // Execute the payment

                $result = $payment->execute($execution, $this->apiContext);
                try {
                    $payment = Payment::get($paymentId, $this->apiContext);
                } catch (Exception $ex) {
                    exit(1);
                }
            } catch (Exception $ex) {
                exit(1);
            }
            return redirect('services')->with('ok', 'The payment is processing. It may take a few minutes');
        } else {
            exit;
        }
    }

    /**
     * ### getBaseUrl function from https://github.com/paypal/PayPal-PHP-SDK/blob/master/sample/common.php
     * // utility function that returns base url for
     * // determining return/cancel urls
     *
     * @return string
     */
    public function getBaseUrl() {
        if (PHP_SAPI == 'cli') {
            $trace = debug_backtrace();
            $relativePath = substr(dirname($trace[0]['file']), strlen(dirname(dirname(__FILE__))));
            echo "Warning: This sample may require a server to handle return URL. Cannot execute in command line. Defaulting URL to http://localhost$relativePath \n";
            return "http://localhost" . $relativePath;
        }
        $protocol = 'http';
        if ($_SERVER['SERVER_PORT'] == 443 || (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on')) {
            $protocol .= 's';
        }
        $host = $_SERVER['HTTP_HOST'];
        $request = $_SERVER['PHP_SELF'];
        return dirname($protocol . '://' . $host . $request);
    }

    /**
     * ### listen IPN from paypal
     *
     *
     * @return mix
     */
    public function ipnListener() {


        $ipn = new PaypalIPN();
// Use the sandbox endpoint during testing.
//        $ipn->useSandbox();
        $verified = $ipn->verifyIPN();
        if ($verified) {

            if (isset($_POST['txn_type']) && $_POST['txn_type'] == 'cart') {
                if (isset($_POST['item_number1']) && isset($_POST['custom'])) {

                    \Illuminate\Support\Facades\Log::info('enter into');
                    //create link to authorize users
                    $service_id = $_POST['item_number1'];
                    $user_id = $_POST['custom'];
                    $this->service_gestion->getById($service_id)->users()->attach($user_id);
                }
            }
        }
// Reply with an empty 200 response to indicate to paypal the IPN was received correctly.
        header("HTTP/1.1 200 OK");
    }

}

//from PayPal IPN sample code https://github.com/paypal/ipn-code-samples

class PaypalIPN {

    /**
     * @var bool $use_sandbox     Indicates if the sandbox endpoint is used.
     */
    private $use_sandbox = false;

    /**
     * @var bool $use_local_certs Indicates if the local certificates are used.
     */
    private $use_local_certs = true;

    /** Production Postback URL */
    const VERIFY_URI = 'https://ipnpb.paypal.com/cgi-bin/webscr';

    /** Sandbox Postback URL */
    const SANDBOX_VERIFY_URI = 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';

    /** Response from PayPal indicating validation was successful */
    const VALID = 'VERIFIED';

    /** Response from PayPal indicating validation failed */
    const INVALID = 'INVALID';

    /**
     * Sets the IPN verification to sandbox mode (for use when testing,
     * should not be enabled in production).
     * @return void
     */
    public function useSandbox() {
        $this->use_sandbox = true;
    }

    /**
     * Sets curl to use php curl's built in certs (may be required in some
     * environments).
     * @return void
     */
    public function usePHPCerts() {
        $this->use_local_certs = false;
    }

    /**
     * Determine endpoint to post the verification data to.
     * @return string
     */
    public function getPaypalUri() {
        if ($this->use_sandbox) {
            return self::SANDBOX_VERIFY_URI;
        } else {
            return self::VERIFY_URI;
        }
    }

    /**
     * Verification Function
     * Sends the incoming post data back to PayPal using the cURL library.
     *
     * @return bool
     * @throws Exception
     */
    public function verifyIPN() {
        if (!count($_POST)) {
            throw new Exception("Missing POST Data");
        }
        $raw_post_data = file_get_contents('php://input');
        $raw_post_array = explode('&', $raw_post_data);
        $myPost = array();
        foreach ($raw_post_array as $keyval) {
            $keyval = explode('=', $keyval);
            if (count($keyval) == 2) {
                // Since we do not want the plus in the datetime string to be encoded to a space, we manually encode it.
                if ($keyval[0] === 'payment_date') {
                    if (substr_count($keyval[1], '+') === 1) {
                        $keyval[1] = str_replace('+', '%2B', $keyval[1]);
                    }
                }
                $myPost[$keyval[0]] = urldecode($keyval[1]);
            }
        }
        // Build the body of the verification post request, adding the _notify-validate command.
        $req = 'cmd=_notify-validate';
        $get_magic_quotes_exists = false;
        if (function_exists('get_magic_quotes_gpc')) {
            $get_magic_quotes_exists = true;
        }
        foreach ($myPost as $key => $value) {
            if ($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
                $value = urlencode(stripslashes($value));
            } else {
                $value = urlencode($value);
            }
            $req .= "&$key=$value";
        }
        // Post the data back to PayPal, using curl. Throw exceptions if errors occur.
        $ch = curl_init($this->getPaypalUri());
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
        curl_setopt($ch, CURLOPT_SSLVERSION, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        // This is often required if the server is missing a global cert bundle, or is using an outdated one.
        if ($this->use_local_certs) {
            curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . "/cert/cacert.pem");
        }
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));

        $res = curl_exec($ch);
        if (!($res)) {
            $errno = curl_errno($ch);
            $errstr = curl_error($ch);
            curl_close($ch);
            throw new \Exception("cURL error: [$errno] $errstr");
        }
        $info = curl_getinfo($ch);
        $http_code = $info['http_code'];
        if ($http_code != 200) {
            throw new \Exception("PayPal responded with http code $http_code");
        }
        curl_close($ch);
        // Check if PayPal verifies the IPN data, and if so, return true.
        if ($res == self::VALID) {
            return true;
        } else {
            return false;
        }
    }

}
