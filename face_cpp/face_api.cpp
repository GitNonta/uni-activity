// face_api.cpp
// ============================================================================
// Face extraction API — SCRFD detector + ArcFace 5-point alignment +
// MobileFaceNet (w600k_mbf) 512-d embedding, served over plain HTTP.
//
// The whole pipeline replicates insightface's buffalo_s FaceAnalysis:
//   detect  : SCRFD det_10g (ncnn), letterbox to det_size with 1/128 std,
//             mean 127.5, strides 8/16/32, 2 anchors per location,
//             distance2bbox/distance2kps, NMS 0.4
//   align   : norm_crop(mode='arcface') == cv2.warpAffine with a least-squares
//             similarity transform onto the ArcFace 112x112 template
//   embed   : (crop - 127.5) / 127.5 -> w600k_mbf -> 512-d, L2-normalized
//
// Runtime deps: ncnn only (image decoding via stb_image; no OpenCV needed).
//
// Endpoints
//   GET  /health                  -> readiness + latency stats
//   POST /extract  (image)        -> detect + embed every face (<= max_faces)
//   POST /detect   (image)        -> boxes + 5-point landmarks only
//   POST /embed    (aligned crop) -> embed a 112x112 (or any) crop, no detection
//
// `image` is either a multipart/form-data part named "image" (how Laravel's
// Http::attach sends it) or the raw request body with an image/* content type.
//
// Usage
//   face_api --serve --model-dir DIR [--port 8010] [--threads 4] [--workers 2]
//            [--det-size 640] [--det-thresh 0.5] [--nms 0.4] [--api-key KEY]
//   face_api --model-dir DIR <image ...>      # CLI: JSON per image (parity runs)
//
//   --workers N   request-handling threads, each with its own ncnn Net
//   --threads N   ncnn intra-op threads per Net (OpenMP)
// ============================================================================

#include <algorithm>
#include <atomic>
#include <cerrno>
#include <cmath>
#include <cstdarg>
#include <cstdint>
#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <ctime>
#include <map>
#include <memory>
#include <string>
#include <thread>
#include <vector>

#include <arpa/inet.h>
#include <netinet/in.h>
#include <signal.h>
#include <sys/socket.h>
#include <sys/time.h>
#include <unistd.h>

#include "net.h"

#define STB_IMAGE_IMPLEMENTATION
#include "stb_image.h"

// ─────────────────────────────────────────────────────────────────────────────
// Image decode (+ EXIF orientation, matching cv2.imread's default behaviour)
// ─────────────────────────────────────────────────────────────────────────────

struct Image {
    int w = 0;
    int h = 0;
    std::vector<unsigned char> rgb;
};

static int rd16(const unsigned char* t, size_t o, bool le)
{
    return le ? (t[o] | (t[o + 1] << 8)) : ((t[o] << 8) | t[o + 1]);
}

static uint32_t rd32(const unsigned char* t, size_t o, bool le)
{
    if (le) {
        return (uint32_t)t[o] | ((uint32_t)t[o + 1] << 8) | ((uint32_t)t[o + 2] << 16) |
               ((uint32_t)t[o + 3] << 24);
    }
    return ((uint32_t)t[o] << 24) | ((uint32_t)t[o + 1] << 16) | ((uint32_t)t[o + 2] << 8) |
           (uint32_t)t[o + 3];
}

static int tiff_orientation(const unsigned char* t, size_t n)
{
    if (n < 8) return 1;
    const bool le = (t[0] == 'I' && t[1] == 'I');
    if (rd16(t, 2, le) != 42) return 1;
    const uint32_t ifd = rd32(t, 4, le);
    if ((size_t)ifd + 2 > n) return 1;
    const int count = rd16(t, (size_t)ifd, le);
    for (int i = 0; i < count; ++i) {
        const size_t e = (size_t)ifd + 2 + (size_t)i * 12;
        if (e + 12 > n) break;
        if (rd16(t, e, le) != 0x0112) continue;
        const int type = rd16(t, e + 2, le);
        const uint32_t v = (type == 3) ? (uint32_t)rd16(t, e + 8, le) : rd32(t, e + 8, le);
        return (v >= 1 && v <= 8) ? (int)v : 1;
    }
    return 1;
}

// Reads the EXIF orientation tag from a JPEG buffer; 1 when absent/unparseable.
static int jpeg_exif_orientation(const unsigned char* d, size_t n)
{
    if (n < 4 || d[0] != 0xFF || d[1] != 0xD8) return 1;
    size_t p = 2;
    while (p + 4 <= n) {
        if (d[p] != 0xFF) {
            ++p;
            continue;
        }
        const unsigned char marker = d[p + 1];
        if (marker == 0xD8 || marker == 0x01 || (marker >= 0xD0 && marker <= 0xD7)) {
            p += 2;
            continue;
        }
        if (marker == 0xDA || marker == 0xD9) break;  // start of scan / end of image
        const size_t len = ((size_t)d[p + 2] << 8) | d[p + 3];
        if (len < 2 || p + 2 + len > n) break;
        if (marker == 0xE1 && len >= 8 && memcmp(d + p + 4, "Exif\0\0", 6) == 0) {
            return tiff_orientation(d + p + 10, len - 8);
        }
        p += 2 + len;
    }
    return 1;
}

static void apply_orientation(Image& im, int o)
{
    if (o <= 1) return;
    const int w = im.w, h = im.h;
    const bool swap = (o >= 5);
    const int nw = swap ? h : w;
    const int nh = swap ? w : h;
    std::vector<unsigned char> out((size_t)nw * nh * 3);
    for (int y = 0; y < nh; ++y) {
        for (int x = 0; x < nw; ++x) {
            int sx = x, sy = y;
            switch (o) {
                case 2: sx = w - 1 - x; sy = y; break;
                case 3: sx = w - 1 - x; sy = h - 1 - y; break;
                case 4: sx = x; sy = h - 1 - y; break;
                case 5: sx = y; sy = x; break;
                case 6: sx = y; sy = h - 1 - x; break;
                case 7: sx = w - 1 - y; sy = h - 1 - x; break;
                case 8: sx = w - 1 - y; sy = x; break;
                default: break;
            }
            const unsigned char* s = &im.rgb[((size_t)sy * w + sx) * 3];
            unsigned char* d = &out[((size_t)y * nw + x) * 3];
            d[0] = s[0];
            d[1] = s[1];
            d[2] = s[2];
        }
    }
    im.w = nw;
    im.h = nh;
    im.rgb.swap(out);
}

static bool load_image(const unsigned char* data, size_t len, Image& im, std::string& err)
{
    int w = 0, h = 0, ch = 0;
    unsigned char* px = stbi_load_from_memory(data, (int)len, &w, &h, &ch, 3);
    if (!px) {
        err = "decode failed";
        return false;
    }
    im.w = w;
    im.h = h;
    im.rgb.assign(px, px + (size_t)w * h * 3);
    stbi_image_free(px);
    apply_orientation(im, jpeg_exif_orientation(data, len));
    if (im.w < 16 || im.h < 16) {
        err = "image too small";
        return false;
    }
    return true;
}

static bool load_image_file(const std::string& path, Image& im, std::string& err)
{
    FILE* f = fopen(path.c_str(), "rb");
    if (!f) {
        err = "cannot open " + path;
        return false;
    }
    std::vector<unsigned char> buf;
    unsigned char tmp[65536];
    size_t n;
    while ((n = fread(tmp, 1, sizeof(tmp), f)) > 0) buf.insert(buf.end(), tmp, tmp + n);
    fclose(f);
    return load_image(buf.data(), buf.size(), im, err);
}

// ─────────────────────────────────────────────────────────────────────────────
// Small helpers
// ─────────────────────────────────────────────────────────────────────────────

static double now_ms()
{
    struct timespec ts;
    clock_gettime(CLOCK_MONOTONIC, &ts);
    return (double)ts.tv_sec * 1000.0 + (double)ts.tv_nsec / 1e6;
}

static double now_s()
{
    struct timespec ts;
    clock_gettime(CLOCK_REALTIME, &ts);
    return (double)ts.tv_sec + (double)ts.tv_nsec / 1e9;
}

// ArcFace 112x112 template (insightface face_align.arcface_dst)
static const float ARC_DST[5][2] = {
    {38.2946f, 51.6963f}, {73.5318f, 51.5014f}, {56.0252f, 71.7366f},
    {41.5493f, 92.3655f}, {70.7299f, 92.2041f},
};

struct FaceBox {
    float x1 = 0, y1 = 0, x2 = 0, y2 = 0;
    float score = 0;
    float kps[10] = {0};  // 5 x (x, y)
    float area() const { return (x2 - x1) * (y2 - y1); }
};

