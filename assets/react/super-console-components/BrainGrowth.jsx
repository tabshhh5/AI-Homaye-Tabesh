import React, { useState, useEffect } from 'react';

/**
 * Brain Growth & Knowledge Fine-Tuner Tab - Tab 4
 * تب ۴: توسعه مغز و ایندکس + اصلاح میکروسکوپی دانش
 * 
 * Knowledge growth visualization and fact management with fine-tuning capabilities
 */
const BrainGrowth = () => {
    const [knowledge, setKnowledge] = useState(null);
    const [facts, setFacts] = useState([]);
    const [selectedFact, setSelectedFact] = useState(null);
    const [editMode, setEditMode] = useState(false);
    const [filter, setFilter] = useState('all');
    const [searchTerm, setSearchTerm] = useState('');
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        loadKnowledgeStats();
        loadFacts();
    }, [filter]);

    const loadKnowledgeStats = async () => {
        try {
            const response = await fetch(
                `${window.homaConsoleConfig.apiUrl}/knowledge/stats`,
                {
                    headers: {
                        'X-WP-Nonce': window.homaConsoleConfig.nonce
                    }
                }
            );
            const data = await response.json();
            if (data.success) {
                setKnowledge(data.data);
            }
        } catch (error) {
            console.error('Failed to load knowledge stats:', error);
        }
    };

    const loadFacts = async () => {
        setLoading(true);
        try {
            const response = await fetch(
                `${window.homaConsoleConfig.apiUrl}/knowledge/facts?filter=${filter}&search=${searchTerm}`,
                {
                    headers: {
                        'X-WP-Nonce': window.homaConsoleConfig.nonce
                    }
                }
            );
            const data = await response.json();
            if (data.success) {
                setFacts(data.data);
            }
        } catch (error) {
            console.error('Failed to load facts:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleEditFact = (fact) => {
        setSelectedFact({ ...fact });
        setEditMode(true);
    };

    const handleSaveFact = async () => {
        try {
            const response = await fetch(
                `${window.homaConsoleConfig.apiUrl}/knowledge/facts/${selectedFact.id}`,
                {
                    method: 'PUT',
                    headers: {
                        'X-WP-Nonce': window.homaConsoleConfig.nonce,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(selectedFact)
                }
            );
            const data = await response.json();
            if (data.success) {
                alert('✅ فکت با موفقیت ویرایش شد');
                setEditMode(false);
                loadFacts();
                loadKnowledgeStats();
            }
        } catch (error) {
            console.error('Failed to save fact:', error);
            alert('❌ خطا در ذخیره فکت');
        }
    };

    const handleDeleteFact = async (factId) => {
        if (!confirm('آیا از حذف این فکت مطمئن هستید؟')) return;

        try {
            const response = await fetch(
                `${window.homaConsoleConfig.apiUrl}/knowledge/facts/${factId}`,
                {
                    method: 'DELETE',
                    headers: {
                        'X-WP-Nonce': window.homaConsoleConfig.nonce
                    }
                }
            );
            const data = await response.json();
            if (data.success) {
                alert('✅ فکت با موفقیت حذف شد');
                loadFacts();
                loadKnowledgeStats();
            }
        } catch (error) {
            console.error('Failed to delete fact:', error);
            alert('❌ خطا در حذف فکت');
        }
    };

    const handleVerifyFact = async (factId, verified) => {
        try {
            const response = await fetch(
                `${window.homaConsoleConfig.apiUrl}/knowledge/facts/${factId}/verify`,
                {
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': window.homaConsoleConfig.nonce,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ verified })
                }
            );
            const data = await response.json();
            if (data.success) {
                loadFacts();
            }
        } catch (error) {
            console.error('Failed to verify fact:', error);
        }
    };

    const stats = knowledge || {
        total_facts: 0,
        by_category: {},
        growth_trend: [],
        pages_indexed: 0,
        plugins_monitored: 0
    };

    return (
        <div className="brain-growth" dir="rtl">
            {/* Knowledge Stats Overview */}
            <div className="stats-overview">
                <div className="stat-card">
                    <div className="stat-icon">📚</div>
                    <div className="stat-content">
                        <div className="stat-value">{stats.total_facts}</div>
                        <div className="stat-label">کل فکت‌ها</div>
                    </div>
                </div>
                <div className="stat-card">
                    <div className="stat-icon">📄</div>
                    <div className="stat-content">
                        <div className="stat-value">{stats.pages_indexed}</div>
                        <div className="stat-label">صفحات ایندکس شده</div>
                    </div>
                </div>
                <div className="stat-card">
                    <div className="stat-icon">🔌</div>
                    <div className="stat-content">
                        <div className="stat-value">{stats.plugins_monitored}</div>
                        <div className="stat-label">افزونه‌های رصد شده</div>
                    </div>
                </div>
                <div className="stat-card">
                    <div className="stat-icon">📈</div>
                    <div className="stat-content">
                        <div className="stat-value">{stats.pending_verification || 0}</div>
                        <div className="stat-label">در انتظار تایید</div>
                    </div>
                </div>
            </div>

            {/* Category Breakdown */}
            {Object.keys(stats.by_category || {}).length > 0 && (
                <div className="category-breakdown">
                    <h3>📊 توزیع دانش بر اساس دسته‌بندی</h3>
                    <div className="category-grid">
                        {Object.entries(stats.by_category).map(([category, count]) => (
                            <div key={category} className="category-item">
                                <div className="category-name">{category}</div>
                                <div className="category-bar">
                                    <div 
                                        className="category-fill"
                                        style={{ 
                                            width: `${(count / stats.total_facts) * 100}%` 
                                        }}
                                    ></div>
                                </div>
                                <div className="category-count">{count}</div>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Knowledge Fine-Tuner Section */}
            <div className="fine-tuner-section">
                <h2>🎯 ویرایشگر دانش (Knowledge Fine-Tuner)</h2>
                
                {/* Controls */}
                <div className="controls-bar">
                    <div className="filter-buttons">
                        <button 
                            className={filter === 'all' ? 'active' : ''} 
                            onClick={() => setFilter('all')}
                        >
                            همه
                        </button>
                        <button 
                            className={filter === 'pending' ? 'active' : ''} 
                            onClick={() => setFilter('pending')}
                        >
                            در انتظار تایید
                        </button>
                        <button 
                            className={filter === 'verified' ? 'active' : ''} 
                            onClick={() => setFilter('verified')}
                        >
                            تایید شده
                        </button>
                    </div>
                    <input 
                        type="text"
                        className="search-input"
                        placeholder="🔍 جستجو در فکت‌ها..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        onKeyPress={(e) => e.key === 'Enter' && loadFacts()}
                    />
                    <button className="search-btn" onClick={loadFacts}>
                        جستجو
                    </button>
                </div>

                {/* Facts List */}
                <div className="facts-list">
                    {loading ? (
                        <div className="loading">در حال بارگذاری...</div>
                    ) : facts.length === 0 ? (
                        <div className="no-data">هیچ فکتی یافت نشد</div>
                    ) : (
                        facts.map(fact => (
                            <div key={fact.id} className="fact-card">
                                <div className="fact-header">
                                    <div className="fact-category">{fact.category || 'عمومی'}</div>
                                    <div className="fact-status">
                                        {fact.verified ? (
                                            <span className="verified">✓ تایید شده</span>
                                        ) : (
                                            <span className="pending">⏳ در انتظار تایید</span>
                                        )}
                                    </div>
                                </div>
                                
                                <div className="fact-content">{fact.fact}</div>
                                
                                {fact.source && (
                                    <div className="fact-source">
                                        <strong>منبع:</strong> {fact.source}
                                    </div>
                                )}
                                
                                {fact.tags && fact.tags.length > 0 && (
                                    <div className="fact-tags">
                                        {fact.tags.map((tag, idx) => (
                                            <span key={idx} className="tag">{tag}</span>
                                        ))}
                                    </div>
                                )}

                                <div className="fact-actions">
                                    <button 
                                        className="btn-edit"
                                        onClick={() => handleEditFact(fact)}
                                    >
                                        ✏️ ویرایش
                                    </button>
                                    {!fact.verified && (
                                        <button 
                                            className="btn-verify"
                                            onClick={() => handleVerifyFact(fact.id, true)}
                                        >
                                            ✓ تایید
                                        </button>
                                    )}
                                    <button 
                                        className="btn-delete"
                                        onClick={() => handleDeleteFact(fact.id)}
                                    >
                                        🗑️ حذف
                                    </button>
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </div>

            {/* Edit Modal */}
            {editMode && selectedFact && (
                <div className="modal-overlay" onClick={() => setEditMode(false)}>
                    <div className="modal-content" onClick={(e) => e.stopPropagation()}>
                        <h3>✏️ ویرایش فکت</h3>
                        
                        <div className="form-group">
                            <label>متن فکت:</label>
                            <textarea
                                value={selectedFact.fact}
                                onChange={(e) => setSelectedFact({...selectedFact, fact: e.target.value})}
                                rows={4}
                            />
                        </div>

                        <div className="form-group">
                            <label>دسته‌بندی:</label>
                            <select
                                value={selectedFact.category}
                                onChange={(e) => setSelectedFact({...selectedFact, category: e.target.value})}
                            >
                                <option value="عمومی">عمومی</option>
                                <option value="محصولات">محصولات</option>
                                <option value="خدمات">خدمات</option>
                                <option value="امنیت">امنیت</option>
                                <option value="زمان تحویل">زمان تحویل</option>
                                <option value="پشتیبانی">پشتیبانی</option>
                            </select>
                        </div>

                        <div className="form-group">
                            <label>منبع:</label>
                            <input
                                type="text"
                                value={selectedFact.source || ''}
                                onChange={(e) => setSelectedFact({...selectedFact, source: e.target.value})}
                            />
                        </div>

                        <div className="form-group">
                            <label>برچسب‌ها (با کاما جدا کنید):</label>
                            <input
                                type="text"
                                value={selectedFact.tags?.join(', ') || ''}
                                onChange={(e) => setSelectedFact({
                                    ...selectedFact, 
                                    tags: e.target.value.split(',').map(t => t.trim())
                                })}
                            />
                        </div>

                        <div className="modal-actions">
                            <button className="btn-save" onClick={handleSaveFact}>
                                💾 ذخیره
                            </button>
                            <button className="btn-cancel" onClick={() => setEditMode(false)}>
                                ✖️ انصراف
                            </button>
                        </div>
                    </div>
                </div>
            )}

            <style jsx>{`
                .brain-growth {
                    padding: 20px;
                }

                .stats-overview {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 15px;
                    margin-bottom: 30px;
                }

                .stat-card {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    background: white;
                    border: 1px solid #e0e0e0;
                    border-radius: 12px;
                    padding: 20px;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                }

                .stat-icon {
                    font-size: 32px;
                }

                .stat-value {
                    font-size: 28px;
                    font-weight: bold;
                    color: #667eea;
                }

                .stat-label {
                    font-size: 13px;
                    color: #666;
                }

                .category-breakdown {
                    background: white;
                    border: 1px solid #e0e0e0;
                    border-radius: 12px;
                    padding: 20px;
                    margin-bottom: 30px;
                }

                .category-breakdown h3 {
                    margin: 0 0 20px 0;
                    color: #333;
                }

                .category-grid {
                    display: flex;
                    flex-direction: column;
                    gap: 12px;
                }

                .category-item {
                    display: grid;
                    grid-template-columns: 150px 1fr 60px;
                    align-items: center;
                    gap: 15px;
                }

                .category-name {
                    font-weight: 600;
                    color: #333;
                }

                .category-bar {
                    height: 20px;
                    background: #f0f0f0;
                    border-radius: 10px;
                    overflow: hidden;
                }

                .category-fill {
                    height: 100%;
                    background: linear-gradient(90deg, #667eea, #764ba2);
                    transition: width 0.5s;
                }

                .category-count {
                    text-align: center;
                    font-weight: bold;
                    color: #667eea;
                }

                .fine-tuner-section {
                    background: white;
                    border: 1px solid #e0e0e0;
                    border-radius: 12px;
                    padding: 20px;
                }

                .fine-tuner-section h2 {
                    margin: 0 0 20px 0;
                    color: #333;
                }

                .controls-bar {
                    display: flex;
                    gap: 15px;
                    margin-bottom: 20px;
                    flex-wrap: wrap;
                }

                .filter-buttons {
                    display: flex;
                    gap: 10px;
                }

                .filter-buttons button {
                    padding: 8px 16px;
                    border: 1px solid #ddd;
                    background: white;
                    border-radius: 6px;
                    cursor: pointer;
                    transition: all 0.3s;
                }

                .filter-buttons button.active {
                    background: #667eea;
                    color: white;
                    border-color: #667eea;
                }

                .search-input {
                    flex: 1;
                    padding: 8px 16px;
                    border: 1px solid #ddd;
                    border-radius: 6px;
                    font-size: 14px;
                }

                .search-btn {
                    padding: 8px 20px;
                    background: #667eea;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                }

                .facts-list {
                    display: flex;
                    flex-direction: column;
                    gap: 15px;
                    max-height: 600px;
                    overflow-y: auto;
                }

                .fact-card {
                    padding: 20px;
                    background: #f9f9f9;
                    border: 1px solid #e0e0e0;
                    border-radius: 8px;
                    border-right: 4px solid #667eea;
                }

                .fact-header {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 12px;
                }

                .fact-category {
                    background: #667eea;
                    color: white;
                    padding: 4px 12px;
                    border-radius: 12px;
                    font-size: 12px;
                    font-weight: 600;
                }

                .verified {
                    color: #2ecc71;
                    font-weight: 600;
                }

                .pending {
                    color: #f39c12;
                    font-weight: 600;
                }

                .fact-content {
                    color: #333;
                    margin-bottom: 12px;
                    line-height: 1.6;
                }

                .fact-source {
                    font-size: 13px;
                    color: #666;
                    margin-bottom: 12px;
                }

                .fact-tags {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 6px;
                    margin-bottom: 15px;
                }

                .tag {
                    padding: 4px 10px;
                    background: #e8eaf6;
                    color: #667eea;
                    border-radius: 10px;
                    font-size: 11px;
                    font-weight: 600;
                }

                .fact-actions {
                    display: flex;
                    gap: 10px;
                }

                .fact-actions button {
                    padding: 6px 12px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 13px;
                    font-weight: 600;
                    transition: all 0.3s;
                }

                .btn-edit {
                    background: #667eea;
                    color: white;
                }

                .btn-verify {
                    background: #2ecc71;
                    color: white;
                }

                .btn-delete {
                    background: #e74c3c;
                    color: white;
                }

                .modal-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.6);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 1000;
                }

                .modal-content {
                    background: white;
                    border-radius: 12px;
                    padding: 30px;
                    max-width: 600px;
                    width: 90%;
                    max-height: 80vh;
                    overflow-y: auto;
                }

                .modal-content h3 {
                    margin: 0 0 20px 0;
                    color: #333;
                }

                .form-group {
                    margin-bottom: 20px;
                }

                .form-group label {
                    display: block;
                    margin-bottom: 8px;
                    color: #333;
                    font-weight: 600;
                }

                .form-group input,
                .form-group select,
                .form-group textarea {
                    width: 100%;
                    padding: 10px;
                    border: 1px solid #ddd;
                    border-radius: 6px;
                    font-size: 14px;
                }

                .modal-actions {
                    display: flex;
                    gap: 10px;
                    justify-content: flex-end;
                }

                .btn-save {
                    padding: 10px 20px;
                    background: #2ecc71;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 600;
                }

                .btn-cancel {
                    padding: 10px 20px;
                    background: #95a5a6;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 600;
                }

                .loading, .no-data {
                    text-align: center;
                    padding: 40px;
                    color: #999;
                }
            `}</style>
        </div>
    );
};

export default BrainGrowth;
