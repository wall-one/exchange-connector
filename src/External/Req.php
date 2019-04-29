<?php

/**
 * @REFACTOR ME PLEASE!
 *
 * 火币REST API库
 * @author https://github.com/del-xiong
 */
class Req
{
    private const URLS = [
        'ru' => 'https://www.huobi.com.ru/api',
        'us' => 'https://api.huobi.com',
        'ch' => 'https://api.huobi.pro'
    ];

    public $api_method = '';
    public $req_method = '';

    private $url = '';
    private $api = '';

    private $access_key;
    private $secret_key;

    public function __construct($region, $access_key, $secret_key)
    {
        $this->url = static::URLS[$region];

        $this->access_key = $access_key;
        $this->secret_key = $secret_key;

        $this->api = parse_url($this->url)['host'];
        date_default_timezone_set("Etc/GMT+0");
    }
    /**
     * 行情类API
     */
    // 获取K线数据
    public function get_history_kline($symbol = '', $period = '', $size = 0)
    {
        $this->api_method = "/market/history/kline";
        $this->req_method = 'GET';
        $param = [
            'symbol' => $symbol,
            'period' => $period
        ];
        if ($size) {
            $param['size'] = $size;
        }
        $url = $this->create_sign_url($param);

        return json_decode($this->curl($url), true);
    }

    // 获取聚合行情(Ticker)
    public function get_detail_merged($symbol = '')
    {
        $this->api_method = "/market/detail/merged";
        $this->req_method = 'GET';
        $param = [
            'symbol' => $symbol,
        ];
        $url = $this->create_sign_url($param);

        return json_decode($this->curl($url), true);
    }

    // 获取 Market Depth 数据
    public function get_market_depth($symbol = '', $type = '')
    {
        $this->api_method = "/market/depth";
        $this->req_method = 'GET';
        $param = [
            'symbol' => $symbol,
            'type' => $type
        ];
        $url = $this->create_sign_url($param);

        return json_decode($this->curl($url), true);
    }

    // 获取 Trade Detail 数据
    public function get_market_trade($symbol = '')
    {
        $this->api_method = "/market/trade";
        $this->req_method = 'GET';
        $param = [
            'symbol' => $symbol
        ];
        $url = $this->create_sign_url($param);

        return json_decode($this->curl($url), true);
    }

    // 批量获取最近的交易记录
    public function get_history_trade($symbol = '', $size = '')
    {
        $this->api_method = "/market/history/trade";
        $this->req_method = 'GET';
        $param = [
            'symbol' => $symbol
        ];
        if ($size) {
            $param['size'] = $size;
        }
        $url = $this->create_sign_url($param);

        return json_decode($this->curl($url), true);
    }

    // 获取 Market Detail 24小时成交量数据
    public function get_market_detail($symbol = '')
    {
        $this->api_method = "/market/detail";
        $this->req_method = 'GET';
        $param = [
            'symbol' => $symbol
        ];
        $url = $this->create_sign_url($param);

        return json_decode($this->curl($url), true);
    }

    public function get_deposit_withdrawals(string $currency, string $type, $from = 0)
    {
        $this->api_method = '/v1/query/deposit-withdraw';
        $this->req_method = 'GET';
        $param = [
            'currency' => $currency,
            'type' => $type,
            'from' => $from,
            'size' => 100,
        ];
        $url = $this->create_sign_url($param);

        return json_decode($this->curl($url), true);
    }

    /**
     * 公共类API
     */
    // 查询系统支持的所有交易对及精度
    public function get_common_symbols()
    {
        $this->api_method = '/v1/common/symbols';
        $this->req_method = 'GET';
        $url = $this->create_sign_url([]);

        return json_decode($this->curl($url), true);
    }

    // 查询系统支持的所有币种
    public function get_common_currencys()
    {
        $this->api_method = "/v1/common/currencys";
        $this->req_method = 'GET';
        $url = $this->create_sign_url([]);

        return json_decode($this->curl($url), true);
    }

    // 查询系统当前时间
    public function get_common_timestamp()
    {
        $this->api_method = "/v1/common/timestamp";
        $this->req_method = 'GET';
        $url = $this->create_sign_url([]);

        return json_decode($this->curl($url), true);
    }

    // 查询当前用户的所有账户(即account-id)
    public function get_account_accounts()
    {
        $this->api_method = "/v1/account/accounts";
        $this->req_method = 'GET';
        $url = $this->create_sign_url([]);

        return json_decode($this->curl($url), true);
    }

