import { create } from 'zustand';

/**
 * Zustand store for Homa chat state management
 */
export const useHomaStore = create((set) => ({
    messages: [],
    userPersona: null,
    isTyping: false,
    sidebarOpen: false,
    
    // PR11: Lead Capture & OTP State
    leadData: null,
    otpState: {
        stage: 'idle', // 'idle' | 'phone' | 'otp' | 'verified'
        phoneNumber: null,
        expiresIn: 120,
    },
    isAuthenticated: false,
    currentUser: null,

    addMessage: (message) => set((state) => ({
        messages: [...state.messages, message]
    })),

    clearMessages: () => set({ messages: [] }),

    setUserPersona: (persona) => set({ userPersona: persona }),

    setIsTyping: (isTyping) => set({ isTyping }),

    setSidebarOpen: (isOpen) => set({ sidebarOpen: isOpen }),

    updateLastMessage: (updates) => set((state) => {
        const messages = [...state.messages];
        if (messages.length > 0) {
            messages[messages.length - 1] = {
                ...messages[messages.length - 1],
                ...updates
            };
        }
        return { messages };
    }),

    // Lead management actions
    setLeadData: (data) => set({ leadData: data }),
    
    updateLeadData: (updates) => set((state) => ({
        leadData: { ...state.leadData, ...updates }
    })),

    // OTP management actions
    setOTPStage: (stage) => set((state) => ({
        otpState: { ...state.otpState, stage }
    })),

    setPhoneNumber: (phone) => set((state) => ({
        otpState: { ...state.otpState, phoneNumber: phone }
    })),

    setOTPExpiry: (seconds) => set((state) => ({
        otpState: { ...state.otpState, expiresIn: seconds }
    })),

    // Authentication actions
    setAuthenticated: (isAuth) => set({ isAuthenticated: isAuth }),

    setCurrentUser: (user) => set({ 
        currentUser: user,
        isAuthenticated: !!user 
    }),

    // Reset OTP state
    resetOTP: () => set({
        otpState: {
            stage: 'idle',
            phoneNumber: null,
            expiresIn: 120,
        }
    }),
}));
