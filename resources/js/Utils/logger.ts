/**
 * Production-safe logger utility for tracking QR Scanner and other critical features
 * Logs are sent to the backend for persistent storage in production
 */

interface LogEntry {
    level: 'debug' | 'info' | 'warn' | 'error';
    component: string;
    message: string;
    data?: any;
    timestamp: string;
    url: string;
    userAgent: string;
}

class Logger {
    private isDevelopment: boolean;
    private logBuffer: LogEntry[] = [];
    private flushInterval: number = 5000; // Flush logs every 5 seconds
    private maxBufferSize: number = 50;
    private flushTimer: NodeJS.Timeout | null = null;

    constructor() {
        this.isDevelopment = import.meta.env.DEV;
        
        // Start the flush timer in production
        if (!this.isDevelopment) {
            this.startFlushTimer();
        }

        // Flush logs before page unload
        if (typeof window !== 'undefined') {
            window.addEventListener('beforeunload', () => {
                this.flush();
            });
        }
    }

    private startFlushTimer() {
        this.flushTimer = setInterval(() => {
            if (this.logBuffer.length > 0) {
                this.flush();
            }
        }, this.flushInterval);
    }

    private createLogEntry(
        level: LogEntry['level'],
        component: string,
        message: string,
        data?: any
    ): LogEntry {
        return {
            level,
            component,
            message,
            data: data ? this.sanitizeData(data) : undefined,
            timestamp: new Date().toISOString(),
            url: typeof window !== 'undefined' ? window.location.href : '',
            userAgent: typeof navigator !== 'undefined' ? navigator.userAgent : '',
        };
    }

    private sanitizeData(data: any): any {
        // Remove sensitive information from log data
        const sanitized = { ...data };
        const sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'credit_card'];
        
        const sanitizeObject = (obj: any): any => {
            if (typeof obj !== 'object' || obj === null) return obj;
            
            const result: any = Array.isArray(obj) ? [] : {};
            
            for (const key in obj) {
                if (sensitiveKeys.some(k => key.toLowerCase().includes(k))) {
                    result[key] = '[REDACTED]';
                } else if (typeof obj[key] === 'object') {
                    result[key] = sanitizeObject(obj[key]);
                } else {
                    result[key] = obj[key];
                }
            }
            
            return result;
        };

        return sanitizeObject(sanitized);
    }

    private async flush() {
        if (this.logBuffer.length === 0) return;

        const logsToSend = [...this.logBuffer];
        this.logBuffer = [];

        try {
            const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
            
            await fetch('/api/frontend-logs', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
                body: JSON.stringify({ logs: logsToSend }),
            });
        } catch (error) {
            // Silently fail in production to avoid disrupting user experience
            if (this.isDevelopment) {
                console.error('Failed to send logs to backend:', error);
            }
        }
    }

    private log(level: LogEntry['level'], component: string, message: string, data?: any) {
        const entry = this.createLogEntry(level, component, message, data);

        // Always log to console in development
        if (this.isDevelopment) {
            const consoleMethod = level === 'error' ? 'error' : level === 'warn' ? 'warn' : 'log';
            console[consoleMethod](`[${component}]`, message, data || '');
        }

        // In production, buffer logs for batch sending
        if (!this.isDevelopment) {
            this.logBuffer.push(entry);
            
            // Flush immediately for errors or if buffer is full
            if (level === 'error' || this.logBuffer.length >= this.maxBufferSize) {
                this.flush();
            }
        }
    }

    debug(component: string, message: string, data?: any) {
        this.log('debug', component, message, data);
    }

    info(component: string, message: string, data?: any) {
        this.log('info', component, message, data);
    }

    warn(component: string, message: string, data?: any) {
        this.log('warn', component, message, data);
    }

    error(component: string, message: string, data?: any) {
        this.log('error', component, message, data);
    }

    // Registration specific logging methods
    registration = {
        pageVisit: (flowId: string, step: string, data?: any) => {
            this.info('Registration', `Registration page visit: ${step}`, { 
                flow_id: flowId, 
                step, 
                ...data 
            });
        },
        
        planSelected: (flowId: string, planData: any) => {
            this.info('Registration', 'User selected membership plan', {
                flow_id: flowId,
                step: 'plan_selection',
                plan_id: planData.id,
                plan_slug: planData.slug,
                plan_price: planData.price,
                is_popular: planData.is_popular,
            });
        },
        
        formSubmitted: (flowId: string, formData: any) => {
            this.info('Registration', 'Registration form submitted', {
                flow_id: flowId,
                step: 'form_submission',
                email: formData.email,
                name: formData.name,
                mobile_number: formData.mobile_number,
                selected_price_id: formData.selected_price_id,
            });
        },
        
        validationError: (flowId: string, errors: any, formData?: any) => {
            this.error('Registration', 'Form validation errors occurred', {
                flow_id: flowId,
                step: 'form_validation',
                validation_errors: errors,
                email: formData?.email,
                selected_price_id: formData?.selected_price_id,
            });
        },
        
        submitError: (flowId: string, error: any, formData?: any) => {
            this.error('Registration', 'Registration form submission failed', {
                flow_id: flowId,
                step: 'form_submission_error',
                error_message: error.message || error,
                error_type: typeof error,
                email: formData?.email,
                selected_price_id: formData?.selected_price_id,
            });
        },
        
        redirectToStripe: (flowId: string, data?: any) => {
            this.info('Registration', 'Redirecting user to Stripe checkout', {
                flow_id: flowId,
                step: 'stripe_redirect',
                ...data,
            });
        },
        
        registrationSuccess: (flowId: string, data?: any) => {
            this.info('Registration', 'Registration completed successfully', {
                flow_id: flowId,
                step: 'registration_complete',
                ...data,
            });
        },
        
        registrationCancelled: (flowId: string, data?: any) => {
            this.warn('Registration', 'Registration cancelled by user', {
                flow_id: flowId,
                step: 'registration_cancelled',
                ...data,
            });
        },
        
        error: (flowId: string, message: string, error: any, context?: any) => {
            this.error('Registration', message, {
                flow_id: flowId,
                error_message: error.message || error,
                error_type: typeof error,
                stack_trace: error.stack,
                ...context,
            });
        },
    };

    // QR Scanner specific logging methods
    qrScanner = {
        init: (data: any) => {
            this.info('QRScanner', 'Initializing QR Scanner', data);
        },
        
        cameraPermission: (status: string, details?: any) => {
            this.info('QRScanner', `Camera permission: ${status}`, details);
        },
        
        scannerReady: (ready: boolean) => {
            this.info('QRScanner', `Scanner ready: ${ready}`);
        },
        
        scanAttempt: (success: boolean, data?: any) => {
            this.info('QRScanner', `Scan attempt: ${success ? 'success' : 'failed'}`, data);
        },
        
        error: (error: any) => {
            this.error('QRScanner', 'Scanner error occurred', {
                message: error.message || error,
                name: error.name,
                stack: error.stack,
            });
        },
        
        stateChange: (state: string, details?: any) => {
            this.debug('QRScanner', `State changed to: ${state}`, details);
        },
    };

    // Clean up method
    destroy() {
        if (this.flushTimer) {
            clearInterval(this.flushTimer);
            this.flushTimer = null;
        }
        this.flush();
    }
}

// Export singleton instance
export const logger = new Logger();

// Export type for use in components
export type { LogEntry };