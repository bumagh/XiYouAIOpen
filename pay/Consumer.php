<?php

namespace app\api\controller;

use Throwable;
use think\facade\Db;
use app\common\facade\Token;
use app\common\controller\Frontend;
use app\admin\model\petto\Provider as RiderModel;
use app\admin\model\petto\ProviderDemand;
use app\admin\model\petto\Service as ProductModel;
use app\admin\model\petto\Order as OrderModel;
use app\admin\model\petto\Review as ReviewModel;
use app\admin\model\petto\IncomeLog;
use app\admin\model\petto\UserAddress as UserAddressModel;

class Consumer extends Frontend
{
    protected array $noNeedLogin = ['login', 'register', 'products', 'categories', 'riders', 'riderDetail'];

    public function initialize(): void
    {
        parent::initialize();
    }

    /**
     * 消费者登录
     * 使用 User 表认证，无需验证码
     * @throws Throwable
     */
    public function login(): void
    {
        if ($this->auth->isLogin()) {
            $userInfo = $this->appendRoleInfo($this->auth->getUserInfo(), $this->auth->id);
            $this->success('已登录', [
                'userInfo' => $userInfo,
            ]);
        }

        if ($this->request->isPost()) {
            $params = $this->request->post(['username', 'password', 'keep']);

            if (empty($params['username']) || empty($params['password'])) {
                $this->error('请输入用户名和密码');
            }

            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9]{2,14}$/', $params['username'])) {
                $this->error('用户名须字母开头，仅含字母和数字(3-15位)');
            }

            $res = $this->auth->login($params['username'], $params['password'], !empty($params['keep']));

