import io
import os
import json
import numpy as np
import cv2
import time
import logging
import socket

# Setup UDP Handler to send logs to Termux monitor UI
class UDPHandler(logging.Handler):
    def __init__(self, host, port):
        super().__init__()
        self.sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        self.host = host
        self.port = port
    def emit(self, record):
        try:
            msg = self.format(record)
            self.sock.sendto(msg.encode('utf-8'), (self.host, self.port))
        except Exception:
            pass

logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(name)s - %(levelname)s - %(message)s")
logger = logging.getLogger("InsightFaceEngine")

# Add UDP logger
try:
    udp_handler = UDPHandler("192.168.1.222", 9997)
    udp_handler.setFormatter(logging.Formatter("%(asctime)s - %(levelname)s - %(message)s"))
    logger.addHandler(udp_handler)
except Exception as e:
    print(f"Could not setup UDP logger: {e}")

# ปรับจูนประสิทธิภาพของ CPU เพื่อความเร็วในสภาพแวดล้อม Termux / Android TV Box
os.environ["OMP_NUM_THREADS"] = "4"
os.environ["OPENBLAS_NUM_THREADS"] = "4"
os.environ["MKL_NUM_THREADS"] = "4"

from fastapi import FastAPI, File, UploadFile, Form, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from insightface.app import FaceAnalysis

# Initialize FastAPI
app = FastAPI(title="Uni-Activity AI Server", version="1.0.0")

# Allow CORS for Laravel
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

import onnxruntime as ort

# Initialize InsightFace model globally
logger.info("Loading InsightFace model (buffalo_l)...")
# ตรวจสอบว่ามี GPU Provider ให้ใช้หรือไม่ (CUDA, DirectML, etc.)
available_providers = ort.get_available_providers()
logger.info(f"Available ONNX Runtime Providers: {available_providers}")

# กำหนด providers โดยจัดลำดับความสำคัญของ GPU
gpu_providers = ['CUDAExecutionProvider', 'DmlExecutionProvider', 'TensorrtExecutionProvider', 'ROCMExecutionProvider', 'CoreMLExecutionProvider']
active_providers = [p for p in gpu_providers if p in available_providers]

if active_providers:
    logger.info(f"🚀 GPU Detected! Using providers: {active_providers}")
    ctx_id = 0  # GPU 0
    # โหลดเฉพาะ 'detection' และ 'recognition' เพื่อความเร็วในการโหลดโมเดลและประหยัด RAM
    face_app = FaceAnalysis(name='buffalo_l', allowed_modules=['detection', 'recognition'], providers=active_providers + ['CPUExecutionProvider'])
else:
    logger.info("🐌 No GPU detected. Using CPU only. (For NVIDIA GPU, install 'onnxruntime-gpu')")
    ctx_id = -1 # CPU
    face_app = FaceAnalysis(name='buffalo_l', allowed_modules=['detection', 'recognition'], providers=['CPUExecutionProvider'])

# ปรับ det_size กลับเป็น (640, 640) เพื่อแก้ปัญหา DirectML (GPU) Reshape node crash
face_app.prepare(ctx_id=ctx_id, det_size=(640, 640), det_thresh=0.5)
logger.info("Model loaded successfully!")

def process_image(file_bytes):
    """Convert uploaded bytes to cv2 image"""
    nparr = np.frombuffer(file_bytes, np.uint8)
    img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    if img is None:
        raise ValueError("Invalid image file")
    return img

@app.post("/extract")
async def extract_face(image: UploadFile = File(...)):
    """
    Extract face embedding from an uploaded image.
    Used when a student uploads their profile picture.
    """
    try:
        start_time = time.time()
        logger.info(f"Extracting face for {image.filename}...")
        
        contents = await image.read()
        img = process_image(contents)
        
        faces = face_app.get(img)
        if len(faces) == 0:
            raise HTTPException(status_code=400, detail="No face detected in the image")
        if len(faces) > 1:
            raise HTTPException(status_code=400, detail="Multiple faces detected. Please upload an image with only one person.")
            
        # Get the 512-d embedding and convert to list for JSON serialization
        embedding = faces[0].normed_embedding.tolist()
        
        elapsed = time.time() - start_time
        logger.info(f"Extraction successful in {elapsed:.4f} seconds")
        
        return {
            "status": "success",
            "message": "Face extracted successfully",
            "embedding": embedding
        }
        
    except ValueError as ve:
        logger.error(f"Validation error: {ve}")
        raise HTTPException(status_code=400, detail=str(ve))
    except Exception as e:
        logger.error(f"Internal error: {e}")
        raise HTTPException(status_code=500, detail=f"Internal server error: {str(e)}")

@app.post("/verify")
async def verify_face(
    image: UploadFile = File(...), 
    known_embedding: str = Form(...)
):
    """
    Verify an uploaded selfie against a known face embedding.
    Used during real-time check-in.
    """
    try:
        start_time = time.time()
        logger.info(f"Verifying face for {image.filename}...")
        
        # Parse known embedding
        try:
            stored_embedding_list = json.loads(known_embedding)
            stored_emb = np.array(stored_embedding_list, dtype=np.float32)
        except Exception:
            raise HTTPException(status_code=400, detail="Invalid known_embedding format. Must be a JSON array.")
            
        if stored_emb.shape != (512,):
            raise HTTPException(status_code=400, detail="Invalid embedding shape. Expected 512 dimensions.")

        # Process selfie image
        contents = await image.read()
        img = process_image(contents)
        
        faces = face_app.get(img)
        if len(faces) == 0:
            return {
                "status": "error",
                "is_match": False,
                "message": "No face detected in the selfie"
            }
            
        # Use the most prominent face if multiple are detected
        selfie_emb = faces[0].normed_embedding
        
        # Calculate Cosine Similarity
        similarity = np.dot(stored_emb, selfie_emb)
        
        # ปรับความแม่นยำ (Threshold) 
        # buffalo_l แนะนำ 0.40 สำหรับความแม่นยำทั่วไป, 0.45 - 0.50 สำหรับความปลอดภัยสูงสุด (Strict)
        # ตั้งค่าที่ 0.42 เป็นจุดสมดุลที่ดีที่สุดระหว่างความแม่นยำและการใช้งานจริง
        THRESHOLD = 0.42
        is_match = bool(similarity >= THRESHOLD)
        
        elapsed = time.time() - start_time
        logger.info(f"Verification completed in {elapsed:.4f} seconds | Match: {is_match} | Score: {similarity*100:.2f}%")
        
        return {
            "status": "success",
            "is_match": is_match,
            "similarity": float(similarity),
            "score_percentage": float(similarity * 100),
            "message": "Match successful" if is_match else "Face does not match"
        }
        
    except ValueError as ve:
        logger.error(f"Validation error: {ve}")
        raise HTTPException(status_code=400, detail=str(ve))
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Internal error: {e}")
        raise HTTPException(status_code=500, detail=f"Internal server error: {str(e)}")

if __name__ == "__main__":
    import uvicorn
    # Run the server on all interfaces, port 8000
    uvicorn.run("server:app", host="0.0.0.0", port=8001, reload=False)
