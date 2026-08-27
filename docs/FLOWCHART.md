# PROJECT FLOWCHART — Uni-Activity

ภาพรวม Flowchart ของระบบทั้งหมดในไฟล์เดียว มี 2 รูปแบบ: **SVG** (ไฟล์แยก) และ **Mermaid.js** (render อัตโนมัติบน GitHub/GitLab)

- ไฟล์ SVG: [`docs/flowchart.svg`](./flowchart.svg)
- เวอร์ชันเชิงลึกแยกรายฟีเจอร์: [`docs/FLOWCHARTS.md`](./FLOWCHARTS.md)

---

## 1) SVG version

![Uni-Activity System Flowchart](./flowchart.svg)

---

## 2) Mermaid.js version

```mermaid
flowchart TB
    subgraph Clients["Clients"]
        S["Student Browser<br/>Web / Mobile"]
        A["Admin Dashboard<br/>staff · admin"]
    end

    LB["Nginx Load Balancer<br/>HTTP :80/443 · WebSocket :8080"]

    subgraph LARAVEL["Laravel Application (node-1 / node-2)"]
        C["Controllers · FormRequest · Sanctum auth"]
        SVC["Services<br/>(ChatService, FaceVerification…)"]
        REPO["Repositories · DB::transaction"]
        C --> SVC --> REPO
    end

    PG[("PostgreSQL 16<br/>uni_activity · internal network")]
    VK[("Valkey / Dragonfly<br/>cache · session · queue · pub/sub")]

    RV["Reverb WebSocket :8080"]
    QW["Queue Workers<br/>ai · notifications · exports · default"]

    LINE["LINE Messaging API<br/>push notifications"]
    AI["AI Microservice FastAPI<br/>/extract · /verify · /liveness"]

    S -- HTTPS --> LB
    A -- HTTPS --> LB
    LB --> C

    REPO -- SQL --> PG
    REPO -- "cache / session / dispatch jobs" --> VK
    VK -- consume --> QW
    QW -- notifications --> LINE
    SVC -- "REST + X-API-Key" --> AI
    VK -.-> RV

    RV -. "wss broadcast" .-> S
    RV -. "wss broadcast" .-> A

    style LB fill:#dbeafe,stroke:#2563eb
    style LARAVEL fill:#f0fdf4,stroke:#16a34a
    style PG fill:#fef3c7,stroke:#d97706
    style VK fill:#fef3c7,stroke:#d97706
    style RV fill:#f3e8ff,stroke:#9333ea
    style QW fill:#f3e8ff,stroke:#9333ea
    style LINE fill:#fee2e2,stroke:#dc2626
    style AI fill:#fee2e2,stroke:#dc2626
```

### Realtime chat path (ย่อ)

```mermaid
sequenceDiagram
    autonumber
    participant STU as Student
    participant API as Laravel API
    participant Q as Valkey Queue
    participant REV as Reverb WS
    participant ADM as Admin

    STU->>API: POST /jobs/{jobId}/chat
    API-->>STU: 201 (ตอบทันที)
    API->>Q: dispatch MessageSent (ShouldBroadcast)
    Q->>REV: queue worker publish
    REV--)STU: .listen('.MessageSent')
    REV--)ADM: .listen('.MessageSent') → admin.inbox
```
