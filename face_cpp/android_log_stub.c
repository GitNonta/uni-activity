/*
 * android_log_stub.c — minimal liblog + libandroid stubs for Termux.
 *
 * The NDK-built libncnn.a references __android_log_* (liblog) and
 * AAssetManager_* (libandroid), neither of which Termux ships. These stubs
 * forward logging to stderr and make the AAsset APIs no-ops (unused here).
 */
#include <stdarg.h>
#include <stdio.h>

#ifdef __cplusplus
extern "C" {
#endif

int __android_log_print(int prio, const char* tag, const char* fmt, ...)
{
    va_list ap;
    va_start(ap, fmt);
    fprintf(stderr, "[%s] ", tag ? tag : "android");
    vfprintf(stderr, fmt, ap);
    fprintf(stderr, "\n");
    va_end(ap);
    return 0;
}

int __android_log_vprint(int prio, const char* tag, const char* fmt, va_list ap)
{
    fprintf(stderr, "[%s] ", tag ? tag : "android");
    vfprintf(stderr, fmt, ap);
    fprintf(stderr, "\n");
    return 0;
}

int __android_log_write(int prio, const char* tag, const char* msg)
{
    fprintf(stderr, "[%s] %s\n", tag ? tag : "android", msg);
    return 0;
}

int __android_log_buf_write(int bufID, int prio, const char* tag, const char* msg)
{
    return __android_log_write(prio, tag, msg);
}

void __android_log_assert(const char* cond, const char* tag, const char* fmt, ...)
{
    va_list ap;
    va_start(ap, fmt);
    fprintf(stderr, "[%s] ASSERT(%s): ", tag ? tag : "android", cond ? cond : "?");
    vfprintf(stderr, fmt, ap);
    fprintf(stderr, "\n");
    va_end(ap);
}

/* ---- libandroid (asset manager) stubs: never used by face_extract ---- */
typedef struct AAssetManager AAssetManager;
typedef struct AAsset AAsset;

AAsset* AAssetManager_open(AAssetManager* mgr, const char* filename, int mode)
{
    (void)mgr; (void)filename; (void)mode;
    return 0;
}

int AAsset_read(AAsset* asset, void* buf, size_t count)
{
    (void)asset; (void)buf; (void)count;
    return 0;
}

off_t AAsset_getLength(AAsset* asset)
{
    (void)asset;
    return 0;
}

off64_t AAsset_getLength64(AAsset* asset)
{
    (void)asset;
    return 0;
}

void AAsset_close(AAsset* asset)
{
    (void)asset;
}

off_t AAsset_seek(AAsset* asset, off_t offset, int whence)
{
    (void)asset; (void)offset; (void)whence;
    return (off_t)-1;
}

const void* AAsset_getBuffer(AAsset* asset)
{
    (void)asset;
    return 0;
}

off64_t AAsset_getRemainingLength64(AAsset* asset)
{
    (void)asset;
    return 0;
}

AAssetManager* AAssetManager_fromJava(void* env, void* jassetManager)
{
    (void)env; (void)jassetManager;
    return 0;
}

#ifdef __cplusplus
}
#endif