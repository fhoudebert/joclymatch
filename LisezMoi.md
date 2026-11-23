# JoclyMatch

Un petit serveur de jeu, à héberger soi-même, pour jouer en ligne avec un ami aux jeux de plateau
[Jocly](https://github.com/fhoudebert/jocly2) — sans base de données, sans compte, sans pistage.

🇬🇧 [English version](README.md)

- **🖥️ Tourne sur n'importe quel hébergement PHP.** Des fichiers et quelques scripts PHP : ni base
  de données, ni framework, ni étape de construction. Un hébergement mutualisé bon marché suffit.
- **👥 Jouer et discuter avec un ami.** Créez une partie, envoyez le second lien, jouez à votre
  rythme. La discussion est chiffrée dans le navigateur : le serveur ne stocke qu'un texte qu'il
  ne peut pas lire.
- **🔗 Un format, trois applications.** Les joueurs [Tabulon](https://github.com/fhoudebert/tabulon)
  (ordinateur) peuvent rejoindre vos parties, et [mogichex](https://github.com/fhoudebert/mogichex)
  (mobile, Android) partage les mêmes formats d'invitation et de discussion.

**▶ [Essayer](https://biscandine.fr/variantes/joclymatch/gamespanel.php)** ·
**▶ [L'installer](#installation)**

---

## Ce qu'on y trouve

- **Jouer contre l'ordinateur** — choisissez un jeu dans le panneau et jouez seul.
- **Jouer avec un ami** — une partie vient avec deux liens, un par joueur. Gardez le vôtre,
  envoyez l'autre.
- **Les règles** de chaque jeu, directement dans l'interface.
- **Une discussion** entre les deux joueurs, chiffrée de bout en bout pour les parties créées
  depuis le panneau.
- **Reprendre un coup** — si la partie l'autorise à sa création (désactivé par défaut).
- **Sauvegarde / photo** — exporter une partie en JSON, ou le plateau en image.
- **Des coups plus rapides, en option** — attente longue, ou notification quasi immédiate par un
  petit serveur Node sur un VPS.

## Choix de conception (qualités ou limites, à vous de voir)

- **Pas de base de données.** Les parties sont de simples fichiers JSON lisibles, effacés après
  30 jours d'inactivité — ou, le plus souvent, dès qu’une partie terminée est fermée.
- **Pas de compte, pas de données personnelles.** Seuls les coups (et la discussion chiffrée)
  sont conservés.
- **Pas de publicité, pas de pistage.**
- **Parole d'honneur.** Qui a un lien joue ce camp. On *pourrait* ouvrir les deux… mais nous
  sommes entre gens de bonne compagnie, n'est-ce pas ?

## Installation

Prérequis : un serveur web avec PHP.

1. Copiez ce dépôt sur votre serveur web.
2. Rendez accessible depuis votre site une construction de
   [jocly2](https://github.com/fhoudebert/jocly2) (le paquet `jocly.js` et ses jeux).
3. Créez un répertoire pour les fichiers de partie, accessible en écriture au serveur web —
   idéalement **hors** de la racine web. S'il doit y rester, copiez-y `saves.htaccess.example` sous
   le nom `.htaccess` (Apache), pour que personne ne puisse lister ni télécharger les parties.
4. Copiez `localconf.php.example` en `localconf.php` et renseignez les quatre réglages
   obligatoires :

   ```php
   $joclyMatchURL  = "https://example.org/joclymatch/";           // cette installation
   $joclyPlayerURL = "https://example.org/joclymatch/index.php";  // la page de jeu
   $joclyDistPath  = "dist/browser/jocly.js";                     // la bibliothèque Jocly
   $savePath       = "saves/";                                    // fichiers de partie (étape 3)
   ```

   Enregistrez-le en **UTF-8 sans BOM** : en UTF-16, PHP l'ignore complètement (les pages vous
   le diront).

5. Ouvrez `gamespanel.php`, choisissez un jeu, et jouez !

Tout le reste est facultatif et documenté dans `localconf.php.example` : durée de vie et tailles
maximales des parties, attente longue (`$enableLongPolling`), notifications push (`$pushWsUrl`,
voir [push/README.md](push/README.md)).

## Documentation

| | |
|---|---|
| [README.md](README.md) | cette page, en anglais |
| [DEVELOPMENT.md](DEVELOPMENT.md) | réglages en détail, protocole du relai, format et chiffrement de la discussion, écrire un autre client, tests *(en anglais)* |
| [push/README.md](push/README.md) | serveur push facultatif pour un VPS |

## Projets liés

- [jocly2](https://github.com/fhoudebert/jocly2) — la bibliothèque de jeux Jocly (jeux, vues 2D/3D, IA)
- [jcfrog/jocly-simple-match](https://github.com/jcfrog/jocly-simple-match) — l'expérience d'origine dont ce projet est issu
- [Tabulon](https://github.com/fhoudebert/tabulon) — application de bureau ; joue à distance via un serveur joclymatch
- [mogichex](https://github.com/fhoudebert/mogichex) — le pendant mobile (application web installable / Android)

## Licence

JoclyMatch est un logiciel libre sous [GNU Affero General Public License v3](LICENSE) ou
ultérieure. Il charge dans ses pages la bibliothèque Jocly, elle-même sous AGPL-3.0. La
bibliothèque de chiffrement de la discussion,
[`@noble/ciphers`](https://github.com/paulmillr/noble-ciphers), est sous licence MIT.
