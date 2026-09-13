// main.cpp — CLI for my own Direct3D 11 compute MobileFaceNet extractor.
//
// Usage: face_dx [--model FILE] [--fp16|--fp32] [--bench N] [--gpu-index N]
//                [--list FILE] [--out FILE] <images...>
//   --list FILE : newline-separated image paths (one engine process handles
//                 the whole batch — no per-image process startup cost)
//   --out FILE  : stream one JSON line per image to FILE (flushed per line)
//                 instead of stdout
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
    std::string list_path;
    std::string out_path;
    bool fp16 = false;
    int bench = 1;
    int gpu_index = -1;
    std::vector<std::string> images;
};

static void usage()
{
    fprintf(stderr,
            "usage: face_dx [--model FILE] [--fp16|--fp32] [--bench N] [--gpu-index N]\n"
            "               [--list FILE] [--out FILE] <image ...>\n");
}

static bool parse_args(int argc, char** argv, Opt& o)
{
    bool have_images = false;
    for (int i = 1; i < argc; i++) {
        std::string a = argv[i];
        if (a == "--model" && i + 1 < argc) {
            o.model = argv[++i];
        } else if (a == "--list" && i + 1 < argc) {
            o.list_path = argv[++i];
        } else if (a == "--out" && i + 1 < argc) {
            o.out_path = argv[++i];
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
            usage();
            return false;
        } else {
            o.images.push_back(a);
            have_images = true;
        }
    }
    return have_images || !o.list_path.empty();
}

// read newline-separated paths into the image list (trims \r / whitespace,
// skips empty lines — safe for CRLF files and trailing newlines)
static bool load_list(const std::string& path, std::vector<std::string>& out)
{
    FILE* f = fopen(path.c_str(), "rb");
    if (!f) {
        fprintf(stderr, "[fdx] cannot read list %s\n", path.c_str());
        return false;
    }
    char buf[4096];
    while (fgets(buf, sizeof(buf), f)) {
        std::string line = buf;
        while (!line.empty() && (line.back() == '\n' || line.back() == '\r' ||
                                 line.back() == ' ' || line.back() == '\t'))
            line.pop_back();
        if (!line.empty()) out.push_back(line);
    }
    fclose(f);
    return true;
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

struct BatchStats {
    int ok = 0;
    int failed = 0;
    double gpu_ms_total = 0.0;
};

static void process_image(fdx::Engine& engine, const fdx::Model& model, const Opt& o,
                          const std::string& img, FILE* outf, BatchStats& st)
{
    if (getenv("FDX_TRACE")) fprintf(stderr, "[trace] loading %s\n", img.c_str());
    std::vector<float> input;
    if (!load_input(img, input)) {
        st.failed++;
        return;
    }
    if (getenv("FDX_TRACE")) fprintf(stderr, "[trace] decoded, running inference\n");

    std::vector<float> emb(512);
    double ms = 0.0;
    if (!engine.run(model, input.data(), emb.data(), &ms)) {
        fprintf(stderr, "[fdx] inference failed for %s\n", img.c_str());
        st.failed++;
        return;
    }
    st.ok++;
    st.gpu_ms_total += ms;

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

    // JSON id uses forward slashes so downstream parsers never see an
    // unescaped backslash sequence
    std::string id = img;
    for (char& c : id)
        if (c == '\\') c = '/';

    fprintf(outf, "{\"id\":\"%s\",\"backend\":\"%s\",\"fp16\":%s,\"ms\":%.2f,\"embedding\":[",
            id.c_str(), o.fp16 ? "gpu-dx16" : "gpu-dx", o.fp16 ? "true" : "false", ms);
    for (int i = 0; i < 512; i++) {
        if (i) fprintf(outf, ",");
        fprintf(outf, "%.8g", emb[i]);
    }
    fprintf(outf, "]}\n");
    fflush(outf);  // stream: readers may consume results while we run
}

int main(int argc, char** argv)
{
    Opt o;
    if (!parse_args(argc, argv, o)) {
        usage();
        return 2;
    }

    if (!o.list_path.empty() && !load_list(o.list_path, o.images)) return 2;
    if (o.images.empty()) {
        usage();
        return 2;
    }

    fdx::Model model;
    std::string err;
    if (!fdx::load_model(o.model, model, &err)) {
        fprintf(stderr, "[fdx] %s\n", err.c_str());
        return 1;
    }
    fprintf(stderr, "[fdx] model %s: %zu layers, %s, %zu images\n", o.model.c_str(),
            model.layers.size(), o.fp16 ? "fp16" : "fp32", o.images.size());

    fdx::print_gpu_info();

    fdx::Engine engine;
    if (!engine.init(o.fp16, o.gpu_index, &err)) {
        fprintf(stderr, "[fdx] init failed: %s\n", err.c_str());
        return 1;
    }

    FILE* outf = stdout;
    if (!o.out_path.empty()) {
        outf = fopen(o.out_path.c_str(), "wb");
        if (!outf) {
            fprintf(stderr, "[fdx] cannot open output %s\n", o.out_path.c_str());
            return 1;
        }
    }

    BatchStats st;
    for (const std::string& img : o.images)
        process_image(engine, model, o, img, outf, st);

    fprintf(stderr, "[summary] ok=%d failed=%d gpu_ms_total=%.1f gpu_ms_avg=%.2f\n",
            st.ok, st.failed, st.gpu_ms_total,
            st.ok ? st.gpu_ms_total / st.ok : 0.0);

    if (outf != stdout) fclose(outf);
    return st.ok > 0 ? 0 : 1;
}
