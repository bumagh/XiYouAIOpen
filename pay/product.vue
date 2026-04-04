<template>
    <div class="product-page">
        <nav>
            <div class="logo-group">
                <img class="logo-img" src="/@/assets/indieclub-icon.png" alt="Logo" />
                <div class="logo-text">创乐坊 <span>AI WORKS</span></div>
            </div>
            <div>
                <span class="user-class">CLASS: DEVELOPER | CREATOR</span>
            </div>
        </nav>

        <section class="hero">
            <h1>Prototypes <br /><span>Materialized</span></h1>
            <div class="hero-desc">
                [ 系统就绪 ]：面向开发与创作群体的灵感具象化服务。<br />
                您提供想法，我们利用次世代生成引擎 + 人工专业编排，为您交付可靠的策划蓝图、视听原型及可部署的定制程序。
            </div>

            <button class="insert-coin-btn" @click="scrollToProcess">
                INSERT COIN
                <span class="sub-text">¥1.00 排期费 | 立即启动</span>
            </button>

            <div style="margin-top: 18px">
                <button class="insert-coin-btn" style="font-size: 1.1rem; padding: 14px 40px" :disabled="paying" @click="openPayDialog">
                    支付 1 元
                    <span class="sub-text">微信/支付宝均可（默认微信）</span>
                </button>
            </div>
        </section>

        <section class="section-container">
            <div class="section-header">
                <h2>SKILL TREE <span style="font-size: 1rem; color: #666; font-weight: normal">// 核心能力</span></h2>
            </div>

            <div class="grid">
                <div class="card">
                    <div class="card-icon"><i class="fas fa-file-contract"></i></div>
                    <h3>01. 战略级文档蓝图</h3>
                    <ul>
                        <li><strong>人工需求分析：</strong> 拒绝AI幻觉，专人拆解核心逻辑。</li>
                        <li><strong>混合生成：</strong> 利用AI生成框架，人工精修填充。</li>
                        <li><strong>交付物：</strong> 游戏策划案(GDD)、市场调查报告、商业路演PPT。</li>
                    </ul>
                </div>

                <div class="card">
                    <div class="card-icon"><i class="fas fa-photo-video"></i></div>
                    <h3>02. 视听原型 & 宣发</h3>
                    <ul>
                        <li><strong>美术资产：</strong> 定制化模型生成概念原画与场景。</li>
                        <li><strong>视频制作：</strong> AI生成基础素材 + 专业剪辑/特效调整。</li>
                        <li><strong>用途：</strong> 适用于项目立项、众筹视频或初期宣发。</li>
                    </ul>
                </div>

                <div class="card">
                    <div class="card-icon"><i class="fas fa-gamepad"></i></div>
                    <h3>03. 可交互实机演示</h3>
                    <ul>
                        <li><strong>多端覆盖：</strong> Web端、PC端、安卓APK、H5小游戏。</li>
                        <li><strong>核心验证：</strong> 快速构建玩法原型，验证核心机制。</li>
                        <li><strong>投资利器：</strong> 让投资人不仅仅看PPT，更能上手玩。</li>
                    </ul>
                </div>

                <div class="card">
                    <div class="card-icon"><i class="fas fa-code-branch"></i></div>
                    <h3>04. 深度定制 & 交付</h3>
                    <ul>
                        <li><strong>全栈开发：</strong> 微信小程序、小游戏及独立APP定制。</li>
                        <li><strong>进度看板：</strong> 提供可视化开发进度，拒绝黑盒交付。</li>
                        <li><strong>完善售后：</strong> 包含3-5次细节微调修改，提供服务器部署服务。</li>
                    </ul>
                </div>
            </div>

            <div class="legendary-box">
                <div class="legendary-badge">LEGENDARY GUILD</div>
                <div class="legendary-img">
                    <img
                        src="https://images.unsplash.com/photo-1556761175-5973dc0f32e7?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80"
                        alt="Chuang Le Fang Team"
                    />
                </div>
                <div class="legendary-content">
                    <h3 style="font-family: 'Orbitron'; font-size: 2rem; color: var(--gold); margin-bottom: 15px">
                        CHUANG LE FANG <br /><span style="color: #fff; font-size: 1.5rem">联合出品认证</span>
                    </h3>
                    <p style="color: #ccc; line-height: 1.6; margin-bottom: 20px">
                        我是【创乐坊】联合创始人。选择我们，不仅是选择技术外包，更是接入一个庞大的独立游戏生态。
                    </p>
                    <div style="display: flex; flex-wrap: wrap; gap: 15px">
                        <span
                            style="background: rgba(255, 215, 0, 0.1); color: var(--gold); padding: 5px 15px; border: 1px solid var(--gold); font-size: 0.9rem"
                        >
                            <i class="fas fa-check"></i> 合法商标授权
                        </span>
                        <span
                            style="background: rgba(255, 215, 0, 0.1); color: var(--gold); padding: 5px 15px; border: 1px solid var(--gold); font-size: 0.9rem"
                        >
                            <i class="fas fa-users"></i> 社区流量扶持
                        </span>
                        <span
                            style="background: rgba(255, 215, 0, 0.1); color: var(--gold); padding: 5px 15px; border: 1px solid var(--gold); font-size: 0.9rem"
                        >
                            <i class="fas fa-image"></i> 商用素材库支持
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <section id="process" ref="processRef" class="process-section">
            <h2 style="font-family: 'Orbitron'; color: var(--neon-cyan); margin-bottom: 10px">QUEST LOG</h2>
            <p style="color: #888">简单四步，开启您的项目</p>

            <div class="steps-container">
                <div class="step-box">
                    <span class="step-num">01</span>
                    <h4 style="color: #fff; margin-bottom: 10px">提交需求</h4>
                    <p style="font-size: 0.85rem; color: #aaa">填写表单，描述您的创意或具体开发需求。</p>
                </div>

                <div class="step-box step-highlight">
                    <span class="step-num" style="color: var(--gold)">02</span>
                    <h4 style="color: var(--gold); margin-bottom: 10px">¥1.00 排期</h4>
                    <p style="font-size: 0.85rem; color: #aaa">支付1元定金，锁定团队档期与AI算力资源。</p>
                </div>

                <div class="step-box">
                    <span class="step-num">03</span>
                    <h4 style="color: #fff; margin-bottom: 10px">混合生产</h4>
                    <p style="font-size: 0.85rem; color: #aaa">AI 生成核心资产 + 人工逻辑编排与代码开发。</p>
                </div>

                <div class="step-box">
                    <span class="step-num">04</span>
                    <h4 style="color: #fff; margin-bottom: 10px">交付与部署</h4>
                    <p style="font-size: 0.85rem; color: #aaa">验收文档/演示/程序，部署上线，获得联合出品权益。</p>
                </div>
            </div>

            <div style="margin-top: 60px">
                <button class="insert-coin-btn" :disabled="paying" @click="openPayDialog">立即排期 (¥1.00)</button>
            </div>
        </section>

        <!-- 支付弹窗 -->
        <div v-if="payDialogVisible" class="dialog-mask" @click.self="closePayDialog">
            <div class="dialog">
                <div class="dialog-header">
                    <div class="dialog-title">提交需求并支付</div>
                    <button class="dialog-close" @click="closePayDialog">×</button>
                </div>

                <div class="dialog-body">
                    <div class="form-item">
                        <div class="label">联系方式</div>
                        <input v-model.trim="form.contact" class="input" placeholder="微信号 / 手机号 / 邮箱" />
                    </div>

                    <div class="form-item">
                        <div class="label">需求类型</div>
                        <select v-model="form.type" class="input">
                            <option value="">请选择（可不选）</option>
                            <option value="策划文档">策划文档</option>
                            <option value="美术/视频">美术/视频</option>
                            <option value="Demo原型">Demo原型</option>
                            <option value="定制开发">定制开发</option>
                            <option value="其他">其他</option>
                        </select>
                    </div>

                    <div class="form-item">
                        <div class="label">需求详细</div>
                        <textarea v-model.trim="form.detail" class="textarea" rows="5" placeholder="请描述你的项目目标、平台、预算、时间等"></textarea>
                    </div>
                </div>

                <div class="dialog-footer">
                    <button class="btn-secondary" :disabled="paying" @click="closePayDialog">取消</button>
                    <button class="btn-primary" :disabled="paying" @click="submitAndPay">确认提交并支付 1 元</button>
                </div>
            </div>
        </div>

        <footer>
            <p>Copyright © 2023 Chuang Le Fang Co-Lab. All Rights Reserved.</p>
            <p style="margin-top: 10px; opacity: 0.6">本服务由创乐坊联合创始人团队提供 | Powered by Human Intelligence & Generative Engines</p>
        </footer>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { payTest } from '/@/api/frontend/payworld'
