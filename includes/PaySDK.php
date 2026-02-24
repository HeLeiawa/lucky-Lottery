<?php
/**
 * 彩虹易支付SDK
 */
class PaySDK {
    private $pay_url;
    private $pid;
    private $key;

    public function __construct($pay_url, $pid, $key) {
        $this->pay_url = rtrim($pay_url, '/');
        $this->pid = $pid;
        $this->key = $key;
    }

    /**
     * 生成签名
     */
    public function generateSign($params) {
        ksort($params);
        $sign_str = '';
        foreach ($params as $k => $v) {
            if ($k == 'sign' || $k == 'sign_type' || $v === '') continue;
            $sign_str .= $k . '=' . $v . '&';
        }
        $sign_str = substr($sign_str, 0, -1);
        return md5($sign_str . $this->key);
    }

    /**
     * 验证签名
     */
    public function verifySign($params) {
        if (!isset($params['sign'])) return false;
        $sign = $params['sign'];
        unset($params['sign']);
        if (isset($params['sign_type'])) unset($params['sign_type']);
        return $this->generateSign($params) === $sign;
    }

    /**
     * 页面跳转支付
     */
    public function getPayUrl($params) {
        $params['pid'] = $this->pid;
        $params['sign'] = $this->generateSign($params);
        $params['sign_type'] = 'MD5';
        return $this->pay_url . '/submit.php?' . http_build_query($params);
    }

    /**
     * API接口支付
     */
    public function apiPay($params) {
        $params['pid'] = $this->pid;
        $params['sign'] = $this->generateSign($params);
        $params['sign_type'] = 'MD5';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->pay_url . '/mapi.php');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $result = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($result, true);
    }

    /**
     * 查询订单
     */
    public function queryOrder($out_trade_no) {
        $url = $this->pay_url . '/api.php?act=order&pid=' . $this->pid . '&key=' . $this->key . '&out_trade_no=' . $out_trade_no;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $result = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($result, true);
    }
}