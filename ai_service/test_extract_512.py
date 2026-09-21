#!/usr/bin/env python3
"""test_extract_512.py — verify the system extracts a valid 512-d face
embedding from an uploaded profile image, end-to-end over HTTP.

Checks (all against the live server, default http://127.0.0.1:8001):
  1. contract        : /extract returns 512 dims, unit L2 norm, fdx space tag,
                       embedder tag, bbox, insightface ride-along vector
  2. cross_space     : fdx and insightface vectors for the same face are NOT
                       comparable (|cosine| < 0.35) — proves fdx was used and
                       re-enrollment from insightface vectors is mandatory
  3. determinism     : extracting the identical upload twice is bit-stable
                       (cosine == 1.0) — reproducible enrollment
  4. formats         : JPEG and PNG uploads of the same photo both extract and
                       agree (cosine >= 0.995) — format-agnostic pipeline
  5. separation      : same-person probe (mirror/zoom) vs different-person
                       image — cosine gap must exceed fdx-calibrated thresholds:
                         same-person  >= 0.75  (calibrated band: 0.858–0.911)
                         diff-person  <= 0.35  (calibrated band: 0.019–0.212)
                         margin       >= 0.45  (calibrated actual: ~0.876)
  6. no-face path    : a blank image is rejected with HTTP 400, not a garbage
                       embedding — fail-correctly

Report: reports/extract_512_test.json
Usage:  python test_extract_512.py [host:port]
"""
import io
import json
import os
import sys
import urllib.error
import urllib.request
import uuid
import zipfile

import cv2
import numpy as np

HOST = sys.argv[1] if len(sys.argv) > 1 else "127.0.0.1:8001"
BASE = f"http://{HOST}"
KEY = "uni-activity-ai-secret-key-2026"
HERE = os.path.dirname(os.path.abspath(__file__))
ZIP_PATH = os.path.abspath(os.path.join(HERE, "..", "face_cpp", "img_align_celeba.zip"))
REPORT_DIR = os.path.join(HERE, "reports")

# ── fdx-calibrated thresholds (CelebA, 5 identities, flip probes) ────────────
# same-person band: 0.858–0.911 → test floor 0.75 (generous but fdx-specific)
# diff-person band: 0.019–0.212 → test ceiling 0.35
# separation margin: ~0.876     → minimum accepted 0.45
FDX_SAME_FLOOR  = 0.75
FDX_DIFF_CEIL   = 0.35
FDX_MIN_MARGIN  = 0.45
# Cross-space incompatibility ceiling: |cos(fdx, insightface)| must be below this
FDX_CROSS_CEIL  = 0.35   # measured 0.037 in calibration

results: dict = {"host": BASE, "checks": {}, "pass": True}


def jpg(img: np.ndarray, q: int = 95) -> bytes:
    ok, buf = cv2.imencode(".jpg", img, [cv2.IMWRITE_JPEG_QUALITY, q])
    assert ok
    return buf.tobytes()


def png(img: np.ndarray) -> bytes:
    ok, buf = cv2.imencode(".png", img)
    assert ok
    return buf.tobytes()


def multipart(files: dict, extra: dict | None = None) -> tuple[bytes, str]:
    b = uuid.uuid4().hex
    out = io.BytesIO()
    for k, v in (extra or {}).items():
        out.write(f"--{b}\r\nContent-Disposition: form-data; name=\"{k}\"\r\n\r\n{v}\r\n".encode())
    for fname, (payload, ctype) in files.items():
        out.write(f"--{b}\r\nContent-Disposition: form-data; name=\"image\"; filename=\"{fname}\"\r\n"
                  f"Content-Type: {ctype}\r\n\r\n".encode())
        out.write(payload)
        out.write("\r\n".encode())
    out.write(f"--{b}--\r\n".encode())
    return out.getvalue(), f"multipart/form-data; boundary={b}"


def call(path: str, data: bytes, ct: str) -> tuple[int, dict]:
    req = urllib.request.Request(BASE + path, data=data, method="POST")
    req.add_header("Content-Type", ct)
    req.add_header("X-API-Key", KEY)
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            return r.status, json.loads(r.read().decode())
    except urllib.error.HTTPError as e:
        try:
            return e.code, json.loads(e.read().decode())
        except Exception:
            return e.code, {}


