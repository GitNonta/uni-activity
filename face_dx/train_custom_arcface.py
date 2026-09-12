#!/usr/bin/env python3
"""
train_custom_arcface.py — Custom ArcFace Training Pipeline with Direct .fvp Exporter.

Designed specifically for the user's own Direct3D 11 Compute Engine (face_dx)
running on Intel iGPU with ZERO third-party runtime dependencies.

Features:
1. Native FVP-Compatible Architecture (NativeArcFaceNet):
   - MobileFaceNet backbone (Conv + PRelu + Depthwise + Add + GEMM)
   - Compatible with face_dx's HLSL shaders (conv.cs, conv11.cs, conv3.cs,
     conv3rb.cs, gemm_partial.cs, gemm_final.cs, add.cs)
2. InsightFace ArcFace Margin Loss:
   - Additive Angular Margin penalty (s=64.0, m=0.50 radians)
   - Hyperspherical metric learning
3. Direct .fvp Serializer:
   - Exports the TRAINED state_dict (not random weights!) to the custom
     binary format (.fvp v2, magic "FVK1")
   - Builds the tensor graph explicitly (tensor 0 = model input, layer j
     writes tensor j+1, ADDs carry two edges) — no manifest needed
   - Assertions enforce the constraints baked into the HLSL kernels
4. check_custom_fvp.py verifies the exported file numerically against the
   PyTorch reference on random inputs.
5. Identity-labeled dataset:
   - labels come from subfolder names or <person>_<n>.png filename prefixes
   - deterministic, sorted label ids (no random label assignment)
"""
from __future__ import annotations

import argparse
import math
import os
import random
import struct
import time
from typing import Dict, List, Tuple

import numpy as np
import torch
import torch.nn as nn
import torch.nn.functional as F
from torch.utils.data import DataLoader, Dataset

MAGIC = b"FVK1"
VERSION = 2
LAYER_CONV = 1
LAYER_ADD = 2
LAYER_GEMM = 3
INPUT_TENSOR = 0xFFFFFFFF  # edge sentinel: model input
AUG_RNG = random.Random(1234)  # dedicated RNG for augmentation (seeded => reproducible runs)


class ArcFaceMarginHead(nn.Module):
    """
    ArcFace Additive Angular Margin Loss Head (Deng et al. / InsightFace)
    Loss = -log( e^(s*cos(theta + m)) / (e^(s*cos(theta + m)) + sum(e^(s*cos(theta_j)))) )
    """
    def __init__(self, in_features: int = 512, num_classes: int = 100, s: float = 64.0, m: float = 0.50):
        super().__init__()
        self.in_features = in_features
        self.num_classes = num_classes
        self.s = s
        self.m = m
        self.weight = nn.Parameter(torch.FloatTensor(num_classes, in_features))
        nn.init.xavier_uniform_(self.weight)

        self.cos_m = math.cos(m)
        self.sin_m = math.sin(m)
        self.th = math.cos(math.pi - m)
        self.mm = math.sin(math.pi - m) * m

    def forward(self, embeddings: torch.Tensor, labels: torch.Tensor) -> torch.Tensor:
        cosine = F.linear(F.normalize(embeddings, p=2, dim=1), F.normalize(self.weight, p=2, dim=1))
        sine = torch.sqrt((1.0 - torch.pow(cosine, 2)).clamp(0, 1))
        phi = cosine * self.cos_m - sine * self.sin_m
        phi = torch.where(cosine > self.th, phi, cosine - self.mm)

        one_hot = torch.zeros(cosine.size(), device=embeddings.device)
        one_hot.scatter_(1, labels.view(-1, 1).long(), 1)
        output = (one_hot * phi) + ((1.0 - one_hot) * cosine)
        output *= self.s
        return output


