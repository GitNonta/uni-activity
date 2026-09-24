"""
native_face.py — InsightFace-free detection / landmarks / alignment / fallback embedding.
=========================================================================================

Replaces the `insightface` pip package with a direct ONNX runtime implementation
of exactly the pieces the server needs:

  1. NativeSCRFD  — SCRFD face detector (det_10g.onnx) returning bounding boxes,
                    5-point landmarks (kps) and detection scores.
  2. norm_crop    — the canonical 5-point ArcFace similarity-transform warp
                    (the alignment contract shared by fdx and the fallback).
  3. NativeArcFace— w600k_mbf.onnx ArcFace embedder used as the fallback when
                    the fdx D3D11 engine is unavailable.

Why this is safe (parity methodology)
-------------------------------------
Every formula here is transcribed from insightface 1.0.1 source
(model_zoo/scrfd.py, model_zoo/arcface_onnx.py, utils/face_align.py) so the
numerical behavior is identical to the package it replaces:

  * preprocessing via the SAME cv2.dnn.blobFromImage calls (det: scale 1/128,
    mean 127.5, swapRB; rec: scale & mean 1/127.5 vs 0/1 picked by the same
    Sub/Mul graph-name scan arcface_onnx.py performs),
  * same stride/anchor decode (strides 8/16/32, 2 anchors, kps heads),
  * same NMS (IoU with the +1 pixel convention, nms_thresh=0.4),
  * same letterbox resize and det_scale un-projection,
  * norm_crop uses the same arcface_dst template + skimage SimilarityTransform.

face_dx/_native_parity_check.py proves parity on real images: kps max delta
~0 px and cos(native, insightface) = 1.0000 on the same face.

The models themselves are unchanged — this module only removes the package
dependency (and with it the FaceAnalysis glob/pack behavior that previously
made recognition-model selection fragile).
"""
from __future__ import annotations

import logging
from dataclasses import dataclass
from typing import List, Optional, Sequence, Tuple

import cv2
import numpy as np
import onnxruntime as ort

logger = logging.getLogger("NativeFace")

# ── 5-point ArcFace alignment template (utils/face_align.py) ─────────────────
ARC_FACE_DST = np.array(
    [[38.2946, 51.6963], [73.5318, 51.5014], [56.0252, 71.7366],
     [41.5493, 92.3655], [70.7299, 92.2041]],
    dtype=np.float32,
)


def estimate_norm(lmk: np.ndarray, image_size: int = 112) -> np.ndarray:
    """Similarity transform mapping lmk (5,2) onto the ArcFace template.

    Byte-equivalent to insightface.utils.face_align.estimate_norm (arcface mode).
    """
    lmk = np.asarray(lmk, dtype=np.float32)
    assert lmk.shape == (5, 2), f"landmark must be (5,2), got {lmk.shape}"
    if image_size % 112 == 0:
        ratio = float(image_size) / 112.0
        diff_x = 0.0
    else:
        assert image_size % 128 == 0, "image_size must be a multiple of 112 or 128"
        ratio = float(image_size) / 128.0
        diff_x = 8.0 * ratio
    dst = ARC_FACE_DST * ratio
    dst[:, 0] += diff_x

    from skimage import transform as trans
    if hasattr(trans.SimilarityTransform, "from_estimate"):
        tform = trans.SimilarityTransform.from_estimate(lmk, dst)
    else:
        tform = trans.SimilarityTransform()
        tform.estimate(lmk, dst)
    return tform.params[0:2, :]


def _make_optimized_session_options(intra_threads: int = 4) -> ort.SessionOptions:
    """Create tuned ONNX SessionOptions for high-throughput inference."""
    so = ort.SessionOptions()
    so.graph_optimization_level = ort.GraphOptimizationLevel.ORT_ENABLE_ALL
    so.intra_op_num_threads = intra_threads
    so.execution_mode = ort.ExecutionMode.ORT_SEQUENTIAL
    so.log_severity_level = 3  # Suppress internal ONNX runtime warning logs
    return so



def norm_crop(img: np.ndarray, landmark: np.ndarray, image_size: int = 112) -> np.ndarray:
    """Canonical 5-point landmark warp to image_size×image_size (BGR).

    Byte-equivalent to insightface.utils.face_align.norm_crop (arcface mode).
    This is the alignment contract: fdx AND the ArcFace fallback only produce
    comparable embeddings on this exact warp.
    """
    M = estimate_norm(landmark, image_size)
    return cv2.warpAffine(img, M, (image_size, image_size), borderValue=0.0)


