<?php

namespace teamones\rpc\common;


class Rpc
{

    public $fault = '';

    // 指定JSON-RPC协议版本的字符串，必须准确写为“2.0”
    protected $jsonrpc = '2.0';

    // 服务端必须回答相同的值如果包含在响应对象。 这个成员用来两个对象之间的关联上下文
    protected $id = null;

    // Parse error语法解析错误，服务端接收到无效的json。该错误发送于服务器尝试解析json文本
    const ERR_PARSE = -32700;

    // Invalid Request无效请求，发送的json不是一个有效的请求对象。
    const ERR_REQUEST = -32600;

    // Method not found找不到方法
    const ERR_METHOD = -32601;

    // Invalid params无效的参数。
    const ERR_PARAMS = -32602;

    // Internal error内部错误。
    const ERR_INTERNAL = -32603;

    // Server error服务端错误。
    const ERR_SERVER = -32000;

    // mode 类型
    const MODE_CHECK = 0;
    const MODE_GET = 1;
    const MODE_EXISTS = 2;

    /**
     * @param $name
     * @return void
     */
    public function __get($name)
    {

        if (isset($this->$name)) {
            return $this->$name;
        }
    }

    /**
     * decode
     * @param $message
     * @param $batch
     * @return mixed
     */
    public static function decode($message, &$batch)
    {

        $struct = json_decode($message, false);
        $batch = is_array($struct);

        return $struct;
    }

    /**
     * get error message
     * @param $name
     * @param bool $exists
     * @return string
     */
    public static function getErrorMsg($name, bool $exists = true)
    {
        if ($name) {
            if ($exists) {
                return 'Invalid value for: ' . $name;
            } else {
                return 'Missing member: ' . $name;
            }

        } else {
            return 'Invalid structure';
        }
    }

    /**
     * @param $name
     * @param $value
     * @param $exists
     * @return mixed
     * @throws \Exception
     */
    protected function check($name, $value, $exists)
    {
        $res = false;

        if ($exists) {
            switch ($name) {
                case 'jsonrpc':
                    $res = $value === $this->jsonrpc;
                    break;
                case 'method':
                    $res = is_string($value) && $value;
                    break;
                case 'params':
                    $res = is_array($value) || is_object($value);
                    break;
                case 'id':
                    $res = $this->checkId($value);
                    break;
                case 'result':
                    $res = true;
                    break;
                case 'error':
                    $res = $this->checkError($value);
                    break;
            }

        }

        if (!$res) {
            throw new \Exception($this->getErrorMsg($name, $exists));
        } else {
            return $value;
        }

    }


    /**
     * @param $container
     * @param $key
     * @param int $mode
     * @return bool|mixed|null
     * @throws \Exception
     */
    protected function get($container, $key, int $mode = 0)
    {

        $exists = false;
        $value = null;

        if (is_array($container)) {
            $exists = array_key_exists($key, $container);
            $value = $exists ? $container[$key] : null;
        } elseif (is_object($container)) {
            $exists = property_exists($container, $key);
            $value = $exists ? $container->$key : null;
        }

        if ($mode === static::MODE_GET) {
            return $value;
        } elseif ($mode === static::MODE_EXISTS) {
            return $exists;
        } else {
            return $this->check($key, $value, $exists);
        }

    }


    /**
     * @param $error
     * @return bool|void
     */
    private function checkError($error)
    {

        if (!is_array($error)) {
            $error = (array)$error;
        }

        $code = $error['code'] ?? null;
        $message = $error['message'] ?? null;

        $allowed = [-32700, -32600, -32601, -32602, -32603];

        if (!in_array($code, $allowed)) {

            $max = -32000;
            $min = -32099;

            if ($code < $min || $code > $max) {
                return;
            }

        }

        return is_int($code) && $code && is_string($message);

    }


    /**
     * @param $id
     * @return bool
     * @throws \Exception
     */
    private function checkId($id): bool
    {

        if ((is_string($id) && $id) || is_int($id)) {
            return true;
        } elseif (!is_null($id)) {
            return false;
        }

        $allowNull = false;

        if (isset($this->error)) {
            $code = $this->get($this->error, 'code', static::MODE_GET);
            $allowNull = $code === static::ERR_PARSE || $code === static::ERR_REQUEST;
        }

        return $allowNull;

    }

    /**
     * @param $struct
     * @param $new
     * @throws \Exception
     */
    protected function setVersion($struct, $new)
    {
        if (!$new) {
            $this->jsonrpc = $this->get($struct, 'jsonrpc');
        }
    }

}
