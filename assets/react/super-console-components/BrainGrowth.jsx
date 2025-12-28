import React, { useState, useEffect, useCallback, useMemo } from 'react';

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

    const loadKnowledgeStats = useCallback(async () => {
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
    }, []);

    const loadFacts = useCallback(async () => {
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
    }, [filter, searchTerm]);

    useEffect(() => {
        loadKnowledgeStats();
        loadFacts();
    }, [loadKnowledgeStats, loadFacts]);

    const handleEditFact = useCallback((fact) => {
        setSelectedFact({ ...fact });
        setEditMode(true);
    }, []);

    const handleSaveFact = useCallback(async () => {
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
    }, [selectedFact, loadFacts, loadKnowledgeStats]);

    const handleDeleteFact = useCallback(async (factId) => {
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
    }, [loadFacts, loadKnowledgeStats]);

    const handleVerifyFact = useCallback(async (factId, verified) => {
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
    }, [loadFacts]);

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

        </div>
    );
};

export default BrainGrowth;
