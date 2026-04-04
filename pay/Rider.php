<?php

namespace app\api\controller;

use Throwable;
use think\facade\Db;
use think\facade\Config;
use app\common\facade\Token;
use app\common\controller\Frontend;
use app\admin\model\petto\Provider as RiderModel;
use app\admin\model\petto\ProviderDemand;
use app\admin\model\petto\Service as ServiceModel;
use app\admin\model\petto\Order as OrderModel;
use app\admin\model\petto\Review as ReviewModel;
use app\admin\model\petto\IncomeLog;

class Rider extends Frontend
{
    protected array $noNeedLogin = ['login'];

    public function initialize(): void
    {
        parent::initialize();
    }

    /**
     * 骑手登录
     * 使用 BuildAdmin 的 user 表进行认证，登录成功后自动关联/创建骑手记录
     * @throws Throwable
     */
    public function login(): void
    {
        if ($this->auth->isLogin()) {
            $rider = RiderModel::where('user_id', $this->auth->id)->find();
            $this->success('已登录', [
                'userInfo'  => $this->auth->getUserInfo(),
                'riderInfo' => $rider ? $rider->toArray() : null,
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
                $userInfo = $this->auth->getUserInfo();
                $rider    = $this->getOrCreateRider($this->auth->id);

                $this->success('登录成功', [
                    'userInfo'  => $userInfo,
                    'riderInfo' => $rider->toArray(),
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
     * 发布需求
     */
    public function publishDemand(): void
    {
        $this->error('请在宠物主人视图发布需求');
    }

    /**
     * 我的需求列表
     */
    public function myDemands(): void
    {
        $this->error('请在宠物主人视图查看需求');
    }

    /**
     * 服务者接单中心需求池
     */
    public function openDemands(): void
    {
        $rider = RiderModel::where('user_id', $this->auth->id)->find();
        if (!$rider) {
            $this->error('请先开通服务者身份');
        }
        if ($rider->status === 'rejected') {
            $this->error('服务者身份审核未通过');
        }

        $page = $this->request->get('page/d', 1);
        $pageSize = $this->request->get('pageSize/d', 20);
        $petType = (string)$this->request->get('pet_type', 'all');

        $query = ProviderDemand::alias('d')
            ->join('pt_order o', 'o.id = d.order_id')
            ->where('d.status', 'open')
            ->where('d.provider_id', 0)
            ->where('d.user_id', '<>', $this->auth->id)
            ->where('o.status', 'paid')
            ->field([
                'd.*',
                'o.order_no',
                'o.status as order_status',
                'd.agreed_amount as pay_amount',
            ]);
        if ($petType !== '' && $petType !== 'all') {
            $query->where(function ($q) use ($petType) {
                $q->where('d.pet_type', $petType)->whereOr('d.pet_type', 'all');
            });
        }

        $res = $query->order('d.id', 'desc')->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        $this->success('', [
            'list' => $res->items(),
            'total' => $res->total(),
        ]);
    }

    /**
     * 服务者已接单需求
     */
    public function acceptedDemands(): void
    {
        $rider = RiderModel::where('user_id', $this->auth->id)->find();
        if (!$rider) {
            $this->error('请先开通服务者身份');
        }
        if ($rider->status === 'rejected') {
            $this->error('服务者身份审核未通过');
        }

        $page = $this->request->get('page/d', 1);
        $pageSize = $this->request->get('pageSize/d', 20);
        $status = (string)$this->request->get('status', 'all');

        $query = ProviderDemand::alias('d')
            ->join('pt_order o', 'o.id = d.order_id')
            ->leftJoin('pt_order so', 'so.id = d.settlement_order_id')
            ->where('d.provider_id', $rider->id)
            ->field([
                'd.*',
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
            $row = is_array($item) ? $item : $item->toArray();
            $orderId = intval($row['order_id'] ?? 0);
            if ($orderId > 0) {
                $orderIds[] = $orderId;
            }
        }
        $orderIds = array_values(array_unique($orderIds));
        $reviewedOrderIds = [];
        if ($orderIds) {
            $reviewedOrderIds = ReviewModel::whereIn('order_id', $orderIds)
                ->where('user_id', $this->auth->id)
                ->where('review_role', 'provider')
                ->column('order_id');
        }

        $list = [];
        foreach ($items as $item) {
            $row = is_array($item) ? $item : $item->toArray();
            $row['settlement_amount'] = max(0, round(floatval($row['pay_amount'] ?? 0) - floatval($row['deposit_amount'] ?? 1), 2));
            $row['provider_reviewed'] = in_array(intval($row['order_id'] ?? 0), array_map('intval', $reviewedOrderIds), true);
            $row['can_review'] = (string)($row['status'] ?? '') === 'closed' && (string)($row['order_status'] ?? '') === 'completed' && !$row['provider_reviewed'];
            $list[] = $row;
        }

        $this->success('', [
            'list' => $list,
            'total' => $res->total(),
        ]);
    }

    /**
     * 服务者接单（认领需求）
     */
    public function acceptDemand(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }

        $rider = RiderModel::where('user_id', $this->auth->id)->find();
        if (!$rider) {
            $this->error('请先开通服务者身份');
        }
        if ($rider->status === 'rejected') {
            $this->error('服务者身份审核未通过');
        }

        $demandId = $this->request->post('id/d', 0);
        if ($demandId <= 0) {
            $this->error('需求ID无效');
        }

        Db::startTrans();
        try {
            $demand = ProviderDemand::where('id', $demandId)
                ->where('status', 'open')
                ->where('provider_id', 0)
                ->lock(true)
                ->find();
            if (!$demand) {
                Db::rollback();
                $this->error('该需求已被接单或已关闭');
            }

            if (intval($demand->user_id) === intval($this->auth->id)) {
                Db::rollback();
                $this->error('不能接自己发布的需求');
            }

            $order = OrderModel::where('id', intval($demand->order_id))
                ->lock(true)
                ->find();
            if (!$order) {
                Db::rollback();
                $this->error('关联订单不存在');
            }
            if ((string)$order->status !== 'paid') {
                Db::rollback();
                $this->error('该需求尚未支付，暂不可接单');
            }

            $demand->save([
                'provider_id' => $rider->id,
                'status' => 'matched',
            ]);

            $order->save([
                'provider_id' => $rider->id,
                'status' => 'serving',
            ]);

            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $this->error('接单失败: ' . $e->getMessage());
        }

        $this->success('接单成功', [
            'demand' => $demand->toArray(),
        ]);
    }

    /**
     * 服务者标记需求完成
     */
    public function finishDemand(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }

        $rider = RiderModel::where('user_id', $this->auth->id)->find();
        if (!$rider) {
            $this->error('请先开通服务者身份');
        }
        if ($rider->status === 'rejected') {
            $this->error('服务者身份审核未通过');
        }

        $demandId = $this->request->post('id/d', 0);
        if ($demandId <= 0) {
            $this->error('需求ID无效');
        }

        $demand = ProviderDemand::where('id', $demandId)
            ->where('provider_id', $rider->id)
            ->find();
        if (!$demand) {
            $this->error('需求不存在或不属于当前服务者');
        }
        if ((string)$demand->status === 'done_wait_owner' || (string)$demand->status === 'closed') {
            $this->success('该需求已提交完成', ['demand' => $demand->toArray()]);
        }
        if ((string)$demand->status !== 'matched') {
            $this->error('仅已接单需求可标记完成');
        }

        $demand->save([
            'status' => 'done_wait_owner',
        ]);

        $this->success('已标记完成', [
            'demand' => $demand->toArray(),
        ]);
    }

    /**
     * 开通服务者身份（复用当前登录账号）
     * 兼容旧路由: /rider/register
     */
    public function register(): void
    {
        if ($this->request->isPost()) {
            if (!$this->auth->isLogin()) {
                $this->error('请先登录后开通服务者身份', [
                    'type' => $this->auth::NEED_LOGIN,
                ], $this->auth::LOGIN_RESPONSE_CODE);
            }

            $params = $this->request->post([
                'name',
                'mobile',
                'service_area',
                'skill_tags',
                'experience_years',
                'has_pet_care_certificate',
                'id_card_no',
                'apply_note',
            ]);

            $user = $this->auth->getUser();

            $name = trim((string)($params['name'] ?? ''));
            if ($name === '') {
                $name = (string)($user->nickname ?: $user->username);
            }

            $mobile = trim((string)($params['mobile'] ?? ''));
            if ($mobile === '') {
                $mobile = (string)($user->mobile ?: '');
            }

            $serviceArea = trim((string)($params['service_area'] ?? ''));
            if ($serviceArea === '') {
                $this->error('请填写服务范围');
            }

            $experienceYears = intval($params['experience_years'] ?? 0);
            if ($experienceYears < 0 || $experienceYears > 30) {
                $this->error('从业年限需在0-30之间');
            }

            $skillTags = $params['skill_tags'] ?? [];
            if (is_string($skillTags)) {
                $skillTags = explode(',', $skillTags);
            }
            if (!is_array($skillTags)) {
                $skillTags = [];
            }
            $skillTags = array_values(array_filter(array_map(static function ($item) {
                return trim((string)$item);
            }, $skillTags), static function ($item) {
                return $item !== '';
            }));
            if (count($skillTags) === 0) {
                $this->error('请至少填写1个技能标签');
            }

            $skillType = $this->mapSkillType($skillTags[0]);
            $level = $experienceYears >= 8 ? 'expert' : ($experienceYears >= 3 ? 'senior' : 'junior');

            $idCardNo = trim((string)($params['id_card_no'] ?? ''));
            if ($idCardNo !== '' && !preg_match('/^(\d{15}|\d{17}[\dXx])$/', $idCardNo)) {
                $this->error('身份证号格式不正确');
            }

            $rider = RiderModel::where('user_id', $this->auth->id)->find();
            $isNew = false;
            if (!$rider) {
                $isNew = true;
                $rider = new RiderModel();
                $rider->user_id = $this->auth->id;
                $rider->status = 'pending';
            }

            $saveData = [
                'name' => $name,
                'mobile' => $mobile,
                'work_area' => $serviceArea,
                'skill_type' => $skillType,
                'level' => $level,
                'id_card' => $idCardNo,
            ];
            if ($isNew) {
                $saveData['rating'] = 5.00;
            }

            $rider->save($saveData);

            $this->success($isNew ? '开通申请已提交' : '服务者信息已更新', [
                'userInfo'  => $this->auth->getUserInfo(),
                'riderInfo' => $rider->toArray(),
                'extra'     => [
                    'skill_tags' => $skillTags,
                    'experience_years' => $experienceYears,
                    'has_pet_care_certificate' => intval($params['has_pet_care_certificate'] ?? 0),
                    'apply_note' => trim((string)($params['apply_note'] ?? '')),
                ],
            ]);
        }

        $this->error('请求方式错误');
    }

    public function myServices(): void
    {
        $rider = RiderModel::where('user_id', $this->auth->id)->find();
        if (!$rider) {
            $this->error('请先开通服务者身份');
        }
        if ($rider->status === 'rejected') {
            $this->error('服务者身份审核未通过');
        }

        $list = ServiceModel::where('provider_id', $rider->id)
            ->order('weigh', 'desc')
            ->order('id', 'desc')
            ->select()
            ->toArray();

        $this->success('', [
            'list' => $list,
        ]);
    }

    public function saveService(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }

        $rider = RiderModel::where('user_id', $this->auth->id)->find();
        if (!$rider) {
            $this->error('请先开通服务者身份');
        }
        if ($rider->status === 'rejected') {
            $this->error('服务者身份审核未通过');
        }

        $params = $this->request->post([
            'id',
            'name',
            'category',
            'image',
            'pet_type',
            'base_price',
            'duration',
            'description',
            'is_hot',
            'weigh',
            'status',
        ]);

        $id = intval($params['id'] ?? 0);
        $name = trim((string)($params['name'] ?? ''));
        $category = trim((string)($params['category'] ?? ''));
        $image = trim((string)($params['image'] ?? ''));
        $petType = trim((string)($params['pet_type'] ?? 'all'));
        $basePrice = round(floatval($params['base_price'] ?? 0), 2);
        $duration = intval($params['duration'] ?? 60);
        $description = trim((string)($params['description'] ?? ''));
        $isHot = intval($params['is_hot'] ?? 0) ? 1 : 0;
        $weigh = intval($params['weigh'] ?? 0);
        $status = trim((string)($params['status'] ?? 'on'));

        if ($name === '' || mb_strlen($name) > 100) {
            $this->error('服务名称不能为空且不超过100字');
        }
        if ($category === '' || mb_strlen($category) > 50) {
            $this->error('服务分类不能为空且不超过50字');
        }
        if (!in_array($petType, ['cat', 'dog', 'all', 'other'], true)) {
            $this->error('适用宠物类型不正确');
        }
        if ($basePrice <= 0) {
            $this->error('服务价格必须大于0');
        }
        if ($duration <= 0 || $duration > 1440) {
            $this->error('服务时长需在1-1440分钟之间');
        }
        if ($description === '' || mb_strlen($description) > 500) {
            $this->error('服务描述不能为空且不超过500字');
        }
        if (!in_array($status, ['on', 'off'], true)) {
            $this->error('服务状态不正确');
        }

        if ($id > 0) {
            $service = ServiceModel::where('id', $id)
                ->where('provider_id', $rider->id)
                ->find();
            if (!$service) {
                $this->error('服务不存在');
            }
        } else {
            $service = new ServiceModel();
        }

        $service->save([
            'provider_id' => $rider->id,
            'name' => $name,
            'category' => $category,
            'image' => $image,
            'pet_type' => $petType,
            'base_price' => number_format($basePrice, 2, '.', ''),
            'duration' => $duration,
            'description' => $description,
            'is_hot' => $isHot,
            'weigh' => $weigh,
            'status' => $status,
        ]);

        $this->success($id > 0 ? '服务已更新' : '服务已创建', [
            'service' => $service->toArray(),
        ]);
    }

    public function toggleService(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }

        $rider = RiderModel::where('user_id', $this->auth->id)->find();
        if (!$rider) {
            $this->error('请先开通服务者身份');
        }

        $id = $this->request->post('id/d', 0);
        $status = trim((string)$this->request->post('status', ''));
        if ($id <= 0 || !in_array($status, ['on', 'off'], true)) {
            $this->error('参数错误');
        }

        $service = ServiceModel::where('id', $id)
            ->where('provider_id', $rider->id)
            ->find();
        if (!$service) {
            $this->error('服务不存在');
        }

        $service->save([
            'status' => $status,
        ]);

        $this->success('操作成功', [
            'service' => $service->toArray(),
        ]);
    }

    private function mapSkillType(string $skillTag): string
    {
        $tag = mb_strtolower(trim($skillTag));
        if (str_contains($tag, '洗') || str_contains($tag, '美')) return 'grooming';
        if (str_contains($tag, '医') || str_contains($tag, '护')) return 'medical';
        if (str_contains($tag, '训')) return 'training';
        if (str_contains($tag, '寄')) return 'boarding';
        if (str_contains($tag, '喂') || str_contains($tag, '遛') || str_contains($tag, '澡')) return 'bath';
        return 'other';
    }

    /**
     * 获取骑手信息
     */
    public function info(): void
    {
        $rider = RiderModel::where('user_id', $this->auth->id)->find();
        if (!$rider) {
            $this->error('骑手信息不存在');
        }

        $this->success('', [
            'riderInfo' => $rider->toArray(),
        ]);
    }

    /**
     * 更新骑手信息
     */
    public function update(): void
    {
        if ($this->request->isPost()) {
            $rider = RiderModel::where('user_id', $this->auth->id)->find();
            if (!$rider) {
                $this->error('骑手信息不存在');
            }

            $data = $this->request->post(['name', 'avatar', 'platform', 'work_area']);
            $data = array_filter($data, function ($v) {
                return $v !== null && $v !== '';
            });

            if (!empty($data)) {
                $rider->save($data);
            }

            $this->success('更新成功', [
                'riderInfo' => $rider->toArray(),
            ]);
        }

        $this->error('请求方式错误');
    }

    /**
     * 切换模式
     */
    public function switchMode(): void
    {
        if ($this->request->isPost()) {
            $mode = $this->request->post('mode', '');
            if (!in_array($mode, ['delivery', 'retail'])) {
                $this->error('无效的模式');
            }

            $rider = RiderModel::where('user_id', $this->auth->id)->find();
            if (!$rider) {
                $this->error('骑手信息不存在');
            }

            $rider->mode = $mode;
            $rider->save();

            $this->success('切换成功', [
                'mode' => $mode,
            ]);
        }

        $this->error('请求方式错误');
    }

    /**
     * 首页统计数据
     */
    public function homeStats(): void
    {
        $rider = RiderModel::where('user_id', $this->auth->id)->find();
        if (!$rider) {
            $this->error('骑手信息不存在');
        }

        // 今日订单数
        $todayStart  = strtotime('today');
        $todayOrders = OrderModel::where('rider_id', $rider->id)
            ->where('create_time', '>=', $todayStart)
            ->count();

        // 今日收入
        $todayIncome = IncomeLog::where('rider_id', $rider->id)
            ->where('type', 'in', ['sale', 'commission', 'bonus'])
            ->where('create_time', '>=', $todayStart)
            ->sum('amount');

        // 待处理订单
        $pendingOrders = OrderModel::where('rider_id', $rider->id)
            ->where('status', 'in', ['paid', 'delivering'])
            ->order('create_time', 'desc')
            ->limit(5)
            ->select()
            ->toArray();

        // 库存商品数
        $stockCount = RiderStock::where('rider_id', $rider->id)
            ->where('status', 'on')
            ->count();

        $this->success('', [
            'riderInfo'     => $rider->toArray(),
            'todayOrders'   => $todayOrders,
            'todayIncome'   => round($todayIncome, 2),
            'pendingOrders' => $pendingOrders,
            'stockCount'    => $stockCount,
        ]);
    }

    /**
     * 收益明细
     */
    public function income(): void
    {
        $rider = RiderModel::where('user_id', $this->auth->id)->find();
        if (!$rider) {
            $this->error('骑手信息不存在');
        }

        $page     = $this->request->get('page/d', 1);
        $pageSize = $this->request->get('pageSize/d', 20);
        $type     = $this->request->get('type', '');

        $query = IncomeLog::where('rider_id', $rider->id);
        if ($type && $type !== 'all') {
            $query->where('type', $type);
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
     * 提现
     */
    public function withdraw(): void
    {
        if ($this->request->isPost()) {
            $rider = RiderModel::where('user_id', $this->auth->id)->find();
            if (!$rider) {
                $this->error('骑手信息不存在');
            }

            $amount = $this->request->post('amount/f', 0);
            if ($amount < 10) {
                $this->error('最低提现金额为10元');
            }
            if ($amount > $rider->balance) {
                $this->error('余额不足');
            }

            Db::startTrans();
            try {
                $beforeBalance = $rider->balance;
                $rider->balance -= $amount;
                $rider->save();

                IncomeLog::create([
                    'rider_id'       => $rider->id,
                    'order_id'       => 0,
                    'type'           => 'withdraw',
                    'amount'         => -$amount,
                    'before_balance' => $beforeBalance,
                    'after_balance'  => $rider->balance,
                    'memo'           => '提现 ¥' . number_format($amount, 2),
                ]);

                Db::commit();
            } catch (Throwable $e) {
                Db::rollback();
                $this->error('提现失败: ' . $e->getMessage());
            }

            $this->success('提现申请已提交', [
                'balance' => $rider->balance,
            ]);
        }

        $this->error('请求方式错误');
    }

    /**
     * 上报骑手定位
     */
    public function updateLocation(): void
    {
        if ($this->request->isPost()) {
            $latitude  = $this->request->post('latitude/f', 0);
            $longitude = $this->request->post('longitude/f', 0);

            if ($latitude == 0 || $longitude == 0) {
                $this->error('定位数据无效');
            }

            $rider = RiderModel::where('user_id', $this->auth->id)->find();
            if (!$rider) {
                $this->error('骑手信息不存在');
            }

            $rider->latitude  = $latitude;
            $rider->longitude = $longitude;
            $rider->location_updated_at = time();
            $rider->save();

            $this->success('定位已更新');
        }

        $this->error('请求方式错误');
    }

    /**
     * 退出登录
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
     * 获取或创建骑手记录
     * @param int   $userId
     * @param array $extra
     * @return RiderModel
     */
    private function getOrCreateRider(int $userId, array $extra = []): RiderModel
    {
        $rider = RiderModel::where('user_id', $userId)->find();
        if (!$rider) {
            $user = $this->auth->getUser();
            $data = array_merge([
                'user_id' => $userId,
                'name'    => $user->nickname ?: $user->username,
                'mobile'  => $user->mobile ?: '',
                'status'  => 'active',
            ], $extra);

            $rider = RiderModel::create($data);
        }
        return $rider;
    }

}
