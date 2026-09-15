"""Unit tests for BatchChunk retry + stall-watchdog + stderr-drain behavior.

Run:  python reports/_test_chunk.py   (from face_dx/)
Cases:
  A) hung engine (silent, never writes) -> stall watchdog -> respawn ->
     chunk completes (the overnight-wedge failure mode)
  B) engine emits >64KB stderr mid-chunk -> drain thread prevents the
     pipe-buffer deadlock that killed the two overnight runs
  C) normal faithful echo (ids, records, summary parse)
"""
import os
import sys
import time

HERE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, HERE)
import celeba_pipeline as cp  # noqa: E402

STUB = os.path.join(HERE, "reports", "_stub_engine.py")
MARK = os.path.join(HERE, "reports", "_stub_hang.marker")


class FakePopen(cp.subprocess.Popen):
    def __init__(self, args, *a, **kw):
        args = list(args)
        args[0:1] = [sys.executable, STUB]
        super().__init__(args, *a, **kw)


real_popen = cp.subprocess.Popen
cp.subprocess.Popen = FakePopen

failures = []
try:
    paths = ["D:/fake/img_0.png", "D:/fake/img_1.png", "D:/fake/img_2.png"]

    def check(name, cond, detail=""):
        status = "PASS" if cond else "FAIL"
        print(f"{status}  {name}  {detail}")
        if not cond:
            failures.append(name)

    # Case A: hung engine -> timeout -> respawn completes
    if os.path.exists(MARK):
        os.remove(MARK)
    os.environ["STUB_MODE"] = "hang_first"
    c = cp.BatchChunk(paths, fp16=True, bench=1)
    t0 = time.time()
    ok, failed, missing = c.join(timeout=2.0, retries=1)
    check("A retry-on-hang completes chunk", ok == 3 and len(missing) == 0,
          f"ok={ok} missing={len(missing)} wall={time.time()-t0:.1f}s")
    check("A respawn captured summary", c.stderr_text is not None and "ok=3" in c.stderr_text)

    # Case B: >64KB stderr must not deadlock the pipe
    os.environ["STUB_MODE"] = "chatty"
    c = cp.BatchChunk(paths, fp16=True, bench=1)
    t0 = time.time()
    ok, failed, missing = c.join(timeout=30.0, retries=1)
    check("B >64KB stderr no deadlock", ok == 3 and len(missing) == 0,
          f"ok={ok} missing={len(missing)} wall={time.time()-t0:.1f}s")
    check("B stderr fully drained", c.stderr_text is not None and len(c.stderr_text) > 64 * 1024,
          f"stderr_len={len(c.stderr_text or '')}")

    # Case C: normal faithful echo
    os.environ["STUB_MODE"] = "ok"
    c = cp.BatchChunk(paths, fp16=True, bench=1)
    ok, failed, missing = c.join(timeout=30.0, retries=1)
    check("C normal echo ok", ok == 3 and len(missing) == 0, f"ok={ok} missing={len(missing)}")
    check("C ids echoed verbatim", all(r["id"] in paths for r in c.records))
finally:
    cp.subprocess.Popen = real_popen
    for p in (MARK,):
        try:
            os.remove(p)
        except OSError:
            pass

print("=" * 40)
if failures:
    print("FAILED:", failures)
    sys.exit(1)
print("ALL CASES PASS")
