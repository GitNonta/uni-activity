// conv11.cs.hlsl — my own tiled 1x1 conv kernel (group == 1), v5.
// float4 register-blocked GEMM tile: each thread computes a 4oc x 4px output
// block (16 FMAs) from TWO float4 shared reads (one weight quad, one pixel
// quad) per channel step -> 4:1 FMA:shared-read ratio and no bank conflicts
// (float4 rows of 16 occupy exactly 32 banks). Shared arrays are channel-major
// float4 rows. Tile: 64 pixels x 64 output channels per group; 256 threads =
// 16 px-quads x 16 oc-quads; chunks of TLC=32 channels staged per iteration.
// FP16 mode (-DFP16): global buffers are u32-packed halves; shared is fp32.

cbuffer Params : register(b0) {
    int4 P0;  // in_c, out_c, plane (H*W), unused
    int4 P1;
    int4 P2;
    int4 P3;  // has_bias, prelu, unused, unused
};

#define in_c     P0.x
#define out_c    P0.y
#define plane    P0.z
#define has_bias P3.x
#define prelu    P3.y

#define TPX 64   // pixels per group (16 thread px-quads x 4)
#define TPA 16   // oc-quads per group (16 x 4 oc = 64 channels)
#define TLC 32   // channel chunk staged through shared

groupshared float4 in_sh[TLC][16];  // [lc][px-quad] = pixels 4q..4q+3
groupshared float4 w_sh[TLC][TPA];  // [lc][oc-quad] = channels 4q..4q+3

#ifdef FP16
Buffer<uint>  InBuf  : register(t0);
Buffer<uint>  WBuf   : register(t1);
Buffer<uint>  ABuf   : register(t2);
RWBuffer<uint> OutBuf : register(u0);

