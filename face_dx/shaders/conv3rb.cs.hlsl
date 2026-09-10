// conv3rb.cs.hlsl — register-blocked 3x3 conv kernel for stride-1 layers
// (depthwise g>=64 and the grouped g=64 conv). Companion to conv3.cs.hlsl,
// which keeps the general 16x16-tile-per-pair shape needed by stride 2.
//
// One group = 8x32 output pixel tile x ONE output-channel pair (gz), 128
// threads = one wave. Each thread computes TWO vertically-adjacent output
// pixels x BOTH channels of the pair -> 4 FMAs per (l,ky,kx) step, halving
// the shared-memory reads per MAC versus one-pixel-per-thread. Weights are
// L1-broadcast (every thread in the group reads the same weight per step).
// The pair's input channels (depthwise 2, grouped 2, g==1 in_c) are staged
// cooperatively through groupshared ONCE per group in a stride-shaped
// 10x10 tile ((8-1)*1+3) — for stride 1 this halves the staging area of a
// 19x19 tile and, for the 7x7 tail layers, cuts redundant tile loads.
//
// FP16 mode (-DFP16): global buffers are u32-packed halves; the shared tile
// is kept as unpacked fp32 after conversion.

cbuffer Params : register(b0) {
    int4 P0;  // in_c, out_c, in_h, in_w
    int4 P1;  // out_h, out_w, kh, kw
    int4 P2;  // stride_h, stride_w, pad_h, pad_w  (stride must be 1)
    int4 P3;  // has_bias, prelu, group, unused
};

#define in_c     P0.x
#define out_c    P0.y
#define in_h     P0.z
#define in_w     P0.w
#define out_h    P1.x
#define out_w    P1.y
#define kh       P1.z
#define kw       P1.w
#define pad_h    P2.z
#define pad_w    P2.w
#define has_bias P3.x
#define prelu    P3.y
#define group    P3.z

#define TW 8                   // output cols per group
#define ROWS 32                // output rows per group (16 thread rows x 2 px)
#define TILE_H (ROWS + 2)      // every output row + halo (ky up to +2)
#define TILE_W (TW + 2)
#define NCH 4                  // max input channels staged (2 * in_c_g <= 4)

groupshared float in_sh[NCH][TILE_H][TILE_W];

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
#endif

