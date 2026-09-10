# JoclyMatch

A tiny, self-hostable game server to play [Jocly](https://github.com/fhoudebert/jocly2) board games online with a friend — no database, no account, no tracking.

## What you get

- **Play against the computer** — pick a game from the panel and play solo.
- **Play with a friend** — create a match and get two links: one for player A, one for player B. Send the other link to your opponent and play at your own pace.
- **Read the rules** — every game comes with its rules, available directly in the interface.
- **Chat** — a simple in-match chat between the two players.
- **Save / snapshot** — export a match as a JSON file, or take a picture of the board.

## Design choices (features or limitations, you decide)

- **No database.** All you need is a web server with PHP. Matches are stored as plain, human-readable JSON text files.
- **No login, no password.** Absolutely no personal data is stored — only the moves played (and the chat messages) of each match.
- **No ads, no tracking.**
- **Honor system.** Anyone who has a link can play that side of the board. You *could* cheat by opening both links… but we are gentlemen, aren't we?
- **Simple polling by default.** The board refreshes with a classic periodic HTTP polling, which works on any shared PHP hosting. Optional faster modes exist (see below).

## Installation

Requirements: any web server able to run PHP. No database, no framework, no build step.

1. Copy the content of this repository to your web server.
2. Get a Jocly distribution (the `jocly.js` bundle and its games) from [jocly2](https://github.com/fhoudebert/jocly2) and make it reachable from your site.
3. Create a writable directory for match files, e.g. `saves/` (the web server user must be able to write to it).
4. Create a `localconf.php` file at the root of the site:

```php
<?php
// URL of this JoclyMatch installation (with trailing slash)
$joclyMatchURL  = "https://example.org/joclymatch/";

// URL of the match player page
$joclyPlayerURL = "https://example.org/joclymatch/index.php";

// Path or URL of the Jocly library bundle
$joclyDistPath  = "dist/browser/jocly.js";

// Directory where match files are stored (with trailing slash, writable)
$savePath       = "saves/";
```

5. Open `gamespanel.php` in your browser, pick a game, and play!

### Optional settings

Match and chat files used to accumulate forever: nothing ever deleted them, and
the only way to reclaim space was to remove them by hand. They now expire, and
the limits below can be tuned — all of them are optional, and the defaults are
what you get without writing anything.

```php
// How long an untouched match file is kept. The common case is the game
// abandoned in silence, which no client is ever going to close.
$saveTTL       = 30 * 24 * 3600;   // 30 days

// Largest match state accepted in one save.
$saveMaxBytes  = 1048576;          // 1 MB

// Largest chat log for one match. The file is appended to, so without a
// bound it grows for as long as the game lasts.
$chatMaxBytes  = 262144;           // 256 KB
```

The clean-up is opportunistic and bounded — there is no cron on shared
hosting, so a handful of files are checked on each save rather than the whole
directory. A client that knows the game is over can also delete it outright:

```
POST gameioaction=drop  gameid=<id>     -> {"ok":true}
```

which removes the match file and its chat log together.

## Optional: faster move notifications

By default, opponents' moves show up through periodic polling. Everything below is **disabled by default** and entirely optional — without these lines in `localconf.php`, the behavior is unchanged.

**Long-polling** (any hosting whose PHP worker pool can hold open requests):

```php
$enableLongPolling = true;
```

**Near-instant push notifications** (requires a VPS able to run a permanent Node.js process). A small Node server watches the `saves/` directory and notifies connected players through WebSocket the moment a move is saved; regular polling keeps running as a safety net. See [`push/README.md`](push/README.md) for deployment instructions, then declare the WebSocket URL:

```php
$pushWsUrl = "wss://example.org/push/";
```

## Related projects

- [jocly2](https://github.com/fhoudebert/jocly2) — the Jocly board game library (games, 2D/3D views, AI)
- [jcfrog/jocly-simple-match](https://github.com/jcfrog/jocly-simple-match) — the original experiment this project is based on
- [tabulon](https://github.com/fhoudebert/tabulon) — Tabulon can play remotely with a joclymatch server
## License

AGPL-3.0 (see `package.json`) — Tabulon builds on the Jocly library and JoclyBoard, both AGPL.
