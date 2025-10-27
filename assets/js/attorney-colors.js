/**
 * Attorney Color Coding System
 * Provides consistent color assignment for attorneys across all schedule views
 * 
 * CURRENT ATTORNEYS (as of 2025-09-09):
 * - Mar John Refrea (Admin) - Light Maroon
 * - Laica Castillo Refrea (Attorney) - Light Blue  
 * - Mario Delmo Refrea (Attorney) - Light Orange
 */

// Attorney color palette - consistent across all files
const ATTORNEY_COLORS = {
    // Current attorneys from database (EXACT NAMES)
    'Mar John Refrea': { bg: '#c92a2a', border: '#a61e4d', name: 'Light Maroon' },
    'Laica Castillo Refrea': { bg: '#74c0fc', border: '#4dabf7', name: 'Light Blue' },
    'Mario Delmo Refrea': { bg: '#ffd43b', border: '#fcc419', name: 'Light Orange' },
    
    // Additional colors for new attorneys
    'default1': { bg: '#ffd43b', border: '#fcc419', name: 'Light Orange' },
    'default2': { bg: '#da77f2', border: '#cc5de8', name: 'Light Violet' },
    'default3': { bg: '#ffa8a8', border: '#ff8787', name: 'Light Pink' },
    'default4': { bg: '#69db7c', border: '#51cf66', name: 'Bright Green' },
    'default5': { bg: '#4dabf7', border: '#339af0', name: 'Bright Blue' },
    'default6': { bg: '#e599f7', border: '#da77f2', name: 'Bright Violet' },
    'default7': { bg: '#ffb3bf', border: '#ffa8a8', name: 'Bright Pink' },
    'default8': { bg: '#96f2d7', border: '#69db7c', name: 'Mint Green' },
    'default9': { bg: '#a5d8ff', border: '#74c0fc', name: 'Sky Blue' },
    'default10': { bg: '#ffec99', border: '#ffd43b', name: 'Bright Yellow' }
};

// Admin color (consistent across all files)
const ADMIN_COLOR = { bg: '#c92a2a', border: '#a61e4d', name: 'Light Maroon' };

/**
 * Get attorney color based on attorney name
 * @param {string} attorneyName - The name of the attorney
 * @param {string} userType - The user type (admin, attorney, etc.)
 * @returns {Object} Color object with bg, border, and name properties
 */
function getAttorneyColor(attorneyName, userType = 'attorney') {
    // Check if it's an admin
    if (userType === 'admin') {
        return ADMIN_COLOR;
    }
    
    // Check if attorney has specific color assignment
    if (ATTORNEY_COLORS[attorneyName]) {
        return ATTORNEY_COLORS[attorneyName];
    }
    
    // For new attorneys, assign color based on name hash for consistency
    const defaultKeys = Object.keys(ATTORNEY_COLORS).filter(key => key.startsWith('default'));
    const hash = simpleHash(attorneyName);
    const colorIndex = hash % defaultKeys.length;
    const selectedKey = defaultKeys[colorIndex];
    
    return ATTORNEY_COLORS[selectedKey];
}

/**
 * Simple hash function for consistent color assignment
 * @param {string} str - String to hash
 * @returns {number} Hash value
 */
function simpleHash(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        const char = str.charCodeAt(i);
        hash = ((hash << 5) - hash) + char;
        hash = hash & hash; // Convert to 32-bit integer
    }
    return Math.abs(hash);
}

/**
 * Apply attorney colors to a calendar event element
 * @param {HTMLElement} element - The calendar event element
 * @param {string} attorneyName - The attorney name
 * @param {string} userType - The user type
 */
function applyAttorneyColors(element, attorneyName, userType = 'attorney') {
    const colors = getAttorneyColor(attorneyName, userType);
    
    element.style.backgroundColor = colors.bg;
    element.style.borderColor = colors.border;
    element.style.color = '#333'; // Dark text for readability
    element.style.fontWeight = '500';
    element.style.borderWidth = '2px';
    element.style.borderStyle = 'solid';
    
    console.log(`Applied ${colors.name} colors to ${attorneyName}:`, colors.bg);
}

/**
 * Get all attorney colors for debugging
 * @returns {Object} All attorney color assignments
 */
function getAllAttorneyColors() {
    return {
        ...ATTORNEY_COLORS,
        'Admin': ADMIN_COLOR
    };
}

// Export functions for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        getAttorneyColor,
        applyAttorneyColors,
        getAllAttorneyColors,
        ATTORNEY_COLORS,
        ADMIN_COLOR
    };
}
