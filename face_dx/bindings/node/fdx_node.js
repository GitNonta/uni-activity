// fdx_node.js — Node.js binding for face_dx.dll (FDX C ABI v1) via koffi.
//
// Library use:
//   const { Fdx } = require('./fdx_node');
//   const fdx = new Fdx('../../build/face_dx.dll');       // ABI-gates the DLL
//   const model  = fdx.loadModel('../../models/w600k_mbf.fvp');
//   const engine = fdx.createEngine(true);                // fp16
//   const { embedding, ms } = fdx.run(engine, model, rgbBytes); // 112*112*3 RGB8
//   fdx.freeEngine(engine); fdx.freeModel(model);
//
// Runner CLI (mirrors fdx_runner.c; used by the cross-language test):
//   node fdx_node.js --dll PATH --model FVP --list RAWS.TXT --out OUT.JSONL
//                    [--fp16] [--probe-errors]
'use strict';

const koffi = require('koffi');
const fs = require('fs');
const path = require('path');

const FDX_ABI_VERSION = 1;
const FDX_INPUT_FLOATS = 37632;   // 3*112*112 NCHW RGB, (x-127.5)/127.5
const FDX_EMBED_DIM = 512;
const ERROR_NAMES = {
    0: 'FDX_OK', '-1': 'FDX_ERR_INVALID_ARG', '-2': 'FDX_ERR_MODEL',
    '-3': 'FDX_ERR_GPU_INIT', '-4': 'FDX_ERR_RUN', '-5': 'FDX_ERR_DEVICE_LOST',
};

// Opaque handle types (the whole point of the C ABI) + named pointer types
// so the proto DSL can reference them and _Out_ has a known-size pointee.
const fdx_model_t = koffi.opaque('fdx_model_t');
const fdx_engine_t = koffi.opaque('fdx_engine_t');
const ModelP = koffi.pointer('fdx_model_p', fdx_model_t);
const EngineP = koffi.pointer('fdx_engine_p', fdx_engine_t);

class FdxError extends Error {
    constructor(code, detail) {
        const name = ERROR_NAMES[String(code)] || `FDX_ERR_${code}`;
        super(`${name} (${code}): ${detail}`);
        this.code = code;
    }
}

// 112*112*3 row-major RGB8 -> NCHW float32 ((x-127.5)/127.5)
function rgb8ToInput(rgb) {
    if (rgb.length !== 112 * 112 * 3)
        throw new Error(`expected ${112 * 112 * 3} RGB bytes, got ${rgb.length}`);
    const input = new Float32Array(FDX_INPUT_FLOATS);
    let i = 0;
    for (let c = 0; c < 3; c++)
        for (let y = 0; y < 112; y++)
            for (let x = 0; x < 112; x++, i++)
                input[i] = (rgb[(y * 112 + x) * 3 + c] - 127.5) / 127.5;
    return input;
}

class Fdx {
    constructor(dllPath) {
        this.lib = koffi.load(path.resolve(dllPath));
        const L = this.lib;
        this._abi = L.func('fdx_abi_version', 'int32_t', []);
        this._gpus = L.func('fdx_gpu_count', 'int32_t', []);
        this._modelLoad = L.func(
            'int32_t fdx_model_load(const char *path, _Out_ fdx_model_p *model, char *err, int32_t cap)');
        this._modelFree = L.func('void fdx_model_free(fdx_model_p model)');
        this._engineCreate = L.func(
            'int32_t fdx_engine_create(int32_t fp16, int32_t gpu, _Out_ fdx_engine_p *engine, char *err, int32_t cap)');
        this._engineFree = L.func('void fdx_engine_free(fdx_engine_p engine)');
        this._run = L.func(
            'int32_t fdx_engine_run(fdx_engine_p engine, fdx_model_p model, const float *input, float *out512, double *out_ms)');

        const v = this._abi();
        if (v !== FDX_ABI_VERSION)
            throw new FdxError(-1, `ABI version mismatch: dll=${v} host=${FDX_ABI_VERSION}`);
    }

    gpuCount() { return this._gpus(); }

    loadModel(fvpPath) {
        const h = [null];
        const err = Buffer.alloc(256);
        const rc = this._modelLoad(path.resolve(fvpPath), h, err, 256);
        if (rc !== 0) {
            const z = err.indexOf(0);
            throw new FdxError(rc, z >= 0 ? err.toString('utf8', 0, z) : '');
        }
        return h[0];
    }

    freeModel(h) { this._modelFree(h); }

