// dx_engine.cpp — my own Direct3D 11 compute inference engine for MobileFaceNet.
// See dx_engine.h for the design. All GPU work goes through the Windows OS
// (d3d11.dll + Intel driver) with kernels written from scratch in HLSL.
#include "dx_engine.h"

#include <cstdio>
#include <cstring>
#include <cmath>
#include <fstream>
#include <chrono>
#include <thread>

#define COBJMACROS
#include <windows.h>
#include "d3d11.h"
#include "d3dcompiler.h"

namespace fdx {

// ---------------------------------------------------------------------------
// .fvp model loader (my own binary format, produced by export_model.py)
// ---------------------------------------------------------------------------
bool load_model(const std::string& path, Model& model, std::string* err)
{
    std::ifstream f(path, std::ios::binary);
    if (!f) {
        if (err) *err = "cannot open " + path;
        return false;
    }
    auto read_bytes = [&](void* dst, size_t n) -> bool {
        f.read((char*)dst, (std::streamsize)n);
        return (size_t)f.gcount() == n;
    };

    char magic[4];
    if (!read_bytes(magic, 4) || memcmp(magic, "FVK1", 4) != 0) {
        if (err) *err = "bad magic (not a .fvp file)";
        return false;
    }
    uint32_t version = 0, n_layers = 0;
    if (!read_bytes(&version, 4) || !read_bytes(&n_layers, 4) || version != 2) {
        if (err) *err = "unsupported format version";
        return false;
    }

    model.layers.resize(n_layers);
    model.tensor_counts.clear();
    model.tensor_shapes.clear();
    model.tensor_counts.push_back(model.in_count);
    model.tensor_shapes.insert(model.tensor_shapes.end(), {1, 3, 112, 112});

    for (uint32_t li = 0; li < n_layers; li++) {
        Layer& L = model.layers[li];
        int32_t type = 0;
        if (!read_bytes(&type, 4) || !read_bytes(L.params, sizeof(L.params))) {
            if (err) *err = "truncated layer header";
            return false;
        }
        L.type = (int)type;

        uint32_t n_in = 0;
        if (!read_bytes(&n_in, 4) || n_in > 2) {
            if (err) *err = "bad input count";
            return false;
        }
        L.n_inputs = (int)n_in;
        for (uint32_t k = 0; k < n_in; k++) {
            uint32_t v = 0;
            if (!read_bytes(&v, 4)) return false;
            L.inputs[k] = (v == 0xFFFFFFFFu) ? -1 : (int)v;
        }
        int32_t wbytes = 0;
        if (!read_bytes(&wbytes, 4) || wbytes < 0) {
            if (err) *err = "bad weight size";
            return false;
        }
        L.weights.resize((size_t)wbytes / 4);
        if (!L.weights.empty() && !read_bytes(L.weights.data(), (size_t)wbytes)) {
            if (err) *err = "truncated weights";
            return false;
        }

        // resolve output shape / count from the input tensor's shape
        const int tin = (L.inputs[0] >= 0) ? L.inputs[0] : 0;
        const int* ish = &model.tensor_shapes[tin * 4];

        if (L.type == LAYER_CONV) {
            const int in_c = L.params[0], out_c = L.params[1];
            const int kh = L.params[2], kw = L.params[3];
            const int sh = L.params[4], sw = L.params[5];
            const int ph = L.params[6], pw = L.params[7];
            const int out_h = (ish[2] + 2 * ph - kh) / sh + 1;
            const int out_w = (ish[3] + 2 * pw - kw) / sw + 1;
            L.out_shape[0] = 1;
            L.out_shape[1] = out_c;
            L.out_shape[2] = out_h;
            L.out_shape[3] = out_w;
            L.aux_offset_elements = out_c * (in_c / L.params[8]) * kh * kw;  // bias+prelu follow
        } else if (L.type == LAYER_GEMM) {
            const int out_c = L.params[0], k = L.params[1];
            L.out_shape[0] = 1;
            L.out_shape[1] = out_c;
            L.out_shape[2] = 1;
            L.out_shape[3] = 1;
            L.aux_offset_elements = out_c * k;
        } else {  // ADD
            for (int d = 0; d < 4; d++) L.out_shape[d] = ish[d];
        }

        L.out_count = L.out_shape[0] * L.out_shape[1] * L.out_shape[2] * L.out_shape[3];
        model.tensor_counts.push_back(L.out_count);
        model.tensor_shapes.insert(model.tensor_shapes.end(), L.out_shape, L.out_shape + 4);
    }
    return true;
}

// ---------------------------------------------------------------------------
// helpers
// ---------------------------------------------------------------------------
static uint32_t float_to_half(float f)
{
    uint32_t x;
    memcpy(&x, &f, 4);
    const uint32_t sign = (x >> 16) & 0x8000u;
    int32_t exp = (int32_t)((x >> 23) & 0xFF) - 127 + 15;
    uint32_t man = x & 0x7FFFFFu;
    if (((x >> 23) & 0xFF) == 0xFF) {  // inf/nan
        return sign | 0x7C00u | (man ? 0x200u : 0);
    }
    if (exp >= 31) return sign | 0x7C00u;              // overflow -> inf
    if (exp <= 0) {
        if (exp < -10) return sign;                    // underflow -> 0
        man = (man | 0x800000u) >> (1 - exp);          // subnormal
        return sign | ((man + 0x1000u) >> 13);         // round to nearest
    }
    uint32_t h = sign | ((uint32_t)exp << 10) | (man >> 13);
    if (man & 0x1000u) h += 1;                         // round to nearest
    return h;
}

static float half_to_float(uint32_t h)
{
    const uint32_t sign = (h & 0x8000u) << 16;
    uint32_t exp = (h >> 10) & 0x1Fu;
    uint32_t man = h & 0x3FFu;
    uint32_t x;
    if (exp == 0) {
        if (man == 0) {
            x = sign;
        } else {  // subnormal
            exp = 127 - 15 + 1;
            while (!(man & 0x400u)) {
                man <<= 1;
                exp--;
            }
            man &= 0x3FFu;
            x = sign | (exp << 23) | (man << 13);
        }
    } else if (exp == 31) {
        x = sign | 0x7F800000u | (man << 13);
    } else {
        x = sign | ((exp - 15 + 127) << 23) | (man << 13);
    }
    float f;
    memcpy(&f, &x, 4);
    return f;
}

// minimal COM scoped pointer (my own, no ATL/WRL). Move-only: copying a raw
// COM pointer without AddRef leads to double-release, so copies are deleted.
template <typename T>
struct ComPtr {
    T* p = nullptr;
    ~ComPtr() { if (p) p->Release(); }
    ComPtr() = default;
    ComPtr(const ComPtr&) = delete;
    ComPtr& operator=(const ComPtr&) = delete;
    ComPtr(ComPtr&& o) noexcept : p(o.p) { o.p = nullptr; }
    ComPtr& operator=(ComPtr&& o) noexcept
    {
        if (p != o.p) {  // same-pointer (incl. self/null) is a no-op
            if (p) p->Release();
            p = o.p;
            o.p = nullptr;
        }
        return *this;
    }
    T** operator&() { return &p; }
    T* operator->() const { return p; }
    operator T*() const { return p; }
    T* get() const { return p; }
    void reset() { if (p) { p->Release(); p = nullptr; } }
};

struct Buffer {
    ComPtr<ID3D11Buffer> buf;
    ComPtr<ID3D11ShaderResourceView> srv;
    ComPtr<ID3D11UnorderedAccessView> uav;
    UINT elements = 0;  // in 4-byte words (fp32 floats or packed u32s)
    size_t bytes = 0;
};

// ---------------------------------------------------------------------------
// engine
// ---------------------------------------------------------------------------
struct Engine::Impl {
    bool fp16 = false;

