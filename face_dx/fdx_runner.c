// fdx_runner.c — zero-dependency C host for face_dx.dll.
//
// Deliberately loads the DLL the way an unmanaged host would: LoadLibraryA +
// GetProcAddress only — no import library, no fdx_capi.h include, no C++
// runtime. Proves the ABI is consumable from a plain C build and that the
// DLL self-locates its kernels regardless of this process's CWD (run it from
// anywhere; kernels are found next to the DLL, not here).
//
// Input contract: a list file of raw RGB8 images (exactly 112*112*3 bytes,
// row-major RGB — the engine's own staging format), so the runner needs no
// image decoder at all.
//
// Usage:
//   face_dx_runner --dll PATH --model FVP --list FILE --out FILE [--fp16]
//
// Output: one JSON line per image, same schema as face_dx.exe
//         ({"id","backend","fp16","ms","embedding":[512]}), so the Python
//         driver can diff runner vs exe embeddings directly.
#include <windows.h>
#include <stdio.h>
#include <stdint.h>
#include <stdlib.h>
#include <string.h>

typedef struct fdx_model  fdx_model;
typedef struct fdx_engine fdx_engine;

typedef int32_t (*fn_abi_version)(void);
typedef int32_t (*fn_gpu_count)(void);
typedef int32_t (*fn_model_load)(const char*, fdx_model**, char*, int32_t);
typedef void    (*fn_model_free)(fdx_model*);
typedef int32_t (*fn_engine_create)(int32_t, int32_t, fdx_engine**, char*, int32_t);
typedef void    (*fn_engine_free)(fdx_engine*);
typedef int32_t (*fn_engine_run)(fdx_engine*, const fdx_model*,
                                 const float*, float*, double*);

#define INPUT_FLOATS 37632 /* 3*112*112 */
#define EMBED_DIM    512
#define RAW_BYTES    (112 * 112 * 3)

static void usage(void)
{
    fprintf(stderr,
            "usage: face_dx_runner --dll PATH --model FVP --list FILE --out FILE"
            " [--fp16]\n");
}

static int load_raw(const char* path, float* out)
{
    FILE* f = fopen(path, "rb");
    if (!f) {
        fprintf(stderr, "[runner] cannot read %s\n", path);
        return 0;
    }
    unsigned char px[RAW_BYTES];
    const size_t got = fread(px, 1, RAW_BYTES, f);
    fclose(f);
    if (got != RAW_BYTES) {
        fprintf(stderr, "[runner] %s: expected %d raw RGB bytes, got %zu\n",
                path, RAW_BYTES, got);
        return 0;
    }
    const int plane = 112 * 112;
    for (int c = 0; c < 3; c++)
        for (int p = 0; p < plane; p++)
            out[c * plane + p] = ((float)px[p * 3 + c] - 127.5f) * (1.0f / 127.5f);
    return 1;
}

