import os
import sys
import json
import time

args = sys.argv[1:]
lst = args[args.index("--list") + 1]
out = args[args.index("--out") + 1]
mode = os.environ.get("STUB_MODE", "ok")
MARK = os.path.join(os.path.dirname(os.path.abspath(__file__)), "_stub_hang.marker")

paths = [l.strip() for l in open(lst, encoding="utf-8") if l.strip()]

if mode == "hang_first" and not os.path.exists(MARK):
    open(MARK, "w").close()
    for _ in range(600):
        time.sleep(1)
    sys.exit(0)

if mode == "chatty":
    for i in range(40000):
        sys.stderr.write("[chatty] line " + str(i) + " " + "p" * 44 + "\n")
    sys.stderr.flush()

with open(out, "a", encoding="utf-8") as f:
    for p in paths:
        f.write(json.dumps({"id": p, "ms": 1.0, "embedding": [0.0] * 512}) + "\n")

sys.stderr.write("summary ok=" + str(len(paths)) + " failed=0\n")
sys.stderr.flush()
