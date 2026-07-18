
## Fichiers modifiés (remplacent l'existant)
- `fileio.php` — validation gameid, écriture atomique, load() sans
  avertissement PHP ni troncature, Content-Type, long-polling optionnel
  (`sinceMtime`)
- `index.php` — émission conditionnelle de `LONG_POLL_ENABLED` et
  `PUSH_WS_URL` (rien n'est émis si les variables ne sont pas définies dans
  `localconf.php`)
- `js/control.js` — garde défensive sur `loadMatchFromID`, référence
  globale `window.currentMatch` (pour push-client.js), mode long-polling
  optionnel
- `.gitignore` — ajout de `push/node_modules`

## Fichiers nouveaux
- `js/push-client.js` — client de notification WebSocket, chargé
  seulement si `PUSH_WS_URL` est défini
- `push/server.js`, `push/package.json`, `push/package-lock.json`,
  `push/README.md` — serveur de notification optionnel (VPS uniquement,
  voir `push/README.md` pour le déploiement)

## Activation (tout est désactivé par défaut)
Dans `localconf.php`, selon ce que vous voulez activer :
```php
$enableLongPolling = true;                        // long-polling
$pushWsUrl = "wss://votredomaine.example/push/";   // notification push (VPS)
```

Sans ces lignes, le comportement est reste identique à avant.