[numthreads(128, 1, 1)]
void main(uint3 gid : SV_GroupID, uint3 tid : SV_GroupThreadID)
{
    const int t = (int)tid.x;
    const int lx = t % TW;                    // output col
    const int ly = t / TW;                    // thread row -> 2 output rows
    const int ox = (int)gid.x * TW + lx;
    const int oy = (int)gid.y * ROWS + 2 * ly;
    const int oc = 2 * (int)gid.z;            // output channel pair

    const int in_c_g = in_c / group;
    const int out_g = out_c / group;
    const int g0 = oc / out_g;
    const int g1 = (oc + 1) / out_g;
    const int base0 = g0 * in_c_g;
    const int base1 = g1 * in_c_g;
    const int nch = (base1 != base0) ? 2 * in_c_g : in_c_g;
    const bool split = base1 != base0;

    // ---- cooperative tile load: rows oy-1 .. oy+9, cols ox-1 .. ox+8 ----
    const int to0 = (int)gid.x * TW - pad_w;
    const int to1 = (int)gid.y * ROWS - pad_h;
    const int total = nch * TILE_H * TILE_W;
    for (int i = t; i < total; i += 128) {
        const int tx = i % TILE_W;
        const int ty = (i / TILE_W) % TILE_H;
        const int ch = i / (TILE_H * TILE_W);
        const int iy = to1 + ty;
        const int ix = to0 + tx;
        const int c = (ch < in_c_g) ? base0 + ch : base1 + (ch - in_c_g);
        in_sh[ch][ty][tx] = (ch < nch && c < in_c && iy >= 0 && iy < in_h &&
                             ix >= 0 && ix < in_w)
                                ? in_elem(c, iy * in_w + ix)
                                : 0.0;
    }
    GroupMemoryBarrierWithGroupSync();

    float a00 = 0.0;  // channel oc   at (oy,   ox)
    float a01 = 0.0;  // channel oc+1 at (oy,   ox)
    float a10 = 0.0;  // channel oc   at (oy+1, ox)
    float a11 = 0.0;  // channel oc+1 at (oy+1, ox)
    if (has_bias == 1) {
        a00 = aux_elem(oc);
        a01 = aux_elem(oc + 1);
        a10 = a00;
        a11 = a01;
    }
    if (oc + 1 < out_c && ox < out_w && oy < out_h) {
        if (!split) {
            // depthwise-style: both output channels read the SAME input
            // values -> 2 shared reads per 4 FMAs
            for (int l = 0; l < in_c_g; l++) {
                for (int ky = 0; ky < kh; ky++) {
                    const int ty = 2 * ly + ky;
                    for (int kx = 0; kx < kw; kx++) {
                        const int tx = lx + kx;
                        const float v0 = in_sh[l][ty][tx];
                        const float v1 = in_sh[l][ty + 1][tx];
                        const float w0 = w_elem(oc, l, ky, kx);
                        const float w1 = w_elem(oc + 1, l, ky, kx);
                        a00 += w0 * v0;
                        a01 += w1 * v0;
                        a10 += w0 * v1;
                        a11 += w1 * v1;
                    }
                }
            }
        } else {
            // grouped: second channel reads its own input channel block
            for (int l = 0; l < in_c_g; l++) {
                for (int ky = 0; ky < kh; ky++) {
                    const int ty = 2 * ly + ky;
                    for (int kx = 0; kx < kw; kx++) {
                        const int tx = lx + kx;
                        const float v0 = in_sh[l][ty][tx];
                        const float v1 = in_sh[l][ty + 1][tx];
                        const float u1 = in_sh[in_c_g + l][ty][tx];
                        const float u2 = in_sh[in_c_g + l][ty + 1][tx];
                        const float w0 = w_elem(oc, l, ky, kx);
                        const float w1 = w_elem(oc + 1, l, ky, kx);
                        a00 += w0 * v0;
                        a01 += w1 * u1;
                        a10 += w0 * v1;
                        a11 += w1 * u2;
                    }
                }
            }
        }
    }
    if (prelu == 1) {
        const float s0 = aux_elem(out_c + oc);
        const float s1 = aux_elem(out_c + oc + 1);
        a00 = a00 > 0.0 ? a00 : s0 * a00;
        a01 = a01 > 0.0 ? a01 : s1 * a01;
        a10 = a10 > 0.0 ? a10 : s0 * a10;
        a11 = a11 > 0.0 ? a11 : s1 * a11;
    }

    const int p0 = oy * out_w + ox;
    if (oc + 1 < out_c && ox < out_w && oy < out_h) {
#ifdef FP16
        OutBuf[(oc >> 1) * (out_h * out_w) + p0] =
            (f32tof16(a00) & 0xFFFFu) | ((f32tof16(a01) & 0xFFFFu) << 16);
#else
        OutBuf[oc * (out_h * out_w) + p0] = a00;
        OutBuf[(oc + 1) * (out_h * out_w) + p0] = a01;
#endif
    }
    if (oc + 1 < out_c && ox < out_w && oy + 1 < out_h) {
        const int p1 = (oy + 1) * out_w + ox;
#ifdef FP16
        OutBuf[(oc >> 1) * (out_h * out_w) + p1] =
            (f32tof16(a10) & 0xFFFFu) | ((f32tof16(a11) & 0xFFFFu) << 16);
#else
        OutBuf[oc * (out_h * out_w) + p1] = a10;
        OutBuf[(oc + 1) * (out_h * out_w) + p1] = a11;
#endif
    }
}
