<?php

namespace app\api\controller;

use app\admin\model\petto\Order as FsOrderModel;
use app\admin\model\petto\IncomeLog;
use app\admin\model\petto\Provider as RiderModel;
use app\admin\model\petto\ProviderDemand;
use app\common\controller\Frontend;
use Throwable;
use think\facade\Db;
use think\facade\Log;

class Payworld extends Frontend
{
    protected array $noNeedLogin = ['payTest', 'payNotify', 'payReturn', 'payOrder'];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function payTest(): void
    {
        $money = (string)$this->request->post('money', '1.00');
        $type  = (string)$this->request->post('type', 'alipay');
        $name  = (string)$this->request->post('name', '支付测试(1元)');
        $param = (string)$this->request->post('param', '');

        if (!preg_match('/^\d+(\.\d{1,2})?$/', $money) || (float)$money <= 0) {
            $this->error('金额不正确');
        }
        if ($type !== '' && !in_array($type, ['alipay', 'wxpay', 'qqpay', 'bank'], true)) {
            $this->error('支付方式不正确');
        }

        $outTradeNo = 'PW' . date('YmdHis') . random_int(100, 999);

        // 本地调试环境可能需要 index.php 入口
        $notifyUrl = $this->request->domain() . '/index.php/api/payworld/payNotify';
        $returnUrl = $this->request->domain() . '/index.php/api/payworld/payReturn';

        $configFile = root_path() . 'app/api/controller/paysdk/lib/epay.config.php';
        if (!is_file($configFile)) {
            $this->error('支付配置缺失');
        }
        $epay_config = [];
        require $configFile;

        if (empty($epay_config['apiurl']) || empty($epay_config['pid']) || empty($epay_config['merchant_private_key'])) {
            $this->error('支付配置不完整');
        }

        $apiurl = rtrim((string)$epay_config['apiurl'], '/') . '/';
        $submitUrl = $apiurl . 'api/pay/submit';

        $timestamp = (string)time();

        $params = [
            'pid'         => (string)$epay_config['pid'],
            'out_trade_no'=> $outTradeNo,
            'notify_url'  => $notifyUrl,
            'return_url'  => $returnUrl,
            'name'        => $name,
            'money'       => $money,
            'param'       => $param,
            'timestamp'   => $timestamp,
        ];
        if ($type !== '') {
            $params['type'] = $type;
        }

        $params['sign'] = $this->rsaSign($this->buildSignContent($params), (string)$epay_config['merchant_private_key']);
        $params['sign_type'] = 'RSA';

        $this->success('', [
            'method' => 'POST',
            'url'    => $submitUrl,
            'params' => $params,
        ]);
    }

    /**
     * Petto订单支付
     * POST /api/payworld/payOrder
     * params: order_no, type (alipay/wxpay/qqpay/bank)
     */
    public function payOrder(): void
    {
        $orderNo = (string)$this->request->post('order_no', '');
        $type    = (string)$this->request->post('type', 'wxpay');

        if (!$orderNo) {
            $this->error('参数错误');
        }
        if ($type !== '' && !in_array($type, ['alipay', 'wxpay', 'qqpay', 'bank'], true)) {
            $this->error('支付方式不正确');
        }

        $order = FsOrderModel::where('order_no', $orderNo)->find();
        if (!$order) {
            $this->error('订单不存在');
        }
        if ($order->status !== 'pending') {
            $this->error('订单状态不允许支付');
        }

        $configFile = root_path() . 'app/api/controller/paysdk/lib/epay.config.php';
        if (!is_file($configFile)) {
            $this->error('支付配置缺失');
        }
        $epay_config = [];
        require $configFile;

        if (empty($epay_config['apiurl']) || empty($epay_config['pid']) || empty($epay_config['merchant_private_key'])) {
            $this->error('支付配置不完整');
        }

        $notifyUrl = $this->request->domain() . '/index.php/api/payworld/payNotify';
        $returnUrl = $this->request->domain() . '/index.php/api/payworld/payReturn';

        $apiurl = rtrim((string)$epay_config['apiurl'], '/') . '/';
        $submitUrl = $apiurl . 'api/pay/submit';

        $timestamp = (string)time();

        $params = [
            'pid'          => (string)$epay_config['pid'],
            'out_trade_no' => $order->order_no,
            'notify_url'   => $notifyUrl,
            'return_url'   => $returnUrl,
            'name'         => 'Petto订单-' . $order->service_name,
            'money'        => (string)$order->total_amount,
            'param'        => $order->order_no,
            'timestamp'    => $timestamp,
        ];
        if ($type !== '') {
            $params['type'] = $type;
        }

        $params['sign'] = $this->rsaSign($this->buildSignContent($params), (string)$epay_config['merchant_private_key']);
        $params['sign_type'] = 'RSA';

        Log::info('[payworld.payOrder] generated', [
            'order_no'   => $order->order_no,
            'money'      => $order->total_amount,
            'type'       => $type,
            'notify_url' => $notifyUrl,
            'return_url' => $returnUrl,
            'submit_url' => $submitUrl,
        ]);

        $this->success('', [
            'method'   => 'POST',
            'url'      => $submitUrl,
            'params'   => $params,
            'order_no' => $order->order_no,
        ]);
    }

