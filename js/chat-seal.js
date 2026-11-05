// js/chat-seal.js — sceller le texte libre de la discussion.
//
// ── Ce que cela protège, et de qui ──────────────────────────────────────────
//
// fileio.php n'authentifie personne : ce qu'on y dépose est lisible par qui
// tient le serveur et par qui connaît l'identifiant de partie. Une clé tirée de
// cet identifiant ne protégerait rien — il est ENVOYÉ au serveur à chaque
// requête. Il faut donc un second secret, qui ne part jamais vers le serveur :
// tiré au hasard pour chaque partie par gamespanel.js, il voyage dans le
// FRAGMENT du lien (`#k=<64 caractères hexadécimaux>`), que le navigateur ne
// transmet jamais — ni à index.php, ni à fileio.php, ni dans un en-tête
// Referer. Le relai ne voit que des octets opaques.
//
// Ce que cela ne protège pas : le serveur voit toujours qui écrit, quand, et
// combien ; et quiconque a le lien complet a la clé. Le sceau est authentifié :
// un message fabriqué sans la clé est rejeté à l'ouverture, pas affiché.
//
// ── Format : celui de Tabulon et de mogichex, à l'octet près ────────────────
//
//   clé     32 octets, en hexadécimal minuscule (64 caractères)
//   sceau   base64 standard de (nonce[24] ‖ chiffré ‖ étiquette[16])
//   chiffre XChaCha20-Poly1305, nonce tiré au hasard à chaque message
//
// Sur le relai, un message scellé porte `enc: 1` dans la ligne du fil : c'est
// ce que Tabulon et mogichex lisent pour savoir qu'il faut l'ouvrir.
//
// Module ES (le chiffre l'est) chargé à côté des scripts classiques de la
// page : il pose `window.joclyChatSeal`, que control.js consulte. Les
// fonctions sont exportées pour être testées sous Node (tests/test-chat-seal.mjs).

import { xchacha20poly1305 } from './vendor/noble-ciphers/chacha.js';

export const KEY_BYTES = 32;
export const NONCE_BYTES = 24;
const KEY_RE = /^[0-9a-f]{64}$/;
const KEY_ID_RE = /^[0-9a-f]{16}$/;

export function isChatKey(value) {
    return typeof value === 'string' && KEY_RE.test(value);
}

/**
 * Lit le fragment d'un lien d'invitation.
 *
 * `k` : la clé de la partie. `kid` : l'EMPREINTE d'une clé de communauté
 * Tabulon — elle désigne une clé sans la donner, et joclymatch n'a pas de
 * trousseau pour la retrouver ; on la relève quand même pour pouvoir le DIRE.
 * Une valeur mal formée est ignorée plutôt qu'employée : une clé à moitié
 * valide ne protège rien et en donne l'apparence.
 */
export function parseChatFragment(hash) {
    const text = String(hash || '').replace(/^#/, '');
    let params;
    try { params = new URLSearchParams(text); } catch { params = new URLSearchParams(); }
    const k = (params.get('k') || '').toLowerCase();
    const kid = (params.get('kid') || '').toLowerCase();
    return {
        chatKey: KEY_RE.test(k) ? k : null,
        chatKeyId: KEY_ID_RE.test(kid) ? kid : null,
    };
}

/**
 * Une clé neuve. Pas de repli vers Math.random : une clé devinable est PIRE
 * qu'une absence de chiffrement, parce qu'elle en donne l'apparence.
 */
export function generateChatKey(rand) {
    const bytes = new Uint8Array(KEY_BYTES);
    if (rand) rand(bytes);
    else if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function')
        crypto.getRandomValues(bytes);
    else throw new Error('chat-seal: aucune source d’aléa sûre disponible');
    return toHex(bytes);
}

function toHex(bytes) {
    return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
}

function fromHex(hex) {
    const out = new Uint8Array(hex.length / 2);
    for (let i = 0; i < out.length; i++) out[i] = parseInt(hex.substr(i * 2, 2), 16);
    return out;
}

function toBase64(bytes) {
    let s = '';
    for (const b of bytes) s += String.fromCharCode(b);
    return btoa(s);
}

function fromBase64(text) {
    const s = atob(text);
    const out = new Uint8Array(s.length);
    for (let i = 0; i < s.length; i++) out[i] = s.charCodeAt(i);
    return out;
}

/**
 * Le scelleur d'une partie. SYNCHRONE : XChaCha en JavaScript n'attend rien,
 * et control.js est écrit en rappels, pas en promesses.
 *
 * `open` rend null pour toute erreur — base64 abîmé, message tronqué, autre
 * clé — sans dire laquelle : la distinction n'aiderait que celui qui cherche
 * à deviner la clé.
 */
export function makeSealer(key, { rand = null } = {}) {
    if (!isChatKey(key)) throw new Error('makeSealer: clé mal formée');
    const raw = fromHex(key);
    const nonce = () => {
        const n = new Uint8Array(NONCE_BYTES);
        if (rand) rand(n);
        else crypto.getRandomValues(n);
        return n;
    };
    return {
        seal(text) {
            const n = nonce();
            const body = xchacha20poly1305(raw, n).encrypt(new TextEncoder().encode(String(text)));
            const framed = new Uint8Array(n.length + body.length);
            framed.set(n, 0);
            framed.set(body, n.length);
            return toBase64(framed);
        },
        open(sealed) {
            try {
                const framed = fromBase64(String(sealed));
                if (framed.length <= NONCE_BYTES) return null;
                return new TextDecoder('utf-8', { fatal: true }).decode(
                    xchacha20poly1305(raw, framed.slice(0, NONCE_BYTES)).decrypt(framed.slice(NONCE_BYTES)));
            } catch {
                return null;
            }
        },
    };
}

// ── Dans la page ────────────────────────────────────────────────────────────
//
// La clé est lue UNE fois, au chargement. control.js n'a pas besoin de la
// connaître : il ne voit que le scelleur.
if (typeof window !== 'undefined' && typeof document !== 'undefined') {
    const { chatKey, chatKeyId } = parseChatFragment(window.location.hash);
    window.joclyChatSeal = {
        sealer: chatKey ? makeSealer(chatKey) : null,
        chatKeyId,
        generateChatKey,
    };
    window.dispatchEvent(new Event('jocly-chat-seal-ready'));
}
