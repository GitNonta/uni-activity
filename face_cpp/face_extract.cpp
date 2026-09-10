// face_extract.cpp
// ============================================================================
// MobileFaceNet (ArcFace) 512-d embedding extractor — ncnn + Vulkan GPU
// ----------------------------------------------------------------------------
// Replicates the InsightFace buffalo_s pipeline (w600k_mbf) exactly:
//   input : aligned 112x112 RGB face crop (as produced by norm_crop)
//   preproc: (pixel - 127.5) / 127.5          (== cv2.dnn.blobFromImage
//              1/127.5, mean 127.5, swapRB=True)
//   model : MobileFaceNet -> 512-d logits (blob "516")
//   post  : L2-normalize (== insightface normed_embedding)
//
// Usage:
//   face_extract [--model DIR] [--gpu|--cpu] [--fp16] [--bench N] <images...>
//
//   --gpu     run on Vulkan GPU (default if available)
//   --cpu     force CPU
//   --fp16    enable fp16 packed/storage/arithmetic (GPU speedup, default off)
//   --bench N run each image N times, report latency stats
// ============================================================================

#include <cmath>
#include <cstdio>
#include <cstring>
#include <string>
#include <vector>

#include <opencv2/opencv.hpp>

#include "benchmark.h"
#include "net.h"

struct Options {
    std::string model_dir = ".";
    bool use_gpu = false;
    bool use_fp16 = false;
    int bench = 1;
    std::vector<std::string> images;
    std::string out_blob = "out0";
};

static void print_usage()
{
    fprintf(stderr,
            "usage: face_extract [--model DIR] [--gpu|--cpu] [--fp16] [--bench N] <image ...>\n");
}

static bool parse_args(int argc, char** argv, Options& opt)
{
    for (int i = 1; i < argc; ++i) {
        std::string a = argv[i];
        if (a == "--model" && i + 1 < argc) {
            opt.model_dir = argv[++i];
        } else if (a == "--gpu") {
            opt.use_gpu = true;
        } else if (a == "--cpu") {
            opt.use_gpu = false;
        } else if (a == "--fp16") {
            opt.use_fp16 = true;
        } else if (a == "--bench" && i + 1 < argc) {
            opt.bench = atoi(argv[++i]);
            if (opt.bench < 1) opt.bench = 1;
        } else if (a == "--out" && i + 1 < argc) {
            opt.out_blob = argv[++i];
        } else if (a == "--help" || a == "-h") {
            print_usage();
            return false;
        } else {
            opt.images.push_back(a);
        }
    }
    return !opt.images.empty();
}

static void print_gpu_info()
{
    int gpu_count = ncnn::get_gpu_count();
    fprintf(stderr, "[gpu] vulkan devices: %d\n", gpu_count);
    for (int i = 0; i < gpu_count; ++i) {
        const ncnn::GpuInfo& gi = ncnn::get_gpu_info(i);
        const uint32_t ver = gi.api_version();
        unsigned vram_mb = 0;
        const VkPhysicalDeviceMemoryProperties& mp = gi.physical_device_memory_properties();
        for (uint32_t h = 0; h < mp.memoryHeapCount; ++h) {
            if (mp.memoryHeaps[h].flags & VK_MEMORY_HEAP_DEVICE_LOCAL_BIT) {
                vram_mb += (unsigned)(mp.memoryHeaps[h].size / (1024 * 1024));
            }
        }
        fprintf(stderr, "[gpu]   #%d %s (vulkan %d.%d.%d, %uMB vram)\n", i, gi.device_name(),
                VK_VERSION_MAJOR(ver), VK_VERSION_MINOR(ver), VK_VERSION_PATCH(ver), vram_mb);
    }
}

