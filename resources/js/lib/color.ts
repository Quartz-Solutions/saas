// Hex <-> OKLCH conversion (Björn Ottosson's OKLab matrices). The theme
// catalog stores oklch (matches resources/css/app.css), but the editor's
// native color picker speaks hex — so we convert at the UI boundary.

function srgbToLinear(c: number): number {
    return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
}

function linearToSrgb(c: number): number {
    return c <= 0.0031308 ? 12.92 * c : 1.055 * c ** (1 / 2.4) - 0.055;
}

function clamp01(n: number): number {
    return Math.min(1, Math.max(0, n));
}

/** "#rrggbb" -> "oklch(L C H)" (L,C to 3dp; H to 1dp). Returns null if unparseable. */
export function hexToOklch(hex: string): string | null {
    const m = /^#?([0-9a-f]{6})$/i.exec(hex.trim());

    if (!m) {
        return null;
    }

    const int = parseInt(m[1], 16);
    const r = srgbToLinear(((int >> 16) & 0xff) / 255);
    const g = srgbToLinear(((int >> 8) & 0xff) / 255);
    const b = srgbToLinear((int & 0xff) / 255);

    const l = Math.cbrt(0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b);
    const mq = Math.cbrt(0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b);
    const s = Math.cbrt(0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b);

    const L = 0.2104542553 * l + 0.793617785 * mq - 0.0040720468 * s;
    const a = 1.9779984951 * l - 2.428592205 * mq + 0.4505937099 * s;
    const bb = 0.0259040371 * l + 0.7827717662 * mq - 0.808675766 * s;

    const C = Math.sqrt(a * a + bb * bb);
    let H = (Math.atan2(bb, a) * 180) / Math.PI;

    if (H < 0) {
        H += 360;
    }

    const round = (n: number, p: number) => {
        const f = 10 ** p;

        return Math.round(n * f) / f;
    };

    return `oklch(${round(L, 3)} ${round(C, 3)} ${round(H, 1)})`;
}

/** "oklch(L C H)" -> "#rrggbb". Falls back to the supplied default on failure. */
export function oklchToHex(value: string, fallback = '#000000'): string {
    const m = /oklch\(\s*([0-9.]+%?)\s+([0-9.]+%?)\s+([0-9.]+)/i.exec(value);

    if (!m) {
        return fallback;
    }

    const L = m[1].endsWith('%') ? parseFloat(m[1]) / 100 : parseFloat(m[1]);
    const C = m[2].endsWith('%') ? (parseFloat(m[2]) / 100) * 0.4 : parseFloat(m[2]);
    const H = (parseFloat(m[3]) * Math.PI) / 180;

    const a = C * Math.cos(H);
    const b = C * Math.sin(H);

    const l_ = (L + 0.3963377774 * a + 0.2158037573 * b) ** 3;
    const m_ = (L - 0.1055613458 * a - 0.0638541728 * b) ** 3;
    const s_ = (L - 0.0894841775 * a - 1.291485548 * b) ** 3;

    const r = linearToSrgb(4.0767416621 * l_ - 3.3077115913 * m_ + 0.2309699292 * s_);
    const g = linearToSrgb(-1.2684380046 * l_ + 2.6097574011 * m_ - 0.3413193965 * s_);
    const bl = linearToSrgb(-0.0041960863 * l_ - 0.7034186147 * m_ + 1.707614701 * s_);

    const toHex = (n: number) =>
        Math.round(clamp01(n) * 255)
            .toString(16)
            .padStart(2, '0');

    return `#${toHex(r)}${toHex(g)}${toHex(bl)}`;
}
