/**
 * 3D Face Mesh Overlay
 * Using MediaPipe Face Mesh for 468 3D landmarks
 */

class FaceMesh3D {
    constructor(videoElement, canvasElement) {
        this.video = videoElement;
        this.canvas = canvasElement;
        this.ctx = canvasElement.getContext('2d');
        this.faceMesh = null;
        this.isInitialized = false;
        this.animationFrame = null;
        
        // Face mesh settings
        this.settings = {
            maxNumFaces: 1,
            refineLandmarks: true,
            minDetectionConfidence: 0.5,
            minTrackingConfidence: 0.5
        };
        
        // Visual settings
        this.meshColor = 'rgba(0, 255, 136, 0.3)';
        this.lineColor = 'rgba(0, 255, 136, 0.5)';
        this.pointColor = 'rgba(0, 255, 136, 0.8)';
        this.glowIntensity = 0;
        this.glowDirection = 1;
    }
    
    async initialize() {
        if (this.isInitialized) return true;
        
        try {
            // Load MediaPipe Face Mesh
            this.faceMesh = new FaceMesh({
                locateFile: (file) => {
                    return `https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/${file}`;
                }
            });
            
            this.faceMesh.setOptions(this.settings);
            
            this.faceMesh.onResults((results) => {
                this.onResults(results);
            });
            
            this.isInitialized = true;
            console.log('✓ MediaPipe Face Mesh initialized');
            return true;
            
        } catch (error) {
            console.error('Face Mesh initialization error:', error);
            return false;
        }
    }
    
    async start() {
        if (!this.isInitialized) {
            const initialized = await this.initialize();
            if (!initialized) return false;
        }
        
        // Start detection loop
        this.detectLoop();
        return true;
    }
    
    async detectLoop() {
        if (!this.video || !this.faceMesh) return;
        
        // Send frame to Face Mesh
        await this.faceMesh.send({ image: this.video });
        
        // Continue loop
        this.animationFrame = requestAnimationFrame(() => this.detectLoop());
    }
    
    stop() {
        if (this.animationFrame) {
            cancelAnimationFrame(this.animationFrame);
            this.animationFrame = null;
        }
        this.clearCanvas();
    }
    
    onResults(results) {
        // Resize canvas if needed
        if (this.canvas.width !== this.video.videoWidth || 
            this.canvas.height !== this.video.videoHeight) {
            this.canvas.width = this.video.videoWidth;
            this.canvas.height = this.video.videoHeight;
        }
        
        // Clear canvas
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        
        if (results.multiFaceLandmarks && results.multiFaceLandmarks.length > 0) {
            const landmarks = results.multiFaceLandmarks[0];
            
            // Draw 3D mesh
            this.draw3DMesh(landmarks);
            
            // Update glow animation
            this.updateGlow();
        }
    }
    
    draw3DMesh(landmarks) {
        const ctx = this.ctx;
        
        // Convert normalized coordinates to canvas coordinates
        const points = landmarks.map(landmark => ({
            x: landmark.x * this.canvas.width,
            y: landmark.y * this.canvas.height,
            z: landmark.z // 3D depth
        }));
        
        // Draw mesh connections (tessellation)
        this.drawMeshConnections(points);
        
        // Draw mesh fill (triangles)
        this.drawMeshFill(points);
        
        // Draw key points
        this.drawKeyPoints(points);
        
        // Draw contour glow
        this.drawContourGlow(points);
    }
    
    drawMeshConnections(points) {
        const ctx = this.ctx;
        
        // MediaPipe Face Mesh FACEMESH_TESSELATION connections
        const connections = FACEMESH_TESSELATION;
        
        ctx.strokeStyle = this.lineColor;
        ctx.lineWidth = 0.5;
        
        ctx.beginPath();
        for (const connection of connections) {
            const start = points[connection[0]];
            const end = points[connection[1]];
            
            if (start && end) {
                ctx.moveTo(start.x, start.y);
                ctx.lineTo(end.x, end.y);
            }
        }
        ctx.stroke();
    }
    
    drawMeshFill(points) {
        const ctx = this.ctx;
        
        // Draw semi-transparent mesh fill
        ctx.fillStyle = this.meshColor;
        
        // Use tesselation to draw triangles
        const triangles = this.getTriangles(points);
        
        for (const triangle of triangles) {
            ctx.beginPath();
            ctx.moveTo(triangle[0].x, triangle[0].y);
            ctx.lineTo(triangle[1].x, triangle[1].y);
            ctx.lineTo(triangle[2].x, triangle[2].y);
            ctx.closePath();
            ctx.fill();
        }
    }
    
