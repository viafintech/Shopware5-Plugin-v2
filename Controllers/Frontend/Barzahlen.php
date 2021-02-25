<?php
use ZerintBarzahlenViacash\Components\Payment\PaymentResponse;
use ZerintBarzahlenViacash\Components\Payment\PaymentService;
use ZerintBarzahlenViacash\Client;
use ZerintBarzahlenViacash\Exception\ApiException;
use ZerintBarzahlenViacash\Request\CreateRequest;
use ZerintBarzahlenViacash\Webhook;
use Shopware\Models\Order\Order;


class Shopware_Controllers_Frontend_Barzahlen extends Shopware_Controllers_Frontend_Payment implements Shopware\Components\CSRFWhitelistAware
{
    /**
     * {@inheritdoc}
     */
    public function getWhitelistedCSRFActions()
    {
        return [
            'notify'
        ];
    }

    const PAYMENTSTATUSPAID = 12;
    const PAYMENTSTATUSCANELED = 35;

    public function preDispatch()
    {
        Shopware()->Container()->get('pluginlogger')->info('Barzahlen headers:' . print_r($this->Request()->headers, true));
        Shopware()->Container()->get('pluginlogger')->info('Barzahlen preDispatch');

        /** @var \Shopware\Components\Plugin $plugin */
        $plugin = $this->get('kernel')->getPlugins()['ZerintBarzahlenViacash'];

        $this->get('template')->addTemplateDir($plugin->getPath() . '/Resources/views/');
    }

    /**
     * Index action method.
     *
     * Forwards to the correct action.
     */
    public function indexAction()
    {
        $oLog = Shopware()->Container()->get('pluginlogger');

        /** @var PaymentService $service */
        $oService = $this->container->get('barzahlen.payment_service');
        $aUser = $this->getUser();
        $aBasket = $this->getBasket();

        $sDivisionID = Shopware()->Config()->getByNamespace('ZerintBarzahlenViacash','division_id');
        $sApiKey = Shopware()->Config()->getByNamespace('ZerintBarzahlenViacash','api_key');

        $sToken = $oService->createPaymentToken($this->getAmount(), $aUser["additional"]["user"]["customernumber"]);

        try {

            $bSandboxMode = Shopware()->Config()->getByNamespace('ZerintBarzahlenViacash','sandbox_mode');

            $oClient = new Client($sDivisionID, $sApiKey, $bSandboxMode);

            $oRequest = new CreateRequest();
            $oRequest->setSlipType('payment');
            $oRequest->setCustomerKey($aUser["additional"]["user"]["email"]);
            $oRequest->setTransaction((string)number_format($aBasket["sAmount"],2), $aBasket["sCurrencyName"]);
            $oRequest->setCustomerEmail($aUser["additional"]["user"]["email"]);
            $oRequest->setReferenceKey($sToken);
            $oRequest->setAddress(array(
                'street_and_no' => $aUser["billingaddress"]["street"],
                'zipcode' => $aUser["billingaddress"]["zipcode"],
                'city' => $aUser["billingaddress"]["city"],
                'country' => $aUser["additional"]["country"]["countryiso"]
            ));

            $sLang = str_replace('_','-',Shopware()->Shop()->getLocale()->getLocale());
            $oRequest->setCustomerLanguage($sLang);

            /**
             * @Todo
             * if $aUser["additional"]["country"]["countryiso"] == IT dann divison X
             * country not found?
             * order not possible -> error(country does not exist, please notify shop owner), log entry (please add division for coutry XY)
             * documentation
             */

            $sExpiresDate = date('Y-m-d\TH:i:s\Z', strtotime('+14 days'));
            $oRequest->setExpiresAt($sExpiresDate);

            $sResponseJson = $oClient->handle($oRequest);

            $oApiResponse = json_decode($sResponseJson);

            Shopware()->Session()->checkout_token = $oApiResponse->checkout_token;

            if($bSandboxMode) {
                Shopware()->Session()->sandbox = "-sandbox";
            } else {
                Shopware()->Session()->sandbox = "";
            }

            Shopware()->Session()->checkout_token = $oApiResponse->checkout_token;
            $this->View()->assign('checkout_token', $oApiResponse->checkout_token);

            if(empty($oApiResponse->id)) {
                if(!empty($oApiResponse->error_code)) {
                    throw new ApiException($oApiResponse->message);
                }
            }

            /** @var PaymentResponse $response */
            $oResponse = $oService->createPaymentResponse($this->Request());

            if(!empty($sResponseJson)) {
                $iSaved = $this->saveOrder(
                    $oApiResponse->id,
                    $sToken,
                    0
                );

                if(empty($iSaved)) {
                    throw new ApiException($oResponse->transactionId . ' could not be saved');
                }

                $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
            }
        }
        catch (ApiException $e) {
            trigger_error('Barzahlen Error: ' . $e->getMessage());
        }
    }