// ─────────────────────────────────────────────────────────────────────────────
// SCRFD detector
// ─────────────────────────────────────────────────────────────────────────────

class Detector {
public:
    bool load(const std::string& param, const std::string& bin, int det_size, int threads,
              float thresh, float nms, std::string& err)
    {
        det_size_ = det_size;
        thresh_ = thresh;
        nms_th_ = nms;
        net_.opt.num_threads = threads;
        net_.opt.use_vulkan_compute = false;
        net_.opt.use_fp16_packed = false;
        net_.opt.use_fp16_storage = false;
        net_.opt.use_fp16_arithmetic = false;
        if (net_.load_param(param.c_str()) != 0) {
            err = "cannot load " + param;
            return false;
        }
        if (net_.load_model(bin.c_str()) != 0) {
            err = "cannot load " + bin;
            return false;
        }
        const std::vector<const char*> ins = net_.input_names();
        const std::vector<const char*> outs = net_.output_names();
        if (ins.empty() || outs.empty()) {
            err = "detector model has no input/output";
            return false;
        }
        in_name_ = ins[0];
        for (const char* o : outs) out_names_.push_back(o);
        if (out_names_.size() < 3) {
            err = "detector must expose at least 3 output blobs";
            return false;
        }
        return true;
    }

    bool detect(const Image& img, std::vector<FaceBox>& faces, std::string& err)
    {
        // insightface FaceAnalysis.detect letterboxing
        const int W = img.w, H = img.h;
        const float im_ratio = (float)H / (float)W;
        int new_w, new_h;
        if (im_ratio > 1.f) {
            new_h = det_size_;
            new_w = (int)((float)new_h / im_ratio);
        } else {
            new_w = det_size_;
            new_h = (int)((float)new_w * im_ratio);
        }
        new_w = std::max(1, std::min(new_w, det_size_));
        new_h = std::max(1, std::min(new_h, det_size_));
        const float det_scale = (float)new_h / (float)H;

        ncnn::Mat resized =
            ncnn::Mat::from_pixels_resize(img.rgb.data(), ncnn::Mat::PIXEL_RGB, W, H, new_w, new_h);
        const float mean_vals[3] = {127.5f, 127.5f, 127.5f};
        const float norm_vals[3] = {1.f / 128.f, 1.f / 128.f, 1.f / 128.f};
        resized.substract_mean_normalize(mean_vals, norm_vals);

        ncnn::Mat blob(det_size_, det_size_, 3);
        blob.fill(0.f);
        for (int c = 0; c < 3; ++c) {
            const float* s = resized.channel(c);
            float* d = blob.channel(c);
            for (int y = 0; y < new_h; ++y) {
                memcpy(d + (size_t)y * det_size_, s + (size_t)y * new_w, (size_t)new_w * sizeof(float));
            }
        }

        ncnn::Extractor ex = net_.create_extractor();
        if (ex.input(in_name_.c_str(), blob) != 0) {
            err = "detector input failed";
            return false;
        }

        // pnnx emits each FPN blob flattened as (rows, cols) with rows ordered
        // per anchor; a planar (w, h, channels) layout is supported as well.
        struct LT {
            const ncnn::Mat* m = nullptr;
            int rows = 0;
            int cols = 0;
            bool planar = false;
        };
        auto at = [](const LT& t, int row, int col) -> float {
            return t.planar ? t.m->channel(col)[row] : t.m->row(row)[col];
        };

        std::vector<ncnn::Mat> mats(out_names_.size());
        std::vector<LT> ts(out_names_.size());
        for (size_t k = 0; k < out_names_.size(); ++k) {
            if (ex.extract(out_names_[k].c_str(), mats[k]) != 0) {
                err = "detector output '" + out_names_[k] + "' failed";
                return false;
            }
            LT t;
            t.m = &mats[k];
            if (mats[k].dims >= 3) {
                t.planar = true;
                t.rows = mats[k].w * mats[k].h;
                t.cols = mats[k].c;
            } else {
                t.planar = false;
                t.rows = mats[k].h;
                t.cols = mats[k].w;
            }
            ts[k] = t;
            if (getenv("FACE_API_DEBUG"))
                fprintf(stderr, "[dbg] out %s dims=%d w=%d h=%d c=%d -> rows=%d cols=%d\n",
                        out_names_[k].c_str(), mats[k].dims, mats[k].w, mats[k].h, mats[k].c,
                        t.rows, t.cols);
        }

        faces.clear();
        std::vector<int> rows_done;
        for (size_t k = 0; k < ts.size(); ++k) {
            if (ts[k].rows <= 0 || ts[k].cols <= 0) continue;
            const int R = ts[k].rows;
            bool dup = false;
            for (int r : rows_done)
                if (r == R) dup = true;
            if (dup) continue;
            rows_done.push_back(R);

            int stride = 0, na = 0;
            for (int s = 8; s <= 32; s *= 2) {
                if (det_size_ % s != 0) continue;
                const long pos = (long)(det_size_ / s) * (det_size_ / s);
                if (pos > 0 && R % pos == 0) {
                    const int c = (int)(R / pos);
                    if (c >= 1 && c <= 4) {
                        stride = s;
                        na = c;
                        break;
                    }
                }
            }
            if (stride == 0) continue;
            const int fw = det_size_ / stride;
            const int fh = fw;

            const LT* score = nullptr;
            const LT* bbox = nullptr;
            const LT* kps = nullptr;
            for (size_t j = 0; j < ts.size(); ++j) {
                if (ts[j].rows != R) continue;
                if (ts[j].cols == 1 || ts[j].cols == na) score = &ts[j];
                else if (ts[j].cols == 4 || ts[j].cols == 4 * na) bbox = &ts[j];
                else if (ts[j].cols == 10 || ts[j].cols == 10 * na) kps = &ts[j];
            }
            if (!score || !bbox || !kps) continue;
            // rows are anchor-major when the score blob has a single column
            const bool per_anchor = (score->cols == 1);
            if (getenv("FACE_API_DEBUG")) {
                float mx = 0.f;
                int hits = 0;
                for (int i = 0; i < fw * fh; ++i)
                    for (int a = 0; a < na; ++a) {
                        const float s = at(*score, per_anchor ? (i * na + a) : i, per_anchor ? 0 : a);
                        if (s > mx) mx = s;
                        if (s >= thresh_) hits++;
                    }
                fprintf(stderr, "[dbg] level stride=%d na=%d pos=%dx%d score_max=%.4f hits=%d\n",
                        stride, na, fw, fh, mx, hits);
            }

            for (int i = 0; i < fw * fh; ++i) {
                const float cx = (float)((i % fw) * stride);
                const float cy = (float)((i / fw) * stride);
                for (int a = 0; a < na; ++a) {
                    const int row = per_anchor ? (i * na + a) : i;
                    const float s = at(*score, row, per_anchor ? 0 : a);
                    if (s < thresh_) continue;

                    const int bcol = per_anchor ? 0 : a * 4;
                    const int kcol = per_anchor ? 0 : a * 10;

                    FaceBox f;
                    f.score = s;
                    f.x1 = (cx - at(*bbox, row, bcol + 0) * stride) / det_scale;
                    f.y1 = (cy - at(*bbox, row, bcol + 1) * stride) / det_scale;
                    f.x2 = (cx + at(*bbox, row, bcol + 2) * stride) / det_scale;
                    f.y2 = (cy + at(*bbox, row, bcol + 3) * stride) / det_scale;
                    for (int p = 0; p < 5; ++p) {
                        f.kps[p * 2 + 0] =
                            (cx + at(*kps, row, kcol + p * 2 + 0) * stride) / det_scale;
                        f.kps[p * 2 + 1] =
                            (cy + at(*kps, row, kcol + p * 2 + 1) * stride) / det_scale;
                    }

                    f.x1 = std::max(0.f, std::min(f.x1, (float)W));
                    f.y1 = std::max(0.f, std::min(f.y1, (float)H));
                    f.x2 = std::max(0.f, std::min(f.x2, (float)W));
                    f.y2 = std::max(0.f, std::min(f.y2, (float)H));
                    if (f.x2 - f.x1 < 2.f || f.y2 - f.y1 < 2.f) continue;
                    faces.push_back(f);
                }
            }
        }

        if (faces.empty()) return true;
        std::sort(faces.begin(), faces.end(),
                  [](const FaceBox& a, const FaceBox& b) { return a.score > b.score; });
        std::vector<FaceBox> kept;
        std::vector<char> dead(faces.size(), 0);
        for (size_t i = 0; i < faces.size(); ++i) {
            if (dead[i]) continue;
            kept.push_back(faces[i]);
            const float ax1 = faces[i].x1, ay1 = faces[i].y1, ax2 = faces[i].x2,
                        ay2 = faces[i].y2;
            const float aarea = (ax2 - ax1) * (ay2 - ay1);
            for (size_t j = i + 1; j < faces.size(); ++j) {
                if (dead[j]) continue;
                const float xx1 = std::max(ax1, faces[j].x1);
                const float yy1 = std::max(ay1, faces[j].y1);
                const float xx2 = std::min(ax2, faces[j].x2);
                const float yy2 = std::min(ay2, faces[j].y2);
                const float iw = std::max(0.f, xx2 - xx1);
                const float ih = std::max(0.f, yy2 - yy1);
                const float inter = iw * ih;
                const float uni = aarea + faces[j].area() - inter;
                if (uni > 0 && inter / uni > nms_th_) dead[j] = 1;
            }
        }
        faces.swap(kept);
        // Primary face = largest box (the photo subject), ties broken by score.
        // Callers use faces[0] for enrollment/verification.
        std::sort(faces.begin(), faces.end(), [](const FaceBox& a, const FaceBox& b) {
            const float aa = a.area(), bb = b.area();
            if (aa != bb) return aa > bb;
            return a.score > b.score;
        });
        return true;
    }

private:
    ncnn::Net net_;
    std::string in_name_;
    std::vector<std::string> out_names_;
    int det_size_ = 640;
    float thresh_ = 0.5f;
    float nms_th_ = 0.4f;
};