class InvertedResidual(nn.Module):
    def __init__(self, in_c: int, exp_c: int, out_c: int, stride: int = 1):
        super().__init__()
        self.stride = stride
        self.use_res = (stride == 1 and in_c == out_c)

        # 1x1 expansion
        self.conv1 = nn.Conv2d(in_c, exp_c, kernel_size=1, bias=True)
        self.prelu1 = nn.PReLU(exp_c)

        # 3x3 depthwise
        self.conv2 = nn.Conv2d(exp_c, exp_c, kernel_size=3, stride=stride, padding=1, groups=exp_c, bias=True)
        self.prelu2 = nn.PReLU(exp_c)

        # 1x1 linear projection (no activation)
        self.conv3 = nn.Conv2d(exp_c, out_c, kernel_size=1, bias=True)

    def forward(self, x: torch.Tensor) -> torch.Tensor:
        out = self.prelu1(self.conv1(x))
        out = self.prelu2(self.conv2(out))
        out = self.conv3(out)
        if self.use_res:
            return x + out
        return out


class NativeArcFaceNet(nn.Module):
    """
    FVP-Native MobileFaceNet Architecture producing 512-d embeddings.
    Designed to directly serialize into face_dx's compute graph.
    """
    def __init__(self, embedding_dim: int = 512):
        super().__init__()
        # Stem: Conv 3x3 stride 2 (3 -> 128)
        self.stem_conv = nn.Conv2d(3, 128, kernel_size=3, stride=2, padding=1, bias=True)
        self.stem_prelu = nn.PReLU(128)

        # Depthwise 3x3 stride 1 (128 -> 128, group 64)
        self.dw0_conv = nn.Conv2d(128, 128, kernel_size=3, stride=1, padding=1, groups=64, bias=True)
        self.dw0_prelu = nn.PReLU(128)

        # 1x1 conv (128 -> 128)
        self.conv0 = nn.Conv2d(128, 128, kernel_size=1, bias=True)
        self.prelu0 = nn.PReLU(128)

        # Stage 1: downsample (stride 2, 128 -> 128) + 4 residual blocks
        self.block1_down = nn.Sequential(
            nn.Conv2d(128, 128, kernel_size=3, stride=2, padding=1, groups=128, bias=True),
            nn.PReLU(128),
            nn.Conv2d(128, 128, kernel_size=1, bias=True)
        )
        self.stage1_blocks = nn.ModuleList([InvertedResidual(128, 128, 128, stride=1) for _ in range(4)])

        # Stage 2: expand (128 -> 256) + downsample (stride 2) + 6 residual blocks
        self.conv_trans1 = nn.Sequential(
            nn.Conv2d(128, 256, kernel_size=1, bias=True),
            nn.PReLU(256)
        )
        self.block2_down = nn.Sequential(
            nn.Conv2d(256, 256, kernel_size=3, stride=2, padding=1, groups=256, bias=True),
            nn.PReLU(256),
            nn.Conv2d(256, 256, kernel_size=1, bias=True)
        )
        self.stage2_blocks = nn.ModuleList([InvertedResidual(256, 256, 256, stride=1) for _ in range(6)])

        # Stage 3: expand (256 -> 512) + downsample (stride 2) + 2 residual blocks
        self.conv_trans2 = nn.Sequential(
            nn.Conv2d(256, 512, kernel_size=1, bias=True),
            nn.PReLU(512)
        )
        self.block3_down = nn.Sequential(
            nn.Conv2d(512, 512, kernel_size=3, stride=2, padding=1, groups=512, bias=True),
            nn.PReLU(512),
            nn.Conv2d(512, 256, kernel_size=1, bias=True)
        )
        self.stage3_blocks = nn.ModuleList([InvertedResidual(256, 256, 256, stride=1) for _ in range(2)])

        # Head convs: 256 -> 512 -> 64
        self.head_conv1 = nn.Sequential(
            nn.Conv2d(256, 512, kernel_size=1, bias=True),
            nn.PReLU(512)
        )
        self.head_conv2 = nn.Sequential(
            nn.Conv2d(512, 64, kernel_size=1, bias=True),
            nn.PReLU(64)
        )

        # GEMM / Linear: in = 64 * 7 * 7 = 3136 -> out = 512
        self.fc = nn.Linear(3136, embedding_dim, bias=True)

    def forward(self, x: torch.Tensor, normalize: bool = True) -> torch.Tensor:
        # Stem
        x = self.stem_prelu(self.stem_conv(x))
        x = self.dw0_prelu(self.dw0_conv(x))
        x = self.prelu0(self.conv0(x))

        # Stage 1
        x = self.block1_down(x)
        for b in self.stage1_blocks:
            x = b(x)

        # Stage 2
        x = self.conv_trans1(x)
        x = self.block2_down(x)
        for b in self.stage2_blocks:
            x = b(x)

        # Stage 3
        x = self.conv_trans2(x)
        x = self.block3_down(x)
        for b in self.stage3_blocks:
            x = b(x)

        # Head
        x = self.head_conv1(x)
        x = self.head_conv2(x)  # shape: [batch, 64, 7, 7]

        # Flatten + Linear (+ optional L2 Normalize)
        x = torch.flatten(x, 1)
        raw = self.fc(x)
        if not normalize:
            return raw  # raw GEMM output: matches the .fvp graph the engine runs
        return F.normalize(raw, p=2, dim=1)


