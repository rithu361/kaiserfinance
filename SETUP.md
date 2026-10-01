# Kai$erFinance setup and day-to-day

The plugin runs on the WordPress at https://kaiserfinance.ch (TrueNAS app `kaiserfinance`, port 30081, behind the Cloudflare tunnel "Home").

## One-time setup (done)
- [x] WordPress installed, address set to https://kaiserfinance.ch
- [x] Plugin installed, homepage drawn by the plugin (Settings → Homepage design)
- [x] Cloudflare Access with email code on `/wp-admin` (login page branded "shokulab")
- [x] Updates from GitHub: `shokulab/kaiserfinance`
- [ ] Twelve Data API key: Kai$erFinance → Settings
- [ ] Cloudflare Access: also protect `wp-login.php`
- [ ] Plugins → Kai$erFinance → **Enable auto-updates**

## Logging money (Kai$erFinance → Ledger)
1. **Cash first.** Deposit in the currency it arrived in (e.g. Deposit · CHF · 10'000).
2. **Exchange before buying in another currency.** Gold is priced in USD, so log a currency exchange CHF → USD first.
3. **Trades.** Search the asset, pick the right listing (exchange + currency shown), enter quantity and price per unit in the asset's currency. The form shows the cash available and refuses buys that would overdraw.
4. Interest/dividends and fees are cash entries of their own.
5. Assets not on the free price plan (e.g. ASX) are marked "Manual price"; update their price in the Holdings table now and then.

Metals are per troy ounce (1 kg = 32.1507 oz).

## What the public sees (Kai$erFinance → Settings)
- **Public amounts:** percentages only / plus totals / plus every holding.
- **Allocation:** optional percentage split.
- Visitors choose USD or CHF, English or German, light or dark, and which benchmarks to compare with (S&P 500 by default).
- Trades, purchase prices and notes are never public.

## How returns are calculated
- Time-weighted return of the whole account, cash included; deposits and withdrawals don't count as performance.
- Benchmarks over the same days via funds on the free plan: S&P 500 (SPY), Nasdaq 100 (QQQ), MSCI World (URTH), Switzerland (EWL), gold, Bitcoin.
- In CHF, values are converted daily, so the dollar–franc effect is included.

## Prices
- Twelve Data free plan, refreshed about hourly when the site gets visits. Quiet periods use no requests; the next refresh fills in missed days.
- If Cloudflare caches pages, bypass the cache for the homepage.

## Updates
- New versions are pushed to https://github.com/shokulab/kaiserfinance.
- WordPress checks about twice a day; with auto-updates on it installs them by itself.
- In a hurry: Dashboard → Updates → **Check again** → update.
- Ledger data and settings live in the database and are never touched by updates.

## Reset
Settings → Reset ledger: type `RESET`, confirm in the window. Deletes all trades, cash, assets and prices; keeps settings and the API key.
