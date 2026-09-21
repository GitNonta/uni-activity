# fdx decoder migration — activity check

The AI server's face decoder for activity check is **fdx** (the custom
MobileFaceNet-512D w600k_mbf.fvp on the zero-dependency D3D11 engine),
selected after testing. Verified: `test_fdx_backend.py` →
`reports/fdx_backend_test.json` (7/7).

## Native face stack (v2.4) — the `insightface` package is gone

Since v2.4 the server does **not** import the `insightface` pip package at
all. `ai_service/native_face.py` runs the same onnx models through raw
onnxruntime sessions:

| stage | before (≤ v2.3) | now (v2.4) |
|---|---|---|
| detection + 5-point landmarks | `insightface.app.FaceAnalysis` (SCRFD det_10g) | `NativeSCRFD` (det_10g.onnx, same decode/NMS/letterbox math) |
| norm_crop alignment | `insightface.utils.face_align` | `native_face.norm_crop` (same arcface_dst template + SimilarityTransform) |
| fallback embedding | ArcFaceONNX via `model_zoo.get_model` | `NativeArcFace` (w600k_mbf.onnx, same 127.5 preprocessing) |

Parity was proven on real CelebA images before the switch
(`face_dx/_native_parity_check.py` → `face_dx/reports/native_parity_check.json`):
**bbox IoU 1.0, kps max delta 0.0 px, norm_crop diff 0, embedding cos 1.0** —
numerically identical to the package it replaces. Models are unchanged;
only the runtime packaging is. `requirements.txt` no longer lists
`insightface` (the model files themselves are still resolved from the same
locations, including `INSIGHTFACE_MODELS_DIR`).

## Embedding-space parity (v2.3)

Both embedders now run the SAME network (w600k_mbf / MobileFaceNet):

| embedder | weights | measured cos vs fdx (aligned crop) |
|---|---|---|
| fdx D3D11 engine | w600k_mbf.fvp (fp16) | — |
| native ArcFace fallback (v2.4) | w600k_mbf.onnx (pinned path) | **0.99998** / 1.0000 |

`face_dx/alignment_probe.py` proves this: on the same norm_crop 112×112
crop, fdx and ONNX-mbf agree to fp16 precision, while the earlier
FaceAnalysis default (w600k_r50, ResNet50 — picked first-match-wins from a
sorted glob before v2.3) scored cos ≈ −0.02 against fdx. That hazard is
structurally gone since v2.4: there is no pack globbing anymore — both
onnx files are loaded from explicit pinned paths.

The recognition onnx is resolved from (first hit wins):
1. `INSIGHTFACE_MODELS_DIR` env var
2. `~/.insightface/models/buffalo_l/w600k_mbf.onnx`
3. `~/.insightface/models/buffalo_s/w600k_mbf.onnx`
4. `ai_service/models/w600k_mbf.onnx`

If none exist the server refuses to start (fail-closed) instead of
silently running a mismatched space. buffalo_l ships w600k_r50 only, so
copy `face_dx/models/w600k_mbf.onnx` into the pack dir (or point
`INSIGHTFACE_MODELS_DIR` at `face_dx/models`).

## Alignment contract (v2.3)

The fdx path only accepts **5-point landmark norm_crop** faces. The old
plain-resize fallback is gone: a non-aligned crop lands elsewhere in the
embedding space (measured cos(aligned, plain) ≈ 0.30 on the same
identity). When SCRFD provides no landmarks the request automatically
uses the insightface fallback — which now shares the fdx space, so
enroll/verify stay consistent either way.

## What changed

| area | behavior |
|---|---|
| `/extract` | returns the **fdx** 512-d vector as `embedding_512d` with `embedding_space: "fdx-w600k-mbf"`; the insightface vector is still included as `embedding_insightface_512d` during the migration window; `embedding_128d` is `null` in fdx mode (PCA must be re-fit on fdx vectors first). Accepts up to 5 profile photos (`image` + repeated `images` multipart field) and returns one L2-normalized **centroid** — see "Ensemble enrollment" below |
| `/verify` | compares the stored vector against the **fdx** embedding; threshold from `FDX_MATCH_THRESHOLD` (default **0.30**); falls back to native ArcFace comparison only if the engine fails mid-request |
| fallback | engine unavailable at startup (no DLL / no GPU / bad model) → server logs a warning and **both endpoints silently use native ArcFace** (fail-open, `embedder: "native-arcface"`); `/health` shows `embedder` + `fdx.reason` |
| `/health` | new fields: `models.fdx`, `embedder`, `fdx` (adapter info, threshold) |

## ⚠ Re-enrollment is mandatory (embedding spaces are incompatible)

fdx and InsightFace ArcFace are different models — their 512-d spaces share
no basis. Measured on the same face: **cos(fdx, insightface) ≈ 0.037**
(near zero). Every stored activity-check vector extracted with the old
decoder is meaningless against fdx comparisons and vice versa.

Migration order:

1. Deploy this server version (fdx enabled).
2. Re-run enrollment: for every user, `POST /extract` on their profile
   photo and store the new `embedding_512d` (now fdx-space).
3. Only after all vectors are re-extracted, remove the
   `embedding_insightface_512d` migration field if you don't need it.