def gather_identity_images(crops_dir: str) -> List[Tuple[str, str]]:
    """Collect (image_path, identity) pairs from a crops directory.

    Identity derivation (deterministic, shared by training and evaluation):
      * subfolder layout: crops_dir/<person>/*.png|jpg|webp
          -> identity = subfolder name (takes precedence when present)
      * flat layout:      crops_dir/<person>_<anything>.ext
          -> identity = filename prefix before the FIRST '_'
         (selfie_1.png -> "selfie", test_student_full.png -> "test":
          the first token is the person, the rest are descriptors, so
          test_student.png and test_student_full.png group consistently;
          names without '_' map to the full stem)
    """
    entries: List[Tuple[str, str]] = []
    subdirs = sorted(d for d in os.listdir(crops_dir)
                     if os.path.isdir(os.path.join(crops_dir, d)))
    for d in subdirs:
        dd = os.path.join(crops_dir, d)
        for fn in sorted(os.listdir(dd)):
            if fn.lower().endswith((".png", ".jpg", ".jpeg", ".webp")):
                entries.append((os.path.join(dd, fn), d))
    if not entries:  # flat layout
        for fn in sorted(os.listdir(crops_dir)):
            if fn.lower().endswith((".png", ".jpg", ".jpeg", ".webp")):
                stem = os.path.splitext(fn)[0]
                ident = stem.split("_", 1)[0] if "_" in stem else stem
                entries.append((os.path.join(crops_dir, fn), ident))
    return entries


