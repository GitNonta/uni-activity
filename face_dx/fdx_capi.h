// fdx_capi.h — stable C ABI for the face_dx inference engine.
//
// This is the packaging contract for build/face_dx.dll (next session): a thin
// extern "C" wrapper over fdx::Model / fdx::Engine (dx_engine.h) so hosts in
// any language (C/C++/C#/Python ctypes) can run the 512-d extractor with zero
// third-party dependencies — the DLL links only d3d11.dll, dxgi.dll and the
// OS-shipped d3dcompiler_47.dll, exactly like the CLI exe today.
//
// ABI rules:
//   - C types only; opaque handles; no C++ objects, exceptions, or STL cross
//     the boundary. All failures are returned as negative int32 status codes;
//     human-readable detail goes into the caller's err_buf (optional).
//   - Version-gate: host calls fdx_abi_version() first and refuses to run on
//     a mismatch with FDX_ABI_VERSION it was compiled against.
#pragma once

#include <stdint.h>

#if defined(_WIN32) && defined(FDX_BUILD_DLL)
#  define FDX_API __declspec(dllexport)
#else
#  define FDX_API /* import: link the .lib, or GetProcAddress("fdx_*") */
#endif

#ifdef __cplusplus
extern "C" {
#endif

/* ── status codes ────────────────────────────────────────────────────────── */
enum {
    FDX_OK              =  0,
    FDX_ERR_INVALID_ARG = -1,   /* NULL handle/buffer, wrong sizes          */
    FDX_ERR_MODEL       = -2,   /* .fvp missing/corrupt/unsupported version */
    FDX_ERR_GPU_INIT    = -3,   /* D3D11 device or kernel setup failed      */
    FDX_ERR_RUN         = -4,   /* inference submit/wait failed             */
    FDX_ERR_DEVICE_LOST = -5    /* GPU device removed — recreate engine     */
};

/* ── contract constants ──────────────────────────────────────────────────── */
#define FDX_ABI_VERSION   1
#define FDX_INPUT_FLOATS  37632  /* 3*112*112 NCHW RGB, (x-127.5)/127.5      */
#define FDX_EMBED_DIM     512    /* L2-normalized embedding out              */

/* ── opaque handles ──────────────────────────────────────────────────────── */
typedef struct fdx_model  fdx_model;   /* parsed .fvp graph + tensor shapes */
typedef struct fdx_engine fdx_engine;  /* D3D11 device + compiled kernels   */

/* ── lifecycle ───────────────────────────────────────────────────────────── */

/* Returns FDX_ABI_VERSION of the loaded DLL. Call first; stop on mismatch. */
FDX_API int32_t fdx_abi_version(void);

/* Number of usable DXGI adapters, for choosing fdx_engine_create(gpu_index). */
FDX_API int32_t fdx_gpu_count(void);

/* Parse a .fvp model file. On FDX_OK, *out_model owns the graph.
 * err_buf/err_cap may be NULL/0; on failure they receive a NUL-terminated
 * reason (truncated to fit). */
FDX_API int32_t fdx_model_load(const char* fvp_path,
                               fdx_model** out_model,
                               char* err_buf, int32_t err_cap);

FDX_API void fdx_model_free(fdx_model* model);

/* Create the GPU engine. fp16 != 0 selects packed-fp16 weight storage with
 * fp32 accumulation (production); 0 selects pure fp32 (reference).
 * gpu_index: -1 = auto-pick, else adapter index from fdx_gpu_count(). */
FDX_API int32_t fdx_engine_create(int32_t fp16, int32_t gpu_index,
                                  fdx_engine** out_engine,
                                  char* err_buf, int32_t err_cap);

FDX_API void fdx_engine_free(fdx_engine* engine);

/* ── inference ───────────────────────────────────────────────────────────── */

/* Run one image. input must hold FDX_INPUT_FLOATS floats (NCHW RGB8, already
 * resized to 112x112 and normalized (x-127.5)/127.5 — the caller owns
 * decoding/resizing, matching the CLI's raw-RGB staging path). out512 must
 * hold FDX_EMBED_DIM floats and receives the L2-normalized embedding.
 * out_ms may be NULL; else receives wall ms including submit+wait.
 *
 * Thread-safety: do not call fdx_engine_run concurrently on the same engine
 * (single immediate context). One engine per thread is supported. */
FDX_API int32_t fdx_engine_run(fdx_engine* engine,
                               const fdx_model* model,
                               const float* input,
                               float* out512,
                               double* out_ms);

#ifdef __cplusplus
}  /* extern "C" */
#endif