    // 查询指定账户的余额
    public function get_account_balance($account)
    {
        $this->api_method = "/v1/account/accounts/$account/balance";
        $this->req_method = 'GET';
        $url = $this->create_sign_url([]);

        return json_decode($this->curl($url), true);
    }
    /**
     * 交易类API
     */
    // 下单
    public function place_order($client_id, $amount = 0, $price = 0, $symbol = '', $type = '')
    {
        $source = 'api';
        $this->api_method = "/v1/order/orders/place";
        $this->req_method = 'POST';
        // 数据参数
        $postdata = [
            'account-id' => $client_id,
            'amount' => $amount,
            'source' => $source,
            'symbol' => $symbol,
            'type' => $type
        ];
        if ($price) {
            $postdata['price'] = $price;
        }
        $url = $this->create_sign_url();
        $return = $this->curl($url, $postdata);

        return json_decode($return);
    }

    // 申请撤销一个订单请求
    public function cancel_order($order_id)
    {
        $this->api_method = '/v1/order/orders/' . $order_id . '/submitcancel';
        $this->req_method = 'POST';
        $postdata = [];
        $url = $this->create_sign_url();
        $return = $this->curl($url, $postdata);

        return json_decode($return, true);
    }

    // 批量撤销订单
    public function cancel_orders($order_ids = [])
    {
        $source = 'api';
        $this->api_method = '/v1/order/orders/batchcancel';
        $this->req_method = 'POST';
        $postdata = [
            'order-ids' => $order_ids
        ];
        $url = $this->create_sign_url();
        $return = $this->curl($url, $postdata);

        return json_decode($return, true);
    }

    // 查询某个订单详情
    public function get_order($order_id)
    {
        $this->api_method = '/v1/order/orders/' . $order_id;
        $this->req_method = 'GET';
        $url = $this->create_sign_url();
        $return = $this->curl($url);

        return json_decode($return, true);
    }

    // 查询某个订单的成交明细
    public function get_order_matchresults($order_id = 0)
    {
        $this->api_method = '/v1/order/orders/' . $order_id . '/matchresults';
        $this->req_method = 'GET';
        $url = $this->create_sign_url();
        $return = $this->curl($url);

        return json_decode($return, true);
    }

    // 查询当前委托、历史委托
    public function get_order_orders(
        $symbol = '',
        $types = '',
        $start_date = '',
        $end_date = '',
        $states = '',
        $from = '',
        $direct = '',
        $size = ''
    ) {
        $this->api_method = '/v1/order/orders';
        $this->req_method = 'GET';
        $postdata = [
            'symbol' => $symbol,
            'states' => $states
        ];
        if ($types) {
            $postdata['types'] = $types;
        }
        if ($start_date) {
            $postdata['start-date'] = $start_date;
        }
        if ($end_date) {
            $postdata['end-date'] = $end_date;
        }
        if ($from) {
            $postdata['from'] = $from;
        }
        if ($direct) {
            $postdata['direct'] = $direct;
        }
        if ($size) {
            $postdata['size'] = $size;
        }
        $url = $this->create_sign_url($postdata);
        $return = $this->curl($url);

        return json_decode($return, true);
    }

    // 查询当前成交、历史成交
    public function get_orders_matchresults(
        $symbol = '',
        $types = '',
        $start_date = '',
        $end_date = '',
        $from = '',
        $direct = '',
        $size = ''
    ) {
        $this->api_method = '/v1/order/matchresults';
        $this->req_method = 'GET';
        $postdata = [
            'symbol' => $symbol
        ];
        if ($types) {
            $postdata['types'] = $types;
        }
        if ($start_date) {
            $postdata['start-date'] = $start_date;
        }
        if ($end_date) {
            $postdata['end-date'] = $end_date;
        }
        if ($from) {
            $postdata['from'] = $from;
        }
        if ($direct) {
            $postdata['direct'] = $direct;
        }
        if ($size) {
            $postdata['size'] = $size;
        }
        $url = $this->create_sign_url();
        $return = $this->curl($url, $postdata);

        return json_decode($return, true);
    }

    // 获取账户余额
    public function get_balance($client_id)
    {
        $this->api_method = sprintf("/v1/account/accounts/%s/balance", $client_id);
        $this->req_method = 'GET';
        $url = $this->create_sign_url();
        $return = $this->curl($url);

        return json_decode($return, true);
    }
    /**
     * 借贷类API
     */
    // 现货账户划入至借贷账户
    public function dw_transfer_in($symbol = '', $currency = '', $amount = '')
    {
        $this->api_method = "/v1/dw/transfer-in/margin";
        $this->req_method = 'POST';
        $postdata = [
            'symbol	' => $symbol,
            'currency' => $currency,
            'amount' => $amount
        ];
        $url = $this->create_sign_url($postdata);
        $return = $this->curl($url);

        return json_decode($return, true);
    }