# ── SCRFD decode helpers (model_zoo/scrfd.py) ────────────────────────────────

def _distance2bbox(points: np.ndarray, distance: np.ndarray) -> np.ndarray:
    """Decode distance regression → (x1,y1,x2,y2) boxes."""
    x1 = points[:, 0] - distance[:, 0]
    y1 = points[:, 1] - distance[:, 1]
    x2 = points[:, 0] + distance[:, 2]
    y2 = points[:, 1] + distance[:, 3]
    return np.stack([x1, y1, x2, y2], axis=-1)


def _distance2kps(points: np.ndarray, distance: np.ndarray) -> np.ndarray:
    """Decode distance regression → (K,5,2) landmarks. Vectorized form of
    scrfd.distance2kps (identical output ordering: x0,y0,x1,y1,...)."""
    px = points[:, 0:1] + distance[:, 0::2]
    py = points[:, 1:2] + distance[:, 1::2]
    return np.stack([px, py], axis=-1)


def _nms(dets: np.ndarray, thresh: float) -> List[int]:
    """Greedy IoU NMS with insightface's +1 pixel area convention."""
    x1, y1, x2, y2 = dets[:, 0], dets[:, 1], dets[:, 2], dets[:, 3]
    scores = dets[:, 4]
    areas = (x2 - x1 + 1) * (y2 - y1 + 1)
    order = scores.argsort()[::-1]

    keep: List[int] = []
    while order.size > 0:
        i = order[0]
        keep.append(i)
        xx1 = np.maximum(x1[i], x1[order[1:]])
        yy1 = np.maximum(y1[i], y1[order[1:]])
        xx2 = np.minimum(x2[i], x2[order[1:]])
        yy2 = np.minimum(y2[i], y2[order[1:]])
        w = np.maximum(0.0, xx2 - xx1 + 1)
        h = np.maximum(0.0, yy2 - yy1 + 1)
        inter = w * h
        ovr = inter / (areas[i] + areas[order[1:]] - inter)
        inds = np.where(ovr <= thresh)[0]
        order = order[inds + 1]
    return keep


@dataclass
class NativeFace:
    """Minimal stand-in for insightface.app.face_analysis.Face.

    Carries exactly the fields server.py consumes downstream
    (bbox / kps / det_score). Embeddings are produced separately by
    NativeArcFace (fallback) or the fdx engine (primary).
    """
    bbox: np.ndarray            # float32 (4,) x1,y1,x2,y2 (original image coords)
    kps: Optional[np.ndarray]   # float32 (5,2) or None
    det_score: float