def record(name: str, ok: bool, detail: str) -> None:
    results["checks"][name] = {"pass": bool(ok), "detail": detail}
    results["pass"] = results["pass"] and bool(ok)
    print(f"[{'PASS' if ok else 'FAIL'}] {name}: {detail}", flush=True)


def check_contract(img_bytes: bytes) -> dict:
    """Check 1 — API contract: dims, norm, space tag, embedder tag, bbox, ride-along."""
    d, ct = multipart({"profile.jpg": (img_bytes, "image/jpeg")})
    status, r = call("/extract", d, ct)
    if status != 200 or not r.get("embedding_512d"):
        record("contract", False, f"HTTP {status}: {r}")
        return {}
    emb = np.asarray(r["embedding_512d"], dtype=np.float32)
    norm = float(np.linalg.norm(emb))
    ins = r.get("embedding_insightface_512d")
    ok = (emb.shape == (512,)
          and abs(norm - 1.0) < 1e-5
          and r.get("embedding_space") == "fdx-w600k-mbf"
          and r.get("embedder") == "fdx-d3d11"
          and bool(r.get("bbox"))
          and isinstance(ins, list) and len(ins) == 512)
    record("contract", ok,
           f"dims={emb.shape[0]} norm={norm:.6f} space={r.get('embedding_space')} "
           f"embedder={r.get('embedder')} bbox={r.get('bbox')} "
           f"insightface={'present' if isinstance(ins, list) else 'missing'}")
    return r


def check_cross_space(contract_response: dict) -> None:
    """Check 2 — fdx and insightface vectors for the same face must NOT be comparable.

    |cos(fdx, insightface)| < FDX_CROSS_CEIL (measured ~0.037).
    This proves the fdx encoder was genuinely used (not silently bypassed)
    and documents that re-enrollment is mandatory when switching encoders.
    """
    emb_fdx = np.asarray(contract_response.get("embedding_512d", []), dtype=np.float32)
    ins_raw = contract_response.get("embedding_insightface_512d")
    if not isinstance(ins_raw, list) or len(ins_raw) != 512:
        record("cross_space", False,
               "embedding_insightface_512d missing or wrong length — cannot verify cross-space incompatibility")
        return
    emb_ins = np.asarray(ins_raw, dtype=np.float32)
    cross = float(np.dot(emb_fdx, emb_ins))
    ok = abs(cross) < FDX_CROSS_CEIL
    record("cross_space", ok,
           f"|cos(fdx, insightface)|={abs(cross):.4f} (must be < {FDX_CROSS_CEIL}) "
           f"— {'incompatible spaces confirmed' if ok else 'WARNING: spaces appear compatible, re-enrollment may be broken'}")


def check_determinism(img_bytes: bytes) -> np.ndarray | None:
    """Check 3 — same upload twice must produce bit-identical embedding (cosine == 1.0)."""
    d1, ct1 = multipart({"a.jpg": (img_bytes, "image/jpeg")})
    d2, ct2 = multipart({"a.jpg": (img_bytes, "image/jpeg")})
    s1, r1 = call("/extract", d1, ct1)
    s2, r2 = call("/extract", d2, ct2)
    if s1 != 200 or s2 != 200:
        record("determinism", False, f"HTTP {s1}/{s2}")
        return None
    e1 = np.asarray(r1["embedding_512d"], dtype=np.float32)
    e2 = np.asarray(r2["embedding_512d"], dtype=np.float32)
    cos = float(np.dot(e1, e2))
    record("determinism", cos == 1.0, f"re-extract cosine = {cos!r} (must be exactly 1.0)")
    return e1


def check_formats(img: np.ndarray) -> None:
    """Check 4 — JPEG and PNG of the same photo must agree (cosine >= 0.995).

    JPEG (q=90) is lossy: pixel deltas shift the embedding a little. What
    matters is that this noise is negligible vs the same/diff margin —
    measured 0.9983 here vs a ~0.87 identity margin (~40x headroom).
    """
    d_j, ct_j = multipart({"p.jpeg": (jpg(img, 90), "image/jpeg")})
    d_p, ct_p = multipart({"p.png": (png(img), "image/png")})
    sj, rj = call("/extract", d_j, ct_j)
    sp, rp = call("/extract", d_p, ct_p)
    if sj != 200 or sp != 200:
        record("formats", False, f"HTTP {sj}(jpeg)/{sp}(png)")
        return
    ej = np.asarray(rj["embedding_512d"], dtype=np.float32)
    ep = np.asarray(rp["embedding_512d"], dtype=np.float32)
    agree = float(np.dot(ej, ep))
    record("formats", agree >= 0.995,
           f"jpeg vs png cosine = {agree:.6f} (>= 0.995; lossy-compression noise "
           f"must stay far below the same/diff margin), both extracted")


