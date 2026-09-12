#!/usr/bin/env python3
"""
collect_faces.py — automated data collection for the ArcFace pipeline.

Detects faces with SCRFD (InsightFace `buffalo_l` -> det_10g.onnx), aligns
them to the standard 112x112 ArcFace template with the 5-point `norm_crop`
similarity transform, and writes crops grouped per identity:

    <dataset_dir>/<person>_<NNNN>.jpg     (flat:  <person> prefix = identity)
    <dataset_dir>/<person>/<person>_NNNN.jpg   (--subfolders)

Both layouts are read natively by `SimpleFacesDataset` /
`gather_identity_images()` in train_custom_arcface.py.

Sources
  * --source dir      : walk a folder of raw photos (flat or per-person
                        subfolders; identities come from subfolder names or
                        filename prefixes, same rule as training)
  * --source webcam   : live capture; identities are auto-named person_01,
                        person_02, ... (or --name to collect one person).
                        SPACE=start capturing, N=next person, Q=quit.

Identity grouping (automatic): each detected face gets an embedding from the
cached ArcFace recognition model (w600k_r50); faces are greedily clustered by
cosine similarity against running per-identity centroids (threshold
--sim-threshold, default 0.55). Every saved crop is also deduped per identity
(cosine >= --dedup-threshold vs an already-saved crop is considered a
duplicate and skipped), so re-running the collector tops identities up
instead of duplicating data.

A collection manifest (manifest.json) and a training-readiness gate are
written/emitted at the end: by default the run exits non-zero unless the
dataset holds >= 10 identities x >= 5 images each (--min-identities,
--min-images). The gate is what "at least 10 identities with 5+ images
each" means operationally.

Models are loaded from the standard InsightFace cache (~/.insightface/models,
buffalo_l is auto-downloaded on first use).
"""
from __future__ import annotations

import argparse
import json
import os
import re
import sys
import time
from typing import Dict, List, Optional, Tuple

import cv2
import numpy as np

IMG_EXTS = (".png", ".jpg", ".jpeg", ".webp", ".bmp")
MIN_DET_SCORE = 0.5          # SCRFD detection threshold
ALIGN_SIZE = 112             # ArcFace input template size


# --------------------------------------------------------------------------
# detection / alignment
# --------------------------------------------------------------------------

def make_detector(det_size: Tuple[int, int], ctx_id: int = -1):
    """SCRFD detector + ArcFace recognizer from the local buffalo_l pack."""
    from insightface.app import FaceAnalysis
    app = FaceAnalysis(name="buffalo_l", allowed_modules=["detection", "recognition"])
    app.prepare(ctx_id=ctx_id, det_size=det_size, det_thresh=MIN_DET_SCORE)
    return app


def detect_faces(rec, img_bgr: np.ndarray):
    """All faces in the image, best (highest det score) first.

    Small images (e.g. re-reading already-cropped 112x112 files during a
    re-run) get a one-shot retry with a gray border pad: SCRFD cannot detect
    a face that fills the entire frame, and the pad restores the margin it
    expects. Only triggers when the plain pass found nothing."""
    faces = [f for f in rec.get(img_bgr) if float(f.det_score) >= MIN_DET_SCORE]
    if not faces:
        h, w = img_bgr.shape[:2]
        if max(h, w) <= 512:
            pad = max(h, w) // 2
            padded = cv2.copyMakeBorder(img_bgr, pad, pad, pad, pad,
                                        cv2.BORDER_CONSTANT,
                                        value=(114, 114, 114))
            faces = [f for f in rec.get(padded)
                     if float(f.det_score) >= MIN_DET_SCORE]
    if faces:
        faces = sorted(faces, key=lambda f: float(f.det_score), reverse=True)
    return faces


def aligned_crop(img_bgr: np.ndarray, face) -> np.ndarray:
    """5-point similarity alignment onto the 112x112 ArcFace template."""
    from insightface.utils.face_align import norm_crop
    return norm_crop(img_bgr, face.kps, image_size=ALIGN_SIZE)


def load_bgr(path: str) -> Optional[np.ndarray]:
    """Unicode-safe image read (cv2.imread fails on non-ASCII Windows paths)."""
    try:
        data = np.fromfile(path, dtype=np.uint8)
        if data.size == 0:
            return None
        return cv2.imdecode(data, cv2.IMREAD_COLOR)
    except OSError:
        return None


