<?php
namespace Pepabo\OAuth2\Client\Test\Provider;

use Pepabo\OAuth2\Client\Provider\ColorMeShop;
use Mockery as m;

class ColorMeShopTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->provider = new ColorMeShop([
            'clientId'     => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri'  => 'none',
        ]);
    }
    /**
     * @test
     */
    public function getAuthorizationUrl()
    {
        parse_str(
            parse_url($this->provider->getAuthorizationUrl())['query'],
            $query
        );

        $this->assertArrayHasKey('client_id',       $query);
        $this->assertArrayHasKey('redirect_uri',    $query);
        $this->assertArrayHasKey('state',           $query);
        $this->assertArrayHasKey('scope',           $query);
        $this->assertArrayHasKey('response_type',   $query);
        $this->assertArrayHasKey('approval_prompt', $query);
    }

    /**
     * @test
     */
    public function getState()
    {
        $this->provider->getAuthorizationUrl();
        $this->assertNotNull($this->provider->getState());
    }

    /**
     * @test
     */
    public function getBaseAccessTokenUrl()
    {
        $uri = parse_url($this->provider->getBaseAccessTokenUrl([]));
        $this->assertEquals('/oauth/token', $uri['path']);
    }

    /**
     * @test
     */
    public function getAccessToken()
    {
        $response = m::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getBody')->andReturn(
            json_encode([
                'access_token'  => ($mockAccessToken = 'mock_access_token'),
                'token_type'    => 'bearer',
                'expires_in'    => null,
                'refresh_token' => null,
                'scope'         => '',
            ])
        );
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(1)->andReturn($response);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $this->assertEquals($mockAccessToken, $token->getToken());
        $this->assertNull($token->getExpires());
        $this->assertNull($token->getRefreshToken());
        $this->assertNull($token->getResourceOwnerId());
    }
}
