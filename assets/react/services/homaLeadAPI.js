/**
 * Lead & OTP API Service
 * 
 * این فایل تمام فراخوانی‌های API مربوط به Lead Conversion و OTP را مدیریت می‌کند
 */

class HomaLeadAPI {
    constructor() {
        this.baseUrl = window.homaConfig?.restUrl || '/wp-json/homa/v1';
        this.nonce = window.homaConfig?.nonce || '';
        this.maxRetries = 2;
    }

    /**
     * Generic fetch with retry logic
     */
    async fetchWithRetry(url, options = {}, retries = 0) {
        try {
            const response = await fetch(url, options);

            // Don't retry on authentication errors
            if (response.status === 401) {
                throw new Error('نشست منقضی شده است. لطفاً صفحه را رفرش کنید.');
            }

            // Retry on server errors if retries remaining
            if (response.status >= 500 && retries < this.maxRetries) {
                console.log(`[HomaLeadAPI] Retrying request (attempt ${retries + 1}/${this.maxRetries})`);
                await new Promise(resolve => setTimeout(resolve, 1000 * (retries + 1)));
                return this.fetchWithRetry(url, options, retries + 1);
            }

            return response;
        } catch (error) {
            // Don't retry on network errors
            throw error;
        }
    }

    /**
     * ارسال کد OTP
     */
    async sendOTP(phoneNumber, sessionToken = null) {
        try {
            const response = await this.fetchWithRetry(`${this.baseUrl}/otp/send`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce,
                },
                body: JSON.stringify({
                    phone_number: phoneNumber,
                    session_token: sessionToken,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'خطا در ارسال کد');
            }

            return data;
        } catch (error) {
            console.error('OTP Send Error:', error);
            throw error;
        }
    }

    /**
     * تایید کد OTP و ثبت‌نام/لاگین
     */
    async verifyOTP(phoneNumber, otpCode, userData = {}) {
        try {
            const response = await this.fetchWithRetry(`${this.baseUrl}/otp/verify`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce,
                },
                body: JSON.stringify({
                    phone_number: phoneNumber,
                    otp_code: otpCode,
                    user_data: userData,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'کد نامعتبر است');
            }

            return data;
        } catch (error) {
            console.error('OTP Verify Error:', error);
            throw error;
        }
    }

    /**
     * ایجاد لید جدید
     */
    async createLead(leadData) {
        try {
            const response = await this.fetchWithRetry(`${this.baseUrl}/leads`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce,
                },
                body: JSON.stringify(leadData),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'خطا در ثبت لید');
            }

            return data;
        } catch (error) {
            console.error('Create Lead Error:', error);
            throw error;
        }
    }

    /**
     * محاسبه امتیاز لید
     */
    async calculateLeadScore(params) {
        try {
            const response = await this.fetchWithRetry(`${this.baseUrl}/leads/calculate-score`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce,
                },
                body: JSON.stringify(params),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'خطا در محاسبه امتیاز');
            }

            return data;
        } catch (error) {
            console.error('Calculate Score Error:', error);
            throw error;
        }
    }

    /**
     * ایجاد سفارش پیش‌نویس
     */
    async createDraftOrder(leadId, products = []) {
        try {
            const response = await this.fetchWithRetry(`${this.baseUrl}/leads/${leadId}/draft-order`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce,
                },
                body: JSON.stringify({ products }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'خطا در ایجاد سفارش');
            }

            return data;
        } catch (error) {
            console.error('Create Draft Order Error:', error);
            throw error;
        }
    }

    /**
     * دریافت اطلاعات لید
     */
    async getLead(leadId) {
        try {
            const response = await this.fetchWithRetry(`${this.baseUrl}/leads/${leadId}`, {
                headers: {
                    'X-WP-Nonce': this.nonce,
                },
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'خطا در دریافت اطلاعات');
            }

            return data;
        } catch (error) {
            console.error('Get Lead Error:', error);
            throw error;
        }
    }

    /**
     * به‌روزرسانی لید
     */
    async updateLead(leadId, updates) {
        try {
            const response = await this.fetchWithRetry(`${this.baseUrl}/leads/${leadId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce,
                },
                body: JSON.stringify(updates),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'خطا در به‌روزرسانی');
            }

            return data;
        } catch (error) {
            console.error('Update Lead Error:', error);
            throw error;
        }
    }
}

// Export singleton instance
export const homaLeadAPI = new HomaLeadAPI();

// Export class for testing
export default HomaLeadAPI;
