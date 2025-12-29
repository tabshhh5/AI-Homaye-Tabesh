import React, { useState, useEffect, useRef } from 'react';
import { useHomaStore } from '../store/homaStore';
import { useHomaEvent, useHomaEmit, useSiteInputChanges } from '../homaReactBridge';
import MessageList from './MessageList';
import ChatInput from './ChatInput';
import SmartChips from './SmartChips';
import ExploreWidget from './ExploreWidget';
import AdminTools from './AdminTools';
import OrderTracker from './OrderTracker';
import SecurityWarning from './SecurityWarning';
import LeadGenerator from './LeadGenerator';

/**
 * HomaSidebar Component
 * Main React component for the Homa chatbot sidebar
 * Integrated with Homa Event Bus (PR 6.5)
 * Role-based UI (PR15)
 */
const HomaSidebar = () => {
    const [isOpen, setIsOpen] = useState(false);
    const [userRoleContext, setUserRoleContext] = useState(null);
    const [roleContextLoading, setRoleContextLoading] = useState(true);
    const { messages, addMessage, userPersona, setUserPersona, setIsTyping } = useHomaStore();
    const messagesEndRef = useRef(null);
    const homaEmit = useHomaEmit();

    useEffect(() => {
        // Load chat history from database first
        loadChatHistoryFromDatabase();
        
        // Fetch user role context (PR15)
        fetchUserRoleContext();

        // Notify that React is ready
        if (window.Homa && window.Homa.emit) {
            window.Homa.emit('react:ready', { timestamp: Date.now() });
        }
    }, []);

    // Listen for sidebar state changes via Homa Event Bus
    useHomaEvent('sidebar:opened', () => {
        setIsOpen(true);
    });

    useHomaEvent('sidebar:closed', () => {
        setIsOpen(false);
    });

    // Listen for site input changes via event bus
    useSiteInputChanges((data) => {
        console.log('[Homa React] Site input changed:', data);
        
        // Show contextual message when user changes important fields
        if (data.meaning && data.value) {
            const notification = {
                id: Date.now(),
                type: 'system',
                content: `در حال تحلیل ${data.meaning}: ${data.value}...`,
                timestamp: new Date()
            };
            // Could add system messages here if needed
        }
    });

    // Listen for AI processing state
    useHomaEvent('ai:processing', (data) => {
        setIsTyping(data.processing || false);
    });

    // Listen for AI responses
    useHomaEvent('ai:response_received', (response) => {
        if (response && response.text) {
            const aiMessage = {
                id: Date.now(),
                type: 'assistant',
                content: response.text,
                timestamp: new Date(),
                actions: response.actions || []
            };
            addMessage(aiMessage);
        }
    });

    useEffect(() => {
        // Auto-scroll to bottom when new messages arrive
        scrollToBottom();
    }, [messages]);

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    /**
     * Load chat history from database
     */
    const loadChatHistoryFromDatabase = async () => {
        const MAX_RETRIES = 2;
        let retryCount = 0;
        
        const attemptLoad = async () => {
            try {
                const response = await fetch('/wp-json/homaye-tabesh/v1/chat/memory', {
                    headers: {
                        'X-WP-Nonce': window.homayeParallelUIConfig?.nonce || ''
                    }
                });

                // Don't retry on auth errors
                if (response.status === 401) {
                    console.warn('[Homa] Session expired. Chat history not loaded.');
                    return;
                }

                // Retry on server errors
                if (response.status >= 500 && retryCount < MAX_RETRIES) {
                    retryCount++;
                    console.log(`[Homa] Retrying chat history load (attempt ${retryCount}/${MAX_RETRIES})`);
                    await new Promise(resolve => setTimeout(resolve, 1000 * retryCount));
                    return attemptLoad();
                }

                if (response.ok) {
                    const data = await response.json();
                    if (data.success && data.messages && data.messages.length > 0) {
                        // Convert database messages to UI format
                        const loadedMessages = data.messages.map((msg, index) => ({
                            id: Date.now() + index,
                            type: msg.type,
                            content: msg.content,
                            timestamp: new Date(msg.timestamp),
                            actions: msg.metadata?.actions || []
                        }));
                        
                        // Load messages into store
                        loadedMessages.forEach(msg => addMessage(msg));
                        
                        console.log('[Homa] Loaded', loadedMessages.length, 'messages from database');
                    }
                }
            } catch (error) {
                console.error('[Homa] Failed to load chat history from database:', error);
                // Don't retry on network errors - fail silently
            }
        };
        
        await attemptLoad();
    };

    const loadChatHistory = () => {
        try {
            const history = localStorage.getItem('homa_chat_history');
            if (history) {
                const parsed = JSON.parse(history);
                // Load messages into store
                if (parsed.messages && Array.isArray(parsed.messages)) {
                    parsed.messages.forEach(msg => addMessage(msg));
                }
                if (parsed.persona) {
                    setUserPersona(parsed.persona);
                }
            }
        } catch (error) {
            console.error('Failed to load chat history:', error);
        }
    };

    const saveChatHistory = () => {
        try {
            const history = {
                messages: messages,
                persona: userPersona,
                timestamp: Date.now()
            };
            localStorage.setItem('homa_chat_history', JSON.stringify(history));
        } catch (error) {
            console.error('Failed to save chat history:', error);
        }
    };

    /**
     * Fetch user role context from server (PR15)
     */
    const fetchUserRoleContext = async () => {
        const MAX_RETRIES = 2;
        let retryCount = 0;
        
        const attemptFetch = async () => {
            try {
                const response = await fetch('/wp-json/homaye-tabesh/v1/capabilities/context', {
                    headers: {
                        'X-WP-Nonce': window.homayeParallelUIConfig?.nonce || ''
                    }
                });

                // Don't retry on auth errors
                if (response.status === 401) {
                    console.warn('[Homa] Session expired. Using guest context.');
                    setRoleContextLoading(false);
                    return;
                }

                // Retry on server errors
                if (response.status >= 500 && retryCount < MAX_RETRIES) {
                    retryCount++;
                    console.log(`[Homa] Retrying role context fetch (attempt ${retryCount}/${MAX_RETRIES})`);
                    await new Promise(resolve => setTimeout(resolve, 1000 * retryCount));
                    return attemptFetch();
                }

                if (response.ok) {
                    const data = await response.json();
                    if (data.success) {
                        setUserRoleContext(data.context);
                        
                        // Only add welcome message if chat is empty (no history)
                        // This prevents greeting from appearing on every page load
                        if (messages.length === 0 && data.welcome_message) {
                            const welcomeMessage = {
                                id: Date.now(),
                                type: 'assistant',
                                content: data.welcome_message,
                                timestamp: new Date(),
                                actions: data.suggested_actions || []
                            };
                            addMessage(welcomeMessage);
                            
                            // Save welcome message to database
                            saveChatMessageToDatabase('assistant', data.welcome_message, {
                                actions: data.suggested_actions || []
                            });
                        }
                    }
                }
            } catch (error) {
                console.error('Error fetching user role context:', error);
                // Fail silently
            } finally {
                setRoleContextLoading(false);
            }
        };
        
        await attemptFetch();
    };

    useEffect(() => {
        // Save chat history whenever messages or persona change
        if (messages.length > 0) {
            saveChatHistory();
        }
    }, [messages, userPersona]);

    /**
     * Save chat message to database
     */
    const saveChatMessageToDatabase = async (messageType, messageContent, metadata = {}) => {
        try {
            await fetch('/wp-json/homaye-tabesh/v1/chat/memory', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.homayeParallelUIConfig?.nonce || ''
                },
                body: JSON.stringify({
                    message_type: messageType,
                    message_content: messageContent,
                    ai_metadata: metadata
                })
            });
        } catch (error) {
            console.error('[Homa] Failed to save message to database:', error);
        }
    };

    const handleSendMessage = async (message) => {
        // Add user message
        const userMessage = {
            id: Date.now(),
            type: 'user',
            content: message,
            timestamp: new Date()
        };
        addMessage(userMessage);

        // Emit user message event
        homaEmit('chat:user_message', { message });

        // Set AI processing state
        homaEmit('ai:processing', { processing: true });

        // Maximum retry attempts
        const MAX_RETRIES = 2;
        let retryCount = 0;

        const attemptSendMessage = async () => {
            try {
                // Check if nonce is available
                if (!window.homayeParallelUIConfig?.nonce) {
                    throw new Error('امنیت: نشست شما منقضی شده است. لطفاً صفحه را رفرش کنید.');
                }

                // Get current page context from Homa state
                const homaState = window.Homa?.getState() || {};
                const formData = getFormData();

                // Sanitize pageMap to remove DOM element references (prevents circular JSON error)
                const sanitizedPageMap = (homaState.pageMap || []).map(item => {
                    const { element, rect, ...safeData } = item;
                    return {
                        ...safeData,
                        // Include basic rect info without the element reference
                        rectInfo: rect ? {
                            width: rect.width || 0,
                            height: rect.height || 0,
                            top: rect.top || 0,
                            left: rect.left || 0
                        } : null
                    };
                });

                const response = await fetch('/wp-json/homaye/v1/ai/chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.homayeParallelUIConfig.nonce
                    },
                    body: JSON.stringify({
                        message: message,
                        persona: userPersona,
                        context: {
                            page: window.location.pathname,
                            formData: formData,
                            currentInput: homaState.currentUserInput,
                            pageMap: sanitizedPageMap
                        }
                    })
                });

                // Handle authentication errors
                if (response.status === 401) {
                    throw new Error('نشست شما منقضی شده است. لطفاً صفحه را رفرش کنید.');
                }

                // Handle server errors
                if (response.status >= 500) {
                    throw new Error('خطای سرور. لطفاً بعداً امتحان کنید.');
                }

                const data = await response.json();
                
                // Stop processing indicator
                homaEmit('ai:processing', { processing: false });
                
                if (data.success) {
                    // Add AI response with streaming effect
                    const aiMessage = {
                        id: Date.now() + 1,
                        type: 'assistant',
                        content: data.response,
                        timestamp: new Date(),
                        actions: data.actions || []
                    };
                    addMessage(aiMessage);

                    // Emit AI response event for command interpreter
                    homaEmit('ai:response_received', {
                        text: data.response,
                        actions: data.actions,
                        commands: data.commands
                    });

                    // Execute any UI actions via event bus
                    if (data.actions && Array.isArray(data.actions)) {
                        data.actions.forEach(action => {
                            homaEmit('ai:command', action);
                        });
                    }

                    // Execute any commands
                    if (data.commands && Array.isArray(data.commands)) {
                        data.commands.forEach(command => {
                            homaEmit('ai:command', command);
                        });
                    }
                } else {
                    throw new Error(data.message || 'خطایی رخ داد');
                }
            } catch (error) {
                console.error('Failed to send message:', error);
                
                // Check if we should retry based on error type (not message content)
                const isRetryableError = error.message && (
                    error.message.includes('Failed to fetch') ||
                    error.message.includes('NetworkError') ||
                    error.message.includes('خطای سرور')
                );
                
                if (retryCount < MAX_RETRIES && isRetryableError) {
                    retryCount++;
                    console.log(`[Homa] Retrying message send (attempt ${retryCount}/${MAX_RETRIES})`);
                    
                    // Wait before retry with exponential backoff
                    await new Promise(resolve => setTimeout(resolve, 1000 * retryCount));
                    return attemptSendMessage();
                }
                
                // Stop processing indicator
                homaEmit('ai:processing', { processing: false });
                
                // Show appropriate error message
                const errorMessage = {
                    id: Date.now() + 1,
                    type: 'assistant',
                    content: error.message || 'متأسفم، خطایی رخ داد. لطفاً دوباره امتحان کنید.',
                    timestamp: new Date()
                };
                addMessage(errorMessage);
            }
        };

        // Start the attempt
        await attemptSendMessage();
    };

    const getFormData = () => {
        // Collect current form data from the site
        const formData = {};
        const inputs = document.querySelectorAll('#homa-site-view input, #homa-site-view select, #homa-site-view textarea');
        inputs.forEach(input => {
            if (input.name) {
                formData[input.name] = input.value;
            }
        });
        return formData;
    };

    const handleChipClick = (chipAction) => {
        if (typeof chipAction === 'string') {
            handleSendMessage(chipAction);
        } else if (chipAction.message) {
            handleSendMessage(chipAction.message);
        }
    };

    /**
     * Render role-based tools component (PR15)
     */
    const renderRoleBasedTools = () => {
        if (roleContextLoading) {
            return (
                <div className="homa-role-loading">
                    <span className="homa-spinner"></span>
                    <p>در حال بارگذاری...</p>
                </div>
            );
        }

        if (!userRoleContext) {
            return null;
        }

        const role = userRoleContext.role;

        switch (role) {
            case 'admin':
                return <AdminTools userContext={userRoleContext} />;
            case 'customer':
                return <OrderTracker userContext={userRoleContext} />;
            case 'intruder':
                return <SecurityWarning detectionReason={userRoleContext.detection_reason} />;
            case 'guest':
            default:
                return <LeadGenerator userContext={userRoleContext} />;
        }
    };

    return (
        <div className={`homa-sidebar-container ${isOpen ? 'open' : ''}`}>
            <div className="homa-sidebar-header">
                <div className="homa-avatar">
                    <span className="homa-avatar-icon">🤖</span>
                </div>
                <div className="homa-header-text">
                    <h3>همای تابش</h3>
                    <p>دستیار هوشمند چاپ</p>
                </div>
                <button 
                    className="homa-close-btn"
                    onClick={() => {
                        // Use orchestrator to close sidebar properly
                        if (window.HomaOrchestrator) {
                            window.HomaOrchestrator.closeSidebar();
                        } else {
                            // Fallback
                            document.body.classList.remove('homa-open');
                            setIsOpen(false);
                            homaEmit('sidebar:close_requested', {});
                        }
                    }}
                    aria-label="بستن"
                >
                    ✕
                </button>
            </div>

            {/* Role-based tools section (PR15) */}
            {userRoleContext && userRoleContext.role !== 'intruder' && (
                <div className="homa-role-tools-section">
                    {renderRoleBasedTools()}
                </div>
            )}

            {/* Show security warning for intruders instead of chat */}
            {userRoleContext && userRoleContext.role === 'intruder' ? (
                <div className="homa-sidebar-content intruder-blocked">
                    {renderRoleBasedTools()}
                </div>
            ) : (
                <>
                    <div className="homa-sidebar-content">
                        <MessageList messages={messages} />
                        <div ref={messagesEndRef} />
                        
                        {/* Explore Widget - Shows when chat is empty or minimal */}
                        {messages.length <= 2 && <ExploreWidget />}
                    </div>

                    <SmartChips 
                        persona={userPersona}
                        onChipClick={handleChipClick}
                    />

                    <ChatInput onSendMessage={handleSendMessage} />
                </>
            )}
        </div>
    );
};

export default HomaSidebar;
