# face_dx cross-language bindings (FDX C ABI v1)

Every binding targets the frozen contract in `../fdx_capi.h` and consumes
`../build/face_dx.dll` (deploy as one folder: `face_dx.dll` + `shaders/`).
All hosts are verified **bit-identical** against the C reference by
`../test_all_languages.py` (report: `../reports/cross_language.json`).

| language | file | mechanism | build step |
|---|---|---|---|
| C | `../fdx_runner.c` | `LoadLibraryA` + `GetProcAddress` (no header, no import lib) | `bash ../build_dll.sh` |
| Python | `python/fdx.py` | `ctypes.CDLL` | none — run directly |
| Node.js | `node/fdx_node.js` | koffi (prebuilt FFI, `npm install` in `node/`) | none — run directly |
| C# | `csharp/FdxSharp.cs` | P/Invoke (DllImport), built with the C# 5 compiler that ships with Windows | `%WINDIR%\Microsoft.NET\Framework64\v4.0.30319\csc.exe -nologo -optimize+ -out:fdx_cs.exe FdxSharp.cs` |
| Java | `java/FdxJava.java` | Foreign Function & Memory API (no JNI, no deps), Java 22+ | none — single-file source launcher |

## Library use (all return the same 512-d L2-normalized embedding)

**Python**
```python
from fdx import Fdx
fdx = Fdx("../../build/face_dx.dll")
model  = fdx.load_model("../../models/w600k_mbf.fvp")
engine = fdx.create_engine(fp16=True)
emb, ms = fdx.run(engine, model, rgb_bytes)     # 112*112*3 RGB8 bytes
fdx.free_engine(engine); fdx.free_model(model)
```

**Node**
```js
const { Fdx } = require('./fdx_node');
const fdx = new Fdx('../../build/face_dx.dll');
const model  = fdx.loadModel('../../models/w600k_mbf.fvp');
const engine = fdx.createEngine(true);
const { embedding, ms } = fdx.run(engine, model, rgbBytes);
fdx.freeEngine(engine); fdx.freeModel(model);
```

**C#** — `FdxSharp.cs` static entry points; see the runner `Main` for the
LoadLibrary-first pattern (DllImport("face_dx.dll") binds to the explicitly
loaded absolute-path module).

**Java**
```java
SymbolLookup lib = SymbolLookup.libraryLookup(Path.of(dll), arena);
// downcall handles per export; see FdxJava.bind()
```

## Shared conventions (kept identical across all five hosts)

- **ABI gate first**: every host refuses to run unless `fdx_abi_version() == 1`.
- **Input**: exactly `112*112*3` row-major RGB8 bytes per image; host converts
  to NCHW float32, `(x-127.5)/127.5`. The DLL never decodes images.
- **Errors**: exceptions (`FdxError`/`FdxException` style) wrap the raw
  int32 status; CLI modes expose `--probe-errors` printing
  `{"bad_model":-2,"null_input":-1}` — asserted identical in the suite.
- **JSONL output**: `{"id","backend","fp16","ms","embedding":[512]}` with
  8 significant digits, mirroring `fdx_runner.c` byte-for-byte.
- **Foreign-cwd rule**: hosts run from their own directory (or a scratch cwd);
  the DLL self-locates `shaders/` from its module path.

## The parity suite

```
cd face_dx && python test_all_languages.py          # 6 crops, fp16
```

Runs the exe + all five hosts on identical staged inputs, then gates:
max|diff| vs exe ≤ 1e-7 (8-digit text round-trip), min cosine ≥ 0.9999999,
probe codes `{-2,-1}` on every host. Last run: all zero diffs except C#/Java
at 1e-8 (formatting), cos 1.0000000 everywhere.
