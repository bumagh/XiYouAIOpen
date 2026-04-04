<?php

namespace app\api\controller;

use Throwable;
use think\facade\Db;
use app\common\controller\Frontend;
use app\admin\model\petto\Provider as RiderModel;
use app\admin\model\petto\Service as ProductModel;

class RiderStock extends Frontend
{
    protected array $noNeedLogin = [];

    /**
     * 获取骑手库存列表
     */
    public function list(): void
    {
        $rider = RiderModel::where('user_id', $this->auth->id)->find();
        if (!$rider) {
            $this->error('骑手信息不存在');
        }

        $page     = $this->request->get('page/d', 1);
        $pageSize = $this->request->get('pageSize/d', 20);

        $res = RiderStockModel::where('rider_id', $rider->id)
            ->order('create_time', 'desc')
            ->paginate([
                'page'      => $page,
                'list_rows' => $pageSize,
            ]);

        $items = [];
        foreach ($res->items() as $stock) {
            $product = ProductModel::find($stock->product_id);
            $items[] = [
                'id'           => $stock->id,
                'product_id'   => $stock->product_id,
                'product_name' => $product ? $product->name : '',
                'image'        => $product ? $product->image : '',
                'category'     => $product ? $product->category : '',
                'sell_price'   => $product ? (string)$product->sell_price : '0',
                'profit'       => $product ? (string)$product->profit : '0',
                'quantity'     => $stock->quantity,
                'sold_count'   => $stock->sold_count,
                'status'       => $stock->status,
                'create_time'  => $stock->create_time,
            ];
        }

        $this->success('', [
            'list'  => $items,
            'total' => $res->total(),
        ]);
    }

    /**
     * 获取可上架商品列表（平台商品库）
     */
    public function productList(): void
    {
        $page     = $this->request->get('page/d', 1);
        $pageSize = $this->request->get('pageSize/d', 20);
        $category = $this->request->get('category', '');
        $keyword  = $this->request->get('keyword', '');

        $query = ProductModel::where('status', 'on');

        if ($category && $category !== '全部') {
            $query->where('category', $category);
        }
        if ($keyword) {
            $query->where('name', 'like', '%' . $keyword . '%');
        }

        $res = $query->order('weigh', 'desc')->order('id', 'desc')->paginate([
            'page'      => $page,
            'list_rows' => $pageSize,
        ]);

        $items = [];
        foreach ($res->items() as $product) {
            $items[] = [
                'id'          => $product->id,
                'name'        => $product->name,
                'category'    => $product->category,
                'image'       => $product->image,
                'sell_price'  => (string)$product->sell_price,
                'profit'      => (string)$product->profit,
                'stock'       => $product->stock,
                'unit'        => $product->unit,
                'description' => $product->description,
                'is_hot'      => (int)$product->is_hot,
            ];
        }

        $this->success('', [
            'list'  => $items,
            'total' => $res->total(),
        ]);
    }

    /**
     * 获取商品分类列表
     */
    public function categories(): void
    {
        $categories = ProductModel::where('status', 'on')
            ->group('category')
            ->column('category');

        array_unshift($categories, '全部');

        $this->success('', [
            'categories' => $categories,
        ]);
    }

    /**
     * 上架商品到骑手仓库
     */
    public function add(): void
    {
        if ($this->request->isPost()) {
            $rider = RiderModel::where('user_id', $this->auth->id)->find();
            if (!$rider) {
                $this->error('骑手信息不存在');
            }

            $productId = $this->request->post('product_id/d', 0);
            $quantity  = $this->request->post('quantity/d', 0);

            if ($productId <= 0 || $quantity <= 0) {
                $this->error('参数错误');
            }

            $product = ProductModel::find($productId);
            if (!$product || $product->status !== 'on') {
                $this->error('商品不存在或已下架');
            }

            if ($quantity > $product->stock) {
                $this->error('平台库存不足');
            }

            Db::startTrans();
            try {
                // 扣减平台库存
                $product->stock -= $quantity;
                $product->save();

                // 查找已有库存记录
                $stock = RiderStockModel::where('rider_id', $rider->id)
                    ->where('product_id', $productId)
                    ->find();

                if ($stock) {
                    $stock->quantity += $quantity;
                    $stock->status = 'on';
                    $stock->save();
                } else {
                    $stock = RiderStockModel::create([
                        'rider_id'   => $rider->id,
                        'product_id' => $productId,
                        'quantity'   => $quantity,
                        'sold_count' => 0,
                        'status'     => 'on',
                    ]);
                }

                Db::commit();
            } catch (Throwable $e) {
                Db::rollback();
                $this->error('上架失败: ' . $e->getMessage());
            }

            $this->success('上架成功', [
                'stock' => $stock->toArray(),
            ]);
        }

        $this->error('请求方式错误');
    }

    /**
     * 更新库存数量
     */
    public function update(): void
    {
        if ($this->request->isPost()) {
            $rider = RiderModel::where('user_id', $this->auth->id)->find();
            if (!$rider) {
                $this->error('骑手信息不存在');
            }

            $id       = $this->request->post('id/d', 0);
            $quantity = $this->request->post('quantity/d', 0);

            $stock = RiderStockModel::where('id', $id)
                ->where('rider_id', $rider->id)
                ->find();

            if (!$stock) {
                $this->error('库存记录不存在');
            }

            if ($quantity < 0) {
                $this->error('数量不能为负');
            }

            $stock->quantity = $quantity;
            $stock->save();

            $this->success('更新成功');
        }

        $this->error('请求方式错误');
    }

    /**
     * 下架/上架商品（切换状态）
     */
    public function remove(): void
    {
        if ($this->request->isPost()) {
            $rider = RiderModel::where('user_id', $this->auth->id)->find();
            if (!$rider) {
                $this->error('骑手信息不存在');
            }

            $id = $this->request->post('id/d', 0);

            $stock = RiderStockModel::where('id', $id)
                ->where('rider_id', $rider->id)
                ->find();

            if (!$stock) {
                $this->error('库存记录不存在');
            }

            $stock->status = $stock->status === 'on' ? 'off' : 'on';
            $stock->save();

            $action = $stock->status === 'on' ? '上架' : '下架';
            $this->success($action . '成功', [
                'status' => $stock->status,
            ]);
        }

        $this->error('请求方式错误');
    }

    /**
     * 退货（归还平台库存）
     */
    public function returnGoods(): void
    {
        if ($this->request->isPost()) {
            $rider = RiderModel::where('user_id', $this->auth->id)->find();
            if (!$rider) {
                $this->error('骑手信息不存在');
            }

            $id       = $this->request->post('id/d', 0);
            $quantity = $this->request->post('quantity/d', 0);

            if ($quantity <= 0) {
                $this->error('退货数量须大于0');
            }

            $stock = RiderStockModel::where('id', $id)
                ->where('rider_id', $rider->id)
                ->find();

            if (!$stock) {
                $this->error('库存记录不存在');
            }

            if ($quantity > $stock->quantity) {
                $this->error('退货数量超过库存');
            }

            Db::startTrans();
            try {
                $stock->quantity -= $quantity;
                if ($stock->quantity <= 0) {
                    $stock->status = 'off';
                }
                $stock->save();

                // 归还平台库存
                $product = ProductModel::find($stock->product_id);
                if ($product) {
                    $product->stock += $quantity;
                    $product->save();
                }

                Db::commit();
            } catch (Throwable $e) {
                Db::rollback();
                $this->error('退货失败: ' . $e->getMessage());
            }

            $this->success('退货成功');
        }

        $this->error('请求方式错误');
    }
}