static int extract_embedding(const ncnn::Net& net, const Options& opt,
                             const std::string& path, std::vector<float>& emb512,
                             double& ms)
{
    cv::Mat bgr = cv::imread(path, cv::IMREAD_COLOR);
    if (bgr.empty()) {
        fprintf(stderr, "[err] cannot read %s\n", path.c_str());
        return -1;
    }
    cv::Mat rgb;
    cv::cvtColor(bgr, rgb, cv::COLOR_BGR2RGB);

    ncnn::Mat in = ncnn::Mat::from_pixels_resize(rgb.data, ncnn::Mat::PIXEL_RGB,
                                                 rgb.cols, rgb.rows, 112, 112);
    const float mean_vals[3] = {127.5f, 127.5f, 127.5f};
    const float norm_vals[3] = {1.0f / 127.5f, 1.0f / 127.5f, 1.0f / 127.5f};
    in.substract_mean_normalize(mean_vals, norm_vals);

    ncnn::Extractor ex = net.create_extractor();
    ex.input("in0", in);

    ncnn::Mat out;
    double t0 = ncnn::get_current_time();
    int ret = ex.extract(opt.out_blob.c_str(), out);
    ms = ncnn::get_current_time() - t0;
    if (ret != 0) {
        fprintf(stderr, "[err] extract '%s' failed (%d) for %s\n",
                opt.out_blob.c_str(), ret, path.c_str());
        return -1;
    }

    const int dims = out.total();
    if (dims != 512) {
        fprintf(stderr, "[err] unexpected output dims %d (want 512) for %s\n", dims, path.c_str());
        return -1;
    }

    emb512.resize(dims);
    for (int i = 0; i < dims; ++i) {
        emb512[i] = out[i];
    }

    // L2 normalize — matches insightface normed_embedding
    double norm = 0.0;
    for (float v : emb512) norm += (double)v * v;
    norm = std::sqrt(norm);
    if (norm > 0.0) {
        for (float& v : emb512) v = (float)(v / norm);
    }
    return 0;
}

static void print_embedding(const std::string& id, bool gpu, bool fp16,
                            double ms, const std::vector<float>& emb)
{
    printf("{\"id\":\"%s\",\"backend\":\"%s\",\"fp16\":%s,\"ms\":%.2f,\"embedding\":[",
           id.c_str(), gpu ? "gpu" : "cpu", fp16 ? "true" : "false", ms);
    for (size_t i = 0; i < emb.size(); ++i) {
        if (i) printf(",");
        printf("%.8g", emb[i]);
    }
    printf("]}\n");
}

int main(int argc, char** argv)
{
    Options opt;
    if (!parse_args(argc, argv, opt)) {
        print_usage();
        return 2;
    }

    int gpu_count = ncnn::get_gpu_count();
    if (opt.use_gpu && gpu_count == 0) {
        fprintf(stderr, "[warn] --gpu requested but no Vulkan device; falling back to CPU\n");
        opt.use_gpu = false;
    }

    print_gpu_info();

    ncnn::Net net;
    net.opt.use_vulkan_compute = opt.use_gpu;
    net.opt.num_threads = 4;
    if (opt.use_fp16) {
        net.opt.use_fp16_packed = true;
        net.opt.use_fp16_storage = true;
        net.opt.use_fp16_arithmetic = true;
    } else {
        net.opt.use_fp16_packed = false;
        net.opt.use_fp16_storage = false;
        net.opt.use_fp16_arithmetic = false;
    }

    std::string param = opt.model_dir + "/w600k_mbf.param";
    std::string bin = opt.model_dir + "/w600k_mbf.bin";
    if (net.load_param(param.c_str()) != 0 || net.load_model(bin.c_str()) != 0) {
        fprintf(stderr, "[err] failed to load model from %s / %s\n", param.c_str(), bin.c_str());
        return 1;
    }
    fprintf(stderr, "[info] model loaded (%s), backend=%s fp16=%s\n",
            opt.model_dir.c_str(), opt.use_gpu ? "vulkan" : "cpu",
            opt.use_fp16 ? "on" : "off");

    for (const std::string& img : opt.images) {
        // first run (warmup + correctness)
        std::vector<float> emb;
        double ms = 0.0;
        if (extract_embedding(net, opt, img, emb, ms) != 0) continue;

        if (opt.bench > 1) {
            std::vector<double> times;
            times.reserve(opt.bench);
            for (int i = 0; i < opt.bench; ++i) {
                std::vector<float> e;
                double t = 0.0;
                if (extract_embedding(net, opt, img, e, t) == 0) times.push_back(t);
            }
            if (!times.empty()) {
                double sum = 0.0, mn = times[0], mx = times[0];
                for (double t : times) { sum += t; mn = std::min(mn, t); mx = std::max(mx, t); }
                double avg = sum / times.size();
                fprintf(stderr,
                        "[bench] %s backend=%s fp16=%s n=%zu  avg=%.2fms  min=%.2fms  max=%.2fms\n",
                        img.c_str(), opt.use_gpu ? "gpu" : "cpu", opt.use_fp16 ? "on" : "off",
                        times.size(), avg, mn, mx);
            }
        }
        print_embedding(img, opt.use_gpu, opt.use_fp16, ms, emb);
    }

    return 0;
}