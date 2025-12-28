import React, { useState, useEffect } from 'react';

/**
 * User Intelligence Tab - Tab 2
 * تب ۲: مرکز مدیریت کاربران
 * 
 * 360-degree user profiles with security scores and conversation history
 */
const UserIntelligence = () => {
    const [users, setUsers] = useState([]);
    const [selectedUser, setSelectedUser] = useState(null);
    const [filter, setFilter] = useState('all');
    const [searchTerm, setSearchTerm] = useState('');
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        loadUsers();
    }, [filter]);

    const loadUsers = async () => {
        setLoading(true);
        try {
            const response = await fetch(
                `${window.homaConsoleConfig.apiUrl}/users?filter=${filter}`,
                {
                    headers: {
                        'X-WP-Nonce': window.homaConsoleConfig.nonce
                    }
                }
            );
            const data = await response.json();
            if (data.success) {
                setUsers(data.data);
            }
        } catch (error) {
            console.error('Failed to load users:', error);
        } finally {
            setLoading(false);
        }
    };

    const loadUserDetails = async (userId) => {
        try {
            const response = await fetch(
                `${window.homaConsoleConfig.apiUrl}/users/${userId}`,
                {
                    headers: {
                        'X-WP-Nonce': window.homaConsoleConfig.nonce
                    }
                }
            );
            const data = await response.json();
            if (data.success) {
                setSelectedUser(data.data);
            }
        } catch (error) {
            console.error('Failed to load user details:', error);
        }
    };

    const getSecurityColor = (score) => {
        if (score >= 80) return '#2ecc71';
        if (score >= 50) return '#f39c12';
        return '#e74c3c';
    };

    const filteredUsers = users.filter(user => 
        user.display_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        user.email?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        user.user_identifier?.includes(searchTerm)
    );

    return (
        <div className="user-intelligence" dir="rtl">
            {/* Filters and Search */}
            <div className="controls-bar">
                <div className="filter-buttons">
                    <button 
                        className={filter === 'all' ? 'active' : ''} 
                        onClick={() => setFilter('all')}
                    >
                        همه کاربران
                    </button>
                    <button 
                        className={filter === 'admins' ? 'active' : ''} 
                        onClick={() => setFilter('admins')}
                    >
                        مدیران
                    </button>
                    <button 
                        className={filter === 'staff' ? 'active' : ''} 
                        onClick={() => setFilter('staff')}
                    >
                        کارمندان
                    </button>
                    <button 
                        className={filter === 'suspicious' ? 'active' : ''} 
                        onClick={() => setFilter('suspicious')}
                    >
                        ⚠️ مشکوک
                    </button>
                </div>
                <input 
                    type="text"
                    className="search-input"
                    placeholder="🔍 جستجوی کاربر..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                />
            </div>

            <div className="user-intelligence-layout">
                {/* Users List */}
                <div className="users-list-panel">
                    <h3>لیست کاربران ({filteredUsers.length})</h3>
                    {loading ? (
                        <div className="loading">در حال بارگذاری...</div>
                    ) : (
                        <div className="users-list">
                            {filteredUsers.map(user => (
                                <div 
                                    key={user.id || user.user_identifier}
                                    className={`user-card ${selectedUser?.id === user.id ? 'selected' : ''}`}
                                    onClick={() => loadUserDetails(user.id || user.user_identifier)}
                                >
                                    <div className="user-avatar">
                                        {user.display_name?.[0] || '?'}
                                    </div>
                                    <div className="user-info">
                                        <div className="user-name">{user.display_name || 'ناشناس'}</div>
                                        <div className="user-email">{user.email || user.user_identifier}</div>
                                    </div>
                                    <div 
                                        className="security-score"
                                        style={{ 
                                            background: getSecurityColor(user.security_score || 100),
                                            color: 'white'
                                        }}
                                    >
                                        {user.security_score || 100}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* User Detail Panel */}
                <div className="user-detail-panel">
                    {selectedUser ? (
                        <>
                            <div className="detail-header">
                                <div className="user-avatar-large">
                                    {selectedUser.display_name?.[0] || '?'}
                                </div>
                                <div className="user-primary-info">
                                    <h2>{selectedUser.display_name || 'کاربر ناشناس'}</h2>
                                    <p>{selectedUser.email || selectedUser.user_identifier}</p>
                                    <div className="user-roles">
                                        {selectedUser.roles?.map(role => (
                                            <span key={role} className="role-badge">{role}</span>
                                        ))}
                                    </div>
                                </div>
                                <div 
                                    className="security-score-large"
                                    style={{ background: getSecurityColor(selectedUser.security_score || 100) }}
                                >
                                    <div className="score-value">{selectedUser.security_score || 100}</div>
                                    <div className="score-label">امتیاز امنیتی</div>
                                </div>
                            </div>

                            {/* User Stats */}
                            <div className="user-stats">
                                <div className="stat-box">
                                    <div className="stat-value">{selectedUser.conversation_count || 0}</div>
                                    <div className="stat-label">گفتگوها</div>
                                </div>
                                <div className="stat-box">
                                    <div className="stat-value">{selectedUser.lead_score || 0}</div>
                                    <div className="stat-label">امتیاز لید</div>
                                </div>
                                <div className="stat-box">
                                    <div className="stat-value">{selectedUser.last_active || 'نامشخص'}</div>
                                    <div className="stat-label">آخرین فعالیت</div>
                                </div>
                            </div>

                            {/* Interests */}
                            {selectedUser.interests && selectedUser.interests.length > 0 && (
                                <div className="user-section">
                                    <h3>🎯 علایق استخراج شده</h3>
                                    <div className="interests-tags">
                                        {selectedUser.interests.map((interest, idx) => (
                                            <span key={idx} className="interest-tag">{interest}</span>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Conversation History */}
                            <div className="user-section">
                                <h3>💬 تاریخچه گفتگوها</h3>
                                {selectedUser.conversations && selectedUser.conversations.length > 0 ? (
                                    <div className="conversations-list">
                                        {selectedUser.conversations.map((conv, idx) => (
                                            <div key={idx} className="conversation-item">
                                                <div className="conv-header">
                                                    <span className="conv-date">{conv.date}</span>
                                                    <span className="conv-type">{conv.type}</span>
                                                </div>
                                                <div className="conv-preview">{conv.preview}</div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="no-data">هیچ گفتگویی ثبت نشده است</p>
                                )}
                            </div>

                            {/* Security Events */}
                            {selectedUser.security_events && selectedUser.security_events.length > 0 && (
                                <div className="user-section">
                                    <h3>🛡️ رویدادهای امنیتی</h3>
                                    <div className="security-events-list">
                                        {selectedUser.security_events.map((event, idx) => (
                                            <div key={idx} className="security-event">
                                                <span className="event-type">{event.type}</span>
                                                <span className="event-penalty">-{event.penalty}</span>
                                                <span className="event-date">{event.date}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="no-selection">
                            <p>👈 کاربری را از لیست انتخاب کنید</p>
                        </div>
                    )}
                </div>
            </div>

        </div>
    );
};

export default UserIntelligence;
