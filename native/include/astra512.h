#ifndef ASTRA512_H
#define ASTRA512_H
#include <stddef.h>
#include <stdint.h>
#define ASTRA512_TAG_BYTES 16
#define ASTRA512_KEY_BYTES 32
int astra512_encrypt(const uint8_t*, const uint8_t*, size_t,
                     const uint8_t*, size_t, uint8_t*, uint8_t*);
int astra512_decrypt(const uint8_t*, const uint8_t*, size_t,
                     const uint8_t*, size_t, const uint8_t*, uint8_t*);
#endif
