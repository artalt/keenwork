<?php

declare(strict_types=1);

namespace Keenwork;

use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class Request extends GuzzleRequest implements ServerRequestInterface
{
    /**
     * @var array
     */
    private array $attributes;

    /**
     * @var array
     */
    private array $cookieParams;

    /**
     * @var null|array|object
     */
    private $parsedBody;

    /**
     * @var array
     */
    private array $queryParams;

    /**
     * @var array
     */
    private array $serverParams;

    /**
     * @var array
     */
    private array $uploadedFiles;

    /**
     * @param string                               $method       HTTP method
     * @param string|UriInterface                  $uri          URI
     * @param array                                $headers      Request headers
     * @param string                               $body         Request body
     * @param string                               $version      Protocol version
     * @param array                                $serverParams Typically the $_SERVER superglobal
     * @param array                                $cookies      Request cookies
     * @param array                                $files        Request files
     * @param array                                $query        Query Params
     */
    public function __construct(
        string $method,
        $uri,
        array $headers,
        string $body,
        string $version,
        array $serverParams,
        array $cookies,
        array $files,
        array $query
    ) {
        $this->serverParams = $serverParams;
        $this->cookieParams = $cookies;
        $this->uploadedFiles = $files;
        $this->queryParams = $query;
        $this->attributes = [];
        $this->parsedBody = null;

        parent::__construct($method, $uri, $headers, $body, $version);
    }

    /**
     * {@inheritdoc}
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * {@inheritdoc}
     */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * {@inheritdoc}
     */
    public function withUploadedFiles(array $uploadedFiles): Request
    {
        return (clone $this)->setUploadedFiles($uploadedFiles);
    }

    /**
     * {@inheritdoc}
     */
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * {@inheritdoc}
     */
    public function withCookieParams(array $cookies): Request
    {
        return (clone $this)->setCookieParams($cookies);
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * {@inheritdoc}
     */
    public function withQueryParams(array $query): Request
    {
        return (clone $this)->setQueryParams($query);
    }

    /**
     * {@inheritdoc}
     */
    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    /**
     * {@inheritdoc}
     */
    public function withParsedBody($data): Request
    {
        return (clone $this)->setParsedBody($data);
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute($attribute, $default = null)
    {
        if (!isset($this->getAttributes()[$attribute])) {
            return $default;
        }

        return $this->getAttributes()[$attribute];
    }

    /**
     * {@inheritdoc}
     */
    public function withAttribute($attribute, $value): Request
    {
        if (!is_string($attribute)) {
            throw new \InvalidArgumentException('ERROR: Request::withAttribute(): invalid argument [attribute]');
        }

        return (clone $this)->addAttribute($attribute, $value);
    }
    /**
     * {@inheritdoc}
     */
    public function withoutAttribute($attribute): self
    {
        if (!isset($this->getAttributes()[$attribute])) {
            return $this;
        }

        return (clone $this)->unsetAttribute($attribute);
    }

    /**
     * @param array $attributes
     */
    private function setAttributes(array $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * @param array $cookieParams
     */
    private function setCookieParams(array $cookieParams): self
    {
        $this->cookieParams = $cookieParams;

        return $this;
    }

    /**
     * @param array|object|null $parsedBody
     */
    private function setParsedBody($parsedBody): self
    {
        $this->parsedBody = $parsedBody;

        return $this;
    }

    /**
     * @param array $queryParams
     */
    private function setQueryParams(array $queryParams): self
    {
        $this->queryParams = $queryParams;

        return $this;
    }

    /**
     * @param array $serverParams
     */
    private function setServerParams(array $serverParams): self
    {
        $this->serverParams = $serverParams;

        return $this;
    }

    /**
     * @param array $uploadedFiles
     */
    private function setUploadedFiles(array $uploadedFiles): self
    {
        $this->uploadedFiles = $uploadedFiles;

        return $this;
    }

    /**
     * Add attribute to this
     * @param $attribute
     * @param $value
     * @return $this
     */
    private function addAttribute($attribute, $value): self
    {
        $this->attributes[$attribute] = $value;

        return $this;
    }

    private function unsetAttribute(string $name): self
    {
        if (isset($this->attributes[$name])) {
            unset($this->attributes[$name]);
        }

        return $this;
    }
}
