import React, { useState } from 'react';

/**
 * Decision Assistant - Layer 4
 * دستیار تصمیم‌سازی: شبیه‌سازی تغییرات قبل از اعمال
 */
const DecisionAssistant = () => {
    const [simulationData, setSimulationData] = useState({
        decision_type: 'price_change',
        current_value: 0,
        risk_level: 0.5,
    });
    const [simulationResult, setSimulationResult] = useState(null);
    const [isSimulating, setIsSimulating] = useState(false);

    const handleInputChange = (field, value) => {
        setSimulationData(prev => ({
            ...prev,
            [field]: value,
        }));
    };

    const runSimulation = async () => {
        setIsSimulating(true);
        try {
            const response = await fetch(
                `${window.atlasConfig.apiUrl}/simulate`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.atlasConfig.nonce,
                    },
                    body: JSON.stringify(simulationData),
                }
            );
            const result = await response.json();
            if (result.success) {
                setSimulationResult(result.data);
            }
        } catch (err) {
            console.error('Atlas Simulation Error:', err);
        } finally {
            setIsSimulating(false);
        }
    };

    return (
        <div className="decision-assistant">
            <h2>🎯 دستیار تصمیم‌سازی (Decision Assistant)</h2>
            <p className="description">
                شبیه‌سازی تأثیر تصمیمات قبل از اعمال - A/B Testing پیشگویانه
            </p>

            {/* Simulation Input Form */}
            <div className="simulation-form">
                <h3>⚙️ پارامترهای شبیه‌سازی</h3>

                <div className="form-group">
                    <label>نوع تصمیم:</label>
                    <select
                        value={simulationData.decision_type}
                        onChange={(e) => handleInputChange('decision_type', e.target.value)}
                    >
                        <option value="price_change">تغییر قیمت</option>
                        <option value="form_simplification">ساده‌سازی فرم</option>
                        <option value="cta_modification">تغییر دکمه CTA</option>
                        <option value="layout_change">تغییر طراحی</option>
                    </select>
                </div>

                <div className="form-group">
                    <label>مقدار فعلی:</label>
                    <input
                        type="number"
                        value={simulationData.current_value}
                        onChange={(e) => handleInputChange('current_value', parseFloat(e.target.value))}
                        placeholder="مثلاً نرخ تبدیل فعلی (2.5)"
                    />
                    <span className="input-hint">
                        {getValueHint(simulationData.decision_type)}
                    </span>
                </div>

                <div className="form-group">
                    <label>سطح ریسک: {(simulationData.risk_level * 100).toFixed(0)}%</label>
                    <input
                        type="range"
                        min="0"
                        max="1"
                        step="0.1"
                        value={simulationData.risk_level}
                        onChange={(e) => handleInputChange('risk_level', parseFloat(e.target.value))}
                        className="risk-slider"
                    />
                    <div className="risk-labels">
                        <span>کم</span>
                        <span>متوسط</span>
                        <span>بالا</span>
                    </div>
                </div>

                <button
                    className="simulate-button"
                    onClick={runSimulation}
                    disabled={isSimulating}
                >
                    {isSimulating ? 'در حال شبیه‌سازی...' : '🚀 اجرای شبیه‌سازی'}
                </button>
            </div>

            {/* Simulation Result */}
            {simulationResult && (
                <div className="simulation-result">
                    <h3>📊 نتایج شبیه‌سازی</h3>

                    <div className="result-grid">
                        <ResultCard
                            title="مقدار فعلی"
                            value={simulationResult.current_value}
                            icon="📍"
                        />
                        <ResultCard
                            title="مقدار پیش‌بینی شده"
                            value={simulationResult.predicted_value}
                            icon="🎯"
                            highlight
                        />
                        <ResultCard
                            title="تغییر مورد انتظار"
                            value={`${simulationResult.expected_change > 0 ? '+' : ''}${simulationResult.expected_change}%`}
                            icon={simulationResult.expected_change > 0 ? '📈' : '📉'}
                            positive={simulationResult.expected_change > 0}
                        />
                        <ResultCard
                            title="سطح اطمینان"
                            value={`${simulationResult.confidence_level}%`}
                            icon="🎲"
                        />
                    </div>

                    {/* Risk Assessment */}
                    <div className={`risk-assessment risk-${simulationResult.risk_assessment}`}>
                        <h4>ارزیابی ریسک: {getRiskText(simulationResult.risk_assessment)}</h4>
                    </div>

                    {/* Recommendation */}
                    <div className="simulation-recommendation">
                        <h4>💡 توصیه اطلس:</h4>
                        <p>{simulationResult.recommendation}</p>
                    </div>

                    {/* Decision Buttons */}
                    <div className="decision-buttons">
                        <button className="proceed-button" title="در نسخه‌های بعدی">
                            ✅ اعمال تصمیم
                        </button>
                        <button className="test-button" title="در نسخه‌های بعدی">
                            🧪 تست A/B
                        </button>
                        <button className="cancel-button" onClick={() => setSimulationResult(null)}>
                            ❌ لغو
                        </button>
                    </div>
                </div>
            )}

            {/* Simulation Algorithm Info */}
            <div className="algorithm-section">
                <h3>⚙️ منطق شبیه‌ساز</h3>
                <pre className="algorithm-code">
{`/**
 * شبیه‌سازی تأثیر تغییر قیمت بر نرخ تبدیل
 */
public function simulate_decision($current_rate, $risk_level) {
    // منطق پیش‌بینی بر اساس داده‌های تاریخی مخزن اطلس
    $prediction = ($current_rate * 1.1) - ($risk_level * 0.05);
    
    return [
        'expected_conversion' => $prediction,
        'recommendation' => 'با توجه به ریسک فعلی، 
                             این تغییر مثبت ارزیابی می‌شود.'
    ];
}`}
                </pre>
            </div>

            {/* Historical Decisions */}
            <div className="history-section">
                <h3>📜 تاریخچه تصمیمات</h3>
                <p className="section-note">
                    تست ثبات: آیا سیستم تاریخچه تصمیمات را برای مقایسه "قبل و بعد" ذخیره کرده است؟
                </p>
                <div className="history-placeholder">
                    <p>📋 در نسخه‌های بعدی: نمایش تاریخچه تصمیمات گذشته و نتایج واقعی آن‌ها</p>
                </div>
            </div>
        </div>
    );
};

/**
 * Result Card Component
 */
const ResultCard = ({ title, value, icon, highlight, positive }) => {
    return (
        <div className={`result-card ${highlight ? 'highlight' : ''} ${positive ? 'positive' : ''}`}>
            <div className="result-icon">{icon}</div>
            <div className="result-content">
                <div className="result-title">{title}</div>
                <div className="result-value">{value}</div>
            </div>
        </div>
    );
};

/**
 * Get value hint based on decision type
 */
const getValueHint = (decisionType) => {
    const hints = {
        price_change: 'نرخ تبدیل فعلی (مثلاً 2.5 یعنی 2.5%)',
        form_simplification: 'نرخ تکمیل فرم فعلی (0-100)',
        cta_modification: 'نرخ کلیک فعلی (مثلاً 5.2%)',
        layout_change: 'نرخ تعامل فعلی (0-100)',
    };
    return hints[decisionType] || 'مقدار فعلی را وارد کنید';
};

/**
 * Get risk text in Persian
 */
const getRiskText = (riskLevel) => {
    const riskMap = {
        high: '🔴 ریسک بالا',
        medium: '🟡 ریسک متوسط',
        low: '🟢 ریسک پایین',
    };
    return riskMap[riskLevel] || 'نامشخص';
};

export default DecisionAssistant;
