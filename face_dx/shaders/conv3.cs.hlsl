// conv3.cs.hlsl — my own general grouped 3x3 convolution kernel, shared-tile.
// The old kernel re-read every input pixel from GLOBAL memory once per output
// channel (the stem conv re-read its 27-value window 64x per thread); this
// version stages the oc-pair's input channels as a padded pixel TILE in
// groupshared ONCE per group, then the 3x3 window walks shared memory.
//
// One group = a 16x16 output pixel tile x ONE output-channel pair (gz).
// The pair's input channel set is small: g==1 -> in_c channels, depthwise ->
// 2 channels, other groups -> 2*in_c_g. Tile dims (16-1)*stride + 3 rows/cols
// handle both stride 1 and stride 2. Weights stay in global memory but every
// pixel-thread in the group reads the SAME weight (L1 broadcast).
//
// FP16 mode (-DFP16): global buffers are u32-packed halves; the shared tile is
// kept as unpacked fp32 after conversion.

cbuffer Params : register(b0) {
    int4 P0;  // in_c, out_c, in_h, in_w
    int4 P1;  // out_h, out_w, kh, kw
    int4 P2;  // stride_h, stride_w, pad_h, pad_w
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
#define stride_h P2.x
#define stride_w P2.y
#define pad_h    P2.z
#define pad_w    P2.w
#define has_bias P3.x
#define prelu    P3.y
#define group    P3.z

#define TH 16
#define TW 16
#define TILE_H (2 * 16 + 3)   // (TH-1)*stride(<=2) + kh(=3), padded rows
#define TILE_W (2 * 16 + 3)
#define NCH 4                 // max input channels staged (2 * in_c_g <= 4)

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

[numthreads(256, 1, 1)]
void main(uint3 gid : SV_GroupID, uint3 tid : SV_GroupThreadID)
{
    const int t = (int)tid.x;
    const int lx = t % TW;
    const int ly = t / TW;
    const int ox = (int)gid.x * TW + lx;   // output pixel
    const int oy = (int)gid.y * TH + ly;
    const int oc = 2 * (int)gid.z;         // output channel pair

    const int in_c_g = in_c / group;
    const int out_g = out_c / group;
    const int g0 = oc / out_g;
    const int g1 = (oc + 1) / out_g;
    const int base0 = g0 * in_c_g;
    const int base1 = g1 * in_c_g;
    const int nch = (base1 != base0) ? 2 * in_c_g : in_c_g;

    // ---- cooperative tile load: absolute input pixel (iy, ix) for the
    // ---- oc-pair's channels; tx fastest so loads are contiguous
    const int to0 = (int)gid.x * TW * stride_w - pad_w;  // input origin
    const int to1 = (int)gid.y * TH * stride_h - pad_h;
    const int total = nch * TILE_H * TILE_W;
    for (int i = t; i < total; i += 256) {
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

    float acc0 = 0.0;
    float acc1 = 0.0;
    if (has_bias == 1) {
        acc0 = aux_elem(oc);
        acc1 = aux_elem(oc + 1);
    }
    const bool split = base1 != base0;
    if (oc + 1 < out_c && oy < out_h && ox < out_w) {
        for (int l = 0; l < in_c_g; l++) {
            for (int ky = 0; ky < kh; ky++) {
                const int ty = ly * stride_h + ky;
                for (int kx = 0; kx < kw; kx++) {
                    const int tx = lx * stride_w + kx;
                    const float v0 = in_sh[l][ty][tx];
                    const float v1 = split ? in_sh[in_c_g + l][ty][tx] : v0;
                    acc0 += w_elem(oc, l, ky, kx) * v0;
                    acc1 += w_elem(oc + 1, l, ky, kx) * v1;
                }
            }
        }
    }
    if (prelu == 1) {
        const float s0 = aux_elem(out_c + oc);
        const float s1 = aux_elem(out_c + oc + 1);
        acc0 = acc0 > 0.0 ? acc0 : s0 * acc0;
        acc1 = acc1 > 0.0 ? acc1 : s1 * acc1;
    }

    const int p = oy * out_w + ox;
    if (oc + 1 < out_c && oy < out_h && ox < out_w) {
#ifdef FP16
        const uint lo = (f32tof16(acc0) & 0xFFFFu) | ((f32tof16(acc1) & 0xFFFFu) << 16);
        OutBuf[(oc >> 1) * (out_h * out_w) + p] = lo;
#else
        OutBuf[oc * (out_h * out_w) + p] = acc0;
        OutBuf[(oc + 1) * (out_h * out_w) + p] = acc1;
#endif
    }
}