# fdx decoder migration — activity check

The AI server's face decoder for activity check is **fdx** (the custom
MobileFaceNet-512D w600k_mbf.fvp on the zero-dependency D3D11 engine),
selected after testing. Verified: `test_fdx_backend.py` →
`reports/fdx_backend_test.json` (7/7).

## Embedding-space parity (v2.3)

Both embedders now run the SAME network (w600k_mbf / MobileFaceNet):

| embedder | weights | measured cos vs fdx (aligned crop) |
|---|---|---|
| fdx D3D11 engine | w600k_mbf.fvp (fp16) | — |
| insightface fallback | w600k_mbf.onnx (pinned via model_zoo.get_model) | **0.99998** |

`face_dx/alignment_probe.py` proves this: on the same norm_crop 112×112
crop, fdx and ONNX-mbf agree to fp16 precision, while the previous
FaceAnalysis default (w600k_r50, ResNet50 — picked first-match-wins from a
sorted glob) scored cos ≈ −0.02 against fdx. If you ever see
`server_vs_engine` cosines near zero again, check which recognition onnx
the server actually loaded (`/health` → `fdx` + startup log line
`InsightFace loaded ✓ (recognition=..., model=w600k_mbf.onnx)`).

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

> v2.3 note: after this one-time re-enrollment, the insightface fallback
> and fdx produce interchangeable vectors (cos 0.99998), so future
> embedder switches no longer require re-enrollment as long as both stay
> on w600k_mbf weights + norm_crop alignment.

## Threshold calibration (CelebA, 5 identities, norm_crop 112×112)

- same-person (identity-preserving transforms): cosine **0.42–0.91**
- different-person: **−0.05–0.27**
- separation margin ≈ 0.15 → default **0.40** balances FAR/FRR for kiosk
  selfie verification; tighten toward 0.55 in controlled lighting,
  loosen toward 0.30 for wide-pose webcam captures.

(Aligned crops compress the band vs the old plain-resize calibration
because norm_crop preserves more pose variation — same-face scores now
range wider, so the old 0.60 default would reject legitimate users.)

Calibration was done on aligned 112×112 CelebA crops (the same alignment
the production pipeline uses); plain-resize ROI crops were the engine's
preprocessing-parity reference (cos 1.000000000 vs ground truth).

## Environment

| var | default | meaning |
|---|---|---|
| `USE_FDX` | `1` | set `0` to disable fdx entirely (insightface only) |
| `FDX_MATCH_THRESHOLD` | `0.40` | fdx verify threshold (mbf space) |
| `FACE_MATCH_THRESHOLD` | `0.40` | insightface-fallback threshold (same space now) |
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
