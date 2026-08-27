# SCREEN FLOWCHART — Uni-Activity (actual working screens)

Flowchart ของ **หน้าจอการใช้งานจริงของเว็บไซต์** ทั้งหมด อ้างอิงจากไฟล์ Blade view (`resources/views/**`) และ `routes/web.php`
รูปแบบ: Mermaid.js (render อัตโนมัติบน GitHub/GitLab) — SVG version: [`flowchart.svg`](./flowchart.svg)

---

## 1) Student Journey — เข้าเว็บครั้งแรก → ใบรับรอง

![Student Journey](./diagrams/01-student-journey.svg)

```mermaid
flowchart TD
    ROOT["/ redirect"] -->|"guest"| LOGIN
    ROOT -->|"auth: staff"| ADM_DASH
    ROOT -->|"auth: student"| ACT_IDX

    subgraph PUBLIC["🌐 Public — ไม่ต้อง login"]
        ACT_IDX["📋 activities/index<br/>GET /activities"]
        ACT_SHOW["📄 activities/show<br/>GET /activities/{id}"]
        ANN_IDX["📢 announcements/index<br/>GET /announcements"]
        JOB_IDX["💼 jobs/index<br/>GET /jobs"]
        JOB_SHOW["📄 jobs/show<br/>GET /jobs/{id}"]
        MAP_IDX["🗺️ map/index<br/>GET /map"]
        CERT_VERIFY["✅ certificates/verify<br/>GET /certificates/verify/{code}"]

        ACT_IDX --> ACT_SHOW
        JOB_IDX --> JOB_SHOW
    end

    subgraph AUTH["🔐 auth/ — login · OTP · register"]
        LOGIN["login.blade<br/>GET /login"]
        REGISTER["register.blade<br/>POST /register"]
        OTP_LOGIN["verify-login-otp.blade<br/>GET /login/verify-otp"]
        STAFF_LOGIN["staff-login.blade<br/>GET /admin/login"]
        OTP_STAFF["verify-otp.blade<br/>GET /admin/verify-otp"]
        FORGOT["forgot-password.blade<br/>GET /forgot-password"]
        RESET["reset-password.blade<br/>GET /reset-password/{token}"]

        LOGIN -->|"credentials ok"| OTP_LOGIN
        OTP_LOGIN -->|"otp pass"| STUDENT_AREA
        STAFF_LOGIN -->|"ok"| OTP_STAFF
        OTP_STAFF -->|"pass"| ADM_DASH
        FORGOT --> RESET
    end

    ACT_SHOW -->|"ลงทะเบียน POST /activities/{id}/register"| REG_WAIT

    subgraph STUDENT_AREA["🎒 Student Area (auth)"]
        REG_WAIT["⏳ รออนุมัติ<br/>(pending request)"]
        MY_ACT["📁 student.my<br/>GET /my-activities"]
        QR_SCAN["📷 checkin/scan.blade<br/>GET /scan"]
        SELFIE["🤳 checkin/selfie.blade<br/>face verify frame"]
        SUCCESS["🎉 checkin/success.blade"]
        HISTORY["🕓 student.history<br/>GET /history"]
        SUMMARY["📊 student.summary<br/>GET /summary"]
        PDF_BTN["🧾 summary.pdf<br/>GET /summary/pdf"]
        CAL["📅 student.calendar<br/>GET /calendar"]
        PROFILE["👤 student.profile<br/>GET /profile"]
        FEEDBACK["⭐ feedback.create<br/>GET activities/{id}/feedback"]
        CHAT_SCR["💬 chat/show.blade<br/>GET /jobs/{id}/chat"]

        REG_WAIT -->|approved| QR_SCAN
        MY_ACT --> QR_SCAN
        QR_SCAN -->|"QR token ok"| SUCCESS
        QR_SCAN -->|"checkin/selfie ต้องยืนยันหน้า"| SELFIE -->|"match+liveness ✓"| SUCCESS
        SELFIE -->|"no match ✗"| QR_SCAN
        SUCCESS --> FEEDBACK
        SUMMARY --> PDF_BTN
        CAL --> ACT_SHOW
        PROFILE --> CHAT_SCR
    end

    MY_ACT --> SUMMARY
    MY_ACT --> HISTORY
    MY_ACT --> CAL
    CHAT_SCR --> CERT_CLAIM["🎓 certificates claim<br/>POST /student/certificates/claim"]
    FEEDBACK --> CERT_CLAIM

    subgraph ADMIN["🖥️ Admin — role:staff+"]
        ADM_DASH["dashboard.blade<br/>GET /admin/dashboard"]
        ADM_ACT["activities admin CRUD<br/>admin/activities/*"]
        CHECKIN_MON["📡 checkin monitor<br/>real-time attendance"]
        INBOX_LIST["📥 inbox/index<br/>GET /admin/inbox"]
        INBOX_CHAT["💬 inbox/show<br/>GET /admin/inbox/{jobId}/{userId}"]
        ADM_APPROVE["quick-approve/reject<br/>POST /admin/quick-*"]

        ADM_DASH --> ADM_ACT
        ADM_DASH --> ADM_APPROVE
        ADM_DASH --> CHECKIN_MON
        ADM_DASH --> INBOX_LIST --> INBOX_CHAT
        ADM_APPROVE -->|"approve"| STUDENT_AREA
    end
```

