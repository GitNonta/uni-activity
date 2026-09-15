import java.lang.foreign.Arena;
import java.lang.foreign.FunctionDescriptor;
import java.lang.foreign.Linker;
import java.lang.foreign.MemorySegment;
import java.lang.foreign.SymbolLookup;
import java.lang.foreign.ValueLayout;
import java.lang.invoke.MethodHandle;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.util.ArrayList;
import java.util.List;
import java.util.Locale;

/**
 * FdxJava — zero-dependency Java host for face_dx.dll (FDX C ABI v1).
 *
 * Uses the Foreign Function & Memory API (Java 22+, final in 25): no JNI, no
 * generated headers, no external dependencies — the DLL is loaded with
 * SymbolLookup.libraryLookup and every fdx_* export is a downcall handle.
 *
 * Run with the single-file source launcher from any cwd (absolute paths):
 *   java --enable-native-access=ALL-UNNAMED FdxJava.java \
 *        --dll PATH --model FVP --list RAWS.TXT --out OUT.JSONL [--fp16] [--probe-errors]
 */
public class FdxJava {

    static final int FDX_ABI_VERSION = 1;
    static final int INPUT_FLOATS = 37632;   // 3*112*112 NCHW RGB, (x-127.5)/127.5
    static final int EMBED_DIM = 512;

    static final Linker LINKER = Linker.nativeLinker();

    static MethodHandle hAbi, hGpus, hLoad, hFreeModel, hCreate, hFreeEngine, hRun;
    static Arena arena;

    public static void main(String[] args) throws Throwable {
        String dll = null, model = null, list = null, outFile = null;
        boolean fp16 = false, probe = false;
        for (int i = 0; i < args.length; i++) {
            switch (args[i]) {
                case "--dll" -> dll = args[++i];
                case "--model" -> model = args[++i];
                case "--list" -> list = args[++i];
                case "--out" -> outFile = args[++i];
                case "--fp16" -> fp16 = true;
                case "--probe-errors" -> probe = true;
                default -> {
                    System.err.println("unknown arg: " + args[i]);
                    System.exit(2);
                }
            }
        }
        if (dll == null) {
            System.err.println("usage: java FdxJava.java --dll PATH --model FVP "
                + "--list RAWS.TXT --out OUT.JSONL [--fp16] [--probe-errors]");
            System.exit(2);
        }

        arena = Arena.global();
        SymbolLookup lib = SymbolLookup.libraryLookup(Path.of(dll), arena);
        bind(lib);

        int abi = (int) hAbi.invoke();
        if (abi != FDX_ABI_VERSION) {
            throw new IllegalStateException("ABI version mismatch: dll=" + abi
                + " host=" + FDX_ABI_VERSION);
        }
        System.err.println("[java] ABI v" + abi + ", " + ((int) hGpus.invoke()) + " DXGI adapter(s)");

        if (probe) {
            String json = probeErrors();
            System.out.println(json);
            return;
        }
        if (model == null || list == null || outFile == null) {
            System.err.println("--model, --list and --out are required without --probe-errors");
            System.exit(2);
        }

        long t0 = System.nanoTime();
        MemorySegment modelSeg = loadModel(Paths.get(model).toAbsolutePath().toString());
        MemorySegment engine = createEngine(fp16);
        String backend = fp16 ? "dll-dx16" : "dll-dx";
        int ok = 0, failed = 0;
        double msTotal = 0.0;
        try {
            MemorySegment input = arena.allocate(ValueLayout.JAVA_FLOAT, INPUT_FLOATS);
            MemorySegment out512 = arena.allocate(ValueLayout.JAVA_FLOAT, EMBED_DIM);
            MemorySegment outMs = arena.allocate(ValueLayout.JAVA_DOUBLE);
            StringBuilder sb = new StringBuilder(4096);

            List<String> paths = Files.readAllLines(Path.of(list), StandardCharsets.UTF_8);
            var writer = Files.newBufferedWriter(Path.of(outFile), StandardCharsets.UTF_8);
            try {
                for (String line : paths) {
                    String p = line.trim();
                    if (p.isEmpty()) continue;
                    try {
                        byte[] rgb = Files.readAllBytes(Path.of(p));
                        if (rgb.length != 112 * 112 * 3) {
                            throw new IllegalArgumentException("expected " + (112 * 112 * 3)
                                + " RGB bytes, got " + rgb.length);
                        }
                        fillInput(rgb, input);
                        int rc = (int) hRun.invoke(engine, modelSeg, input, out512, outMs);
                        if (rc != 0) throw new RuntimeException("fdx_engine_run -> " + rc);

                        double ms = outMs.getAtIndex(ValueLayout.JAVA_DOUBLE, 0);
                        ok++; msTotal += ms;

                        sb.setLength(0);
                        sb.append("{\"id\":\"").append(p.replace('\\', '/'))
                          .append("\",\"backend\":\"").append(backend)
                          .append("\",\"fp16\":").append(fp16)
                          .append(",\"ms\":").append(String.format(Locale.ROOT, "%.2f", ms))
                          .append(",\"embedding\":[");
                        for (int j = 0; j < EMBED_DIM; j++) {
                            if (j > 0) sb.append(',');
                            sb.append(String.format(Locale.ROOT, "%.8g",
                                out512.getAtIndex(ValueLayout.JAVA_FLOAT, j)));
                        }
                        sb.append("]}\n");
                        writer.write(sb.toString());
                        writer.flush();
                    } catch (Exception e) {
                        System.err.println("[java] " + p + ": " + e.getMessage());
                        failed++;
                    }
                }
            } finally {
                writer.close();
            }
        } finally {
            hFreeEngine.invoke(engine);
            hFreeModel.invoke(modelSeg);
        }
        double wall = (System.nanoTime() - t0) / 1e9;
        double avg = ok > 0 ? msTotal / ok : 0.0;
        System.err.printf(Locale.ROOT,
            "[summary] ok=%d failed=%d ms_total=%.1f ms_avg=%.2f wall_s=%.2f%n",
            ok, failed, msTotal, avg, wall);
        System.exit(failed == 0 ? 0 : 1);
    }

