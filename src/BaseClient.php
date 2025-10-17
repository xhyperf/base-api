<?php

namespace XHyperf\BaseApi;

use GuzzleHttp\Exception\RequestException;
use Hyperf\Collection\Arr;
use Hyperf\Context\ApplicationContext;
use Hyperf\Guzzle\ClientFactory;

use XHyperf\BaseApi\Constant\ConfigKey;
use XHyperf\BaseApi\Exception\ApiException;
use XHyperf\Invoker\Invoker;
use XHyperf\Invoker\StrategyType;

use function Hyperf\Support\call;

class BaseClient
{
    /** @var string|StrategyType 注解调用事件名 */
    protected const string STRATEGY_TYPE = '';

    const string API_URI = '';

    /**
     * @var array 基本配置
     */
    protected array $config = [
        ConfigKey::URI          => '${API_URI}${method}',
        ConfigKey::TYPE         => 'POST',
        ConfigKey::HEADERS      => [
            'Accept' => 'application/json',
        ],
        ConfigKey::FORMAT       => 'json',  // 自动格式化 POST 数据
        ConfigKey::DECODE       => true,    // 是否解析返回数据
        ConfigKey::RETURN_FIELD => null,    // 默认返回字段，null 返回整个数组
        ConfigKey::SUCCESS_KEY  => '',      // 判断成功响应的键
        ConfigKey::SUCCESS_VAL  => '',      // 判断成功响应的值，需要与成功键同时配置
        ConfigKey::ERR_KEY      => '',      // 判断错误响应的键，优先判断错误配置，再判断成功配置
        ConfigKey::ERR_VAL      => '',      // 判断错误响应的值，未配置时，只要响应体包含错误键即为错误响应
        ConfigKey::ERR_MSG      => '',      // 错误提示信息取键
        ConfigKey::THROW        => true,    // 是否直接抛出异常
        ConfigKey::ON_ERROR     => null,    // 错误回调
    ];

    /**
     * @var array 扩展类使用的扩展配置
     */
    protected array $configExt = [];

    /**
     * 设置扩展配置
     * @param string $key   配置名
     * @param mixed  $value 配置值
     * @return $this
     */
    public function setConfig(string $key, mixed $value): static
    {
        Arr::set($this->configExt, $key, $value);

        return $this;
    }

    /**
     * 获取完整配置
     * @param string|array $setting 请求级配置
     * @return array
     */
    protected function getConfig(string|array $setting): array
    {
        if (is_string($setting)) {
            $setting = ['method' => $setting];
        }

        return array_replace_recursive($this->config, $this->configExt, $setting);
    }

    /**
     * 发送请求
     * @param array|string $setting 请求级配置
     * @param array        $post    POST数据
     * @return mixed
     * @throws
     */
    protected function send(array|string $setting, array $post = []): mixed
    {
        $conf = $this->getConfig($setting);

        [$uri, $option] = $this->buildRequestArgs($conf, $post);

        $response = $this->request($conf[ConfigKey::TYPE], $uri, $option, $exception);

        //不用解析返回数据
        if (! $conf[ConfigKey::DECODE]) {
            return $response;
        }

        [$ok, $res] = $this->decode($response);

        // 解析失败，且没有异常抛出，返回原始数据
        if (! $ok && $exception !== true) {
            return $response;
        }

        // 异常处理
        if ($conf[ConfigKey::THROW] !== false && ($exception || $this->isThrow($res, $conf))) {
            // 配置有错误回调
            if (is_callable(Arr::get($conf, ConfigKey::ON_ERROR))) {
                $temp = call($conf[ConfigKey::ON_ERROR], [$res, $conf, func_get_args()]);
                if ($temp !== false) {
                    return $temp;
                }
            }
            $this->exception(Arr::get($res, $conf[ConfigKey::ERR_MSG], ''), $res);
        }

        return Arr::get($res, $conf[ConfigKey::RETURN_FIELD]);
    }

