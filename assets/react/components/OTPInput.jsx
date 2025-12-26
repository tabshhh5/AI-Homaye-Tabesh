import React, { useState, useRef, useEffect } from 'react';
import './OTPInput.css';

/**
 * کامپوننت ورود کد تایید OTP
 * 
 * این کامپوننت جریان دو مرحله‌ای احراز هویت را مدیریت می‌کند:
 * 1. دریافت شماره موبایل
 * 2. تایید کد OTP
 */
const OTPInput = ({ onComplete, onResend, phoneNumber, expiresIn = 120 }) => {
    const [otp, setOtp] = useState(['', '', '', '', '', '']);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState('');
    const [timeLeft, setTimeLeft] = useState(expiresIn);
    const inputRefs = useRef([]);

    // شمارش معکوس برای انقضای کد
    useEffect(() => {
        if (timeLeft <= 0) return;

        const timer = setInterval(() => {
            setTimeLeft(prev => {
                if (prev <= 1) {
                    clearInterval(timer);
                    return 0;
                }
                return prev - 1;
            });
        }, 1000);

        return () => clearInterval(timer);
    }, [timeLeft]);

    // فوکوس خودکار روی اولین input
    useEffect(() => {
        if (inputRefs.current[0]) {
            inputRefs.current[0].focus();
        }
    }, []);

    const handleChange = (index, value) => {
        // فقط اعداد قبول شوند
        if (!/^\d*$/.test(value)) return;

        const newOtp = [...otp];
        newOtp[index] = value.slice(-1); // فقط آخرین رقم
        setOtp(newOtp);

        // پاک کردن خطا
        if (error) setError('');

        // حرکت به input بعدی
        if (value && index < 5) {
            inputRefs.current[index + 1]?.focus();
        }

        // اگر همه خانه‌ها پر شد، ارسال خودکار
        if (index === 5 && value) {
            const fullOtp = [...newOtp];
            fullOtp[5] = value;
            handleSubmit(fullOtp.join(''));
        }
    };

    const handleKeyDown = (index, e) => {
        // Backspace: حرکت به input قبلی
        if (e.key === 'Backspace' && !otp[index] && index > 0) {
            inputRefs.current[index - 1]?.focus();
        }

        // Arrow keys: ناوبری بین input‌ها
        if (e.key === 'ArrowLeft' && index > 0) {
            inputRefs.current[index - 1]?.focus();
        }
        if (e.key === 'ArrowRight' && index < 5) {
            inputRefs.current[index + 1]?.focus();
        }
    };

    const handlePaste = (e) => {
        e.preventDefault();
        const pastedData = e.clipboardData.getData('text').slice(0, 6);
        
        if (!/^\d+$/.test(pastedData)) return;

        const newOtp = [...otp];
        pastedData.split('').forEach((char, i) => {
            if (i < 6) newOtp[i] = char;
        });
        setOtp(newOtp);

        // فوکوس روی آخرین خانه
        const lastIndex = Math.min(pastedData.length - 1, 5);
        inputRefs.current[lastIndex]?.focus();

        // اگر کد کامل شد، ارسال خودکار
        if (pastedData.length === 6) {
            handleSubmit(pastedData);
        }
    };

    const handleSubmit = async (code) => {
        const otpCode = code || otp.join('');

        if (otpCode.length !== 6) {
            setError('لطفاً کد 6 رقمی را وارد کنید');
            return;
        }

        if (timeLeft <= 0) {
            setError('کد منقضی شده است. لطفاً کد جدید درخواست کنید');
            return;
        }

        setIsSubmitting(true);
        setError('');

        try {
            await onComplete(otpCode);
        } catch (err) {
            setError(err.message || 'کد نامعتبر است');
            setOtp(['', '', '', '', '', '']);
            inputRefs.current[0]?.focus();
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleResend = async () => {
        setOtp(['', '', '', '', '', '']);
        setError('');
        setTimeLeft(expiresIn);
        
        try {
            await onResend();
            inputRefs.current[0]?.focus();
        } catch (err) {
            setError(err.message || 'خطا در ارسال مجدد کد');
        }
    };

    const formatTime = (seconds) => {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    };

    return (
        <div className="homa-otp-input">
            <div className="homa-otp-header">
                <div className="homa-otp-icon">🔐</div>
                <h3>کد تایید</h3>
                <p>
                    کد 6 رقمی ارسال شده به شماره<br/>
                    <strong dir="ltr">{phoneNumber}</strong>
                </p>
            </div>

            <div className="homa-otp-content">
                <div className="homa-otp-inputs" onPaste={handlePaste}>
                    {otp.map((digit, index) => (
                        <input
                            key={index}
                            ref={(el) => (inputRefs.current[index] = el)}
                            type="text"
                            inputMode="numeric"
                            pattern="\d*"
                            maxLength={1}
                            value={digit}
                            onChange={(e) => handleChange(index, e.target.value)}
                            onKeyDown={(e) => handleKeyDown(index, e)}
                            disabled={isSubmitting || timeLeft <= 0}
                            className={error ? 'error' : ''}
                            autoComplete="one-time-code"
                        />
                    ))}
                </div>

                {error && (
                    <div className="homa-otp-error">
                        ⚠️ {error}
                    </div>
                )}

                <div className="homa-otp-timer">
                    {timeLeft > 0 ? (
                        <>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                <path d="M8 1a7 7 0 100 14A7 7 0 008 1zm1 8.5V5a1 1 0 10-2 0v4.5a1 1 0 001 1h3a1 1 0 100-2H9z"/>
                            </svg>
                            <span>زمان باقی‌مانده: {formatTime(timeLeft)}</span>
                        </>
                    ) : (
                        <span className="expired">⏰ کد منقضی شده است</span>
                    )}
                </div>

                {isSubmitting && (
                    <div className="homa-otp-loading">
                        <div className="spinner"></div>
                        <span>در حال بررسی...</span>
                    </div>
                )}

                <button
                    type="button"
                    className="homa-otp-resend"
                    onClick={handleResend}
                    disabled={timeLeft > 0 || isSubmitting}
                >
                    {timeLeft > 0 ? (
                        `ارسال مجدد (${formatTime(timeLeft)})`
                    ) : (
                        '🔄 ارسال مجدد کد'
                    )}
                </button>
            </div>
        </div>
    );
};

/**
 * کامپوننت دریافت شماره موبایل (Stage 1)
 */
export const PhoneNumberInput = ({ onSubmit, initialPhone = '' }) => {
    const [phone, setPhone] = useState(initialPhone);
    const [error, setError] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const validatePhone = (phoneNumber) => {
        const phoneRegex = /^(?:\+98|98|0)?9\d{9}$/;
        return phoneRegex.test(phoneNumber.replace(/[^0-9+]/g, ''));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (!validatePhone(phone)) {
            setError('شماره موبایل نامعتبر است');
            return;
        }

        setIsSubmitting(true);
        setError('');

        try {
            await onSubmit(phone);
        } catch (err) {
            setError(err.message || 'خطا در ارسال کد. لطفاً دوباره تلاش کنید');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <div className="homa-phone-input">
            <div className="homa-phone-header">
                <div className="homa-phone-icon">📱</div>
                <h3>ورود یا ثبت‌نام</h3>
                <p>برای ادامه، شماره موبایل خود را وارد کنید</p>
            </div>

            <form onSubmit={handleSubmit} className="homa-phone-content">
                <div className="homa-phone-group">
                    <input
                        type="tel"
                        value={phone}
                        onChange={(e) => {
                            setPhone(e.target.value);
                            if (error) setError('');
                        }}
                        placeholder="09123456789"
                        disabled={isSubmitting}
                        className={error ? 'error' : ''}
                        autoComplete="tel"
                        dir="ltr"
                        autoFocus
                    />
                    {error && (
                        <span className="error-message">{error}</span>
                    )}
                </div>

                <button
                    type="submit"
                    className="homa-phone-submit"
                    disabled={isSubmitting || !phone}
                >
                    {isSubmitting ? (
                        <>
                            <span className="spinner"></span>
                            در حال ارسال...
                        </>
                    ) : (
                        '→ دریافت کد تایید'
                    )}
                </button>

                <div className="homa-phone-note">
                    با ورود یا ثبت‌نام، شما <a href="/terms">قوانین</a> را می‌پذیرید
                </div>
            </form>
        </div>
    );
};

export default OTPInput;
