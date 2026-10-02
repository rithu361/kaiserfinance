# Kai$erFinance

WordPress plugin behind [kaiserfinance.ch](https://kaiserfinance.ch): a private trade ledger with a public performance page compared against the S&P 500 and other benchmarks, in USD or CHF.

- Private ledger for trades and multi-currency cash, with overdraft protection
- Time-weighted return of the whole account, cash included
- Public page: English/German, light/dark, benchmark chips, returns by period, optional amounts
- Prices from Twelve Data (free plan), refreshed hourly on visits
- Link-preview image and favicon

## Updates

Installed sites update themselves from this repo: when the `Version:` in `kaiserfinance.php` goes up on `main`, WordPress shows "Update available" under Plugins, or installs it automatically if auto-updates are on. The repo (`rithu361/kaiserfinance`) is set under Kai$erFinance → Settings → Updates from GitHub.

## About this repository

Kai$erFinance was developed by [shokulab](https://shokulab.ch), who wrote the plugin: the ledger, the performance calculations, the public page, the updater and the backups.

In October 2026 the repository was transferred from `shokulab/kaiserfinance` to [`rithu361/kaiserfinance`](https://github.com/rithu361/kaiserfinance). shokulab does not stand behind the visual design choices made from version 3.0.0 onward; they were made at the client's request. For that reason the "Powered by shokulab" credit was removed from the site (3.3.1) and the project now lives under the client's account.

shokulab's name, logo and links must not be added to the site; see `AGENTS.md` (also read by AI coding tools).