def save_jpg(path: str, img: np.ndarray, quality: int = 95) -> None:
    """Unicode-safe image write."""
    os.makedirs(os.path.dirname(os.path.abspath(path)), exist_ok=True)
    ok, buf = cv2.imencode(".jpg", img, [int(cv2.IMWRITE_JPEG_QUALITY), quality])
    if not ok:
        raise IOError(f"JPEG encode failed for {path}")
    buf.tofile(path)


# --------------------------------------------------------------------------
# identity bookkeeping: auto-naming, greedy clustering, per-identity dedup
# --------------------------------------------------------------------------

def next_auto_index(dataset_dir: str, prefix: str) -> int:
    """Largest existing auto index + 1, so re-runs never reuse a name."""
    pat = re.compile(rf"^{re.escape(prefix)}_(\d+)$")
    idx = 0
    if os.path.isdir(dataset_dir):
        for entry in os.listdir(dataset_dir):
            m = pat.match(os.path.splitext(entry)[0]) if os.path.isfile(
                os.path.join(dataset_dir, entry)) else pat.match(entry)
            if m:
                idx = max(idx, int(m.group(1)))
    return idx + 1


class IdentityStore:
    """Maps face embeddings to identity names.

    * fixed_name  : everything belongs to one identity (dir mode / --name)
    * otherwise   : greedy cosine clustering against running centroids;
                    a face below --sim-threshold of every centroid opens a
                    new auto-named identity (person_01, person_02, ...)
    * dedup_ok()  : rejects a crop too similar (cosine >= dedup_thr) to one
                    already saved for that identity
    """

    def __init__(self, dataset_dir: str, fixed_name: Optional[str] = None,
                 auto_prefix: str = "person", sim_threshold: float = 0.55,
                 dedup_threshold: float = 0.90, auto_start: int = 1):
        self.dataset_dir = dataset_dir
        self.fixed_name = fixed_name
        self.auto_prefix = auto_prefix
        self.sim_thr = sim_threshold
        self.dedup_thr = dedup_threshold
        self.auto_next = auto_start
        self.centroids: Dict[str, np.ndarray] = {}
        self.counts: Dict[str, int] = {}
        self.crop_embs: Dict[str, List[np.ndarray]] = {}
        self._file_next: Dict[str, int] = {}

    # -- naming ------------------------------------------------------------
    def peek_name(self) -> str:
        """Name the NEXT new identity would get (without consuming it)."""
        return self.fixed_name or f"{self.auto_prefix}_{self.auto_next:02d}"

    def assign(self, emb: np.ndarray) -> str:
        """Cluster-assign one embedding and register a new identity if needed."""
        if self.fixed_name:
            name = self.fixed_name
        else:
            name, best = None, self.sim_thr
            for cname, cent in self.centroids.items():
                sim = float(np.dot(emb, cent))
                if sim >= best:
                    name, best = cname, sim
            if name is None:
                name = f"{self.auto_prefix}_{self.auto_next:02d}"
                self.auto_next += 1
        if name not in self.centroids:
            self.centroids[name] = emb.astype(np.float32).copy()
            self.counts[name] = 0
            self.crop_embs[name] = []
        return name

    # -- dedup / centroid updates -------------------------------------------
    def dedup_ok(self, name: str, emb: np.ndarray) -> bool:
        return all(float(np.dot(emb, e)) < self.dedup_thr
                   for e in self.crop_embs.get(name, ()))

    def record(self, name: str, emb: np.ndarray, path: str) -> int:
        """Register a saved crop; returns its 1-based image index.

        Self-registering: works with or without a prior assign() (dir mode
        has a fixed name and never assigns)."""
        if name not in self.centroids:
            self.centroids[name] = emb.astype(np.float32).copy()
            self.counts[name] = 0
            self.crop_embs[name] = []
        c = self.centroids[name]
        n = self.counts[name]
        cent = (c * n + emb) / (n + 1.0)
        self.centroids[name] = (cent / (np.linalg.norm(cent) + 1e-9)).astype(np.float32)
        self.counts[name] = n + 1
        self.crop_embs[name].append(emb.astype(np.float32))
        if name not in self._file_next:
            self._file_next[name] = _next_file_index(self.dataset_dir, name)
        idx = self._file_next[name]
        self._file_next[name] = idx + 1
        return idx

    def preload_existing(self, rec, name: str) -> int:
        """Re-embed crops already on disk for `name` (re-run support: dedup
        across sessions and a truthful centroid from the first crop)."""
        dd = self.dataset_dir
        files = []
        if os.path.isdir(dd):
            if self.fixed_name and any(
                    f.lower().endswith(IMG_EXTS) for f in os.listdir(dd)
                    if os.path.isfile(os.path.join(dd, f))):
                files = [f for f in sorted(os.listdir(dd))
                         if f.lower().endswith(IMG_EXTS)]
            else:
                pd = os.path.join(dd, name)
                if os.path.isdir(pd):
                    files = [os.path.join(name, f) for f in sorted(os.listdir(pd))
                             if f.lower().endswith(IMG_EXTS)]
        loaded = 0
        for rel in files:
            fp = os.path.join(dd, rel)
            if not _belongs_to(rel, name):
                continue
            img = load_bgr(fp)
            if img is None:
                continue
            faces = detect_faces(rec, img)
            if not faces:
                continue
            emb = np.asarray(faces[0].normed_embedding, dtype=np.float32)
            if name not in self.centroids:
                self.centroids[name] = emb.copy()
                self.counts[name] = 0
                self.crop_embs[name] = []
            self.crop_embs[name].append(emb)
            self.counts[name] += 1
            loaded += 1
        return loaded