class SimpleFacesDataset(Dataset):
    """Loads identity-labeled face crops for ArcFace training.

    Identity is derived deterministically from the data layout — never
    randomly:
      * subfolder layout: crops_dir/<person>/*.png|jpg|webp
          -> identity = subfolder name
      * flat layout:      crops_dir/<person>_<anything>.ext
          -> identity = filename prefix before the FIRST '_'
         (selfie_1.png -> "selfie", test_student_full.png -> "test":
          the first token is the person, the rest are descriptors, so
          test_student.png and test_student_full.png group consistently;
          names without '_' map to the full stem)
    Subfolder mode takes precedence when at least one subfolder contains
    images. Label ids are contiguous ints assigned over the SORTED identity
    names, so they are stable across runs.

    Training requires >= 2 identities with >= 2 usable images each: the
    additive angular margin is meaningless for a single sample per class.

    With augment=True, __getitem__ applies light face-preserving augmentation
    (brightness/contrast jitter, scale+translate, horizontal flip) on top of
    the normalized tensor. Evaluation should construct the dataset with
    augment=False so embeddings are deterministic.
    """

    IMG_EXTS = (".png", ".jpg", ".jpeg", ".webp")

    def __init__(self, crops_dir: str, augment: bool = False):
        super().__init__()
        self.augment = augment
        if not crops_dir or not os.path.isdir(crops_dir):
            raise ValueError(f"crops_dir not found: {crops_dir!r}")

        import cv2

        def load_tensor(fp: str) -> torch.Tensor | None:
            img = cv2.imread(fp)
            if img is None:
                return None
            img = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
            img = cv2.resize(img, (112, 112))
            t = torch.from_numpy(img).permute(2, 0, 1).float()
            return (t - 127.5) / 127.5

        # ---- gather (path, identity) pairs ----
        entries = gather_identity_images(crops_dir)

        if not entries:
            raise ValueError(
                f"no images found under {crops_dir!r}; expected "
                f"crops_dir/<person>/*.png or crops_dir/<person>_<n>.png")

        identities = sorted({ident for _, ident in entries})
        label_of = {name: i for i, name in enumerate(identities)}
        self.class_names = identities
        self.num_classes = len(identities)

        self.tensors: List[torch.Tensor] = []
        self.labels: List[int] = []
        self.files: List[str] = []
        skipped = 0
        for fp, ident in entries:
            t = load_tensor(fp)
            if t is None:
                skipped += 1
                continue
            self.tensors.append(t)
            self.labels.append(label_of[ident])
            self.files.append(fp)
        if skipped:
            print(f"[dataset] warning: {skipped} unreadable images skipped")

        per_class = {name: sum(1 for l in self.labels if l == i)
                     for name, i in label_of.items()}
        if self.num_classes < 2 or min(per_class.values()) < 2:
            raise ValueError(
                "need >= 2 identities with >= 2 images each for ArcFace "
                f"margin training; got {per_class} in {crops_dir!r}")

    def __len__(self) -> int:
        return len(self.tensors)

    def _augment(self, t: torch.Tensor) -> torch.Tensor:
        """Light face-preserving augmentation with pure torch ops (no
        torchvision dependency). Returns a NEW tensor; stored tensors are
        never modified."""
        # photometric: brightness/contrast jitter (scalar factors so channel
        # ordering stays natural)
        if AUG_RNG.random() < 0.8:
            t = t * AUG_RNG.uniform(0.8, 1.2) + AUG_RNG.uniform(-0.1, 0.1)
        # geometric: scale 0.9-1.1 + translate up to 6 px (affine_grid takes
        # normalized coords, so a pixel offset maps to px * 2 / 112)
        if AUG_RNG.random() < 0.8:
            s = AUG_RNG.uniform(0.9, 1.1)
            tx = AUG_RNG.uniform(-6.0, 6.0) / 56.0
            ty = AUG_RNG.uniform(-6.0, 6.0) / 56.0
            theta = torch.tensor([[s, 0.0, tx], [0.0, s, ty]], dtype=torch.float32)
            grid = F.affine_grid(theta.unsqueeze(0), (1, 3, 112, 112),
                                 align_corners=False)
            t = F.grid_sample(t.unsqueeze(0), grid, mode="bilinear",
                              padding_mode="zeros", align_corners=False).squeeze(0)
        # horizontal flip: keeps identity, diversifies pose
        if AUG_RNG.random() < 0.5:
            t = torch.flip(t, dims=(2,))
        return torch.clamp(t, -1.0, 1.0)

    def __getitem__(self, idx: int) -> Tuple[torch.Tensor, torch.Tensor]:
        t = self.tensors[idx].clone()
        if self.augment:
            t = self._augment(t)
        return t, torch.tensor(self.labels[idx], dtype=torch.long)