    ComPtr<ID3D11Device> dev;
    ComPtr<ID3D11DeviceContext> ctx;
    ComPtr<ID3D11ComputeShader> cs_conv, cs_conv11, cs_conv3, cs_conv3rb, cs_gemm_partial, cs_gemm_final, cs_add;
    Buffer scratch;  // gemm k-split partial sums [16][512]
    ComPtr<ID3D11Buffer> cbuf;
    ComPtr<ID3D11Query> sync_q;

    std::vector<Buffer> tensor;   // index 0 = model input
    std::vector<Buffer> weight;
    std::vector<Buffer> aux;      // separate per-layer bias/prelu buffer (offset-free)
    std::vector<ComPtr<ID3D11Query>> ts_begin, ts_end;  // per-layer GPU timestamps
    std::vector<double> ts_gpu_ms;
    ComPtr<ID3D11Query> ts_disjoint;
    double ts_freq = 0.0;
    std::vector<ComPtr<ID3D11ShaderResourceView>> srv_scratch;
    std::vector<ComPtr<ID3D11UnorderedAccessView>> uav_scratch;

    size_t elements_to_words(int n) const { return (size_t)((n + 1) / 2); }

    bool make_buffer(size_t bytes, UINT elements, Buffer& b)
    {
        D3D11_BUFFER_DESC bd{};
        bd.ByteWidth = (UINT)bytes;
        bd.Usage = D3D11_USAGE_DEFAULT;
        bd.BindFlags = D3D11_BIND_SHADER_RESOURCE | D3D11_BIND_UNORDERED_ACCESS;
        if (FAILED(dev->CreateBuffer(&bd, nullptr, &b.buf))) return false;
        D3D11_SHADER_RESOURCE_VIEW_DESC sv{};
        sv.Format = fp16 ? DXGI_FORMAT_R32_UINT : DXGI_FORMAT_R32_FLOAT;
        sv.ViewDimension = D3D11_SRV_DIMENSION_BUFFER;
        sv.Buffer.FirstElement = 0;
        sv.Buffer.NumElements = elements;
        if (FAILED(dev->CreateShaderResourceView(b.buf, &sv, &b.srv))) return false;
        D3D11_UNORDERED_ACCESS_VIEW_DESC uv{};
        uv.Format = fp16 ? DXGI_FORMAT_R32_UINT : DXGI_FORMAT_R32_FLOAT;
        uv.ViewDimension = D3D11_UAV_DIMENSION_BUFFER;
        uv.Buffer.FirstElement = 0;
        uv.Buffer.NumElements = elements;
        if (FAILED(dev->CreateUnorderedAccessView(b.buf, &uv, &b.uav))) return false;
        b.elements = elements;
        b.bytes = bytes;
        return true;
    }