int main(int argc, char** argv)
{
    const char* dll_path = NULL;
    const char* model_path = NULL;
    const char* list_path = NULL;
    const char* out_path = NULL;
    int fp16 = 0;

    for (int i = 1; i < argc; i++) {
        if (!strcmp(argv[i], "--dll") && i + 1 < argc) dll_path = argv[++i];
        else if (!strcmp(argv[i], "--model") && i + 1 < argc) model_path = argv[++i];
        else if (!strcmp(argv[i], "--list") && i + 1 < argc) list_path = argv[++i];
        else if (!strcmp(argv[i], "--out") && i + 1 < argc) out_path = argv[++i];
        else if (!strcmp(argv[i], "--fp16")) fp16 = 1;
        else { usage(); return 2; }
    }
    if (!dll_path || !model_path || !list_path || !out_path) {
        usage();
        return 2;
    }

    // ---- load the DLL dynamically (no import lib, no header) ----
    HMODULE dll = LoadLibraryA(dll_path);
    if (!dll) {
        fprintf(stderr, "[runner] LoadLibraryA(%s) failed (err %lu)\n",
                dll_path, (unsigned long)GetLastError());
        return 1;
    }
    fn_abi_version   p_abi   = (fn_abi_version)(void*)GetProcAddress(dll, "fdx_abi_version");
    fn_gpu_count     p_gpus  = (fn_gpu_count)(void*)GetProcAddress(dll, "fdx_gpu_count");
    fn_model_load    p_load  = (fn_model_load)(void*)GetProcAddress(dll, "fdx_model_load");
    fn_model_free    p_mfree = (fn_model_free)(void*)GetProcAddress(dll, "fdx_model_free");
    fn_engine_create p_ecre  = (fn_engine_create)(void*)GetProcAddress(dll, "fdx_engine_create");
    fn_engine_free   p_efree = (fn_engine_free)(void*)GetProcAddress(dll, "fdx_engine_free");
    fn_engine_run    p_run   = (fn_engine_run)(void*)GetProcAddress(dll, "fdx_engine_run");
    if (!p_abi || !p_gpus || !p_load || !p_mfree || !p_ecre || !p_efree || !p_run) {
        fprintf(stderr, "[runner] missing exports in %s\n", dll_path);
        return 1;
    }

    // ---- ABI version gate (contract: refuse to run on mismatch) ----
    const int32_t abi = p_abi();
    if (abi != 1) {
        fprintf(stderr, "[runner] ABI version mismatch: dll=%d host=1\n", abi);
        return 1;
    }
    fprintf(stderr, "[runner] ABI v%d, %d DXGI adapter(s)\n", abi, p_gpus());

    // ---- model ----
    fdx_model* model = NULL;
    char err[256];
    err[0] = 0;
    const int32_t mrc = p_load(model_path, &model, err, (int32_t)sizeof(err));
    if (mrc != 0) {
        fprintf(stderr, "[runner] fdx_model_load -> %d: %s\n", mrc, err);
        return 1;
    }

    // ---- engine ----
    fdx_engine* engine = NULL;
    err[0] = 0;
    const int32_t erc = p_ecre(fp16 ? 1 : 0, -1, &engine, err, (int32_t)sizeof(err));
    if (erc != 0) {
        fprintf(stderr, "[runner] fdx_engine_create -> %d: %s\n", erc, err);
        p_mfree(model);
        return 1;
    }

    // ---- image list ----
    FILE* lf = fopen(list_path, "rb");
    if (!lf) {
        fprintf(stderr, "[runner] cannot read list %s\n", list_path);
        p_efree(engine);
        p_mfree(model);
        return 1;
    }
    FILE* out = fopen(out_path, "wb");
    if (!out) {
        fprintf(stderr, "[runner] cannot open output %s\n", out_path);
        fclose(lf);
        p_efree(engine);
        p_mfree(model);
        return 1;
    }

    char line[4096];
    float input[INPUT_FLOATS];
    float emb[EMBED_DIM];
    int ok = 0, failed = 0;
    double ms_total = 0.0;

    while (fgets(line, sizeof(line), lf)) {
        size_t n = strlen(line);
        while (n && (line[n - 1] == '\n' || line[n - 1] == '\r' ||
                     line[n - 1] == ' ' || line[n - 1] == '\t'))
            line[--n] = 0;
        if (!n) continue;

        if (!load_raw(line, input)) { failed++; continue; }

        double ms = 0.0;
        const int32_t rrc = p_run(engine, model, input, emb, &ms);
        if (rrc != 0) {
            fprintf(stderr, "[runner] fdx_engine_run -> %d for %s\n", rrc, line);
            failed++;
            continue;
        }
        ok++;
        ms_total += ms;

        // JSON id with forward slashes (same convention as the exe)
        char id[4096];
        for (size_t i = 0; i <= n && i < sizeof(id); i++)
            id[i] = (line[i] == '\\') ? '/' : line[i];

        fprintf(out, "{\"id\":\"%s\",\"backend\":\"%s\",\"fp16\":%s,\"ms\":%.2f,\"embedding\":[",
                id, fp16 ? "dll-dx16" : "dll-dx", fp16 ? "true" : "false", ms);
        for (int i = 0; i < EMBED_DIM; i++) {
            if (i) fputc(',', out);
            fprintf(out, "%.8g", emb[i]);
        }
        fprintf(out, "]}\n");
        fflush(out);
    }
    fclose(lf);
    fclose(out);

    fprintf(stderr, "[summary] ok=%d failed=%d ms_total=%.1f ms_avg=%.2f\n",
            ok, failed, ms_total, ok ? ms_total / ok : 0.0);

    p_efree(engine);
    p_mfree(model);
    FreeLibrary(dll);
    return ok > 0 ? 0 : 1;
}