class NativeSCRFD:
    """SCRFD detector over a raw ONNX session — no insightface package.

    Replicates FaceAnalysis(name="buffalo_l", allowed_modules=["detection"])
    behavior with det_size=(640,640), det_thresh=0.5, nms_thresh=0.4:
    aspect-preserving letterbox resize → forward → per-stride anchor decode →
    score threshold → un-scale → cross-strategy NMS. Results are returned
    sorted by det_score (descending), best face first.
    """

    def __init__(
        self,
        onnx_path: str,
        providers: Optional[Sequence[str]] = None,
        sess_options: Optional[ort.SessionOptions] = None,
    ):
        self.onnx_path = onnx_path
        if sess_options is None:
            sess_options = _make_optimized_session_options(intra_threads=4)
        self.session = ort.InferenceSession(
            onnx_path,
            sess_options=sess_options,
            providers=list(providers) if providers else ["CPUExecutionProvider"],
        )
        self.center_cache: dict = {}
        self._init_vars()

    def _init_vars(self) -> None:
        inp = self.session.get_inputs()[0]
        self.input_name = inp.name
        shape = inp.shape
        # det_10g is dynamic (str dims); static models keep their own size.
        self.static_input_size: Optional[Tuple[int, int]] = None
        if not isinstance(shape[2], str) and not isinstance(shape[3], str):
            self.static_input_size = (int(shape[3]), int(shape[2]))  # (w, h)

        outputs = self.session.get_outputs()
        self.output_names = [o.name for o in outputs]

        # Head topology by output count (scrfd._init_vars).
        self.use_kps = False
        self._num_anchors = 1
        if len(outputs) == 6:
            self.fmc, self._feat_stride_fpn = 3, [8, 16, 32]
            self._num_anchors = 2
        elif len(outputs) == 9:
            self.fmc, self._feat_stride_fpn = 3, [8, 16, 32]
            self._num_anchors = 2
            self.use_kps = True
        elif len(outputs) == 10:
            self.fmc, self._feat_stride_fpn = 5, [8, 16, 32, 64, 128]
        elif len(outputs) == 15:
            self.fmc, self._feat_stride_fpn = 5, [8, 16, 32, 64, 128]
            self.use_kps = True
        else:
            raise RuntimeError(
                f"{self.onnx_path}: unexpected SCRFD output count {len(outputs)} "
                "(expected 6/9/10/15) — not a det_10g-style model?")

        # det_10g preprocessing (scrfd.forward).
        self.input_mean = 127.5
        self.input_std = 128.0
        logger.info(f"NativeSCRFD loaded ({self.onnx_path.split('/')[-1].split(chr(92))[-1]}): "
                    f"outputs={len(outputs)} kps={self.use_kps} "
                    f"anchors={self._num_anchors} strides={self._feat_stride_fpn}")

    def _forward(self, det_img: np.ndarray, threshold: float):
        blob = cv2.dnn.blobFromImage(
            det_img, 1.0 / self.input_std, (det_img.shape[1], det_img.shape[0]),
            (self.input_mean, self.input_mean, self.input_mean), swapRB=True)
        net_outs = self.session.run(self.output_names, {self.input_name: blob})

        input_height, input_width = blob.shape[2], blob.shape[3]
        scores_list, bboxes_list, kpss_list = [], [], []
        for idx, stride in enumerate(self._feat_stride_fpn):
            scores = net_outs[idx]
            bbox_preds = net_outs[idx + self.fmc] * stride
            kps_preds = net_outs[idx + self.fmc * 2] * stride if self.use_kps else None

            height, width = input_height // stride, input_width // stride
            key = (height, width, stride)
            anchor_centers = self.center_cache.get(key)
            if anchor_centers is None:
                anchor_centers = np.stack(
                    np.mgrid[:height, :width][::-1], axis=-1).astype(np.float32)
                anchor_centers = (anchor_centers * stride).reshape(-1, 2)
                if self._num_anchors > 1:
                    anchor_centers = np.stack(
                        [anchor_centers] * self._num_anchors, axis=1).reshape(-1, 2)
                if len(self.center_cache) < 100:
                    self.center_cache[key] = anchor_centers

            pos_inds = np.where(scores >= threshold)[0]
            bboxes = _distance2bbox(anchor_centers, bbox_preds)
            scores_list.append(scores[pos_inds])
            bboxes_list.append(bboxes[pos_inds])
            if self.use_kps:
                kpss = _distance2kps(anchor_centers, kps_preds)
                kpss_list.append(kpss[pos_inds].reshape(-1, 5, 2))
        return scores_list, bboxes_list, kpss_list

    def detect(self,
               img: np.ndarray,
               input_size: Tuple[int, int] = (640, 640),
               det_thresh: float = 0.5,
               nms_thresh: float = 0.4,
               max_num: int = 0) -> List[NativeFace]:
        """Detect faces; returns NativeFace list, best (max det_score) first.

        input_size is letterboxed (aspect preserved) exactly like
        scrfd._detect_candidates; coordinates are mapped back by det_scale.
        """
        if self.static_input_size is not None:
            input_size = self.static_input_size

        im_ratio = float(img.shape[0]) / img.shape[1]
        model_ratio = float(input_size[1]) / input_size[0]
        if im_ratio > model_ratio:
            new_height = input_size[1]
            new_width = int(new_height / im_ratio)
        else:
            new_width = input_size[0]
            new_height = int(new_width * im_ratio)
        det_scale = float(new_height) / img.shape[0]
        resized = cv2.resize(img, (new_width, new_height))
        det_img = np.zeros((input_size[1], input_size[0], 3), dtype=np.uint8)
        det_img[:new_height, :new_width, :] = resized

        scores_list, bboxes_list, kpss_list = self._forward(det_img, det_thresh)

        if not scores_list or sum(s.size for s in scores_list) == 0:
            return []

        scores = np.vstack(scores_list).ravel()
        order = scores.argsort()[::-1]
        bboxes = np.vstack(bboxes_list) / det_scale
        kpss = np.vstack(kpss_list) / det_scale if self.use_kps else None

        pre_det = np.hstack((bboxes, scores.reshape(-1, 1))).astype(np.float32, copy=False)
        pre_det = pre_det[order, :]
        if kpss is not None:
            kpss = kpss[order, :, :]

        keep = _nms(pre_det, nms_thresh)
        det, kpss = pre_det[keep, :], (kpss[keep, :, :] if kpss is not None else None)

        if max_num > 0 and det.shape[0] > max_num:
            # scrfd 'default' metric: area minus 2× center offset distance²
            area = (det[:, 2] - det[:, 0]) * (det[:, 3] - det[:, 1])
            img_center = img.shape[0] // 2, img.shape[1] // 2
            offsets = np.vstack([
                (det[:, 0] + det[:, 2]) / 2 - img_center[1],
                (det[:, 1] + det[:, 3]) / 2 - img_center[0]])
            values = area - np.sum(np.power(offsets, 2.0), 0) * 2.0
            bindex = np.argsort(values)[::-1][:max_num]
            det, kpss = det[bindex, :], (kpss[bindex, :, :] if kpss is not None else None)

        faces: List[NativeFace] = []
        for i in range(det.shape[0]):
            faces.append(NativeFace(
                bbox=det[i, 0:4],
                kps=(kpss[i] if kpss is not None else None),
                det_score=float(det[i, 4])))
        return faces


