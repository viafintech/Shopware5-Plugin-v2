<?php
use Symfony\Component\HttpFoundation\Response;
use ZerintBarzahlenViacash\Client;
use ZerintBarzahlenViacash\Request\CreatePing;


class Shopware_Controllers_Backend_Barzahlen extends \Shopware_Controllers_Backend_ExtJs {
    /*
     * @var Logger
     */
    public function testAction()
    {
        try {
            $sDivisionID = Shopware()->Config()->getByNamespace('ZerintBarzahlenViacash','division_id');
            $sApiKey = Shopware()->Config()->getByNamespace('ZerintBarzahlenViacash','api_key');

            $oClient = new Client($sDivisionID, $sApiKey, true);

            $oRequest = new CreatePing();

            $sResponseJson = $oClient->handle($oRequest);

            $oApiResponse = json_decode($sResponseJson);

            return $oApiResponse;
        } catch (Exception $exception) {

            $this->response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
            echo ($exception->getMessage());
            $this->View()->assign('response', $exception->getMessage());
        }

        return false;
    }
}


