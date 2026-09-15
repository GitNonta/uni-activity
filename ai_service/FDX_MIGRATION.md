# fdx decoder migration — activity check

The AI server's face decoder for activity check is now **fdx** (the custom
MobileFaceNet-512D w600k_mbf.fvp on the zero-dependency D3D11 engine),
selected after testing. Verified: `test_fdx_backend.py` →
`reports/fdx_backend_test.json` (7/7).

## What changed

| area | behavior |
|---|---|
| `/extract` | returns the **fdx** 512-d vector as `embedding_512d` with `embedding_space: "fdx-w600k-mbf"`; the insightface vector is still included as `embedding_insightface_512d` during the migration window; `embedding_128d` is `null` in fdx mode (PCA must be re-fit on fdx vectors first) |
| `/verify` | compares the stored vector against the **fdx** embedding; threshold from `FDX_MATCH_THRESHOLD` (default **0.60**); falls back to insightface comparison only if the engine fails mid-request |
| fallback | engine unavailable at startup (no DLL / no GPU / bad model) → server logs a warning and **both endpoints silently use insightface ArcFace** (fail-open); `/health` shows `embedder` + `fdx.reason` |
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

## Threshold calibration (CelebA, 5 identities)

- same-person (identity-preserving transforms): cosine **0.858–0.911**
- different-person: **0.019–0.212**
- separation margin: **0.646** → default **0.60** sits close to the
  same-person floor; tighten toward 0.75 for a stricter gate if your
  capture conditions are controlled (kiosk).

Calibration was done on aligned 112×112 CelebA crops (the same alignment
the production pipeline uses); plain-resize ROI crops were the engine's
preprocessing-parity reference (cos 1.000000000 vs ground truth).

## Environment

| var | default | meaning |
|---|---|---|
| `USE_FDX` | `1` | set `0` to disable fdx entirely (insightface only) |
| `FDX_MATCH_THRESHOLD` | `0.60` | fdx verify threshold |
| `FDX_GPU_INDEX` | `-1` | `-1` auto GPU, `-2` force WARP (CPU), `≥0` adapter index |
| `FDX_FP16` | `1` | fp16 weight storage + fp32 accumulation (production) |

## Runtime notes

- The fdx wheel (`face_dx/pylib/dist/*.whl`) is the preferred install;
  the repo fallback (`face_dx/pylib`) requires `face_dx/build/face_dx.dll`.
- One engine per process, thread-marshalled inside `fdx_backend` —
  FastAPI's threadpool is safe but serializes fdx work (~35–50 ms/img).
  Scale by process, not thread.
- GPU device loss mid-request → that request falls back to insightface;
  call `POST /health` polling + `fdx_backend.reinit()` from a maintenance
  endpoint if you want automatic recovery.
