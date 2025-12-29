import React, { useState, useEffect } from 'react';

/**
 * Order Management Tab - مدیریت سفارشات
 * 
 * Comprehensive order management dashboard for admins
 * - View all orders with advanced filtering
 * - Create new orders manually
 * - Edit existing orders
 * - Update order status
 * - View order details
 * 
 * @package HomayeTabesh
 * @since 1.0.0
 */
const OrderManagement = () => {
    const [orders, setOrders] = useState([]);
    const [selectedOrder, setSelectedOrder] = useState(null);
    const [viewMode, setViewMode] = useState('list'); // 'list', 'create', 'edit', 'view'
    const [loading, setLoading] = useState(true);
    const [filter, setFilter] = useState('all');
    const [searchTerm, setSearchTerm] = useState('');
    const [formData, setFormData] = useState({
        customer_name: '',
        customer_email: '',
        customer_phone: '',
        billing_address: '',
        shipping_address: '',
        items: [{ product_name: '', quantity: 1, price: 0 }],
        status: 'pending',
        payment_method: 'bank',
        shipping_method: 'standard',
        notes: ''
    });

    useEffect(() => {
        loadOrders();
    }, [filter]);

    const loadOrders = async () => {
        setLoading(true);
        try {
            const response = await fetch(
                `${window.homaConsoleConfig.apiUrl}/orders?filter=${filter}`,
                {
                    headers: {
                        'X-WP-Nonce': window.homaConsoleConfig.nonce
                    }
                }
            );
            const data = await response.json();
            if (data.success) {
                setOrders(data.data || []);
            }
        } catch (error) {
            console.error('Failed to load orders:', error);
        } finally {
            setLoading(false);
        }
    };

    const loadOrderDetails = async (orderId) => {
        try {
            const response = await fetch(
                `${window.homaConsoleConfig.apiUrl}/orders/${orderId}`,
                {
                    headers: {
                        'X-WP-Nonce': window.homaConsoleConfig.nonce
                    }
                }
            );
            const data = await response.json();
            if (data.success) {
                setSelectedOrder(data.data);
                setFormData({
                    customer_name: data.data.customer_name || '',
                    customer_email: data.data.customer_email || '',
                    customer_phone: data.data.customer_phone || '',
                    billing_address: data.data.billing_address || '',
                    shipping_address: data.data.shipping_address || '',
                    items: data.data.items || [{ product_name: '', quantity: 1, price: 0 }],
                    status: data.data.status || 'pending',
                    payment_method: data.data.payment_method || 'bank',
                    shipping_method: data.data.shipping_method || 'standard',
                    notes: data.data.notes || ''
                });
                setViewMode('view');
            }
        } catch (error) {
            console.error('Failed to load order details:', error);
        }
    };

    const handleCreateOrder = async () => {
        try {
            const response = await fetch(
                `${window.homaConsoleConfig.apiUrl}/orders`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.homaConsoleConfig.nonce
                    },
                    body: JSON.stringify(formData)
                }
            );
            const data = await response.json();
            if (data.success) {
                alert('✅ سفارش با موفقیت ثبت شد!');
                setViewMode('list');
                loadOrders();
                resetForm();
            } else {
                alert('❌ خطا در ثبت سفارش: ' + (data.message || 'خطای ناشناخته'));
            }
        } catch (error) {
            console.error('Failed to create order:', error);
            alert('❌ خطا در ارتباط با سرور');
        }
    };

    const handleUpdateOrder = async () => {
        if (!selectedOrder) return;
        
        try {
            const response = await fetch(
                `${window.homaConsoleConfig.apiUrl}/orders/${selectedOrder.id}`,
                {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.homaConsoleConfig.nonce
                    },
                    body: JSON.stringify(formData)
                }
            );
            const data = await response.json();
            if (data.success) {
                alert('✅ سفارش با موفقیت بروزرسانی شد!');
                setViewMode('list');
                loadOrders();
            } else {
                alert('❌ خطا در بروزرسانی سفارش: ' + (data.message || 'خطای ناشناخته'));
            }
        } catch (error) {
            console.error('Failed to update order:', error);
            alert('❌ خطا در ارتباط با سرور');
        }
    };

    const handleDeleteOrder = async (orderId) => {
        if (!confirm('آیا مطمئن هستید که می‌خواهید این سفارش را حذف کنید؟')) {
            return;
        }

        try {
            const response = await fetch(
                `${window.homaConsoleConfig.apiUrl}/orders/${orderId}`,
                {
                    method: 'DELETE',
                    headers: {
                        'X-WP-Nonce': window.homaConsoleConfig.nonce
                    }
                }
            );
            const data = await response.json();
            if (data.success) {
                alert('✅ سفارش با موفقیت حذف شد!');
                loadOrders();
            } else {
                alert('❌ خطا در حذف سفارش: ' + (data.message || 'خطای ناشناخته'));
            }
        } catch (error) {
            console.error('Failed to delete order:', error);
            alert('❌ خطا در ارتباط با سرور');
        }
    };

    const resetForm = () => {
        setFormData({
            customer_name: '',
            customer_email: '',
            customer_phone: '',
            billing_address: '',
            shipping_address: '',
            items: [{ product_name: '', quantity: 1, price: 0 }],
            status: 'pending',
            payment_method: 'bank',
            shipping_method: 'standard',
            notes: ''
        });
        setSelectedOrder(null);
    };

    const addItem = () => {
        setFormData({
            ...formData,
            items: [...formData.items, { product_name: '', quantity: 1, price: 0 }]
        });
    };

    const removeItem = (index) => {
        const newItems = formData.items.filter((_, i) => i !== index);
        setFormData({ ...formData, items: newItems });
    };

    const updateItem = (index, field, value) => {
        const newItems = [...formData.items];
        newItems[index] = { ...newItems[index], [field]: value };
        setFormData({ ...formData, items: newItems });
    };

    const calculateTotal = () => {
        return formData.items.reduce((sum, item) => {
            return sum + (parseFloat(item.price || 0) * parseInt(item.quantity || 0));
        }, 0);
    };

    const getStatusLabel = (status) => {
        const labels = {
            'pending': 'در انتظار پرداخت',
            'processing': 'در حال پردازش',
            'on-hold': 'معلق',
            'preparing': 'در حال آماده‌سازی',
            'shipped': 'ارسال شده',
            'completed': 'تکمیل شده',
            'cancelled': 'لغو شده',
            'refunded': 'بازگشت داده شده',
            'failed': 'ناموفق'
        };
        return labels[status] || status;
    };

    const getStatusColor = (status) => {
        const colors = {
            'pending': '#f39c12',
            'processing': '#3498db',
            'on-hold': '#95a5a6',
            'preparing': '#9b59b6',
            'shipped': '#1abc9c',
            'completed': '#2ecc71',
            'cancelled': '#e74c3c',
            'refunded': '#e67e22',
            'failed': '#c0392b'
        };
        return colors[status] || '#95a5a6';
    };

    const filteredOrders = orders.filter(order => {
        const matchesFilter = filter === 'all' || order.status === filter;
        const matchesSearch = 
            !searchTerm ||
            order.order_number?.toString().includes(searchTerm) ||
            order.customer_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
            order.customer_email?.toLowerCase().includes(searchTerm.toLowerCase()) ||
            order.customer_phone?.includes(searchTerm);
        return matchesFilter && matchesSearch;
    });

    // Render List View
    const renderListView = () => (
        <div className="order-list-view" dir="rtl">
            {/* Header with Actions */}
            <div className="list-header">
                <h2>📦 مدیریت سفارشات</h2>
                <button 
                    className="btn-primary btn-create-order"
                    onClick={() => {
                        resetForm();
                        setViewMode('create');
                    }}
                >
                    ➕ ثبت سفارش جدید
                </button>
            </div>

            {/* Filters and Search */}
            <div className="controls-bar">
                <div className="filter-buttons">
                    <button 
                        className={filter === 'all' ? 'active' : ''} 
                        onClick={() => setFilter('all')}
                    >
                        همه ({orders.length})
                    </button>
                    <button 
                        className={filter === 'pending' ? 'active' : ''} 
                        onClick={() => setFilter('pending')}
                    >
                        در انتظار
                    </button>
                    <button 
                        className={filter === 'processing' ? 'active' : ''} 
                        onClick={() => setFilter('processing')}
                    >
                        در حال پردازش
                    </button>
                    <button 
                        className={filter === 'completed' ? 'active' : ''} 
                        onClick={() => setFilter('completed')}
                    >
                        تکمیل شده
                    </button>
                    <button 
                        className={filter === 'cancelled' ? 'active' : ''} 
                        onClick={() => setFilter('cancelled')}
                    >
                        لغو شده
                    </button>
                </div>
                <input 
                    type="text"
                    className="search-input"
                    placeholder="🔍 جستجو بر اساس شماره، نام، ایمیل یا تلفن..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                />
            </div>

            {/* Orders Table */}
            {loading ? (
                <div className="loading-container">
                    <div className="spinner"></div>
                    <p>در حال بارگذاری سفارشات...</p>
                </div>
            ) : filteredOrders.length === 0 ? (
                <div className="no-data">
                    <p>هیچ سفارشی یافت نشد</p>
                </div>
            ) : (
                <div className="orders-table-container">
                    <table className="orders-table">
                        <thead>
                            <tr>
                                <th>شماره سفارش</th>
                                <th>مشتری</th>
                                <th>تماس</th>
                                <th>تاریخ</th>
                                <th>مبلغ کل</th>
                                <th>وضعیت</th>
                                <th>روش پرداخت</th>
                                <th>ارسال</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filteredOrders.map(order => (
                                <tr key={order.id}>
                                    <td className="order-number">#{order.order_number || order.id}</td>
                                    <td className="customer-name">{order.customer_name}</td>
                                    <td className="customer-contact">
                                        <div>{order.customer_email}</div>
                                        <div className="phone">{order.customer_phone}</div>
                                    </td>
                                    <td className="order-date">{order.date_created}</td>
                                    <td className="order-total">{order.total?.toLocaleString('fa-IR')} تومان</td>
                                    <td>
                                        <span 
                                            className="status-badge"
                                            style={{ backgroundColor: getStatusColor(order.status) }}
                                        >
                                            {getStatusLabel(order.status)}
                                        </span>
                                    </td>
                                    <td>{order.payment_method_title || order.payment_method}</td>
                                    <td>{order.shipping_method_title || order.shipping_method}</td>
                                    <td className="actions">
                                        <button 
                                            className="btn-icon btn-view"
                                            onClick={() => loadOrderDetails(order.id)}
                                            title="مشاهده جزئیات"
                                        >
                                            👁️
                                        </button>
                                        <button 
                                            className="btn-icon btn-edit"
                                            onClick={() => {
                                                loadOrderDetails(order.id);
                                                setTimeout(() => setViewMode('edit'), 100);
                                            }}
                                            title="ویرایش"
                                        >
                                            ✏️
                                        </button>
                                        <button 
                                            className="btn-icon btn-delete"
                                            onClick={() => handleDeleteOrder(order.id)}
                                            title="حذف"
                                        >
                                            🗑️
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );

    // Render Create/Edit Form
    const renderForm = () => (
        <div className="order-form-view" dir="rtl">
            <div className="form-header">
                <h2>
                    {viewMode === 'create' ? '➕ ثبت سفارش جدید' : '✏️ ویرایش سفارش'}
                </h2>
                <button 
                    className="btn-secondary"
                    onClick={() => {
                        setViewMode('list');
                        resetForm();
                    }}
                >
                    ← بازگشت به لیست
                </button>
            </div>

            <div className="order-form">
                {/* Customer Information */}
                <div className="form-section">
                    <h3>👤 اطلاعات مشتری</h3>
                    <div className="form-row">
                        <div className="form-group">
                            <label>نام و نام خانوادگی *</label>
                            <input 
                                type="text"
                                value={formData.customer_name}
                                onChange={(e) => setFormData({...formData, customer_name: e.target.value})}
                                placeholder="نام مشتری"
                                required
                            />
                        </div>
                        <div className="form-group">
                            <label>ایمیل</label>
                            <input 
                                type="email"
                                value={formData.customer_email}
                                onChange={(e) => setFormData({...formData, customer_email: e.target.value})}
                                placeholder="example@email.com"
                            />
                        </div>
                        <div className="form-group">
                            <label>شماره تماس *</label>
                            <input 
                                type="tel"
                                value={formData.customer_phone}
                                onChange={(e) => setFormData({...formData, customer_phone: e.target.value})}
                                placeholder="09xxxxxxxxx"
                                required
                            />
                        </div>
                    </div>
                </div>

                {/* Address Information */}
                <div className="form-section">
                    <h3>📍 آدرس</h3>
                    <div className="form-row">
                        <div className="form-group full-width">
                            <label>آدرس صورتحساب</label>
                            <textarea 
                                value={formData.billing_address}
                                onChange={(e) => setFormData({...formData, billing_address: e.target.value})}
                                placeholder="آدرس کامل برای صدور فاکتور"
                                rows="3"
                            />
                        </div>
                    </div>
                    <div className="form-row">
                        <div className="form-group full-width">
                            <label>آدرس ارسال</label>
                            <textarea 
                                value={formData.shipping_address}
                                onChange={(e) => setFormData({...formData, shipping_address: e.target.value})}
                                placeholder="آدرس محل تحویل سفارش"
                                rows="3"
                            />
                        </div>
                    </div>
                </div>

                {/* Order Items */}
                <div className="form-section">
                    <h3>📦 آیتم‌های سفارش</h3>
                    {formData.items.map((item, index) => (
                        <div key={index} className="item-row">
                            <div className="form-group">
                                <label>نام محصول</label>
                                <input 
                                    type="text"
                                    value={item.product_name}
                                    onChange={(e) => updateItem(index, 'product_name', e.target.value)}
                                    placeholder="نام محصول"
                                />
                            </div>
                            <div className="form-group">
                                <label>تعداد</label>
                                <input 
                                    type="number"
                                    min="1"
                                    value={item.quantity}
                                    onChange={(e) => updateItem(index, 'quantity', parseInt(e.target.value))}
                                />
                            </div>
                            <div className="form-group">
                                <label>قیمت واحد (تومان)</label>
                                <input 
                                    type="number"
                                    min="0"
                                    value={item.price}
                                    onChange={(e) => updateItem(index, 'price', parseFloat(e.target.value))}
                                />
                            </div>
                            <div className="form-group">
                                <label>جمع</label>
                                <input 
                                    type="text"
                                    value={(item.price * item.quantity).toLocaleString('fa-IR')}
                                    disabled
                                />
                            </div>
                            {formData.items.length > 1 && (
                                <button 
                                    className="btn-icon btn-delete"
                                    onClick={() => removeItem(index)}
                                    title="حذف آیتم"
                                >
                                    🗑️
                                </button>
                            )}
                        </div>
                    ))}
                    <button className="btn-secondary btn-add-item" onClick={addItem}>
                        ➕ افزودن آیتم جدید
                    </button>
                    <div className="order-total-display">
                        <strong>مبلغ کل:</strong> {calculateTotal().toLocaleString('fa-IR')} تومان
                    </div>
                </div>

                {/* Order Details */}
                <div className="form-section">
                    <h3>⚙️ جزئیات سفارش</h3>
                    <div className="form-row">
                        <div className="form-group">
                            <label>وضعیت سفارش</label>
                            <select 
                                value={formData.status}
                                onChange={(e) => setFormData({...formData, status: e.target.value})}
                            >
                                <option value="pending">در انتظار پرداخت</option>
                                <option value="processing">در حال پردازش</option>
                                <option value="on-hold">معلق</option>
                                <option value="preparing">در حال آماده‌سازی</option>
                                <option value="shipped">ارسال شده</option>
                                <option value="completed">تکمیل شده</option>
                                <option value="cancelled">لغو شده</option>
                                <option value="refunded">بازگشت داده شده</option>
                            </select>
                        </div>
                        <div className="form-group">
                            <label>روش پرداخت</label>
                            <select 
                                value={formData.payment_method}
                                onChange={(e) => setFormData({...formData, payment_method: e.target.value})}
                            >
                                <option value="bank">انتقال بانکی</option>
                                <option value="card">کارت به کارت</option>
                                <option value="cash">نقدی</option>
                                <option value="online">پرداخت آنلاین</option>
                            </select>
                        </div>
                        <div className="form-group">
                            <label>روش ارسال</label>
                            <select 
                                value={formData.shipping_method}
                                onChange={(e) => setFormData({...formData, shipping_method: e.target.value})}
                            >
                                <option value="standard">معمولی</option>
                                <option value="express">سریع</option>
                                <option value="post">پست</option>
                                <option value="courier">پیک</option>
                            </select>
                        </div>
                    </div>
                </div>

                {/* Notes */}
                <div className="form-section">
                    <h3>📝 یادداشت‌ها</h3>
                    <div className="form-row">
                        <div className="form-group full-width">
                            <label>یادداشت سفارش</label>
                            <textarea 
                                value={formData.notes}
                                onChange={(e) => setFormData({...formData, notes: e.target.value})}
                                placeholder="یادداشت‌ها و توضیحات اضافی"
                                rows="4"
                            />
                        </div>
                    </div>
                </div>

                {/* Form Actions */}
                <div className="form-actions">
                    <button 
                        className="btn-primary btn-save"
                        onClick={viewMode === 'create' ? handleCreateOrder : handleUpdateOrder}
                    >
                        {viewMode === 'create' ? '✅ ثبت سفارش' : '💾 بروزرسانی سفارش'}
                    </button>
                    <button 
                        className="btn-secondary"
                        onClick={() => {
                            setViewMode('list');
                            resetForm();
                        }}
                    >
                        ❌ انصراف
                    </button>
                </div>
            </div>
        </div>
    );

    // Render View Mode (Order Details)
    const renderViewMode = () => {
        if (!selectedOrder) return null;

        return (
            <div className="order-view-mode" dir="rtl">
                <div className="view-header">
                    <h2>📋 جزئیات سفارش #{selectedOrder.order_number || selectedOrder.id}</h2>
                    <div className="view-actions">
                        <button 
                            className="btn-secondary"
                            onClick={() => setViewMode('edit')}
                        >
                            ✏️ ویرایش
                        </button>
                        <button 
                            className="btn-secondary"
                            onClick={() => {
                                setViewMode('list');
                                setSelectedOrder(null);
                            }}
                        >
                            ← بازگشت به لیست
                        </button>
                    </div>
                </div>

                <div className="order-details-grid">
                    {/* Order Status */}
                    <div className="detail-card">
                        <h3>🎯 وضعیت سفارش</h3>
                        <div 
                            className="status-badge-large"
                            style={{ backgroundColor: getStatusColor(selectedOrder.status) }}
                        >
                            {getStatusLabel(selectedOrder.status)}
                        </div>
                        <div className="detail-info">
                            <p><strong>تاریخ ثبت:</strong> {selectedOrder.date_created}</p>
                            <p><strong>آخرین بروزرسانی:</strong> {selectedOrder.date_modified || selectedOrder.date_created}</p>
                        </div>
                    </div>

                    {/* Customer Info */}
                    <div className="detail-card">
                        <h3>👤 اطلاعات مشتری</h3>
                        <div className="detail-info">
                            <p><strong>نام:</strong> {selectedOrder.customer_name}</p>
                            <p><strong>ایمیل:</strong> {selectedOrder.customer_email || '—'}</p>
                            <p><strong>تلفن:</strong> {selectedOrder.customer_phone}</p>
                        </div>
                    </div>

                    {/* Payment Info */}
                    <div className="detail-card">
                        <h3>💳 اطلاعات پرداخت</h3>
                        <div className="detail-info">
                            <p><strong>روش پرداخت:</strong> {selectedOrder.payment_method_title || selectedOrder.payment_method}</p>
                            <p><strong>وضعیت پرداخت:</strong> {selectedOrder.payment_status || 'در انتظار'}</p>
                            <p><strong>مبلغ کل:</strong> <span className="price-large">{selectedOrder.total?.toLocaleString('fa-IR')} تومان</span></p>
                        </div>
                    </div>

                    {/* Shipping Info */}
                    <div className="detail-card">
                        <h3>🚚 اطلاعات ارسال</h3>
                        <div className="detail-info">
                            <p><strong>روش ارسال:</strong> {selectedOrder.shipping_method_title || selectedOrder.shipping_method}</p>
                            <p><strong>کد رهگیری:</strong> {selectedOrder.tracking_code || 'هنوز صادر نشده'}</p>
                            {selectedOrder.shipping_address && (
                                <p><strong>آدرس:</strong> {selectedOrder.shipping_address}</p>
                            )}
                        </div>
                    </div>

                    {/* Order Items */}
                    <div className="detail-card full-width">
                        <h3>📦 آیتم‌های سفارش</h3>
                        <table className="items-table">
                            <thead>
                                <tr>
                                    <th>محصول</th>
                                    <th>تعداد</th>
                                    <th>قیمت واحد</th>
                                    <th>جمع</th>
                                </tr>
                            </thead>
                            <tbody>
                                {selectedOrder.items && selectedOrder.items.map((item, index) => (
                                    <tr key={index}>
                                        <td>{item.product_name || item.name}</td>
                                        <td>{item.quantity}</td>
                                        <td>{item.price?.toLocaleString('fa-IR')} تومان</td>
                                        <td><strong>{(item.price * item.quantity).toLocaleString('fa-IR')} تومان</strong></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Notes */}
                    {selectedOrder.notes && (
                        <div className="detail-card full-width">
                            <h3>📝 یادداشت‌ها</h3>
                            <p className="notes-content">{selectedOrder.notes}</p>
                        </div>
                    )}
                </div>
            </div>
        );
    };

    // Main Render
    return (
        <div className="order-management">
            {viewMode === 'list' && renderListView()}
            {(viewMode === 'create' || viewMode === 'edit') && renderForm()}
            {viewMode === 'view' && renderViewMode()}
        </div>
    );
};

export default OrderManagement;
