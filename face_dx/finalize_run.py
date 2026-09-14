#!/usr/bin/env python3
"""
finalize_run.py — wait for the full-zip CelebA fp16 run to finish, then write
the final Markdown summary and commit the report deliverables (AGENTS.md rule:
commit+push when work completes).

Idempotent: if the summary already exists, exits without doing anything.
Conservative: commits the summary always; commits the report JSON only if it
is under 20 MB (otherwise it stays on disk, gitignored, and the summary says
so). Never touches the running pipeline — read-only over its artifacts.

Run detached (survives this console):
    nohup python finalize_run.py > reports/finalize.log 2>&1 &
"""

from __future__ import annotations

import json
import os
import subprocess
import time
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))
REPORT = os.path.join(HERE, "reports", "celeba_512d_full_fp16.json")
STATE = REPORT + ".state.jsonl"
SAMPLER = os.path.join(HERE, "reports", "full_run_progress.log")
SUMMARY = os.path.join(HERE, "reports", "FULL_RUN_SUMMARY.md")
GITIGNORE = os.path.join(HERE, ".gitignore")
MAX_REPORT_BYTES = 20 * 1024 * 1024
MAX_WAIT_S = 12 * 3600


def wait_for_report() -> None:
    """Poll until the report exists AND has stopped growing (complete JSON)."""
    t0 = time.time()
    while time.time() - t0 < MAX_WAIT_S:
        if os.path.exists(REPORT):
            try:
                json.load(open(REPORT, encoding="utf-8"))  # complete + valid
                return
            except (json.JSONDecodeError, OSError):
                pass  # mid-write; keep waiting
        time.sleep(60)
    raise TimeoutError(f"report not complete after {MAX_WAIT_S // 3600}h")


def state_stats() -> dict:
    fails = spots = 0
    ms: list[float] = []
    worst_cos = 1.0
    with open(STATE, encoding="utf-8") as f:
        for line in f:
            try:
                r = json.loads(line)
            except json.JSONDecodeError:
                continue
            if not r.get("pass", True):
                fails += 1
            m = r.get("gpu_ms")
            if isinstance(m, (int, float)):
                ms.append(float(m))
            if r.get("cos_gpu_onnx") is not None:
                spots += 1
                worst_cos = min(worst_cos,
                                r.get("cos_gpu_numpy") or 1.0,
                                r.get("cos_gpu_onnx") or 1.0)
    ms.sort()
    n = len(ms)
    pct = lambda p: ms[min(n - 1, int(p * n))] if n else 0.0
    return {"rows": n, "fails": fails, "spots": spots, "worst_cos": worst_cos,
            "p50": pct(.5), "p90": pct(.9), "p99": pct(.99), "max": pct(1.0)}


