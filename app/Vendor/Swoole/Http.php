<?php
/**
 * Created by PhpStorm.
 * User: ifehrim@gmail.com
 * Date: 1/12/2018
 * Time: 2:51 PM
 */

namespace App\Vendor\Swoole;


use App\Unit\Json;
use Illuminate\Contracts\Console\Kernel;
use Log;
use Swoole\Async;
use Exception;
use swoole_http_client;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

class Http
{


    CONST HTTP_GET = "GET";
    CONST HTTP_POST = "POST";
    CONST HTTP_PUT = "PUT";
    CONST HTTP_DELETE = "DELETE";

    const DEBUG=false;

    public function __construct()
    {

    }

    public static function get($url, $headers = [], $callable = null)
    {
        return self::request($url, self::HTTP_GET, null, $headers, $callable);
    }

    public static function post($url, $body, $headers = [], $callable = null)
    {
        return self::request($url, self::HTTP_POST, $body, $headers, $callable);
    }

    public static function put($url, $body, $headers = [], $callable = null)
    {
        return self::request($url, self::HTTP_PUT, $body, $headers, $callable);
    }

    public static function delete($url, $body, $headers = [], $callable = null)
    {
        return self::request($url, self::HTTP_DELETE, $body, $headers, $callable);
    }

    public static function request($url, $method, $body = null, $headers = [], $callable = null)
    {

        if(self::DEBUG){
            Log::info("request:url:".$url);
            if(Json::is($body)){
                Log::info("request:body:".$body);
            }else{
                Log::info("request:body:",$body);
            }
        }

        $parse_urls = parse_url($url);
        $host = $parse_urls['host'];
        $port = $parse_urls['scheme'] == "https" ? 443 : 80;
        $uri = $parse_urls['path'] . (isset($parse_urls["query"]) ? "?" . $parse_urls["query"] : "");
        $ssl = $port == 443 ? true : false;

        $base_path=dirname(app_path());

        swoole_async_dns_lookup($host, function ($domainName,$ip) use ($uri, $method, $port, $ssl, $body, $headers, $callable,$base_path) {

            $cli = new swoole_http_client($ip, $port, $ssl);
            $headers["Host"]=$domainName;
            if(!empty($headers)) $cli->setHeaders($headers);
            try{
                $cli->set(['timeout' => 8.0]);
                $cli->setMethod(strtoupper($method));
                if(!empty($body))$cli->setData($body);
                $cli->execute($uri, function (swoole_http_client $cli) use ($callable,$base_path) {
                    if (is_callable($callable)) {
                        $response = (array)$cli;
                        $response["http_code"]=$cli->statusCode;
                        call_user_func($callable, $response);
                        try{
                            $cli->close();
                        }catch (\Exception $exception){
                            Log::error("Swoole\Http\Client::CloseException",[$exception->getMessage()]);
                        }
                    }
                });

            }catch (Exception $exception){
                Log::info(__CLASS__."Exception",[$exception->getMessage()]);
            }
        });
        return true;
    }




}