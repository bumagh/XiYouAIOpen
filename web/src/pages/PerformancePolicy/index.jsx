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

const performancePolicyContent = `# 履约政策 / Performance Policy

**西游AI / Journey to the West AI**

最后更新 / Last Updated：2026年4月20日 / April 20, 2026

---

## 简体中文版

### 1. 服务可用性

本团队承诺订阅服务月度可用率不低于99%（计划维护时间除外）。

### 2. 数据安全

用户业务数据加密存储，不用于训练或对外共享。

### 3. 技术支持

- 订阅用户：工作日9:00-18:00在线支持，响应时间不超过4小时。
- 定制用户：项目期间提供专属对接，交付后30天内免费维护。

### 4. 违约处理

若因本团队原因导致服务中断超过24小时，将按比例延长订阅周期作为补偿。

### 5. 争议解决

双方协商解决为优先，协商不成可提交本团队所在地有管辖权的法院处理。

---

## English Version

### 1. Service Availability

Our team commits to a monthly uptime rate of no less than 99% for subscription services (excluding scheduled maintenance periods).

### 2. Data Security

User business data is stored with encryption and will not be used for model training or shared externally with third parties.

### 3. Technical Support

- Subscription Users: Online support is available on business days from 9:00 AM to 6:00 PM, with a response time not exceeding 4 hours.
- Customization Users: Dedicated liaison support is provided during the project period, along with free maintenance for 30 days following delivery.

### 4. Breach of Contract

Should service interruption caused by our team exceed 24 consecutive hours, the subscription period will be extended proportionately as compensation.

### 5. Dispute Resolution

Disputes shall be resolved through mutual negotiation as the priority. If negotiation fails, either party may submit the dispute to the competent court in the jurisdiction where our team is located.
`;

const PerformancePolicy = () => {
  const { t } = useTranslation();

  return (
    <div className='min-h-screen bg-gray-50'>
      <div className='max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8'>
        <div className='bg-white rounded-lg shadow-sm p-8'>
          <Title heading={2} className='text-center mb-8'>
            {t('履约政策')} / {t('Performance Policy')}
          </Title>
          <div className='prose prose-lg max-w-none'>
            <MarkdownRenderer content={performancePolicyContent} />
          </div>
        </div>
      </div>
    </div>
  );
};

export default PerformancePolicy;
