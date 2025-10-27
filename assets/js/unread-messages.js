// Unread Message Badge Manager
class UnreadMessageManager {
    constructor() {
        this.badgeElement = document.getElementById('unreadMessageBadge');
        this.pollInterval = 10000; // 10 seconds for more frequent updates
        this.isPolling = false;
        
        if (this.badgeElement) {
            this.init();
        }
    }
    
    init() {
        // Load initial count
        this.updateUnreadCount();
        
        // Start polling for updates
        this.startPolling();
        
        // Update count when page becomes visible (user switches tabs)
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.updateUnreadCount();
            }
        });
        
        // Update count when user sends a message (if on messages page)
        this.setupMessageListeners();
        
        // Update count when conversation is opened
        this.setupConversationListeners();
    }
    
    async updateUnreadCount() {
        try {
            const response = await fetch('get_unread_messages.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.setUnreadCount(data.unread_count, data.has_unread);
            } else {
                console.error('Error fetching unread count:', data.error);
            }
        } catch (error) {
            console.error('Error updating unread count:', error);
        }
    }
    
    setUnreadCount(count, hasUnread) {
        if (!this.badgeElement) return;
        
        const badge = this.badgeElement;
        
        if (hasUnread && count > 0) {
            badge.textContent = count > 99 ? '99+' : count.toString();
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }
    
    startPolling() {
        if (this.isPolling) return;
        
        this.isPolling = true;
        this.pollTimer = setInterval(() => {
            this.updateUnreadCount();
        }, this.pollInterval);
    }
    
    stopPolling() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
        this.isPolling = false;
    }
    
    // Method to manually refresh count (can be called from other scripts)
    refresh() {
        this.updateUnreadCount();
    }
    
    // Setup listeners for message sending to update count immediately
    setupMessageListeners() {
        // Listen for message sending events
        document.addEventListener('messageSent', () => {
            // Update count after a short delay to allow server processing
            setTimeout(() => {
                this.updateUnreadCount();
            }, 1000);
        });
        
        // Listen for message receiving events (if using WebSocket or similar)
        document.addEventListener('messageReceived', () => {
            this.updateUnreadCount();
        });
    }
    
    // Setup listeners for conversation opening
    setupConversationListeners() {
        // Listen for conversation opened events
        document.addEventListener('conversationOpened', () => {
            // Update count after conversation is opened and messages are marked as read
            setTimeout(() => {
                this.updateUnreadCount();
            }, 500);
        });
        
        // Listen for messages marked as read events
        document.addEventListener('messagesMarkedAsRead', () => {
            this.updateUnreadCount();
        });
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.unreadMessageManager = new UnreadMessageManager();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = UnreadMessageManager;
}
