// fdx_capi.cpp — implementation of the stable C ABI (fdx_capi.h) as a thin
// wrapper over fdx::Model / fdx::Engine (dx_engine.cpp). Built by
// build_dll.sh into build/face_dx.dll.
//
// Design notes:
//   - No C++ types, exceptions, or allocation ownership cross the ABI: hosts
//     see opaque handles, int32 status codes, and caller-owned buffers.
//   - dx_engine.cpp resolves kernel sources as "shaders/<name>" relative to
//     the process CWD — correct for the CLI (run from face_dx/) but wrong for
//     a loaded DLL whose host may run anywhere. fdx_engine_create therefore
//     switches the CWD to the DLL's own directory for the duration of engine
//     creation and restores it before returning (RAII guard keeps this
//     exception-safe).
#define FDX_BUILD_DLL
#include "fdx_capi.h"

#include "dx_engine.h"

#include <windows.h>
#include <cstring>   // strncpy (fdx_engine_describe)
#include <dxgi.h>    // vendored (vendor/) — fdx_gpu_count adapter enumeration
#include <direct.h>  // _wchdir

#include <cstring>
#include <new>
#include <string>
#include <vector>

struct fdx_model  { fdx::Model m; };
struct fdx_engine { fdx::Engine e; };

static void set_err(char* err_buf, int32_t err_cap, const std::string& msg)
{
    if (!err_buf || err_cap <= 0) return;
    const size_t n = msg.size() < (size_t)err_cap - 1 ? msg.size() : (size_t)err_cap - 1;
    if (n) memcpy(err_buf, msg.data(), n);
    err_buf[n] = 0;
}

// ---------------------------------------------------------------------------
// CWD guard: switch to the DLL's own directory while kernels compile, restore
// on destruction (all exit paths).
// ---------------------------------------------------------------------------
struct DirGuard {
    std::wstring prev;
    bool changed = false;

    ~DirGuard()
    {
        if (changed) _wchdir(prev.c_str());
    }

    bool to_dll_dir(char* err_buf, int32_t err_cap)
    {
        HMODULE hm = nullptr;
        // FROM_ADDRESS with an address inside this module -> the DLL itself,
        // no matter which host process loaded it or from where.
        if (!GetModuleHandleExW(GET_MODULE_HANDLE_EX_FLAG_FROM_ADDRESS |
                                    GET_MODULE_HANDLE_EX_FLAG_UNCHANGED_REFCOUNT,
                                (LPCWSTR)(const void*)&set_err, &hm)) {
            set_err(err_buf, err_cap, "cannot resolve DLL module handle");
            return false;
        }
        wchar_t wpath[32768];
        const DWORD n = GetModuleFileNameW(hm, wpath,
                                           (DWORD)(sizeof(wpath) / sizeof(wpath[0])));
        if (n == 0 || n >= sizeof(wpath) / sizeof(wpath[0])) {
            set_err(err_buf, err_cap, "cannot resolve DLL path");
            return false;
        }
        std::wstring dir(wpath, n);
        const size_t slash = dir.find_last_of(L"\\/");
        dir = (slash == std::wstring::npos) ? L"." : dir.substr(0, slash);

        // remember the host's CWD so the destructor can restore it
        const DWORD need = GetCurrentDirectoryW(0, nullptr);
        if (need > 0) {
            prev.resize(need);
            GetCurrentDirectoryW(need, &prev[0]);
        }
        if (_wchdir(dir.c_str()) != 0) {
            set_err(err_buf, err_cap, "cannot enter the DLL directory");
            return false;
        }
        changed = true;
        return true;
    }
};

// ---------------------------------------------------------------------------
// lifecycle
// ---------------------------------------------------------------------------
FDX_API int32_t fdx_abi_version(void)
{
    return FDX_ABI_VERSION;
}

FDX_API int32_t fdx_gpu_count(void)
{
    IDXGIFactory1* fac = nullptr;
    if (FAILED(CreateDXGIFactory1(__uuidof(IDXGIFactory1), (void**)&fac))) return 0;
    int32_t n = 0;
    IDXGIAdapter* ad = nullptr;
    while (n < 16 && fac->EnumAdapters((UINT)n, &ad) != DXGI_ERROR_NOT_FOUND) {
        if (ad) ad->Release();
        n++;
    }
    fac->Release();
    return n;
}