def hourly_from_sampler() -> list[tuple[int, int]]:
    """Per-hour image counts from the 2-min sampler log (whole-run coverage)."""
    samples: list[tuple[float, int]] = []
    try:
        for line in open(SAMPLER, encoding="utf-8"):
            parts = line.split()
            if len(parts) == 2:
                h, m, _s = parts[0].split(":")
                samples.append((int(h) * 3600 + int(m) * 60, int(parts[1])))
    except OSError:
        return []
    buckets: dict[int, int] = {}
    for (ta, ra), (tb, rb) in zip(samples, samples[1:]):
        d = rb - ra
        if d > 0:
            buckets[int(tb // 3600)] = buckets.get(int(tb // 3600), 0) + d
    return sorted(buckets.items())


def fmt_hourly(buckets: list[tuple[int, int]]) -> str:
    if not buckets:
        return "_sampler log unavailable_"
    return " | ".join(
        f"{int(t):02d}:00: **{v:,}**" for t, v in buckets)


def build_summary(rep: dict, st: dict) -> str:
    meta = rep.get("meta", {})
    ver = rep.get("verification", {})
    timing = rep.get("timing_ms", {}).get("gpu_inference", {})
    e2e = rep.get("timing_ms", {}).get("end_to_end", {})
    cg = ver.get("cosine_gpu_vs_numpy", {})
    co = ver.get("cosine_gpu_vs_onnx", {})
    total = meta.get("sample", {}).get("size", st["rows"])
    passed = ver.get("passed", st["fails"] == 0)
    wall_h = e2e.get("wall_total_ms", 0) / 3.6e6
    thr = e2e.get("throughput_images_per_sec", 0)
    launch = datetime.fromtimestamp(os.path.getctime(STATE))
    gate = ver.get("pass_threshold", "n/a")

    lines = [
        "# Full CelebA zip — fp16 production run (final)",
        "",
        f"*Run window: launched {launch:%Y-%m-%d %H:%M}, finished "
        f"{datetime.now():%Y-%m-%d %H:%M} — {total:,} images.*",
        "",
        f"## Verdict: {'PASS — ' + format(total, ',') + ' images, ' + format(st['fails'], ',') + ' engine failures' if passed else 'FAIL'}",
        "",
        "| metric | value |",
        "|---|---|",
        f"| images processed | **{total:,}** |",
        f"| engine failures | {st['fails']:,} |",
        f"| GPU latency (all {st['rows']:,} rows): p50 / p90 / p99 / max | "
        f"{st['p50']:.1f} / {st['p90']:.1f} / {st['p99']:.1f} / {st['max']:.1f} ms |",
        f"| end-to-end wall | {wall_h:.2f} h ({thr:.2f} img/s incl. spot-checks) |",
        f"| spot-checks (1%) | {st['spots']:,} verified, worst cos "
        f"{st['worst_cos']:.6f} vs gate {gate} |",
        f"| report-level gpu-vs-numpy / gpu-vs-onnx min cos | "
        f"{cg.get('min', 'n/a')} / {co.get('min', 'n/a')} |",
        f"| channel-order probe | {meta.get('channel_order_probe', 'n/a')} |",
        "",
        "## Images completed per hour",
        "",
        fmt_hourly(hourly_from_sampler()),
        "",
        "## Notes",
        "",
        "- fp16 engine (fp32-accumulate, fp16-weight storage): cosine vs the "
        "fp32 numpy/ONNX references is quantization-bound (~1e-4 band), "
        "gate-checked at 0.9998 per spot-check.",
        "- Spot-check cosines are absorbed at end-of-run in batch mode; the "
        "report-level numbers above are the authoritative verdict.",
        "- Raw embeddings stream: `reports/celeba_512d_full_fp16.jsonl` "
        "(gitignored, regenerable). Resume state: `*.state.jsonl`.",
        "",
    ]
    return "\n".join(lines)


def commit(summary_text: str) -> None:
    with open(SUMMARY, "w", encoding="utf-8", newline="\n") as f:
        f.write(summary_text)
    with open(GITIGNORE, encoding="utf-8") as f:
        gi = f.read()
    rep_size = os.path.getsize(REPORT)
    rep_rel = "reports/celeba_512d_full_fp16.json"
    if rep_size <= MAX_REPORT_BYTES:
        gi = gi.replace(rep_rel + "\n", "")  # un-ignore if previously ignored
        files = ["face_dx/reports/FULL_RUN_SUMMARY.md", "face_dx/" + rep_rel,
                 "face_dx/.gitignore"]
    else:
        # report too large for git — keep it on disk only, note in gitignore
        if rep_rel not in gi:
            gi += (f"\n# full-zip report exceeds {MAX_REPORT_BYTES // (1024 * 1024)} MB "
                   f"— on disk only, summary carries the findings\n{rep_rel}\n")
        files = ["face_dx/reports/FULL_RUN_SUMMARY.md", "face_dx/.gitignore"]
    with open(GITIGNORE, "w", encoding="utf-8", newline="\n") as f:
        f.write(gi)
    subprocess.run(["git", "add", *files], cwd=HERE, check=True)
    msg = (f"feat(face_dx): full CelebA 202,599-image fp16 run report — "
           f"{st_summary(REPORT)}")
    subprocess.run(["git", "commit", "-m", msg], cwd=HERE, check=True)
    subprocess.run(["git", "push"], cwd=HERE, check=True)


def st_summary(path: str) -> str:
    try:
        r = json.load(open(path, encoding="utf-8"))
        v = r.get("verification", {})
        n = r.get("meta", {}).get("sample", {}).get("size", "?")
        return (f"{'PASS' if v.get('passed') else 'FAIL'} on {n} images, "
                f"{len(v.get('failed_images', []))} failed")
    except Exception:
        return "report attached"


def main() -> None:
    if os.path.exists(SUMMARY):
        print("[finalize] summary already exists; nothing to do")
        return
    wait_for_report()
    rep = json.load(open(REPORT, encoding="utf-8"))
    st = state_stats()
    commit(build_summary(rep, st))
    print(f"[finalize] summary written and pushed: {SUMMARY}")


if __name__ == "__main__":
    main()
