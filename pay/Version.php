<?php

namespace app\api\controller;

use app\common\controller\Frontend;
use app\admin\model\petto\AppVersion as AppVersionModel;

class Version extends Frontend
{
    protected array $noNeedLogin = ['check'];

    public function initialize(): void
    {
        parent::initialize();
    }

    /**
     * 检查版本更新
     * GET /api/version/check?app_type=owner&version_code=100
     */
    public function check(): void
    {
        $appType     = $this->request->get('app_type', '');
        $versionCode = $this->request->get('version_code/d', 0);

        if (!in_array($appType, ['provider', 'owner']) || $versionCode <= 0) {
            $this->error('参数错误');
        }

        $latest = AppVersionModel::where('app_type', $appType)
            ->where('status', 1)
            ->order('version_code desc')
            ->find();

        if (!$latest || $latest->version_code <= $versionCode) {
            $this->success('已是最新版本', [
                'has_update' => false,
            ]);
        }

        $this->success('发现新版本', [
            'has_update'     => true,
            'version_code'   => $latest->version_code,
            'version_name'   => $latest->version_name,
            'force_update'   => (int)$latest->force_update === 1,
            'update_title'   => $latest->update_title,
            'update_content' => $latest->update_content,
            'download_url'   => $latest->download_url,
        ]);
    }
}