---

## 2) Check-in Screens Detail

![Check-in detail](./diagrams/02-checkin-detail.svg)

```mermaid
flowchart TD
    START["student opens QR scanner<br/>GET /scan (checkin/scan.blade)"]
    TOKEN{"กวาด QR<br/>สำเร็จ?"}
    SHOW["checkin page<br/>GET /check-in/{token}"]
    VERIFY_FRAME["POST /check-in/{token}/verify-frame"]
    API_FACE["POST /api/face/verify"]
    LIVE{"liveness ✓"}
    MATCH{"face match ✓"}
    STORE["store attendance<br/>DB::transaction"]
    WALKIN["walk-in (staff only)<br/>GET /walkin/{token}"]
    ATT_API["attendees real-time list<br/>GET /walkin/{token}/attendees"]

    START --> TOKEN
    TOKEN -- no --> START
    TOKEN -- yes --> SHOW
    SHOW -->|"auto submit camera frame"| VERIFY_FRAME --> API_FACE
    MATCH -- yes --> LIVE
    MATCH -- no --> REJECT["❌ ปฏิเสธ + log SecurityLog"] --> SHOW
    LIVE -- yes --> STORE
    LIVE -- no --> REJECT
    STORE --> SUCCESS_S["🎉 checkin/success.blade"]
    WALKIN --> ATT_API
```

---

## 3) Realtime Chat Screens

![Chat screens](./diagrams/03-chat-screens.svg)

```mermaid
flowchart LR
    THREADS["chat threads<br/>GET /chat/threads"]
    ROOM["chat/show.blade<br/>GET /jobs/{jobId}/chat"]
    INBOX["inbox/index<br/>GET /admin/inbox"]
    INBOX_ROOM["inbox/show<br/>GET /admin/inbox/{jobId}/{userId}"]
    WS_ENV{{".listen('.MessageSent')<br/>.listen('.MessagesRead')<br/>channel chat.room.{id}<br/>admin.inbox"}}

    THREADS --> ROOM
    INBOX --> INBOX_ROOM
    ROOM <-->|"send/read/edit/delete messages"| WS_ENV
    INBOX_ROOM <-->|same events| WS_ENV
```

---

### Screen inventory (from `resources/views`)

| Area | Screens |
|---|---|
| Public | activities index/show, announcements, jobs index/show, map, certificate verify |
| Auth | login, register, verify-login-otp, staff-login, verify-otp, forgot/reset-password |
| Student | my-activities, history, summary (+PDF), calendar, profile, scanner, selfie, success |
| Chat | chat/show, threads |
| Admin | dashboard, activities/*, inbox, students, users, categories, exports, audit-logs, security, backups, settings, api-keys, cluster, failed-jobs |
