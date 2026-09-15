// FdxSharp.cs — zero-dependency C# binding for face_dx.dll (FDX C ABI v1)
// using plain P/Invoke. Written against C# 5 so the .NET Framework compiler
// that ships with Windows can build it with no SDK installed:
//
//   %WINDIR%\Microsoft.NET\Framework64\v4.0.30319\csc.exe /nologo /optimize+ /out:fdx_cs.exe FdxSharp.cs
//
// Run (any cwd, absolute paths; mirrors fdx_runner.c):
//   fdx_cs.exe --dll PATH --model FVP --list RAWS.TXT --out OUT.JSONL [--fp16] [--probe-errors]
using System;
using System.Collections.Generic;
using System.Globalization;
using System.IO;
using System.Runtime.InteropServices;
using System.Text;

public static class FdxSharp
{
    const int FDX_ABI_VERSION = 1;
    const int INPUT_FLOATS = 37632;   // 3*112*112 NCHW RGB, (x-127.5)/127.5
    const int EMBED_DIM = 512;

    // LoadLibraryA with the absolute path first, so the DllImport("face_dx.dll")
    // entry points below bind against exactly that module (host CWD irrelevant).
    [DllImport("kernel32.dll", SetLastError = true, CharSet = CharSet.Ansi)]
    static extern IntPtr LoadLibraryA(string lpFileName);

    [DllImport("face_dx.dll", EntryPoint = "fdx_abi_version", CallingConvention = CallingConvention.Cdecl)]
    static extern int fdx_abi_version();

    [DllImport("face_dx.dll", EntryPoint = "fdx_gpu_count", CallingConvention = CallingConvention.Cdecl)]
    static extern int fdx_gpu_count();

    [DllImport("face_dx.dll", EntryPoint = "fdx_model_load", CallingConvention = CallingConvention.Cdecl, CharSet = CharSet.Ansi)]
    static extern int fdx_model_load(string fvpPath, out IntPtr model, byte[] errBuf, int errCap);

    [DllImport("face_dx.dll", EntryPoint = "fdx_model_free", CallingConvention = CallingConvention.Cdecl)]
    static extern void fdx_model_free(IntPtr model);

    [DllImport("face_dx.dll", EntryPoint = "fdx_engine_create", CallingConvention = CallingConvention.Cdecl)]
    static extern int fdx_engine_create(int fp16, int gpuIndex, out IntPtr engine, byte[] errBuf, int errCap);

    [DllImport("face_dx.dll", EntryPoint = "fdx_engine_free", CallingConvention = CallingConvention.Cdecl)]
    static extern void fdx_engine_free(IntPtr engine);

    [DllImport("face_dx.dll", EntryPoint = "fdx_engine_run", CallingConvention = CallingConvention.Cdecl)]
    static extern int fdx_engine_run(IntPtr engine, IntPtr model, float[] input, float[] out512, out double outMs);

    static string ErrString(byte[] buf)
    {
        int n = Array.IndexOf(buf, (byte)0);
        if (n < 0) n = buf.Length;
        return Encoding.UTF8.GetString(buf, 0, n);
    }

    static IntPtr LoadDll(string path)
    {
        string abs = Path.GetFullPath(path);
        if (LoadLibraryA(abs) == IntPtr.Zero)
            throw new Exception("LoadLibraryA failed: " + abs);
        return IntPtr.Zero;
    }

    static IntPtr LoadModel(string fvpPath)
    {
        IntPtr model;
        byte[] err = new byte[256];
        int rc = fdx_model_load(Path.GetFullPath(fvpPath), out model, err, err.Length);
        if (rc != 0) throw new Exception("fdx_model_load -> " + rc + ": " + ErrString(err));
        return model;
    }

    static IntPtr CreateEngine(bool fp16)
    {
        IntPtr engine;
        byte[] err = new byte[256];
        int rc = fdx_engine_create(fp16 ? 1 : 0, -1, out engine, err, err.Length);
        if (rc != 0) throw new Exception("fdx_engine_create -> " + rc + ": " + ErrString(err));
        return engine;
    }

    // 112*112*3 row-major RGB8 -> NCHW float32 ((x-127.5)/127.5)
    static float[] Rgb8ToInput(byte[] rgb)
    {
        if (rgb.Length != 112 * 112 * 3)
            throw new Exception("expected " + (112 * 112 * 3) + " RGB bytes, got " + rgb.Length);
        float[] input = new float[INPUT_FLOATS];
        int i = 0;
        for (int c = 0; c < 3; c++)
            for (int y = 0; y < 112; y++)
                for (int x = 0; x < 112; x++, i++)
                    input[i] = (rgb[(y * 112 + x) * 3 + c] - 127.5f) / 127.5f;
        return input;
    }

