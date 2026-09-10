// conv.cs.hlsl — my own D3D11 compute kernel: general grouped convolution.
// One thread per output pixel; each thread computes an output-channel pair so
// fp16 (u32-packed halves) stores are race-free. Handles group == 1 (standard
// conv), 1 < group < C (grouped, e.g. MobileFaceNet's 128->128 g=64 layer) and
// group == C (pure depthwise). Weight layout [out_c, in_c/group, kh, kw].
// Epilogue fused: bias + per-channel PRelu.
// FP16 mode (via -DFP16): activations/weights are u32-packed half pairs,
// element (c, p) at u32[(c>>1)*plane + p], half = c&1.

cbuffer Params : register(b0) {
    int4 P0;  // in_c, out_c, in_h, in_w
    int4 P1;  // out_h, out_w, kh, kw
    int4 P2;  // stride_h, stride_w, pad_h, pad_w
    int4 P3;  // has_bias, prelu, group, unused
};

#define in_c  P0.x
#define out_c P0.y
#define in_h  P0.z
#define in_w  P0.w
#define out_h P1.x
#define out_w P1.y
#define kh    P1.z
#define kw    P1.w
#define stride_h P2.x
#define stride_w P2.y
#define pad_h  P2.z
#define pad_w  P2.w
#define has_bias P3.x
#define prelu  P3.y
#define group  P3.z

#ifdef FP16
Buffer<uint>  InBuf  : register(t0);
Buffer<uint>  WBuf   : register(t1);
Buffer<uint>  ABuf   : register(t2);
RWBuffer<uint> OutBuf : register(u0);

float in_elem(int c, int p) {
    uint u = InBuf[(c >> 1) * (in_h * in_w) + p];
    uint h = (c & 1) != 0 ? (u >> 16) : (u & 0xFFFFu);
    return f16tof32(h);
}
float w_elem(int o, int l, int ky, int kx) {
    int idx = ((o * (in_c / group) + l) * kh + ky) * kw + kx;
    uint u = WBuf[idx >> 1];
    uint h = (idx & 1) != 0 ? (u >> 16) : (u & 0xFFFFu);
    return f16tof32(h);
}
float aux_elem(int c) {
    uint u = ABuf[c >> 1];
    uint h = (c & 1) != 0 ? (u >> 16) : (u & 0xFFFFu);
    return f16tof32(h);
}
void store_pair(int oc, int p, float v0, float v1) {
    uint lo = f32tof16(v0) & 0xFFFFu;
    uint hi = (f32tof16(v1) & 0xFFFFu) << 16;
    OutBuf[(oc >> 1) * (out_h * out_w) + p] = lo | hi;
}
#else
Buffer<float>  InBuf  : register(t0);
Buffer<float>  WBuf   : register(t1);
Buffer<float>  ABuf   : register(t2);
RWBuffer<float> OutBuf : register(u0);

float in_elem(int c, int p) { return InBuf[c * (in_h * in_w) + p]; }
float w_elem(int o, int l, int ky, int kx) {
    return WBuf[((o * (in_c / group) + l) * kh + ky) * kw + kx];
}
float aux_elem(int c) { return ABuf[c]; }
void store_pair(int oc, int p, float v0, float v1) {
    int plane = out_h * out_w;
    OutBuf[oc * plane + p] = v0;
    OutBuf[(oc + 1) * plane + p] = v1;
}
#endif

[numthreads(8, 8, 1)]
void main(uint3 gid : SV_DispatchThreadID) {
    int x = (int)gid.x;
    int y = (int)gid.y;
    if (x >= out_w || y >= out_h) return;

    int p = y * out_w + x;
    int in_c_g = in_c / group;  // input channels per group
    int out_g = out_c / group;  // output channels per group

    for (int oc = 0; oc < out_c; oc += 2) {
        int g0 = oc / out_g;
        int g1 = (oc + 1) / out_g;
        int base0 = g0 * in_c_g;
        int base1 = g1 * in_c_g;

        float acc0 = 0.0;
        float acc1 = 0.0;
        if (has_bias == 1) {
            acc0 = aux_elem(oc);
            acc1 = aux_elem(oc + 1);
        }
        for (int l = 0; l < in_c_g; l++) {
            for (int ky = 0; ky < kh; ky++) {
                int iy = y * stride_h + ky - pad_h;
                if (iy < 0 || iy >= in_h) continue;
                for (int kx = 0; kx < kw; kx++) {
                    int ix = x * stride_w + kx - pad_w;
                    if (ix < 0 || ix >= in_w) continue;
                    float v = in_elem(base0 + l, iy * in_w + ix);
                    acc0 += w_elem(oc, l, ky, kx) * v;
                    if (base1 != base0) {
                        // oc and oc+1 are in different groups (pure depthwise)
                        acc1 += w_elem(oc + 1, l, ky, kx) * in_elem(base1 + l, iy * in_w + ix);
                    } else {
                        acc1 += w_elem(oc + 1, l, ky, kx) * v;
                    }
                }
            }
        }
        if (prelu == 1) {
            float s0 = aux_elem(out_c + oc);
            float s1 = aux_elem(out_c + oc + 1);
            acc0 = acc0 > 0.0 ? acc0 : s0 * acc0;
            acc1 = acc1 > 0.0 ? acc1 : s1 * acc1;
        }
        store_pair(oc, p, acc0, acc1);
    }
}