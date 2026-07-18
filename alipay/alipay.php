<?php
require_once __DIR__ . "/../func.php";

function alipay_order_info($__post){
    $__config = get_alipay_config();
    $time = time();
    $appid = $__config['app_id'];
    $method = 'alipay.data.bill.accountlog.query';
    $charset = 'utf-8';
    $sign_type = 'RSA2';
    $timestamp = date('Y-m-d H:i:s', $time);
    $version = '1.0';
    
    $query_days = (int)$__config['query_days'];
    $timetmp = $query_days * 24 * 60 * 60;
    $end_time = date('Y-m-d', $time) . ' 23:59:59';
    $start_time = date("Y-m-d", $time - $timetmp) . ' 00:00:00';
    
    $arr = [
        'start_time' => $start_time,
        'end_time' => $end_time,
        'alipay_order_no' => $__post['order_sn'],
    ];
    $biz_content = json_encode($arr);

    $arr_final = array('app_id' => $appid, 'method' => $method, 'charset' => $charset, 'sign_type' => $sign_type, 'timestamp' => $timestamp, 'version' => $version, 'biz_content' => $biz_content);

    $str = order_str($arr_final);   
    $str = urldecode($str);

    $signature = '';
    $priv_key = $__config['merchant_private_key'];
    $key = openssl_pkey_get_private($priv_key);
    if ($key === false) {
        $priv_key = trim($priv_key);
        $priv_key = preg_replace('/^-----BEGIN RSA PRIVATE KEY-----\s*/', '', $priv_key);
        $priv_key = preg_replace('/\s*-----END RSA PRIVATE KEY-----$/', '', $priv_key);
        $priv_key = trim($priv_key);
        $priv_key = "-----BEGIN RSA PRIVATE KEY-----\n" . wordwrap($priv_key, 64, "\n", true) . "\n-----END RSA PRIVATE KEY-----";
        $key = openssl_pkey_get_private($priv_key);
    }
    if ($key === false) {
        rejson(array('code' => -2002, 'msg' => false, 'data' => '私钥格式错误，请检查配置' . get_contact_info()));
    }
    openssl_sign($str, $signature, $key, 'SHA256');
    openssl_free_key($key);
    $sign = base64_encode($signature);

    $post_final = $str . "&sign=" . urlencode($sign);

    $url = 'https://openapi.alipay.com/gateway.do';
    parse_str($post_final, $post);
    $result = sc_post_alipay($url, $post, 'POST');
    $result = mb_convert_encoding($result, 'UTF-8', 'GBK');
    $result = (array)json_decode($result, true);
    return($result);
}

function sc_post_alipay($url, $postarr, $method="GET", $header='Content-type: application/x-www-form-urlencoded')
{
    $postdata = http_build_query($postarr);
    $opts = array('http' =>
    array(
        'method'  => $method,
        'timeout' => 10,
        'header'  => $header,
        'content' => $postdata));
    $context  = stream_context_create($opts);
    return $result = file_get_contents($url, false, $context);
}

function order_str($param){
    if(!$param){
        return false;
    }
    ksort($param);
    $str = http_build_query($param);
    return $str;
}
?>
