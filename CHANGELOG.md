# Changelog

## 2.9.0
- If prices are more than 6 hours old (for example the price service was unreachable), the public page says so: "Prices may be delayed: last updated 12 hours ago", in English and German.
- "Prices as of" now shows the last successful update, not just the last attempt.

## 2.8.0
- Phones: the "Returns by period" table turns on its side (one row per period), so it fits the screen without sideways scrolling, even with several benchmarks switched on.
- Phones: tighter tables; the holdings table fits too, with the ticker under the name.

## 2.7.0
- Faster homepage: the numbers are sent as compact JSON instead of an escaped HTML attribute, and chart lines use two decimals (page about 20% smaller now, more as history grows).
- The script loads in parallel with the page (deferred, in the head).
- Space for the block is reserved while it loads, so the footer no longer jumps.
- Back/forward navigation can show the page instantly; it still asks for fresh prices on every visit.

## 2.6.0
- Holdings and allocation on the public page link to each instrument's chart on TradingView (new tab, small arrow icon).
- Plain links only: no TradingView script or cookies on the site. Cash and manually priced items have no link.

## 2.5.0
- YouTube and Instagram links in the homepage footer, translated, editable under Settings → Social links (empty = hidden).
- Search engines get schema.org data linking the site to those accounts.
- Plain links only: nothing is loaded from YouTube or Instagram, so no trackers.

## 2.4.0
- Chart tooltip: hover, tap or use the arrow keys to see the date and every line's value, with a guide line.

## 2.3.3
- Update check asks both GitHub's raw file and its API, so it works even if one is unreachable or cached.
- Settings shows the result of the last update check, with a "Check GitHub now" link.

## 2.3.2
- Updates keep working even if the "Updates from GitHub" field was saved empty.

## 2.3.1
- After an update, cached numbers are cleared and missing prices (e.g. new benchmarks) are fetched on the next visits instead of waiting an hour.
- Benchmark chips appear only once a benchmark has prices.

## 2.3.0
- Changelog now shows in WordPress under Plugins → Kai$erFinance → View details.
- Setup notes brought up to date (cash, benchmarks, public amounts, GitHub updates).

## 2.1.1
- Updates come from GitHub (shokulab/kaiserfinance) out of the box.

## 2.1.0
- Visitors choose benchmarks themselves: S&P 500 by default, plus Nasdaq 100, MSCI World, Switzerland, gold and Bitcoin as extra lines.

## 2.0.0
- USD / CHF switch, including the dollar–franc effect.
- Optional public amounts: totals or every holding.
- Link-preview image for WhatsApp, LinkedIn & co.
- Self-updates from GitHub.
- Reset ledger with a confirmation window.

## 1.8
- Cash currency dropdowns; buys and sells settle in cash; no overdrawing.
- Price field hints the last known price and the cash available.

## 1.7
- Login icon in the header; gold $ favicon.

## 1.6
- Light/dark switch with a soft fade; language fade.

## 1.5
- Multi-currency cash, time-weighted returns, 7D–All periods.

## 1.0–1.4
- Ledger, hourly prices from Twelve Data, public page in English and German.