import createAxios from '/@/utils/axios'

const paying = ref(false)
const processRef = ref(null)

const payDialogVisible = ref(false)
const form = ref({
    contact: '',
    type: '',
    detail: '',
})

function submitForm(url, params) {
    const formEl = document.createElement('form')
    formEl.method = 'POST'
    formEl.action = url
    formEl.style.display = 'none'

    Object.keys(params || {}).forEach((key) => {
        const input = document.createElement('input')
        input.type = 'hidden'
        input.name = key
        input.value = String((params || {})[key] ?? '')
        formEl.appendChild(input)
    })

    document.body.appendChild(formEl)
    formEl.submit()
    document.body.removeChild(formEl)
}

const scrollToProcess = () => {
    const el = processRef.value
    if (!el) return
    el.scrollIntoView({ behavior: 'smooth' })
}

const openPayDialog = () => {
    payDialogVisible.value = true
}

const closePayDialog = () => {
    payDialogVisible.value = false
}

const submitAndPay = async () => {
    if (!form.value.contact) {
        window.alert('请填写联系方式')
        return
    }
    if (!form.value.detail) {
        window.alert('请填写需求详细')
        return
    }

    paying.value = true
    try {
        // 1) 先落库需求信息，拿到 requirement_id
        const requirementRes = await createAxios({
            url: '/api/requirement/create',
            method: 'POST',
            data: {
                contact: form.value.contact,
                type: form.value.type,
                detail: form.value.detail,
                payment_method: 'wxpay',
                payment_amount: 1,
            },
        })

        const requirementId = requirementRes?.data?.id
        if (!requirementId) {
            throw new Error('需求提交失败：未返回 requirement_id')
        }

        // 2) 发起支付：param 只透传 requirement_id（纯值）
        const res = await payTest({
            money: 1,
            type: 'wxpay',
            name: '排期费(1元)',
            param: String(requirementId),
        })

        closePayDialog()
        submitForm(res.data.url, res.data.params)
    } catch (e) {
        window.alert(e?.message || '提交失败，请稍后再试')
    } finally {
        paying.value = false
    }
}
</script>

