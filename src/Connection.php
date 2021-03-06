<?php

/**
 * @file
 * Connection class for Glassdoor API.
 */

namespace Glassdoor;

use Glassdoor\Action\ActionInterface;
use Glassdoor\Error\GlassDoorResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;

/**
 * Makes the calls to Glassdoor.
 */
final class Connection {

  /**
   * Configuration class.
   *
   * @var \Glassdoor\Config
   */
  private $config;

  /**
   * Stream handler.
   *
   * @var HandlerStack|null
   */
  private $stack;

  /**
   * Construction method.
   *
   * @param \Glassdoor\Config $config
   *   Configuation class.
   */
  public function __construct(Config $config) {
    $this->config = $config;
  }

  /**
   * Set handler stack.
   *
   * @param \GuzzleHttp\HandlerStack $stack
   *   Stack to inject.
   */
  public function setHandlerStack(HandlerStack $stack) {
    $this->stack = $stack;
  }

  /**
   * Build a URI object for the Request.
   *
   * @param \Glassdoor\Action\ActionInterface $action
   *   Action to send to API.
   *
   * @return \GuzzleHttp\Psr7\Uri
   *   Uri object.
   */
  private function buildUri(ActionInterface $action) {
    $parts = parse_url($this->config->getBaseUrl());

    $params = $action->getParams();
    $params['v'] = $action->getVersion();
    $params['format'] = $this->config->getResponseFormat();
    $params['t.p'] = $this->config->getPartnerId();
    $params['t.k'] = $this->config->getPartnerKey();
    $params['userip'] = $this->config->getUserIp();
    $params['useragent'] = $this->config->getUserAgent();
    $params['action'] = $action->action();

    // Allow any overrides.
    if (!empty($parts['query'])) {
      parse_str($parts['query'], $query_params);
      array_merge($params, $query_params);
    }

    $parts['query'] = http_build_query($params);

    return Uri::fromParts($parts);
  }

  /**
   * Make a call to the GlassDoor API.
   *
   * @param \Glassdoor\Action\ActionInterface $action
   *   Action to send to API.
   *
   * @return \Glassdoor\ResponseObject\ResponseInterface
   *   Response from API.
   *
   * @throws \Glassdoor\Error\GlassDoorResponseException
   *   Exception.
   */
  public function call(ActionInterface $action) {
    // If a handler is set then use that to build the client.
    if ($this->stack) {
      $client = new Client([
        'handler' => $this->stack,
      ]);
    }
    else {
      $client = new Client();
    }

    $request = new Request($action->getMethod(), $this->buildUri($action));

    $response = $client->send($request);

    if ($response->getStatusCode() !== 200) {
      throw new GlassDoorResponseException($response->getReasonPhrase(), $response->getStatusCode());
    }

    $body = json_decode($response->getBody(), TRUE);

    if (!$body || $body['status'] !== 'OK') {
      if ($body) {
        $status = $body['status'];
      }
      else {
        $status = $response->getReasonPhrase();
      }
      throw new GlassDoorResponseException($status, $response->getStatusCode());
    }

    return $action->buildResponse($body, $response);
  }

}
