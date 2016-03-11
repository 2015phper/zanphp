<?php
/**
 * @author hupp
 * create date: 16/03/02
 */

namespace Zan\Framework\Network\Http;

use \swoole_client;
use Zan\Framework\Foundation\Contract\Async;
use Zan\Framework\Foundation\Core\Config;

class HttpClient implements Async {

    const EOF = "\r\n";

    /**
     * @var swoole_client
     */
    private $client = null;
    private $callback = null;

    protected $host;
    protected $port = 80;
    protected $path;
    protected $method;
    protected $request;
    protected $timeout;
    protected $postData = '';
    protected $userAgent = 'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.116 Safari/537.36';

    private $clientConfKey = 'http.client';

    public function __construct($path, $parameter = [], $method = 'POST')
    {
        $config = Config::get($this->clientConfKey);

        $this->host    = $config['host'];
        $this->port    = $config['port'] ? $config['port'] : $this->port;
        $this->timeout = $config['timeout'];
        $this->method  = $method;

        $this->setPath($path);
        $this->buildParams($parameter);
    }

    public function execute(callable $callback)
    {
        $this->callback = $callback;

        $this->call();
    }

    public function call()
    {
        $this->client = new swoole_client(SWOOLE_TCP, SWOOLE_SOCK_ASYNC);

        $this->bindEvent();
        $this->client->connect($this->host, $this->port, $this->timeout);
    }

    private function setPath($path)
    {
        if (false === strpos($path, '.')) {
            return false;
        }
        $this->path = '/' . str_replace('.', '/', $path);
    }

    private function buildParams($parameter)
    {
        if (is_string($parameter)) {
            $this->postData = $parameter;
        }
        else if (is_array($parameter) || is_object($parameter)) {
            $this->postData = http_build_query($parameter);
        }
        $this->postData .= '&debug=json';
    }

    private function buildHeader()
    {
        $header  = $this->method.' '. $this->path .' HTTP/1.1'. self::EOF;
        $header .= 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8' . self::EOF;
        $header .= 'Accept-Encoding: gzip,deflate' . self::EOF;
        $header .= 'Accept-Language: zh-CN,zh;q=0.8,en;q=0.6,zh-TW;q=0.4,ja;q=0.2' . self::EOF;
        $header .= 'Host: '. $this->host . self::EOF;
        $header .= $this->userAgent . self::EOF;

        if ($this->postData) {
            $header .= 'Content-Type: application/x-www-form-urlencoded' . self::EOF;
            $header .= 'Content-Length: ' . strlen($this->postData) . self::EOF;
        }
        return $header;
    }

    private function bindEvent()
    {
        $this->client->on('connect', [$this, 'onConnect']);
        $this->client->on('receive', [$this, 'onReceive']);
        $this->client->on('error',   [$this, 'onError']);
        $this->client->on('close',   [$this, 'onClose']);
    }

    public function onConnect()
    {
        $this->client->send($this->buildHeader() . self::EOF . $this->postData);
    }

    public function onReceive($cli, $data)
    {
        list($header, $body) = Parser::parseResponseData($data);

        call_user_func($this->callback, $body);
    }

    public function OnError()
    {
        call_user_func($this->callback, "Connect to server failed.");
    }

    public function onClose()
    {
        $this->client->close();
    }

}