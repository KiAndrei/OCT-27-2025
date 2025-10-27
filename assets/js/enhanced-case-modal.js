/**
 * Enhanced Case Modal System
 * Unified modal management with improved UX and schedule tracking
 */

class EnhancedCaseModal {
    constructor() {
        this.currentModal = null;
        this.currentCaseId = null;
        this.currentUserRole = this.getUserRole();
        this.currentUserId = this.getUserId();
    }

    getUserRole() {
        // Try to get role from various session variables
        const role = window.userRole || 
                    (window.userType || 'client').toLowerCase();
        
        console.log('Getting user role:', {
            windowUserRole: window.userRole,
            windowUserType: window.userType,
            finalRole: role
        });
        
        return role;
    }

    getUserId() {
        const userId = window.userId || 
                      window.attorneyId || 
                      window.adminId || 
                      window.clientId || 
                      null;
        
        console.log('Getting user ID:', {
            windowUserId: window.userId,
            windowAttorneyId: window.attorneyId,
            windowAdminId: window.adminId,
            windowClientId: window.clientId,
            finalUserId: userId
        });
        
        return userId;
    }

    /**
     * Manually set user data (fallback method)
     */
    setUserData(userRole, userId) {
        this.currentUserRole = userRole;
        this.currentUserId = userId;
        
        console.log('Manually set user data:', {
            userRole: userRole,
            userId: userId
        });
    }

    /**
     * Show enhanced case modal with improved design
     */
    showCaseModal(caseData, options = {}) {
        this.currentCaseId = caseData.id;
        this.currentCaseData = caseData; // Store the full case data
        
        // Refresh user data in case it wasn't set when modal was initialized
        this.currentUserRole = this.getUserRole();
        this.currentUserId = this.getUserId();
        
        console.log('Opening case modal:', {
            caseData: caseData,
            userRole: this.currentUserRole,
            userId: this.currentUserId
        });
        
        // Create modal if it doesn't exist
        if (!document.getElementById('enhancedCaseModal')) {
            this.createModalStructure();
        }

        const modal = document.getElementById('enhancedCaseModal');
        const modalContent = modal.querySelector('.enhanced-modal-content');
        
        // Populate modal content
        modalContent.innerHTML = this.generateModalContent(caseData, options);
        
        // Show modal with animation
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Focus management
        const firstFocusable = modal.querySelector('button, input, select, textarea');
        if (firstFocusable) {
            firstFocusable.focus();
        }

        // Load schedules and documents for this case
        this.loadCaseSchedules(caseData.id);
        this.loadCaseDocuments(caseData.id);
        
        // Debug: Check if upload button exists
        setTimeout(() => {
            const uploadBtn = modal.querySelector('.enhanced-upload-btn');
            console.log('Upload button found:', !!uploadBtn);
            if (uploadBtn) {
                console.log('Upload button is visible');
            } else {
                console.log('Upload button not found - checking permissions...');
                const canUpload = this.canUploadDocuments(this.currentCaseData);
                console.log('Can upload documents:', canUpload);
            }
        }, 100);
    }