    static Dictionary<string, int> ProbeErrors()
    {
        var res = new Dictionary<string, int>();
        IntPtr m; byte[] err = new byte[256];
        res["bad_model"] = fdx_model_load("definitely_missing.fvp", out m, err, err.Length);
        double ms;
        float[] out512 = new float[EMBED_DIM];
        res["null_input"] = fdx_engine_run(IntPtr.Zero, IntPtr.Zero, null, out512, out ms);
        return res;
    }

    static int Main(string[] args)
    {
        string dll = null, model = null, list = null, outFile = null;
        bool fp16 = false, probe = false;
        for (int i = 0; i < args.Length; i++)
        {
            string a = args[i];
            if (a == "--dll") dll = args[++i];
            else if (a == "--model") model = args[++i];
            else if (a == "--list") list = args[++i];
            else if (a == "--out") outFile = args[++i];
            else if (a == "--fp16") fp16 = true;
            else if (a == "--probe-errors") probe = true;
            else { Console.Error.WriteLine("unknown arg: " + a); return 2; }
        }
        if (dll == null)
        {
            Console.Error.WriteLine("usage: fdx_cs --dll PATH --model FVP --list RAWS.TXT --out OUT.JSONL [--fp16] [--probe-errors]");
            return 2;
        }

        try
        {
            LoadDll(dll);
            int abi = fdx_abi_version();
            if (abi != FDX_ABI_VERSION)
                throw new Exception("ABI version mismatch: dll=" + abi + " host=" + FDX_ABI_VERSION);

            if (probe)
            {
                var res = ProbeErrors();
                var sb = new StringBuilder("{");
                bool first = true;
                foreach (var kv in res)
                {
                    if (!first) sb.Append(",");
                    sb.Append('"').Append(kv.Key).Append("\":").Append(kv.Value);
                    first = false;
                }
                sb.Append("}");
                Console.Out.WriteLine(sb.ToString());
                return 0;
            }

            if (model == null || list == null || outFile == null)
            {
                Console.Error.WriteLine("--model, --list and --out are required without --probe-errors");
                return 2;
            }

            IntPtr m = LoadModel(model);
            IntPtr eng = CreateEngine(fp16);
            string backend = fp16 ? "dll-dx16" : "dll-dx";
            float[] input = new float[INPUT_FLOATS];
            float[] out512 = new float[EMBED_DIM];
            int ok = 0, failed = 0;
            double msTotal = 0.0;
            DateTime t0 = DateTime.UtcNow;
            try
            {
                using (var reader = new StreamReader(list))
                using (var writer = new StreamWriter(outFile, false, new UTF8Encoding(false)))
                {
                    string line;
                    while ((line = reader.ReadLine()) != null)
                    {
                        line = line.Trim();
                        if (line.Length == 0) continue;
                        byte[] rgb;
                        try { rgb = File.ReadAllBytes(line); }
                        catch (Exception e) { Console.Error.WriteLine("[cs] " + line + ": " + e.Message); failed++; continue; }

                        double ms;
                        Rgb8ToInput(rgb); // validate size first
                        try
                        {
                            input = Rgb8ToInput(rgb);
                            int rc = fdx_engine_run(eng, m, input, out512, out ms);
                            if (rc != 0) throw new Exception("fdx_engine_run -> " + rc);
                        }
                        catch (Exception e) { Console.Error.WriteLine("[cs] " + line + ": " + e.Message); failed++; continue; }

                        ok++; msTotal += ms;
                        var sb = new StringBuilder(4096);
                        sb.Append("{\"id\":\"").Append(line.Replace('\\', '/'))
                          .Append("\",\"backend\":\"").Append(backend)
                          .Append("\",\"fp16\":").Append(fp16 ? "true" : "false")
                          .Append(",\"ms\":").Append(ms.ToString("F2", CultureInfo.InvariantCulture))
                          .Append(",\"embedding\":[");
                        for (int i = 0; i < EMBED_DIM; i++)
                        {
                            if (i > 0) sb.Append(',');
                            sb.Append(out512[i].ToString("G8", CultureInfo.InvariantCulture));
                        }
                        sb.Append("]}\n");
                        writer.Write(sb.ToString());
                        writer.Flush();
                    }
                }
            }
            finally
            {
                fdx_engine_free(eng);
                fdx_model_free(m);
            }
            double wall = (DateTime.UtcNow - t0).TotalSeconds;
            double avg = ok > 0 ? msTotal / ok : 0.0;
            Console.Error.WriteLine(string.Format(CultureInfo.InvariantCulture,
                "[summary] ok={0} failed={1} ms_total={2:F1} ms_avg={3:F2} wall_s={4:F2}",
                ok, failed, msTotal, avg, wall));
            return failed == 0 ? 0 : 1;
        }
        catch (Exception e)
        {
            Console.Error.WriteLine("[cs] fatal: " + e.Message);
            return 1;
        }
    }
}
