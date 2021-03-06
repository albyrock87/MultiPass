<?php

namespace MultiPass\Strategies;

class OAuth2 extends \MultiPass\Strategy
{
  public $options = array();

  protected $client      = null;
  protected $name        = 'oauth2';
  protected $accessToken = null;

  public function __construct($opts = array())
  {
    parent::__construct($opts);
    
    // Default options
    $this->options = array_replace_recursive(array(
        'client_id'         => null
      , 'client_secret'     => array()
      , 'client_options'    => array()
      , 'authorize_params'  => array()
      , 'authorize_options' => array()
      , 'token_params'      => array()
      , 'token_options'     => array()
    ), $this->options);

    // Instanciate client
    $this->client = new \OAuth2\Client($this->options['client_id'], $this->options['client_secret'], $this->options['client_options']);
  }

  public function getAuthType()
  {
    return 'oauth2';
  }

  public function getClient()
  {
    return $this->client;
  }

  public function getCallbackUrl()
  {
    return $this->getFullHost().$this->getCallbackPath();
  }

  public function uid() {}

  public function info() {}

  public function credentials()
  {
    $hash = array('token' => $this->accessToken->getToken());
    if ($this->accessToken->expires() && $this->accessToken->getRefreshToken()) {
      $hash['refresh_token'] = $this->accessToken->getRefreshToken();
    }
    if ($this->accessToken->expires()) {
      $hash['expires_at'] = $this->accessToken->getExpiresAt();
    }
    $hash['expires'] = $this->accessToken->expires();
    return $hash;
  }

  public function extra($rawInfo = null)
  {
    $rawInfo = $rawInfo ?: $this->rawInfo();
    
    return array('raw_info' => $rawInfo);
  }

  public function requestPhase()
  {
    header('Location: '.$this->client->authCode()->authorizeUrl(array_merge(array('redirect_uri' => $this->getCallbackUrl()), $this->authorizeParams())));
    exit;
  }

  public function authorizeParams()
  {
    return array_merge($this->options['authorize_params'], $this->options['authorize_options']);
  }

  public function tokenParams()
  {
    return array_merge($this->options['token_params'], $this->options['token_options']);
  }

  public function callbackPhase()
  {
    try {
      if (isset($_GET['error'])) {
        throw new \MultiPass\Error\CallbackError($_GET['error'], isset($_GET['error_description']) ? $_GET['error_description'] : null, isset($_GET['error_uri']) ? $_GET['error_uri'] : null);
      }
      
      $this->accessToken = $this->buildAccessToken();
      if ($this->accessToken->isExpired()) {
        $this->accessToken = $this->accessToken->refresh();
      }
      
      return parent::callbackPhase();
    } catch (\ErrorException $e) {
      print_r($e);
    }
  }

  protected function buildAccessToken()
  {
    $verifier = $_GET['code'];
    return $this->client->authCode()->getToken($verifier, array_merge(array('redirect_uri' => $this->getCallbackUrl()), $this->options['token_params']), $this->options['token_options']);
  }
}
