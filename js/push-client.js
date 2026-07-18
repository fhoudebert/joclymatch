// js/push-client.js — compagnon OPTIONNEL de la boucle de polling existante
// (checkIfOtherUserPlayed()/reloadCounter dans control.js, INCHANGÉE). Ne la
// remplace pas : se contente de déclencher un reload immédiat quand une
// notification arrive, en plus du polling qui continue de tourner en filet
// de sécurité (utile si la connexion WebSocket tombe, ou si le serveur de
// notification n'est pas lancé). Voir push/README.md.
//
// Chargé uniquement si PUSH_WS_URL est défini (voir index.php, dépend d'une
// variable optionnelle de localconf.php) -- absent par défaut, donc aucun
// changement de comportement pour un déploiement qui ne l'active pas.
(function () {
    if (typeof PUSH_WS_URL === 'undefined' || !PUSH_WS_URL) return;

    var ws = null;
    var reconnectDelay = 1000;
    var closedByUs = false;

    function subscribe() {
        if (typeof matchDetails !== 'undefined' && matchDetails.matchId && ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({ type: 'subscribe', gameid: matchDetails.matchId }));
        }
    }

    function onMessage(ev) {
        var msg;
        try { msg = JSON.parse(ev.data); }
        catch (e) { return; } // notification malformee -> ignoree, le polling reste le filet de securite
        if (msg && msg.type === 'changed' && msg.gameid === matchDetails.matchId) {
            // Reutilise TELLE QUELLE la fonction de polling existante --
            // meme logique de comparaison/application, on ne fait que la
            // declencher plus tot que son prochain cycle programme.
            if (window.currentMatch && typeof loadMatchFromID === 'function') {
                loadMatchFromID(matchDetails.matchId, window.currentMatch);
            }
        }
    }

    function scheduleReconnect() {
        if (closedByUs) return;
        setTimeout(connect, reconnectDelay);
        reconnectDelay = Math.min(reconnectDelay * 2, 30000); // backoff, plafonne a 30s
    }

    function connect() {
        try {
            ws = new WebSocket(PUSH_WS_URL);
        } catch (e) {
            console.warn('[push-client] connexion impossible, polling seul:', e);
            return; // pas de reconnexion : URL invalide, pas la peine d'insister
        }
        ws.addEventListener('open', function () {
            reconnectDelay = 1000;
            subscribe();
        });
        ws.addEventListener('message', onMessage);
        ws.addEventListener('close', scheduleReconnect);
        ws.addEventListener('error', function () { ws.close(); });
    }

    window.addEventListener('beforeunload', function () {
        closedByUs = true;
        if (ws) ws.close();
    });

    connect();
})();