class NativeArcFace:
    """w600k_mbf-style ArcFace embedder over a raw ONNX session.

    Replicates arcface_onnx.ArcFaceONNX: input 112×112, preprocessing picked
    by the same Sub/Mul graph-name scan (127.5/127.5 for standard glint
    exports, 0/1 for mxnet-style graphs), swapRB blob, single 512-d output.
    Returns RAW (unnormalized) features — callers normalize, mirroring how
    insightface distinguishes face.embedding from face.normed_embedding.
    """

    def __init__(
        self,
        onnx_path: str,
        providers: Optional[Sequence[str]] = None,
        sess_options: Optional[ort.SessionOptions] = None,
    ):
        self.onnx_path = onnx_path
        if sess_options is None:
            sess_options = _make_optimized_session_options(intra_threads=2)
        self.session = ort.InferenceSession(
            onnx_path,
            sess_options=sess_options,
            providers=list(providers) if providers else ["CPUExecutionProvider"],
        )

        # Same heuristic as arcface_onnx.ArcFaceONNX.__init__.
        find_sub = find_mul = False
        try:
            import onnx
            model = onnx.load(onnx_path)
            for node in model.graph.node[:8]:
                if node.name.startswith("Sub") or node.name.startswith("_minus"):
                    find_sub = True
                if node.name.startswith("Mul") or node.name.startswith("_mul"):
                    find_mul = True
        except Exception as e:  # onnx missing/corrupt → fall back to glint default
            logger.warning(f"onnx graph scan failed ({e}); assuming 127.5 preprocessing")
        if find_sub and find_mul:
            self.input_mean, self.input_std = 0.0, 1.0
        else:
            self.input_mean, self.input_std = 127.5, 127.5

        inp = self.session.get_inputs()[0]
        self.input_name = inp.name
        self.input_size = (int(inp.shape[3]), int(inp.shape[2]))  # (w, h)
        outs = self.session.get_outputs()
        assert len(outs) == 1, "ArcFace onnx must have exactly one output"
        self.output_names = [outs[0].name]

    def get_feat(self, aligned_bgr: np.ndarray) -> np.ndarray:
        """RAW 512-d features for a norm_crop'd 112×112 BGR crop."""
        if aligned_bgr.shape[0] != self.input_size[1] or aligned_bgr.shape[1] != self.input_size[0]:
            raise ValueError(
                f"crop is {aligned_bgr.shape[1]}x{aligned_bgr.shape[0]}, "
                f"model expects {self.input_size[0]}x{self.input_size[1]} — feed norm_crop output")
        blob = cv2.dnn.blobFromImage(
            aligned_bgr, 1.0 / self.input_std, self.input_size,
            (self.input_mean, self.input_mean, self.input_mean), swapRB=True)
        return self.session.run(self.output_names, {self.input_name: blob})[0].flatten()

    def normed_embedding(self, aligned_bgr: np.ndarray) -> np.ndarray:
        """L2-normalized 512-d embedding (== insightface face.normed_embedding)."""
        feat = self.get_feat(aligned_bgr).astype(np.float32)
        norm = float(np.linalg.norm(feat))
        if norm < 1e-12:
            raise ValueError("degenerate ArcFace embedding (zero norm)")
        return feat / norm
