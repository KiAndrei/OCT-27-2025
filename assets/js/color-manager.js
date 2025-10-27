/**
 * JavaScript Color Management System
 * Handles client-side color application and consistency
 */

class ColorManager {
    constructor() {
        this.colorConfig = null;
        this.colorMap = {};
        this.init();
    }
    
    init() {
        this.loadColorConfig();
    }
    
    /**
     * Load color configuration from server
     */
    async loadColorConfig() {
        try {
            const response = await fetch('get_color_config.php');
            const config = await response.json();
            
            this.colorConfig = config;
            this.colorMap = config.colorMap || {};
            
            console.log('Color configuration loaded:', this.colorConfig);
            this.applyColorsToExistingElements();
        } catch (error) {
            console.error('Error loading color configuration:', error);
        }
    }
    
    /**
     * Get colors for a specific user
     */
    getUserColors(userId) {
        return this.colorMap[userId] || {
            scheduleCardColor: '#D3D3D3',
            calendarEventColor: '#696969',
            colorName: 'Default Gray'
        };
    }
    
    /**
     * Apply colors to schedule card
     */
    applyScheduleCardColors(cardElement, userId) {
        if (!cardElement || !userId) return;
        
        const colors = this.getUserColors(userId);
        
        // Set data attribute for CSS targeting
        cardElement.setAttribute('data-user-id', userId);
        
        // Apply colors directly as fallback
        cardElement.style.borderLeftColor = colors.scheduleCardColor;
        cardElement.style.background = `linear-gradient(135deg, ${this.hexToRgba(colors.scheduleCardColor, 0.08)} 0%, ${this.hexToRgba(colors.scheduleCardColor, 0.15)} 100%)`;
        
        console.log(`Applied schedule card colors for user ${userId}:`, colors.colorName);
    }
    
    /**
     * Apply colors to calendar event
     */
    applyCalendarEventColors(eventElement, userId) {
        if (!eventElement || !userId) return;
        
        const colors = this.getUserColors(userId);
        
        // Set data attribute for CSS targeting
        eventElement.setAttribute('data-user-id', userId);
        
        // Apply colors directly as fallback
        eventElement.style.backgroundColor = colors.calendarEventColor;
        eventElement.style.borderColor = colors.calendarEventColor;
        
        console.log(`Applied calendar event colors for user ${userId}:`, colors.colorName);
    }
    
    /**
     * Apply colors to all existing elements
     */
    applyColorsToExistingElements() {
        // Apply to schedule cards
        document.querySelectorAll('.event-card').forEach(card => {
            const userId = card.getAttribute('data-attorney-id') || card.getAttribute('data-user-id');
            if (userId) {
                this.applyScheduleCardColors(card, userId);
            }
        });
        
        // Apply to calendar events
        document.querySelectorAll('.fc-event').forEach(event => {
            const userId = event.getAttribute('data-user-id');
            if (userId) {
                this.applyCalendarEventColors(event, userId);
            }
        });
    }
    
    /**
     * Update event card colors when status changes
     */
    updateEventCardColors(cardElement, userId, newStatus) {
        if (!cardElement || !userId) return;
        
        // Remove previous status classes
        cardElement.classList.remove('status-scheduled', 'status-completed', 'status-rescheduled', 'status-cancelled');
        
        // Add new status class
        cardElement.classList.add(`status-${newStatus.toLowerCase()}`);
        
        // Reapply colors
        this.applyScheduleCardColors(cardElement, userId);
    }
    
    /**
     * Convert hex color to rgba
     */
    hexToRgba(hex, alpha = 1) {
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }
    
    /**
     * Generate CSS for dynamic color coding
     */
    generateDynamicCSS() {
        if (!this.colorConfig) return '';
        
        let css = '';
        
        this.colorConfig.users.forEach(user => {
            const userId = user.id;
            const scheduleColor = user.scheduleCardColor;
            const calendarColor = user.calendarEventColor;
            
            css += `
            .event-card[data-user-id="${userId}"] {
                border-left: 4px solid ${scheduleColor} !important;
                background: linear-gradient(135deg, ${this.hexToRgba(scheduleColor, 0.08)} 0%, ${this.hexToRgba(scheduleColor, 0.15)} 100%) !important;
            }
            
            .fc-event[data-user-id="${userId}"] {
                background-color: ${calendarColor} !important;
                border-color: ${calendarColor} !important;
            }
            `;
        });
        
        return css;
    }
    
    /**
     * Inject dynamic CSS into page
     */
    injectDynamicCSS() {
        const css = this.generateDynamicCSS();
        if (!css) return;
        
        // Remove existing dynamic color styles
        const existingStyle = document.getElementById('dynamic-color-styles');
        if (existingStyle) {
            existingStyle.remove();
        }
        
        // Create new style element
        const style = document.createElement('style');
        style.id = 'dynamic-color-styles';
        style.textContent = css;
        document.head.appendChild(style);
    }
    
    /**
     * Refresh color configuration
     */
    async refreshColors() {
        await this.loadColorConfig();
        this.injectDynamicCSS();
        this.applyColorsToExistingElements();
    }
    
    /**
     * Get color statistics
     */
    getColorStats() {
        if (!this.colorConfig) return null;
        
        const stats = {
            totalUsers: this.colorConfig.users.length,
            admins: this.colorConfig.users.filter(u => u.type === 'admin').length,
            attorneys: this.colorConfig.users.filter(u => u.type === 'attorney').length,
            colors: this.colorConfig.users.map(u => u.colorName)
        };
        
        return stats;
    }
}

// Global color manager instance
let colorManager = null;

// Initialize color manager when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    colorManager = new ColorManager();
    
    // Inject dynamic CSS
    setTimeout(() => {
        colorManager.injectDynamicCSS();
    }, 100);
});

// Export for use in other scripts
window.ColorManager = ColorManager;
window.colorManager = colorManager;