    /**
     * 构建请求参数
     * @param array $conf 配置
     * @param array $post POST数据
     * @return array
     */
    protected function buildRequestArgs(array $conf, array $post): array
    {
        $uri = $conf[ConfigKey::URI] = $this->parse($conf[ConfigKey::URI], $conf);

        $option = [
            'headers' => $conf[ConfigKey::HEADERS] ? $this->parse($conf[ConfigKey::HEADERS], $conf) : [],
        ];

        if ($post) {
            $post = $this->parse($post, $conf);
            if (strtoupper($conf[ConfigKey::TYPE]) == 'GET') {
                $uri .= (strpos($uri, '?') ? '&' : '?') . http_build_query($post);
            } elseif (! empty($conf['multipart'])) {
                foreach ($post as $key => $val) {
                    $conf['multipart'][] = [
                        'name'     => $key,
                        'contents' => $val,
                    ];
                }
            } else {
                $option['json'] = $post;
            }
        }

        // multipart/form-data 及 x-www-form-urlencoded 表单
        $option += Arr::only($conf, ['multipart', 'form_params']);

        return [$uri, $option];
    }

    /**
     * http 请求
     * @param string $type   请求类型
     * @param string $uri    请求地址
     * @param array  $option 选项
     * @param bool   $exception
     * @return string
     * @throws
     */
    protected function request(string $type, string $uri = '', array $option = [], ?bool &$exception = false): string
    {
        try {
            $client = ApplicationContext::getContainer()->get(ClientFactory::class)->create();

            $response = $client->request($type, $uri, $option)->getBody();
        } catch (RequestException $e) {
            $exception = true;
            $response  = $e->getResponse()->getBody();
        }

        return (string)$response;
    }

    /**
     * 解析返回数据
     * @param string $response 响应数据
     * @return array [是否成功, 解析后的数据]
     */
    protected function decode(string $response): array
    {
        if (! json_validate($response)) {
            return [false, null];
        }

        return [true, json_decode($response, true)];
    }

    protected function parse($string, $conf = [])
    {
        if (is_array($string)) {
            foreach ($string as &$line) {
                $line = $this->parse($line, $conf);
            }

            return $string;
        }

        if (! is_string($string)) {
            return $string;
        }

        return preg_replace_callback('/\${([^{}]*)}/', function ($match) use ($conf) {
            $match = $match[1];

            if (static::STRATEGY_TYPE && ($result = Invoker::strategy(static::STRATEGY_TYPE::tryFrom($match), $conf + ['caller' => $this]))) {
                return $result;
            }

            return match ($match) {
                'API_URI' => static::API_URI,
                isset($conf[$match]) ? $match : '' => $conf[$match],
                default => (function () use ($conf, $match) {
                    $key    = str_replace(['.', '#'], ['/', '.'], $match);
                    $key    = explode('/', $key);
                    $prefix = array_shift($key);

                    return match ($prefix) {
                        'data' => $this->getData(...$key),
                        'conf' => Arr::get($conf, implode('.', $key)),
                        default => ''
                    };
                })()
            };
        },                           $string);
    }

    protected function isThrow($res, $conf): bool
    {
        if (is_callable($conf[ConfigKey::THROW])) {
            return call($conf[ConfigKey::THROW], [$res, $conf]);
        }

        // 有配置判断错误请求的值，那判断响应 key 是否和错误值相等，否则只需要有错误 key 就错有错
        if (! empty($conf[ConfigKey::ERR_KEY])
            && (empty($conf[ConfigKey::ERR_VAL])
                ? Arr::get($res, $conf[ConfigKey::ERR_KEY])
                : Arr::get($res, $conf[ConfigKey::ERR_KEY]) == $conf[ConfigKey::ERR_VAL])) {
            return true;
        }

        return ! empty($conf[ConfigKey::SUCCESS_KEY]) && Arr::get($res, $conf[ConfigKey::SUCCESS_KEY]) != $conf[ConfigKey::SUCCESS_VAL];
    }

    protected function getData(string $method, ...$args): mixed
    {
        return compact('method', 'args');
    }

    /**
     * @throws ApiException
     */
    protected function exception($message)
    {
        throw new ApiException($message);
    }
}