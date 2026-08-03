/**
 * Smart Face Scanner - Balanced Real-time Processing
 * ปรับสมดุลระหว่าง Frontend (JS) และ Backend (Python AI)
 * 
 * Features:
 * - Adaptive request throttling
 * - Smart fallback system
 * - Performance monitoring
 * - Queue management
 */

class SmartFaceScanner {
    constructor(options = {}) {
        this.options = {
            // Performance settings
            maxConcurrentRequests: 2,
            adaptiveThrottling: true,
            fallbackThreshold: 3, // failures before fallback
            
            // Timing settings
            minInterval: 500,     // minimum ms between requests
            maxInterval: 2000,    // maximum ms between requests
            baseInterval: 1000,   // starting interval
            
            // Quality settings
            preferAccuracy: false, // true = always try Python first
            hybridMode: true,     // use both JS and Python
            
            ...options
        };
        
        // State management
        this.activeRequests = 0;
        this.consecutiveFailures = 0;
        this.currentInterval = this.options.baseInterval;
        this.lastRequestTime = 0;
        this.performanceMetrics = {
            pythonResponseTimes: [],
            jsResponseTimes: [],
            failureRate: 0
        };
        
        // Mode tracking
        this.currentMode = 'hybrid'; // 'python', 'js', 'hybrid'
        this.fallbackActive = false;
        
        // Request queue
        this.requestQueue = [];
        this.isProcessingQueue = false;
        
        console.log('🧠 SmartFaceScanner initialized:', this.options);
    }
    
    /**
     * Main scanning method - intelligently decides processing approach
     */
    async scanFrame(videoElement, userDescriptor) {
        const now = Date.now();
        
        // Throttle requests based on current performance
        if (now - this.lastRequestTime < this.currentInterval) {
            return null;
        }
        
        // Check if we should queue this request
        if (this.activeRequests >= this.options.maxConcurrentRequests) {
            return this.queueRequest(videoElement, userDescriptor);
        }
        
        this.lastRequestTime = now;
        this.activeRequests++;
        
        try {
            let result = null;
            
            switch (this.currentMode) {
                case 'python':
                    result = await this.pythonScan(videoElement, userDescriptor);
                    break;
                    
                case 'js':
                    result = await this.jsScan(videoElement, userDescriptor);
                    break;
                    
                case 'hybrid':
                default:
                    result = await this.hybridScan(videoElement, userDescriptor);
                    break;
            }
            
            // Update performance metrics and intervals
            this.onRequestSuccess(result);
            return result;
            
        } catch (error) {
            this.onRequestError(error);
            return null;
        } finally {
            this.activeRequests--;
        }
    }
    
    /**
     * Hybrid scanning: Start with JS, enhance with Python
     */
    async hybridScan(videoElement, userDescriptor) {
        const startTime = Date.now();
        
        // 1. Quick JS scan for immediate feedback
        const jsPromise = this.jsScan(videoElement, userDescriptor, false);
        
        // 2. Parallel Python scan for accuracy (if conditions are good)
        let pythonPromise = null;
        if (!this.fallbackActive && this.shouldUsePython()) {
            pythonPromise = this.pythonScan(videoElement, userDescriptor, false);
        }
        
        // 3. Wait for JS result first (fast feedback)
        const jsResult = await jsPromise;
        const jsTime = Date.now() - startTime;
        
        // 4. If JS confidence is high enough, use it
        if (jsResult && jsResult.confidence > 0.8) {
            // Cancel Python request if still pending
            if (pythonPromise) {
                pythonPromise.catch(() => {}); // Ignore if it fails
            }
            
            this.recordMetric('js', jsTime, true);
            return {
                ...jsResult,
                source: 'js_primary',
                processingTime: jsTime
            };
        }
        
        // 5. Otherwise wait for Python (if available)
        if (pythonPromise) {
            try {
                const pythonResult = await pythonPromise;
                const totalTime = Date.now() - startTime;
                
                this.recordMetric('python', totalTime - jsTime, true);
                return {
                    ...pythonResult,
                    source: 'python_enhanced',
                    processingTime: totalTime,
                    jsBackup: jsResult
                };
            } catch (pythonError) {
                console.warn('Python scan failed, using JS result:', pythonError);
                this.recordMetric('python', Date.now() - startTime - jsTime, false);
            }
        }
        
        // 6. Fallback to JS result
        return {
            ...jsResult,
            source: 'js_fallback',
            processingTime: jsTime
        };
    }
    
