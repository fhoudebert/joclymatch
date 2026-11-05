# @noble/ciphers — sous-ensemble embarqué

`@noble/ciphers` **2.4.0**, MIT (Paul Miller) — voir `LICENSE`.
Origine : <https://github.com/paulmillr/noble-ciphers>

Quatre fichiers du paquet publié, **copiés tels quels, sans retouche** —
exactement les mêmes que ceux de mogichex (`js/vendor/noble-ciphers/`),
vérifiés identiques au paquet npm `@noble/ciphers@2.4.0` :

| Fichier | Rôle |
|---|---|
| `chacha.js` | `xchacha20poly1305` — le seul export utilisé |
| `_arx.js` | noyau ChaCha, importé par `chacha.js` |
| `_poly1305.js` | l'authentificateur, importé par `chacha.js` |
| `utils.js` | fonctions communes, importées par les trois autres |

## Pourquoi du code tiers

Le navigateur ne sait pas faire XChaCha20-Poly1305 (`crypto.subtle` ne propose
qu'AES-GCM), or c'est le sceau de Tabulon et de mogichex : les trois
applications partagent le même fil de discussion sur le relai, et un autre
chiffre les rendrait sourdes les unes aux autres. Écrire soi-même un chiffre
serait la pire des options.

## Mise à jour

`npm pack @noble/ciphers@<version>`, recopier les quatre fichiers et `LICENSE`,
puis `node tests/test-chat-seal.mjs` (qui ouvre un message scellé par mogichex,
le vecteur que Tabulon vérifie aussi).
