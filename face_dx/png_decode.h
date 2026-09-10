// png_decode.h — my own minimal PNG decoder (no third-party libraries).
// Supports 8-bit, non-interlaced images of color types 0/2/3/4/6 (gray, RGB,
// palette, gray+alpha, RGBA). Implements the full DEFLATE (RFC 1951) decode
// path: stored / fixed-Huffman / dynamic-Huffman blocks + LZ77 back-references,
// and all five PNG scanline filters. Output: packed RGB(A) bytes, 3 or 4
// channels.
#pragma once

#include <cstdint>
#include <cstdlib>
#include <cstring>
#include <cstdio>
#include <string>
#include <vector>

namespace fdxpng {

struct Image {
    int w = 0;
    int h = 0;
    int ch = 0;  // 3 or 4
    std::vector<uint8_t> px;  // w*h*ch, row-major RGB(A)
};

namespace {

// ---- bit reader over a byte span ----
struct BitReader {
    const uint8_t* p = nullptr;
    size_t n = 0;
    size_t pos = 0;   // byte index
    uint32_t bitbuf = 0;
    int bitcnt = 0;

    uint32_t peek(int nbits)
    {
        while (bitcnt < nbits && pos < n) {
            bitbuf |= (uint32_t)p[pos++] << bitcnt;
            bitcnt += 8;
        }
        return bitbuf & ((1u << nbits) - 1);
    }
    uint32_t read(int nbits)
    {
        const uint32_t v = peek(nbits);
        bitbuf >>= nbits;
        bitcnt -= nbits;
        return v;
    }
    bool eof() const { return bitcnt == 0 && pos >= n; }
};

// ---- canonical Huffman table (zlib-style decoder) ----
struct HuffTable {
    uint16_t counts[16] = {0};  // counts[len] = # symbols of that length
    uint16_t symbols[288];
    int max_len = 0;
    int n_sym = 0;

    // lens: code length per symbol; symbols sorted by (len, symbol) implicitly
    bool build(const uint8_t* lens, int nlens)
    {
        for (int i = 0; i < 16; i++) counts[i] = 0;
        for (int i = 0; i < nlens; i++) {
            if (lens[i] > 15) return false;
            counts[lens[i]]++;
        }
        max_len = 0;
        for (int l = 1; l < 16; l++)
            if (counts[l]) max_len = l;

        n_sym = 0;
        for (int l = 1; l <= max_len; l++) {
            for (int s = 0; s < nlens; s++) {
                if (lens[s] == l) symbols[n_sym++] = (uint16_t)s;
            }
        }
        return true;
    }

