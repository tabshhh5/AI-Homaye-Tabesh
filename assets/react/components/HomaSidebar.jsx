import React, { useState, useEffect, useRef } from 'react';
import { useHomaStore } from '../store/homaStore';
import { useHomaEvent, useHomaEmit, useSiteInputChanges } from '../homaReactBridge';
import MessageList from './MessageList';
import ChatInput from './ChatInput';
import SmartChips from './SmartChips';

/**
 * HomaSidebar Component
 * Main React component for the Homa chatbot sidebar
 * Integrated with Homa Event Bus (PR 6.5)
 */
const HomaSidebar = () => {
    const [isOpen, setIsOpen] = useState(false);
    const { messages, addMessage, userPersona, setUserPersona, setIsTyping } = useHomaStore();
    const messagesEndRef = useRef(null);
    const homaEmit = useHomaEmit();

    useEffect(() => {
        // Listen for sidebar toggle events
        const handleToggle = (event) => {
            setIsOpen(event.detail.isOpen);
        };

        document.addEventListener('homa:toggle-sidebar', handleToggle);

        // Load chat history from localStorage
        loadChatHistory();

        // Notify that React is ready
        if (window.Homa && window.Homa.emit) {
            window.Homa.emit('react:ready', { timestamp: Date.now() });
        }

        return () => {
            document.removeEventListener('homa:toggle-sidebar', handleToggle);
        };
    }, []);

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

    useEffect(() => {
        // Save chat history whenever messages or persona change
        if (messages.length > 0) {
            saveChatHistory();
        }
    }, [messages, userPersona]);

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

        // Call AI endpoint
        try {
            // Check if nonce is available
            if (!window.homayeParallelUIConfig?.nonce) {
                throw new Error('امنیت: نشست شما منقضی شده است. لطفاً صفحه را رفرش کنید.');
            }

            // Get current page context from Homa state
            const homaState = window.Homa?.getState() || {};
            const formData = getFormData();

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
                        pageMap: homaState.pageMap
                    }
                })
            });

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
            }
        } catch (error) {
            console.error('Failed to send message:', error);
            homaEmit('ai:processing', { processing: false });
            
            const errorMessage = {
                id: Date.now() + 1,
                type: 'assistant',
                content: 'متأسفم، خطایی رخ داد. لطفاً دوباره امتحان کنید.',
                timestamp: new Date()
            };
            addMessage(errorMessage);
        }
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
                        document.body.classList.remove('homa-open');
                        setIsOpen(false);
                        homaEmit('sidebar:close_requested', {});
                    }}
                    aria-label="بستن"
                >
                    ✕
                </button>
            </div>

            <div className="homa-sidebar-content">
                <MessageList messages={messages} />
                <div ref={messagesEndRef} />
            </div>

            <SmartChips 
                persona={userPersona}
                onChipClick={handleChipClick}
            />

            <ChatInput onSendMessage={handleSendMessage} />
        </div>
    );
};

export default HomaSidebar;
