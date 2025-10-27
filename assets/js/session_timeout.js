// Session Timeout Manager
class SessionTimeoutManager {
    constructor() {
        this.warningTime = 5 * 60 * 1000; // 5 minutes in milliseconds
        this.timeoutTime = 60 * 60 * 1000; // 1 hour in milliseconds
        this.warningShown = false;
        this.countdownInterval = null;
        this.init();
    }

    init() {
        // Check session status every minute
        setInterval(() => this.checkSession(), 60000);
        
        // Extend session on user activity
        this.bindActivityEvents();
        
        // Check session immediately
        this.checkSession();
    }

    bindActivityEvents() {
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        events.forEach(event => {
            document.addEventListener(event, () => this.extendSession(), true);
        });
    }

    async checkSession() {
        try {
            const response = await fetch('check_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'check_session' })
            });

            const data = await response.json();
            
            if (!data.valid) {
                this.logout('Session expired. Please login again.');
                return;
            }

            const timeLeft = data.time_left * 1000; // Convert to milliseconds
            
            if (timeLeft <= this.warningTime && !this.warningShown) {
                this.showWarning(timeLeft);
            }
        } catch (error) {
            console.error('Session check failed:', error);
        }
    }

    showWarning(timeLeft) {
        this.warningShown = true;
        
        // Create warning modal
        const modal = document.createElement('div');
        modal.id = 'sessionWarningModal';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        `;

        const warningBox = document.createElement('div');
        warningBox.style.cssText = `
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        `;

        const warningTitle = document.createElement('h3');
        warningTitle.textContent = '⚠️ Session Timeout Warning';
        warningTitle.style.cssText = `
            color: #e74c3c;
            margin-bottom: 15px;
            font-size: 18px;
        `;

        const warningText = document.createElement('p');
        warningText.textContent = 'Your session will expire soon. Click "Extend Session" to stay logged in.';
        warningText.style.cssText = `
            margin-bottom: 20px;
            color: #333;
            line-height: 1.5;
        `;

        const countdown = document.createElement('div');
        countdown.id = 'sessionCountdown';
        countdown.style.cssText = `
            font-size: 24px;
            font-weight: bold;
            color: #e74c3c;
            margin-bottom: 20px;
        `;

        const buttonContainer = document.createElement('div');
        buttonContainer.style.cssText = `
            display: flex;
            gap: 15px;
            justify-content: center;
        `;

        const extendBtn = document.createElement('button');
        extendBtn.textContent = 'Extend Session';
        extendBtn.style.cssText = `
            padding: 12px 24px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        `;
        extendBtn.onclick = () => this.extendSession();

        const logoutBtn = document.createElement('button');
        logoutBtn.textContent = 'Logout Now';
        logoutBtn.style.cssText = `
            padding: 12px 24px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        `;
        logoutBtn.onclick = () => this.logout('Logged out by user.');

        buttonContainer.appendChild(extendBtn);
        buttonContainer.appendChild(logoutBtn);
        warningBox.appendChild(warningTitle);
        warningBox.appendChild(warningText);
        warningBox.appendChild(countdown);
        warningBox.appendChild(buttonContainer);
        modal.appendChild(warningBox);
        document.body.appendChild(modal);

        // Start countdown
        this.startCountdown(countdown, timeLeft);
    }

    startCountdown(countdownElement, timeLeft) {
        const updateCountdown = () => {
            const minutes = Math.floor(timeLeft / 60000);
            const seconds = Math.floor((timeLeft % 60000) / 1000);
            countdownElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                this.logout('Session expired. Please login again.');
                return;
            }
            
            timeLeft -= 1000;
        };

        updateCountdown();
        this.countdownInterval = setInterval(updateCountdown, 1000);
    }

    async extendSession() {
        try {
            const response = await fetch('check_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'extend_session' })
            });

            const data = await response.json();
            
            if (data.success) {
                this.hideWarning();
                this.warningShown = false;
                
                // Show success message
                this.showMessage('Session extended successfully!', 'success');
            } else {
                this.logout('Failed to extend session. Please login again.');
            }
        } catch (error) {
            console.error('Failed to extend session:', error);
            this.logout('Failed to extend session. Please login again.');
        }
    }

    hideWarning() {
        const modal = document.getElementById('sessionWarningModal');
        if (modal) {
            if (this.countdownInterval) {
                clearInterval(this.countdownInterval);
            }
            modal.remove();
        }
    }

    showMessage(message, type = 'info') {
        const messageDiv = document.createElement('div');
        messageDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            font-weight: bold;
            z-index: 10000;
            background: ${type === 'success' ? '#27ae60' : '#3498db'};
        `;
        messageDiv.textContent = message;
        
        document.body.appendChild(messageDiv);
        
        setTimeout(() => {
            messageDiv.remove();
        }, 3000);
    }

    logout(message) {
        // Clear any intervals
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
        
        // Hide warning if shown
        this.hideWarning();
        
        // Show logout message
        if (message) {
            alert(message);
        }
        
        // Redirect to logout
        window.location.href = 'logout.php';
    }
}

// Initialize session timeout manager when page loads
document.addEventListener('DOMContentLoaded', () => {
    new SessionTimeoutManager();
});

// Handle back button navigation more intelligently
window.addEventListener('pageshow', (event) => {
    // Only reload if the page was loaded from back-forward cache AND session is expired
    if (event.persisted) {
        // Check session status before deciding to reload
        fetch('check_session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'check_session' })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.valid) {
                // Session is expired, reload to trigger login redirect
                window.location.reload();
            }
            // If session is valid, don't reload - let user stay on the page
        })
        .catch(error => {
            console.error('Session check failed on back navigation:', error);
            // On error, don't reload - let user stay on the page
        });
    }
});

// Additional security: prevent right-click context menu
document.addEventListener('contextmenu', (e) => {
    e.preventDefault();
});

// Prevent F12, Ctrl+Shift+I, Ctrl+U
document.addEventListener('keydown', (e) => {
    if (
        e.key === 'F12' ||
        (e.ctrlKey && e.shiftKey && e.key === 'I') ||
        (e.ctrlKey && e.key === 'u')
    ) {
        e.preventDefault();
        return false;
    }
});