    // decode one symbol (canonical algorithm per the PNG spec / zlib inflate).
    // NOTE: code/first/index must be SIGNED ints — the check `code - count <
    // first` relies on signed wraparound (puff.c uses int).
    int decode(BitReader& br) const
    {
        int code = 0, first = 0, index = 0;
        for (int len = 1; len <= 15; len++) {
            code |= (int)br.read(1);
            const int count = (int)counts[len];
            if (code - count < first)
                return symbols[index + (code - first)];
            index += count;
            first += count;
            first <<= 1;
            code <<= 1;
        }
        return -1;  // invalid code
    }
};

// length / distance code tables (RFC 1951 fixed tables)
const uint16_t LEN_BASE[29] = {3,  4,  5,  6,  7,  8,  9,  10, 11, 13, 15, 17,
                               19, 23, 27, 31, 35, 43, 51, 59, 67, 83, 99, 115,
                               131, 163, 195, 227, 258};
const uint8_t LEN_EXTRA[29] = {0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1,
                               2, 2, 2, 2, 3, 3, 3, 3, 4, 4, 4, 4,
                               5, 5, 5, 5, 0};
const uint16_t DIST_BASE[30] = {1,    2,    3,    4,    5,    7,     9,     13,
                                17,   25,   33,   49,   65,   97,    129,   193,
                                257,  385,  513,  769,  1025, 1537,  2049,  3073,
                                4097, 6145, 8193, 12289, 16385, 24577};
const uint8_t DIST_EXTRA[30] = {0, 0, 0, 0, 1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6,
                                6, 7, 7, 8, 8, 9, 9, 10, 10, 11, 11, 12, 12, 13, 13};
const uint8_t CLEN_ORDER[19] = {16, 17, 18, 0, 8, 7, 9, 6, 10, 5,
                                11, 4, 12, 3, 13, 2, 14, 1, 15};

// build a Huffman table from a "code length code length" stream
bool build_from_stream(BitReader& br, HuffTable& lt, HuffTable& dt, int& hlit, int& hdist)
{
    // dynamic block header
    hlit = (int)br.read(5) + 257;   // literal/length codes
    hdist = (int)br.read(5) + 1;    // distance codes
    const int hclen = (int)br.read(4) + 4;

    uint8_t cl_lens[19] = {0};
    for (int i = 0; i < hclen; i++) cl_lens[CLEN_ORDER[i]] = (uint8_t)br.read(3);

    HuffTable cl;
    if (!cl.build(cl_lens, 19)) return false;

    // decode the hlit+hdist code lengths (with repeat codes 16/17/18)
    std::vector<uint8_t> lens(hlit + hdist, 0);
    int idx = 0;
    while (idx < hlit + hdist) {
        const int sym = cl.decode(br);
        if (sym < 0) return false;
        if (sym < 16) {
            lens[idx++] = (uint8_t)sym;
        } else if (sym == 16) {
            const int rep = (int)br.read(2) + 3;
            const uint8_t prev = idx ? lens[idx - 1] : 0;
            if (idx + rep > hlit + hdist) return false;
            for (int r = 0; r < rep; r++) lens[idx++] = prev;
        } else if (sym == 17) {
            const int rep = (int)br.read(3) + 3;
            if (idx + rep > hlit + hdist) return false;
            for (int r = 0; r < rep; r++) lens[idx++] = 0;
        } else {  // 18
            const int rep = (int)br.read(7) + 11;
            if (idx + rep > hlit + hdist) return false;
            for (int r = 0; r < rep; r++) lens[idx++] = 0;
        }
    }
    if (!lt.build(lens.data(), hlit)) return false;
    if (!dt.build(lens.data() + hlit, hdist)) return false;
    return true;
}

// inflate a full zlib stream; appends decompressed bytes to `out`
bool inflate(const uint8_t* src, size_t n, std::vector<uint8_t>& out)
{
    BitReader br{src, n, 0, 0, 0};
    // zlib header: CMF/FLG
    if (n < 2) return false;
    const int cmf = src[0];
    if ((cmf & 0x0F) != 8) return false;        // only deflate
    if (((cmf << 8) | src[1]) % 31 != 0) return false;  // header check
    br.pos = 2;

    // fixed Huffman tables (RFC 1951 section 3.2.6)
    static HuffTable fix_lt, fix_dt;
    static bool fix_built = false;
    if (!fix_built) {
        uint8_t ll[288], dd[32];
        for (int i = 0; i < 144; i++) ll[i] = 8;
        for (int i = 144; i < 256; i++) ll[i] = 9;
        for (int i = 256; i < 280; i++) ll[i] = 7;
        for (int i = 280; i < 288; i++) ll[i] = 8;
        for (int i = 0; i < 32; i++) dd[i] = 5;
        fix_lt.build(ll, 288);
        fix_dt.build(dd, 32);
        fix_built = true;
    }

    bool final = false;
    while (!final) {
        final = br.read(1) != 0;
        const int btype = (int)br.read(2);

        if (btype == 0) {  // stored (uncompressed)
            br.read(5);  // skip to byte boundary
            const size_t len = (size_t)br.read(16);
            br.read(16);  // nlen
            if (br.pos + len > n) return false;
            out.insert(out.end(), src + br.pos, src + br.pos + len);
            br.pos += len;
            br.bitbuf = 0;
            br.bitcnt = 0;
        } else {
            const HuffTable* lt;
            const HuffTable* dt;
            HuffTable dyn_lt, dyn_dt;
            int hlit = 0, hdist = 0;
            if (btype == 1) {
                lt = &fix_lt;
                dt = &fix_dt;
            } else if (btype == 2) {
                if (!build_from_stream(br, dyn_lt, dyn_dt, hlit, hdist)) return false;
                lt = &dyn_lt;
                dt = &dyn_dt;
            } else {
                return false;  // btype 3 is reserved
            }

            // decode symbols until end-of-block
            bool block_end = false;
            long n_sym_decoded = 0;
            while (!block_end) {
                const int sym = lt->decode(br);
                if (getenv("FDX_DEBUG") && n_sym_decoded < 20) {
                    fprintf(stderr, "[dbg] btype=%d sym=%d\n", btype, sym);
                }
                if (sym < 0) return false;
                n_sym_decoded++;
                if (sym < 256) {
                    out.push_back((uint8_t)sym);
                } else if (sym == 256) {
                    block_end = true;
                } else if (sym <= 285) {
                    const int li = sym - 257;
                    int len = LEN_BASE[li];
                    if (LEN_EXTRA[li]) len += (int)br.read(LEN_EXTRA[li]);
                    const int dsym = dt->decode(br);
                    if (dsym < 0 || dsym >= 30) return false;
                    int dist = DIST_BASE[dsym];
                    if (DIST_EXTRA[dsym]) dist += (int)br.read(DIST_EXTRA[dsym]);
                    if (dist > (int)out.size()) return false;
                    const size_t start = out.size() - dist;
                    for (int r = 0; r < len; r++) out.push_back(out[start + r]);
                } else {
                    return false;
                }
            }
        }
    }
    return true;
}

// unfilter one scanline (PNG spec 7.2); `raw` = row bytes, `prev` = previous
// row bytes, `bpp` = bytes per pixel
void unfilter_row(uint8_t* raw, const uint8_t* prev, size_t len, int bpp)
{
    const int f = raw[0];
    uint8_t* p = raw + 1;
    for (size_t i = 0; i < len; i++) {
        const uint8_t a = (i >= (size_t)bpp) ? p[i - bpp] : 0;
        const uint8_t b = prev ? prev[i] : 0;
        const uint8_t c = (i >= (size_t)bpp && prev) ? prev[i - bpp] : 0;
        switch (f) {
            case 0: break;  // None
            case 1: p[i] += a; break;             // Sub
            case 2: p[i] += b; break;             // Up
            case 3: p[i] += (uint8_t)((a + b) / 2); break;  // Average
            case 4: {  // Paeth
                const int pa = b - c < 0 ? c - b : b - c;
                const int pb = a - c < 0 ? c - a : a - c;
                const int pc = a + b - 2 * c < 0 ? 2 * c - a - b : a + b - 2 * c;
                const int pr = (pa <= pb && pa <= pc) ? a : (pb <= pc ? b : c);
                p[i] += (uint8_t)pr;
                break;
            }
            default: break;
        }
    }
}

}  // namespace

// exposed for standalone testing of the DEFLATE stage
bool inflate_test(const uint8_t* src, size_t n, std::vector<uint8_t>& out)
{
    return inflate(src, n, out);
}

// decode an 8-bit non-interlaced PNG; returns false on error
bool decode(const uint8_t* data, size_t size, Image& img)
{
    static const uint8_t SIG[8] = {137, 80, 78, 71, 13, 10, 26, 10};
    if (size < 8 || memcmp(data, SIG, 8) != 0) return false;

    size_t pos = 8;
    bool have_ihdr = false, have_idat = false;
    std::vector<uint8_t> idat;
    std::vector<uint8_t> palette;
    int bit_depth = 8, color_type = 2;

    while (pos + 12 <= size) {
        const uint32_t len = ((uint32_t)data[pos] << 24) | ((uint32_t)data[pos + 1] << 16) |
                             ((uint32_t)data[pos + 2] << 8) | (uint32_t)data[pos + 3];
        const char type[5] = {(char)data[pos + 4], (char)data[pos + 5],
                              (char)data[pos + 6], (char)data[pos + 7], 0};
        if (pos + 12 + len > size) return false;
        const uint8_t* chunk = data + pos + 8;

        if (strcmp(type, "IHDR") == 0 && len >= 13) {
            img.w = ((int)chunk[0] << 24) | ((int)chunk[1] << 16) |
                    ((int)chunk[2] << 8) | chunk[3];
            img.h = ((int)chunk[4] << 24) | ((int)chunk[5] << 16) |
                    ((int)chunk[6] << 8) | chunk[7];
            bit_depth = chunk[8];
            color_type = chunk[9];
            if (chunk[12] != 0) return false;  // no interlace
            have_ihdr = true;
        } else if (strcmp(type, "PLTE") == 0) {
            palette.assign(chunk, chunk + len);
        } else if (strcmp(type, "IDAT") == 0) {
            idat.insert(idat.end(), chunk, chunk + len);
            have_idat = true;
        } else if (strcmp(type, "IEND") == 0) {
            break;
        }
        pos += 12 + len;
    }
    if (!have_ihdr || !have_idat || bit_depth != 8) return false;
    if (img.w <= 0 || img.h <= 0) return false;
    if (color_type != 0 && color_type != 2 && color_type != 3 && color_type != 4 &&
        color_type != 6)
        return false;

    // inflate the concatenated IDAT streams
    std::vector<uint8_t> raw;
    if (!inflate(idat.data(), idat.size(), raw)) return false;

    // channels per pixel and total bytes per scanline (incl. filter byte)
    const int ch_in = (color_type == 0) ? 1 : (color_type == 2) ? 3 :
                      (color_type == 4) ? 2 : (color_type == 6) ? 4 : 1;
    const int bpp = ch_in;
    const size_t row_bytes = (size_t)img.w * ch_in;
    if (raw.size() < (row_bytes + 1) * (size_t)img.h) return false;

    img.ch = (color_type == 0 || color_type == 2 || color_type == 3) ? 3 : 4;
    img.px.resize((size_t)img.w * img.h * img.ch);

    std::vector<uint8_t> prev_row;  // previous UNFILTERED row (persistent across rows)
    size_t off = 0;
    for (int y = 0; y < img.h; y++) {
        const uint8_t* row = raw.data() + off;
        // unfilter works in place on a copy
        std::vector<uint8_t> work(row, row + row_bytes + 1);
        unfilter_row(work.data(), prev_row.empty() ? nullptr : prev_row.data(), row_bytes, bpp);
        prev_row.assign(work.begin() + 1, work.end());

        const uint8_t* src = work.data() + 1;
        uint8_t* dst = img.px.data() + (size_t)y * img.w * img.ch;
        for (int x = 0; x < img.w; x++) {
            const uint8_t* s = src + (size_t)x * ch_in;
            if (color_type == 0) {
                dst[x * 3 + 0] = dst[x * 3 + 1] = dst[x * 3 + 2] = s[0];
            } else if (color_type == 2) {
                dst[x * 3 + 0] = s[0];
                dst[x * 3 + 1] = s[1];
                dst[x * 3 + 2] = s[2];
            } else if (color_type == 3) {
                const size_t pi = (size_t)s[0] * 3;
                if (pi + 2 >= palette.size()) return false;
                dst[x * 3 + 0] = palette[pi + 0];
                dst[x * 3 + 1] = palette[pi + 1];
                dst[x * 3 + 2] = palette[pi + 2];
            } else if (color_type == 4) {
                dst[x * 4 + 0] = dst[x * 4 + 1] = dst[x * 4 + 2] = s[0];
                dst[x * 4 + 3] = s[1];
            } else {  // 6
                dst[x * 4 + 0] = s[0];
                dst[x * 4 + 1] = s[1];
                dst[x * 4 + 2] = s[2];
                dst[x * 4 + 3] = s[3];
            }
        }
        off += row_bytes + 1;
    }
    return true;
}

}  // namespace fdxpng