FDX_API int32_t fdx_model_load(const char* fvp_path,
                               fdx_model** out_model,
                               char* err_buf, int32_t err_cap)
{
    if (!fvp_path || !out_model) {
        set_err(err_buf, err_cap, "null argument");
        return FDX_ERR_INVALID_ARG;
    }
    *out_model = nullptr;

    fdx::Model m;
    std::string serr;
    try {
        if (!fdx::load_model(fvp_path, m, &serr)) {
            set_err(err_buf, err_cap, serr.empty() ? "model load failed" : serr);
            return FDX_ERR_MODEL;
        }
    } catch (const std::bad_alloc&) {
        set_err(err_buf, err_cap, "out of memory during model load");
        return FDX_ERR_MODEL;
    } catch (...) {
        set_err(err_buf, err_cap, "unexpected exception during model load");
        return FDX_ERR_MODEL;
    }

    fdx_model* h = new (std::nothrow) fdx_model();
    if (!h) {
        set_err(err_buf, err_cap, "out of memory");
        return FDX_ERR_INVALID_ARG;
    }
    h->m = std::move(m);
    *out_model = h;
    return FDX_OK;
}

FDX_API void fdx_model_free(fdx_model* model)
{
    delete model;
}

FDX_API int32_t fdx_engine_create(int32_t fp16, int32_t gpu_index,
                                  fdx_engine** out_engine,
                                  char* err_buf, int32_t err_cap)
{
    if (!out_engine) return FDX_ERR_INVALID_ARG;
    *out_engine = nullptr;

    fdx_engine* h = new (std::nothrow) fdx_engine();
    if (!h) {
        set_err(err_buf, err_cap, "out of memory");
        return FDX_ERR_INVALID_ARG;
    }

    DirGuard dg;
    std::string serr;
    bool ok = false;
    if (dg.to_dll_dir(err_buf, err_cap)) {
        try {
            ok = h->e.init(fp16 != 0, (int)gpu_index, &serr);
        } catch (const std::bad_alloc&) {
            serr = "out of memory during engine init";
        } catch (...) {
            serr = "unexpected exception during engine init";
        }
    }
    if (!ok) {
        delete h;
        if (!err_buf || !*err_buf)
            set_err(err_buf, err_cap, serr.empty() ? "engine init failed" : serr);
        return FDX_ERR_GPU_INIT;
    }
    *out_engine = h;
    return FDX_OK;
}

FDX_API void fdx_engine_free(fdx_engine* engine)
{
    delete engine;
}

FDX_API int32_t fdx_engine_describe(const fdx_engine* engine,
                                    char* name_buf, int32_t name_cap,
                                    int32_t* is_warp,
                                    unsigned long long* luid)
{
    if (!engine) return FDX_ERR_INVALID_ARG;
    if (name_buf && name_cap > 0) {
        strncpy(name_buf, engine->e.adapter_name(), (size_t)name_cap - 1);
        name_buf[name_cap - 1] = 0;
    }
    if (is_warp) *is_warp = engine->e.adapter_is_warp() ? 1 : 0;
    if (luid) *luid = engine->e.adapter_luid();
    return FDX_OK;
}

// ---------------------------------------------------------------------------
// inference
// ---------------------------------------------------------------------------
FDX_API int32_t fdx_engine_run(fdx_engine* engine,
                               const fdx_model* model,
                               const float* input,
                               float* out512,
                               double* out_ms)
{
    if (!engine || !model || !input || !out512) return FDX_ERR_INVALID_ARG;
    try {
        if (engine->e.run(model->m, input, out512, out_ms)) return FDX_OK;
        // distinguish GPU device removal / missing device init from a
        // generic in-run failure, so hosts can pick the right recovery
        const int le = engine->e.last_error();
        return le == FDX_ERR_DEVICE_LOST ? FDX_ERR_DEVICE_LOST
             : le == FDX_ERR_GPU_INIT   ? FDX_ERR_GPU_INIT
             : FDX_ERR_RUN;
    } catch (const std::bad_alloc&) {
        return FDX_ERR_RUN;
    } catch (...) {
        return FDX_ERR_RUN;
    }
}

/* Recover a lost device (FDX_ERR_DEVICE_LOST) or switch adapters in place.
 * Tears down every device-bound resource and creates a new D3D11 device on
 * the same engine handle (gpu_index semantics as fdx_engine_create).
 * Model handles stay valid; tensor/weight buffers are re-created lazily on
 * the next fdx_engine_run. */
FDX_API int32_t fdx_engine_reinit(fdx_engine* engine,
                                  int32_t fp16, int32_t gpu_index,
                                  char* err_buf, int32_t err_cap)
{
    if (!engine) return FDX_ERR_INVALID_ARG;
    DirGuard dg;
    std::string serr;
    bool ok = false;
    if (dg.to_dll_dir(err_buf, err_cap)) {
        try {
            ok = engine->e.reinit(fp16 != 0, (int)gpu_index, &serr);
        } catch (const std::bad_alloc&) {
            serr = "out of memory during engine reinit";
        } catch (...) {
            serr = "unexpected exception during engine reinit";
        }
    }
    if (!ok) {
        if (!err_buf || !*err_buf)
            set_err(err_buf, err_cap, serr.empty() ? "engine reinit failed" : serr);
        return FDX_ERR_GPU_INIT;
    }
    return FDX_OK;
}
