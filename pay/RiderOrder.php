<?php

namespace app\api\controller;

use Throwable;
use think\facade\Db;
use app\common\controller\Frontend;
use app\admin\model\petto\Provider as RiderModel;
use app\admin\model\petto\Order as OrderModel;
use app\admin\model\petto\IncomeLog;

class RiderOrder extends Frontend
{
    protected array $noNeedLogin = [];

    /**
     * 获取骑手订单列表
     */
    public function list(): void
    {
        $rider = RiderModel::where('user_id', $this->auth->id)->find();
        if (!$rider) {
            $this->error('骑手信息不存在');
        }

        $page     = $this->request->get('page/d', 1);
        $pageSize = $this->request->get('pageSize/d', 20);
        $status   = $this->request->get('status', '');

        $query = OrderModel::where('rider_id', $rider->id);
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $res = $query->order('create_time', 'desc')->paginate([
            'page'      => $page,
            'list_rows' => $pageSize,
        ]);

        $this->success('', [
            'list'  => $res->items(),
            'total' => $res->total(),
        ]);
    }

    /**
     * 获取订单详情
     */
    public function detail(): void
    {
        $rider = RiderModel::where('user_id', $this->auth->id)->find();
        if (!$rider) {
            $this->error('骑手信息不存在');
        }

        $id = $this->request->get('id/d', 0);

        $order = OrderModel::where('id', $id)
            ->where('rider_id', $rider->id)
            ->find();

        if (!$order) {
            $this->error('订单不存在');
        }

        $this->success('', [
            'order' => $order->toArray(),
        ]);
    }

    /**
     * 接单（开始配送）
     */
    public function accept(): void
    {
        if ($this->request->isPost()) {
            $rider = RiderModel::where('user_id', $this->auth->id)->find();
            if (!$rider) {
                $this->error('骑手信息不存在');
            }

            $id    = $this->request->post('id/d', 0);
            $order = OrderModel::where('id', $id)
                ->where('rider_id', $rider->id)
                ->find();

            if (!$order) {
                $this->error('订单不存在');
            }

            if ($order->status !== 'paid') {
                $this->error('订单状态不允许接单');
            }

            $order->status = 'delivering';
            $order->save();

            $this->success('接单成功');
        }

        $this->error('请求方式错误');
    }

    /**
     * 完成订单
     */
    public function complete(): void
    {
        if ($this->request->isPost()) {
            $rider = RiderModel::where('user_id', $this->auth->id)->find();
            if (!$rider) {
                $this->error('骑手信息不存在');
            }

            $id    = $this->request->post('id/d', 0);
            $order = OrderModel::where('id', $id)
                ->where('rider_id', $rider->id)
                ->find();

            if (!$order) {
                $this->error('订单不存在');
            }

            if (!in_array($order->status, ['paid', 'delivering'])) {
                $this->error('订单状态不允许完成');
            }

            Db::startTrans();
            try {
                $order->status       = 'completed';
                $order->completed_at = time();
                $order->save();

                // 更新骑手库存已售数量
                $riderStock = RiderStockModel::where('rider_id', $rider->id)
                    ->where('product_id', $order->product_id)
                    ->find();
                if ($riderStock) {
                    $riderStock->sold_count += $order->quantity;
                    $riderStock->save();
                }

                // 记录收益
                $beforeBalance = $rider->balance;
                $rider->balance      += $order->rider_profit;
                $rider->total_income += $order->rider_profit;
                $rider->total_orders += 1;
                $rider->month_sales  += $order->total_amount;
                $rider->save();

                IncomeLog::create([
                    'rider_id'       => $rider->id,
                    'order_id'       => $order->id,
                    'type'           => 'sale',
                    'amount'         => $order->rider_profit,
                    'before_balance' => $beforeBalance,
                    'after_balance'  => $rider->balance,
                    'memo'           => '订单完成 ' . $order->order_no,
                ]);

                Db::commit();
            } catch (Throwable $e) {
                Db::rollback();
                $this->error('操作失败: ' . $e->getMessage());
            }

            $this->success('订单已完成');
        }

        $this->error('请求方式错误');
    }

    /**
     * 取消订单（含退款）
     */
    public function cancel(): void
    {
        if ($this->request->isPost()) {
            $rider = RiderModel::where('user_id', $this->auth->id)->find();
            if (!$rider) {
                $this->error('骑手信息不存在');
            }

            $id     = $this->request->post('id/d', 0);
            $reason = $this->request->post('reason', '');

            $order = OrderModel::where('id', $id)
                ->where('rider_id', $rider->id)
                ->find();

            if (!$order) {
                $this->error('订单不存在');
            }

            if (in_array($order->status, ['completed', 'cancelled', 'refunded'])) {
                $this->error('订单状态不允许取消');
            }

            $needRefund = in_array($order->status, ['paid', 'delivering']);

            if ($needRefund) {
                $refundResult = Payworld::doRefund($order);
                if (!$refundResult['ok']) {
                    $this->error($refundResult['msg']);
                }
            }

            Db::startTrans();
            try {
                $order->status = $needRefund ? 'refunded' : 'cancelled';
                if ($needRefund) {
                    $order->refund_no   = $refundResult['refund_no'];
                    $order->refunded_at = time();
                }
                $order->save();

                // 归还骑手库存
                $riderStock = RiderStockModel::where('rider_id', $rider->id)
                    ->where('product_id', $order->product_id)
                    ->find();
                if ($riderStock) {
                    $riderStock->quantity += $order->quantity;
                    if ($riderStock->status === 'off') {
                        $riderStock->status = 'on';
                    }
                    $riderStock->save();
                }

                Db::commit();
            } catch (Throwable $e) {
                Db::rollback();
                $this->error('取消失败: ' . $e->getMessage());
            }

            $msg = $needRefund ? '订单已取消，退款将原路返回' : '订单已取消';
            $this->success($msg);
        }

        $this->error('请求方式错误');
    }
}
