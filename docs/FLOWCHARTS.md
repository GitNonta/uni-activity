# FLOWCHARTS — Uni-Activity System

แผนภาพ Flowchart หลักของโปรเจกต์ (วาดด้วย [Mermaid](https://mermaid.js.org/) — แสดงผลได้บน GitHub/GitLab)
สถาปัตยกรรมอ้างอิงจาก: `docker-compose.yml`, `docker-compose.prod.yml`, `routes/web.php`, `routes/channels.php`, `app/Services/ChatService.php`, `app/Events/MessageSent.php`, `ai_service/server.py`

---

## 1. System Architecture Overview (Deployment Topology)

ใช้เป็นภาพรวมก่อนสุด — แสดงองค์ประกอบ Infrastructure ทั้งหมด

```mermaid
flowchart TB
    subgraph Clients["Clients"]
        S["Student Web / Mobile"]
        A["Admin Dashboard"]
    end

    subgraph Edge["Ingress Layer"]
        LB["Nginx Load Balancer<br/>HTTP :80/443 · WebSocket :8080"]
    end

    subgraph AppTier["Application Tier"]
        APP1["Laravel App Node-1<br/>PHP-FPM / Octane"]
        APP2["Laravel App Node-2<br/>PHP-FPM / Octane"]
        QW["Queue Worker<br/>queues: ai, notifications,<br/>exports, cassandra, default"]
        RV["Laravel Reverb<br/>WebSocket Server :8080"]
        AI["AI Microservice (Python FastAPI)<br/>InsightFace + YOLOv8-face<br/>/extract · /verify · /liveness"]
    end

    subgraph DataTier["Data Tier (internal network)"]
        PG[("PostgreSQL 16<br/>uni_activity")]
        VK[("Valkey / Dragonfly<br/>Cache · Session · Queue · PubSub · Locks")]
    end

    LINE["LINE Messaging API"]

    S --> LB
    A --> LB
    LB --> APP1
    LB --> APP2
    APP1 <--> PG
    APP2 <--> PG
    APP1 <--> VK
    APP2 <--> VK
    APP1 -- dispatch jobs --> VK
    VK -- consume --> QW
    QW <--> PG
    APP1 -- REST + API key --> AI
    APP2 -- REST + API key --> AI
    APP1 -. pub/sub chat events .-> VK
    S <-->|"wss (Echo + pusher-js)"| RV
    A <-->|"wss (Echo + pusher-js)"| RV
    RV <-- Redis pub/sub --> VK
    QW -- notifications --> LINE
```

> Production (`docker-compose.prod.yml`) เพิ่ม Zero Trust: PostgreSQL และ Valkey อยู่บน `backend-network` ที่ตั้งค่า `internal: true` เข้าถึงจาก Internet ไม่ได้ มีเฉพาะ Nginx Ingress ที่ expose 80/443/8080

---

## 2. Real-time Chat Flow (Student ↔ Admin)

Flow หลักของระบบ: `ChatController → ChatService → ChatRepository → MessageSent event → Reverb`

```mermaid
sequenceDiagram
    autonumber
    participant STU as Student Browser
    participant API as Laravel App (API)
    participant REPO as ChatRepository
    participant PG as PostgreSQL
    participant Q as Valkey Queue
    participant W as Queue Worker
    participant REV as Reverb WS
    participant ADM as Admin Browser

    STU->>API: POST /jobs/{jobId}/chat (body, files)
    Note over API: throttle:chat-send + auth middleware
    API->>REPO: find/create Room (direct/job room)
    REPO->>PG: DB::transaction insert Message
    API->>API: store attachments → storage/app/public/chat/attachments
    API-->>STU: HTTP 201 + formatted message (response ทันที ไม่รอ broadcast)

    API->>Q: dispatch MessageSent (ShouldBroadcast)
    Q-->>W: pop job
    W->>REV: publish to private channels
    Note over REV: chat.room.{roomId}<br/>chat.student.{studentId}<br/>admin.inbox
    REV--)ADM: echo .listen('.MessageSent')
    REV--)STU: echo .listen('.MessageSent')

    ADM->>API: POST /inbox/{jobId}/{userId}/read
    API->>PG: update room_user.last_read_at
    API->>Q: dispatch MessagesRead
    W->>REV: publish MessagesRead (read receipt ~instant)
```

**Channels ที่ต้อง authorize ใน `routes/channels.php`:**

| Channel | ผู้ใช้ | เงื่อนไข |
|---|---|---|
| `chat.room.{roomId}` | ทั้งคู่ | เป็นสมาชิกห้อง |
| `chat.student.{userId}` | Student | id ตรงกับตัวเอง |
| `admin.inbox` | Staff/Admin | `isStaffOrAdmin()` |
| `online` (presence) | ทุก role | login แล้ว |

---

## 3. Student Activity Lifecycle (ลงทะเบียน → เช็คอิน → ชั่วโมงกิจกรรม)

```mermaid
flowchart TD
    A["ดูรายการกิจกรรม<br/>GET /activities"] --> B{Login แล้ว?}
    B -- "No" --> B1["Auth: Login (+OTP)<br/>หรือ LINE OAuth"] --> C
    B -- "Yes" --> C["ลงทะเบียนกิจกรรม<br/>POST /activities/{id}/register"]
    C --> D{Admin Approve?}
    D -- pending/reject --> E["รออนุมัติ<br/>/ admin quick-approve"]
    D -- approve --> F["ได้ QR Code เช็คอิน"]
    F --> G["Scan QR หน้างาน<br/>POST /check-in/{token}"]
    G --> H{ตรวจสอบ}
    H -- "QR only" --> I["Check-in สำเร็จ<br/>สร้าง Attendance"]
    H -- "face frame" --> J["POST /check-in/{token}/verify-frame<br/>→ AI Microservice /verify + /liveness"]
    J --> K{Face match + มีชีวิต?}
    K -- No --> L["ปฏิเสธ / ขึ้น Selfie รอ Admin review"]
    K -- Yes --> I
    E --> F
    I --> M["Feedback ประเมินกิจกรรม<br/>POST activities/{id}/feedback"]
    M --> N["สรุปชั่วโมง<br/>GET /summary → PDF"]
    N --> O["เคลมใบรับรอง<br/>POST /student/certificates/claim"]
    O --> P["ตรวจสอบสาธารณะ<br/>GET /certificates/verify/{code}"]
```

---

## 4. Face Verification Flow (Check-in ด้วยใบหน้า)

```mermaid
flowchart TD
    A["Browser จับ frame จากกล้อง"] --> B["POST /api/face/verify<br/>(middleware: face-verify, auth)"]
    B --> C{"มี JS descriptor<br/>บนโปรไฟล์?"}
    C -- Yes --> D["เทียบ descriptor ฝั่ง PHP<br/>(fast path)"]
    C -- No --> E["Forward ไป AI Service"]
    E --> F["FastAPI POST /verify<br/>X-API-Key header"]
    F --> G["YOLOv8 detect face<br/>→ InsightFace extract embedding"]
    G --> H{Compare cosine similarity<br/>กับ biometric ในฐานข้อมูล}
    H -- match --> I["POST /liveness<br/>anti-spoofing check"]
    I --> J{liveness pass?}
    J -- Yes --> K["✅ Verified → บันทึก Attendance"]
    J -- No --> L["❌ Reject (spoof suspected)<br/>log SecurityLog"]
    H -- no match --> L
    K --> M["ExtractFaceBiometricsJob<br/>queue: ai → เก็บ embedding"]
```

---

## 5. Authentication Flow (Student / Staff + OTP)

```mermaid
flowchart TD
    A["GET /login หรือ /admin/login"] --> B["POST credentials<br/>throttle:student-login / staff-login"]
    B --> C{Credentials valid?}
    C -- No --> B
    C -- Yes --> D{Student หรือ Staff?}
    D -- Student --> E["redirect /login/verify-otp<br/>ส่ง OTP"]
    D -- Staff --> F["POST /admin/verify-otp"]
    E --> G{OTP correct?}
    F --> G
    G -- No --> H["resend OTP<br/>POST /login/resend-otp"] --> E
    G -- Yes --> I["Session established<br/>(driver: Valkey)"]
    I --> J{Role?}
    J -- student --> K["activities.index"]
    J -- staff/admin --> L["admin.dashboard"]
    K --> M["LINE OAuth linking<br/>/line/redirect → callback"]
    L --> N["Password reset flow<br/>mail link + OTP"]
```

---

## 6. Queue Jobs Processing Flow

```mermaid
flowchart LR
    subgraph Producers["Producers (Web requests)"]
        P1["Chat broadcast<br/>MessageSent/Read/Edited/Deleted"]
        P2["Excel export<br/>ExportExcelJob"]
        P3["PDF transcript<br/>GeneratePdfTranscriptJob"]
        P4["LINE notify<br/>SendLineNotificationJob"]
        P5["Backup + stats<br/>ProcessAutomatedBackupJob<br/>RecomputeActivityStatisticsJob"]
    end

    V[("Valkey Queue<br/>ai · notifications · exports · cassandra · default")]

    subgraph Workers["Queue Workers (--tries=3 --timeout=120)"]
        W["queue:work redis"]
    end

    P1 & P2 & P3 & P4 & P5 --> V
    V --> W
    W --> OK["✅ Job done"]
    W -->|"fail × 3"| FJ[("failed_jobs table<br/>Admin: /system/failed-jobs<br/>retry / retry-all / flush")]
```

---

## 7. Deployment / Dev Loop (CI → Cluster Nodes)

```mermaid
flowchart TD
    DEV["Developer push"] --> GIT["Git (main branch)"]
    GIT --> DEP["deploy.sh / deploy-native.sh"]
    DEP --> S1["Node S1 (primary)<br/>Laravel + Reverb + workers"]
    DEP -->|"git sync"| S2["Node S2 (secondary/AI)<br/>FastAPI InsightFace + workers"]
    S1 --> NG["Nginx LB / Cloudflare"]
    S2 --> NG
    NG --> U["Users"]
    MON["monitor-ui + watchdog scripts<br/>check_*.py / verify_*.py"] -. health checks .-> S1
    MON -.-> S2
```

---

### Checklist เวลาวาด Flowchart เอง

- Event ทุก broadcast path ต้องผ่าน queue (`ShouldBroadcast`) — อย่าลืมวาด worker แยกจาก web request
- Data tier ฝั่ง production วาดใน `internal network` เสมอ
- Chat events ฟังด้วย `.listen('.MessageSent')` (มี dot นำหน้า เพราะใช้ `broadcastAs()`)
