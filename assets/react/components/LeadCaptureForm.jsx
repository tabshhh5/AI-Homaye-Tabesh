import React, { useState } from 'react';
import './LeadCaptureForm.css';

/**
 * فرم هوشمند دریافت اطلاعات تماس
 * 
 * این کامپوننت در چت هما ظاهر می‌شود و اطلاعات تماس کاربر را دریافت می‌کند
 */
const LeadCaptureForm = ({ onSubmit, onSkip, initialData = {} }) => {
    const [formData, setFormData] = useState({
        contact_name: initialData.contact_name || '',
        contact_info: initialData.contact_info || '',
        company_name: initialData.company_name || '',
    });

    const [errors, setErrors] = useState({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    const validatePhone = (phone) => {
        // فرمت‌های قابل قبول: 09123456789, +989123456789
        const phoneRegex = /^(?:\+98|98|0)?9\d{9}$/;
        return phoneRegex.test(phone.replace(/[^0-9+]/g, ''));
    };

    const validateForm = () => {
        const newErrors = {};

        if (!formData.contact_name.trim()) {
            newErrors.contact_name = 'لطفاً نام خود را وارد کنید';
        }

        if (!formData.contact_info.trim()) {
            newErrors.contact_info = 'لطفاً شماره موبایل خود را وارد کنید';
        } else if (!validatePhone(formData.contact_info)) {
            newErrors.contact_info = 'شماره موبایل نامعتبر است';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (!validateForm()) {
            return;
        }

        setIsSubmitting(true);

        try {
            await onSubmit(formData);
        } catch (error) {
            console.error('Lead capture error:', error);
            setErrors({ submit: 'خطا در ارسال اطلاعات. لطفاً دوباره تلاش کنید.' });
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleChange = (field, value) => {
        setFormData(prev => ({
            ...prev,
            [field]: value
        }));

        // پاک کردن خطا هنگام تایپ
        if (errors[field]) {
            setErrors(prev => ({
                ...prev,
                [field]: undefined
            }));
        }
    };

    return (
        <div className="homa-lead-capture-form">
            <div className="homa-lead-form-header">
                <div className="homa-lead-form-icon">📝</div>
                <h3>اطلاعات تماس</h3>
                <p>برای دریافت پیشنهاد قیمت و مشاوره رایگان</p>
            </div>

            <form onSubmit={handleSubmit} className="homa-lead-form-content">
                <div className="homa-form-group">
                    <label htmlFor="contact_name">
                        نام و نام خانوادگی <span className="required">*</span>
                    </label>
                    <input
                        type="text"
                        id="contact_name"
                        value={formData.contact_name}
                        onChange={(e) => handleChange('contact_name', e.target.value)}
                        placeholder="مثال: علی احمدی"
                        disabled={isSubmitting}
                        className={errors.contact_name ? 'error' : ''}
                        autoComplete="name"
                    />
                    {errors.contact_name && (
                        <span className="error-message">{errors.contact_name}</span>
                    )}
                </div>

                <div className="homa-form-group">
                    <label htmlFor="contact_info">
                        شماره موبایل <span className="required">*</span>
                    </label>
                    <input
                        type="tel"
                        id="contact_info"
                        value={formData.contact_info}
                        onChange={(e) => handleChange('contact_info', e.target.value)}
                        placeholder="09123456789"
                        disabled={isSubmitting}
                        className={errors.contact_info ? 'error' : ''}
                        autoComplete="tel"
                        dir="ltr"
                    />
                    {errors.contact_info && (
                        <span className="error-message">{errors.contact_info}</span>
                    )}
                </div>

                <div className="homa-form-group">
                    <label htmlFor="company_name">
                        نام شرکت (اختیاری)
                    </label>
                    <input
                        type="text"
                        id="company_name"
                        value={formData.company_name}
                        onChange={(e) => handleChange('company_name', e.target.value)}
                        placeholder="مثال: چاپ‌کو"
                        disabled={isSubmitting}
                        autoComplete="organization"
                    />
                </div>

                {errors.submit && (
                    <div className="error-message submit-error">{errors.submit}</div>
                )}

                <div className="homa-form-actions">
                    <button
                        type="submit"
                        className="homa-btn-primary"
                        disabled={isSubmitting}
                    >
                        {isSubmitting ? (
                            <>
                                <span className="spinner"></span>
                                در حال ارسال...
                            </>
                        ) : (
                            <>
                                ✓ ارسال اطلاعات
                            </>
                        )}
                    </button>

                    {onSkip && (
                        <button
                            type="button"
                            className="homa-btn-secondary"
                            onClick={onSkip}
                            disabled={isSubmitting}
                        >
                            فعلاً نه
                        </button>
                    )}
                </div>

                <div className="homa-form-note">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                        <path d="M8 1a7 7 0 100 14A7 7 0 008 1zm0 10.5a.75.75 0 110-1.5.75.75 0 010 1.5zm.75-3.25a.75.75 0 11-1.5 0V5a.75.75 0 011.5 0v3.25z"/>
                    </svg>
                    اطلاعات شما محفوظ و امن خواهد ماند
                </div>
            </form>
        </div>
    );
};

export default LeadCaptureForm;
