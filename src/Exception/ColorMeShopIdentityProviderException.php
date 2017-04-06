<?php
namespace Pepabo\OAuth2\Client\Exception;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Http\Message\ResponseInterface;

class ColorMeShopIdentityProviderException extends IdentityProviderException
{
    /**
     * Creates client exception from response.
     *
     * @param  ResponseInterface $response
     * @param  string $data Parsed response data
     * @return IdentityProviderException
     */
    public static function clientException(ResponseInterface $response, $data)
    {
        return new static($response->getReasonPhrase(), $response->getStatusCode(), (string) $response->getBody());
    }

    /**
     * Creates oauth exception from response.
     *
     * @param  ResponseInterface $response
     * @param  string $data Parsed response data
     * @return IdentityProviderException
     */
    public static function oauthException(ResponseInterface $response, $data)
    {
        $message = $response->getReasonPhrase();
        if (isset($data['errors']) && ($decoded = json_encode($data['errors']))) {
            $message .= $decoded;
        }

        return new static($message, $response->getStatusCode(), (string)$response->getBody());
    }
}
