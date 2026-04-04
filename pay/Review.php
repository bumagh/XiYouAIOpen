<?php

namespace app\api\controller;

use app\admin\model\petto\Order as OrderModel;
use app\admin\model\petto\Provider as RiderModel;
use app\admin\model\petto\ProviderDemand;
use app\admin\model\petto\Review as ReviewModel;
use app\common\controller\Frontend;
use app\common\model\User as UserModel;
use think\facade\Db;
use Throwable;

class Review extends Frontend
{
    public function initialize(): void
    {
        parent::initialize();
    }

    public function context(): void
    {
        $orderId = $this->request->get('order_id/d', 0);
        $role = (string)$this->request->get('role', 'owner');
        if ($orderId <= 0) {
            $this->error('订单ID无效');
        }
        if (!in_array($role, ['owner', 'provider'], true)) {
            $this->error('评价角色无效');
        }

        [$order, $provider, $targetUser, $canReview, $existingReview] = $this->resolveReviewContext($orderId, $role);

        $this->success('', [
            'order' => [
                'id' => intval($order->id),
                'order_no' => (string)$order->order_no,
                'service_name' => (string)$order->service_name,
                'total_amount' => (string)$order->total_amount,
                'status' => (string)$order->status,
                'provider_name' => $provider ? (string)$provider->name : '',
                'provider_mobile' => $provider ? (string)$provider->mobile : '',
            ],
            'target' => [
                'user_id' => $targetUser ? intval($targetUser->id) : 0,
                'name' => $this->buildDisplayName($targetUser, $role === 'owner' ? ($provider ? (string)$provider->name : '') : ''),
                'mobile' => $targetUser ? (string)$targetUser->mobile : '',
                'role' => $role === 'owner' ? 'provider' : 'owner',
            ],
            'can_review' => $canReview,
            'existing_review' => $existingReview ? $existingReview->toArray() : null,
            'role' => $role,
        ]);
    }

    public function submit(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }

        $orderId = $this->request->post('order_id/d', 0);
        $role = (string)$this->request->post('role', 'owner');
        $rating = $this->request->post('rating/d', 5);
        $content = trim((string)$this->request->post('content', ''));

        if ($orderId <= 0) {
            $this->error('订单ID无效');
        }
        if (!in_array($role, ['owner', 'provider'], true)) {
            $this->error('评价角色无效');
        }
        if ($rating < 1 || $rating > 5) {
            $this->error('评分范围为1-5');
        }
        if ($content === '' || mb_strlen($content) > 300) {
            $this->error('评价内容不能为空且不超过300字');
        }

        [$order, $provider, $targetUser, $canReview] = $this->resolveReviewContext($orderId, $role);
        if (!$canReview) {
            $this->error('当前订单暂不可评价或已评价');
        }

        Db::startTrans();
        try {
            $review = ReviewModel::create([
                'order_id' => intval($order->id),
                'user_id' => intval($this->auth->id),
                'provider_id' => intval($order->provider_id),
                'review_role' => $role,
                'target_user_id' => $targetUser ? intval($targetUser->id) : 0,
                'rating' => $rating,
                'content' => $content,
                'images' => '',
                'reply' => '',
                'status' => 'visible',
            ]);

            if ($role === 'owner' && $provider) {
                $avgRating = ReviewModel::where('provider_id', $provider->id)
                    ->where('review_role', 'owner')
                    ->avg('rating');
                if ($avgRating !== null) {
                    $provider->save([
                        'rating' => number_format((float)$avgRating, 2, '.', ''),
                    ]);
                }
            }

            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $this->error('评价失败: ' . $e->getMessage());
        }

        $this->success('评价成功', [
            'review' => $review->toArray(),
        ]);
    }

    private function resolveReviewContext(int $orderId, string $role): array
    {
        $order = OrderModel::where('id', $orderId)->find();
        if (!$order) {
            $this->error('订单不存在');
        }
        if ((string)$order->status !== 'completed') {
            $this->error('仅已完成订单可评价');
        }

        $provider = RiderModel::find($order->provider_id);
        if ($role === 'owner') {
            if (intval($order->user_id) !== intval($this->auth->id)) {
                $this->error('无权评价该订单');
            }
            $targetUser = $provider ? UserModel::find($provider->user_id) : null;
        } else {
            $rider = RiderModel::where('user_id', $this->auth->id)->find();
            if (!$rider || intval($order->provider_id) !== intval($rider->id)) {
                $this->error('无权评价该订单');
            }
            $demand = ProviderDemand::where('order_id', $order->id)->find();
            if ($demand && (string)$demand->status !== 'closed') {
                $this->error('请等待宠物主人确认完成后再评价');
            }
            $targetUser = UserModel::find($order->user_id);
        }

        $existingReview = ReviewModel::where('order_id', $order->id)
            ->where('user_id', $this->auth->id)
            ->where('review_role', $role)
            ->find();

        return [$order, $provider, $targetUser, $existingReview ? false : true, $existingReview];
    }

    private function buildDisplayName(?UserModel $user, string $fallback): string
    {
        if ($fallback !== '') {
            return $fallback;
        }
        if (!$user) {
            return '';
        }
        $nickname = trim((string)$user->nickname);
        if ($nickname !== '') {
            return $nickname;
        }
        $mobile = trim((string)$user->mobile);
        if ($mobile !== '') {
            return substr($mobile, 0, 3) . '****' . substr($mobile, -4);
        }
        return '用户';
    }
}