            if ($res) {
                $userInfo = $this->appendRoleInfo($this->auth->getUserInfo(), $this->auth->id);
                $this->success('登录成功', [
                    'userInfo' => $userInfo,
                ]);
            } else {
                $msg = $this->auth->getError();
                $msg = $msg ?: '登录失败，请重试';
                $this->error($msg);
            }
        }

        $this->error('请求方式错误');
    }

    /**
     * 消费者注册
     * 使用 User 表注册，无需验证码
     * @throws Throwable
     */
    public function register(): void
    {
        if ($this->request->isPost()) {
            $params = $this->request->post(['username', 'password', 'mobile', 'nickname']);

            if (empty($params['username']) || empty($params['password'])) {
                $this->error('请输入用户名和密码');
            }

            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9]{2,14}$/', $params['username'])) {
                $this->error('用户名须字母开头，仅含字母和数字(3-15位)');
            }

            $mobile = $params['mobile'] ?? '';
            $res    = $this->auth->register($params['username'], $params['password'], $mobile);

            if ($res) {
                // 设置昵称
                if (!empty($params['nickname'])) {
                    $user = $this->auth->getUser();
                    $user->nickname = $params['nickname'];
                    $user->save();
                }

                $userInfo = $this->appendRoleInfo($this->auth->getUserInfo(), $this->auth->id);

                $this->success('注册成功', [
                    'userInfo' => $userInfo,
                ]);
            } else {
                $msg = $this->auth->getError();
                $msg = $msg ?: '注册失败，请重试';
                $this->error($msg);
            }
        }

        $this->error('请求方式错误');
    }

    /**
     * 登出
     */
    public function logout(): void
    {
        if ($this->request->isPost()) {
            $refreshToken = $this->request->post('refreshToken', '');
            if ($refreshToken) Token::delete((string)$refreshToken);
            $this->auth->logout();
            $this->success();
        }
    }

    /**
     * 宠物主人发布需求
     */
    public function publishDemand(): void
    {
        if ($this->request->isPost()) {
            $params = $this->request->post([
                'title',
                'pet_type',
                'demand_desc',
                'service_area',
                'budget_min',
                'budget_max',
                'pay_amount',
                'expected_time',
                'contact_mobile',
                'latitude',
                'longitude',
            ]);

            $title = trim((string)($params['title'] ?? ''));
            if ($title === '' || mb_strlen($title) > 40) {
                $this->error('需求标题不能为空且不超过40字');
            }

            $petType = trim((string)($params['pet_type'] ?? ''));
            if (!in_array($petType, ['cat', 'dog', 'all'], true)) {
                $this->error('宠物类型仅支持 cat/dog/all');
            }

            $demandDesc = trim((string)($params['demand_desc'] ?? ''));
            if ($demandDesc === '' || mb_strlen($demandDesc) > 300) {
                $this->error('需求描述不能为空且不超过300字');
            }

            $serviceArea = trim((string)($params['service_area'] ?? ''));
            if ($serviceArea === '' || mb_strlen($serviceArea) > 80) {
                $this->error('服务范围不能为空且不超过80字');
            }

            $budgetMin = round(floatval($params['budget_min'] ?? 0), 2);
            $budgetMax = round(floatval($params['budget_max'] ?? 0), 2);
            if ($budgetMin < 0 || $budgetMax <= 0 || $budgetMax < $budgetMin) {
                $this->error('预算区间不正确');
            }

            $payAmount = round(floatval($params['pay_amount'] ?? 0), 2);
            if ($payAmount <= 0) {
                $this->error('支付金额必须大于0');
            }
            if ($payAmount < $budgetMin || $payAmount > $budgetMax) {
                $this->error('支付金额需在预算区间内');
            }

            $depositAmount = 1.00;
            $latitude = round(floatval($params['latitude'] ?? 0), 7);
            $longitude = round(floatval($params['longitude'] ?? 0), 7);

            $expectedTime = intval($params['expected_time'] ?? 0);
            if ($expectedTime <= 0) {
                $expectedTime = time() + 86400;
            }

            $contactMobile = trim((string)($params['contact_mobile'] ?? ''));
            if ($contactMobile === '') {
                $contactMobile = (string)($this->auth->getUser()->mobile ?: '');
            }
            if ($contactMobile !== '' && !preg_match('/^1[3-9]\d{9}$/', $contactMobile)) {
                $this->error('联系电话格式不正确');
            }

            $orderNo = 'PTD' . date('YmdHis') . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $owner = $this->auth->getUser();

            try {
                Db::startTrans();

                $order = OrderModel::create([
                    'order_no' => $orderNo,
                    'provider_id' => 0,
                    'user_id' => $this->auth->id,
                    'service_id' => 0,
                    'service_name' => $title,
                    'pet_name' => '',
                    'pet_type' => $petType,
                    'quantity' => 1,
                    'unit_price' => number_format($depositAmount, 2, '.', ''),
                    'total_amount' => number_format($depositAmount, 2, '.', ''),
                    'provider_income' => number_format($depositAmount, 2, '.', ''),
                    'platform_fee' => '0.00',
                    'service_type' => 'onsite',
                    'status' => 'pending',
                    'owner_name' => (string)($owner->nickname ?? ''),
                    'owner_mobile' => $contactMobile,
                    'service_address' => $serviceArea,
                    'appointment_time' => $expectedTime,
                ]);

                $demand = ProviderDemand::create([
                    'provider_id' => 0,
                    'user_id' => $this->auth->id,
                    'order_id' => $order->id,
                    'title' => $title,
                    'pet_type' => $petType,
                    'demand_desc' => $demandDesc,
                    'service_area' => $serviceArea,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'budget_min' => $budgetMin,
                    'budget_max' => $budgetMax,
                    'agreed_amount' => $payAmount,
                    'deposit_amount' => $depositAmount,
                    'expected_time' => $expectedTime,
                    'contact_mobile' => $contactMobile,
                    'status' => 'open',
                ]);

                Db::commit();
            } catch (Throwable $e) {
                Db::rollback();
                $this->error('发布失败: ' . $e->getMessage());
            }

            $this->success('发布成功，请先支付1元展示押金', [
                'demand' => $demand->toArray(),
                'order' => $order->toArray(),
            ]);
        }

        $this->error('请求方式错误');
    }

    /**
     * 宠物主人我的需求
     */
    public function myDemands(): void
    {
        $page = $this->request->get('page/d', 1);
        $pageSize = $this->request->get('pageSize/d', 20);
        $status = (string)$this->request->get('status', '');

        $query = ProviderDemand::alias('d')
            ->leftJoin('pt_provider p', 'p.id = d.provider_id')
            ->leftJoin('pt_order o', 'o.id = d.order_id')
            ->leftJoin('pt_order so', 'so.id = d.settlement_order_id')
            ->where('d.user_id', $this->auth->id)
            ->field([
                'd.*',
                'p.name as matched_provider_name',
                'p.mobile as matched_provider_mobile',
                'o.order_no',
                'o.status as order_status',
                'so.order_no as settlement_order_no',
                'so.status as settlement_order_status',
                'd.agreed_amount as pay_amount',
            ]);
        if ($status !== '' && $status !== 'all') {
            $query->where('d.status', $status);
        }

        $res = $query->order('d.id', 'desc')->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        $items = $res->items();
        $orderIds = [];
        foreach ($items as $item) {
            $orderId = intval($item['order_id'] ?? 0);
            if ($orderId > 0) {
                $orderIds[] = $orderId;
            }
        }
        $orderIds = array_values(array_unique($orderIds));
        $reviewedOrderIds = [];
        if ($orderIds) {
            $reviewedOrderIds = ReviewModel::whereIn('order_id', $orderIds)
                ->where('user_id', $this->auth->id)
                ->where('review_role', 'owner')
                ->column('order_id');
        }

        $list = [];
        foreach ($items as $item) {
            $row = is_array($item) ? $item : $item->toArray();
            $orderStatus = (string)($row['order_status'] ?? '');
            $settlementOrderStatus = (string)($row['settlement_order_status'] ?? '');
            $statusValue = (string)($row['status'] ?? 'open');
            if ($statusValue === 'open' && $orderStatus === 'pending') {
                $statusValue = 'pending_pay';
            } elseif ($statusValue === 'open' && in_array($orderStatus, ['cancelled', 'refunded'], true)) {
                $statusValue = 'cancelled';
            } elseif ($statusValue === 'wait_final_pay' && in_array($settlementOrderStatus, ['pending', ''], true)) {
                $statusValue = 'wait_final_pay';
            }
            $row['status'] = $statusValue;
            $row['owner_reviewed'] = in_array(intval($row['order_id'] ?? 0), array_map('intval', $reviewedOrderIds), true);
            $row['settlement_amount'] = max(0, round(floatval($row['pay_amount'] ?? 0) - floatval($row['deposit_amount'] ?? 1), 2));
            $row['can_pay'] = $orderStatus === 'pending' || ($statusValue === 'wait_final_pay' && $settlementOrderStatus === 'pending');
            $row['pay_stage'] = $orderStatus === 'pending' ? 'deposit' : (($statusValue === 'wait_final_pay' && $settlementOrderStatus === 'pending') ? 'final' : '');
            $row['pay_order_no'] = $orderStatus === 'pending'
                ? (string)($row['order_no'] ?? '')
                : (($statusValue === 'wait_final_pay' && $settlementOrderStatus === 'pending') ? (string)($row['settlement_order_no'] ?? '') : '');
            $row['can_review'] = $statusValue === 'closed' && $orderStatus === 'completed' && !$row['owner_reviewed'];
            $list[] = $row;
        }

        $this->success('', [
            'list' => $list,
            'total' => $res->total(),
        ]);
    }

    /**
     * 宠物主人确认需求完成
     */
    public function closeDemand(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }

        $demandId = $this->request->post('id/d', 0);
        if ($demandId <= 0) {
            $this->error('需求ID无效');
        }

        $demand = ProviderDemand::where('id', $demandId)
            ->where('user_id', $this->auth->id)
            ->find();
        if (!$demand) {
            $this->error('需求不存在');
        }
        if ((string)$demand->status === 'closed') {
            $this->success('该需求已完成', [
                'demand' => $demand->toArray(),
            ]);
        }
        if ((string)$demand->status === 'wait_final_pay') {
            $settlementOrder = OrderModel::where('id', intval($demand->settlement_order_id))->find();
            if ($settlementOrder && (string)$settlementOrder->status === 'pending') {
                $this->success('需求已确认，请支付尾款', [
                    'demand' => $demand->toArray(),
                    'order' => $settlementOrder->toArray(),
                ]);
            }
        }
        if ((string)$demand->status !== 'done_wait_owner') {
            $this->error('请等待服务者先标记完成');
        }

        $order = OrderModel::where('id', intval($demand->order_id))
            ->where('user_id', $this->auth->id)
            ->find();
        if (!$order) {
            $this->error('关联订单不存在');
        }

        Db::startTrans();
        try {
            $agreedAmount = round(floatval($demand->agreed_amount ?? 0), 2);
            $depositAmount = round(floatval($demand->deposit_amount ?? 1), 2);
            $settlementAmount = max(0, round($agreedAmount - $depositAmount, 2));

            if ($settlementAmount > 0) {
                $settlementOrder = OrderModel::where('id', intval($demand->settlement_order_id))
                    ->lock(true)
                    ->find();
                if (!$settlementOrder) {
                    $settlementOrderNo = 'PTS' . date('YmdHis') . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $settlementOrder = OrderModel::create([
                        'order_no' => $settlementOrderNo,
                        'provider_id' => intval($order->provider_id),
                        'user_id' => $this->auth->id,
                        'service_id' => 0,
                        'service_name' => $demand->title . '尾款',
                        'pet_name' => '',
                        'pet_type' => (string)$demand->pet_type,
                        'quantity' => 1,
                        'unit_price' => number_format($settlementAmount, 2, '.', ''),
                        'total_amount' => number_format($settlementAmount, 2, '.', ''),
                        'provider_income' => number_format($settlementAmount, 2, '.', ''),
                        'platform_fee' => '0.00',
                        'service_type' => 'onsite',
                        'status' => 'pending',
                        'owner_name' => (string)$order->owner_name,
                        'owner_mobile' => (string)$order->owner_mobile,
                        'service_address' => (string)$order->service_address,
                        'appointment_time' => intval($order->appointment_time),
                    ]);
                }

                $demand->save([
                    'settlement_order_id' => intval($settlementOrder->id),
                    'status' => 'wait_final_pay',
                ]);

                Db::commit();
                $this->success('服务已确认，请支付尾款', [
                    'demand' => $demand->toArray(),
                    'order' => $settlementOrder->toArray(),
                ]);
            }

            $demand->save([
                'status' => 'closed',
            ]);

            if ((string)$order->status !== 'completed') {
                $order->status = 'completed';
                $order->completed_at = time();
                $order->save();
            }

            $provider = RiderModel::find($order->provider_id);
            if ($provider) {
                $providerIncome = min($depositAmount, $agreedAmount);
                $incomeLog = IncomeLog::where('order_id', $order->id)
                    ->where('type', 'service')
                    ->find();
                if (!$incomeLog && $providerIncome > 0) {
                    $beforeBalance = floatval($provider->balance);
                    $provider->total_orders += 1;
                    $provider->balance += $providerIncome;
                    $provider->total_income += $providerIncome;
                    $provider->month_income += $providerIncome;
                    $provider->save();

                    IncomeLog::create([
                        'provider_id' => $provider->id,
                        'order_id' => $order->id,
                        'type' => 'service',
                        'amount' => $providerIncome,
                        'before_balance' => $beforeBalance,
                        'after_balance' => floatval($provider->balance),
                        'memo' => '需求订单完成 ' . $order->order_no,
                    ]);
                }
            }

            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $this->error('确认完成失败: ' . $e->getMessage());
        }

        $this->success('确认完成成功', [
            'demand' => $demand->toArray(),
            'order' => $order->toArray(),
        ]);
    }

    private function appendRoleInfo(array $userInfo, int $userId): array
    {
        $roles = ['owner'];
        $providerStatus = '';

        $provider = RiderModel::where('user_id', $userId)->find();
        if ($provider) {
            $roles[] = 'provider';
            $providerStatus = (string)$provider->status;
        }

        $userInfo['roles'] = $roles;
        $userInfo['provider_status'] = $providerStatus;
        return $userInfo;
    }

    private function getCurrentProviderId(): int
    {
        if (!$this->auth->isLogin()) {
            return 0;
        }

        $provider = RiderModel::where('user_id', $this->auth->id)->find();
        return $provider ? intval($provider->id) : 0;
    }

    // ========== 商品相关 ==========

    /**
     * 浏览商品（从骑手库存中查询在售商品）
     * 支持分类、关键词筛选
     */
    public function products(): void
    {
        $page     = $this->request->get('page/d', 1);
        $pageSize = $this->request->get('pageSize/d', 20);
        $category = $this->request->get('category', '');
        $keyword  = $this->request->get('keyword', '');
        $excludeSelf = intval($this->request->get('exclude_self/d', 0)) === 1;

        $query = ProductModel::alias('s')
            ->join('pt_provider p', 'p.id = s.provider_id')
            ->where('s.status', 'on')
            ->where('p.status', '<>', 'rejected')
            ->field([
                's.*',
                'p.name as provider_name',
                'p.rating as provider_rating',
                'p.total_orders as provider_total_orders',
            ]);

        if ($excludeSelf) {
            $currentProviderId = $this->getCurrentProviderId();
            if ($currentProviderId > 0) {
                $query->where('s.provider_id', '<>', $currentProviderId);
            }
        }

        if ($category && $category !== '全部') {
            $query->where('s.category', $category);
        }
        if ($keyword) {
            $query->where('s.name', 'like', '%' . $keyword . '%');
        }

        $res = $query->order('s.is_hot', 'desc')
            ->order('s.weigh', 'desc')
            ->order('s.id', 'desc')
            ->paginate([
                'page'      => $page,
                'list_rows' => $pageSize,
            ]);

        $items = [];
        foreach ($res->items() as $service) {
            $items[]  = [
                'id'           => (int)$service->id,
                'stock_id'     => (int)$service->id,
                'rider_id'     => (int)$service->provider_id,
                'product_id'   => (int)$service->id,
                'quantity'     => 999,
                'sold_count'   => 0,
                'name'         => (string)$service->name,
                'category'     => (string)$service->category,
                'image'        => (string)$service->image,
                'sell_price'   => (string)$service->base_price,
                'description'  => (string)$service->description,
                'unit'         => '次',
                'is_hot'       => (int)$service->is_hot,
                'rider_name'   => (string)$service->provider_name,
                'credit_score' => (int)round(((float)$service->provider_rating) * 20),
                'rider_orders' => (int)$service->provider_total_orders,
            ];
        }

        $this->success('', [
            'list'  => $items,
            'total' => $res->total(),
        ]);
    }

    /**
     * 获取商品分类（从在售骑手库存中提取）
     */
    public function categories(): void
    {
        $excludeSelf = intval($this->request->get('exclude_self/d', 0)) === 1;

        $categories = ProductModel::alias('s')
            ->join('pt_provider p', 'p.id = s.provider_id')
            ->where('s.status', 'on')
            ->where('p.status', '<>', 'rejected')
            ->group('s.category');

        if ($excludeSelf) {
            $currentProviderId = $this->getCurrentProviderId();
            if ($currentProviderId > 0) {
                $categories->where('s.provider_id', '<>', $currentProviderId);
            }
        }

        $categories = $categories->column('s.category');

        array_unshift($categories, '全部');

        $this->success('', [
            'categories' => $categories,
        ]);
    }

    // ========== 骑手相关 ==========

    /**
     * 获取活跃骑手列表（含在售商品数、距离）
     * 传入 latitude / longitude 时按距离排序并返回真实距离
     */
    public function riders(): void
    {
        $page      = $this->request->get('page/d', 1);
        $pageSize  = $this->request->get('pageSize/d', 20);
        $userLat   = $this->request->get('latitude/f', 0);
        $userLng   = $this->request->get('longitude/f', 0);
        $excludeSelf = intval($this->request->get('exclude_self/d', 0)) === 1;

        $query = RiderModel::where('status', '<>', 'rejected');
        if ($excludeSelf) {
            $currentProviderId = $this->getCurrentProviderId();
            if ($currentProviderId > 0) {
                $query->where('id', '<>', $currentProviderId);
            }
        }

        $riders = $query->order('rating', 'desc')
            ->order('total_orders', 'desc')
            ->paginate([
                'page'      => $page,
                'list_rows' => $pageSize,
            ]);

        $riderIds = [];
        foreach ($riders->items() as $rider) {
            $riderIds[] = intval($rider->id);
        }

        $serviceCountMap = [];
        $serviceTagMap = [];
        if ($riderIds) {
            $serviceCountRows = ProductModel::whereIn('provider_id', $riderIds)
                ->where('status', 'on')
                ->field('provider_id, COUNT(*) as total')
                ->group('provider_id')
                ->select()
                ->toArray();
            foreach ($serviceCountRows as $row) {
                $serviceCountMap[intval($row['provider_id'])] = intval($row['total']);
            }

            $serviceTagRows = ProductModel::whereIn('provider_id', $riderIds)
                ->where('status', 'on')
                ->field('provider_id, category')
                ->group('provider_id, category')
                ->select()
                ->toArray();
            foreach ($serviceTagRows as $row) {
                $pid = intval($row['provider_id']);
                if (!isset($serviceTagMap[$pid])) {
                    $serviceTagMap[$pid] = [];
                }
                $serviceTagMap[$pid][] = (string)$row['category'];
            }
        }

        $items = [];
        foreach ($riders->items() as $rider) {
            $distance = null;
            $riderLat = null;
            $riderLng = null;
            $riderLat = $rider->latitude ? (float)$rider->latitude : null;
            $riderLng = $rider->longitude ? (float)$rider->longitude : null;
            if ($userLat && $userLng && $riderLat && $riderLng) {
                $distance = $this->calcDistance($userLat, $userLng, $riderLat, $riderLng);
            }

            $items[] = [
                'id'            => $rider->id,
                'name'          => $rider->name,
                'avatar'        => $rider->avatar,
                'credit_score'  => (int)round(((float)$rider->rating) * 20),
                'total_orders'  => $rider->total_orders,
                'product_count' => intval($serviceCountMap[intval($rider->id)] ?? 0),
                'tags'          => $serviceTagMap[intval($rider->id)] ?? [],
                'latitude'      => $riderLat,
                'longitude'     => $riderLng,
                'distance'      => $distance,
            ];
        }

        // 按距离升序排序（有坐标时）
        if ($userLat && $userLng) {
            usort($items, function ($a, $b) {
                if ($a['distance'] === null) return 1;
                if ($b['distance'] === null) return -1;
                return $a['distance'] <=> $b['distance'];
            });
        }

        $this->success('', [
            'list'  => $items,
            'total' => $riders->total(),
        ]);
    }

    /**
     * Haversine 公式计算两点距离（单位：米）
     */
    private function calcDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
           * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c, 0);
    }

    /**
     * 骑手详情（含在售商品列表）
     */
    public function riderDetail(): void
    {
        $id = $this->request->get('id/d', 0);
        if ($id <= 0) {
            $this->error('参数错误');
        }

        $rider = RiderModel::where('id', $id)->where('status', '<>', 'rejected')->find();
        if (!$rider) {
            $this->error('骑手不存在');
        }

        $stocks = ProductModel::where('provider_id', $rider->id)
            ->where('status', 'on')
            ->field('id, provider_id, name, category, image, base_price, description')
            ->order('is_hot', 'desc')
            ->order('weigh', 'desc')
            ->select()
            ->toArray();

        $items = [];
        foreach ($stocks as $service) {
            $items[] = [
                'stock_id'    => (int)$service['id'],
                'rider_id'    => (int)$service['provider_id'],
                'product_id'  => (int)$service['id'],
                'quantity'    => 999,
                'sold_count'  => 0,
                'name'        => (string)$service['name'],
                'category'    => (string)$service['category'],
                'image'       => (string)$service['image'],
                'sell_price'  => (string)$service['base_price'],
                'description' => (string)$service['description'],
                'unit'        => '次',
            ];
        }

        // 完成订单数
        $completedOrders = OrderModel::where('provider_id', $rider->id)
            ->where('status', 'completed')
            ->count();

        $this->success('', [
            'rider' => [
                'id'            => $rider->id,
                'name'          => $rider->name,
                'avatar'        => $rider->avatar,
                'credit_score'  => (int)round(((float)$rider->rating) * 20),
                'total_orders'  => $completedOrders,
            ],
            'products' => $items,
        ]);
    }

    // ========== 收货地址相关 ==========

    /**
     * 收货地址列表
     */
    public function addressList(): void
    {
        $list = UserAddressModel::where('user_id', $this->auth->id)
            ->order('is_default desc, update_time desc')
            ->select()
            ->toArray();

        $this->success('', ['list' => $list]);
    }

    /**
     * 添加收货地址
     */
    public function addressAdd(): void
    {
        if ($this->request->isPost()) {
            $params = $this->request->post(['name', 'mobile', 'province', 'city', 'district', 'detail', 'is_default']);

            if (empty($params['name']) || empty($params['mobile']) || empty($params['detail'])) {
                $this->error('请填写完整的收货信息');
            }

            $userId = $this->auth->id;

            // 如果设为默认，先取消其他默认
            if (!empty($params['is_default'])) {
                UserAddressModel::where('user_id', $userId)->update(['is_default' => 0]);
            }

            // 如果是第一个地址，自动设为默认
            $count = UserAddressModel::where('user_id', $userId)->count();
            if ($count === 0) {
                $params['is_default'] = 1;
            }

            $address = UserAddressModel::create([
                'user_id'    => $userId,
                'name'       => $params['name'],
                'mobile'     => $params['mobile'],
                'province'   => $params['province'] ?? '',
                'city'       => $params['city'] ?? '',
                'district'   => $params['district'] ?? '',
                'detail'     => $params['detail'],
                'is_default' => $params['is_default'] ?? 0,
            ]);

            $this->success('添加成功', ['address' => $address->toArray()]);
        }

        $this->error('请求方式错误');
    }

    /**
     * 编辑收货地址
     */
    public function addressEdit(): void
    {
        if ($this->request->isPost()) {
            $params = $this->request->post(['id', 'name', 'mobile', 'province', 'city', 'district', 'detail', 'is_default']);

            $id = intval($params['id'] ?? 0);
            if ($id <= 0) {
                $this->error('参数错误');
            }

            $address = UserAddressModel::where('id', $id)
                ->where('user_id', $this->auth->id)
                ->find();

            if (!$address) {
                $this->error('地址不存在');
            }

            // 如果设为默认，先取消其他默认
            if (!empty($params['is_default'])) {
                UserAddressModel::where('user_id', $this->auth->id)
                    ->where('id', '<>', $id)
                    ->update(['is_default' => 0]);
            }

            $address->save([
                'name'       => $params['name'] ?? $address->name,
                'mobile'     => $params['mobile'] ?? $address->mobile,
                'province'   => $params['province'] ?? $address->province,
                'city'       => $params['city'] ?? $address->city,
                'district'   => $params['district'] ?? $address->district,
                'detail'     => $params['detail'] ?? $address->detail,
                'is_default' => $params['is_default'] ?? $address->is_default,
            ]);

            $this->success('修改成功');
        }

        $this->error('请求方式错误');
    }

    /**
     * 删除收货地址
     */
    public function addressDelete(): void
    {
        if ($this->request->isPost()) {
            $id = $this->request->post('id/d', 0);

            $address = UserAddressModel::where('id', $id)
                ->where('user_id', $this->auth->id)
                ->find();

            if (!$address) {
                $this->error('地址不存在');
            }

            $wasDefault = $address->is_default;
            $address->delete();

            // 如果删除的是默认地址，把最新的一条设为默认
            if ($wasDefault) {
                $first = UserAddressModel::where('user_id', $this->auth->id)
                    ->order('update_time desc')
                    ->find();
                if ($first) {
                    $first->is_default = 1;
                    $first->save();
                }
            }

            $this->success('删除成功');
        }

        $this->error('请求方式错误');
    }

    /**
     * 设为默认地址
     */
    public function addressSetDefault(): void
    {
        if ($this->request->isPost()) {
            $id = $this->request->post('id/d', 0);

            $address = UserAddressModel::where('id', $id)
                ->where('user_id', $this->auth->id)
                ->find();

            if (!$address) {
                $this->error('地址不存在');
            }

            UserAddressModel::where('user_id', $this->auth->id)->update(['is_default' => 0]);
            $address->is_default = 1;
            $address->save();

            $this->success('设置成功');
        }

        $this->error('请求方式错误');
    }

    // ========== 订单相关 ==========

    /**
     * 消费者订单列表
     */
    public function orders(): void
    {
        $page     = $this->request->get('page/d', 1);
        $pageSize = $this->request->get('pageSize/d', 20);
        $status   = $this->request->get('status', '');

        $query = OrderModel::alias('o')
            ->leftJoin('pt_provider_demand d', 'd.order_id = o.id')
            ->where('o.user_id', $this->auth->id)
            ->field('o.*');
        if ($status && $status !== 'all') {
            if ($status === 'delivering') {
                $query->where(function ($q) {
                    $q->whereIn('o.status', ['serving', 'delivering'])
                        ->where(function ($subQuery) {
                            $subQuery->whereNull('d.id')
                                ->whereOr('d.status', 'not in', ['wait_final_pay', 'closed']);
                        });
                });
            } elseif ($status === 'completed') {
                $query->where(function ($q) {
                    $q->where('o.status', 'completed')
                        ->whereOr(function ($subQuery) {
                            $subQuery->where('o.status', 'serving')
                                ->whereIn('d.status', ['wait_final_pay', 'closed']);
                        });
                });
            } else {
                $query->where('o.status', $status);
            }
        }

        $res = $query->order('o.create_time', 'desc')->paginate([
            'page'      => $page,
            'list_rows' => $pageSize,
        ]);

        $rawOrders = $res->items();
        $orderIds = [];
        foreach ($rawOrders as $order) {
            $orderIds[] = intval($order->id);
        }
        $orderIds = array_values(array_unique(array_filter($orderIds)));

        $demandMap = [];
        $settlementDemandMap = [];
        if ($orderIds) {
            $demands = ProviderDemand::whereIn('order_id', $orderIds)->select();
            foreach ($demands as $demand) {
                $demandMap[intval($demand->order_id)] = $demand;
            }

            $settlementDemands = ProviderDemand::whereIn('settlement_order_id', $orderIds)->select();
            foreach ($settlementDemands as $demand) {
                $settlementDemandMap[intval($demand->settlement_order_id)] = $demand;
            }
        }

        $reviewTargetOrderIds = $orderIds;
        foreach ($rawOrders as $order) {
            $settlementDemand = $settlementDemandMap[intval($order->id)] ?? null;
            if ($settlementDemand) {
                $reviewTargetOrderIds[] = intval($settlementDemand->order_id);
            }
        }
        $reviewTargetOrderIds = array_values(array_unique(array_filter($reviewTargetOrderIds)));

        $reviewedOrderIds = [];
        if ($reviewTargetOrderIds) {
            $reviewedOrderIds = ReviewModel::whereIn('order_id', $reviewTargetOrderIds)
                ->where('user_id', $this->auth->id)
                ->where('review_role', 'owner')
                ->column('order_id');
        }

        $items = [];
        foreach ($rawOrders as $order) {
            $provider = RiderModel::find($order->provider_id);
            $settlementDemand = $settlementDemandMap[intval($order->id)] ?? null;
            $mainDemand = $demandMap[intval($order->id)] ?? null;
            $demand = $mainDemand ?: $settlementDemand;
            $isSettlementOrder = $settlementDemand ? true : false;
            $statusValue = $order->status === 'serving' ? 'delivering' : $order->status;
            if ($demand && !$isSettlementOrder && in_array((string)$demand->status, ['wait_final_pay', 'closed'], true) && $statusValue === 'delivering') {
                $statusValue = 'completed';
            }

            $reviewTargetOrderId = $demand ? intval($demand->order_id) : intval($order->id);
            $isDemandOrder = $demand ? true : false;
            $demandStage = $isDemandOrder ? ($isSettlementOrder ? 'final' : 'deposit') : '';
            $demandTotalAmount = $demand ? number_format(floatval($demand->agreed_amount ?? 0), 2, '.', '') : number_format(floatval($order->total_amount), 2, '.', '');
            $demandDepositAmount = $demand ? number_format(floatval($demand->deposit_amount ?? 1), 2, '.', '') : '0.00';
            $demandSettlementAmount = $demand
                ? number_format(max(0, round(floatval($demand->agreed_amount ?? 0) - floatval($demand->deposit_amount ?? 1), 2)), 2, '.', '')
                : '0.00';
            $canReview = $statusValue === 'completed'
                && $reviewTargetOrderId === intval($order->id)
                && (!$demand || (string)$demand->status === 'closed')
                && !in_array($reviewTargetOrderId, array_map('intval', $reviewedOrderIds), true);
            $items[] = array_merge($order->toArray(), [
                'status'       => $statusValue,
                'rider_name'   => $provider ? $provider->name : '',
                'product_name' => $order->service_name,
                'product_id'   => $order->service_id,
                'order_type'   => $order->service_type === 'online' ? 'scan' : 'delivery',
                'time'         => date('Y-m-d H:i:s', (int)$order->create_time),
                'demand_id'    => $demand ? intval($demand->id) : 0,
                'demand_status'=> $demand ? (string)$demand->status : '',
                'allow_confirm'=> $statusValue === 'delivering' && (!$demand || (string)$demand->status === 'done_wait_owner'),
                'reviewed'     => in_array($reviewTargetOrderId, array_map('intval', $reviewedOrderIds), true),
                'can_review'   => $canReview,
                'review_target_order_id' => $reviewTargetOrderId,
                'is_demand_order' => $isDemandOrder,
                'demand_order_stage' => $demandStage,
                'demand_order_stage_text' => $demandStage === 'final' ? '尾款订单' : ($demandStage === 'deposit' ? '押金订单' : ''),
                'demand_total_amount' => $demandTotalAmount,
                'demand_deposit_amount' => $demandDepositAmount,
                'demand_settlement_amount' => $demandSettlementAmount,
            ]);
        }

        $this->success('', [
            'list'  => $items,
            'total' => $res->total(),
        ]);
    }

    /**
     * 消费者订单详情
     */
    public function orderDetail(): void
    {
        $id = $this->request->get('id/d', 0);

        $order = OrderModel::where('id', $id)
            ->where('user_id', $this->auth->id)
            ->find();

        if (!$order) {
            $this->error('订单不存在');
        }

        $provider = RiderModel::find($order->provider_id);
        $service  = ProductModel::find($order->service_id);
        $mainDemand = ProviderDemand::where('order_id', $order->id)->find();
        $settlementDemand = ProviderDemand::where('settlement_order_id', $order->id)->find();
        $demand = $mainDemand ?: $settlementDemand;
        $isSettlementOrder = $settlementDemand ? true : false;
        $statusValue = $order->status === 'serving' ? 'delivering' : $order->status;
        if ($demand && !$isSettlementOrder && in_array((string)$demand->status, ['wait_final_pay', 'closed'], true) && $statusValue === 'delivering') {
            $statusValue = 'completed';
        }

        $reviewTargetOrderId = $demand ? intval($demand->order_id) : intval($order->id);
        $reviewed = ReviewModel::where('order_id', $reviewTargetOrderId)
            ->where('user_id', $this->auth->id)
            ->where('review_role', 'owner')
            ->find();

        $demandStage = $demand ? ($isSettlementOrder ? 'final' : 'deposit') : '';
        $demandTotalAmount = $demand ? number_format(floatval($demand->agreed_amount ?? 0), 2, '.', '') : number_format(floatval($order->total_amount), 2, '.', '');
        $demandDepositAmount = $demand ? number_format(floatval($demand->deposit_amount ?? 1), 2, '.', '') : '0.00';
        $demandSettlementAmount = $demand
            ? number_format(max(0, round(floatval($demand->agreed_amount ?? 0) - floatval($demand->deposit_amount ?? 1), 2)), 2, '.', '')
            : '0.00';
        $canReview = $statusValue === 'completed'
            && $reviewTargetOrderId === intval($order->id)
            && (!$demand || (string)$demand->status === 'closed')
            && !$reviewed;

        $this->success('', [
            'order' => array_merge($order->toArray(), [
                'status'        => $statusValue,
                'rider_name'    => $provider ? $provider->name : '',
                'rider_mobile'  => $provider ? $provider->mobile : '',
                'rider_credit'  => $provider ? (int)round(((float)$provider->rating) * 20) : 0,
                'product_name'  => $order->service_name,
                'product_id'    => $order->service_id,
                'product_image' => $service ? $service->image : '',
                'order_type'    => $order->service_type === 'online' ? 'scan' : 'delivery',
                'time'          => date('Y-m-d H:i:s', (int)$order->create_time),
                'pay_amount'    => $order->total_amount,
                'delivery_fee'  => '0.00',
                'demand_id'     => $demand ? intval($demand->id) : 0,
                'demand_status' => $demand ? (string)$demand->status : '',
                'allow_confirm' => $statusValue === 'delivering' && (!$demand || (string)$demand->status === 'done_wait_owner'),
                'reviewed'      => $reviewed ? true : false,
                'can_review'    => $canReview,
                'review_target_order_id' => $reviewTargetOrderId,
                'is_demand_order' => $demand ? true : false,
                'demand_order_stage' => $demandStage,
                'demand_order_stage_text' => $demandStage === 'final' ? '尾款订单' : ($demandStage === 'deposit' ? '押金订单' : ''),
                'demand_total_amount' => $demandTotalAmount,
                'demand_deposit_amount' => $demandDepositAmount,
                'demand_settlement_amount' => $demandSettlementAmount,
            ]),
        ]);
    }

    /**
     * 下单购买
     */
    public function createOrder(): void
    {
        if ($this->request->isPost()) {
            $params = $this->request->post(['stock_id', 'rider_id', 'quantity', 'order_type', 'buyer_name', 'buyer_mobile', 'buyer_address']);

            $stockId   = intval($params['stock_id'] ?? 0);
            $providerId = intval($params['rider_id'] ?? 0);
            $quantity  = intval($params['quantity'] ?? 1);
            $orderType = $params['order_type'] ?? 'delivery';

            if ($stockId <= 0 || $quantity <= 0) {
                $this->error('参数错误');
            }

            $service = ProductModel::where('id', $stockId)
                ->where('status', 'on')
                ->find();
            if (!$service) {
                $this->error('服务已下架');
            }

            $serviceProviderId = intval($service->provider_id ?? 0);
            if ($serviceProviderId > 0) {
                if ($providerId > 0 && $providerId !== $serviceProviderId) {
                    $this->error('服务与服务者不匹配');
                }
                $providerId = $serviceProviderId;
            }

            $providerQuery = RiderModel::where('status', '<>', 'rejected');
            if ($providerId > 0) {
                $providerQuery->where('id', $providerId);
            }
            $provider = $providerQuery->order('rating', 'desc')->find();
            if (!$provider) {
                $this->error('服务者不可用');
            }

            $unitPrice    = (string)$service->base_price;
            $totalAmount  = bcmul($unitPrice, (string)$quantity, 2);
            $providerIncome = $totalAmount;
            $platformFee  = '0.00';
            $orderNo      = 'PT' . date('YmdHis') . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $serviceType  = $orderType === 'scan' ? 'online' : 'onsite';

            Db::startTrans();
            try {
                // 创建订单
                $order = OrderModel::create([
                    'order_no'      => $orderNo,
                    'provider_id'   => $provider->id,
                    'user_id'       => $this->auth->id,
                    'service_id'    => $service->id,
                    'service_name'  => $service->name,
                    'quantity'      => $quantity,
                    'unit_price'    => $unitPrice,
                    'total_amount'  => $totalAmount,
                    'provider_income' => $providerIncome,
                    'platform_fee'  => $platformFee,
                    'service_type'  => $serviceType,
                    'status'        => 'pending',
                    'owner_name'    => $params['buyer_name'] ?? '',
                    'owner_mobile'  => $params['buyer_mobile'] ?? '',
                    'service_address' => $params['buyer_address'] ?? '',
                ]);

                Db::commit();
            } catch (Throwable $e) {
                Db::rollback();
                $this->error('下单失败: ' . $e->getMessage());
            }

            $this->success('下单成功', [
                'order' => $order->toArray(),
            ]);
        }

        $this->error('请求方式错误');
    }

    /**
     * 确认收货
     */
    public function confirmOrder(): void
    {
        if ($this->request->isPost()) {
            $id = $this->request->post('id/d', 0);

            $order = OrderModel::where('id', $id)
                ->where('user_id', $this->auth->id)
                ->find();

            if (!$order) {
                $this->error('订单不存在');
            }

            if (!in_array($order->status, ['paid', 'delivering', 'serving'])) {
                $this->error('订单状态不允许确认收货');
            }

            Db::startTrans();
            try {
                $order->status       = 'completed';
                $order->completed_at = time();
                $order->save();

                // 更新服务者统计
                $provider = RiderModel::find($order->provider_id);
                if ($provider) {
                    $provider->total_orders += 1;
                    $provider->balance      += (float)$order->provider_income;
                    $provider->total_income += (float)$order->provider_income;
                    $provider->save();
                }

                Db::commit();
            } catch (Throwable $e) {
                Db::rollback();
                $this->error('操作失败: ' . $e->getMessage());
            }

            $this->success('收货成功');
        }

        $this->error('请求方式错误');
    }

    /**
     * 取消订单（含退款）
     */
    public function cancelOrder(): void
    {
        if ($this->request->isPost()) {
            $id = $this->request->post('id/d', 0);

            $order = OrderModel::where('id', $id)
                ->where('user_id', $this->auth->id)
                ->find();

            if (!$order) {
                $this->error('订单不存在');
            }

            if (in_array($order->status, ['completed', 'cancelled', 'refunded'])) {
                $this->error('订单状态不允许取消');
            }

            $needRefund = in_array($order->status, ['paid', 'delivering', 'serving']);

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

                $demand = ProviderDemand::where('order_id', $order->id)
                    ->where('user_id', $this->auth->id)
                    ->find();
                if ($demand && (string)$demand->status !== 'closed') {
                    $demand->save([
                        'status' => 'closed',
                    ]);
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