// ─────────────────────────────────────────────────────────────────────────────
// ArcFace alignment (norm_crop) + recognizer
// ─────────────────────────────────────────────────────────────────────────────

// Least-squares similarity transform dst = A * src + t with A = [[a, -b], [b, a]].
struct SimTransform {
    float a = 1, b = 0, tx = 0, ty = 0;

    bool inverse_map(float dx, float dy, float& sx, float& sy) const
    {
        const float det = a * a + b * b;
        if (det < 1e-8f) return false;
        const float ux = dx - tx;
        const float uy = dy - ty;
        sx = (a * ux + b * uy) / det;
        sy = (-b * ux + a * uy) / det;
        return true;
    }
};

static bool estimate_similarity(const float src[5][2], const float dst[5][2], SimTransform& out)
{
    float msx = 0, msy = 0, mdx = 0, mdy = 0;
    for (int i = 0; i < 5; ++i) {
        msx += src[i][0];
        msy += src[i][1];
        mdx += dst[i][0];
        mdy += dst[i][1];
    }
    msx /= 5.f;
    msy /= 5.f;
    mdx /= 5.f;
    mdy /= 5.f;

    float num_a = 0, num_b = 0, den = 0;
    for (int i = 0; i < 5; ++i) {
        const float sx = src[i][0] - msx;
        const float sy = src[i][1] - msy;
        const float dx = dst[i][0] - mdx;
        const float dy = dst[i][1] - mdy;
        num_a += sx * dx + sy * dy;
        num_b += sx * dy - sy * dx;
        den += sx * sx + sy * sy;
    }
    if (den < 1e-8f) return false;
    const float a = num_a / den;
    const float b = num_b / den;
    if (a * a + b * b < 1e-8f) return false;
    out.a = a;
    out.b = b;
    out.tx = mdx - (a * msx - b * msy);
    out.ty = mdy - (b * msx + a * msy);
    return true;
}

// cv2.warpAffine(INTER_LINEAR, BORDER_CONSTANT 0) onto the 112x112 template.
static bool align_face(const Image& img, const float kps[10], ncnn::Mat& out112)
{
    float src[5][2];
    for (int i = 0; i < 5; ++i) {
        src[i][0] = kps[i * 2 + 0];
        src[i][1] = kps[i * 2 + 1];
    }
    SimTransform t;
    if (!estimate_similarity(src, ARC_DST, t)) return false;

    out112.create(112, 112, 3);
    const float border = 0.f;
    for (int y = 0; y < 112; ++y) {
        for (int x = 0; x < 112; ++x) {
            float sx, sy;
            if (!t.inverse_map((float)x, (float)y, sx, sy)) {
                for (int c = 0; c < 3; ++c) out112.channel(c)[y * 112 + x] = border;
                continue;
            }
            if (sx < -1.f || sy < -1.f || sx > (float)img.w || sy > (float)img.h) {
                for (int c = 0; c < 3; ++c) out112.channel(c)[y * 112 + x] = border;
                continue;
            }
            const int x0 = (int)std::floor(sx);
            const int y0 = (int)std::floor(sy);
            const float fx = sx - (float)x0;
            const float fy = sy - (float)y0;
            for (int c = 0; c < 3; ++c) {
                auto px = [&](int xx, int yy) -> float {
                    if (xx < 0) xx = 0;
                    if (yy < 0) yy = 0;
                    if (xx >= img.w) xx = img.w - 1;
                    if (yy >= img.h) yy = img.h - 1;
                    return (float)img.rgb[((size_t)yy * img.w + xx) * 3 + c];
                };
                const float v = px(x0, y0) * (1 - fx) * (1 - fy) + px(x0 + 1, y0) * fx * (1 - fy) +
                                px(x0, y0 + 1) * (1 - fx) * fy + px(x0 + 1, y0 + 1) * fx * fy;
                out112.channel(c)[y * 112 + x] = v;
            }
        }
    }
    return true;
}

class Recognizer {
public:
    bool load(const std::string& param, const std::string& bin, int threads, std::string& err)
    {
        net_.opt.num_threads = threads;
        net_.opt.use_vulkan_compute = false;
        net_.opt.use_fp16_packed = false;
        net_.opt.use_fp16_storage = false;
        net_.opt.use_fp16_arithmetic = false;
        if (net_.load_param(param.c_str()) != 0) {
            err = "cannot load " + param;
            return false;
        }
        if (net_.load_model(bin.c_str()) != 0) {
            err = "cannot load " + bin;
            return false;
        }
        const std::vector<const char*> ins = net_.input_names();
        if (ins.empty()) {
            err = "recognizer has no input";
            return false;
        }
        in_name_ = ins[0];
        return true;
    }

    // Runs the recognizer on an aligned 112x112 RGB crop and L2-normalizes the
    // 512-d output (insightface normed_embedding).
    bool embed_aligned(const ncnn::Mat& crop112, std::vector<float>& emb, std::string& err)
    {
        ncnn::Mat in(112, 112, 3);
        for (int c = 0; c < 3; ++c) {
            const float* s = crop112.channel(c);
            float* d = in.channel(c);
            for (int i = 0; i < 112 * 112; ++i) d[i] = (s[i] - 127.5f) / 127.5f;
        }

        ncnn::Extractor ex = net_.create_extractor();
        if (ex.input(in_name_.c_str(), in) != 0) {
            err = "recognizer input failed";
            return false;
        }
        std::vector<const char*> outs = net_.output_names();
        ncnn::Mat out;
        bool ok = false;
        for (const char* name : outs) {
            if (ex.extract(name, out) != 0) continue;
            if (out.total() == 512) {
                ok = true;
                break;
            }
        }
        if (!ok) {
            err = "recognizer output is not 512-d";
            return false;
        }
        emb.resize(512);
        double norm = 0.0;
        for (int i = 0; i < 512; ++i) {
            emb[i] = out[i];
            norm += (double)out[i] * out[i];
        }
        norm = std::sqrt(norm);
        if (norm > 0.0) {
            for (float& v : emb) v = (float)(v / norm);
        }
        return true;
    }

    bool embed_image(const Image& img, std::vector<float>& emb, std::string& err)
    {
        ncnn::Mat resized =
            ncnn::Mat::from_pixels_resize(img.rgb.data(), ncnn::Mat::PIXEL_RGB, img.w, img.h, 112, 112);
        return embed_aligned(resized, emb, err);
    }

private:
    ncnn::Net net_;
    std::string in_name_;
};

// ─────────────────────────────────────────────────────────────────────────────
// Pipeline (one instance per worker thread: own Net objects)
// ─────────────────────────────────────────────────────────────────────────────

// ─────────────────────────────────────────────────────────────────────────────
// Passive liveness heuristics (/verify)
// NOTE: these are quality/geometry checks, NOT a learned anti-spoofing model —
// they catch blurred or tiny captures but not a good photo-of-a-photo. Keep the
// Python node (moire/glare/depth models) online if real anti-spoofing is needed.
// ─────────────────────────────────────────────────────────────────────────────

struct LivenessOut {
    bool passed = true;
    float score = 1.f;
    std::string json = "{}";
};

