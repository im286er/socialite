<?php

namespace Namet\Socialite\Drivers;

use Namet\Socialite\DriverInterface;
use Namet\Socialite\DriverBase;
use Namet\Socialite\SocialiteException;

class QQ extends DriverBase implements DriverInterface
{
    // client_id
    protected $_appid = null;
    // client_secret
    protected $_secret = null;
    // OPEN ID
    protected $_openid = null;
    // 跳转链接
    protected $_redirect_uri = null;
    // 接口返回的原始数据存储
    protected $_response = [];
    // 用户授权后，得到的code参数
    protected $_code = null;
    // 用户的token
    protected $_access_token = null;
    // oauth_api地址
    protected $_base_url = 'https://graph.qq.com/';
    // 此Driver的名称
    protected $_name = 'qq';

    /**
     * 跳转到用户授权界面
     */
    public function authorize($redirect = true)
    {
        return $this->redirect('oauth2.0/authorize', $redirect);
    }

    /**
     * 获取access token
     *
     * @return string Access Token
     * @throws \Namet\Socialite\SocialiteException
     */
    public function getToken()
    {
        if (!$this->_access_token) {
            $params = [
                'client_id' => $this->_appid,
                'client_secret' => $this->_secret,
                'code' => $this->getCode(),
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->_redirect_uri,
            ];

            !empty($this->_state) && $params['state'] = $this->_state;

            $res = $this->get('oauth2.0/token', [
                'query' => $params,
                'headers' => ['Accept' => 'application/json'],
            ]);
            $res = $this->_parse($res);
            // 检查是否有错误
            $this->_checkError($res);
            // 记录返回的数据
            $this->_response['token'] = $res;
            // 将得到的数据赋值到属性
            $this->config($res);
        }

        return $this->_access_token;
    }

    /**
     * 解析返回值
     *
     * @param  string $res 原返回值
     * @return array
     */
    private function _parse($res)
    {
        $data = [];
        foreach (explode('&', $res) as $item) {
            list($k, $v) = explode('=', $item);
            $data[$k] = $v;
        }

        return $data;
    }

    /**
     * 根据access_token获取openid
     *
     * @return mixed
     * @throws \Namet\Socialite\SocialiteException
     */
    public function getOpenId()
    {
        if (!$this->_openid) {
            $params = [
                'access_token' => $this->getToken(),
            ];
            $res = $this->get('oauth2.0/me', ['query' => $params]);
            $data = json_decode(trim(substr(trim($res), 9, -2)), true);
            if (!is_array($data)) {
                throw new SocialiteException(
                    'get openid response error, the original data returned from remote is :' . print_r($res, true)
                );
            }
            // 检查是否有错误
            $this->_checkError($data);
            // 将得到的数据赋值到属性
            $this->config($data);
        }

        return $this->_openid;
    }

    /**
     * 判断接口返回的数据是否有错误
     *
     * @param array $res 请求的结果
     *
     * @throws \Namet\Socialite\SocialiteException
     */
    private function _checkError($res)
    {
        if (!empty($res['error_code'])) {
            throw new SocialiteException($res['error_code'] . ' : ' . $res['error']);
        }
    }

    /**
     * 根据access_token获取用户基本信息
     *
     * @param string $lang
     *
     * @throws \Namet\Socialite\SocialiteException
     *
     * @return array
     */
    public function getUserInfo($lang = '')
    {
        if (!$this->_user_info) {
            $res = $this->get($this->_base_url . 'user/get_user_info', [
                'query' => [
                    'access_token' => $this->getToken(),
                    'oauth_consumer_key' => $this->_appid,
                    'openid' => $this->getOpenId(),
                ],
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);
            // 检查返回值是否有错误
            $this->_checkError($res);
            // 记录返回的数据
            $this->_response['user'] = $res;

            return $this->_formatUserInfo();
        }

        return $this->_user_info;
    }

    public function _formatUserInfo()
    {
        $this->_user_info = [
            'uid' => $this->getOpenId(),
            'uname' => $this->_response['user']['nickname'],
            'avatar' => $this->_response['user']['figureurl_qq_2'],
            'email' => '',
        ];

        return $this->_user_info;
    }

    /**
     * 刷新access_token
     *
     * @return $this
     * @throws \Namet\Socialite\SocialiteException
     */
    public function refreshToken()
    {
        $params = [
            'appid' => $this->_appid,
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->_refresh_token,
        ];
        // 获取返回值数组
        $res = $this->get('oauth2.0/token', ['query' => $params]);
        // 检查返回值中是否有错误
        $this->_checkError($res);
        // 记录返回的数据
        $this->_response['refresh'] = $res;
        // 更新配置
        $this->config($res);

        return $this;
    }
}
