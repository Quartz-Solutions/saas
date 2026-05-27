/**
 * Lightweight client-side ULID generator for block ids.
 *
 * Block ids are stored as 26-char Crockford-base32 ULIDs. They are stable
 * across saves and used as React keys + dnd-kit ids. We don't need
 * cryptographic strength here — server validates the tree shape on save.
 */
const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

export function ulid(): string {
    const time = Date.now();
    let timeChars = '';
    let t = time;

    for (let i = 0; i < 10; i++) {
        const mod = t % 32;
        timeChars = ALPHABET[mod] + timeChars;
        t = Math.floor(t / 32);
    }

    let randChars = '';

    for (let i = 0; i < 16; i++) {
        const r = Math.floor(Math.random() * 32);
        randChars += ALPHABET[r];
    }

    return timeChars + randChars;
}
