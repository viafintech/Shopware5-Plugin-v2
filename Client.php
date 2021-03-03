<?php

namespace ZerintBarzahlenViacash;

use ZerintBarzahlenViacash\Exception\ApiException;
use ZerintBarzahlenViacash\Request\Request;
use ZerintBarzahlenViacash\Exception as Exception;

class Client
{
    const API_URL = 'https://api.barzahlen.de:443/v2';

    const API_SANDBOX_URL = 'https://api-sandbox.barzahlen.de:443/v2';

    /**
     * @var string
     */
    private $divisionId;

    /**
     * @var string
     */
    private $paymentKey;

    /**
     * @var string
     */
    private $apiUrl;

    /**
     * @var string
     */
    private $userAgent = 'PHP SDK v2.1.2';

    /**
     * @var string
     */
    private $header = '';




    /**
     * @param string $divisionId
     * @param string $paymentKey
     * @param boolean $sandbox
     */
    public function __construct($divisionId, $paymentKey, $sandbox = false)
    {
        $this->divisionId = $divisionId;
        $this->paymentKey = $paymentKey;
        $this->apiUrl = $sandbox ? self::API_SANDBOX_URL : self::API_URL;
    }

    /**
     * @param string $userAgent
     * @return Client
     */
    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    /**
     * @param Request $request
     * @param bool $bHeader
     * @param bool $bRaw
     * @return string
     * @throws ApiException
     * @throws Exception\CurlException
     */
    public function handle($request, $bHeader = false, $bRaw = false)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->apiUrl . $request->getPath());
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->buildHeader($request));
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $request->getMethod());
        curl_setopt($curl, CURLOPT_POSTFIELDS, $request->getBody());
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        if($bHeader) {
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HEADER, 1);
        }

        $response = curl_exec($curl);

        if($bRaw) {
            return $response;
        }

        $error = curl_error($curl);
        if ($error || false === $response) {
            throw new Exception\CurlException('Error during cURL: ' . $error . ' [' . curl_errno($curl) . ']');
        }

        $contentType = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $this->checkResponse($response, $contentType);

        curl_close($curl);

        return $response;
    }

    /**
     * @param Request $request
     * @return array
     */
    public function buildHeader($request)
    {
        $date = gmdate('D, d M Y H:i:s T');
        $idempotencyKey = $request->getIdempotence() ? md5(uniqid(rand(), true)) : '';

        $signature = Middleware::generateSignature(
            parse_url($this->apiUrl, PHP_URL_HOST) . ':' . parse_url($this->apiUrl, PHP_URL_PORT),
            $request->getMethod(),
            parse_url($this->apiUrl . $request->getPath(), PHP_URL_PATH),
            parse_url($this->apiUrl, PHP_URL_QUERY),
            $date,
            $idempotencyKey,
            $request->getBody(),
            $this->paymentKey
        );

        $header = array(
            "Host: " . parse_url($this->apiUrl, PHP_URL_HOST),
            "Date: " . $date,
            "User-Agent: " . $this->userAgent,
            "Authorization: BZ1-HMAC-SHA256 DivisionId=" . $this->divisionId . ", Signature=" . $signature
        );

        if ($idempotencyKey !== '') {
            $header[] = "Idempotency-Key: " . $idempotencyKey;
        }

        return $header;
    }

    /**
     * @param string $response
     * @param string $contentType
     * @return string|void
     * @throws \Exception
     */
    public function checkResponse($response, $contentType)
    {
        if (Middleware::stringIsPrefix('application/json', $contentType)) {

            if (strpos($response, 'error_class') === false) {
                return;
            }

            $oResponse = json_decode($response);

            /*
            $errorMapping = array(
                'auth'              => '\ZerintBarzahlenViacash\Exception\AuthException',
                'transport'         => '\ZerintBarzahlenViacash\Exception\TransportException',
                'idempotency'       => '\ZerintBarzahlenViacash\Exception\IdempotencyException',
                'rate_limit'        => '\ZerintBarzahlenViacash\Exception\RateLimitException',
                'invalid_format'    => '\ZerintBarzahlenViacash\Exception\InvalidFormatException',
                'invalid_state'     => '\ZerintBarzahlenViacash\Exception\InvalidStateException',
                'invalid_parameter' => '\ZerintBarzahlenViacash\Exception\InvalidParameterException',
                'not_allowed'       => '\ZerintBarzahlenViacash\Exception\NotAllowedException',
                'server_error'      => '\ZerintBarzahlenViacash\Exception\ServerException'
            );

            if (isset($errorMapping[$response->error_class])) {
                throw new ApiException($response->message, $response->request_id);
            }
            */

            throw new \Exception($oResponse->request_id . "|" . $oResponse->message);
        }
    }

    /**
     * helper method, generates a unique id
     */
    public function generateUUID() {
        return uniqid();
    }
}