struct LivenessConfig {
    // 0.02 ≈ a face covering 2% of the frame area; calibrated on real check-in
    // selfies from the app (they range 0.039–0.23).
    float min_face_ratio = 0.02f;
    float min_sharpness = 40.f;
    float min_brightness = 40.f;
    float max_brightness = 220.f;
    float min_channel_std = 6.f;
};

static LivenessOut evaluate_liveness(const Image& img, const FaceBox& box, int num_faces,
                                     const ncnn::Mat& crop112, const LivenessConfig& lc)
{
    LivenessOut out;
    const bool single = (num_faces == 1);
    const double area = (double)(box.x2 - box.x1) * (box.y2 - box.y1);
    const double ratio = area / ((double)img.w * (double)img.h);
    const bool size_ok = ratio >= (double)lc.min_face_ratio;

    // Grayscale statistics + Laplacian variance on the aligned crop.
    double mean = 0.0;
    std::vector<float> gray(112 * 112);
    for (int y = 0; y < 112; ++y) {
        for (int x = 0; x < 112; ++x) {
            const float r = crop112.channel(0)[y * 112 + x];
            const float g = crop112.channel(1)[y * 112 + x];
            const float b = crop112.channel(2)[y * 112 + x];
            const float v = 0.299f * r + 0.587f * g + 0.114f * b;
            gray[y * 112 + x] = v;
            mean += v;
        }
    }
    mean /= (112.0 * 112.0);
    const bool brightness_ok = (mean >= lc.min_brightness && mean <= lc.max_brightness);

    double lap_sum = 0.0, lap_sq = 0.0;
    int lap_n = 0;
    for (int y = 1; y < 111; ++y) {
        for (int x = 1; x < 111; ++x) {
            const float l = 4.f * gray[y * 112 + x] - gray[(y - 1) * 112 + x] -
                            gray[(y + 1) * 112 + x] - gray[y * 112 + x - 1] - gray[y * 112 + x + 1];
            lap_sum += l;
            lap_sq += (double)l * l;
            ++lap_n;
        }
    }
    const double lap_mean = lap_n ? lap_sum / lap_n : 0.0;
    const double sharpness = lap_n ? (lap_sq / lap_n - lap_mean * lap_mean) : 0.0;
    const bool sharp_ok = sharpness >= (double)lc.min_sharpness;

    double min_std = 1e9;
    for (int c = 0; c < 3; ++c) {
        double m = 0.0;
        for (int i = 0; i < 112 * 112; ++i) m += crop112.channel(c)[i];
        m /= (112.0 * 112.0);
        double var = 0.0;
        for (int i = 0; i < 112 * 112; ++i) {
            const double d = crop112.channel(c)[i] - m;
            var += d * d;
        }
        var /= (112.0 * 112.0);
        min_std = std::min(min_std, std::sqrt(var));
    }
    const bool color_ok = min_std >= (double)lc.min_channel_std;

    // `single` is reported for diagnostics only: bystanders in the frame are
    // common in check-in selfies and must not reject a genuine capture.
    const int gated = (size_ok ? 1 : 0) + (brightness_ok ? 1 : 0) + (sharp_ok ? 1 : 0) +
                      (color_ok ? 1 : 0);
    out.score = (float)gated / 4.f;
    out.passed = (gated == 4);

    char buf[640];
    snprintf(buf, sizeof(buf),
             "{\"single_face\":%s,\"face_ratio\":%.3f,\"face_size_ok\":%s,"
             "\"sharpness\":%.1f,\"sharpness_ok\":%s,\"brightness\":%.1f,"
             "\"brightness_ok\":%s,\"color_std\":%.1f,\"color_ok\":%s,"
             "\"rejection_reason\":\"%s\"}",
             single ? "true" : "false", ratio, size_ok ? "true" : "false", sharpness,
             sharp_ok ? "true" : "false", mean, brightness_ok ? "true" : "false", min_std,
             color_ok ? "true" : "false",
             out.passed ? ""
                        : (!size_ok ? "face_too_small"
                                    : (!brightness_ok ? "bad_exposure"
                                                      : (!sharp_ok ? "image_blurry"
                                                                   : "low_color_variance"))));
    out.json = buf;
    return out;
}

struct PipelineConfig {
    std::string model_dir;
    std::string det_param = "det_10g.param";
    std::string det_bin = "det_10g.bin";
    std::string rec_param = "w600k_mbf.param";
    std::string rec_bin = "w600k_mbf.bin";
    int det_size = 640;
    int threads = 4;
    float det_thresh = 0.5f;
    float nms = 0.4f;
    int max_faces = 5;
    float match_threshold = 0.30f;
    bool liveness_enabled = true;
    LivenessConfig liveness;
};

struct FaceResult {
    FaceBox box;
    std::vector<float> emb;
};

struct Pipeline {
    Detector det;
    Recognizer rec;

    bool load(const PipelineConfig& cfg, std::string& err)
    {
        if (!det.load(cfg.model_dir + "/" + cfg.det_param, cfg.model_dir + "/" + cfg.det_bin,
                      cfg.det_size, cfg.threads, cfg.det_thresh, cfg.nms, err))
            return false;
        if (!rec.load(cfg.model_dir + "/" + cfg.rec_param, cfg.model_dir + "/" + cfg.rec_bin,
                      cfg.threads, err))
            return false;
        // Warm the kernels so the first real request is not penalised.
        Image warm;
        warm.w = 320;
        warm.h = 240;
        warm.rgb.assign((size_t)warm.w * warm.h * 3, 128);
        std::vector<FaceBox> f;
        det.detect(warm, f, err);
        ncnn::Mat crop(112, 112, 3);
        crop.fill(0.f);
        std::vector<float> e;
        rec.embed_aligned(crop, e, err);
        err.clear();
        return true;
    }

    bool run(const Image& img, const PipelineConfig& cfg, std::vector<FaceResult>& out,
             std::string& err, double* det_ms = nullptr, double* emb_ms = nullptr,
             int face_limit = -1, int* total_faces = nullptr)
    {
        out.clear();
        std::vector<FaceBox> faces;
        const double t0 = now_ms();
        if (!det.detect(img, faces, err)) return false;
        const double t1 = now_ms();
        if (det_ms) *det_ms = t1 - t0;
        if (total_faces) *total_faces = (int)faces.size();

        const int want = face_limit > 0 ? face_limit : cfg.max_faces;
        const int limit = std::min((int)faces.size(), std::max(1, want));
        for (int i = 0; i < limit; ++i) {
            FaceResult r;
            r.box = faces[i];
            ncnn::Mat crop;
            if (!align_face(img, faces[i].kps, crop)) continue;
            if (!rec.embed_aligned(crop, r.emb, err)) return false;
            out.push_back(std::move(r));
        }
        if (emb_ms) *emb_ms = now_ms() - t1;
        return true;
    }
};

// ─────────────────────────────────────────────────────────────────────────────
// JSON helpers
// ─────────────────────────────────────────────────────────────────────────────

static void append_floats(std::string& s, const std::vector<float>& v)
{
    char buf[32];
    s += '[';
    for (size_t i = 0; i < v.size(); ++i) {
        snprintf(buf, sizeof(buf), i ? ",%.8g" : "%.8g", v[i]);
        s += buf;
    }
    s += ']';
}

static std::string faces_json(const std::vector<FaceResult>& faces)
{
    char buf[256];
    std::string s = "[";
    for (size_t i = 0; i < faces.size(); ++i) {
        if (i) s += ',';
        snprintf(buf, sizeof(buf), "{\"bbox\":[%.2f,%.2f,%.2f,%.2f],\"det_score\":%.4f,\"kps\":[",
                 faces[i].box.x1, faces[i].box.y1, faces[i].box.x2, faces[i].box.y2,
                 faces[i].box.score);
        s += buf;
        for (int k = 0; k < 5; ++k) {
            snprintf(buf, sizeof(buf), k ? ",%.2f,%.2f" : "%.2f,%.2f", faces[i].box.kps[k * 2],
                     faces[i].box.kps[k * 2 + 1]);
            s += buf;
        }
        s += "],\"embedding\":";
        append_floats(s, faces[i].emb);
        s += '}';
    }
    s += ']';
    return s;
}