<style>
:root {
    --neon-cyan: #00f3ff;
    --neon-magenta: #ff0055;
    --neon-purple: #bc13fe;
    --gold: #ffd700;
    --bg-dark: #050505;
    --card-bg: rgba(20, 20, 30, 0.85);
    --border-color: rgba(0, 243, 255, 0.3);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Rajdhani', 'PingFang SC', sans-serif;
}

.product-page {
    background-color: var(--bg-dark);
    color: #fff;
    overflow-x: hidden;
    background-image: url('https://images.unsplash.com/photo-1511512578047-dfb367046420?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    min-height: 100vh;
}

.product-page::after {
    content: ' ';
    display: block;
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    right: 0;
    background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.1) 50%),
        linear-gradient(90deg, rgba(255, 0, 0, 0.06), rgba(0, 255, 0, 0.02), rgba(0, 0, 255, 0.06));
    z-index: 9999;
    background-size: 100% 2px, 3px 100%;
    pointer-events: none;
}

nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 5%;
    background: rgba(5, 5, 10, 0.9);
    border-bottom: 2px solid var(--neon-cyan);
    box-shadow: 0 0 15px rgba(0, 243, 255, 0.2);
    position: fixed;
    width: 100%;
    z-index: 1000;
    backdrop-filter: blur(10px);
}

