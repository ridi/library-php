<?php
declare(strict_types=1);

namespace Ridibooks\Store\Library\AccountCommandApiClient;

use Firebase\JWT\JWT;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use Ridibooks\Store\Library\AccountCommandApiClient\Payload\Payload;

class Client
{
    public const JWT_EXPIRATION_TIME_OPTION = 'option: JWT expiration time';

    private const DEFAULT_ACCOUNT_SERVER_URI = 'https://library-api.ridibooks.com';

    /** @var Client */
    private $client;
    /** @var string */
    private $jwt_private_key;
    /** @var int */
    private $default_jwt_expiration_time = 300;

    /**
     * @param string $jwt_private_key
     * @param array $config
     * @throws \InvalidArgumentException
     */
    public function __construct(string $jwt_private_key, array $config = [])
    {
        if (!isset($config['base_uri'])) {
            $config['base_uri'] = self::DEFAULT_ACCOUNT_SERVER_URI;
        }
        if (isset($config[self::JWT_EXPIRATION_TIME_OPTION])) {
            $this->default_jwt_expiration_time = $config[self::JWT_EXPIRATION_TIME_OPTION];
            unset($config[self::JWT_EXPIRATION_TIME_OPTION]);
        }
        $this->client = new GuzzleClient($config);
        $this->jwt_private_key = $jwt_private_key;
    }

    /**
     * @param Payload $payload
     * @param array $options
     * @return PromiseInterface
     */
    public function sendCommandAsync(Payload $payload, array $options = []): PromiseInterface
    {
        if (isset($options[self::JWT_EXPIRATION_TIME_OPTION])) {
            $jwt_expiration_time = $options[self::JWT_EXPIRATION_TIME_OPTION];
            unset($options[self::JWT_EXPIRATION_TIME_OPTION]);
        } else {
            $jwt_expiration_time = $this->default_jwt_expiration_time;
        }

        $jwt_payload = [
            'iss' => 'user-book',
            'exp' => (new \DateTime("+$jwt_expiration_time seconds"))->getTimestamp(),
            'aud' => 'library',
        ];

        $options[RequestOptions::HEADERS] = [
            'Authorization' => 'Bearer ' . JWT::encode($jwt_payload, $this->jwt_private_key, 'RS256'),
            'Accept' => 'application/json',
        ];
        $options[RequestOptions::JSON] = $payload;

        return $this->client->requestAsync($payload->getRequestMethod(), $payload->getRequestUri(), $options);
    }

    /**
     * @param Payload $payload
     * @param array $options (RequestOptions::X => Y)[]
     * @return Response
     * @throws \LogicException
     */
    public function sendCommand(Payload $payload, array $options = []): Response
    {
        return $this->sendCommandAsync($payload, $options)->wait();
    }
}