    /**
     * Create the modal structure
     */
    createModalStructure() {
        const modalHTML = `
            <div id="enhancedCaseModal" class="enhanced-modal">
                <div class="enhanced-modal-content">
                    <!-- Content will be populated dynamically -->
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Add event listeners
        this.addEventListeners();
    }

    /**
     * Add event listeners for modal interactions
     */
    addEventListeners() {
        const modal = document.getElementById('enhancedCaseModal');
        
        // Close on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.closeModal();
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                this.closeModal();
            }
        });
    }

    /**
     * Generate modal content based on case data and user role
     */
    generateModalContent(caseData, options) {
        const canUploadDocuments = this.canUploadDocuments(caseData);
        const canEditCase = this.canEditCase(caseData);
        
        return `
            <div class="enhanced-modal-header">
                <div class="enhanced-header-content">
                    <div class="enhanced-header-icon">
                        <i class="fas fa-gavel"></i>
                    </div>
                    <div class="enhanced-header-text">
                        <h2>Case Details</h2>
                        <p>Comprehensive information about case #${caseData.id}</p>
                    </div>
                </div>
                <button class="enhanced-close-btn" onclick="enhancedCaseModal.closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="enhanced-modal-body">
                ${this.generateCaseOverview(caseData)}
                ${this.generateDetailsGrid(caseData)}
                ${this.generateScheduleSection()}
                ${this.generateDocumentSection(caseData)}
            </div>

            <div class="enhanced-modal-footer">
                ${canEditCase ? this.generateActionButtons(caseData) : ''}
                <button class="enhanced-btn enhanced-btn-secondary" onclick="enhancedCaseModal.closeModal()">
                    <i class="fas fa-times"></i>
                    Close
                </button>
            </div>
        `;
    }

    /**
     * Generate case overview section
     */
    generateCaseOverview(caseData) {
        const statusClass = (caseData.status || 'active').toLowerCase();
        const statusText = caseData.status || 'Active';
        
        return `
            <div class="enhanced-case-overview fade-in">
                <div class="enhanced-status-banner status-${statusClass}">
                    <i class="fas fa-circle"></i>
                    <span>${statusText}</span>
                </div>
                <h1 class="enhanced-case-title">${caseData.title || 'Untitled Case'}</h1>
                <p class="enhanced-case-description">
                    ${caseData.description || 'No description provided for this case.'}
                </p>
            </div>
        `;
    }

    /**
     * Generate details grid section
     */
    generateDetailsGrid(caseData) {
        return `
            <div class="enhanced-details-grid">
                <div class="enhanced-detail-section slide-up">
                    <div class="enhanced-section-header">
                        <div class="enhanced-section-icon">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <h3 class="enhanced-section-title">Case Information</h3>
                    </div>
                    <div class="enhanced-detail-item">
                        <div class="enhanced-detail-label">
                            <i class="fas fa-hashtag"></i>
                            <span>Case ID</span>
                        </div>
                        <div class="enhanced-detail-value">#${caseData.id}</div>
                    </div>
                    <div class="enhanced-detail-item">
                        <div class="enhanced-detail-label">
                            <i class="fas fa-tag"></i>
                            <span>Type</span>
                        </div>
                        <div class="enhanced-detail-value">${caseData.case_type || 'General Legal Matter'}</div>
                    </div>
                    <div class="enhanced-detail-item">
                        <div class="enhanced-detail-label">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Date Filed</span>
                        </div>
                        <div class="enhanced-detail-value">${this.formatDate(caseData.created_at)}</div>
                    </div>
                    <div class="enhanced-detail-item">
                        <div class="enhanced-detail-label">
                            <i class="fas fa-clock"></i>
                            <span>Last Updated</span>
                        </div>
                        <div class="enhanced-detail-value">${this.formatDate(caseData.updated_at || caseData.created_at)}</div>
                    </div>
                </div>

                <div class="enhanced-detail-section slide-up">
                    <div class="enhanced-section-header">
                        <div class="enhanced-section-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="enhanced-section-title">People Involved</h3>
                    </div>
                    <div class="enhanced-detail-item">
                        <div class="enhanced-detail-label">
                            <i class="fas fa-user"></i>
                            <span>Client</span>
                        </div>
                        <div class="enhanced-detail-value">${caseData.client_name || 'Not Assigned'}</div>
                    </div>
                    <div class="enhanced-detail-item">
                        <div class="enhanced-detail-label">
                            <i class="fas fa-user-tie"></i>
                            <span>Attorney</span>
                        </div>
                        <div class="enhanced-detail-value">${caseData.attorney_name || 'Not Assigned'}</div>
                    </div>
                    <div class="enhanced-detail-item">
                        <div class="enhanced-detail-label">
                            <i class="fas fa-gavel"></i>
                            <span>Next Hearing</span>
                        </div>
                        <div class="enhanced-detail-value">${caseData.next_hearing || 'Not Scheduled'}</div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Generate schedule section
     */
    generateScheduleSection() {
        return `
            <div class="enhanced-schedule-section slide-up">
                <div class="enhanced-schedule-header">
                    <h3 class="enhanced-schedule-title">
                        <i class="fas fa-calendar-check"></i>
                        Case Schedules
                    </h3>
                    <div class="enhanced-schedule-count" id="scheduleCount">Loading...</div>
                </div>
                <div class="enhanced-schedule-list" id="scheduleList">
                    <div class="enhanced-loading">
                        <i class="fas fa-spinner"></i>
                        Loading schedules...
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Generate document section (if user can upload)
     */
    generateDocumentSection(caseData) {
        const canUploadDocuments = this.canUploadDocuments(caseData);
        
        console.log('Generating document section:', {
            canUploadDocuments: canUploadDocuments,
            userRole: this.currentUserRole,
            userId: this.currentUserId,
            caseData: caseData
        });
        
        return `
            <div class="enhanced-document-section slide-up">
                <div class="enhanced-document-header">
                    <h3 class="enhanced-document-title">
                        <i class="fas fa-file-alt"></i>
                        Case Documents
                    </h3>
                    ${canUploadDocuments ? `
                        <button class="enhanced-upload-btn" onclick="enhancedCaseModal.showDocumentUpload()">
                            <i class="fas fa-upload"></i>
                            Upload Document
                        </button>
                    ` : `
                        <div style="color: #666; font-size: 12px; padding: 8px;">
                            No upload permission (Role: ${this.currentUserRole}, Attorney ID: ${caseData.attorney_id}, User ID: ${this.currentUserId})
                        </div>
                    `}
                </div>
                <div id="documentList">
                    <div class="enhanced-loading">
                        <i class="fas fa-spinner"></i>
                        Loading documents...
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Generate action buttons based on permissions
     */
    generateActionButtons(caseData) {
        const buttons = [];
        
        if (this.currentUserRole === 'admin') {
            buttons.push(`
                <button class="enhanced-btn enhanced-btn-primary" onclick="enhancedCaseModal.editCase(${caseData.id})">
                    <i class="fas fa-edit"></i>
                    Edit Case
                </button>
            `);
        }
        
        return buttons.join('');
    }

    /**
     * Load schedules for the current case
     */
    async loadCaseSchedules(caseId) {
        try {
            const response = await fetch('get_case_schedules.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `case_id=${caseId}`
            });
            
            const schedules = await response.json();
            this.displaySchedules(schedules);
        } catch (error) {
            console.error('Error loading schedules:', error);
            this.displaySchedules([]);
        }
    }

    /**
     * Display schedules in the modal with enhanced tracking
     */
    displaySchedules(schedules) {
        const scheduleList = document.getElementById('scheduleList');
        const scheduleCount = document.getElementById('scheduleCount');
        
        scheduleCount.textContent = `${schedules.length} Schedule${schedules.length !== 1 ? 's' : ''}`;
        
        if (schedules.length === 0) {
            scheduleList.innerHTML = `
                <div class="enhanced-empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Schedules</h3>
                    <p>No schedules have been created for this case yet.</p>
                </div>
            `;
            return;
        }
        
        // Sort schedules by date (upcoming first, then past)
        const sortedSchedules = schedules.sort((a, b) => {
            const dateA = new Date(a.date);
            const dateB = new Date(b.date);
            const now = new Date();
            
            // If both are in the past or both are in the future, sort by date
            if ((dateA < now && dateB < now) || (dateA >= now && dateB >= now)) {
                return dateA - dateB;
            }
            // Future dates first
            return dateB - dateA;
        });
        
        scheduleList.innerHTML = sortedSchedules.map(schedule => {
            const scheduleDate = new Date(schedule.date);
            const now = new Date();
            const isUpcoming = scheduleDate >= now;
            const isPast = scheduleDate < now;
            
            return `
                <div class="enhanced-schedule-item fade-in ${isPast ? 'past-schedule' : ''}">
                    <div class="enhanced-schedule-item-header">
                        <div class="enhanced-schedule-type">${schedule.type}</div>
                        <div class="enhanced-schedule-status ${schedule.status.toLowerCase()}">${schedule.status}</div>
                        ${isUpcoming ? '<div class="schedule-indicator upcoming">Upcoming</div>' : ''}
                        ${isPast ? '<div class="schedule-indicator past">Past</div>' : ''}
                    </div>
                    <div class="enhanced-schedule-details">
                        <div class="enhanced-schedule-detail">
                            <i class="fas fa-calendar"></i>
                            <span>${this.formatDate(schedule.date)}</span>
                        </div>
                        <div class="enhanced-schedule-detail">
                            <i class="fas fa-clock"></i>
                            <span>${this.formatTime(schedule.start_time)} - ${this.formatTime(schedule.end_time)}</span>
                        </div>
                        ${schedule.location ? `
                            <div class="enhanced-schedule-detail">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>${schedule.location}</span>
                            </div>
                        ` : ''}
                        ${schedule.attorney_name ? `
                            <div class="enhanced-schedule-detail">
                                <i class="fas fa-user-tie"></i>
                                <span>Attorney: ${schedule.attorney_name}</span>
                            </div>
                        ` : ''}
                        ${schedule.client_name ? `
                            <div class="enhanced-schedule-detail">
                                <i class="fas fa-user"></i>
                                <span>Client: ${schedule.client_name}</span>
                            </div>
                        ` : ''}
                        ${schedule.description ? `
                            <div class="enhanced-schedule-detail full-width">
                                <i class="fas fa-comment"></i>
                                <span>${schedule.description}</span>
                            </div>
                        ` : ''}
                        <div class="enhanced-schedule-detail">
                            <i class="fas fa-user-plus"></i>
                            <span>Created by: ${schedule.created_by_name || 'System'}</span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    /**
     * Check if user can upload documents for this case
     */
    canUploadDocuments(caseData) {
        console.log('Checking upload permissions:', {
            userRole: this.currentUserRole,
            userId: this.currentUserId,
            caseData: caseData,
            attorneyId: caseData.attorney_id,
            isMatch: caseData.attorney_id == this.currentUserId
        });
        
        if (this.currentUserRole === 'client') {
            console.log('Client cannot upload documents');
            return false; // Clients cannot upload documents
        }
        
        if (this.currentUserRole === 'admin') {
            // Admin can upload to cases assigned to them (as attorney)
            // Check if attorney_id matches the current user's ID
            const canUpload = caseData.attorney_id == this.currentUserId;
            console.log('Admin upload check:', canUpload, 'attorney_id:', caseData.attorney_id, 'currentUserId:', this.currentUserId);
            return canUpload;
        }
        
        if (this.currentUserRole === 'attorney') {
            // Attorneys can only upload to cases assigned to them
            // Check if attorney_id matches the current user's ID
            const canUpload = caseData.attorney_id == this.currentUserId;
            console.log('Attorney upload check:', canUpload, 'attorney_id:', caseData.attorney_id, 'currentUserId:', this.currentUserId);
            return canUpload;
        }
        
        if (this.currentUserRole === 'employee') {
            console.log('Employee can upload to any case');
            return true;
        }
        
        console.log('No matching role, cannot upload');
        return false;
    }

    /**
     * Check if user can edit this case
     */
    canEditCase(caseData) {
        return this.currentUserRole === 'admin';
    }

    /**
     * Show document upload interface
     */
    showDocumentUpload() {
        // Get the current case data from the modal
        const modal = document.getElementById('enhancedCaseModal');
        const caseIdElement = modal.querySelector('.case-id');
        const caseId = caseIdElement ? parseInt(caseIdElement.textContent.replace('#', '')) : this.currentCaseId;
        
        // Create a minimal case data object with the current case ID
        const caseData = {
            id: caseId,
            attorney_id: this.currentCaseData ? this.currentCaseData.attorney_id : null
        };
        
        // Check if user can upload documents
        if (!this.canUploadDocuments(caseData)) {
            alert('You do not have permission to upload documents to this case.');
            return;
        }
        
        const uploadModal = this.createDocumentUploadModal();
        
        // Add upload modal to the page
        document.body.insertAdjacentHTML('beforeend', uploadModal);
        
        // Show upload modal
        const uploadModalElement = document.getElementById('documentUploadModal');
        uploadModalElement.classList.add('active');
        
        // Add event listeners
        this.addDocumentUploadListeners();
    }

    /**
     * Create document upload modal
     */
    createDocumentUploadModal() {
        return `
            <div id="documentUploadModal" class="enhanced-modal">
                <div class="enhanced-modal-content" style="max-width: 700px;">
                    <div class="enhanced-modal-header">
                        <div class="enhanced-header-content">
                            <div class="enhanced-header-icon">
                                <i class="fas fa-upload"></i>
                            </div>
                            <div class="enhanced-header-text">
                                <h2>Upload Case Documents</h2>
                                <p>Add important documents to case #${this.currentCaseId}</p>
                            </div>
                        </div>
                        <button class="enhanced-close-btn" onclick="enhancedCaseModal.closeDocumentUploadModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="enhanced-modal-body">
                        <div class="upload-instructions">
                            <div class="instruction-item">
                                <i class="fas fa-info-circle"></i>
                                <span>Upload documents related to this case (contracts, evidence, correspondence, etc.)</span>
                            </div>
                            <div class="instruction-item">
                                <i class="fas fa-file-alt"></i>
                                <span>Supported formats: PDF, Word documents, Images (JPG, PNG, GIF)</span>
                            </div>
                            <div class="instruction-item">
                                <i class="fas fa-weight-hanging"></i>
                                <span>Maximum file size: 5MB per file</span>
                            </div>
                        </div>
                        
                        <form id="documentUploadForm" enctype="multipart/form-data">
                            <input type="hidden" name="case_id" value="${this.currentCaseId}">
                            
                            <div class="upload-area" id="uploadArea">
                                <div class="upload-content">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <h3>Drop files here or click to browse</h3>
                                    <p>Select multiple files to upload at once</p>
                                    <input type="file" id="fileInput" name="documents[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif" style="display: none;">
                                </div>
                            </div>
                            
                            <div id="fileList" class="file-list" style="display: none;">
                                <!-- Selected files will be listed here -->
                            </div>
                            
                            <div class="form-actions" style="margin-top: 20px;">
                                <button type="button" class="enhanced-btn enhanced-btn-secondary" onclick="enhancedCaseModal.closeDocumentUploadModal()">
                                    <i class="fas fa-times"></i>
                                    Cancel
                                </button>
                                <button type="submit" class="enhanced-btn enhanced-btn-primary" id="uploadBtn" disabled>
                                    <i class="fas fa-upload"></i>
                                    Upload Documents
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Add event listeners for document upload
     */
    addDocumentUploadListeners() {
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const uploadForm = document.getElementById('documentUploadForm');
        
        // Click to browse files
        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });
        
        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('drag-over');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('drag-over');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');
            const files = e.dataTransfer.files;
            this.handleFileSelection(files);
        });
        
        // File input change
        fileInput.addEventListener('change', (e) => {
            this.handleFileSelection(e.target.files);
        });
        
        // Form submission
        uploadForm.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitDocumentUpload();
        });
    }

    /**
     * Handle file selection
     */
    handleFileSelection(files) {
        const fileList = document.getElementById('fileList');
        const uploadBtn = document.getElementById('uploadBtn');
        
        if (files.length === 0) return;
        
        fileList.innerHTML = '';
        fileList.style.display = 'block';
        
        Array.from(files).forEach((file, index) => {
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item';
            fileItem.innerHTML = `
                <div class="file-info">
                    <i class="fas fa-file"></i>
                    <span class="file-name">${file.name}</span>
                    <span class="file-size">${this.formatFileSize(file.size)}</span>
                </div>
                <div class="file-inputs">
                    <div class="input-group">
                        <label>Document Name *</label>
                        <input type="text" name="doc_names[]" placeholder="Enter a descriptive name for this document" required>
                    </div>
                    <div class="input-group">
                        <label>Category *</label>
                        <select name="categories[]" required>
                            <option value="">Select Document Category</option>
                            <option value="Contract">Contract</option>
                            <option value="Evidence">Evidence</option>
                            <option value="Correspondence">Correspondence</option>
                            <option value="Legal Document">Legal Document</option>
                            <option value="Court Filing">Court Filing</option>
                            <option value="Client Information">Client Information</option>
                            <option value="Case Notes">Case Notes</option>
                            <option value="Financial Document">Financial Document</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="input-group full-width">
                        <label>Description</label>
                        <textarea name="descriptions[]" placeholder="Provide additional details about this document (optional)" rows="3"></textarea>
                    </div>
                </div>
            `;
            fileList.appendChild(fileItem);
        });
        
        // Update file input to include selected files
        const fileInput = document.getElementById('fileInput');
        fileInput.files = files;
        
        uploadBtn.disabled = false;
    }

    /**
     * Submit document upload
     */
    async submitDocumentUpload() {
        const form = document.getElementById('documentUploadForm');
        const formData = new FormData(form);
        const uploadBtn = document.getElementById('uploadBtn');
        
        // Add case_id to form data
        formData.append('case_id', this.currentCaseId);
        
        // Add user data for debugging
        console.log('Upload request data:', {
            caseId: this.currentCaseId,
            userRole: this.currentUserRole,
            userId: this.currentUserId,
            formData: Object.fromEntries(formData.entries())
        });
        
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        
        try {
            const response = await fetch('enhanced_document_upload.php', {
                method: 'POST',
                body: formData
            });
            
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const text = await response.text();
            console.log('Raw response:', text);
            
            // Try to parse JSON
            let result;
            try {
                result = JSON.parse(text);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response text:', text);
                throw new Error('Invalid JSON response from server');
            }
            
            if (result.success) {
                // Show success message
                this.showUploadMessage('success', result.message);
                
                // Immediately refresh document list
                this.loadCaseDocuments(this.currentCaseId);
                
                // Close modal after a delay
                setTimeout(() => {
                    this.closeDocumentUploadModal();
                }, 2000);
            } else {
                this.showUploadMessage('error', 'Upload failed: ' + (result.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Upload error:', error);
            alert('Upload failed: ' + error.message);
        } finally {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload Documents';
        }
    }

    /**
     * Show upload message
     */
    showUploadMessage(type, message) {
        const form = document.getElementById('documentUploadForm');
        const existingMessage = document.querySelector('.upload-message');
        
        if (existingMessage) {
            existingMessage.remove();
        }
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `upload-message ${type}`;
        messageDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            ${message}
        `;
        
        form.insertBefore(messageDiv, form.firstChild);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }

    /**
     * Close document upload modal
     */
    closeDocumentUploadModal() {
        const modal = document.getElementById('documentUploadModal');
        if (modal) {
            modal.remove();
        }
    }

    /**
     * Load case documents
     */
    async loadCaseDocuments(caseId) {
        try {
            const response = await fetch('get_case_documents.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `case_id=${caseId}`
            });
            
            const documents = await response.json();
            this.displayDocuments(documents);
        } catch (error) {
            console.error('Error loading documents:', error);
            this.displayDocuments([]);
        }
    }

    /**
     * Display documents in the modal
     */
    displayDocuments(documents) {
        const documentList = document.getElementById('documentList');
        
        if (documents.length === 0) {
            documentList.innerHTML = `
                <div class="enhanced-empty-state">
                    <i class="fas fa-file-alt"></i>
                    <h3>No Documents</h3>
                    <p>No documents have been uploaded for this case yet.</p>
                </div>
            `;
            return;
        }
        
        documentList.innerHTML = documents.map(doc => `
            <div class="enhanced-document-item fade-in">
                <div class="document-icon">
                    <i class="fas fa-file-${this.getFileIcon(doc.file_type)}"></i>
                </div>
                <div class="document-info">
                    <div class="document-name">${doc.file_name}</div>
                    <div class="document-meta">
                        <span class="document-category">${doc.category}</span>
                        <span class="document-size">${doc.file_size_formatted}</span>
                        <span class="document-date">${doc.upload_date_formatted}</span>
                    </div>
                    ${doc.description ? `<div class="document-description">${doc.description}</div>` : ''}
                </div>
                <div class="document-actions">
                    <button class="btn-download" onclick="enhancedCaseModal.downloadDocument('${doc.file_path}', '${doc.file_name}')">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
            </div>
        `).join('');
    }

    /**
     * Get file icon based on file type
     */
    getFileIcon(fileType) {
        if (fileType.includes('pdf')) return 'pdf';
        if (fileType.includes('word') || fileType.includes('document')) return 'word';
        if (fileType.includes('image')) return 'image';
        return 'alt';
    }

    /**
     * Download document
     */
    downloadDocument(filePath, fileName) {
        const link = document.createElement('a');
        link.href = filePath;
        link.download = fileName;
        link.click();
    }

    /**
     * Format file size for display
     */
    formatFileSize(bytes) {
        if (bytes >= 1073741824) {
            return (bytes / 1073741824).toFixed(2) + ' GB';
        } else if (bytes >= 1048576) {
            return (bytes / 1048576).toFixed(2) + ' MB';
        } else if (bytes >= 1024) {
            return (bytes / 1024).toFixed(2) + ' KB';
        } else {
            return bytes + ' bytes';
        }
    }

    /**
     * Edit case functionality
     */
    editCase(caseId) {
        // This would integrate with existing case editing functionality
        alert('Case editing functionality will be integrated here');
    }

    /**
     * Close the modal
     */
    closeModal() {
        const modal = document.getElementById('enhancedCaseModal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
            this.currentCaseId = null;
        }
    }

    /**
     * Format date for display
     */
    formatDate(dateString) {
        if (!dateString) return 'Not Available';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    /**
     * Format time for display
     */
    formatTime(timeString) {
        if (!timeString) return '';
        const time = new Date(`2000-01-01T${timeString}`);
        return time.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    }
}

// Initialize the enhanced modal system
const enhancedCaseModal = new EnhancedCaseModal();

// Global function to show case modal (for backward compatibility)
function showEnhancedCaseModal(caseData, options = {}) {
    enhancedCaseModal.showCaseModal(caseData, options);
}

// Global function to close modal (for backward compatibility)
function closeEnhancedCaseModal() {
    enhancedCaseModal.closeModal();
}
