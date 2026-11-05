// Test du sceau des messages (js/chat-seal.js), execute par Node, sans
// dependance :
//
//   node tests/test-chat-seal.mjs
//
// CE QUI COMPTE LE PLUS : le premier test. joclymatch, Tabulon et mogichex
// partagent le meme fil de discussion ; un sceau que les deux autres ne
// sauraient pas ouvrir rendrait la conversation muette entre eux. Le vecteur
// ci-dessous a ete scelle par mogichex, et Tabulon verifie le meme dans ses
// tests Rust (seal_cmds.rs, opens_a_message_sealed_by_mogichex).

import { makeSealer, parseChatFragment, generateChatKey, isChatKey } from '../js/chat-seal.js';

let failures = 0;
let tests = 0;
function check(label, cond) {
    tests++;
    if (cond) console.log('ok   ' + label);
    else { failures++; console.log('FAIL ' + label); }
}

const VECTOR_KEY = '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f';
const VECTOR_SEALED = 'QEFCQ0RFRkdISUpLTE1OT1BRUlNUVVZXllBgHvCKFmNMXadcLwhFUTKa2at6eTf/SluSMGx2A3KPZ779Qa8OQjXIeDAFZqenCI4=';

// --- interoperabilite ---------------------------------------------------------
check('ouvre un message scelle par mogichex (le vecteur que Tabulon verifie aussi)',
    makeSealer(VECTOR_KEY).open(VECTOR_SEALED) === 'Bien joué — à toi de jouer ♞');

// Le meme nonce donne le meme sceau : notre chiffrement produit donc, octet
// pour octet, ce que produisent les deux autres applications.
{
    const nonce = Uint8Array.from({ length: 24 }, (_, i) => 0x40 + i); // 0x40.. = celui du vecteur
    const s = makeSealer(VECTOR_KEY, { rand: (b) => b.set(nonce.subarray(0, b.length)) });
    check('avec le nonce du vecteur, le sceau est identique a celui de mogichex',
        s.seal('Bien joué — à toi de jouer ♞') === VECTOR_SEALED);
}

// --- aller-retour et ce que voit le relai --------------------------------------
const KEY = generateChatKey();
const sealer = makeSealer(KEY);
const sealed = sealer.seal('rendez-vous a 18h');
check('aller-retour', sealer.open(sealed) === 'rendez-vous a 18h');
check('le texte ne transparait pas dans le sceau', !sealed.includes('rendez') && !sealed.includes('18h'));
check('deux sceaux du meme texte different (nonce tire a chaque message)',
    sealer.seal('bien joue') !== sealer.seal('bien joue'));
check('un sceau est du base64 sur une ligne (le relai refuse les sauts de ligne)',
    /^[A-Za-z0-9+/]+=*$/.test(sealed));

// --- ce qui doit echouer, et comment -------------------------------------------
check('une autre cle n’ouvre pas', makeSealer(generateChatKey()).open(sealed) === null);
{
    const bytes = Buffer.from(sealed, 'base64');
    bytes[bytes.length - 1] ^= 1;
    check('un sceau modifie est rejete (authentifie)', sealer.open(bytes.toString('base64')) === null);
}
check('du texte quelconque est rejete sans exception', sealer.open('pas du base64 !!') === null);
check('un sceau trop court est rejete', sealer.open('AAAA') === null);
check('le texte vide reste du texte vide', sealer.open(sealer.seal('')) === '');

// --- la cle --------------------------------------------------------------------
check('une cle neuve fait 64 caracteres hexadecimaux', isChatKey(KEY));
check('deux cles neuves different', generateChatKey() !== generateChatKey());
let threw = false;
try { makeSealer('pas une cle'); } catch { threw = true; }
check('une cle mal formee est refusee plutot qu’employee', threw);

// --- le fragment du lien -------------------------------------------------------
check('#k= donne la cle', parseChatFragment('#k=' + KEY).chatKey === KEY);
check('la cle est relue en minuscules', parseChatFragment('#k=' + KEY.toUpperCase()).chatKey === KEY);
check('#kid= (cle de communaute Tabulon) est releve, sans cle',
    (() => { const f = parseChatFragment('#kid=0123456789abcdef'); return f.chatKey === null && f.chatKeyId === '0123456789abcdef'; })());
check('une cle tronquee (copier-coller abime) est ignoree', parseChatFragment('#k=' + KEY.slice(0, 40)).chatKey === null);
check('pas de fragment : ni cle ni empreinte',
    (() => { const f = parseChatFragment(''); return f.chatKey === null && f.chatKeyId === null; })());

console.log(`\n${tests} verifications, ${failures} echec(s)`);
process.exit(failures === 0 ? 0 : 1);