def _belongs_to(rel_name: str, identity: str) -> bool:
    """Does a dataset-relative filename belong to `identity`?"""
    parts = rel_name.replace("\\", "/").split("/")
    if len(parts) == 2:            # subfolder layout
        return parts[0] == identity
    stem = os.path.splitext(parts[-1])[0]
    return stem.split("_", 1)[0] == identity


def _next_file_index(dataset_dir: str, identity: str) -> int:
    """Next free <identity>_<NNNN> index (subfolder or flat layout)."""
    pd = os.path.join(dataset_dir, identity)
    if os.path.isdir(pd):
        names = os.listdir(pd)
    elif os.path.isdir(dataset_dir):
        names = [f for f in os.listdir(dataset_dir)
                 if os.path.isfile(os.path.join(dataset_dir, f))]
    else:
        return 1  # fresh dataset dir
    pat = re.compile(rf"^{re.escape(identity)}_(\d+)$")
    idx = 0
    for f in names:
        m = pat.match(os.path.splitext(f)[0])
        if m:
            idx = max(idx, int(m.group(1)))
    return idx + 1


# --------------------------------------------------------------------------
# source: directory of raw photos
# --------------------------------------------------------------------------

def _list_images(root: str, recursive: bool) -> List[str]:
    out: List[str] = []
    if recursive:
        for dirpath, _dirnames, filenames in os.walk(root):
            for fn in sorted(filenames):
                if fn.lower().endswith(IMG_EXTS):
                    out.append(os.path.join(dirpath, fn))
    else:
        for fn in sorted(os.listdir(root)):
            if fn.lower().endswith(IMG_EXTS) and os.path.isfile(os.path.join(root, fn)):
                out.append(os.path.join(root, fn))
    return out


def _group_dir_identities(root: str, recursive: bool) -> List[Tuple[str, List[str]]]:
    """(identity, image paths) from a raw folder.

    Subfolders take precedence: <root>/<person>/**/*.ext -> identity = person.
    Flat folders use the training set's rule: <person>_<anything>.ext ->
    identity = prefix before the FIRST underscore.
    """
    subdirs = sorted(d for d in os.listdir(root)
                     if os.path.isdir(os.path.join(root, d)))
    groups: List[Tuple[str, List[str]]] = []
    for d in subdirs:
        imgs = _list_images(os.path.join(root, d), recursive=True)
        if imgs:
            groups.append((d, imgs))
    if not groups:
        bucket: Dict[str, List[str]] = {}
        for fp in _list_images(root, recursive=False):
            stem = os.path.splitext(os.path.basename(fp))[0]
            ident = stem.split("_", 1)[0] if "_" in stem else stem
            bucket.setdefault(ident, []).append(fp)
        groups = [(k, v) for k, v in sorted(bucket.items())]
    return groups