def check_separation(enroll_emb: np.ndarray, img: np.ndarray, other_bytes: bytes) -> None:
    """Check 5 — same-person vs different-person separation using fdx-calibrated thresholds.

    fdx calibration (CelebA, 5 identities, flip probes):
      same-person cosine: 0.858–0.911  → floor tested at FDX_SAME_FLOOR (0.75)
      diff-person cosine: 0.019–0.212  → ceiling tested at FDX_DIFF_CEIL (0.35)
      margin:             ~0.646+      → minimum FDX_MIN_MARGIN (0.45)

    Thresholds are tighter than the production gate (0.60) to catch any
    regression where fdx silently falls back to insightface (whose same-person
    cosine would be in a different range).
    """
    probe = cv2.resize(cv2.flip(img, 1), None, fx=1.08, fy=1.08)
    d_same, ct_same = multipart({"s.jpg": (jpg(probe), "image/jpeg")})
    s1, r_same = call("/extract", d_same, ct_same)

    d_other, ct_other = multipart({"o.jpg": (other_bytes, "image/jpeg")})
    s2, r_other = call("/extract", d_other, ct_other)

    if s1 != 200 or s2 != 200:
        record("separation", False, f"HTTP {s1}/{s2}")
        return
    e_same  = np.asarray(r_same["embedding_512d"],  dtype=np.float32)
    e_other = np.asarray(r_other["embedding_512d"], dtype=np.float32)
    cos_same = float(np.dot(enroll_emb, e_same))
    cos_diff = float(np.dot(enroll_emb, e_other))
    margin   = cos_same - cos_diff
    ok = (cos_same >= FDX_SAME_FLOOR
          and cos_diff <= FDX_DIFF_CEIL
          and margin   >= FDX_MIN_MARGIN)
    record("separation", ok,
           f"same-person cosine={cos_same:.4f} (>={FDX_SAME_FLOOR}), "
           f"different-person={cos_diff:.4f} (<={FDX_DIFF_CEIL}), "
           f"margin={margin:.4f} (>={FDX_MIN_MARGIN})")


def check_no_face() -> None:
    """Check 6 — blank image must be rejected with HTTP 400 (not embedded)."""
    blank = np.full((224, 224, 3), 128, dtype=np.uint8)
    d, ct = multipart({"blank.jpg": (jpg(blank), "image/jpeg")})
    status, r = call("/extract", d, ct)
    ok = status == 400 and "No face" in str(r.get("detail", ""))
    record("no_face_path", ok,
           f"blank image -> HTTP {status} ({r.get('detail', '')[:60]}) — rejected, not embedded")


def main() -> int:
    z = zipfile.ZipFile(ZIP_PATH)
    names = [n for n in z.namelist() if n.startswith("img_align_celeba/") and n.endswith(".jpg")]
    enroll_raw = z.read(names[11])
    other_raw  = z.read(names[12])
    print(f"target {BASE}; enroll={names[11].split('/')[-1]} other={names[12].split('/')[-1]}")

    # 1. contract
    r = check_contract(enroll_raw)
    if not r:
        print(json.dumps(results, indent=2))
        return 1
    emb = np.asarray(r["embedding_512d"], dtype=np.float32)

    # 2. cross-space incompatibility (fdx vs insightface)
    check_cross_space(r)

    # 3. determinism
    det = check_determinism(enroll_raw)

    # 4. formats (JPEG vs PNG)
    img = cv2.imdecode(np.frombuffer(enroll_raw, np.uint8), cv2.IMREAD_COLOR)
    if det is not None:
        check_formats(img)

    # 5. separation (fdx-calibrated thresholds)
    check_separation(emb, img, other_raw)

    # 6. no-face path
    check_no_face()

    os.makedirs(REPORT_DIR, exist_ok=True)
    with open(os.path.join(REPORT_DIR, "extract_512_test.json"), "w") as f:
        json.dump(results, f, indent=2)
    print("RESULT:", "PASS" if results["pass"] else "FAIL")
    return 0 if results["pass"] else 1


if __name__ == "__main__":
    sys.exit(main())