static void respond(int fd, int status, const std::string& body,
                    const char* ctype = "application/json")
{
    const char* reason = "OK";
    switch (status) {
        case 200: reason = "OK"; break;
        case 400: reason = "Bad Request"; break;
        case 401: reason = "Unauthorized"; break;
        case 404: reason = "Not Found"; break;
        case 405: reason = "Method Not Allowed"; break;
        case 413: reason = "Payload Too Large"; break;
        case 500: reason = "Internal Server Error"; break;
        case 503: reason = "Service Unavailable"; break;
        default: reason = "OK"; break;
    }
    char head[512];
    snprintf(head, sizeof(head),
             "HTTP/1.1 %d %s\r\nContent-Type: %s\r\nContent-Length: %zu\r\n"
             "Connection: close\r\nServer: face-api/ncnn\r\n\r\n",
             status, reason, ctype, body.size());
    std::string out = head;
    out += body;
    size_t sent = 0;
    while (sent < out.size()) {
        ssize_t n = ::send(fd, out.data() + sent, out.size() - sent, 0);
        if (n <= 0) {
            if (n < 0 && errno == EINTR) continue;
            break;
        }
        sent += (size_t)n;
    }
}

static std::string lower(std::string s)
{
    for (char& c : s)
        if (c >= 'A' && c <= 'Z') c = (char)(c - 'A' + 'a');
    return s;
}

static std::string trim(const std::string& s)
{
    size_t b = s.find_first_not_of(" \t\r\n");
    if (b == std::string::npos) return "";
    size_t e = s.find_last_not_of(" \t\r\n");
    return s.substr(b, e - b + 1);
}

struct HttpRequest {
    std::string method;
    std::string path;
    std::string query;
    std::map<std::string, std::string> headers;
    std::string body;
};

// Reads an integer query parameter (?faces=1&...), returning -1 when absent.
static int query_int(const HttpRequest& req, const std::string& key)
{
    std::string q = "?" + req.query + "&";
    const std::string pat = key + "=";
    size_t p = 0;
    while ((p = q.find(pat, p)) != std::string::npos) {
        if (p == 0 || q[p - 1] == '&' || q[p - 1] == '?') {
            const size_t v = p + pat.size();
            const size_t e = q.find('&', v);
            return atoi(q.substr(v, e - v).c_str());
        }
        p += pat.size();
    }
    return -1;
}

struct MultipartPart {
    std::string name;
    std::string filename;
    std::string data;
};

// Splits a multipart/form-data body into its parts (name, filename, payload).
static bool multipart_parts(const std::string& body, const std::string& ctype,
                            std::vector<MultipartPart>& parts, std::string& err)
{
    const std::string key = "boundary=";
    size_t bp = ctype.find(key);
    if (bp == std::string::npos) {
        err = "multipart without boundary";
        return false;
    }
    std::string boundary = trim(ctype.substr(bp + key.size()));
    if (!boundary.empty() && boundary.front() == '"' && boundary.back() == '"')
        boundary = boundary.substr(1, boundary.size() - 2);
    const std::string delim = "--" + boundary;

    size_t pos = body.find(delim);
    if (pos == std::string::npos) {
        err = "multipart body malformed";
        return false;
    }
    parts.clear();
    while (true) {
        pos += delim.size();
        if (pos + 2 <= body.size() && body.compare(pos, 2, "--") == 0) break;  // closing
        if (pos + 2 <= body.size() && body.compare(pos, 2, "\r\n") == 0) pos += 2;

        const size_t head_end = body.find("\r\n\r\n", pos);
        if (head_end == std::string::npos) {
            err = "multipart part without headers";
            return false;
        }
        const std::string headers = body.substr(pos, head_end - pos);
        const size_t data_start = head_end + 4;
        size_t data_end = body.find(delim, data_start);
        if (data_end == std::string::npos) {
            err = "multipart part without terminator";
            return false;
        }
        size_t real_end = data_end;
        if (real_end >= 2 && body.compare(real_end - 2, 2, "\r\n") == 0) real_end -= 2;

        MultipartPart p;
        const size_t np = headers.find("name=\"");
        if (np != std::string::npos) {
            const size_t s = np + 6;
            const size_t e = headers.find('"', s);
            if (e != std::string::npos) p.name = headers.substr(s, e - s);
        }
        const size_t fp = headers.find("filename=\"");
        if (fp != std::string::npos) {
            const size_t s = fp + 10;
            const size_t e = headers.find('"', s);
            if (e != std::string::npos) p.filename = headers.substr(s, e - s);
        }
        p.data.assign(body, data_start, real_end - data_start);
        parts.push_back(std::move(p));
        pos = data_end;
    }
    if (parts.empty()) {
        err = "no parts in multipart body";
        return false;
    }
    return true;
}

// Extracts the bytes of the multipart part named "image" (or the first part
// that carries a filename). Returns false when no such part exists.
static bool multipart_image(const std::string& body, const std::string& ctype, std::string& out,
                            std::string& err)
{
    std::vector<MultipartPart> parts;
    if (!multipart_parts(body, ctype, parts, err)) return false;
    for (const MultipartPart& p : parts) {
        if (p.name == "image" && !p.data.empty()) {
            out = p.data;
            return true;
        }
    }
    for (const MultipartPart& p : parts) {
        if (!p.filename.empty() && !p.data.empty()) {
            out = p.data;
            return true;
        }
    }
    err = "no 'image' part in multipart body";
    return false;
}

// Parses "[0.1, 0.2, ...]" (the known_embedding form field).
static bool parse_float_array(const std::string& s, std::vector<float>& out)
{
    out.clear();
    const char* p = s.c_str();
    while (*p && *p != '[') ++p;
    if (*p == '[') ++p;
    while (*p) {
        while (*p == ' ' || *p == '\t' || *p == '\r' || *p == '\n' || *p == ',') ++p;
        if (*p == ']' || *p == 0) break;
        char* end = nullptr;
        const float v = strtof(p, &end);
        if (end == p) return false;
        out.push_back(v);
        p = end;
    }
    return !out.empty();
}

static bool read_request(int fd, HttpRequest& req, size_t max_body, std::string& err)
{
    std::string buf;
    buf.reserve(8192);
    size_t header_end = std::string::npos;
    char tmp[8192];
    while (header_end == std::string::npos) {
        ssize_t n = ::recv(fd, tmp, sizeof(tmp), 0);
        if (n <= 0) {
            if (n < 0 && errno == EINTR) continue;
            err = "connection closed before headers";
            return false;
        }
        buf.append(tmp, (size_t)n);
        header_end = buf.find("\r\n\r\n");
        if (buf.size() > 65536) {
            err = "headers too large";
            return false;
        }
    }

    const std::string head = buf.substr(0, header_end);
    size_t line_end = head.find("\r\n");
    const std::string request_line = head.substr(0, line_end);
    size_t sp1 = request_line.find(' ');
    size_t sp2 = request_line.find(' ', sp1 + 1);
    if (sp1 == std::string::npos || sp2 == std::string::npos) {
        err = "malformed request line";
        return false;
    }
    req.method = request_line.substr(0, sp1);
    std::string target = request_line.substr(sp1 + 1, sp2 - sp1 - 1);
    const size_t q = target.find('?');
    req.path = (q == std::string::npos) ? target : target.substr(0, q);
    req.query = (q == std::string::npos) ? std::string() : target.substr(q + 1);

    size_t lp = line_end;
    while (lp != std::string::npos && lp + 2 <= head.size()) {
        lp += 2;
        const size_t e = head.find("\r\n", lp);
        const std::string line = head.substr(lp, e == std::string::npos ? std::string::npos : e - lp);
        const size_t colon = line.find(':');
        if (colon != std::string::npos) {
            req.headers[lower(trim(line.substr(0, colon)))] = trim(line.substr(colon + 1));
        }
        if (e == std::string::npos) break;
        lp = e;
    }

    size_t content_length = 0;
    auto it = req.headers.find("content-length");
    if (it != req.headers.end()) content_length = (size_t)strtoull(it->second.c_str(), nullptr, 10);
    if (content_length > max_body) {
        err = "payload too large";
        return false;
    }
    if (req.headers.count("expect") && lower(req.headers["expect"]).find("100-continue") != std::string::npos) {
        const std::string cont = "HTTP/1.1 100 Continue\r\n\r\n";
        ::send(fd, cont.data(), cont.size(), 0);
    }

    req.body = buf.substr(header_end + 4);
    while (req.body.size() < content_length) {
        ssize_t n = ::recv(fd, tmp, sizeof(tmp), 0);
        if (n <= 0) {
            if (n < 0 && errno == EINTR) continue;
            err = "connection closed mid-body";
            return false;
        }
        req.body.append(tmp, (size_t)n);
    }
    if (req.body.size() > content_length) req.body.resize(content_length);
    return true;
}

// ─────────────────────────────────────────────────────────────────────────────
// Server
// ─────────────────────────────────────────────────────────────────────────────

