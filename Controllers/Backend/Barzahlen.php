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
            $this->container->get('events')->notify(
                'Shopware_Plugins_HttpCache_ClearCache'
            );

            $sDivisionID = Shopware()->Config()->getByNamespace('ZerintBarzahlenViacash','division_id');
            $sApiKey = Shopware()->Config()->getByNamespace('ZerintBarzahlenViacash','api_key');

            $oClient = new Client($sDivisionID, $sApiKey, true);

            $oRequest = new CreatePing();

            $sResponseJson = $oClient->handle($oRequest,true, true);

            if(mb_stripos($sResponseJson, '200 OK')  > 0)
            {   echo " Test OK";
            } else {
                throw new Exception(" Error " . $sResponseJson);
            }

            return $sResponseJson;

        } catch (Exception $exception) {

            $this->response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
            echo ($exception->getMessage());
            $this->View()->assign('response', $exception->getMessage());
        }

        return false;
    }
}


