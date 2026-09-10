// gemm_partial.cs.hlsl — my own tiled GEMM, k-split partial pass (v2).
// Vectorized float4 loads: the transposed [k][out_c] weight layout makes 4
// consecutive output channels contiguous, so each thread accumulates 4 oc
// with ONE 16-byte load per k step (vs 2 scalar 4-byte loads before). 128
// threads cover all 512 oc; NSPLIT groups split k (k/NSPLIT per group) and
// write partial sums to scratch[nsplit][out_c]. A second dispatch
// (gemm_final) reduces the partials. The input k-slice is staged through
// groupshared once per group (each input element is read 1x, not 128x).

cbuffer Params : register(b0) {
    int4 P0;  // out_c, k, plane (in_h*in_w), unused
    int4 P1;
    int4 P2;
    int4 P3;
};

#define out_c P0.x
#define k     P0.y
#define plane P0.z

#define NSPLIT 16
#define TPB    128

groupshared float in_sh[196];  // k / NSPLIT = 196

#ifdef FP16
Buffer<uint>  InBuf  : register(t0);
Buffer<uint>  WBuf   : register(t1);
Buffer<uint>  ABuf   : register(t2);
RWBuffer<uint> PartBuf : register(u0);

float in_elem(int i) {
    int c = i / plane;
    int p = i - c * plane;
    uint u = InBuf[(c >> 1) * plane + p];
    uint h = (c & 1) != 0 ? (u >> 16) : (u & 0xFFFFu);
    return f16tof32(h);
}
// 4 oc at fp16 = 2 consecutive packed words (out_c even => idx even)
float4 w4(int i, int ocb) {
    int idx = i * out_c + ocb;
    uint u0 = WBuf[idx >> 1];
    uint u1 = WBuf[(idx >> 1) + 1];
    return float4(f16tof32(u0 & 0xFFFFu), f16tof32(u0 >> 16),
                  f16tof32(u1 & 0xFFFFu), f16tof32(u1 >> 16));
}
#else
Buffer<float>  InBuf  : register(t0);
Buffer<float>  WBuf   : register(t1);
Buffer<float>  ABuf   : register(t2);
RWBuffer<float> PartBuf : register(u0);

float in_elem(int i) { return InBuf[i]; }
// NOTE: Buffer<T>::Load() addresses BYTES, not elements, on typed buffers,
// so vectorize via 4 scalar element reads (the driver folds them into one
// 16-byte load since ocb is 4-aligned and out_c is a multiple of 4)
float4 w4(int i, int ocb) {
    const int b = i * out_c + ocb;
    return float4(WBuf[b], WBuf[b + 1], WBuf[b + 2], WBuf[b + 3]);
}
#endif

[numthreads(TPB, 1, 1)]
void main(uint3 gid : SV_GroupID, uint3 tid : SV_GroupThreadID)
{
    const int t = (int)tid.x;
    const int g = (int)gid.x;
    const int klen = k / NSPLIT;
    const int k0 = g * klen;
    const int oc = 4 * t;

    // cooperative staging of this group's k-slice of the input vector
    for (int i = t; i < klen; i += TPB) {
        const int ki = k0 + i;
        in_sh[i] = ki < k ? in_elem(ki) : 0.0;
    }
    GroupMemoryBarrierWithGroupSync();

    float acc0 = 0.0, acc1 = 0.0, acc2 = 0.0, acc3 = 0.0;
    for (int i = 0; i < klen; i++) {
        const float v = in_sh[i];
        const float4 w = w4(k0 + i, oc);
        acc0 += v * w.x;
        acc1 += v * w.y;
        acc2 += v * w.z;
        acc3 += v * w.w;
    }
#ifdef FP16
    const uint w0 = (f32tof16(acc0) & 0xFFFFu) | ((f32tof16(acc1) & 0xFFFFu) << 16);
    const uint w1 = (f32tof16(acc2) & 0xFFFFu) | ((f32tof16(acc3) & 0xFFFFu) << 16);
    PartBuf[g * (out_c / 2) + (oc >> 1)] = w0;
    PartBuf[g * (out_c / 2) + (oc >> 1) + 1] = w1;
#else
    PartBuf[g * out_c + oc]     = acc0;
    PartBuf[g * out_c + oc + 1] = acc1;
    PartBuf[g * out_c + oc + 2] = acc2;
    PartBuf[g * out_c + oc + 3] = acc3;
#endif
}