# JoclyMatch

A tiny, self-hostable game server to play [Jocly](https://github.com/fhoudebert/jocly2) board games
online with a friend — no database, no account, no tracking.

🇫🇷 [Version française](LisezMoi.md)

- **🖥️ Runs on any PHP hosting.** Plain files and a few PHP scripts: no database, no framework, no
  build step. Cheap shared hosting is enough.
- **👥 Play and chat with a friend.** Create a match, send the second link, play at your own pace.
  The chat is encrypted in the browser: the server stores only text it cannot read.
- **🔗 One format, three apps.** [Tabulon](https://github.com/fhoudebert/tabulon) (desktop) players
  can join your matches, and [mogichex](https://github.com/fhoudebert/mogichex) (mobile, Android)
  shares the same invitation and chat formats.

**▶ [Try it](https://biscandine.fr/variantes/joclymatch/gamespanel.php)** ·
**▶ [Install it](#installation)**

---

## What you get

- **Play against the computer** — pick a game from the panel and play solo.
- **Play with a friend** — a match comes with two links, one per player. Keep yours, send the other.
- **Rules** for every game, right in the interface.
- **Chat** between the two players, encrypted end to end for matches created from the panel.
- **Take back a move** — if allowed when the match is created (off by default).
- **Save / snapshot** — export a match as JSON, or the board as a picture.
- **Optional faster moves** — long-polling, or near-instant push from a small Node server on a VPS.

## Design choices (features or limitations, you decide)

- **No database.** Matches are plain, human-readable JSON files, which expire after 30 days of
  inactivity — or, usually, as soon as a finished game is closed.
- **No login, no personal data.** Only the moves (and the encrypted chat) are stored.
- **No ads, no tracking.**
- **Honor system.** Anyone with a link plays that side. You *could* open both… but we are
  gentlemen, aren't we?

## Installation

Requirements: a web server running PHP.

1. Copy this repository to your web server.
2. Make a [jocly2](https://github.com/fhoudebert/jocly2) build reachable from your site (the
   `jocly.js` bundle and its games).
3. Create a directory for match files, writable by the web server — ideally **outside** the web
   root. If it must stay inside, copy `saves.htaccess.example` into it as `.htaccess` (Apache), so
   that nobody can list or download the match files.
4. Copy `localconf.php.example` to `localconf.php` and fill in the four required settings:

   ```php
   $joclyMatchURL  = "https://example.org/joclymatch/";           // this installation
   $joclyPlayerURL = "https://example.org/joclymatch/index.php";  // the player page
   $joclyDistPath  = "dist/browser/jocly.js";                     // the Jocly bundle
   $savePath       = "saves/";                                    // match files (step 3)
   ```

   Save it as **UTF-8 without BOM**: in UTF-16, PHP ignores it entirely (the pages will tell you).

5. Open `gamespanel.php`, pick a game, and play!

Everything else is optional and documented in `localconf.php.example`: match lifetime and size
limits, long-polling (`$enableLongPolling`), and push notifications (`$pushWsUrl`, see
[push/README.md](push/README.md)).

## Documentation

| | |
|---|---|
| [LisezMoi.md](LisezMoi.md) | this page, in French |
| [DEVELOPMENT.md](DEVELOPMENT.md) | settings in detail, relay protocol, chat format and encryption, writing another client, tests |
| [push/README.md](push/README.md) | optional push server for a VPS *(French)* |

## Related projects

- [jocly2](https://github.com/fhoudebert/jocly2) — the Jocly board game library (games, 2D/3D views, AI)
- [jcfrog/jocly-simple-match](https://github.com/jcfrog/jocly-simple-match) — the original experiment this project is based on
- [Tabulon](https://github.com/fhoudebert/tabulon) — desktop application; plays remotely through a joclymatch server
- [mogichex](https://github.com/fhoudebert/mogichex) — the mobile counterpart (installable web app / Android)

## License

JoclyMatch is free software under the [GNU Affero General Public License v3](LICENSE) or later.
It loads the Jocly library, itself AGPL-3.0, into its pages. The chat cipher,
[`@noble/ciphers`](https://github.com/paulmillr/noble-ciphers), is MIT.