    /**
     * Notify action method
     *
     * Reads the transactionResult and represents it for the customer.
     */
    public function notifyAction()
    {
        $oLog = Shopware()->Container()->get('pluginlogger');

        $aHeaders = (array)$this->Request()->headers;
        $sBody    = $this->Request()->getRawBody();

        $oLog->info('Barzahlen $sBody:' . $this->Request()->getRawBody());
        $oLog->info('Barzahlen $sBody stripslashes:' . $sBody);
        $oLog->info('Barzahlen headers:' . print_r($aHeaders, true));
        $oLog->info('Barzahlen: received header and body');

        try {

            $parentPayment = 1;

            $aHeader = array_merge($aHeaders, $_SERVER);

            // Get api key from config
            $sApiKey = Shopware()->Config()->getByNamespace('ZerintBarzahlenViacash','api_key');

            // Verify BZ signature before continue
            $oWebhook = new Webhook($sApiKey);
            $bResult = $oWebhook->verify($aHeader, $sBody);

            $oLog->info("Barzahlen API verification: " . $bResult);

            if($bResult == false) {
                echo(json_encode($bResult));
                $oLog->error("Barzahlen error verifying api result header and body.");
                throw new ApiException("Barzahlen error verifying api result header and body.");
            }

            $oBody = json_decode($sBody);
            $sSlipType = $oBody->event;


            if (empty($oBody->slip)) {
                $oLog->error('Barzahlen slip data not available.');
                throw new ApiException('Barzahlen slip data not available.');
            }

            $sEmail = $oBody->slip->customer->email;
            if (empty($sEmail)) {
                $oLog->error('Barzahlen Error: mail data not available.');
                throw new ApiException('Barzahlen Error: mail data not available.');
            }

            $iTransactionId = $oBody->slip->reference_key;
            if (empty($iTransactionId)) {
                $oLog->error('Barzahlen Error: reference key (order id) not available.');
                throw new ApiException('Barzahlen Error: reference key (order id) not available.');
            }

            // Get transactions of order
            $aTransactions = $oBody->slip->transactions;
            if (empty($aTransactions)) {
                $oLog->error('Barzahlen Error: no transaction data available.');
                throw new ApiException('Barzahlen Error: no transaction data available.');
            }

            $sSlipId = $oBody->slip->id;

            $oLog->info("Barzahlen slip type: " . $sSlipType);

            switch($sSlipType) {
                case 'paid': {
                    $this->_processTransactionPaid($sSlipId, $iTransactionId);
                    exit;
                }

                case 'expired': {
                    $this->_processTransactionExpired($sSlipId, $iTransactionId);
                    exit;
                }
            }

        } catch (ApiException $e) {
            $oLog->error($e->getMessage());
            exit;
        }
    }


    /**
     * @param $oApiResponse
     */
    protected function _processTransactionPaid($sSlipId, $sTransactionId) {

        Shopware()->Container()->get('pluginlogger')->info("sSlipId " . $sSlipId);
        Shopware()->Container()->get('pluginlogger')->info("sTransactionId " . $sTransactionId);

        $this->savePaymentStatus(
            $sSlipId,
            $sTransactionId,
            self::PAYMENTSTATUSPAID
        );

        Shopware()->Container()->get('pluginlogger')->info("_processTransactionPaid");
    }


    /**
     * @param $oApiResponse
     */
    protected function _processTransactionExpired($sSlipId, $sTransactionId) {

        Shopware()->Container()->get('pluginlogger')->info("sSlipId " . $sSlipId);
        Shopware()->Container()->get('pluginlogger')->info("sTransactionId " . $sTransactionId);

        $iSaved = $this->savePaymentStatus(
            $sSlipId,
            $sTransactionId,
            self::PAYMENTSTATUSCANELED
        );

        Shopware()->Container()->get('pluginlogger')->info("_processTransactionPaid");
    }

    /**
     * Creates the url parameters
     */
    private function getUrlParameters()
    {
        /** @var PaymentService $service */
        $service = $this->container->get('barzahlen.payment_service');
        $router = $this->Front()->Router();
        $user = $this->getUser();
        $billing = $user['billingaddress'];

        $parameter = [
            'amount' => $this->getAmount(),
            'currency' => $this->getCurrencyShortName(),
            'firstName' => $billing['firstname'],
            'lastName' => $billing['lastname'],
            'returnUrl' => $router->assemble(['action' => 'return', 'forceSecure' => true]),
            'cancelUrl' => $router->assemble(['action' => 'cancel', 'forceSecure' => true]),
            'token' => $service->createPaymentToken($this->getAmount(), $billing['customernumber'])
        ];

        return '?' . http_build_query($parameter);
    }
}