def collect_from_dir(rec, args) -> Dict[str, dict]:
    groups = _group_dir_identities(args.source, args.recursive)
    if args.identities:
        want = {n.strip() for n in args.identities.split(",") if n.strip()}
        groups = [(n, ps) for n, ps in groups if n in want]
        missing = want - {n for n, _ in groups}
        if missing:
            print(f"[dir] warning: identities not found in source: "
                  f"{', '.join(sorted(missing))}")
    if not groups:
        raise SystemExit(f"[dir] no images/identities found under {args.source!r}")

    tally: Dict[str, dict] = {}
    for ident, paths in groups:
        store = IdentityStore(args.dataset, fixed_name=ident,
                              sim_threshold=args.sim_threshold,
                              dedup_threshold=args.dedup_threshold)
        preloaded = store.preload_existing(rec, ident)
        stat = {"found": 0, "saved": 0, "dup": 0, "no_face": 0,
                "preloaded": preloaded}
        for fp in paths:
            if args.max_per_identity and \
                    (stat["preloaded"] + stat["saved"]) >= args.max_per_identity:
                break
            img = load_bgr(fp)
            if img is None:
                stat["no_face"] += 1
                continue
            faces = detect_faces(rec, img)
            if not faces:
                stat["no_face"] += 1
                continue
            stat["found"] += 1
            best = faces[0]
            emb = np.asarray(best.normed_embedding, dtype=np.float32)
            if not store.dedup_ok(ident, emb):
                stat["dup"] += 1
                continue
            crop = aligned_crop(img, best)
            idx = store.record(ident, emb, fp)
            out = (os.path.join(args.dataset, ident, f"{ident}_{idx:04d}.jpg")
                   if args.subfolders else
                   os.path.join(args.dataset, f"{ident}_{idx:04d}.jpg"))
            save_jpg(out, crop)
            stat["saved"] += 1
        tally[ident] = stat
        print(f"[dir] {ident:<24} found={stat['found']:<4} "
              f"saved={stat['saved']:<4} dup={stat['dup']:<3} "
              f"preloaded={stat['preloaded']:<3} "
              f"no-face/unreadable={stat['no_face']}")
    return tally


# --------------------------------------------------------------------------
# source: webcam
# --------------------------------------------------------------------------

def collect_webcam(rec, args) -> Dict[str, dict]:
    cap = cv2.VideoCapture(args.cam_index)
    if not cap.isOpened():
        raise SystemExit(
            f"[webcam] cannot open camera index {args.cam_index}; "
            "pass --cam-index N or use --source dir")
    cap.set(cv2.CAP_PROP_FRAME_WIDTH, 1280)
    cap.set(cv2.CAP_PROP_FRAME_HEIGHT, 720)

    store = IdentityStore(args.dataset, fixed_name=args.name,
                          sim_threshold=args.sim_threshold,
                          dedup_threshold=args.dedup_threshold)
    if args.name:
        pre = store.preload_existing(rec, args.name)
        if pre:
            print(f"[webcam] resuming '{args.name}': {pre} existing crops")

    target = args.target_images
    fixed = args.name is not None
    paused = True
    deadline = time.time() + args.max_minutes * 60.0
    last_save = 0.0
    total_crops = sum(store.counts.values())
    window = "collect_faces (SPACE=start, N=next person, Q=quit)"

    print(f"[webcam] target: {target} crops per identity, "
          f"auto-naming: {'OFF' if fixed else 'ON'}")

    while True:
        ok, frame = cap.read()
        if not ok:
            print("[webcam] frame grab failed; stopping")
            break
        frame = cv2.flip(frame, 1)  # selfie-mirror: what the user sees is what is saved
        now = time.time()

        if not paused and now - last_save >= args.min_interval:
            faces = detect_faces(rec, frame)
            if faces and float(faces[0].det_score) >= MIN_DET_SCORE:
                best = faces[0]
                emb = np.asarray(best.normed_embedding, dtype=np.float32)
                name = store.assign(emb)
                if store.dedup_ok(name, emb):
                    crop = aligned_crop(frame, best)
                    idx = store.record(name, emb, "")
                    out = (os.path.join(args.dataset, name, f"{name}_{idx:04d}.jpg")
                           if args.subfolders else
                           os.path.join(args.dataset, f"{name}_{idx:04d}.jpg"))
                    save_jpg(out, crop)
                    total_crops += 1
                    last_save = now
                    cnt = store.counts[name]
                    print(f"[webcam] saved {out}  ({cnt}/{target})")
                    if cnt >= target:
                        if fixed:
                            break
                        paused = True  # auto-advance to the next person

        cur = store.peek_name()
        cnt = store.counts.get(cur, 0)
        msg = (f"capturing: {cur}  {cnt}/{target}" if not paused
               else f"[paused] next: {cur}  (SPACE to start)")
        canvas = frame.copy()
        cv2.putText(canvas, msg, (16, 36), cv2.FONT_HERSHEY_SIMPLEX, 0.9,
                    (0, 255, 0) if not paused else (0, 200, 255), 2, cv2.LINE_AA)
        cv2.putText(canvas, f"identities: {len(store.centroids)}   crops: {total_crops}",
                    (16, 68), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2, cv2.LINE_AA)
        cv2.imshow(window, canvas)
        key = cv2.waitKey(1) & 0xFF
        if key in (ord("q"), 27):
            break
        if key == ord(" "):
            paused = False
        elif key == ord("n") and not fixed:
            paused = True  # next assign() opens a fresh auto identity

        if now > deadline:
            print(f"[webcam] --max-minutes {args.max_minutes} reached; stopping")
            break

    cap.release()
    cv2.destroyAllWindows()
    return {name: {"found": c, "saved": c, "dup": 0, "no_face": 0}
            for name, c in store.counts.items() if c > 0}


