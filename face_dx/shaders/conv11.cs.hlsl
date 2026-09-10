// conv11.cs.hlsl — my own tiled 1x1 conv kernel (group == 1), shared-memory
// input tiles. Each group: 256 threads = 16 pixels x 16 output-channel PAIRS
// (covers 32 output channels per group). Input channels are processed in
// 128-wide chunks staged through groupshared so each input element is loaded
// from global memory once per group instead of once per thread. Weights stay
// in global memory but are broadcast (all threads read the same w[oc][l] in
// lockstep -> L2-cached). One thread computes an output-channel pair, making
// fp16 (u32-packed halves) stores race-free.
// FP16 mode (via -DFP16): global buffers are u32-packed halves; the shared
// tile is kept in fp32 after conversion.

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

#define TPX 16   // pixels per group
#define TPA 16   // output-channel pairs per group (=> 32 channels)
#define TLC 128  // input-channel chunk staged through shared

groupshared float in_sh[TPX][TLC];

#ifdef FP16
Buffer<uint>  InBuf  : register(t0);
Buffer<uint>  WBuf   : register(t1);
Buffer<uint>  ABuf   : register(t2);
RWBuffer<uint> OutBuf : register(u0);

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
    const int px = t % TPX;
    const int pa = t / TPX;              // pair index 0..TPA-1
    const int p0 = (int)gid.x * TPX;
    const int oc0 = (int)gid.y * (2 * TPA);
    const int oc = oc0 + 2 * pa;

    float acc0 = 0.0;
    float acc1 = 0.0;
    for (int l0 = 0; l0 < in_c; l0 += TLC) {
        // cooperative load of the input tile chunk (all threads participate)
        for (int i = t; i < TPX * TLC; i += 256) {
            const int lc = i % TLC;
            const int lxp = i / TLC;
            const int p = p0 + lxp;
            const int l = l0 + lc;
            in_sh[lxp][lc] = (p < plane && l < in_c) ? in_elem(l, p) : 0.0;
        }
        GroupMemoryBarrierWithGroupSync();

        if (oc + 1 < out_c && p0 + px < plane) {
            const int lim = (in_c - l0) < TLC ? (in_c - l0) : TLC;
            for (int lc = 0; lc < lim; lc++) {
                const float w = in_sh[px][lc];
                acc0 += w * w_elem(oc, l0 + lc);
                acc1 += w * w_elem(oc + 1, l0 + lc);
            }
        }
        GroupMemoryBarrierWithGroupSync();
    }

    const int p = p0 + px;
    if (oc + 1 < out_c && p < plane) {
        if (has_bias == 1) {
            acc0 += aux_elem(oc);
            acc1 += aux_elem(oc + 1);
        }
        if (prelu == 1) {
            acc0 = acc0 > 0.0 ? acc0 : aux_elem(out_c + oc) * acc0;
            acc1 = acc1 > 0.0 ? acc1 : aux_elem(out_c + oc + 1) * acc1;
        }
#ifdef FP16
        uint lo = f32tof16(acc0) & 0xFFFFu;
        uint hi = (f32tof16(acc1) & 0xFFFFu) << 16;
        OutBuf[(oc >> 1) * plane + p] = lo | hi;
#else
        OutBuf[oc * plane + p] = acc0;
        OutBuf[(oc + 1) * plane + p] = acc1;
#endif
    }
}