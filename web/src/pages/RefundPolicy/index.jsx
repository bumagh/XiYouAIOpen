/*
Copyright (C) 2025 QuantumNous

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program. If not, see <https://www.gnu.org/licenses/>.

For commercial licensing, please contact support@quantumnous.com
*/

import React from 'react';
import { useTranslation } from 'react-i18next';
import { Typography } from '@douyinfe/semi-ui';
import MarkdownRenderer from '../../components/common/markdown/MarkdownRenderer';

const { Title } = Typography;

const refundPolicyContent = `# 退款政策 / Refund Policy

**西游AI / Journey to the West AI**

最后更新 / Last Updated：2026年4月20日 / April 20, 2026

---

## 简体中文版

### 1. 订阅服务

- 订阅后7日内，如服务存在重大功能缺陷且无法修复，可申请全额退款。
- 超过7日或已正常使用的订阅周期，不支持退款。
- 按月订阅不支持部分退款。

### 2. 定制服务

- 项目启动前取消：退还已付款项的80%。
- 项目进行中取消：按已完成工作量比例扣除费用后退款。
- 项目交付后：不支持退款，但提供30天内的问题修复服务。

### 3. 申请方式

发送退款申请至客服渠道，注明订单号及退款原因，7个工作日内处理。

---

## English Version

### 1. Subscription Services

- A full refund may be requested within 7 days of subscription if the Service exhibits a critical functional defect that cannot be rectified.
- No refunds will be issued for subscription periods exceeding 7 days or for periods during which the Service has been used normally.
- Monthly subscriptions are not eligible for partial refunds.

### 2. Customization Services

- Cancellation Prior to Project Commencement: 80% of the paid amount will be refunded.
- Cancellation During Project Execution: A refund will be issued after deducting fees proportionate to the completed workload.
- After Project Delivery: No refunds will be provided; however, issue remediation services are available for 30 days following delivery.

### 3. Application Procedure

Refund requests must be submitted to the customer service channel, specifying the order number and the reason for the refund. Requests will be processed within 7 business days.
`;

const RefundPolicy = () => {
  const { t } = useTranslation();

  return (
    <div className='min-h-screen bg-gray-50'>
      <div className='max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8'>
        <div className='bg-white rounded-lg shadow-sm p-8'>
          <Title heading={2} className='text-center mb-8'>
            {t('退款政策')} / {t('Refund Policy')}
          </Title>
          <div className='prose prose-lg max-w-none'>
            <MarkdownRenderer content={refundPolicyContent} />
          </div>
        </div>
      </div>
    </div>
  );
};

export default RefundPolicy;
