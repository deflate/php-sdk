<?php

namespace Deflate;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

/**
 * Class Deflate
 *
 * @package Deflate
 *
 * @author Dave Macaulay <dave@gene.co.uk>
 */
class Deflate
{
    /**
     * The URL for the API
     */
    const API_URL = 'https://api.deflate.io/v1/';

    /**
     * The current SDK version
     */
    const SDK_VERSION = '1.0.0';

    /**
     * Declare compression types
     */
    const LOSSY = 'lossy';
    const LOSSLESS = 'lossless';

    /**
     * API Key
     *
     * @var string
     */
    private $apiKey;

    /**
     * API secret
     *
     * @var string
     */
    private $apiSecret;

    /**
     * Default options for compression requests
     *
     * Possible options:
     * - id
     * - wait
     * - callback
     * - custom
     *
     * @var array
     */
    private $options = [];

    /**
     * Store the last error
     *
     * @var string|bool
     */
    private $lastError = false;

    /**
     * Deflate constructor.
     *
     * @param string $apiKey
     * @param string $apiSecret
     * @param array $options
     *
     * @throws \Exception
     */
    public function __construct($apiKey, $apiSecret, $options = [])
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->options = $options;

        // Check we can login with the details provided
        if (!$this->account()) {
            throw new \Exception('Unable to connect to Deflate API using provided credentials.');
        }
    }

    /**
     * Grab the account information
     *
     * @return bool|mixed
     */
    public function account()
    {
        if ($response = $this->request('account')) {
            if (isset($response->success) && $response->success == true) {
                return $response->account;
            }
        }
        return false;
    }

    /**
     * Compress an individual image
     *
     * @param string|array $image
     * @param string $type
     * @param array $options
     *
     * @return bool|mixed
     * @throws \Exception
     */
    public function compress($image, $type, $options = [])
    {
        // Build our request
        $request = [
            'type'  => $type
        ];

        if (is_array($image)) {
            $request['images'] = $image;
        } else {
            $request['image'] = $image;
        }

        $request = $this->buildRequest($request, $options);

        // Verify that we have a callback
        if (isset($options['wait']) && $options['wait'] === false &&
            isset($options['callback']) && $options['callback'] === false
        ) {
            throw new \Exception('A callback must be defined when you\'re not wanting to wait.');
        }

        if ($response = $this->request('deflate', $request)) {
            if (isset($response->success) && $response->success == true) {
                return $response;
            }
        }

        return false;
    }

    /**
     * Compress multiple images
     *
     * @param array $images
     * @param       $type
     * @param array $options
     *
     * @return bool|mixed
     * @throws \Exception
     */
    public function compressMultiple(array $images, $type, $options = [])
    {
        return $this->compress($images, $type, $options);
    }

    /**
     * Return the total limit of images that can be compressed at once
     *
     * @return bool
     */
    public function limit()
    {
        if ($response = $this->request('limit')) {
            if (isset($response->limit) && $response->limit == true) {
                return $response->limit;
            }
        }
        return false;
    }

    /**
     * Return the supported image formats
     *
     * @param bool $type
     *
     * @return bool|mixed
     */
    public function supported($type = false)
    {
        if ($response = $this->request('supported')) {
            if (isset($response->success) && $response->success == true) {
                switch ($type) {
                    case 'extensions':
                        return $response->extensions;
                        break;
                    case 'mime':
                        return $response->types;
                        break;
                    default:
                        return $response;
                        break;
                }
            }
        }
        return false;
    }

    /**
     * Return the last error the API encountered
     *
     * @return string
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Return the callback response as an array
     *
     * @return mixed
     */
    public function callbackResponse()
    {
        return json_decode(file_get_contents('php://input'));
    }

    /**
     * Build up the request from the supplied options
     *
     * @param array $request
     * @param array $options
     *
     * @return array
     */
    private function buildRequest(array $request, array $options)
    {
        $allowedOptions = ['id', 'wait', 'callback', 'custom'];

        // Merge the global options with the local options
        $finalOptions = array_intersect_key(array_merge($this->options, $options), array_flip($allowedOptions));

        // Merge the options into the final request
        return array_merge($request, $finalOptions);
    }

    /**
     * Make a request to the API
     *
     * @param            $action
     * @param bool|false $data
     *
     * @return mixed
     */
    private function request($action, $data = false)
    {
        // Start building our body
        $body = [
            'auth' => [
                'api_key'    => $this->apiKey,
                'api_secret' => $this->apiSecret
            ]
        ];

        // Merge in the data
        if ($data !== false && is_array($data)) {
            $body = array_merge($body, $data);
        }

        // Start the client
        $client = new Client([
            'base_uri' => self::API_URL
        ]);

        try {
            $request = $client->request('POST', $action, [
                'body' => json_encode($body)
            ]);

            // Ensure the response is a 200 status
            if ($request->getStatusCode() == 200) {
                return json_decode($request->getBody()->getContents());
            }
        } catch (ClientException $e) {
            $this->lastError = json_decode($e->getResponse()->getBody()->getContents());
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
        }

        return false;
    }
}