struct Stats {
    std::atomic<long> requests{0};
    std::atomic<long> errors{0};
    std::atomic<long> faces{0};
    std::atomic<double> total_ms{0};
};

struct ServerConfig {
    PipelineConfig pipeline;
    std::string host = "0.0.0.0";
    int port = 8010;
    int workers = 2;
    std::string api_key;
    size_t max_body = 8 * 1024 * 1024;
};

static std::atomic<bool> g_stop{false};
static double g_start_time = 0.0;

static void on_signal(int)
{
    g_stop = true;
}

static int make_listener(const std::string& host, int port, std::string& err)
{
    const int fd = ::socket(AF_INET, SOCK_STREAM, 0);
    if (fd < 0) {
        err = "socket() failed";
        return -1;
    }
    int one = 1;
    setsockopt(fd, SOL_SOCKET, SO_REUSEADDR, &one, sizeof(one));
    struct sockaddr_in addr;
    memset(&addr, 0, sizeof(addr));
    addr.sin_family = AF_INET;
    addr.sin_port = htons((uint16_t)port);
    if (host.empty() || host == "0.0.0.0") {
        addr.sin_addr.s_addr = htonl(INADDR_ANY);
    } else if (::inet_pton(AF_INET, host.c_str(), &addr.sin_addr) != 1) {
        err = "invalid bind address";
        ::close(fd);
        return -1;
    }
    if (::bind(fd, (struct sockaddr*)&addr, sizeof(addr)) != 0) {
        err = std::string("bind failed: ") + strerror(errno);
        ::close(fd);
        return -1;
    }
    if (::listen(fd, 64) != 0) {
        err = std::string("listen failed: ") + strerror(errno);
        ::close(fd);
        return -1;
    }
    return fd;
}

// Logs through snprintf so the argument types are checked at compile time.
#define log_line(...)                       \
    do {                                    \
        char _logbuf[768];                  \
        snprintf(_logbuf, sizeof(_logbuf), __VA_ARGS__); \
        fputs(_logbuf, stderr);             \
        fflush(stderr);                     \
    } while (0)

// POST /verify — same contract as ai_service/server.py (/verify):
//   multipart: image=<file>, known_embedding=<json [512]>, check_liveness=true|false
//   -> is_match = face_match && liveness_passed (server-authoritative decision)
static void handle_verify(int fd, Pipeline& pipe, const ServerConfig& cfg, Stats& stats,
                          const HttpRequest& req, const std::string& ctype, double t0)
{
    std::string err;
    if (lower(ctype).find("multipart/form-data") == std::string::npos) {
        stats.errors++;
        respond(fd, 400, "{\"success\":false,\"error\":\"verify requires multipart/form-data\"}");
        return;
    }

    std::vector<MultipartPart> parts;
    if (!multipart_parts(req.body, ctype, parts, err)) {
        stats.errors++;
        respond(fd, 400, "{\"success\":false,\"error\":\"" + err + "\"}");
        return;
    }

    std::string img_bytes, known_json, liveness_field;
    for (const MultipartPart& p : parts) {
        if (p.name == "image" && !p.data.empty()) img_bytes = p.data;
        else if (p.name == "known_embedding") known_json = p.data;
        else if (p.name == "check_liveness") liveness_field = p.data;
    }
    if (img_bytes.empty()) {
        stats.errors++;
        respond(fd, 400, "{\"success\":false,\"error\":\"missing image part\"}");
        return;
    }

    std::vector<float> known;
    if (!parse_float_array(known_json, known) || known.size() != 512) {
        stats.errors++;
        respond(fd, 400,
                "{\"success\":false,\"error\":\"known_embedding must be a JSON array of 512 floats\"}");
        return;
    }
    double kn = 0.0;
    for (float v : known) kn += (double)v * v;
    kn = std::sqrt(kn);
    if (kn <= 0.0) {
        stats.errors++;
        respond(fd, 400, "{\"success\":false,\"error\":\"known_embedding must be non-zero\"}");
        return;
    }
    for (float& v : known) v = (float)(v / kn);

    Image img;
    if (!load_image((const unsigned char*)img_bytes.data(), img_bytes.size(), img, err)) {
        stats.errors++;
        respond(fd, 400, "{\"success\":false,\"error\":\"" + err + "\"}");
        return;
    }

    std::vector<FaceResult> faces;
    double det_ms = 0, emb_ms = 0;
    int total_faces = 0;
    if (!pipe.run(img, cfg.pipeline, faces, err, &det_ms, &emb_ms, 1, &total_faces)) {
        stats.errors++;
        respond(fd, 500, "{\"success\":false,\"error\":\"" + err + "\"}");
        return;
    }

    const double ms_total = now_ms() - t0;
    if (faces.empty()) {
        char body[512];
        snprintf(body, sizeof(body),
                 "{\"status\":\"no_face\",\"is_match\":false,\"face_match\":false,"
                 "\"liveness_passed\":false,\"similarity\":0.0,\"score_percentage\":0.0,"
                 "\"liveness_score\":0.0,\"message\":\"No face detected in frame\","
                 "\"detector_used\":\"scrfd-%d\",\"processing_ms\":%.2f}",
                 cfg.pipeline.det_size, ms_total);
        stats.requests++;
        stats.errors++;
        stats.total_ms = stats.total_ms.load() + ms_total;
        log_line("[api] /verify no_face %.1fms\n", ms_total);
        respond(fd, 200, body);
        return;
    }

    const FaceResult& best = faces[0];  // detector returns score-desc order
    double sim = 0.0;
    for (int i = 0; i < 512; ++i) sim += (double)known[i] * best.emb[i];

    const bool want_liveness = !(lower(liveness_field) == "false" || liveness_field == "0");
    LivenessOut liv;
    std::string liveness_mode = "off";
    if (cfg.pipeline.liveness_enabled && want_liveness) {
        ncnn::Mat crop;
        if (align_face(img, best.box.kps, crop))
            liv = evaluate_liveness(img, best.box, total_faces, crop, cfg.pipeline.liveness);
        liveness_mode = "heuristic";
    }

    const float thr = cfg.pipeline.match_threshold;
    const bool face_match = (sim >= (double)thr);
    const bool final_pass = face_match && liv.passed;
    const double score_pct = sim * 100.0;

    char msg[512];
    if (final_pass)
        snprintf(msg, sizeof(msg), "Face verified (%0.1f%%) - liveness confirmed", score_pct);
    else if (!face_match)
        snprintf(msg, sizeof(msg), "Face does not match (%0.1f%%)", score_pct);
    else
        snprintf(msg, sizeof(msg), "Face matches (%0.1f%%) but liveness check failed", score_pct);

    char head[1024];
    snprintf(head, sizeof(head),
             "{\"status\":\"success\",\"is_match\":%s,\"face_match\":%s,"
             "\"liveness_passed\":%s,\"similarity\":%.4f,\"score_percentage\":%.2f,"
             "\"liveness_score\":%.4f,\"liveness_mode\":\"%s\",\"embedder\":\"ncnn-w600k_mbf\","
             "\"match_threshold\":%.4f,\"detector_used\":\"scrfd-%d\",\"num_faces\":%d,"
             "\"processing_ms\":%.2f,\"timings_ms\":{\"detect\":%.2f,\"embed\":%.2f},"
             "\"message\":\"%s\",\"liveness_checks\":",
             final_pass ? "true" : "false", face_match ? "true" : "false",
             liv.passed ? "true" : "false", sim, score_pct, liv.score, liveness_mode.c_str(), thr,
             cfg.pipeline.det_size, total_faces, ms_total, det_ms, emb_ms, msg);
    std::string body = head;
    body += liv.json;
    body += ",\"depth_liveness_checks\":{\"available\":false}}";

    stats.requests++;
    stats.faces.fetch_add((long)total_faces);
    if (!final_pass) stats.errors++;
    stats.total_ms = stats.total_ms.load() + ms_total;
    log_line("[api] /verify match=%d(%.1f%%) live=%d(%s) final=%d %.1fms\n", (int)face_match,
             score_pct, (int)liv.passed, liveness_mode.c_str(), (int)final_pass, ms_total);
    respond(fd, 200, body);
}

