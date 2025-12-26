import React, { useState, useEffect } from 'react';
import { useHomaEvent, useHomaEmit } from '../homaReactBridge';
import './ExploreWidget.css';

/**
 * Explore Widget Component
 * Displays personalized product/content recommendations based on user interests
 * Similar to Instagram's Explore feature
 * 
 * @package HomayeTabesh
 * @since PR10
 */
const ExploreWidget = () => {
    const [recommendations, setRecommendations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [selectedCategory, setSelectedCategory] = useState('all');
    const homaEmit = useHomaEmit();

    useEffect(() => {
        loadRecommendations();
    }, []);

    // Listen for user behavior changes to update recommendations
    useHomaEvent('vault:interests_updated', (data) => {
        console.log('[Explore Widget] Interests updated, refreshing recommendations');
        loadRecommendations();
    });

    // Listen for navigation events to update recommendations
    useHomaEvent('page:navigate', (data) => {
        console.log('[Explore Widget] Page navigation detected, updating recommendations');
        loadRecommendations();
    });

    /**
     * Load personalized recommendations from server
     */
    const loadRecommendations = async () => {
        try {
            setLoading(true);
            setError(null);

            // Check if nonce is available
            if (!window.homayeParallelUIConfig?.nonce) {
                throw new Error('نشست شما منقضی شده است');
            }

            // Get user interests from Vault (PR7)
            const response = await fetch('/wp-json/homaye-tabesh/v1/vault/interests', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.homayeParallelUIConfig.nonce
                }
            });

            if (!response.ok) {
                throw new Error('خطا در دریافت پیشنهادات');
            }

            const data = await response.json();

            // Transform interests into recommendation cards
            const recs = transformInterestsToRecommendations(data.interests || []);
            setRecommendations(recs);
            setLoading(false);

            // Emit event that recommendations are loaded
            homaEmit('explore:recommendations_loaded', { count: recs.length });

        } catch (err) {
            console.error('[Explore Widget] Error loading recommendations:', err);
            setError(err.message);
            setLoading(false);
        }
    };

    /**
     * Transform user interests into recommendation cards
     * 
     * @param {Array} interests User interests from Vault
     * @returns {Array} Recommendation cards
     */
    const transformInterestsToRecommendations = (interests) => {
        if (!interests || interests.length === 0) {
            return getDefaultRecommendations();
        }

        // Group interests by category
        const byCategory = {};
        interests.forEach(interest => {
            const category = interest.category || 'عمومی';
            if (!byCategory[category]) {
                byCategory[category] = [];
            }
            byCategory[category].push(interest);
        });

        // Create recommendation cards based on interest patterns
        const cards = [];
        Object.entries(byCategory).forEach(([category, items]) => {
            // Get top interest in this category
            const topInterest = items.sort((a, b) => b.score - a.score)[0];
            
            cards.push({
                id: `rec-${category}-${Date.now()}`,
                category: category,
                title: getRecommendationTitle(topInterest),
                description: getRecommendationDescription(topInterest),
                image: getRecommendationImage(topInterest),
                link: topInterest.related_url || '#',
                score: topInterest.score || 0.5,
                reason: getRecommendationReason(topInterest)
            });
        });

        return cards.sort((a, b) => b.score - a.score);
    };

    /**
     * Get recommendation title based on interest
     */
    const getRecommendationTitle = (interest) => {
        if (interest.product_name) {
            return interest.product_name;
        }
        if (interest.page_title) {
            return interest.page_title;
        }
        return `پیشنهاد ${interest.category || 'ویژه'}`;
    };

    /**
     * Get recommendation description
     */
    const getRecommendationDescription = (interest) => {
        if (interest.description) {
            return interest.description;
        }
        if (interest.context) {
            return interest.context.substring(0, 100) + '...';
        }
        return 'بر اساس علایق شما پیشنهاد می‌شود';
    };

    /**
     * Get recommendation image
     */
    const getRecommendationImage = (interest) => {
        if (interest.thumbnail) {
            return interest.thumbnail;
        }
        // Default placeholder image
        return window.homayeParallelUIConfig?.pluginUrl + '/assets/images/placeholder.jpg' || '';
    };

    /**
     * Get recommendation reason
     */
    const getRecommendationReason = (interest) => {
        const reasons = [
            'چون به این محتوا علاقه نشان دادید',
            'بر اساس بازدیدهای اخیر شما',
            'محصول مکمل برای خرید شما',
            'پیشنهاد ویژه برای شما',
            'دیگران این را هم دیدند'
        ];
        
        const index = Math.floor(interest.score * reasons.length);
        return reasons[Math.min(index, reasons.length - 1)];
    };

    /**
     * Get default recommendations when no interests are available
     */
    const getDefaultRecommendations = () => {
        return [
            {
                id: 'default-1',
                category: 'محبوب',
                title: 'محصولات پرفروش',
                description: 'محبوب‌ترین محصولات این ماه',
                image: '',
                link: '/shop',
                score: 1.0,
                reason: 'پیشنهاد محبوب'
            },
            {
                id: 'default-2',
                category: 'جدید',
                title: 'تازه‌های سایت',
                description: 'آخرین محصولات اضافه شده',
                image: '',
                link: '/shop?orderby=date',
                score: 0.9,
                reason: 'جدیدترین‌ها'
            }
        ];
    };

    /**
     * Handle card click
     */
    const handleCardClick = (card) => {
        // Emit navigation event
        homaEmit('explore:card_clicked', {
            cardId: card.id,
            category: card.category,
            title: card.title
        });

        // Navigate to link
        if (card.link && card.link !== '#') {
            window.location.href = card.link;
        }
    };

    /**
     * Filter recommendations by category
     */
    const filteredRecommendations = selectedCategory === 'all' 
        ? recommendations 
        : recommendations.filter(r => r.category === selectedCategory);

    /**
     * Get unique categories
     */
    const categories = ['all', ...new Set(recommendations.map(r => r.category))];

    if (loading) {
        return (
            <div className="explore-widget">
                <div className="explore-header">
                    <h3>🔍 اکسپلور</h3>
                </div>
                <div className="explore-loading">
                    <div className="loading-spinner"></div>
                    <p>در حال بارگذاری پیشنهادات...</p>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="explore-widget">
                <div className="explore-header">
                    <h3>🔍 اکسپلور</h3>
                </div>
                <div className="explore-error">
                    <p>⚠️ {error}</p>
                    <button onClick={loadRecommendations} className="retry-btn">
                        تلاش مجدد
                    </button>
                </div>
            </div>
        );
    }

    if (recommendations.length === 0) {
        return (
            <div className="explore-widget">
                <div className="explore-header">
                    <h3>🔍 اکسپلور</h3>
                </div>
                <div className="explore-empty">
                    <p>هنوز پیشنهادی برای شما نداریم</p>
                    <p className="explore-hint">کمی در سایت بگردید تا بتوانیم شما را بهتر بشناسیم 😊</p>
                </div>
            </div>
        );
    }

    return (
        <div className="explore-widget">
            <div className="explore-header">
                <h3>🔍 اکسپلور</h3>
                <button 
                    className="explore-refresh-btn"
                    onClick={loadRecommendations}
                    title="به‌روزرسانی"
                >
                    ↻
                </button>
            </div>

            {/* Category filter */}
            {categories.length > 2 && (
                <div className="explore-categories">
                    {categories.map(category => (
                        <button
                            key={category}
                            className={`category-btn ${selectedCategory === category ? 'active' : ''}`}
                            onClick={() => setSelectedCategory(category)}
                        >
                            {category === 'all' ? 'همه' : category}
                        </button>
                    ))}
                </div>
            )}

            {/* Recommendation cards */}
            <div className="explore-cards">
                {filteredRecommendations.map(card => (
                    <div 
                        key={card.id}
                        className="explore-card"
                        onClick={() => handleCardClick(card)}
                    >
                        {card.image && (
                            <div className="card-image">
                                <img src={card.image} alt={card.title} />
                            </div>
                        )}
                        <div className="card-content">
                            <div className="card-category">{card.category}</div>
                            <h4 className="card-title">{card.title}</h4>
                            <p className="card-description">{card.description}</p>
                            <div className="card-reason">
                                <span className="reason-icon">💡</span>
                                <span className="reason-text">{card.reason}</span>
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            {filteredRecommendations.length === 0 && selectedCategory !== 'all' && (
                <div className="explore-empty">
                    <p>پیشنهادی در این دسته وجود ندارد</p>
                </div>
            )}
        </div>
    );
};

export default ExploreWidget;