# --------------------------------------------------------------------------
# manifest + training-readiness gate
# --------------------------------------------------------------------------

def dataset_identity_counts(dataset_dir: str) -> Dict[str, int]:
    """Identity -> crop count, using the SAME layout rules as training."""
    counts: Dict[str, int] = {}
    subdirs = sorted(d for d in os.listdir(dataset_dir)
                     if os.path.isdir(os.path.join(dataset_dir, d)))
    for d in subdirs:
        n = sum(1 for f in os.listdir(os.path.join(dataset_dir, d))
                if f.lower().endswith(IMG_EXTS))
        if n:
            counts[d] = counts.get(d, 0) + n
    if not counts:
        for f in os.listdir(dataset_dir):
            if f.lower().endswith(IMG_EXTS):
                stem = os.path.splitext(f)[0]
                ident = stem.split("_", 1)[0] if "_" in stem else stem
                counts[ident] = counts.get(ident, 0) + 1
    return counts


def write_manifest(dataset_dir: str, args, tally: Dict[str, dict],
                   source_label: str) -> str:
    counts = dataset_identity_counts(args.dataset)
    manifest = {
        "dataset_dir": os.path.abspath(args.dataset),
        "source": source_label,
        "detector": "SCRFD-10GF (insightface buffalo_l / det_10g.onnx)",
        "alignment": "5-point similarity norm_crop -> 112x112 ArcFace template",
        "sim_threshold": args.sim_threshold,
        "dedup_threshold": args.dedup_threshold,
        "min_det_score": MIN_DET_SCORE,
        "session": {name: dict(stat) for name, stat in tally.items()},
        "identities": {name: {"images": n} for name, n in sorted(counts.items())},
        "total_images": sum(counts.values()),
        "num_identities": len(counts),
    }
    path = os.path.join(dataset_dir, "manifest.json")
    with open(path, "w", encoding="utf-8") as f:
        json.dump(manifest, f, indent=2)
    print(f"[manifest] wrote {path}")
    return path


def validate_dataset(dataset_dir: str, min_identities: int,
                     min_images: int) -> Tuple[bool, List[str]]:
    """The gate: >= min_identities identities with >= min_images crops each."""
    problems: List[str] = []
    if not os.path.isdir(dataset_dir):
        print("\n=== dataset readiness gate ===")
        print(f"GATE FAIL: dataset dir missing: {dataset_dir!r}")
        return False, [f"dataset dir missing: {dataset_dir!r}"]
    counts = dataset_identity_counts(dataset_dir)
    if not counts:
        print("\n=== dataset readiness gate ===")
        print(f"GATE FAIL: no crops found in {dataset_dir!r} yet")
        print("keep collecting (re-run the same command to top up)")
        return False, ["no crops found in dataset dir"]
    short = {n: c for n, c in sorted(counts.items()) if c < min_images}
    if len(counts) < min_identities:
        problems.append(f"only {len(counts)} identities (need {min_identities})")
    if short:
        problems.append(
            f"{len(short)} identities below {min_images} images: "
            + ", ".join(f"{n}({c})" for n, c in short.items()))
    ok = not problems
    print("\n=== dataset readiness gate ===")
    print(f"identities: {len(counts)}  total crops: {sum(counts.values())}")
    for n, c in sorted(counts.items()):
        flag = "" if c >= min_images else "  [LOW]"
        print(f"  {n:<24} {c:>4}{flag}")
    if ok:
        print(f"GATE PASS: >= {min_identities} identities x >= {min_images} images")
    else:
        print("GATE FAIL:")
        for p in problems:
            print(f"  - {p}")
        print("keep collecting (re-run the same command to top up)")
    return ok, problems