Until re-enrollment completes, verifications against old vectors will
score near zero (fail-closed, no false accepts — but no true accepts
either). The `embedding_space` tag in `/extract` responses lets the
backend verify which space a stored vector came from.

> v2.3 note: after this one-time re-enrollment, the insightface fallback
> and fdx produce interchangeable vectors (cos 0.99998), so future
> embedder switches no longer require re-enrollment as long as both stay
> on w600k_mbf weights + norm_crop alignment.

## Ensemble enrollment (v2.4)

`/extract` now accepts up to **5 profile photos**: the required `image`
field plus extras in the repeated multipart field `images`. Every photo
must contain exactly one face; failed photos are skipped as long as at
least one succeeds. All valid vectors are averaged into one
L2-normalized **centroid**, returned as `embedding_512d` — so client
storage and `/verify` are unchanged (still one 512-d vector).

Response additions: `enrolled_images` (count) and `enrollment`
(`"single"` or `"centroid"`). A single-photo request behaves exactly as
before (`enrollment: "single"`).

```http
POST /extract
Content-Type: multipart/form-data

image     = profile_1.jpg      (required)
images    = profile_2.jpg      (repeated field, optional)
images    = profile_3.jpg
images    = profile_4.jpg
images    = profile_5.jpg
```

The backend should ask users for **5 different profile photos** (different
days/poses, not near-duplicates). Per-identity vectors in the ensemble cut
false rejects sharply: at the same FAR (~5e-6), FRR@0.40 drops from 20.5%
(single photo) to **5.3%** (centroid-of-5) on 500 CelebA identities.

## Threshold calibration (CelebA ground truth, 500 identities × 8 images)

Real identity labels (`identity_CelebA.txt`), production contract (norm_crop
112×112 → fdx engine, 5-image centroid enrollment, seed 0). Full report:
`face_dx/reports/threshold_calibration_500.json` (the earlier 100-id × 12
single-photo run is archived in `threshold_calibration.json`).

| metric | centroid-of-5 | single-photo (reference) |
|---|---|---|
| same-person cosine | mean **0.653**, p5 **0.390** (n=1496) | mean 0.516, p5 0.207 |
| different-person cosine | p95 **0.127**, max 0.658 (n=746,504) | p95 0.121 |
| AUC / d-prime | **0.9926** / **5.60** | 0.9866 / 4.05 |
| TAR @ FAR 1e-1 / 1e-2 / 1e-3 | **0.986 / 0.978 / 0.973** | 0.973 / 0.957 / 0.939 |

Exact FAR/FRR sweep (centroid-of-5):

| threshold | FAR | FRR |
|---|---|---|
| 0.20 | 0.64% | 2.3% |
| **0.30 (default)** | **0.021%** | **3.0%** |
| 0.256 | 0.10% | 2.7% |
| 0.40 | ~5e-6 | 5.3% |
| 0.50 | ~1e-6 | 11.0% |

Operating-point guidance (centroid enrollment):

- **0.30 (default)** — zero-FAR-equivalent point from the 100-id run; on
  500 ids it costs 0.021% FAR with 3.0% FRR. Good default for activity
  checks where a rare false accept is tolerable.
- **0.40** — high-security mode: FAR ~5e-6 (1 in 187k impostor pairs),
  FRR 5.3% with a 5-photo ensemble (20.5% without). Raise
  `FDX_MATCH_THRESHOLD` for fully-controlled kiosk capture only.
- **0.20 floor** — convenience mode (FAR 0.64%). Do not go below 0.20:
  the diff-max 0.658 outlier means sub-0.20 thresholds trade real
  impostor risk for marginal FRR gains.

The earlier 0.60 default rejected nearly all legitimate attempts —
aligned-crop scores sit much lower than plain-resize-era scores because
norm_crop preserves real pose variation.

Calibration was done on aligned 112×112 CelebA crops (the same alignment
the production pipeline uses); plain-resize ROI crops were the engine's
preprocessing-parity reference (cos 1.000000000 vs ground truth).

## Environment

| var | default | meaning |
|---|---|---|
| `USE_FDX` | `1` | set `0` to disable fdx entirely (native ArcFace only) |
| `FDX_MATCH_THRESHOLD` | `0.30` | fdx verify threshold (mbf space, calibrated) |
| `FACE_MATCH_THRESHOLD` | `0.30` | native-fallback threshold (same w600k_mbf space) |
| `FDX_GPU_INDEX` | `-1` | `-1` auto GPU, `-2` force WARP (CPU), `≥0` adapter index |
| `FDX_FP16` | `1` | fp16 weight storage + fp32 accumulation (production) |

## Runtime notes

- The fdx wheel (`face_dx/pylib/dist/*.whl`) is the preferred install;
  the repo fallback (`face_dx/pylib`) requires `face_dx/build/face_dx.dll`.
- One engine per process, thread-marshalled inside `fdx_backend` —
  FastAPI's threadpool is safe but serializes fdx work (~35–50 ms/img).
  Scale by process, not thread.
- GPU device loss mid-request → that request falls back to the native
  ArcFace (same w600k_mbf space, embeddings stay comparable);
  call `POST /health` polling + `fdx_backend.reinit()` from a maintenance
  endpoint if you want automatic recovery.
