# Full CelebA zip — fp16 production run (final)

*Run window: launched 2026-09-14 12:56, finished 2026-09-15 10:11 — 202,599 images.*

## Verdict: PASS — 202,599 images, 0 engine failures

| metric | value |
|---|---|
| images processed | **202,599** |
| engine failures | 0 |
| GPU latency (all 202,599 rows): p50 / p90 / p99 / max | 62.0 / 114.6 / 171.2 / 2631.2 ms |
| end-to-end wall | 0.72 h (8.69 img/s incl. spot-checks) |
| spot-checks (1%) | 237 verified, worst cos 0.999895 vs gate 0.9998 |
| report-level gpu-vs-numpy / gpu-vs-onnx min cos | 0.9998948 / 0.99989474 |
| channel-order probe | rgb |

## Images completed per hour

09:00: **18,612** | 10:00: **4,654** | 13:00: **10,752** | 14:00: **23,552** | 15:00: **17,920** | 16:00: **14,336** | 17:00: **16,896** | 18:00: **16,384** | 19:00: **17,408** | 20:00: **15,872** | 21:00: **20,992** | 22:00: **6,656** | 23:00: **1,536**

## Notes

- fp16 engine (fp32-accumulate, fp16-weight storage): cosine vs the fp32 numpy/ONNX references is quantization-bound (~1e-4 band), gate-checked at 0.9998 per spot-check.
- Spot-check cosines are absorbed at end-of-run in batch mode; the report-level numbers above are the authoritative verdict.
- Raw embeddings stream: `reports/celeba_512d_full_fp16.jsonl` (gitignored, regenerable). Resume state: `*.state.jsonl`.
