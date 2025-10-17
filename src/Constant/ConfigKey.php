<?php

declare(strict_types=1);

namespace XHyperf\BaseApi\Constant;

class ConfigKey
{
    /**
     * 请求地址
     */
    const string URI = 'uri';

    /**
     * 请求类型
     */
    const string TYPE = 'type';

     /**
      * 请求头
      */
    const string HEADERS = 'headers';

     /**
      * 请求体格式化类型
      */
    const string FORMAT = 'format';

     /**
      * 是否解析返回数据
      */
    const string DECODE = 'decode';

     /**
      * 默认返回字段，null 返回整个数组
      */
    const string RETURN_FIELD = 'returnField';

     /**
      * 判断成功响应的键
      */
    const string SUCCESS_KEY = 'successKey';

     /**
      * 判断成功响应的值
      */
    const string SUCCESS_VAL = 'successVal';

    /**
     * 判断错误响应的键
     */
    const string ERR_KEY = 'errKey';

     /**
      * 判断错误响应的键
      */
    const string ERR_VAL = 'errVal';

     /**
      * 错误提示信息取值
      */
    const string ERR_MSG = 'errMsg';

     /**
      * 是否直接抛出异常
      */
    const string THROW = 'throw';

     /**
      * 错误回调
      */
    const string ON_ERROR = 'onError';
}