    // 借贷账户划出至现货账户
    public function dw_transfer_out($symbol = '', $currency = '', $amount = '')
    {
        $this->api_method = "/v1/dw/transfer-out/margin";
        $this->req_method = 'POST';
        $postdata = [
            'symbol	' => $symbol,
            'currency' => $currency,
            'amount' => $amount
        ];
        $url = $this->create_sign_url($postdata);
        $return = $this->curl($url);

        return json_decode($return, true);
    }

    // 申请借贷
    public function margin_orders($symbol = '', $currency = '', $amount = '')
    {
        $this->api_method = "/v1/margin/orders";
        $this->req_method = 'POST';
        $postdata = [
            'symbol	' => $symbol,
            'currency' => $currency,
            'amount' => $amount
        ];
        $url = $this->create_sign_url($postdata);
        $return = $this->curl($url);

        return json_decode($return, true);
    }

    // 归还借贷
    public function repay_margin_orders($order_id = '', $amount = '')
    {
        $this->api_method = "/v1/margin/orders/{$order_id}/repay";
        $this->req_method = 'POST';
        $postdata = [
            'amount' => $amount
        ];
        $url = $this->create_sign_url($postdata);
        $return = $this->curl($url);

        return json_decode($return, true);
    }

    // 借贷订单
    public function get_loan_orders($symbol = '', $currency = '', $start_date, $end_date, $states, $from, $direct, $size)
    {
        $this->api_method = "/v1/margin/loan-orders";
        $this->req_method = 'GET';
        $postdata = [
            'symbol' => $symbol,
            'currency' => $currency,
            'states' => $states
        ];
        if ($currency) {
            $postdata['currency'] = $currency;
        }
        if ($start_date) {
            $postdata['start-date'] = $start_date;
        }
        if ($end_date) {
            $postdata['end-date'] = $end_date;
        }
        if ($from) {
            $postdata['from'] = $from;
        }
        if ($direct) {
            $postdata['direct'] = $direct;
        }
        if ($size) {
            $postdata['size'] = $size;
        }
        $url = $this->create_sign_url($postdata);
        $return = $this->curl($url);

        return json_decode($return, true);
    }

    // 借贷账户详情
    public function margin_balance($symbol = '')
    {
        $this->api_method = "/v1/margin/accounts/balance";
        $this->req_method = 'POST';
        $postdata = [
        ];
        if ($symbol) {
            $postdata['symbol'] = $symbol;
        }
        $url = $this->create_sign_url($postdata);
        $return = $this->curl($url);

        return json_decode($return, true);
    }
    /**
     * 虚拟币提现API
     */
    // 申请提现虚拟币
    public function withdraw_create($address = '', $amount = '', $currency = '', $fee = '', $addr_tag = '')
    {
        $this->api_method = "/v1/dw/withdraw/api/create";
        $this->req_method = 'POST';
        $postdata = [
            'address' => $address,
            'amount' => $amount,
            'currency' => $currency
        ];
        if ($fee) {
            $postdata['fee'] = $fee;
        }
        if ($addr_tag) {
            $postdata['addr-tag'] = $addr_tag;
        }
        $url = $this->create_sign_url($postdata);
        $return = $this->curl($url);

        return json_decode($return, true);
    }

    // 申请取消提现虚拟币
    public function withdraw_cancel($withdraw_id = '')
    {
        $this->api_method = "/v1/dw/withdraw-virtual/{$withdraw_id}/cancel";
        $this->req_method = 'POST';
        $url = $this->create_sign_url();
        $return = $this->curl($url);

        return json_decode($return, true);
    }
    /**
     * 类库方法
     */
    // 生成验签URL
    private function create_sign_url($append_param = []): string
    {
        // 验签参数
        $param = [
            'AccessKeyId' => $this->access_key,
            'SignatureMethod' => 'HmacSHA256',
            'SignatureVersion' => 2,
            'Timestamp' => date('Y-m-d\TH:i:s')
        ];
        if ($append_param) {
            foreach ($append_param as $k => $ap) {
                $param[$k] = $ap;
            }
        }

        return $this->url . $this->api_method . '?' . $this->bind_param($param);
    }

    // 组合参数
    private function bind_param($param): string
    {
        $u = [];
        foreach ($param as $k => $v) {
            $u[] = $k . "=" . urlencode($v);
        }
        asort($u);
        $u[] = "Signature=" . urlencode($this->create_sig($u));

        return implode('&', $u);
    }

    // 生成签名
    private function create_sig($param): string
    {
        $sign_param_1 = $this->req_method . "\n" . $this->api . "\n" . $this->api_method . "\n" . implode('&', $param);
        $signature = hash_hmac('sha256', $sign_param_1, $this->secret_key, true);

        return base64_encode($signature);
    }

    private function curl($url, $postdata = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if ($this->req_method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }
}