static void handle_connection(int fd, Pipeline& pipe, const ServerConfig& cfg, Stats& stats)
{
    struct timeval tv;
    tv.tv_sec = 15;
    tv.tv_usec = 0;
    setsockopt(fd, SOL_SOCKET, SO_RCVTIMEO, &tv, sizeof(tv));
    setsockopt(fd, SOL_SOCKET, SO_SNDTIMEO, &tv, sizeof(tv));

    HttpRequest req;
    std::string err;
    const double t0 = now_ms();
    if (!read_request(fd, req, cfg.max_body, err)) {
        stats.errors++;
        const bool too_big = (err == "payload too large");
        respond(fd, too_big ? 413 : 400, "{\"success\":false,\"error\":\"" + err + "\"}");
        return;
    }

    if (!cfg.api_key.empty()) {
        auto it = req.headers.find("x-api-key");
        if (it == req.headers.end() || it->second != cfg.api_key) {
            stats.errors++;
            respond(fd, 401, "{\"success\":false,\"error\":\"invalid api key\"}");
            return;
        }
    }

    if (req.path == "/health" || req.path == "/") {
        const long n = stats.requests.load();
        const double avg = n > 0 ? stats.total_ms.load() / (double)n : 0.0;
        char body[512];
        snprintf(body, sizeof(body),
                 "{\"status\":\"healthy\",\"service\":\"face-api\",\"backend\":\"ncnn-cpu\","
                 "\"pipeline\":\"scrfd10g+arcface+mobilefacenet512\",\"models\":{\"detector\":true,"
                 "\"recognizer\":true,\"verify\":true},\"det_size\":%d,\"threads\":%d,"
                 "\"workers\":%d,\"match_threshold\":%.2f,\"liveness\":\"%s\","
                 "\"dynamic_range\":\"fp32\",\"requests\":%ld,\"errors\":%ld,\"faces\":%ld,"
                 "\"avg_ms\":%.1f,\"uptime_s\":%.0f}",
                 cfg.pipeline.det_size, cfg.pipeline.threads, cfg.workers,
                 cfg.pipeline.match_threshold,
                 cfg.pipeline.liveness_enabled ? "heuristic" : "off", n,
                 stats.errors.load(), stats.faces.load(), avg, now_s() - g_start_time);
        respond(fd, 200, body);
        return;
    }

    if (req.path == "/verify") {
        std::string ctype0;
        auto ct0 = req.headers.find("content-type");
        if (ct0 != req.headers.end()) ctype0 = ct0->second;
        handle_verify(fd, pipe, cfg, stats, req, ctype0, t0);
        return;
    }

    const bool is_extract = (req.path == "/extract");
    const bool is_detect = (req.path == "/detect");
    const bool is_embed = (req.path == "/embed");
    if (!is_extract && !is_detect && !is_embed) {
        respond(fd, 404, "{\"success\":false,\"error\":\"unknown endpoint\"}");
        return;
    }
    if (req.method != "POST") {
        respond(fd, 405, "{\"success\":false,\"error\":\"use POST\"}");
        return;
    }

    // NB: the multipart boundary is case-sensitive, so keep the raw header value
    std::string ctype;
    auto ct = req.headers.find("content-type");
    if (ct != req.headers.end()) ctype = ct->second;

    std::string img_bytes = req.body;
    if (lower(ctype).find("multipart/form-data") != std::string::npos) {
        img_bytes.clear();
        if (!multipart_image(req.body, ctype, img_bytes, err)) {
            stats.errors++;
            respond(fd, 400, "{\"success\":false,\"error\":\"" + err + "\"}");
            return;
        }
    }
    if (img_bytes.empty()) {
        stats.errors++;
        respond(fd, 400, "{\"success\":false,\"error\":\"empty image\"}");
        return;
    }

    Image img;
    if (!load_image((const unsigned char*)img_bytes.data(), img_bytes.size(), img, err)) {
        stats.errors++;
        respond(fd, 400, "{\"success\":false,\"error\":\"" + err + "\"}");
        return;
    }

    if (is_embed) {
        std::vector<float> emb;
        if (!pipe.rec.embed_image(img, emb, err)) {
            stats.errors++;
            respond(fd, 500, "{\"success\":false,\"error\":\"" + err + "\"}");
            return;
        }
        const double ms = now_ms() - t0;
        stats.requests++;
        stats.total_ms = stats.total_ms.load() + ms;
        char head[256];
        snprintf(head, sizeof(head),
                 "{\"success\":true,\"model\":\"w600k_mbf\",\"dim\":512,\"embedding\":");
        std::string body = head;
        append_floats(body, emb);
        snprintf(head, sizeof(head), ",\"embedding_512d\":");
        body += head;
        append_floats(body, emb);
        snprintf(head, sizeof(head), ",\"embedding_128d\":null,\"processing_ms\":%.2f}", ms);
        body += head;
        log_line("[api] /embed ok %.1fms\n", ms);
        respond(fd, 200, body);
        return;
    }

    std::vector<FaceResult> faces;
    double det_ms = 0, emb_ms = 0;
    // ?faces=N limits how many detected faces get an embedding (the rest are
    // still reported with their geometry); the primary face is the first one.
    const int face_limit = query_int(req, "faces");
    if (!pipe.run(img, cfg.pipeline, faces, err, &det_ms, &emb_ms, face_limit)) {
        stats.errors++;
        respond(fd, 500, "{\"success\":false,\"error\":\"" + err + "\"}");
        return;
    }
    const double ms = now_ms() - t0;

    if (is_detect) {
        char body[1024];
        std::string fj = faces_json(faces);
        // strip embeddings from the detect payload
        std::string trimmed;
        for (size_t i = 0; i < fj.size(); ++i) {
            if (fj.compare(i, 13, ",\"embedding\":") == 0) {
                int d = 0;
                size_t j = i + 12;
                while (j < fj.size()) {
                    if (fj[j] == '[') d++;
                    else if (fj[j] == ']') {
                        d--;
                        if (d == 0) break;
                    }
                    j++;
                }
                i = j;
                continue;
            }
            trimmed += fj[i];
        }
        snprintf(body, sizeof(body),
                 "{\"success\":true,\"num_faces\":%zu,\"faces\":%s,\"processing_ms\":%.2f}",
                 faces.size(), trimmed.c_str(), ms);
        stats.requests++;
        stats.total_ms = stats.total_ms.load() + ms;
        respond(fd, 200, body);
        return;
    }

    if (faces.empty()) {
        char body[512];
        snprintf(body, sizeof(body),
                 "{\"success\":false,\"error\":\"no face detected\",\"num_faces\":0,\"faces\":[],"
                 "\"processing_ms\":%.2f}",
                 ms);
        stats.requests++;
        stats.errors++;
        stats.total_ms = stats.total_ms.load() + ms;
        log_line("[api] /extract no-face %.1fms (det %.1fms, emb %.1fms)\n", ms, det_ms, emb_ms);
        respond(fd, 200, body);
        return;
    }

    const std::vector<float>& primary = faces[0].emb;
    std::string body = "{\"success\":true,\"model\":\"w600k_mbf\",\"dim\":512,\"num_faces\":";
    char buf[512];
    snprintf(buf, sizeof(buf), "%zu", faces.size());
    body += buf;
    snprintf(buf, sizeof(buf),
             ",\"bbox\":[%.2f,%.2f,%.2f,%.2f],\"det_score\":%.4f,\"embedding\":", faces[0].box.x1,
             faces[0].box.y1, faces[0].box.x2, faces[0].box.y2, faces[0].box.score);
    body += buf;
    append_floats(body, primary);
    body += ",\"embedding_512d\":";
    append_floats(body, primary);
    body += ",\"embedding_128d\":null";
    // Keep the geometry of every detected face for higher-level matching.
    body += ",\"faces\":[";
    for (size_t i = 0; i < faces.size(); ++i) {
        if (i) body += ',';
        snprintf(buf, sizeof(buf),
                 "{\"bbox\":[%.2f,%.2f,%.2f,%.2f],\"det_score\":%.4f,\"kps\":[", faces[i].box.x1,
                 faces[i].box.y1, faces[i].box.x2, faces[i].box.y2, faces[i].box.score);
        body += buf;
        for (int k = 0; k < 5; ++k) {
            snprintf(buf, sizeof(buf), k ? ",%.2f,%.2f" : "%.2f,%.2f", faces[i].box.kps[k * 2],
                     faces[i].box.kps[k * 2 + 1]);
            body += buf;
        }
        body += "],\"embedding\":";
        append_floats(body, faces[i].emb);
        body += '}';
    }
    body += ']';
    snprintf(buf, sizeof(buf),
             ",\"timings_ms\":{\"detect\":%.2f,\"embed\":%.2f,\"total\":%.2f},\"processing_ms\":%.2f}",
             det_ms, emb_ms, ms, ms);
    body += buf;

    stats.requests++;
    stats.faces.fetch_add((long)faces.size());
    stats.total_ms = stats.total_ms.load() + ms;
    log_line("[api] /extract ok %zu face(s) %.1fms (det %.1fms, emb %.1fms)\n", faces.size(), ms,
             det_ms, emb_ms);
    respond(fd, 200, body);
}

