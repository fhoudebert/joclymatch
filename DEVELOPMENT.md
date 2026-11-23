# JoclyMatch — developer notes

What is not needed to install and play: settings in detail, the relay protocol, the chat format
and its encryption, and what to know to write another client. For installation, see
[README.md](README.md) ([LisezMoi.md](LisezMoi.md) in French).

- [Settings in detail](#settings-in-detail)
- [Other clients](#other-clients) — dialects, chat envelope, taking back moves
- [Chat protection](#chat-protection)
- [Faster move notifications](#faster-move-notifications)
- [Tests](#tests)

---

## Settings in detail

All of them are in `localconf.php.example`, with their default values.

### `localconf.php` encoding

Save `localconf.php` as **UTF-8 without BOM** (the example is plain ASCII). Some editors silently
switch to UTF-16 as soon as a character does not fit their default charset; PHP then does not
read the file at all, and no setting is applied. In that case — and if the file is missing or a
required setting is absent — the pages say so plainly (an HTML page explaining the cause, a JSON
error from `fileio.php`, see `localconf-check.php`) instead of breaking. To convert:

```sh
iconv -f UTF-16 -t UTF-8 localconf.php > localconf.tmp && mv localconf.tmp localconf.php
```

### Where to put match files

`$savePath` holds every match and chat file, named after the match id. Place it **outside the
web root** when the host allows it. Otherwise copy `saves.htaccess.example` into it as
`.htaccess`: it denies all HTTP access (Apache 2.2 and 2.4), listing included — a listing would
hand out every match id, and with it the right to play either side. Nothing breaks: `fileio.php`
and `push/server.js` read these files from the filesystem, never over HTTP. Under nginx the file
is ignored; use `location ^~ /joclymatch/saves/ { deny all; }` instead.

Checked on Apache 2.4 with `AllowOverride All` and `Options +Indexes`: with the file, the
directory, a match file and the `.htaccess` itself answer 403; without it, 200.

### Expiry and limits

Match and chat files expire, and the limits below can be tuned — all of them are
optional, and the defaults are what you get without writing anything.

```php
// How long an untouched match file is kept. The common case is the game
// abandoned in silence, which no client is ever going to close.
$saveTTL       = 30 * 24 * 3600;   // 30 days

// Largest match state accepted in one save.
$saveMaxBytes  = 1048576;          // 1 MB

// Largest chat log for one match. The file is appended to, so without a
// bound it grows for as long as the game lasts.
$chatMaxBytes  = 262144;           // 256 KB

// Once that bound is reached, the OLDEST messages make room for the new
// ones instead of the log closing. A correspondence game lasts weeks, so
// the bound is a normal end of the file, not a rare case — and a
// conversation frozen mid-game is worse than a forgotten beginning.
// Clients keep on screen what they have already received, so nothing
// disappears under a reader's eyes; what is lost is what someone opening
// the match *now* would see. Set to false to keep the old refusal.
$chatTrimOldest = true;

// How far down to trim when it happens. Going just under the bound would
// rewrite the whole file on every message; a quarter at a time means one
// rewrite per few hundred messages.
$chatTrimTo    = 196608;           // 192 KB (75% of $chatMaxBytes)
```

The clean-up is opportunistic and bounded — there is no cron on shared
hosting, so a handful of files are checked on each save rather than the whole
directory. A client that knows the game is over can also delete it outright:

```
POST gameioaction=drop  gameid=<id>     -> {"ok":true}
```

which removes the match file and its chat log together.

**This client does it for you.** When a game ends on a move *received* from
the other player, the page deletes the match (`drop`) **when it is closed** —
with `navigator.sendBeacon` on `pagehide`, since an ordinary request sent while
a page closes is not guaranteed to leave. Only the player who received the
last move does it: they know both sides have seen the end, whereas the one who
played it does not, and deleting from that side could remove the move before
the other reads it. On close rather than at the end, so that what is said
after the last move stays readable while the page is open; the end-of-game
message says so. Same rule as Tabulon and mogichex. If that player never comes
back, `$saveTTL` does the cleaning.

Saving a chat message answers `{"ok":true,"trimmed":n}`, where `n` counts the
oldest messages dropped to make room for this one — zero almost always. Two
refusals remain, and they do not call for the same reaction: **413**
`chat message too large` is about *that* message (it is bigger than the whole
log, so no trimming would help — shorten it), while **413** `chat log full`
only comes from a relay where `$chatTrimOldest` is off.

## Other clients

Three applications speak this relay's format — joclymatch, [Tabulon](https://github.com/fhoudebert/tabulon)
and [mogichex](https://github.com/fhoudebert/mogichex) — so the endpoints accept
two spellings of the same operations, and the chat renderer tolerates messages
it did not write. A match lives on ONE relay: a Tabulon player joins a joclymatch
match here; mogichex ships its own `fileio.php` next to its own match files, so a
mogichex match is joined from Tabulon, not from this server.

**Two dialects, one behaviour.** `action/mid/data` is translated to
`gameioaction/gameid/gamedata` before anything else happens, `since` to
`sinceMtime`, and `X-Match-Mtime` is sent alongside `X-File-Mtime`. The
translation happens *before* the game-id validation, so neither spelling opens a
door the other closes. If both are present the original spelling wins, so an
existing client gets exactly what it got before.

**The chat envelope.** One JSON object per line, `{"data": {…}}`, appended by
the server:

| Field | Meaning |
|---|---|
| `msg` | the text, **or** a base64 seal when `enc` is set |
| `player` | `1` / `-1`, or `0` for service messages |
| `pseudo` | display name, free text — **absent** from sealed messages |
| `pseudoEnc` | optional: the display name, sealed with the same key (joclymatch only; other clients show the side) |
| `time`, `key` | `time + "-" + key` is the message id |
| `kind` | optional: `chat` when absent, else `presence`, `nudge`, … |
| `quick`, `state` | optional: identifiers translated by the reader |
| `enc` | optional: `1` means `msg` is sealed |

**What this client does with what it does not understand.** An unknown `kind` is
skipped in silence, so a fourth kind can be added without breaking installed
clients. A message with no textual `msg` is skipped rather than shown as
`undefined`. A sealed body (`enc:1`) is opened when the page has the match key;
otherwise it is shown as a padlock and an explanation, never as raw base64 — a
message you cannot read is still worth knowing about. A
missing `pseudo` falls back to "Player A" / "Player B".

**Taking back moves.** Whether a match allows it is a setting of the *match*,
not of a player. It is announced by the link (`tb=1` / `tb=0`, in the query
string) and then carried by the match file as `matchDetails.allowTakeback`
(boolean). The file wins over the link: it is the same for both players and
survives a reload or a truncated link. **Absent means forbidden**, as in mogichex
and Tabulon: the other end of such a match may be an older client that does not
follow a takeback. Receiving a takeback never depends on the setting. Both clients rebuild
`matchDetails` on each save, so a client writing the other end must **copy
`allowTakeback` back** into every save, or its first save erases the host's
choice. A takeback is a save with a *lower* `nbTurns` and the full state: load
it as is, never replay its last move. `nbTurns` is the number of moves played
(`getPlayedMoves().length`), not a counter: a takeback from this client lowers it
by two (the player's own move and the reply).

A takeback is only offered on your own turn: this client polls the relay only
while it waits for the opponent, so a takeback sent during its own turn would
never be seen, and its next move would overwrite it.

**Known limit.** A server still serving an older `js/control.js` ignores the
setting: its player can take back even in a match created with the box
unchecked. The setting is only enforceable by up-to-date clients.

Two things are worth knowing if you write the other end: a message must be **one
line** (the server reads the file line by line and rejects `\r` or `\n` with a
400), and `msg` is what a client that ignores `quick` will display — leaving it
empty puts an empty bubble in the thread.

## Chat protection

**What it protects, and from whom.** This relay has no accounts: whoever runs
the server, and whoever knows a match id, can read what is stored. So the chat
text of a match created from the games panel is **sealed in the browser before
it leaves the page**, and the server only ever stores opaque bytes.

- **The key.** `gamespanel.js` draws 32 random bytes per match and puts them in
  the **fragment** of both links: `index.php?…&player=a#k=<64 hex chars>`. A
  browser never sends the fragment to the server — not to `index.php`, not to
  `fileio.php`, not in a `Referer` header. Whoever has the full link has the key:
  send links privately, as you already do.
- **The seal.** XChaCha20-Poly1305 with a random 192-bit nonce per message,
  `msg = base64(nonce ‖ ciphertext ‖ tag)` and `enc: 1` — byte for byte the
  format of Tabulon and mogichex, so the three applications read each other in
  a shared match. The cipher is [`@noble/ciphers`](https://github.com/paulmillr/noble-ciphers)
  2.4.0 (MIT), vendored unmodified in `js/vendor/noble-ciphers/` (see its
  `PROVENANCE.md`), loaded by `js/chat-seal.js` as an ES module.
- **The name.** In a protected match the display name is sealed too
  (`pseudoEnc`) instead of travelling in clear with every message.
- **What stays visible to the server**: who writes (the side), when, the length
  of each message, Tabulon's quick messages and presence flags (they carry no
  personal text, by design), and the moves.
- **What the chat panel shows**: "🔒 Protected" / "Not protected" next to
  *My name*, with the explanation as a tooltip. A message received **in clear**
  in a protected match (an old client, a link that lost its fragment) is shown
  and marked "(sent without protection)". A sealed message that does not open
  with the match key says so. If the page has a key but the browser could not
  load the encryption, messages are **not sent** rather than sent in clear.
- **Rematch** links offered at the end of a game keep the key.
- **The key leaves the address bar once read.** `js/chat-seal.js` stores it in
  `sessionStorage` — per **tab**, cleared when the tab closes — under the match
  id (`joclymatch-chat-key:<mid>`), then removes `k`/`kid` from the fragment
  with `history.replaceState`. A reload finds it there; another tab opened on
  the cleaned address does not. So the key no longer shows on a shared screen,
  nor travels with an address copied from the bar. If storage is unavailable,
  the fragment is **left** in place (losing the key on the first reload would
  be worse). What it does **not** do: erase the browser's history, which keeps
  the visited address, key included (checked on Chromium) — like the message
  that carried the link.
- **Not covered**: a Tabulon *community key* (`#kid=` in the link) — joclymatch
  has no keyring, so such a match is shown as not protected; an older link
  without a key stays unprotected, as before.

Tests: `node tests/test-chat-seal.mjs` (no dependency) checks the seal, and
opens a message sealed by mogichex — the same vector Tabulon checks in its own
tests — so the three implementations are known to agree.

## Faster move notifications

By default, opponents' moves show up through periodic polling. Everything below is **disabled by default** and entirely optional — without these lines in `localconf.php`, the behavior is unchanged.

**Long-polling** (any hosting whose PHP worker pool can hold open requests):

```php
$enableLongPolling = true;
```

**Near-instant push notifications** (requires a VPS able to run a permanent Node.js process). A small Node server watches the `saves/` directory and notifies connected players through WebSocket the moment a move is saved; regular polling keeps running as a safety net. See [`push/README.md`](push/README.md) for deployment instructions, then declare the WebSocket URL:

```php
$pushWsUrl = "wss://example.org/push/";
```

## Tests

No dependency, no build:

```sh
php tests/test-fileio.php        # relay: dialects, expiry, limits, drop, chat trimming
php tests/test-localconf.php     # configuration diagnostics (missing file, UTF-16, BOM…)
node tests/test-chat-seal.mjs    # chat seal, including a message sealed by mogichex
```
