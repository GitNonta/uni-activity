// main.cpp — CLI for my own Direct3D 11 compute MobileFaceNet extractor.
//
// Usage: face_dx [--model FILE] [--fp16|--fp32] [--bench N] [--gpu-index N] <images...>
// Output: one JSON line per image, same schema as face_cpp/face_extract so
//         compare_embeddings.py can validate it.
//
// Entire pipeline is my own code: PNG decode (png_decode.h), model parsing
// (.fvp), graph execution on the Intel GPU through the Windows OS only
// (d3d11.dll + Intel driver) with hand-written HLSL kernels.
#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <string>
#include <vector>

#include "png_decode.h"
#include "dx_engine.h"

struct Opt {
    std::string model = "models/w600k_mbf.fvp";
    bool fp16 = false;
    int bench = 1;
    int gpu_index = -1;
    std::vector<std::string> images;
};

static bool parse_args(int argc, char** argv, Opt& o)
{
    for (int i = 1; i < argc; i++) {
        std::string a = argv[i];
        if (a == "--model" && i + 1 < argc) {
            o.model = argv[++i];
        } else if (a == "--fp16") {
            o.fp16 = true;
        } else if (a == "--fp32") {
            o.fp16 = false;
        } else if (a == "--bench" && i + 1 < argc) {
            o.bench = atoi(argv[++i]);
            if (o.bench < 1) o.bench = 1;
        } else if (a == "--gpu-index" && i + 1 < argc) {
            o.gpu_index = atoi(argv[++i]);
        } else if (a == "--help" || a == "-h") {
            fprintf(stderr,
                    "usage: face_dx [--model FILE] [--fp16|--fp32] [--bench N] [--gpu-index N] <image ...>\n");
            return false;
        } else {
            o.images.push_back(a);
        }
    }
    return !o.images.empty();
}

// load image -> normalized NCHW float[3*112*112], returns false on failure
static bool load_input(const std::string& path, std::vector<float>& out)
{
    std::vector<uint8_t> bytes;
    FILE* f = fopen(path.c_str(), "rb");
    if (!f) {
        fprintf(stderr, "[fdx] cannot read %s\n", path.c_str());
        return false;
    }
    fseek(f, 0, SEEK_END);
    const long sz = ftell(f);
    fseek(f, 0, SEEK_SET);
    if (sz <= 0) {
        fclose(f);
        return false;
    }
    bytes.resize((size_t)sz);
    const size_t got = fread(bytes.data(), 1, (size_t)sz, f);
    fclose(f);
    if (got != bytes.size()) return false;

    fdxpng::Image img;
    if (!fdxpng::decode(bytes.data(), bytes.size(), img)) {
        fprintf(stderr, "[fdx] cannot decode %s (expected an 8-bit PNG)\n", path.c_str());
        return false;
    }
    if (img.w != 112 || img.h != 112) {
        fprintf(stderr, "[fdx] %s is %dx%d, expected an aligned 112x112 crop\n",
                path.c_str(), img.w, img.h);
        return false;
    }

    constexpr int plane = 112 * 112;
    out.resize(3 * plane);
    constexpr float scale = 1.0f / 127.5f;
    for (int c = 0; c < 3; c++) {
        for (int p = 0; p < plane; p++) {
            const float v = (float)img.px[p * img.ch + c];  // RGB order
            out[c * plane + p] = (v - 127.5f) * scale;
        }
    }
    return true;
}

int main(int argc, char** argv)
{
    Opt o;
    if (!parse_args(argc, argv, o)) return 2;

    fdx::Model model;
    std::string err;
    if (!fdx::load_model(o.model, model, &err)) {
        fprintf(stderr, "[fdx] %s\n", err.c_str());
        return 1;
    }
    fprintf(stderr, "[fdx] model %s: %zu layers, %s\n", o.model.c_str(), model.layers.size(),
            o.fp16 ? "fp16" : "fp32");

    fdx::print_gpu_info();

    fdx::Engine engine;
    if (!engine.init(o.fp16, o.gpu_index, &err)) {
        fprintf(stderr, "[fdx] init failed: %s\n", err.c_str());
        return 1;
    }

    for (const std::string& img : o.images) {
        if (getenv("FDX_TRACE")) fprintf(stderr, "[trace] loading %s\n", img.c_str());
        std::vector<float> input;
        if (!load_input(img, input)) continue;
        if (getenv("FDX_TRACE")) fprintf(stderr, "[trace] decoded, running inference\n");

        std::vector<float> emb(512);
        double ms = 0.0;
        if (!engine.run(model, input.data(), emb.data(), &ms)) {
            fprintf(stderr, "[fdx] inference failed for %s\n", img.c_str());
            continue;
        }

        if (o.bench > 1) {
            std::vector<double> times;
            times.reserve(o.bench);
            for (int i = 0; i < o.bench; i++) {
                double t = 0.0;
                if (engine.run(model, input.data(), emb.data(), &t)) times.push_back(t);
            }
            if (!times.empty()) {
                double sum = 0.0, mn = times[0], mx = times[0];
                for (double t : times) {
                    sum += t;
                    mn = mn < t ? mn : t;
                    mx = mx > t ? mx : t;
                }
                fprintf(stderr,
                        "[bench] %s backend=%s n=%zu avg=%.2fms min=%.2fms max=%.2fms\n",
                        img.c_str(), o.fp16 ? "gpu-dx16" : "gpu-dx", times.size(),
                        sum / times.size(), mn, mx);
            }
        }

        printf("{\"id\":\"%s\",\"backend\":\"%s\",\"fp16\":%s,\"ms\":%.2f,\"embedding\":[",
               img.c_str(), o.fp16 ? "gpu-dx16" : "gpu-dx", o.fp16 ? "true" : "false", ms);
        for (int i = 0; i < 512; i++) {
            if (i) printf(",");
            printf("%.8g", emb[i]);
        }
        printf("]}\n");
    }
    return 0;
}