#include <stdint.h>
#include <string.h>
#include <stdlib.h>
#include "astra512.h"
#define ROTL64(x,n) (((x) << (n)) | ((x) >> (64 - (n))))
static inline void qround(uint64_t *a, uint64_t *b, uint64_t *c, uint64_t *d){
    *a = *a + *b; *d ^= *a; *d = ROTL64(*d, 32);
    *c = *c + *d; *b ^= *c; *b = ROTL64(*b, 24);
    *a = *a + *b; *d ^= *a; *d = ROTL64(*d, 16);
    *c = *c + *d; *b = ROTL64(*b, 63);
}
static const uint64_t IV[8] = {
    0x53414e4453495630ULL, 0x243f6a8885a308d3ULL, 0x13198a2e03707344ULL, 0xa4093822299f31d0ULL,
    0x082efa98ec4e6c89ULL, 0x452821e638d01377ULL, 0xbe5466cf34e90c6cULL, 0xc0acf169b5f18a8cULL
};
static void permute(uint64_t s[8], int rounds){
    for(int r=0;r<rounds;r++){
        s[0] ^= (0x9e3779b97f4a7c15ULL ^ (uint64_t)r);
        qround(&s[0],&s[2],&s[4],&s[6]);
        qround(&s[1],&s[3],&s[5],&s[7]);
        qround(&s[0],&s[3],&s[5],&s[6]);
        qround(&s[1],&s[2],&s[4],&s[7]);
    }
}
static void absorb_block(uint64_t s[8], const uint8_t blk[32]){
    for(int i=0;i<4;i++){
        uint64_t w = 0; memcpy(&w, blk + i*8, 8);
        s[i] ^= w;
    }
    permute(s, 8);
}
static void pad_block(uint8_t *tmp, size_t len){
    memset(tmp+len, 0, 32-len);
    tmp[len] = 0x80;
    if(len == 31) tmp[31] |= 0x01;
}
static void domain_absorb(uint64_t s[8], uint8_t label, size_t len){
    uint8_t hdr[32]={0};
    hdr[0] = label;
    memcpy(hdr+1, &len, sizeof(len));
    absorb_block(s, hdr);
}
static void mac_tag(const uint8_t K1[16], const uint8_t *aad, size_t aad_len,
                    const uint8_t *pt, size_t pt_len, uint8_t tag[16]){
    uint64_t s[8]; memcpy(s, IV, sizeof(IV));
    uint64_t k0=0,k1=0; memcpy(&k0,K1,8); memcpy(&k1,K1+8,8);
    s[0]^=k0; s[1]^=k1; s[7]^=0x6b65792d6d61632dULL;
    permute(s,8);
    domain_absorb(s, 'A', aad_len);
    for(size_t i=0;i<aad_len; i+=32){
        uint8_t tmp[32]; size_t n = (aad_len - i < 32) ? (aad_len - i) : 32;
        memcpy(tmp, aad + i, n);
        if(n<32){ pad_block(tmp, n); }
        absorb_block(s, tmp);
    }
    domain_absorb(s, 'M', pt_len);
    for(size_t i=0;i<pt_len; i+=32){
        uint8_t tmp[32]; size_t n = (pt_len - i < 32) ? (pt_len - i) : 32;
        memcpy(tmp, pt + i, n);
        if(n<32){ pad_block(tmp, n); }
        absorb_block(s, tmp);
    }
    permute(s,8);
    memcpy(tag, s, 16);
}
static void keystream(const uint8_t K2[16], const uint8_t tag[16], uint8_t *out, size_t nbytes){
    uint64_t s[8]; memcpy(s, IV, sizeof(IV));
    uint64_t k0=0,k1=0,t0=0,t1=0;
    memcpy(&k0,K2,8); memcpy(&k1,K2+8,8);
    memcpy(&t0,tag,8); memcpy(&t1,tag+8,8);
    s[0]^=k0; s[1]^=k1; s[2]^=t0; s[3]^=t1; s[7]^=0x6b65792d6d736b2dULL;
    size_t off=0;
    while(off<nbytes){
        permute(s,8);
        uint8_t block[32]; memcpy(block, s, 32);
        size_t n = (nbytes - off < 32) ? (nbytes - off) : 32;
        memcpy(out+off, block, n);
        off += n;
    }
}
int astra512_encrypt(const uint8_t *key32, const uint8_t *aad, size_t aad_len,
                     const uint8_t *pt, size_t pt_len, uint8_t *ct_out, uint8_t *tag16_out){
    if(!key32 || (!pt && pt_len) || (!ct_out && pt_len) || !tag16_out) return -2;
    const uint8_t *K1 = key32; const uint8_t *K2 = key32+16;
    const uint8_t *aadp = (aad && aad_len)? aad : (const uint8_t *)"";
    mac_tag(K1, aadp, aad_len, pt ? pt : (const uint8_t *)"", pt_len, tag16_out);
    if(pt_len){
        uint8_t *ks = (uint8_t*)malloc(pt_len); if(!ks) return -2;
        keystream(K2, tag16_out, ks, pt_len);
        for(size_t i=0;i<pt_len;i++) ct_out[i] = pt[i]^ks[i];
        free(ks);
    }
    return 0;
}
int astra512_decrypt(const uint8_t *key32, const uint8_t *aad, size_t aad_len,
                     const uint8_t *ct, size_t ct_len, const uint8_t *tag16, uint8_t *pt_out){
    if(!key32 || (!ct && ct_len) || (!pt_out && ct_len) || !tag16) return -2;
    const uint8_t *K1 = key32; const uint8_t *K2 = key32+16;
    if(ct_len){
        uint8_t *ks = (uint8_t*)malloc(ct_len); if(!ks) return -2;
        keystream(K2, tag16, ks, ct_len);
        for(size_t i=0;i<ct_len;i++) pt_out[i] = ct[i]^ks[i];
        free(ks);
    }
    uint8_t chk[ASTRA512_TAG_BYTES];
    const uint8_t *aadp = (aad && aad_len)? aad : (const uint8_t *)"";
    mac_tag(K1, aadp, aad_len, pt_out ? pt_out : (const uint8_t *)"", ct_len, chk);
    if(memcmp(chk, tag16, ASTRA512_TAG_BYTES)!=0){
        if(pt_out && ct_len){ memset(pt_out, 0, ct_len); }
        return -1;
    }
    return 0;
}