static void worker_loop(int listen_fd, Pipeline& pipe, const ServerConfig& cfg, Stats& stats)
{
    while (!g_stop) {
        struct sockaddr_in peer;
        socklen_t plen = sizeof(peer);
        const int fd = ::accept(listen_fd, (struct sockaddr*)&peer, &plen);
        if (fd < 0) {
            if (errno == EINTR || errno == EBADF) {
                if (g_stop) break;
                continue;
            }
            continue;
        }
        handle_connection(fd, pipe, cfg, stats);
        ::close(fd);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// main
// ─────────────────────────────────────────────────────────────────────────────

static void usage()
{
    fprintf(stderr,
            "usage: face_api --model-dir DIR [--serve --port 8010] [options] [image ...]\n"
            "  --serve            run the HTTP API (default when no images given)\n"
            "  --host ADDR        bind address (default 0.0.0.0)\n"
            "  --port N           listen port (default 8010)\n"
            "  --workers N        request threads, one ncnn Net each (default 2)\n"
            "  --threads N        ncnn intra-op threads per Net (default 4)\n"
            "  --det-size N       detector input size (default 640)\n"
            "  --det-thresh F     detection score threshold (default 0.5)\n"
            "  --nms F            NMS IoU threshold (default 0.4)\n"
            "  --max-faces N      faces processed per request (default 5)\n"
            "  --api-key KEY      require X-API-Key header\n"
            "  --match-threshold F  /verify cosine threshold (default 0.30)\n"
            "  --no-liveness      disable /verify heuristic liveness\n"
            "  --min-face-ratio F  min face bbox area ratio for liveness (default 0.02)\n"
            "  --det-model NAME   detector param/bin basename (default det_10g)\n"
            "  --rec-model NAME   recognizer param/bin basename (default w600k_mbf)\n"
            "  --repeat N         CLI: run the pipeline N times per image\n");
}

int main(int argc, char** argv)
{
    PipelineConfig pcfg;
    ServerConfig scfg;
    bool serve = false;
    int repeat = 1;
    std::string dump_crop_dir;
    std::vector<std::string> images;
    std::string det_model = "det_10g", rec_model = "w600k_mbf";

    std::map<std::string, std::string> kv;
    for (int i = 1; i < argc; ++i) {
        const std::string a = argv[i];
        auto needs = [&](void) -> std::string {
            if (i + 1 >= argc) return "";
            return argv[++i];
        };
        if (a == "--serve") serve = true;
        else if (a == "--model-dir") pcfg.model_dir = needs();
        else if (a == "--host") scfg.host = needs();
        else if (a == "--port") scfg.port = atoi(needs().c_str());
        else if (a == "--workers") scfg.workers = std::max(1, atoi(needs().c_str()));
        else if (a == "--threads") pcfg.threads = std::max(1, atoi(needs().c_str()));
        else if (a == "--det-size") pcfg.det_size = std::max(64, atoi(needs().c_str()));
        else if (a == "--det-thresh") pcfg.det_thresh = (float)atof(needs().c_str());
        else if (a == "--nms") pcfg.nms = (float)atof(needs().c_str());
        else if (a == "--max-faces") pcfg.max_faces = std::max(1, atoi(needs().c_str()));
        else if (a == "--api-key") scfg.api_key = needs();
        else if (a == "--match-threshold") pcfg.match_threshold = (float)atof(needs().c_str());
        else if (a == "--no-liveness") pcfg.liveness_enabled = false;
        else if (a == "--min-face-ratio") pcfg.liveness.min_face_ratio = (float)atof(needs().c_str());
        else if (a == "--det-model") det_model = needs();
        else if (a == "--rec-model") rec_model = needs();
        else if (a == "--repeat") repeat = std::max(1, atoi(needs().c_str()));
        else if (a == "--dump-crop") dump_crop_dir = needs();
        else if (a == "--help" || a == "-h") { usage(); return 0; }
        else if (a.rfind("--", 0) == 0) { fprintf(stderr, "unknown option %s\n", a.c_str()); usage(); return 2; }
        else images.push_back(a);
    }

    if (pcfg.model_dir.empty()) {
        usage();
        return 2;
    }
    pcfg.det_param = det_model + ".param";
    pcfg.det_bin = det_model + ".bin";
    pcfg.rec_param = rec_model + ".param";
    pcfg.rec_bin = rec_model + ".bin";
    scfg.pipeline = pcfg;
    if (scfg.api_key.empty()) {
        if (const char* env = getenv("FACE_API_KEY")) scfg.api_key = env;
    }

    if (!images.empty()) {
        // CLI parity/benchmark mode
        Pipeline pipe;
        std::string err;
        if (!pipe.load(pcfg, err)) {
            fprintf(stderr, "[err] %s\n", err.c_str());
            return 1;
        }
        for (const std::string& path : images) {
            Image img;
            if (!load_image_file(path, img, err)) {
                fprintf(stderr, "[err] %s: %s\n", path.c_str(), err.c_str());
                continue;
            }
            std::vector<FaceResult> faces;
            double det_ms = 0, emb_ms = 0;
            std::vector<double> totals;
            for (int r = 0; r < repeat; ++r) {
                const double t = now_ms();
                if (!pipe.run(img, pcfg, faces, err, &det_ms, &emb_ms)) {
                    fprintf(stderr, "[err] %s: %s\n", path.c_str(), err.c_str());
                    break;
                }
                totals.push_back(now_ms() - t);
            }
            if (totals.size() > 1) {
                double sum = 0, mn = totals[1], mx = totals[1];
                for (size_t i = 1; i < totals.size(); ++i) {
                    sum += totals[i];
                    mn = std::min(mn, totals[i]);
                    mx = std::max(mx, totals[i]);
                }
                fprintf(stderr, "[bench] %s n=%zu avg=%.2fms min=%.2fms max=%.2fms (det %.2fms, emb %.2fms)\n",
                        path.c_str(), totals.size() - 1, sum / (double)(totals.size() - 1), mn, mx,
                        det_ms, emb_ms);
            }
            if (!dump_crop_dir.empty()) {
                for (size_t i = 0; i < faces.size(); ++i) {
                    ncnn::Mat crop;
                    if (!align_face(img, faces[i].box.kps, crop)) continue;
                    char name[512];
                    snprintf(name, sizeof(name), "%s/crop%zu.ppm", dump_crop_dir.c_str(), i);
                    FILE* fp = fopen(name, "wb");
                    if (!fp) continue;
                    fprintf(fp, "P6\n112 112\n255\n");
                    for (int y = 0; y < 112; ++y)
                        for (int x = 0; x < 112; ++x)
                            for (int c = 0; c < 3; ++c) {
                                float v = crop.channel(c)[y * 112 + x];
                                v = std::max(0.f, std::min(255.f, v));
                                fputc((int)(v + 0.5f), fp);
                            }
                    fclose(fp);
                }
            }
            printf("{\"id\":\"%s\",\"size\":[%d,%d],\"backend\":\"cpu\",\"num_faces\":%zu,\"faces\":%s}\n",
                   path.c_str(), img.w, img.h, faces.size(), faces_json(faces).c_str());
        }
        return 0;
    }

    // Server mode
    signal(SIGINT, on_signal);
    signal(SIGTERM, on_signal);
    signal(SIGPIPE, SIG_IGN);
    g_start_time = now_s();

    std::string err;
    std::vector<std::unique_ptr<Pipeline>> pipes;
    const int workers = scfg.workers;
    for (int i = 0; i < workers; ++i) {
        auto pipe = std::make_unique<Pipeline>();
        if (!pipe->load(pcfg, err)) {
            fprintf(stderr, "[err] worker %d: %s\n", i, err.c_str());
            return 1;
        }
        pipes.push_back(std::move(pipe));
    }

    const int listen_fd = make_listener(scfg.host, scfg.port, err);
    if (listen_fd < 0) {
        fprintf(stderr, "[err] %s\n", err.c_str());
        return 1;
    }
    log_line("[face-api] listening on %s:%d workers=%d threads=%d det_size=%d models=%s\n",
             scfg.host.c_str(), scfg.port, workers, pcfg.threads, pcfg.det_size,
             pcfg.model_dir.c_str());

    Stats stats;
    std::vector<std::thread> threads;
    for (int i = 0; i < workers; ++i) {
        threads.emplace_back(worker_loop, listen_fd, std::ref(*pipes[i]), std::cref(scfg),
                             std::ref(stats));
    }
    for (auto& t : threads) t.join();
    ::close(listen_fd);
    log_line("[face-api] stopped\n");
    return 0;
}
