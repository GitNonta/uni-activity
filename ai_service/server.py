import io
import os
import json
import numpy as np
import cv2

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

# Initialize InsightFace model globally
print("Loading InsightFace model (buffalo_l)...")
# ใช้ buffalo_l เพื่อความแม่นยำสูงสุด (512-d)
face_app = FaceAnalysis(name='buffalo_l')
# ปรับ det_size ให้เล็กลงเป็น (320, 320) เพื่อเพิ่ม "ความเร็ว" อย่างมาก (เนื่องจากรูป Selfie หน้าจะใหญ่และชัดอยู่แล้ว)
face_app.prepare(ctx_id=-1, det_size=(320, 320), det_thresh=0.5)
print("Model loaded successfully!")

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
        contents = await image.read()
        img = process_image(contents)
        
        faces = face_app.get(img)
        if len(faces) == 0:
            raise HTTPException(status_code=400, detail="No face detected in the image")
        if len(faces) > 1:
            raise HTTPException(status_code=400, detail="Multiple faces detected. Please upload an image with only one person.")
            
        # Get the 512-d embedding and convert to list for JSON serialization
        embedding = faces[0].normed_embedding.tolist()
        
        return {
            "status": "success",
            "message": "Face extracted successfully",
            "embedding": embedding
        }
        
    except ValueError as ve:
        raise HTTPException(status_code=400, detail=str(ve))
    except Exception as e:
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
        
        return {
            "status": "success",
            "is_match": is_match,
            "similarity": float(similarity),
            "score_percentage": float(similarity * 100),
            "message": "Match successful" if is_match else "Face does not match"
        }
        
    except ValueError as ve:
        raise HTTPException(status_code=400, detail=str(ve))
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Internal server error: {str(e)}")

if __name__ == "__main__":
    import uvicorn
    # Run the server on all interfaces, port 8000
    uvicorn.run("server:app", host="0.0.0.0", port=8000, reload=True)
