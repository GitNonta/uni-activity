// gemm_final.cs.hlsl — reduce the k-split partial sums: out[oc] =
// sum_g partial[g][oc] + bias. 1 group x 256 threads (one output-channel pair
// per thread; race-free fp16 stores).

cbuffer Params : register(b0) {
    int4 P0;  // out_c, KSPLIT, unused, unused
    int4 P1;
    int4 P2;
    int4 P3;
};

#define out_c P0.x
#define nsplit P0.y

#ifdef FP16
Buffer<uint>  PartBuf : register(t0);
Buffer<uint>  ABuf    : register(t1);
RWBuffer<uint> OutBuf : register(u0);

float part_elem(int idx) {
    uint u = PartBuf[idx >> 1];
    uint h = (idx & 1) != 0 ? (u >> 16) : (u & 0xFFFFu);
    return f16tof32(h);
}
float aux_elem(int c) {
    uint u = ABuf[c >> 1];
    uint h = (c & 1) != 0 ? (u >> 16) : (u & 0xFFFFu);
    return f16tof32(h);
}
#else
Buffer<float>  PartBuf : register(t0);
Buffer<float>  ABuf    : register(t1);
RWBuffer<float> OutBuf : register(u0);

float part_elem(int idx) { return PartBuf[idx]; }
float aux_elem(int c) { return ABuf[c]; }
#endif

[numthreads(256, 1, 1)]
void main(uint3 gid : SV_GroupID, uint3 tid : SV_GroupThreadID)
{
    const int oc = 2 * (int)tid.x;
    if (oc + 1 >= out_c) return;
    float acc0 = 0.0;
    float acc1 = 0.0;
    for (int g = 0; g < nsplit; g++) {
        acc0 += part_elem(g * out_c + oc);
        acc1 += part_elem(g * out_c + oc + 1);
    }
    acc0 += aux_elem(oc);
    acc1 += aux_elem(oc + 1);
#ifdef FP16
    uint lo = f32tof16(acc0) & 0xFFFFu;
    uint hi = (f32tof16(acc1) & 0xFFFFu) << 16;
    OutBuf[oc >> 1] = lo | hi;
#else
    OutBuf[oc] = acc0;
    OutBuf[oc + 1] = acc1;
#endif
}