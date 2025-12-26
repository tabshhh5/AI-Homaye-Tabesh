import React, { useState, useEffect } from 'react';

/**
 * Order Tracker Component
 * Displays customer order status and shipping information
 * 
 * @package HomayeTabesh
 * @since PR15
 */
const OrderTracker = ({ userContext }) => {
    const [orders, setOrders] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        // Fetch user orders on mount
        fetchUserOrders();
    }, []);

    const fetchUserOrders = async () => {
        try {
            const response = await fetch('/wp-json/homaye-tabesh/v1/orders/my-orders', {
                headers: {
                    'X-WP-Nonce': window.homayeParallelUIConfig?.nonce || ''
                }
            });

            if (response.ok) {
                const data = await response.json();
                setOrders(data.orders || []);
            }
        } catch (error) {
            console.error('Error fetching orders:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleTrackOrder = (orderId) => {
        if (window.Homa && window.Homa.emit) {
            window.Homa.emit('customer:track_order', { orderId });
        }
    };

    const handleRenewInvoice = (orderId) => {
        if (window.Homa && window.Homa.emit) {
            window.Homa.emit('customer:renew_invoice', { orderId });
        }
    };

    const handleCreateTicket = () => {
        if (window.Homa && window.Homa.emit) {
            window.Homa.emit('customer:create_ticket', {});
        }
    };

    return (
        <div className="homa-order-tracker">
            <div className="homa-tools-header">
                <h4>📦 سفارش‌های من</h4>
                <span className="homa-role-badge customer">مشتری</span>
            </div>

            <div className="homa-welcome-customer">
                <p>
                    سلام {userContext?.identity} عزیز! 👋
                    <br />
                    خوشحالیم که دوباره می‌بینیمتون. می‌توانم وضعیت سفارش‌ها و پیگیری مرسوله‌ها را بررسی کنم.
                </p>
            </div>

            <div className="homa-quick-actions">
                <button 
                    className="homa-action-button track"
                    onClick={() => orders.length > 0 && handleTrackOrder(orders[0].id)}
                    disabled={orders.length === 0}
                >
                    🚚 پیگیری آخرین سفارش
                </button>

                <button 
                    className="homa-action-button ticket"
                    onClick={handleCreateTicket}
                >
                    💬 تیکت پشتیبانی
                </button>
            </div>

            {loading ? (
                <div className="homa-loading">
                    <span className="homa-spinner"></span>
                    <p>در حال بارگذاری سفارش‌ها...</p>
                </div>
            ) : orders.length === 0 ? (
                <div className="homa-empty-orders">
                    <p>شما هنوز سفارشی ثبت نکرده‌اید.</p>
                    <a href="/shop" className="homa-browse-button">
                        مشاهده محصولات
                    </a>
                </div>
            ) : (
                <div className="homa-orders-list">
                    {orders.slice(0, 3).map((order) => (
                        <div key={order.id} className="homa-order-card">
                            <div className="homa-order-header">
                                <span className="homa-order-number">#{order.number}</span>
                                <span className={`homa-order-status ${order.status}`}>
                                    {order.status_label}
                                </span>
                            </div>
                            <div className="homa-order-info">
                                <p className="homa-order-date">{order.date}</p>
                                <p className="homa-order-total">{order.total}</p>
                            </div>
                            <div className="homa-order-actions">
                                <button 
                                    onClick={() => handleTrackOrder(order.id)}
                                    className="homa-order-action-btn"
                                >
                                    پیگیری
                                </button>
                                {order.can_renew && (
                                    <button 
                                        onClick={() => handleRenewInvoice(order.id)}
                                        className="homa-order-action-btn renew"
                                    >
                                        تمدید
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {orders.length > 3 && (
                <div className="homa-view-all">
                    <a href="/my-account/orders">مشاهده همه سفارش‌ها</a>
                </div>
            )}
        </div>
    );
};

export default OrderTracker;
