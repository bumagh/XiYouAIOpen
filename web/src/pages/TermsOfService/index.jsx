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

const termsOfServiceContent = `# 服务条款 / Terms of Service

**西游AI / Journey to the West AI**

最后更新 / Last Updated：2026年4月20日 / April 20, 2026

---

## 简体中文版

### 1. 服务说明

西游AI数字员工（以下简称"本服务"）由本团队提供，包括AI数字员工订阅服务及定制化数字员工开发服务。

### 2. 账户与订阅

用户需提供真实信息注册账户。订阅服务按周期计费，到期未续费则服务自动暂停。

### 3. 使用规范

禁止将本服务用于违法、欺诈或侵权用途。禁止对系统进行逆向工程或恶意攻击。

### 4. 知识产权

平台及数字员工底层技术归本团队所有。用户上传的业务数据归用户所有。

### 5. 免责声明

AI输出内容仅供参考，不构成法律、财务或医疗建议。因用户误用导致的损失本团队不承担责任。

### 6. 条款变更

本团队保留修改条款的权利，变更后将通过平台公告通知用户。

---

## English Version

### 1. Service Description

Journey to the West AI Digital Employees (hereinafter referred to as "the Service") is provided by our team, including AI digital employee subscription services and customized digital employee development services.

### 2. Account and Subscription

Users must provide accurate information to register an account. Subscription services are billed on a recurring cycle basis. The Service will be automatically suspended if the subscription is not renewed upon expiration.

### 3. Usage Guidelines

The Service shall not be used for illegal, fraudulent, or infringing purposes. Reverse engineering of the system or malicious attacks are strictly prohibited.

### 4. Intellectual Property Rights

The underlying technology of the platform and digital employees is owned by our team. Business data uploaded by users remains the property of the users.

### 5. Disclaimer

AI-generated output is for reference only and does not constitute legal, financial, or medical advice. Our team assumes no liability for losses resulting from user misuse.

### 6. Amendment of Terms

Our team reserves the right to modify these terms. Any changes will be announced via platform notification.
`;

const TermsOfService = () => {
  const { t } = useTranslation();

  return (
    <div className='min-h-screen bg-gray-50'>
      <div className='max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8'>
        <div className='bg-white rounded-lg shadow-sm p-8'>
          <Title heading={2} className='text-center mb-8'>
            {t('服务条款')} / {t('Terms of Service')}
          </Title>
          <div className='prose prose-lg max-w-none'>
            <MarkdownRenderer content={termsOfServiceContent} />
          </div>
        </div>
      </div>
    </div>
  );
};

export default TermsOfService;