# --------------------------------------------------------------------------
# CLI
# --------------------------------------------------------------------------

def main() -> int:
    here = os.path.dirname(os.path.abspath(__file__))
    default_dataset = os.path.join(here, "dataset")

    parser = argparse.ArgumentParser(
        description="Collect aligned 112x112 face crops (SCRFD + norm_crop) "
                    "for ArcFace training, auto-grouped per identity.",
        formatter_class=argparse.ArgumentDefaultsHelpFormatter)
    parser.add_argument("--source", required=True,
                        help="raw image folder, or the literal 'webcam' "
                             "for live capture")
    parser.add_argument("--dataset", default=default_dataset,
                        help="output dataset dir (training crops)")
    parser.add_argument("--subfolders", action="store_true",
                        help="write <dataset>/<person>/<person>_NNNN.jpg "
                             "instead of flat <dataset>/<person>_NNNN.jpg")
    parser.add_argument("--det-size", type=int, default=640,
                        help="SCRFD input size (640 fast, 1280 finds smaller faces)")
    parser.add_argument("--ctx-id", type=int, default=-1,
                        help="execution provider id (-1 = CPU)")
    # clustering / dedup
    parser.add_argument("--sim-threshold", type=float, default=0.55,
                        help="cosine >= this joins an existing identity "
                             "(else a new auto identity opens)")
    parser.add_argument("--dedup-threshold", type=float, default=0.90,
                        help="skip a crop this similar to an already-saved "
                             "crop of the same identity")
    # dir mode
    parser.add_argument("--recursive", action=argparse.BooleanOptionalAction,
                        default=True,
                        help="search subfolders for images (dir mode)")
    parser.add_argument("--identities", default=None,
                        help="comma list restricting dir mode to these identities")
    parser.add_argument("--max-per-identity", type=int, default=20,
                        help="cap saved crops per identity (0 = unlimited)")
    # webcam mode
    parser.add_argument("--name", default=None,
                        help="webcam mode: collect one fixed identity name "
                             "(else auto person_NN per new face cluster)")
    parser.add_argument("--cam-index", type=int, default=0)
    parser.add_argument("--target-images", type=int, default=8,
                        help="webcam mode: crops per identity before auto-advance")
    parser.add_argument("--min-interval", type=float, default=0.6,
                        help="webcam mode: seconds between saved crops")
    parser.add_argument("--max-minutes", type=float, default=15.0,
                        help="webcam mode: hard time cap for the session")
    # gate
    parser.add_argument("--min-identities", type=int, default=10,
                        help="gate: identities required for training")
    parser.add_argument("--min-images", type=int, default=5,
                        help="gate: images required per identity")
    args = parser.parse_args()

    os.makedirs(args.dataset, exist_ok=True)  # manifest must survive a 0-crop session
    rec = make_detector((args.det_size, args.det_size), args.ctx_id)

    if args.source == "webcam":
        tally = collect_webcam(rec, args)
        source_label = "webcam cam=%d" % args.cam_index
    else:
        if not os.path.isdir(args.source):
            raise SystemExit(f"[dir] source folder not found: {args.source!r} "
                             "(or did you mean --source webcam?)")
        tally = collect_from_dir(rec, args)
        source_label = "dir %s" % args.source

    write_manifest(args.dataset, args, tally, source_label)
    ok, _problems = validate_dataset(args.dataset, args.min_identities, args.min_images)

    print("\nnext steps:")
    print(f"  python face_dx/train_custom_arcface.py --crops-dir {args.dataset}")
    print(f"  python face_dx/eval_custom_arcface.py --crops-dir {args.dataset} "
          "--ckpt face_dx/models/custom_arcface.pt")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
