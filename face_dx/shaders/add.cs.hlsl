// add.cs.hlsl — my own D3D11 compute kernel: elementwise residual add,
// out = a + b (same size). One thread per packed u32 (fp16) / element (fp32).

cbuffer Params : register(b0) {
    int4 P0;  // n, unused, unused, unused
    int4 P1;
    int4 P2;
    int4 P3;
};

#define n P0.x

#ifdef FP16
Buffer<uint>   InBuf  : register(t0);
Buffer<uint>   WBuf   : register(t1);
RWBuffer<uint> OutBuf : register(u0);

[numthreads(256, 1, 1)]
void main(uint3 gid : SV_DispatchThreadID) {
    int i = (int)gid.x;
    if (i >= n) return;
    uint a = InBuf[i];
    uint b = WBuf[i];
    uint lo = f32tof16(f16tof32(a & 0xFFFFu) + f16tof32(b & 0xFFFFu)) & 0xFFFFu;
    uint hi = (f32tof16(f16tof32(a >> 16) + f16tof32(b >> 16)) & 0xFFFFu) << 16;
    OutBuf[i] = lo | hi;
}
#else
Buffer<float>   InBuf  : register(t0);
Buffer<float>   WBuf   : register(t1);
RWBuffer<float> OutBuf : register(u0);

[numthreads(256, 1, 1)]
void main(uint3 gid : SV_DispatchThreadID) {
    int i = (int)gid.x;
    if (i >= n) return;
    OutBuf[i] = InBuf[i] + WBuf[i];
}
#endif