def serialize_model_to_fvp(model: NativeArcFaceNet, out_fvp_path: str) -> None:
    """
    Serializes the TRAINED state_dict of NativeArcFaceNet into .fvp format.

    Walks the graph explicitly (stem -> stages -> residual adds -> head -> gemm),
    packing weights in the exact layouts dx_engine.cpp expects:
      * conv:  [out_c][in_c/group][kh][kw] + bias[out_c] + prelu[out_c]
      * gemm:  transposed [k][out_c] + bias[out_c]  (contiguous float4 loads
        of output-channel quads in gemm_partial.cs.hlsl)
    Tensor edges are chained exactly like the engine resolves them:
    tensor 0 = model input, layer j writes tensor j+1, ADD carries two edges.

    Assertions enforce the constraints baked into the HLSL kernels:
      * conv out_c even (all conv kernels process oc in pairs)
      * group==1 convs route to conv11: out_c a multiple of 4 (4-oc quads)
      * grouped convs stage <= 2 input channels per group (NCH=4 tile)
      * gemm k a multiple of NSPLIT=16 with klen >= 2, out_c == 512
    """
    print(f"[fvp_serializer] Serializing TRAINED weights to {out_fvp_path}...")

    sd = model.state_dict()
    body = bytearray()
    n_layers = 0
    last_tensor = 0  # tensor id produced by the most recent conv (0 = model input)

    def np_f32(t: torch.Tensor) -> np.ndarray:
        return t.detach().cpu().to(torch.float32).contiguous().numpy().astype("<f4")

    def emit_conv(w: torch.Tensor, b: torch.Tensor | None,
                  p: torch.Tensor | None, stride: int = 1, groups: int = 1,
                  pad: int = 0, k: int = 1) -> None:
        # defaults match torch 1x1 convs (pad=0); 3x3 callers pass pad=1
        nonlocal n_layers, last_tensor, body
        out_c, in_c_g = int(w.shape[0]), int(w.shape[1])
        assert out_c % 2 == 0, f"conv out_c={out_c} must be even (oc-pair kernels)"
        if groups == 1:
            assert out_c % 4 == 0, f"conv11 out_c={out_c} must be a multiple of 4"
        else:
            assert out_c % groups == 0, f"out_c={out_c} not divisible by group={groups}"
            assert in_c_g <= 2, f"grouped conv stages <= 2 in-ch/group, got {in_c_g}"

        blob = bytearray()
        blob += struct.pack("<i", LAYER_CONV)
        blob += struct.pack("<12i",
                            int(w.shape[1]) * groups, out_c, k, k,
                            stride, stride, pad, pad, groups,
                            1 if b is not None else 0,
                            1 if p is not None else 0, 0)
        blob += struct.pack("<I", 1)
        blob += struct.pack("<I", last_tensor)  # default path: previous tensor
        wb = bytearray(np_f32(w).tobytes())
        if b is not None:
            wb += np_f32(b).tobytes()
        if p is not None:
            wb += np_f32(p).tobytes()
        blob += struct.pack("<i", len(wb))
        blob += wb
        body += blob
        n_layers += 1
        last_tensor = n_layers  # layer j writes tensor j+1

    def emit_gemm(w: torch.Tensor, b: torch.Tensor) -> None:
        nonlocal n_layers, last_tensor, body
        out_c, kk = int(w.shape[0]), int(w.shape[1])
        assert out_c == 512, f"gemm out_c={out_c}, engine output is 512-d"
        assert out_c % 4 == 0 and kk % 16 == 0 and kk // 16 >= 2, \
            f"gemm k={kk} must be a multiple of NSPLIT=16 with klen >= 2"
        blob = bytearray()
        blob += struct.pack("<i", LAYER_GEMM)
        blob += struct.pack("<12i", out_c, kk, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)
        blob += struct.pack("<I", 1)
        blob += struct.pack("<I", last_tensor)
        wb = bytearray(np_f32(w.t().contiguous()).tobytes())  # [k][out_c] for float4 loads
        wb += np_f32(b).tobytes()
        blob += struct.pack("<i", len(wb))
        blob += wb
        body += blob
        n_layers += 1
        last_tensor = n_layers

    def emit_add(res_tensor: int) -> None:
        """out = tensor(res_tensor) + tensor(last_tensor)."""
        nonlocal n_layers, last_tensor, body
        blob = bytearray()
        blob += struct.pack("<i", LAYER_ADD)
        blob += struct.pack("<12i", 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)
        blob += struct.pack("<I", 2)
        blob += struct.pack("<II", res_tensor, last_tensor)
        blob += struct.pack("<i", 0)
        body += blob
        n_layers += 1
        last_tensor = n_layers

    def emit_stage_blocks(prefix: str, count: int, groups: int) -> None:
        """count InvertedResidual blocks: conv1(1x1+prelu) -> conv2(dw3x3+prelu)
        -> conv3(1x1) -> ADD(res). The residual source is res_src_holder[0]
        (the tensor before the block); each ADD's output becomes both the new
        running tensor and the next block's residual source."""
        for i in range(count):
            p = f"{prefix}.{i}"
            emit_conv(sd[f"{p}.conv1.weight"], sd[f"{p}.conv1.bias"],
                      sd[f"{p}.prelu1.weight"], k=1)
            emit_conv(sd[f"{p}.conv2.weight"], sd[f"{p}.conv2.bias"],
                      sd[f"{p}.prelu2.weight"], stride=1, groups=groups, pad=1, k=3)
            emit_conv(sd[f"{p}.conv3.weight"], sd[f"{p}.conv3.bias"],
                      None, k=1)
            emit_add(res_src_holder[0])
            res_src_holder[0] = last_tensor  # the ADD output feeds the next block

    res_src_holder = [0]

    # ---- stem: conv3 s2 (general kernel) -> dw3x3 s1 g=64 -> 1x1 ----
    emit_conv(sd["stem_conv.weight"], sd["stem_conv.bias"],
              sd["stem_prelu.weight"], stride=2, pad=1, k=3)
    emit_conv(sd["dw0_conv.weight"], sd["dw0_conv.bias"],
              sd["dw0_prelu.weight"], stride=1, groups=64, pad=1, k=3)
    emit_conv(sd["conv0.weight"], sd["conv0.bias"], sd["prelu0.weight"], k=1)

    # ---- stage 1: downsample dw s2 -> 1x1, then 4 residual blocks on the downsample output ----
    emit_conv(sd["block1_down.0.weight"], sd["block1_down.0.bias"],
              sd["block1_down.1.weight"], stride=2, groups=128, pad=1, k=3)
    emit_conv(sd["block1_down.2.weight"], sd["block1_down.2.bias"], None, k=1)
    res_src_holder[0] = last_tensor
    emit_stage_blocks("stage1_blocks", 4, groups=128)

    # ---- stage 2: 1x1 expand -> downsample dw s2 -> 1x1, then 6 residual blocks ----
    emit_conv(sd["conv_trans1.0.weight"], sd["conv_trans1.0.bias"],
              sd["conv_trans1.1.weight"], k=1)
    emit_conv(sd["block2_down.0.weight"], sd["block2_down.0.bias"],
              sd["block2_down.1.weight"], stride=2, groups=256, pad=1, k=3)
    emit_conv(sd["block2_down.2.weight"], sd["block2_down.2.bias"], None, k=1)
    res_src_holder[0] = last_tensor
    emit_stage_blocks("stage2_blocks", 6, groups=256)

    # ---- stage 3: 1x1 expand -> downsample dw s2 -> 1x1, then 2 residual blocks ----
    emit_conv(sd["conv_trans2.0.weight"], sd["conv_trans2.0.bias"],
              sd["conv_trans2.1.weight"], k=1)
    emit_conv(sd["block3_down.0.weight"], sd["block3_down.0.bias"],
              sd["block3_down.1.weight"], stride=2, groups=512, pad=1, k=3)
    emit_conv(sd["block3_down.2.weight"], sd["block3_down.2.bias"], None, k=1)
    res_src_holder[0] = last_tensor
    emit_stage_blocks("stage3_blocks", 2, groups=256)

    # ---- head + 512-d projection ----
    emit_conv(sd["head_conv1.0.weight"], sd["head_conv1.0.bias"],
              sd["head_conv1.1.weight"], k=1)
    emit_conv(sd["head_conv2.0.weight"], sd["head_conv2.0.bias"],
              sd["head_conv2.1.weight"], k=1)
    emit_gemm(sd["fc.weight"], sd["fc.bias"])

    os.makedirs(os.path.dirname(os.path.abspath(out_fvp_path)), exist_ok=True)
    with open(out_fvp_path, "wb") as f:
        f.write(MAGIC + struct.pack("<II", VERSION, n_layers) + body)

    print(f"[fvp_serializer] wrote {out_fvp_path} "
          f"({len(body)} bytes, {n_layers} layers, TRAINED weights)")