    void upload(Buffer& b, const void* src)
    {
        ctx->UpdateSubresource(b.buf, 0, nullptr, src, 0, 0);
    }

    bool download(const Buffer& b, void* dst)
    {
        D3D11_BUFFER_DESC bd{};
        b.buf->GetDesc(&bd);
        ComPtr<ID3D11Buffer> staging;
        D3D11_BUFFER_DESC sd{};
        sd.ByteWidth = bd.ByteWidth;
        sd.Usage = D3D11_USAGE_STAGING;
        sd.CPUAccessFlags = D3D11_CPU_ACCESS_READ;
        if (FAILED(dev->CreateBuffer(&sd, nullptr, &staging))) return false;
        ctx->CopyResource(staging, b.buf);
        D3D11_MAPPED_SUBRESOURCE map{};
        if (FAILED(ctx->Map(staging, 0, D3D11_MAP_READ, 0, &map))) return false;
        memcpy(dst, map.pData, bd.ByteWidth);
        ctx->Unmap(staging, 0);
        return true;
    }

    // force GPU idle (D3D11 full sync: the equivalent of a pipeline barrier)
    void gpu_sync()
    {
        ctx->End(sync_q);
        ctx->Flush();
        while (ctx->GetData(sync_q, nullptr, 0, 0) == S_FALSE) std::this_thread::yield();
    }

    bool compile_shader(const char* file, bool fp16, ComPtr<ID3D11ComputeShader>& out)
    {
        std::ifstream f(std::string("shaders/") + file, std::ios::binary);
        if (!f) {
            fprintf(stderr, "[fdx] cannot read shaders/%s\n", file);
            return false;
        }
        std::string src((std::istreambuf_iterator<char>(f)), std::istreambuf_iterator<char>());
        ComPtr<ID3DBlob> blob, errblob;
        // NOTE: a D3D_SHADER_MACRO with a null Definition DEFINES the macro as
        // empty; to leave it undefined the macro must be absent from the list.
        const D3D_SHADER_MACRO defs_fp16[] = {{"FP16", "1"}, {nullptr, nullptr}};
        const D3D_SHADER_MACRO defs_fp32[] = {{nullptr, nullptr}};
        const D3D_SHADER_MACRO* defs = fp16 ? defs_fp16 : defs_fp32;
        const HRESULT hr = D3DCompile(src.data(), src.size(), file, defs, nullptr, "main",
                                      "cs_5_0", 0, 0, &blob, &errblob);
        if (FAILED(hr)) {
            if (errblob && errblob->GetBufferPointer()) {
                fprintf(stderr, "[fdx] %s: %s\n", file, (const char*)errblob->GetBufferPointer());
            } else {
                fprintf(stderr, "[fdx] %s: compile failed (0x%08lX)\n", file, (unsigned long)hr);
            }
            return false;
        }
        return SUCCEEDED(dev->CreateComputeShader(blob->GetBufferPointer(), blob->GetBufferSize(),
                                                  nullptr, &out));
    }

