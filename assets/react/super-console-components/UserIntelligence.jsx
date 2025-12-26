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

            <style jsx>{`
                .user-intelligence {
                    padding: 20px;
                }

                .controls-bar {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 20px;
                    gap: 20px;
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
                    padding: 8px 16px;
                    border: 1px solid #ddd;
                    border-radius: 6px;
                    width: 300px;
                    font-size: 14px;
                }

                .user-intelligence-layout {
                    display: grid;
                    grid-template-columns: 350px 1fr;
                    gap: 20px;
                    min-height: 600px;
                }

                .users-list-panel {
                    background: white;
                    border: 1px solid #e0e0e0;
                    border-radius: 12px;
                    padding: 20px;
                    overflow-y: auto;
                    max-height: 700px;
                }

                .users-list-panel h3 {
                    margin: 0 0 15px 0;
                    font-size: 16px;
                    color: #333;
                }

                .users-list {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                }

                .user-card {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    padding: 12px;
                    background: #f9f9f9;
                    border: 2px solid transparent;
                    border-radius: 8px;
                    cursor: pointer;
                    transition: all 0.3s;
                }

                .user-card:hover {
                    background: #f0f0f0;
                }

                .user-card.selected {
                    background: #e8eaf6;
                    border-color: #667eea;
                }

                .user-avatar {
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    background: #667eea;
                    color: white;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: bold;
                    font-size: 18px;
                }

                .user-info {
                    flex: 1;
                }

                .user-name {
                    font-weight: 600;
                    color: #333;
                    font-size: 14px;
                }

                .user-email {
                    font-size: 12px;
                    color: #666;
                }

                .security-score {
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: bold;
                    font-size: 14px;
                }

                .user-detail-panel {
                    background: white;
                    border: 1px solid #e0e0e0;
                    border-radius: 12px;
                    padding: 20px;
                    overflow-y: auto;
                    max-height: 700px;
                }

                .detail-header {
                    display: flex;
                    gap: 20px;
                    margin-bottom: 30px;
                    padding-bottom: 20px;
                    border-bottom: 2px solid #f0f0f0;
                }

                .user-avatar-large {
                    width: 80px;
                    height: 80px;
                    border-radius: 50%;
                    background: #667eea;
                    color: white;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: bold;
                    font-size: 32px;
                }

                .user-primary-info {
                    flex: 1;
                }

                .user-primary-info h2 {
                    margin: 0 0 8px 0;
                    font-size: 24px;
                    color: #333;
                }

                .user-primary-info p {
                    margin: 0 0 12px 0;
                    color: #666;
                    font-size: 14px;
                }

                .user-roles {
                    display: flex;
                    gap: 8px;
                }

                .role-badge {
                    padding: 4px 12px;
                    background: #667eea;
                    color: white;
                    border-radius: 12px;
                    font-size: 12px;
                    font-weight: 600;
                }

                .security-score-large {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    width: 100px;
                    height: 100px;
                    border-radius: 50%;
                    color: white;
                }

                .score-value {
                    font-size: 32px;
                    font-weight: bold;
                }

                .score-label {
                    font-size: 11px;
                }

                .user-stats {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 15px;
                    margin-bottom: 30px;
                }

                .stat-box {
                    background: #f9f9f9;
                    padding: 15px;
                    border-radius: 8px;
                    text-align: center;
                }

                .stat-value {
                    font-size: 24px;
                    font-weight: bold;
                    color: #667eea;
                    margin-bottom: 5px;
                }

                .stat-label {
                    font-size: 13px;
                    color: #666;
                }

                .user-section {
                    margin-bottom: 30px;
                }

                .user-section h3 {
                    margin: 0 0 15px 0;
                    font-size: 16px;
                    color: #333;
                }

                .interests-tags {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                }

                .interest-tag {
                    padding: 6px 14px;
                    background: #e8eaf6;
                    color: #667eea;
                    border-radius: 16px;
                    font-size: 13px;
                    font-weight: 600;
                }

                .conversations-list {
                    display: flex;
                    flex-direction: column;
                    gap: 12px;
                }

                .conversation-item {
                    padding: 15px;
                    background: #f9f9f9;
                    border-radius: 8px;
                    border-left: 4px solid #667eea;
                }

                .conv-header {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 8px;
                    font-size: 12px;
                    color: #666;
                }

                .conv-type {
                    background: #667eea;
                    color: white;
                    padding: 2px 8px;
                    border-radius: 10px;
                }

                .conv-preview {
                    color: #333;
                    font-size: 14px;
                }

                .security-events-list {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                }

                .security-event {
                    display: flex;
                    justify-content: space-between;
                    padding: 12px;
                    background: #fff3cd;
                    border-radius: 6px;
                    font-size: 13px;
                }

                .event-penalty {
                    color: #e74c3c;
                    font-weight: bold;
                }

                .no-selection {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    height: 100%;
                    color: #999;
                    font-size: 16px;
                }

                .no-data {
                    text-align: center;
                    color: #999;
                    padding: 20px;
                }

                .loading {
                    text-align: center;
                    padding: 40px;
                    color: #999;
                }
            `}</style>
        </div>
    );
};

export default UserIntelligence;