    public function payNotify(): void
    {
        $rawGet = $_GET ?? [];

        Log::info('[payworld.payNotify] incoming', [
            'get'         => $rawGet,
            'ip'          => $this->request->ip(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'http_host'   => $_SERVER['HTTP_HOST'] ?? '',
            'method'      => $_SERVER['REQUEST_METHOD'] ?? '',
        ]);

        $configFile = root_path() . 'app/api/controller/paysdk/lib/epay.config.php';
        if (!is_file($configFile)) {
            Log::error('[payworld.payNotify] missing config', ['configFile' => $configFile]);
            echo 'fail';
            return;
        }
        $epay_config = [];
        require $configFile;

        $sdkFile = root_path() . 'app/api/controller/paysdk/lib/EpayCore.class.php';
        if (!is_file($sdkFile)) {
            Log::error('[payworld.payNotify] missing sdk', ['sdkFile' => $sdkFile]);
            echo 'fail';
            return;
        }
        require_once $sdkFile;

        try {
            $epay = new \EpayCore($epay_config);
            $verify = $epay->verify($rawGet);
            Log::info('[payworld.payNotify] verify result', [
                'ok'        => $verify ? 1 : 0,
                'sign'      => $rawGet['sign'] ?? null,
                'sign_type' => $rawGet['sign_type'] ?? null,
                'timestamp' => $rawGet['timestamp'] ?? null,
            ]);
            if (!$verify) {
                Log::warning('[payworld.payNotify] verify FAILED', $rawGet);
                echo 'fail';
                return;
            }

            $tradeStatus = (string)($rawGet['trade_status'] ?? '');
            $outTradeNo  = (string)($rawGet['out_trade_no'] ?? '');
            $tradeNo     = (string)($rawGet['trade_no'] ?? '');
            $money       = (string)($rawGet['money'] ?? '');
            $payType     = (string)($rawGet['type'] ?? '');

            Log::info('[payworld.payNotify] verified payload', [
                'trade_status' => $tradeStatus,
                'out_trade_no' => $outTradeNo,
                'trade_no'     => $tradeNo,
                'money'        => $money,
                'type'         => $payType,
            ]);

            if ($tradeStatus !== 'TRADE_SUCCESS') {
                Log::info('[payworld.payNotify] trade not success, skip', ['trade_status' => $tradeStatus]);
                echo 'success';
                return;
            }

            $fsOrder = FsOrderModel::where('order_no', $outTradeNo)->find();
            if ($fsOrder) {
                Log::info('[payworld.payNotify] found fs_order', [
                    'order_no' => $outTradeNo,
                    'status'   => $fsOrder->status,
                ]);
                if ($fsOrder->status === 'pending') {
                    $fsOrder->status   = 'paid';
                    $fsOrder->paid_at  = time();
                    $fsOrder->trade_no = $tradeNo;
                    $fsOrder->save();
                    Log::info('[payworld.payNotify] fs_order updated to paid', [
                        'order_no' => $outTradeNo,
                        'trade_no' => $tradeNo,
                        'money'    => $money,
                    ]);
                } else {
                    Log::info('[payworld.payNotify] fs_order already processed', [
                        'order_no' => $outTradeNo,
                        'status'   => $fsOrder->status,
                    ]);
                }

                self::syncDemandSettlementAfterPaid(intval($fsOrder->id));
            } else {
                Log::warning('[payworld.payNotify] order not found', ['out_trade_no' => $outTradeNo]);
            }

            echo 'success';
        } catch (Throwable $e) {
            Log::error('[payworld.payNotify] exception', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);
            echo 'fail';
        }
    }

    public function payReturn(): void
    {
        $rawGet = $_GET ?? [];

        Log::info('[payworld.payReturn] incoming', [
            'get'         => $rawGet,
            'ip'          => $this->request->ip(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'http_host'   => $_SERVER['HTTP_HOST'] ?? '',
            'method'      => $_SERVER['REQUEST_METHOD'] ?? '',
            'referer'     => $_SERVER['HTTP_REFERER'] ?? '',
        ]);

        $configFile = root_path() . 'app/api/controller/paysdk/lib/epay.config.php';
        if (!is_file($configFile)) {
            Log::error('[payworld.payReturn] missing config', ['configFile' => $configFile]);
            echo '<h1>支付配置缺失</h1>';
            return;
        }
        $epay_config = [];
        require $configFile;

        $sdkFile = root_path() . 'app/api/controller/paysdk/lib/EpayCore.class.php';
        if (!is_file($sdkFile)) {
            Log::error('[payworld.payReturn] missing sdk', ['sdkFile' => $sdkFile]);
            echo '<h1>支付SDK缺失</h1>';
            return;
        }
        require_once $sdkFile;

        $epay = new \EpayCore($epay_config);
        $verify = $epay->verify($rawGet);
        Log::info('[payworld.payReturn] verify result', [
            'ok'        => $verify ? 1 : 0,
            'sign'      => $rawGet['sign'] ?? null,
            'sign_type' => $rawGet['sign_type'] ?? null,
            'timestamp' => $rawGet['timestamp'] ?? null,
        ]);

        $tradeStatus = (string)($rawGet['trade_status'] ?? '');
        $outTradeNo  = (string)($rawGet['out_trade_no'] ?? '');
        $tradeNo     = (string)($rawGet['trade_no'] ?? '');
        $money       = (string)($rawGet['money'] ?? '');
        $payType     = (string)($rawGet['type'] ?? '');

        Log::info('[payworld.payReturn] payload', [
            'verify'       => $verify ? 'OK' : 'FAILED',
            'trade_status' => $tradeStatus,
            'out_trade_no' => $outTradeNo,
            'trade_no'     => $tradeNo,
            'money'        => $money,
            'type'         => $payType,
        ]);

        if (!$verify) {
            Log::warning('[payworld.payReturn] verify FAILED', $rawGet);
            $this->error('验签失败');
        }

        if ($tradeStatus !== 'TRADE_SUCCESS') {
            Log::warning('[payworld.payReturn] trade not success', ['trade_status' => $tradeStatus]);
            $this->error('支付未成功：' . $tradeStatus);
        }

        // 前端基础地址：config/app.php -> petto_frontend_url，为空则使用当前域名
        $frontendBase = rtrim((string)config('app.petto_frontend_url'), '/');
        if ($frontendBase === '') {
            $frontendBase = $this->request->domain();
        }

        $fsOrder = FsOrderModel::where('order_no', $outTradeNo)->find();
        if ($fsOrder) {
            Log::info('[payworld.payReturn] found fs_order', [
                'order_no' => $outTradeNo,
                'status'   => $fsOrder->status,
            ]);
            if ($fsOrder->status === 'pending') {
                $fsOrder->status   = 'paid';
                $fsOrder->paid_at  = time();
                $fsOrder->trade_no = $tradeNo;
                $fsOrder->save();
                Log::info('[payworld.payReturn] fs_order updated to paid', ['order_no' => $outTradeNo, 'trade_no' => $tradeNo]);
            }
            self::syncDemandSettlementAfterPaid(intval($fsOrder->id));
            $url = $frontendBase . '/petto/#/pages/orders/detail?id=' . $fsOrder->id;
        } else {
            Log::warning('[payworld.payReturn] order not found', ['out_trade_no' => $outTradeNo]);
            $url = $frontendBase . '/petto/#/pages/orders/index';
        }

        Log::info('[payworld.payReturn] redirect', ['url' => $url]);
        header('Location: ' . $url);
        exit;
    }

    /**
     * 订单退款（内部调用）
     * 传入 fs_order 对象，自动调用支付平台退款接口
     * 返回 ['ok' => bool, 'msg' => string, 'refund_no' => string]
     */
    public static function doRefund($order): array
    {
        $tradeNo = $order->trade_no;
        if (empty($tradeNo)) {
            return ['ok' => false, 'msg' => '订单无支付交易号，无法退款', 'refund_no' => ''];
        }

        $configFile = root_path() . 'app/api/controller/paysdk/lib/epay.config.php';
        if (!is_file($configFile)) {
            return ['ok' => false, 'msg' => '支付配置缺失', 'refund_no' => ''];
        }
        $epay_config = [];
        require $configFile;

        $sdkFile = root_path() . 'app/api/controller/paysdk/lib/EpayCore.class.php';
        if (!is_file($sdkFile)) {
            return ['ok' => false, 'msg' => '支付SDK缺失', 'refund_no' => ''];
        }
        require_once $sdkFile;

        $outRefundNo = 'RF' . date('YmdHis') . random_int(100, 999);
        $money = (string)$order->total_amount;

        try {
            $epay = new \EpayCore($epay_config);
            $result = $epay->refund($outRefundNo, $tradeNo, $money);
            Log::info('[payworld.doRefund] success', [
                'order_no'      => $order->order_no,
                'trade_no'      => $tradeNo,
                'out_refund_no' => $outRefundNo,
                'money'         => $money,
                'result'        => $result,
            ]);
            return ['ok' => true, 'msg' => '退款成功', 'refund_no' => $outRefundNo];
        } catch (\Throwable $e) {
            Log::error('[payworld.doRefund] failed', [
                'order_no' => $order->order_no,
                'trade_no' => $tradeNo,
                'money'    => $money,
                'error'    => $e->getMessage(),
            ]);
            return ['ok' => false, 'msg' => '退款失败: ' . $e->getMessage(), 'refund_no' => $outRefundNo];
        }
    }

    private static function syncDemandSettlementAfterPaid(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        Db::startTrans();
        try {
            $settlementOrder = FsOrderModel::where('id', $orderId)
                ->lock(true)
                ->find();
            if (!$settlementOrder || (string)$settlementOrder->status !== 'paid') {
                Db::commit();
                return;
            }

            $demand = ProviderDemand::where('settlement_order_id', $orderId)
                ->lock(true)
                ->find();
            if (!$demand || (string)$demand->status === 'closed') {
                Db::commit();
                return;
            }

            $mainOrder = FsOrderModel::where('id', intval($demand->order_id))
                ->lock(true)
                ->find();
            if (!$mainOrder) {
                Db::commit();
                return;
            }

            if ((string)$settlementOrder->status !== 'completed') {
                $settlementOrder->status = 'completed';
                $settlementOrder->completed_at = time();
                $settlementOrder->save();
            }

            if ((string)$mainOrder->status !== 'completed') {
                $mainOrder->status = 'completed';
                $mainOrder->completed_at = time();
                $mainOrder->save();
            }

            $demand->save([
                'status' => 'closed',
            ]);

            $provider = RiderModel::where('id', intval($mainOrder->provider_id))
                ->lock(true)
                ->find();
            if ($provider) {
                $incomeLog = IncomeLog::where('order_id', intval($mainOrder->id))
                    ->where('type', 'service')
                    ->find();
                $providerIncome = round(floatval($demand->agreed_amount ?? 0), 2);
                if (!$incomeLog && $providerIncome > 0) {
                    $beforeBalance = floatval($provider->balance);
                    $provider->total_orders += 1;
                    $provider->balance += $providerIncome;
                    $provider->total_income += $providerIncome;
                    $provider->month_income += $providerIncome;
                    $provider->save();

                    IncomeLog::create([
                        'provider_id' => $provider->id,
                        'order_id' => $mainOrder->id,
                        'type' => 'service',
                        'amount' => $providerIncome,
                        'before_balance' => $beforeBalance,
                        'after_balance' => floatval($provider->balance),
                        'memo' => '需求订单完成 ' . $mainOrder->order_no,
                    ]);
                }
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            Log::error('[payworld.syncDemandSettlementAfterPaid] failed', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function buildSignContent(array $params): string
    {
        // 1) 获取所有非空参数；剔除 sign/sign_type；不包含数组、字节流等复杂类型
        $filtered = [];
        foreach ($params as $k => $v) {
            if ($k === 'sign' || $k === 'sign_type') {
                continue;
            }
            if (is_array($v) || is_object($v)) {
                continue;
            }
            if ($v === null) {
                continue;
            }
            $v = trim((string)$v);
            if ($v === '') {
                continue;
            }
            $filtered[$k] = $v;
        }

        // 2) 按 ASCII 升序排序
        ksort($filtered);

        // 3) 参数=参数值 以 & 拼接
        $pairs = [];
        foreach ($filtered as $k => $v) {
            $pairs[] = $k . '=' . $v;
        }
        return implode('&', $pairs);
    }

    private function rsaSign(string $data, string $merchantPrivateKey): string
    {
        // SHA256WithRSA
        $key = "-----BEGIN PRIVATE KEY-----\n" . wordwrap($merchantPrivateKey, 64, "\n", true) . "\n-----END PRIVATE KEY-----";
        $privateKey = openssl_get_privatekey($key);
        if (!$privateKey) {
            $this->error('签名失败');
        }
        openssl_sign($data, $sign, $privateKey, OPENSSL_ALGO_SHA256);
        return base64_encode($sign);
    }
}
