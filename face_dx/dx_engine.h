// dx_engine.h — my own Direct3D 11 compute inference engine for MobileFaceNet.
// Hand-written HLSL kernels (conv / gemm / add) compiled to bytecode at
// runtime via D3DCompile (d3dcompiler_47.dll, ships with Windows); the model
// is my own .fvp format produced by export_model.py. No third-party libraries
// and no Vulkan: the only external code that runs is the Windows OS itself
// (d3d11.dll + the Intel GPU driver).
#pragma once

#include <cstdint>
#include <string>
#include <vector>

namespace fdx {

constexpr int LAYER_CONV = 1;
constexpr int LAYER_ADD = 2;
constexpr int LAYER_GEMM = 3;

struct Layer {
    int type = 0;
    int params[12] = {0};       // conv: in_c,out_c,kh,kw,sh,sw,ph,pw,group,has_bias,prelu,-
    std::vector<uint32_t> weights;  // raw u32 words (fp32 bits or packed fp16)
    int n_inputs = 0;
    int inputs[2] = {-1, -1};   // producer tensor ids (-1 = model input)
    int out_shape[4] = {0, 0, 0, 0};  // NCHW
    int out_count = 0;          // C*H*W elements
    int aux_offset_elements = 0;  // bias/prelu offset within weights (in elements)
};

struct Model {
    std::vector<Layer> layers;
    int in_count = 3 * 112 * 112;
    std::vector<int> tensor_counts;   // element counts, index 0 = model input
    std::vector<int> tensor_shapes;   // NCHW (4 ints) per tensor, index 0 = input
};

// parse the .fvp file, resolve tensor graph + shapes
bool load_model(const std::string& path, Model& model, std::string* err);

void print_gpu_info();

class Engine {
public:
    Engine();
    ~Engine();

    // init(false) = fp32; init(true) = packed-fp16. gpu_index -1 = auto.
    bool init(bool fp16, int gpu_index, std::string* err);

    // input: 3*112*112 float NCHW, already normalized (x-127.5)/127.5
    // out: 512 floats (L2-normalized) ; ms: wall time incl. submit+wait
    bool run(const Model& model, const float* input, float* out512, double* ms);

private:
    struct Impl;
    Impl* p_ = nullptr;
};

}  // namespace fdx