    bool ensure_weights(const Model& model, size_t li)
    {
        if (weight.size() < model.layers.size()) weight.resize(model.layers.size());
        if (weight[li].buf) return true;
        const Layer& L = model.layers[li];
        const size_t n = L.weights.size();
        Buffer b;
        if (L.type == LAYER_GEMM) {
            // the .fvp already stores the gemm weight transposed [k][out_c]
            // (see export_model.py) so the kernel's float4 loads are
            // contiguous; upload the blob verbatim, no CPU transpose needed
            const int out_c = L.params[0], k = L.params[1];
            if (!fp16) {
                if (!make_buffer((size_t)out_c * k * 4, (UINT)(out_c * k), b)) return false;
                upload(b, L.weights.data());
            } else {
                std::vector<uint32_t> packed(elements_to_words(out_c * k), 0u);
                for (size_t i = 0; i < (size_t)out_c * k; i++) {
                    const uint32_t v = float_to_half(*(const float*)&L.weights[i]);
                    uint32_t& w = packed[i >> 1];
                    if (i & 1)
                        w = (w & 0xFFFFu) | (v << 16);
                    else
                        w = (w & 0xFFFF0000u) | (v & 0xFFFFu);
                }
                if (!make_buffer(packed.size() * 4, (UINT)packed.size(), b)) return false;
                upload(b, packed.data());
            }
            weight[li] = std::move(b);
            return true;
        }
        if (!fp16) {
            if (!make_buffer(n * 4, (UINT)n, b)) return false;
            upload(b, L.weights.data());
        } else {
            std::vector<uint32_t> packed(elements_to_words((int)n), 0u);
            for (size_t i = 0; i < n; i++) {
                float f;
                memcpy(&f, &L.weights[i], 4);
                const uint32_t v = float_to_half(f);
                uint32_t& w = packed[i >> 1];
                if (i & 1)
                    w = (w & 0xFFFFu) | (v << 16);
                else
                    w = (w & 0xFFFF0000u) | (v & 0xFFFFu);
            }
            if (!make_buffer(packed.size() * 4, (UINT)packed.size(), b)) return false;
            upload(b, packed.data());
        }
        weight[li] = std::move(b);
        return true;
    }