def train_scratch(
    epochs: int = 5,
    batch_size: int = 16,
    lr: float = 1e-3,
    out_fvp: str = "face_dx/models/custom_arcface.fvp",
    crops_dir: str = "face_cpp/testdata/crops",
    augment: bool = True
) -> None:
    device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
    print(f"[ArcFace Scratch Training] Initializing NativeArcFaceNet on device: {device}")

    model = NativeArcFaceNet(embedding_dim=512).to(device)

    dataset = SimpleFacesDataset(crops_dir=crops_dir, augment=augment)
    print(f"[dataset] augmentation: {'ON' if augment else 'OFF'}")
    print("[dataset] identity classes (sorted, deterministic ids): "
          + ", ".join(f"{n}={i}(x{dataset.labels.count(i)})"
                      for i, n in enumerate(dataset.class_names)))

    arcface_head = ArcFaceMarginHead(in_features=512, num_classes=dataset.num_classes,
                                     s=64.0, m=0.50).to(device)

    if len(dataset) < batch_size:
        print(f"[ArcFace Scratch Training] batch size reduced to dataset "
              f"size ({len(dataset)}); drop_last would otherwise yield 0 steps")
        batch_size = len(dataset)
    loader = DataLoader(dataset, batch_size=batch_size, shuffle=True, drop_last=True)

    optimizer = torch.optim.AdamW(
        list(model.parameters()) + list(arcface_head.parameters()),
        lr=lr,
        weight_decay=1e-4
    )
    criterion = nn.CrossEntropyLoss()

    print(f"[ArcFace Scratch Training] Starting training ({epochs} epochs, ArcFace s=64.0, m=0.50)...")
    print(f"{'Epoch':<8} {'Loss':<12} {'Accuracy':<12} {'Time (s)':<10}")
    print("-" * 46)

    for ep in range(1, epochs + 1):
        t0 = time.perf_counter()
        model.train()
        arcface_head.train()
        total_loss = 0.0
        correct = 0
        total = 0

        for images, labels in loader:
            images = images.to(device)
            labels = labels.to(device)

            optimizer.zero_grad()
            emb = model(images)
            logits = arcface_head(emb, labels)
            loss = criterion(logits, labels)
            loss.backward()
            optimizer.step()

            total_loss += loss.item()
            preds = logits.argmax(dim=1)
            correct += (preds == labels).sum().item()
            total += labels.size(0)

        dt = time.perf_counter() - t0
        avg_loss = total_loss / max(1, len(loader))
        acc = (correct / max(1, total)) * 100.0
        print(f"{ep:<8} {avg_loss:<12.4f} {acc:<11.2f}% {dt:<10.2f}")

    print("-" * 46)
    print("[ArcFace Scratch Training] Training completed successfully!")

    # Keep a proper checkpoint of the trained weights
    ckpt_path = os.path.splitext(out_fvp)[0] + ".pt"
    torch.save(model.state_dict(), ckpt_path)
    print(f"[ArcFace Scratch Training] checkpoint saved: {ckpt_path}")

    # Export the TRAINED weights to .fvp for face_dx.exe
    serialize_model_to_fvp(model, out_fvp)


def main():
    here = os.path.dirname(os.path.abspath(__file__))
    default_crops = os.path.normpath(os.path.join(here, "..", "face_cpp", "testdata", "crops"))
    default_out = os.path.join(here, "models", "custom_arcface.fvp")

    parser = argparse.ArgumentParser(description="Train Custom ArcFace and Export Directly to .fvp for Intel iGPU")
    parser.add_argument("--epochs", type=int, default=5, help="Number of training epochs")
    parser.add_argument("--batch-size", type=int, default=16, help="Batch size")
    parser.add_argument("--lr", type=float, default=1e-3, help="Learning rate")
    parser.add_argument("--no-augment", action="store_true",
                        help="disable training augmentation")
    parser.add_argument("--crops-dir", type=str, default=default_crops,
                        help="Identity-labeled crops: subfolder per person, "
                             "or flat <person>_<n>.png files")
    parser.add_argument("--out", type=str, default=default_out, help="Output .fvp path")
    args = parser.parse_args()

    train_scratch(
        epochs=args.epochs,
        batch_size=args.batch_size,
        lr=args.lr,
        out_fvp=args.out,
        crops_dir=args.crops_dir,
        augment=not args.no_augment
    )


if __name__ == "__main__":
    main()