.logo-group {
    display: flex;
    align-items: center;
    gap: 15px;
}

.logo-img {
    width: 28px;
    height: 28px;
    border-radius: 4px;
    object-fit: contain;
}

.logo-text {
    font-family: 'Orbitron', sans-serif;
    font-size: 1.5rem;
    font-weight: 800;
    letter-spacing: 2px;
    color: #fff;
    text-transform: uppercase;
}

.logo-text span {
    color: var(--neon-cyan);
}

.user-class {
    font-size: 0.8rem;
    color: var(--gold);
    border: 1px solid var(--gold);
    padding: 2px 8px;
    border-radius: 4px;
    background: rgba(255, 215, 0, 0.1);
}

.hero {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    padding: 100px 20px;
    position: relative;
}

.hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    z-index: -1;
}

.hero h1 {
    font-family: 'Orbitron', sans-serif;
    font-size: 3.5rem;
    line-height: 1.2;
    text-transform: uppercase;
    text-shadow: 0 0 20px var(--neon-cyan);
    margin-bottom: 20px;
}

.hero h1 span {
    color: var(--neon-magenta);
}

.hero-desc {
    font-size: 1.3rem;
    color: #d1d5db;
    max-width: 800px;
    margin-bottom: 40px;
    background: rgba(0, 0, 0, 0.6);
    padding: 20px;
    border-left: 4px solid var(--neon-cyan);
    border-right: 4px solid var(--neon-cyan);
}

.insert-coin-btn {
    background: linear-gradient(90deg, var(--neon-cyan), #00a8ff);
    color: #000;
    font-family: 'Orbitron', sans-serif;
    font-weight: 900;
    font-size: 1.5rem;
    padding: 20px 60px;
    border: none;
    cursor: pointer;
    clip-path: polygon(15% 0, 100% 0, 100% 70%, 85% 100%, 0 100%, 0 30%);
    transition: 0.3s;
    position: relative;
    box-shadow: 0 0 30px rgba(0, 243, 255, 0.4);
    text-transform: uppercase;
}

.insert-coin-btn:hover {
    transform: scale(1.05) translateY(-5px);
    box-shadow: 0 0 50px rgba(0, 243, 255, 0.8);
    color: #fff;
}

.insert-coin-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.insert-coin-btn .sub-text {
    display: block;
    font-size: 0.8rem;
    font-weight: normal;
    margin-top: 5px;
    opacity: 0.8;
}

.section-container {
    padding: 80px 10%;
    max-width: 1600px;
    margin: 0 auto;
}

.section-header {
    text-align: center;
    margin-bottom: 60px;
}

.section-header h2 {
    font-family: 'Orbitron', sans-serif;
    font-size: 2.5rem;
    color: #fff;
    display: inline-block;
    border-bottom: 3px solid var(--neon-magenta);
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
}

.card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    padding: 30px;
    position: relative;
    overflow: hidden;
    transition: 0.3s;
}

.card:hover {
    transform: translateY(-10px);
    border-color: var(--neon-cyan);
    box-shadow: 0 0 25px rgba(0, 243, 255, 0.15);
}

.card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, var(--neon-cyan), var(--neon-purple));
}

.card-icon {
    font-size: 2.5rem;
    color: var(--neon-cyan);
    margin-bottom: 20px;
    text-shadow: 0 0 10px var(--neon-cyan);
}

.card h3 {
    font-family: 'Orbitron', sans-serif;
    font-size: 1.4rem;
    margin-bottom: 15px;
    color: #fff;
}

.card ul {
    list-style: none;
    color: #b0b0b0;
}

.card ul li {
    margin-bottom: 10px;
    display: flex;
    align-items: start;
}