    // separate per-layer aux buffer (bias [+ prelu slope]): no view offsets needed
    bool ensure_aux(const Model& model, size_t li)
    {
        if (aux.size() < model.layers.size()) aux.resize(model.layers.size());
        if (aux[li].buf) return true;
        const Layer& L = model.layers[li];
        const int n_aux = (L.type == LAYER_GEMM) ? L.params[0] : 2 * L.params[1];
        if (n_aux <= 0) return true;  // layer without epilogue
        Buffer b;
        if (!fp16) {
            if (!make_buffer((size_t)n_aux * 4, (UINT)n_aux, b)) return false;
            upload(b, L.weights.data() + L.aux_offset_elements);
        } else {
            // shaders read aux channel-pair: bias at words 0..out_c/2-1, prelu
            // slope at words out_c/2..out_c-1 (conv only). For even out_c the
            // flat pack of [bias, slope] already matches; pack sections anyway.
            const int n_bias = (L.type == LAYER_GEMM) ? n_aux : L.params[1];
            const size_t words = (size_t)((n_aux + 1) / 2);
            std::vector<uint32_t> packed(words, 0u);
            const auto put = [&](int idx, float f) {
                const uint32_t v = float_to_half(f);
                uint32_t& w = packed[idx >> 1];
                if (idx & 1)
                    w = (w & 0xFFFFu) | (v << 16);
                else
                    w = (w & 0xFFFF0000u) | (v & 0xFFFFu);
            };
            for (int i = 0; i < n_bias; i++) {
                float f;
                memcpy(&f, &L.weights[L.aux_offset_elements + i], 4);
                put(i, f);
            }
            if (L.type == LAYER_CONV) {
                for (int i = 0; i < n_bias; i++) {
                    float f;
                    memcpy(&f, &L.weights[L.aux_offset_elements + n_bias + i], 4);
                    put(n_bias + i, f);
                }
            }
            if (!make_buffer(packed.size() * 4, (UINT)packed.size(), b)) return false;
            upload(b, packed.data());
        }
        aux[li] = std::move(b);
        return true;
    }
};

void print_gpu_info()
{
    // DXGI adapter info for the default hardware adapter
    ComPtr<IDXGIFactory1> fac;
    if (FAILED(CreateDXGIFactory1(__uuidof(IDXGIFactory1), (void**)&fac))) return;
    IDXGIAdapter* base = nullptr;
    if (FAILED(fac->EnumAdapters(0, &base))) return;
    ComPtr<IDXGIAdapter1> ada;
    if (FAILED(base->QueryInterface(__uuidof(IDXGIAdapter1), (void**)&ada))) return;
    DXGI_ADAPTER_DESC1 d{};
    if (FAILED(ada->GetDesc1(&d))) return;
    char name[128];
    const int n = WideCharToMultiByte(CP_UTF8, 0, d.Description, -1, name, sizeof(name), nullptr, nullptr);
    if (n > 0) name[n - 1] = 0;
    fprintf(stderr, "[fdx] adapter: %s (VRAM %.2f GB, feature level via D3D11)\n", name,
            (double)d.DedicatedVideoMemory / (1024.0 * 1024.0 * 1024.0));
}

Engine::Engine() { p_ = new Impl; }

Engine::~Engine()
{
    if (!p_) return;
    delete p_;
    p_ = nullptr;
}

bool Engine::init(bool fp16, int gpu_index, std::string* err)
{
    (void)gpu_index;
    Impl& I = *p_;
    I.fp16 = fp16;

    const bool trace = getenv("FDX_TRACE") != nullptr;
    D3D_FEATURE_LEVEL levels[] = {D3D_FEATURE_LEVEL_11_0, D3D_FEATURE_LEVEL_10_1};
    D3D_FEATURE_LEVEL got = D3D_FEATURE_LEVEL_11_0;
    ComPtr<ID3D11Device> dev;
    ComPtr<ID3D11DeviceContext> ctx;
    if (trace) fprintf(stderr, "[trace] creating d3d11 device\n");
    HRESULT hr = D3D11CreateDevice(nullptr, D3D_DRIVER_TYPE_HARDWARE, nullptr,
                                   D3D11_CREATE_DEVICE_BGRA_SUPPORT, levels, 2,
                                   D3D11_SDK_VERSION, &dev, &got, &ctx);
    if (FAILED(hr)) {
        // WARP fallback (OS software rasterizer) — only used if no GPU at all
        hr = D3D11CreateDevice(nullptr, D3D_DRIVER_TYPE_WARP, nullptr, 0, levels, 2,
                               D3D11_SDK_VERSION, &dev, &got, &ctx);
        if (FAILED(hr)) {
            if (err) *err = "D3D11CreateDevice failed";
            return false;
        }
        fprintf(stderr, "[fdx] warning: hardware device unavailable, using WARP (CPU)\n");
    }
    if (trace) fprintf(stderr, "[trace] device created\n");
    I.dev = std::move(dev);
    I.ctx = std::move(ctx);

    D3D11_QUERY_DESC qd{};
    qd.Query = D3D11_QUERY_EVENT;
    if (trace) fprintf(stderr, "[trace] creating event query\n");
    if (FAILED(I.dev->CreateQuery(&qd, &I.sync_q))) {
        if (err) *err = "query creation failed";
        return false;
    }
    if (trace) {
        D3D11_QUERY_DESC td{};
        td.Query = D3D11_QUERY_TIMESTAMP;
        td.MiscFlags = 0;
        I.ts_begin.resize(64);
        I.ts_end.resize(64);
        for (size_t i = 0; i < 64; i++) {
            I.dev->CreateQuery(&td, &I.ts_begin[i]);
            I.dev->CreateQuery(&td, &I.ts_end[i]);
        }
        td.Query = D3D11_QUERY_TIMESTAMP_DISJOINT;
        I.dev->CreateQuery(&td, &I.ts_disjoint);
        I.ts_gpu_ms.assign(64, 0.0);
    }

    if (trace) fprintf(stderr, "[trace] creating constant buffer\n");
    D3D11_BUFFER_DESC cb{};
    cb.ByteWidth = 64;
    cb.Usage = D3D11_USAGE_DYNAMIC;  // Map(WRITE_DISCARD) never stalls the GPU
    cb.BindFlags = D3D11_BIND_CONSTANT_BUFFER;
    cb.CPUAccessFlags = D3D11_CPU_ACCESS_WRITE;
    if (FAILED(I.dev->CreateBuffer(&cb, nullptr, &I.cbuf))) {
        if (err) *err = "constant buffer failed";
        return false;
    }

    if (trace) fprintf(stderr, "[trace] compiling conv shader\n");
    if (!I.compile_shader("conv.cs.hlsl", fp16, I.cs_conv)) return false;
    if (!I.compile_shader("conv11.cs.hlsl", fp16, I.cs_conv11)) return false;
    if (!I.compile_shader("conv3.cs.hlsl", fp16, I.cs_conv3)) return false;
    if (!I.compile_shader("conv3rb.cs.hlsl", fp16, I.cs_conv3rb)) return false;
    if (trace) fprintf(stderr, "[trace] compiling gemm shader\n");
    if (!I.compile_shader("gemm_partial.cs.hlsl", fp16, I.cs_gemm_partial)) return false;
    if (!I.compile_shader("gemm_final.cs.hlsl", fp16, I.cs_gemm_final)) return false;
    if (trace) fprintf(stderr, "[trace] compiling add shader\n");
    if (!I.compile_shader("add.cs.hlsl", fp16, I.cs_add)) {
        if (err) *err = "shader compile failed";
        return false;
    }
    return true;
}

bool Engine::run(const Model& model, const float* input, float* out512, double* ms)
{
    Impl& I = *p_;
    const bool f16 = I.fp16;

    const bool trace = getenv("FDX_TRACE") != nullptr;
    // ---- ensure tensor buffers exist ----
    if (I.tensor.size() < model.tensor_counts.size()) I.tensor.resize(model.tensor_counts.size());
    for (size_t t = 0; t < model.tensor_counts.size(); t++) {
        if (trace && t % 10 == 0) fprintf(stderr, "[trace] tensor buf %zu\n", t);
        if (I.tensor[t].buf) continue;
        const int elems = model.tensor_counts[t];
        Buffer b;
        if (!f16) {
            if (!I.make_buffer((size_t)elems * 4, (UINT)elems, b)) return false;
        } else if (t == 0) {
            // input has 3 channels (odd): channel-pair packing needs
            // ceil(3/2) = 2 half-planes, not ceil(3*plane/2) words
            const UINT words = 2 * 112 * 112;
            if (!I.make_buffer((size_t)words * 4, words, b)) return false;
        } else {
            const UINT words = (UINT)I.elements_to_words(elems);
            if (!I.make_buffer((size_t)words * 4, words, b)) return false;
        }
        I.tensor[t] = std::move(b);
    }

    if (trace) fprintf(stderr, "[trace] uploading input\n");
    // ---- upload input (NCHW, normalized): channel-pair layout for fp16 ----
    {
        const int plane = 112 * 112;
        if (f16) {
            std::vector<uint32_t> packed(2 * plane, 0u);  // clears the pad half for ch 3
            for (int c = 0; c < 3; c++) {
                for (int p = 0; p < plane; p++) {
                    const uint32_t v = float_to_half(input[c * plane + p]);
                    uint32_t& w = packed[(c >> 1) * plane + p];
                    if (c & 1)
                        w = (w & 0xFFFFu) | (v << 16);
                    else
                        w = (w & 0xFFFF0000u) | (v & 0xFFFFu);
                }
            }
            I.upload(I.tensor[0], packed.data());
        } else {
            I.upload(I.tensor[0], input);
        }
    }

    // ---- run the graph ----
    const auto t0 = std::chrono::steady_clock::now();
    if (trace) {
        fprintf(stderr, "[trace] graph start\n");
        I.ctx->Begin(I.ts_disjoint);
    }
    for (size_t li = 0; li < model.layers.size(); li++) {
        if (trace && li < I.ts_begin.size()) I.ctx->End(I.ts_begin[li]);
        const Layer& L = model.layers[li];
        const int tin = (L.inputs[0] >= 0) ? L.inputs[0] : 0;
        const int tout = (int)li + 1;  // layer i writes tensor i+1

        int pc[15] = {0};
        ID3D11ComputeShader* cs = nullptr;
        bool gemm_final = false;
        UINT gx = 1, gy = 1, gz = 1;
        const int* ish = &model.tensor_shapes[tin * 4];

        if (L.type == LAYER_CONV) {
            pc[0] = L.params[0]; pc[1] = L.params[1];
            pc[2] = ish[2]; pc[3] = ish[3];
            pc[4] = L.out_shape[2]; pc[5] = L.out_shape[3];
            pc[6] = L.params[2]; pc[7] = L.params[3];
            pc[8] = L.params[4]; pc[9] = L.params[5];
            pc[10] = L.params[6]; pc[11] = L.params[7];
            pc[12] = L.params[9]; pc[13] = L.params[10];
            pc[14] = L.params[8];
            const bool is_11 = L.params[2] == 1 && L.params[3] == 1 && L.params[8] == 1;
            if (is_11) {
                // tiled 1x1 kernel: group = 64px x 64 out-channels. (A
                // 16px-tile small-plane variant was tried for the 7x7/14x14
                // tail and benchmarked SLOWER than this 64x64-tile kernel on
                // this iGPU, so 1x1 convs always route here.)
                pc[0] = L.params[0]; pc[1] = L.params[1];
                pc[2] = ish[2] * ish[3];  // plane
                pc[12] = L.params[9]; pc[13] = L.params[10];
                cs = I.cs_conv11;
                gx = ((UINT)pc[2] + 63) / 64;
                gy = ((UINT)L.params[1] + 63) / 64;
            } else if (L.params[2] == 3 && L.params[3] == 3 &&
                       (L.params[4] == 1 || L.params[4] == 2)) {
                // shared-tile 3x3 kernels: register-blocked variant for the
                // stride-1 layers (2 px x 2 ch per thread), general 16x16
                // tile for stride 2; one oc-pair per group
                cs = (L.params[4] == 1) ? I.cs_conv3rb : I.cs_conv3;
                const int rows = (L.params[4] == 1) ? 32 : 16;
                const int cols = (L.params[4] == 1) ? 8 : 16;
                gx = ((UINT)L.out_shape[3] + cols - 1) / cols;
                gy = ((UINT)L.out_shape[2] + rows - 1) / rows;
                gz = ((UINT)L.params[1] + 1) / 2;
            } else {
                cs = I.cs_conv;
                gx = ((UINT)L.out_shape[3] + 7) / 8;
                gy = ((UINT)L.out_shape[2] + 7) / 8;
            }
            if (!I.ensure_weights(model, li)) return false;
            if (!I.ensure_aux(model, li)) return false;
        } else if (L.type == LAYER_GEMM) {
            if (!I.ensure_weights(model, li)) return false;
            if (!I.ensure_aux(model, li)) return false;
            // scratch buffer for k-split partial sums [16][512]
            if (!I.scratch.buf) {
                const UINT n = f16 ? 4096 : 8192;
                if (!I.make_buffer((size_t)n * 4, n, I.scratch)) return false;
            }
            // ---- partial pass: 16 groups x 128 threads (float4 x 4 oc) ----
            {
                int pcp[15] = {0};
                pcp[0] = L.params[0]; pcp[1] = L.params[1];
                pcp[2] = ish[2] * ish[3];  // input plane
                ID3D11UnorderedAccessView* puav[1] = {I.scratch.uav};
                I.ctx->CSSetUnorderedAccessViews(0, 1, puav, nullptr);
                ID3D11ShaderResourceView* psrvs[3] = {I.tensor[tin].srv, I.weight[li].srv,
                                                      nullptr};
                D3D11_MAPPED_SUBRESOURCE pm{};
                if (FAILED(I.ctx->Map(I.cbuf, 0, D3D11_MAP_WRITE_DISCARD, 0, &pm)))
                    return false;
                memcpy(pm.pData, pcp, sizeof(pcp));
                I.ctx->Unmap(I.cbuf, 0);
                I.ctx->CSSetShader(I.cs_gemm_partial, nullptr, 0);
                I.ctx->CSSetConstantBuffers(0, 1, &I.cbuf.p);
                I.ctx->CSSetShaderResources(0, 3, psrvs);
                I.ctx->Dispatch(16, 1, 1);
            }
            // ---- final reduction pass (through the shared dispatch path) ----
            pc[0] = L.params[0]; pc[1] = 16;  // out_c, nsplit
            cs = I.cs_gemm_final;
            gx = 1;
            gy = 1;
            gemm_final = true;
        } else {  // ADD
            const int count = model.tensor_counts[tin];
            const int n = f16 ? (int)I.elements_to_words(count) : count;
            pc[0] = n;
            cs = I.cs_add;
            gx = ((UINT)n + 255) / 256;
            gy = 1;
        }

        // Bind the UAV FIRST: the output tensor is never bound as an SRV by
        // the current layer, and replacing the previous layer's UAV binding
        // clears any alias before the new SRVs are set. This avoids unbind
        // calls entirely (each unbind forces a driver state sync on Intel).
        ID3D11UnorderedAccessView* uavs[1] = {I.tensor[tout].uav};
        I.ctx->CSSetUnorderedAccessViews(0, 1, uavs, nullptr);

        // bind inputs: conv/gemm use (in, weights, aux); add uses (a, b, -);
        // the gemm final pass reads (scratch, aux)
        ID3D11ShaderResourceView* srvs[3] = {nullptr, nullptr, nullptr};
        if (L.type == LAYER_ADD) {
            const int tin1 = (L.inputs[1] >= 0) ? L.inputs[1] : 0;
            srvs[0] = I.tensor[tin].srv;
            srvs[1] = I.tensor[tin1].srv;
        } else if (gemm_final) {
            srvs[0] = I.scratch.srv;
            srvs[1] = I.aux[li].srv;
        } else {
            srvs[0] = I.tensor[tin].srv;
            srvs[1] = I.weight[li].srv;
            srvs[2] = I.aux[li].srv;
        }
        D3D11_MAPPED_SUBRESOURCE cmap{};
        if (FAILED(I.ctx->Map(I.cbuf, 0, D3D11_MAP_WRITE_DISCARD, 0, &cmap))) return false;
        memcpy(cmap.pData, pc, sizeof(pc));
        I.ctx->Unmap(I.cbuf, 0);
        I.ctx->CSSetShader(cs, nullptr, 0);
        I.ctx->CSSetConstantBuffers(0, 1, &I.cbuf.p);
        I.ctx->CSSetShaderResources(0, 3, srvs);

        I.ctx->Dispatch(gx, gy, gz);
        if (trace && li < I.ts_end.size()) I.ctx->End(I.ts_end[li]);
    }
    if (trace) I.ctx->End(I.ts_disjoint);
    // single full sync at the end: dispatches on the immediate context execute
    // in order, so intermediate layers are already ordered by the runtime; the
    // final readback below must wait for all of them.
    if (trace) fprintf(stderr, "[trace] graph done, syncing\n");
    I.gpu_sync();
    if (trace) {
        // read per-layer GPU times from timestamp queries
        fprintf(stderr, "[trace] synced\n");
        D3D11_QUERY_DATA_TIMESTAMP_DISJOINT dj{};
        if (I.ctx->GetData(I.ts_disjoint, &dj, sizeof(dj), 0) == S_OK && dj.Frequency)
            I.ts_freq = 1000.0 / (double)dj.Frequency;
        for (size_t i = 0; i < model.layers.size(); i++) {
            UINT64 b = 0, e = 0;
            if (I.ts_begin[i] && I.ts_end[i] &&
                I.ctx->GetData(I.ts_begin[i], &b, sizeof(b), 0) == S_OK &&
                I.ctx->GetData(I.ts_end[i], &e, sizeof(e), 0) == S_OK && e > b) {
                I.ts_gpu_ms[i] = (double)(e - b) * I.ts_freq;
                fprintf(stderr, "[trace] layer %zu GPU: %.2f ms\n", i, I.ts_gpu_ms[i]);
            }
        }
    }
    const auto t1 = std::chrono::steady_clock::now();
    if (ms) *ms = std::chrono::duration<double, std::milli>(t1 - t0).count();
    if (trace) {
        fprintf(stderr, "[trace] dispatch+sync took %.1f ms\n", *ms);
    }

    if (trace) fprintf(stderr, "[trace] all layers done\n");

    // ---- optional intermediate dump for debugging ----
    if (const char* dumpdir = getenv("FDX_DUMP_DIR")) {
        char path[512];
        for (size_t t = 0; t < model.tensor_counts.size(); t++) {
            snprintf(path, sizeof(path), "%s/t%03zu_f16%d.f32", dumpdir, t, f16 ? 1 : 0);
            std::vector<float> tmp(model.tensor_counts[t]);
            if (f16) {
                // buffer may be larger than the element count (3-channel input
                // pad half-plane); download the full buffer. Tensors are stored
                // in channel-pair layout: word[(c>>1)*plane + p].
                std::vector<uint32_t> packed(I.tensor[t].elements, 0);
                I.download(I.tensor[t], packed.data());
                const int* sh = &model.tensor_shapes[t * 4];
                const int C = sh[1], plane = sh[2] * sh[3];
                for (int c = 0; c < C; c++) {
                    for (int p = 0; p < plane; p++) {
                        const uint32_t u = packed[(c >> 1) * plane + p];
                        const uint16_t h = (c & 1) ? (uint16_t)(u >> 16) : (uint16_t)(u & 0xFFFFu);
                        tmp[c * plane + p] = half_to_float(h);
                    }
                }
            } else {
                I.download(I.tensor[t], tmp.data());
            }
            FILE* f = fopen(path, "wb");
            if (f) {
                fwrite(tmp.data(), 4, tmp.size(), f);
                fclose(f);
            }
        }
    }

    // ---- read output (tensor id = n_layers) ----
    const size_t last = model.layers.size();
    if (f16) {
        std::vector<uint32_t> packed(256);
        I.download(I.tensor[last], packed.data());
        for (int i = 0; i < 512; i++) {
            const uint32_t u = packed[i >> 1];
            const uint16_t h = (i & 1) ? (uint16_t)(u >> 16) : (uint16_t)(u & 0xFFFFu);
            out512[i] = half_to_float(h);
        }
    } else {
        I.download(I.tensor[last], out512);
    }

    // L2 normalize (matches insightface normed_embedding)
    double norm = 0.0;
    for (int i = 0; i < 512; i++) norm += (double)out512[i] * out512[i];
    norm = std::sqrt(norm);
    if (norm > 0.0) {
        for (int i = 0; i < 512; i++) out512[i] = (float)(out512[i] / norm);
    }
    return true;
}

}  // namespace fdx