    static void bind(SymbolLookup lib) {
        hAbi = LINKER.downcallHandle(lib.find("fdx_abi_version").orElseThrow(),
            FunctionDescriptor.of(ValueLayout.JAVA_INT));
        hGpus = LINKER.downcallHandle(lib.find("fdx_gpu_count").orElseThrow(),
            FunctionDescriptor.of(ValueLayout.JAVA_INT));
        hLoad = LINKER.downcallHandle(lib.find("fdx_model_load").orElseThrow(),
            FunctionDescriptor.of(ValueLayout.JAVA_INT,
                ValueLayout.ADDRESS, ValueLayout.ADDRESS, ValueLayout.ADDRESS, ValueLayout.JAVA_INT));
        hFreeModel = LINKER.downcallHandle(lib.find("fdx_model_free").orElseThrow(),
            FunctionDescriptor.ofVoid(ValueLayout.ADDRESS));
        hCreate = LINKER.downcallHandle(lib.find("fdx_engine_create").orElseThrow(),
            FunctionDescriptor.of(ValueLayout.JAVA_INT,
                ValueLayout.JAVA_INT, ValueLayout.JAVA_INT,
                ValueLayout.ADDRESS, ValueLayout.ADDRESS, ValueLayout.JAVA_INT));
        hFreeEngine = LINKER.downcallHandle(lib.find("fdx_engine_free").orElseThrow(),
            FunctionDescriptor.ofVoid(ValueLayout.ADDRESS));
        hRun = LINKER.downcallHandle(lib.find("fdx_engine_run").orElseThrow(),
            FunctionDescriptor.of(ValueLayout.JAVA_INT,
                ValueLayout.ADDRESS, ValueLayout.ADDRESS, ValueLayout.ADDRESS,
                ValueLayout.ADDRESS, ValueLayout.ADDRESS));
    }

    static MemorySegment cstr(String s) {
        return arena.allocateFrom(s, StandardCharsets.UTF_8);
    }

    static String errString(MemorySegment buf) {
        return buf.getString(0, StandardCharsets.UTF_8);
    }

    static MemorySegment loadModel(String fvpPath) throws Throwable {
        MemorySegment out = arena.allocate(ValueLayout.ADDRESS);
        MemorySegment err = arena.allocate(256);
        int rc = (int) hLoad.invoke(cstr(fvpPath), out, err, 256);
        if (rc != 0) throw new RuntimeException("fdx_model_load -> " + rc + ": " + errString(err));
        return out.get(ValueLayout.ADDRESS, 0);
    }

    static MemorySegment createEngine(boolean fp16) throws Throwable {
        MemorySegment out = arena.allocate(ValueLayout.ADDRESS);
        MemorySegment err = arena.allocate(256);
        int rc = (int) hCreate.invoke(fp16 ? 1 : 0, -1, out, err, 256);
        if (rc != 0) throw new RuntimeException("fdx_engine_create -> " + rc + ": " + errString(err));
        return out.get(ValueLayout.ADDRESS, 0);
    }

    // 112*112*3 row-major RGB8 -> NCHW float32 ((x-127.5)/127.5)
    static void fillInput(byte[] rgb, MemorySegment input) {
        int i = 0;
        for (int c = 0; c < 3; c++) {
            for (int y = 0; y < 112; y++) {
                for (int x = 0; x < 112; x++, i++) {
                    int idx = (y * 112 + x) * 3 + c;
                    input.setAtIndex(ValueLayout.JAVA_FLOAT, i,
                        ((rgb[idx] & 0xFF) - 127.5f) / 127.5f);  // Java bytes are SIGNED: mask to unsigned
                }
            }
        }
    }

    static String probeErrors() throws Throwable {
        MemorySegment err = arena.allocate(256);
        int badModel = (int) hLoad.invoke(cstr("definitely_missing.fvp"),
            arena.allocate(ValueLayout.ADDRESS), err, 256);
        int nullInput = (int) hRun.invoke(MemorySegment.NULL, MemorySegment.NULL,
            MemorySegment.NULL, arena.allocate(ValueLayout.JAVA_FLOAT, EMBED_DIM),
            MemorySegment.NULL);
        return "{\"bad_model\":" + badModel + ",\"null_input\":" + nullInput + "}";
    }
}