    createEngine(fp16 = true, gpuIndex = -1) {
        const h = [null];
        const err = Buffer.alloc(256);
        const rc = this._engineCreate(fp16 ? 1 : 0, gpuIndex, h, err, 256);
        if (rc !== 0) {
            const z = err.indexOf(0);
            throw new FdxError(rc, z >= 0 ? err.toString('utf8', 0, z) : '');
        }
        return h[0];
    }

    freeEngine(h) { this._engineFree(h); }

    // rgb: Buffer/Uint8Array of exactly 112*112*3 RGB8 bytes.
    // Returns { embedding: Float32Array(512), ms: number }.
    run(engine, model, rgb) {
        const input = rgb8ToInput(rgb);
        const out512 = new Float32Array(FDX_EMBED_DIM);
        const ms = new Float64Array(1);
        const rc = this._run(engine, model, input, out512, ms);
        if (rc !== 0) throw new FdxError(rc, 'fdx_engine_run failed');
        return { embedding: out512, ms: ms[0] };
    }

    // Contract checks: bad model -> -2, NULL input -> -1.
    probeErrors() {
        const err = Buffer.alloc(256);
        const badModel = this._modelLoad('definitely_missing.fvp', [null], err, 256);
        const nullInput = this._run(null, null, new Float32Array(FDX_INPUT_FLOATS),
            new Float32Array(FDX_EMBED_DIM), new Float64Array(1));
        return { bad_model: badModel, null_input: nullInput };
    }
}

function main() {
    const args = process.argv.slice(2);
    const opt = {};
    for (let i = 0; i < args.length; i++) {
        switch (args[i]) {
            case '--dll': opt.dll = args[++i]; break;
            case '--model': opt.model = args[++i]; break;
            case '--list': opt.list = args[++i]; break;
            case '--out': opt.out = args[++i]; break;
            case '--fp16': opt.fp16 = true; break;
            case '--probe-errors': opt.probe = true; break;
            default: console.error('unknown arg: ' + args[i]); process.exit(2);
        }
    }
    if (!opt.dll) {
        console.error('usage: node fdx_node.js --dll PATH --model FVP --list RAWS.TXT ' +
            '--out OUT.JSONL [--fp16] [--probe-errors]');
        process.exit(2);
    }

    const fdx = new Fdx(opt.dll);
    if (opt.probe) {
        console.log(JSON.stringify(fdx.probeErrors()));
        return;
    }
    if (!opt.model || !opt.list || !opt.out) {
        console.error('--model, --list and --out are required without --probe-errors');
        process.exit(2);
    }

    const model = fdx.loadModel(opt.model);
    const engine = fdx.createEngine(opt.fp16);
    const backend = opt.fp16 ? 'dll-dx16' : 'dll-dx';
    let ok = 0, failed = 0, msTotal = 0;
    const t0 = Date.now();
    try {
        const lines = fs.readFileSync(opt.list, 'utf8').split(/\r?\n/);
        const out = fs.openSync(opt.out, 'w');
        try {
            for (const line of lines) {
                const p = line.trim();
                if (!p) continue;
                try {
                    const rgb = fs.readFileSync(p);
                    const { embedding, ms } = fdx.run(engine, model, rgb);
                    ok++; msTotal += ms;
                    const emb = [];
                    for (let i = 0; i < FDX_EMBED_DIM; i++)
                        emb.push(Number(embedding[i].toPrecision(8)));
                    fs.writeSync(out, JSON.stringify({
                        id: p.replace(/\\/g, '/'),
                        backend, fp16: !!opt.fp16,
                        ms: Number(ms.toFixed(2)),
                        embedding: emb,
                    }) + '\n');
                } catch (e) {
                    console.error(`[node] ${p}: ${e.message}`);
                    failed++;
                }
            }
        } finally {
            fs.closeSync(out);
        }
    } finally {
        fdx.freeEngine(engine);
        fdx.freeModel(model);
    }
    const wall = (Date.now() - t0) / 1000;
    console.error(`[summary] ok=${ok} failed=${failed} ms_total=${msTotal.toFixed(1)} ` +
        `ms_avg=${ok ? (msTotal / ok).toFixed(2) : '0.00'} wall_s=${wall.toFixed(2)}`);
    process.exit(failed === 0 ? 0 : 1);
}

module.exports = { Fdx, FdxError, rgb8ToInput, FDX_ABI_VERSION, FDX_INPUT_FLOATS, FDX_EMBED_DIM };

if (require.main === module) main();
