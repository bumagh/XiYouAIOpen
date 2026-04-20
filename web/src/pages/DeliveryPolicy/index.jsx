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

const deliveryPolicyContent = `# 交付政策 / Delivery Policy

**西游AI / Journey to the West AI**

最后更新 / Last Updated：2026年4月20日 / April 20, 2026

---

## 简体中文版

### 1. 订阅服务交付

- 付款成功后即时开通，账户内可立即使用。
- 服务以线上SaaS形式交付，无需物流。

### 2. 定制服务交付

- 签约后3个工作日内启动项目，双方确认需求文档。
- 标准定制项目交付周期：7-15个工作日（视复杂度而定）。
- 交付物通过平台或指定渠道在线移交。

### 3. 交付验收

- 用户收到交付物后5个工作日内完成验收。
- 验收期内可提出合理修改意见，超出原需求范围的修改另行报价。

---

## English Version

### 1. Subscription Service Delivery

- Services are activated immediately upon successful payment and are accessible within the account right away.
- Services are delivered online as SaaS; no physical logistics are required.

### 2. Customization Service Delivery

- Projects commence within 3 business days after contract signing, following mutual confirmation of the requirements document.
- Standard customization project delivery timeline: 7-15 business days (depending on complexity).
- Deliverables will be transferred online via the platform or a designated channel.

### 3. Delivery Acceptance

- Users must complete the acceptance inspection within 5 business days of receiving the deliverables.
- Reasonable modification requests may be submitted during the acceptance period. Modifications exceeding the original scope of requirements will be quoted separately.
`;

const DeliveryPolicy = () => {
  const { t } = useTranslation();

  return (
    <div className='min-h-screen bg-gray-50'>
      <div className='max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8'>
        <div className='bg-white rounded-lg shadow-sm p-8'>
          <Title heading={2} className='text-center mb-8'>
            {t('交付政策')} / {t('Delivery Policy')}
          </Title>
          <div className='prose prose-lg max-w-none'>
            <MarkdownRenderer content={deliveryPolicyContent} />
          </div>
        </div>
      </div>
    </div>
  );
};

export default DeliveryPolicy;
