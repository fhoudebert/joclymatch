# Notification push (optionnel, VPS uniquement)

Le site fonctionne par défaut avec un simple polling HTTP
(`fileio.php`/`js/control.js`), qui marche sur n'importe quel hébergement
mutualisé PHP classique — c'est le mode par défaut, **rien ici ne le change
ni ne le remplace**.

Si le site est hébergé sur un **VPS** (accès shell, capacité à faire tourner
un processus permanent), ce dossier ajoute une notification quasi-instantanée
en plus du polling existant : un petit serveur Node surveille le dossier
`saves/` que `fileio.php` écrit déjà, et prévient par WebSocket les clients
concernés dès qu'un fichier change. Le client (`js/push-client.js`) ne fait
alors que déclencher un rechargement immédiat via l'endpoint `fileio.php`
existant — ce serveur ne stocke et ne sert lui-même aucune donnée de partie.

**Le polling continue de tourner sans changement, même quand cette
notification est active** : c'est un filet de sécurité qui rattrape tout
(connexion WebSocket coupée, serveur de notification arrêté, redémarré...).

## Installation (sur le VPS)
Ça nécessite de faire tourner un processus permanent (Node), ce qu'un
hébergement mutualisé classique ne permet en général pas (pas d'accès shell
persistant, pas de port personnalisé ouvert). 

```bash
cd push/
npm install
PUSH_PORT=8787 SAVES_DIR=/chemin/absolu/vers/saves node server.js
```

À faire tourner en permanence (systemd, exemple minimal) :

```ini
# /etc/systemd/system/joclymatch-push.service
[Unit]
Description=jocly-simple-match push notifier
After=network.target

[Service]
Environment=PUSH_PORT=8787
Environment=SAVES_DIR=/var/www/joclymatch/saves
ExecStart=/usr/bin/node /var/www/joclymatch/push/server.js
Restart=on-failure
User=www-data

[Install]
WantedBy=multi-user.target
```

Reverse-proxy nginx (pour servir en `wss://` derrière le même domaine que le
site, plutôt qu'exposer le port Node directement) :

```nginx
location /push/ {
    proxy_pass http://127.0.0.1:8787/;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
}
```

## Activation côté site

Dans `localconf.php`, ajouter (absent par défaut = fonctionnalité désactivée) :

```php
$pushWsUrl = "wss://votredomaine.example/push/";
// ou, sans reverse-proxy : "ws://votredomaine.example:8787"
```

`index.php` ne charge `js/push-client.js` que si cette variable est définie
et non vide — sans elle, le comportement est identique à avant ce composant.

## Limites

- Pas d'authentification (comme le reste du site : la confidentialité de
  l'identifiant de partie est la seule protection, cohérent avec
  `fileio.php`).
- `fs.watch()` peut occasionnellement manquer un événement selon la
  plateforme/le système de fichiers (documenté côté Node) — sans
  conséquence ici puisque le polling existant tourne toujours en parallèle
  et finira par rattraper le coup.
- Un seul abonnement actif par connexion WebSocket (un onglet = une partie
  suivie à la fois).