// scalar element read honoring the channel-pair half position
float in_elem(int c, int p) {
    uint u = InBuf[(c >> 1) * plane + p];
    uint h = (c & 1) != 0 ? (u >> 16) : (u & 0xFFFFu);
    return f16tof32(h);
}
float w_elem(int o, int l) {
    int idx = o * in_c + l;
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

float in_elem(int c, int p) { return InBuf[c * plane + p]; }
float w_elem(int o, int l) { return WBuf[o * in_c + l]; }
float aux_elem(int c) { return ABuf[c]; }
#endif

[numthreads(256, 1, 1)]
void main(uint3 gid : SV_GroupID, uint3 tid : SV_GroupThreadID)
{
    const int t = (int)tid.x;
    const int pxq = t % 16;            // pixel-quad index (4 px each)
    const int pa = t / 16;             // oc-quad index (4 oc each)
    const int pb = (int)gid.x * TPX + 4 * pxq;    // pixel base
    const int oc0 = (int)gid.y * (4 * TPA);
    const int oc = oc0 + 4 * pa;

    float4 acc0 = float4(0, 0, 0, 0);  // oc+0..oc+3 at px pb
    float4 acc1 = float4(0, 0, 0, 0);  // at px pb+1
    float4 acc2 = float4(0, 0, 0, 0);  // at px pb+2
    float4 acc3 = float4(0, 0, 0, 0);  // at px pb+3

    for (int l0 = 0; l0 < in_c; l0 += TLC) {
        const int lim = (in_c - l0) < TLC ? (in_c - l0) : TLC;

        // input tile: px-quads fastest -> consecutive threads read 16B chunks
        // of the same channel (contiguous global lines, conflict-free banks);
        // per-pixel bounds so odd-plane edges keep their valid pixels
        for (int i = t; i < TLC * 16; i += 256) {
            const int q = i % 16;
            const int lc = i / 16;
            const int p = (int)gid.x * TPX + 4 * q;
            const int l = l0 + lc;
            float4 v = float4(0, 0, 0, 0);
            if (l < in_c) {
                if (p < plane) v.x = in_elem(l, p);
                if (p + 1 < plane) v.y = in_elem(l, p + 1);
                if (p + 2 < plane) v.z = in_elem(l, p + 2);
                if (p + 3 < plane) v.w = in_elem(l, p + 3);
            }
            in_sh[lc][q] = v;
        }
        // weight tile, one oc at a time so each loop is coalesced
        // (consecutive lc => consecutive addresses for fixed o)
        for (int k = 0; k < 4; k++) {
            for (int i = t; i < TLC * TPA; i += 256) {
                const int q = i % TPA;
                const int lc = i / TPA;
                const int o = oc0 + 4 * q + k;
                const int l = l0 + lc;
                if (o < out_c && l < in_c) w_sh[lc][q][k] = w_elem(o, l);
            }
        }
        GroupMemoryBarrierWithGroupSync();

        if (oc + 3 < out_c && pb < plane) {
            for (int lc = 0; lc < lim; lc++) {
                const float4 w = w_sh[lc][pa];
                const float4 v = in_sh[lc][pxq];
                acc0 += w * v.x;
                acc1 += w * v.y;
                acc2 += w * v.z;
                acc3 += w * v.w;
            }
        }
        GroupMemoryBarrierWithGroupSync();
    }

    if (oc + 3 < out_c && pb < plane) {
        if (has_bias == 1) {
            const float4 b = float4(aux_elem(oc), aux_elem(oc + 1),
                                    aux_elem(oc + 2), aux_elem(oc + 3));
            acc0 += b; acc1 += b; acc2 += b; acc3 += b;
        }
        if (prelu == 1) {
            const float4 s = float4(aux_elem(out_c + oc), aux_elem(out_c + oc + 1),
                                    aux_elem(out_c + oc + 2), aux_elem(out_c + oc + 3));
            acc0 = acc0 > 0.0 ? acc0 : s * acc0;
            acc1 = acc1 > 0.0 ? acc1 : s * acc1;
            acc2 = acc2 > 0.0 ? acc2 : s * acc2;
            acc3 = acc3 > 0.0 ? acc3 : s * acc3;
        }
#ifdef FP16
        // the 4-channel quad = TWO channel-pair words per pixel:
        // (oc,oc+1) at wb0 and (oc+2,oc+3) at wb1; store each pixel's pair
        const int wb0 = (oc >> 1) * plane;
        const int wb1 = ((oc + 2) >> 1) * plane;
        const uint p00 = (f32tof16(acc0.x) & 0xFFFFu) | ((f32tof16(acc0.y) & 0xFFFFu) << 16);
        const uint p01 = (f32tof16(acc0.z) & 0xFFFFu) | ((f32tof16(acc0.w) & 0xFFFFu) << 16);
        const uint p10 = (f32tof16(acc1.x) & 0xFFFFu) | ((f32tof16(acc1.y) & 0xFFFFu) << 16);
        const uint p11 = (f32tof16(acc1.z) & 0xFFFFu) | ((f32tof16(acc1.w) & 0xFFFFu) << 16);
        const uint p20 = (f32tof16(acc2.x) & 0xFFFFu) | ((f32tof16(acc2.y) & 0xFFFFu) << 16);
        const uint p21 = (f32tof16(acc2.z) & 0xFFFFu) | ((f32tof16(acc2.w) & 0xFFFFu) << 16);
        const uint p30 = (f32tof16(acc3.x) & 0xFFFFu) | ((f32tof16(acc3.y) & 0xFFFFu) << 16);
        const uint p31 = (f32tof16(acc3.z) & 0xFFFFu) | ((f32tof16(acc3.w) & 0xFFFFu) << 16);
        OutBuf[wb0 + pb] = p00;
        OutBuf[wb1 + pb] = p01;
        if (pb + 1 < plane) {
            OutBuf[wb0 + pb + 1] = p10;
            OutBuf[wb1 + pb + 1] = p11;
        }
        if (pb + 2 < plane) {
            OutBuf[wb0 + pb + 2] = p20;
            OutBuf[wb1 + pb + 2] = p21;
        }
        if (pb + 3 < plane) {
            OutBuf[wb0 + pb + 3] = p30;
            OutBuf[wb1 + pb + 3] = p31;
        }
#else
        for (int k = 0; k < 4; k++) {
            const int ob = (oc + k) * plane + pb;
            OutBuf[ob] = acc0[k];
            if (pb + 1 < plane) OutBuf[ob + 1] = acc1[k];
            if (pb + 2 < plane) OutBuf[ob + 2] = acc2[k];
            if (pb + 3 < plane) OutBuf[ob + 3] = acc3[k];
        }
#endif
    }
}