.card ul li::before {
    content: '>';
    color: var(--neon-magenta);
    margin-right: 10px;
    font-weight: bold;
}

.legendary-box {
    margin-top: 80px;
    background: linear-gradient(135deg, rgba(20, 20, 20, 0.9), rgba(10, 10, 10, 0.95));
    border: 2px solid var(--gold);
    padding: 40px;
    display: flex;
    align-items: center;
    gap: 40px;
    position: relative;
    box-shadow: 0 0 40px rgba(255, 215, 0, 0.1);
}

.legendary-badge {
    position: absolute;
    top: -15px;
    left: 30px;
    background: var(--gold);
    color: #000;
    font-weight: 900;
    padding: 5px 20px;
    font-family: 'Orbitron';
    text-transform: uppercase;
}

.legendary-img {
    flex: 1;
    min-width: 300px;
}

.legendary-img img {
    width: 100%;
    border-radius: 8px;
    border: 1px solid #333;
    filter: contrast(1.1);
}

.legendary-content {
    flex: 1.5;
}

.process-section {
    background: rgba(255, 255, 255, 0.02);
    padding: 80px 0;
    text-align: center;
}

.steps-container {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 40px;
}

.step-box {
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid #333;
    padding: 25px;
    width: 220px;
    position: relative;
}

.step-num {
    font-family: 'Orbitron';
    font-size: 2rem;
    color: rgba(255, 255, 255, 0.1);
    position: absolute;
    top: 5px;
    right: 10px;
    font-weight: bold;
}

.step-highlight {
    border-color: var(--gold);
    box-shadow: 0 0 15px rgba(255, 215, 0, 0.1);
}

footer {
    background: #020202;
    padding: 40px;
    text-align: center;
    color: #555;
    font-size: 0.8rem;
    border-top: 1px solid #111;
}

.dialog-mask {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.65);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.dialog {
    width: min(720px, 100%);
    background: rgba(10, 10, 15, 0.95);
    border: 1px solid rgba(0, 243, 255, 0.3);
    box-shadow: 0 0 30px rgba(0, 243, 255, 0.15);
    border-radius: 12px;
    overflow: hidden;
}

.dialog-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    border-bottom: 1px solid rgba(0, 243, 255, 0.2);
}

.dialog-title {
    font-family: 'Orbitron', sans-serif;
    font-size: 1.1rem;
    letter-spacing: 1px;
}

.dialog-close {
    width: 34px;
    height: 34px;
    border: none;
    background: transparent;
    color: #fff;
    font-size: 22px;
    cursor: pointer;
    opacity: 0.85;
}

.dialog-close:hover {
    opacity: 1;
}

.dialog-body {
    padding: 16px;
}

.form-item {
    margin-bottom: 14px;
}

.label {
    margin-bottom: 8px;
    color: rgba(255, 255, 255, 0.85);
    font-size: 0.95rem;
}

.input,
.textarea {
    width: 100%;
    box-sizing: border-box;
    border-radius: 10px;
    border: 1px solid rgba(0, 243, 255, 0.25);
    background: rgba(0, 0, 0, 0.35);
    color: #fff;
    padding: 10px 12px;
    outline: none;
}

.input:focus,
.textarea:focus {
    border-color: rgba(0, 243, 255, 0.6);
    box-shadow: 0 0 0 3px rgba(0, 243, 255, 0.12);
}

.textarea {
    resize: vertical;
}

.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 14px 16px;
    border-top: 1px solid rgba(0, 243, 255, 0.2);
}

.btn-primary,
.btn-secondary {
    border: none;
    border-radius: 10px;
    padding: 10px 14px;
    cursor: pointer;
    font-weight: 700;
}

.btn-primary {
    background: linear-gradient(90deg, var(--neon-cyan), #00a8ff);
    color: #000;
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.btn-primary:disabled,
.btn-secondary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .legendary-box {
        flex-direction: column-reverse;
    }
    .hero h1 {
        font-size: 2.2rem;
    }
    .hero-desc {
        font-size: 1rem;
    }
}
</style>