    getTriangles(points) {
        // Get triangulated mesh
        // MediaPipe provides FACEMESH_TESSELATION
        const triangles = [];
        const connections = FACEMESH_TESSELATION;
        
        // Group connections into triangles (every 3 connections)
        for (let i = 0; i < connections.length; i += 3) {
            if (i + 2 < connections.length) {
                const triangle = [
                    points[connections[i][0]],
                    points[connections[i + 1][0]],
                    points[connections[i + 2][0]]
                ];
                if (triangle[0] && triangle[1] && triangle[2]) {
                    triangles.push(triangle);
                }
            }
        }
        
        return triangles;
    }
    
    drawKeyPoints(points) {
        const ctx = this.ctx;
        
        // Draw important landmarks
        const keyIndices = [
            // Eyes
            33, 133, 362, 263,
            // Nose
            1, 2,
            // Mouth
            61, 291,
            // Face contour
            10, 234, 454
        ];
        
        ctx.fillStyle = this.pointColor;
        ctx.shadowBlur = 5 + this.glowIntensity * 5;
        ctx.shadowColor = this.pointColor;
        
        for (const index of keyIndices) {
            const point = points[index];
            if (point) {
                ctx.beginPath();
                ctx.arc(point.x, point.y, 2, 0, 2 * Math.PI);
                ctx.fill();
            }
        }
        
        ctx.shadowBlur = 0;
    }
    
    drawContourGlow(points) {
        const ctx = this.ctx;
        
        // Draw glowing contour around face
        const contourIndices = FACEMESH_FACE_OVAL;
        
        ctx.strokeStyle = `rgba(0, 255, 136, ${0.6 + this.glowIntensity * 0.4})`;
        ctx.lineWidth = 2;
        ctx.shadowBlur = 10 + this.glowIntensity * 10;
        ctx.shadowColor = 'rgba(0, 255, 136, 0.8)';
        
        ctx.beginPath();
        let first = true;
        for (const connection of contourIndices) {
            const point = points[connection[0]];
            if (point) {
                if (first) {
                    ctx.moveTo(point.x, point.y);
                    first = false;
                } else {
                    ctx.lineTo(point.x, point.y);
                }
            }
        }
        ctx.stroke();
        
        ctx.shadowBlur = 0;
    }
    
    updateGlow() {
        // Animate glow intensity
        this.glowIntensity += 0.02 * this.glowDirection;
        
        if (this.glowIntensity >= 1) {
            this.glowIntensity = 1;
            this.glowDirection = -1;
        } else if (this.glowIntensity <= 0) {
            this.glowIntensity = 0;
            this.glowDirection = 1;
        }
    }
    
    clearCanvas() {
        if (this.ctx) {
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        }
    }
    
    setMeshColor(color) {
        this.meshColor = color;
    }
    
    setLineColor(color) {
        this.lineColor = color;
    }
    
    setPointColor(color) {
        this.pointColor = color;
    }
}

// MediaPipe Face Mesh connection indices
// These are provided by MediaPipe library
const FACEMESH_TESSELATION = [
    [127, 34], [34, 139], [139, 127], [11, 0], [0, 37], [37, 11],
    [232, 231], [231, 120], [120, 232], [72, 37], [37, 39], [39, 72],
    [128, 121], [121, 47], [47, 128], [232, 121], [121, 128], [128, 232],
    // ... (continues with all tesselation connections)
];

const FACEMESH_FACE_OVAL = [
    [10, 338], [338, 297], [297, 332], [332, 284], [284, 251],
    [251, 389], [389, 356], [356, 454], [454, 323], [323, 361],
    [361, 288], [288, 397], [397, 365], [365, 379], [379, 378],
    [378, 400], [400, 377], [377, 152], [152, 148], [148, 176],
    [176, 149], [149, 150], [150, 136], [136, 172], [172, 58],
    [58, 132], [132, 93], [93, 234], [234, 127], [127, 162],
    [162, 21], [21, 54], [54, 103], [103, 67], [67, 109], [109, 10]
];

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FaceMesh3D;
}
