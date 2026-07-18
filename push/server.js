#!/usr/bin/env node
// push/server.js -- Notificateur WebSocket optionnel pour jocly-simple-match.
//
// A ne lancer QUE sur un hebergement de type VPS (acces shell, capacite a
// faire tourner un processus permanent) -- inutilisable sur un hebergement
// mutualise classique, qui reste servi tel quel par le simple polling
// existant (fileio.php/control.js, INCHANGES par ce composant).
//
// Principe volontairement minimal : ce serveur ne stocke ni ne sert AUCUNE
// donnee de partie. Il se contente de surveiller le meme dossier saves/ que
// fileio.php ecrit deja, et de prevenir les clients abonnes qu'un fichier a
// change -- charge a eux de recharger via l'endpoint fileio.php existant
// (voir js/push-client.js). Zero duplication du format d'enveloppe JSON,
// zero risque de desynchronisation avec fileio.php : ce serveur ne
// comprend meme pas le contenu des fichiers qu'il surveille.
//
// Lancement :
//   PUSH_PORT=8787 SAVES_DIR=/chemin/vers/saves node push/server.js
// (par defaut : port 8787, dossier "../saves" relatif a ce script)
//
// Voir push/README.md pour le déploiement (systemd, reverse-proxy nginx).

const fs = require('fs');
const path = require('path');
const { WebSocketServer } = require('ws');

const PORT = parseInt(process.env.PUSH_PORT || '8787', 10);
const SAVES_DIR = process.env.SAVES_DIR || path.join(__dirname, '..', 'saves');

if (!fs.existsSync(SAVES_DIR)) {
    console.error(`push/server.js: le dossier ${SAVES_DIR} n'existe pas -- verifiez SAVES_DIR.`);
    process.exit(1);
}

const wss = new WebSocketServer({ port: PORT });

// gameid -> Set<ws> abonnes a ce match
const subscribers = new Map();

function subscribe(ws, gameid) {
    if (ws._gameid) unsubscribe(ws); // un seul abonnement actif par connexion
    if (!subscribers.has(gameid)) subscribers.set(gameid, new Set());
    subscribers.get(gameid).add(ws);
    ws._gameid = gameid;
}

function unsubscribe(ws) {
    const set = subscribers.get(ws._gameid);
    set?.delete(ws);
    if (set && set.size === 0) subscribers.delete(ws._gameid);
    ws._gameid = null;
}

wss.on('connection', (ws) => {
    ws.on('message', (raw) => {
        let msg;
        try { msg = JSON.parse(raw); } catch { return; }
        if (msg && msg.type === 'subscribe' && typeof msg.gameid === 'string' && msg.gameid.length > 0) {
            subscribe(ws, msg.gameid);
        }
    });
    ws.on('close', () => { if (ws._gameid) unsubscribe(ws); });
    ws.on('error', () => {}); // une connexion en erreur se fermera d'elle-meme
});

// Meme validation de forme que fileio.php (voir la correction dediee) --
// meme si ce n'est ici qu'un nom de fichier observe, pas une entree utilisateur
// directement utilisee dans un chemin.
const VALID_GAMEID = /^[A-Za-z0-9_-]+$/;

// fs.watch() declenche parfois plusieurs evenements pour une seule ecriture
// (comportement documente, variable selon la plateforme) -- un court
// anti-rebond par gameid evite d'envoyer des notifications redondantes.
const pendingNotify = new Map(); // gameid -> Timeout
const DEBOUNCE_MS = 150;

fs.watch(SAVES_DIR, (eventType, filename) => {
    if (!filename || !filename.endsWith('.txt') || filename.endsWith('-chat.txt')) return;
    const gameid = filename.slice(0, -4);
    if (!VALID_GAMEID.test(gameid)) return;

    if (pendingNotify.has(gameid)) clearTimeout(pendingNotify.get(gameid));
    pendingNotify.set(gameid, setTimeout(() => {
        pendingNotify.delete(gameid);
        const set = subscribers.get(gameid);
        if (!set || set.size === 0) return;
        const payload = JSON.stringify({ type: 'changed', gameid });
        for (const client of set) {
            if (client.readyState === client.OPEN) client.send(payload);
        }
    }, DEBOUNCE_MS));
});

console.log(`push/server.js: en ecoute sur ws://0.0.0.0:${PORT}, surveille ${SAVES_DIR}`);