    /**
     * Python AI Server scan
     */
    async pythonScan(videoElement, userDescriptor, standalone = true) {
        if (standalone) console.log('🐍 Python AI scan...');
        
        const startTime = Date.now();
        const canvas = this.captureFrame(videoElement, { maxDim: 640, quality: 0.8 });
        const blob = await this.canvasToBlob(canvas);
        
        const formData = new FormData();
        formData.append('image', blob, 'frame.jpg');
        formData.append('known_embedding', JSON.stringify(userDescriptor.embedding_512d));
        formData.append('check_liveness', 'true');
        
        const response = await fetch('/api/ai-server/verify', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        if (!response.ok) {
            throw new Error(`Python AI Server error: ${response.status}`);
        }
        
        const result = await response.json();
        const processingTime = Date.now() - startTime;
        
        return {
            confidence: result.score_percentage / 100,
            passed: result.is_match,
            liveness: result.liveness_passed,
            score: result.score_percentage,
            processingTime,
            details: result
        };
    }
    
    /**
     * JavaScript face-api.js scan
     */
    async jsScan(videoElement, userDescriptor, standalone = true) {
        if (standalone) console.log('🧠 JavaScript scan...');
        
        const startTime = Date.now();
        
        if (!window.faceapi || !userDescriptor.embedding_128d) {
            throw new Error('Face-api.js not ready or no 128D descriptor');
        }
        
        const detection = await faceapi
            .detectSingleFace(videoElement, new faceapi.TinyFaceDetectorOptions({ inputSize: 416 }))
            .withFaceLandmarks()
            .withFaceDescriptor();
        
        if (!detection) {
            return {
                confidence: 0,
                passed: false,
                score: 0,
                processingTime: Date.now() - startTime,
                message: 'No face detected'
            };
        }
        
        const distance = faceapi.euclideanDistance(userDescriptor.embedding_128d, detection.descriptor);
        const confidence = Math.max(0, (1 - distance));
        const score = confidence * 100;
        const passed = distance < 0.5; // Threshold for JS matching
        
        return {
            confidence,
            passed,
            score,
            processingTime: Date.now() - startTime,
            liveness: true, // JS doesn't do liveness check
            landmarks: detection.landmarks.positions
        };
    }
    
    /**
     * Capture frame from video with optimization
     */
    captureFrame(videoElement, options = {}) {
        const { maxDim = 480, quality = 0.7 } = options;
        
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        // Calculate optimal size
        let { videoWidth: width, videoHeight: height } = videoElement;
        
        if (width > height && width > maxDim) {
            height = Math.round(height * (maxDim / width));
            width = maxDim;
        } else if (height > maxDim) {
            width = Math.round(width * (maxDim / height));
            height = maxDim;
        }
        
        canvas.width = width;
        canvas.height = height;
        
        // Mirror for selfie
        ctx.translate(width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(videoElement, 0, 0, width, height);
        
        return canvas;
    }
    
    /**
     * Convert canvas to blob
     */
    canvasToBlob(canvas, type = 'image/jpeg', quality = 0.8) {
        return new Promise(resolve => {
            canvas.toBlob(resolve, type, quality);
        });
    }
    
    /**
     * Request queue management
     */
    queueRequest(videoElement, userDescriptor) {
        if (this.requestQueue.length < 3) { // Max 3 queued requests
            this.requestQueue.push({ videoElement, userDescriptor, timestamp: Date.now() });
        }
        
        if (!this.isProcessingQueue) {
            this.processQueue();
        }
        
        return Promise.resolve(null); // Don't block UI
    }
    
    async processQueue() {
        this.isProcessingQueue = true;
        
        while (this.requestQueue.length > 0 && this.activeRequests < this.options.maxConcurrentRequests) {
            const request = this.requestQueue.shift();
            
            // Skip stale requests (older than 2 seconds)
            if (Date.now() - request.timestamp > 2000) {
                continue;
            }
            
            // Process without waiting
            this.scanFrame(request.videoElement, request.userDescriptor)
                .catch(error => console.warn('Queued request failed:', error));
        }
        
        this.isProcessingQueue = false;
    }
    
    /**
     * Performance monitoring and adaptive throttling
     */
    recordMetric(type, time, success) {
        const metrics = this.performanceMetrics;
        
        if (success) {
            metrics[`${type}ResponseTimes`].push(time);
            
            // Keep only last 10 measurements
            if (metrics[`${type}ResponseTimes`].length > 10) {
                metrics[`${type}ResponseTimes`].shift();
            }
        }
        
        this.updateAdaptiveInterval();
    }
    
    updateAdaptiveInterval() {
        if (!this.options.adaptiveThrottling) return;
        
        const pythonTimes = this.performanceMetrics.pythonResponseTimes;
        const avgPythonTime = pythonTimes.length > 0 
            ? pythonTimes.reduce((a, b) => a + b) / pythonTimes.length 
            : 2000;
        
        // Adapt interval based on Python performance
        if (avgPythonTime > 3000) {
            this.currentInterval = Math.min(this.options.maxInterval, avgPythonTime * 0.8);
        } else if (avgPythonTime < 1000) {
            this.currentInterval = Math.max(this.options.minInterval, avgPythonTime * 1.2);
        }
        
        console.log(`📊 Adaptive interval: ${this.currentInterval}ms (avg Python: ${avgPythonTime}ms)`);
    }
    
    shouldUsePython() {
        // Don't use Python if failure rate is too high
        const recentFailures = this.consecutiveFailures;
        if (recentFailures >= this.options.fallbackThreshold) {
            return false;
        }
        
        // Don't use Python if response times are consistently too slow
        const pythonTimes = this.performanceMetrics.pythonResponseTimes;
        if (pythonTimes.length >= 3) {
            const avgTime = pythonTimes.reduce((a, b) => a + b) / pythonTimes.length;
            if (avgTime > 5000) return false;
        }
        
        return true;
    }
    
    onRequestSuccess(result) {
        this.consecutiveFailures = 0;
        
        // Re-enable Python if it was disabled
        if (this.fallbackActive && this.consecutiveFailures === 0) {
            this.fallbackActive = false;
            console.log('✅ Python AI Server back online');
        }
    }
    
    onRequestError(error) {
        this.consecutiveFailures++;
        
        console.warn(`❌ Request failed (${this.consecutiveFailures}/${this.options.fallbackThreshold}):`, error.message);
        
        // Enable fallback mode
        if (this.consecutiveFailures >= this.options.fallbackThreshold && !this.fallbackActive) {
            this.fallbackActive = true;
            this.currentMode = 'js';
            console.warn('🔄 Switched to JavaScript-only mode due to Python failures');
        }
        
        // Increase interval on errors
        if (this.options.adaptiveThrottling) {
            this.currentInterval = Math.min(this.options.maxInterval, this.currentInterval * 1.5);
        }
    }
    
    /**
     * Get current performance status
     */
    getStatus() {
        const metrics = this.performanceMetrics;
        const avgPython = metrics.pythonResponseTimes.length > 0
            ? Math.round(metrics.pythonResponseTimes.reduce((a, b) => a + b) / metrics.pythonResponseTimes.length)
            : 0;
        const avgJs = metrics.jsResponseTimes.length > 0
            ? Math.round(metrics.jsResponseTimes.reduce((a, b) => a + b) / metrics.jsResponseTimes.length)
            : 0;
        
        return {
            mode: this.currentMode,
            fallbackActive: this.fallbackActive,
            activeRequests: this.activeRequests,
            queueLength: this.requestQueue.length,
            currentInterval: this.currentInterval,
            consecutiveFailures: this.consecutiveFailures,
            performance: {
                avgPythonTime: avgPython,
                avgJsTime: avgJs,
                samples: {
                    python: metrics.pythonResponseTimes.length,
                    js: metrics.jsResponseTimes.length
                }
            }
        };
    }
}

// Export for use in blade template
window.SmartFaceScanner = SmartFaceScanner;