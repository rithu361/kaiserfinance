# Roadmap

Improvements are shipped one at a time, roughly hourly, each as its own version.

## Done
- 2.3.3: sturdier update check with visible status
- 2.4.0: chart tooltip (hover, tap, arrow keys)
- 2.5.0: YouTube and Instagram links
- 2.6.0: TradingView chart links on holdings
- 2.7.0: speed (compact data, deferred script, no layout jump)
- 2.8.0: tables fit on phones
- 2.9.0: "prices may be delayed" note
- 2.9.1: tooltip closes when tapping outside the chart (phones)
- 2.9.2: centred footer on phones
- 2.10.0: smoother chart (cross-fades, draw-in)
- 2.10.1: tooltip animation
- 2.10.2: benchmark lines glide instead of swapping charts
- 2.11.0: header rehaul (display menu, login in footer)
- 2.12.0: time ranges glide (zoom) instead of cross-fading
- 2.12.1: calmer, warmer dark mode
- 2.13.0: backup, restore with undo, CSV export
- 3.0.0: beige design overhaul, new wordmark and icon
- 3.0.1: gold removed, new ink icon
- 3.1.0: exchange rate auto-fill in the cash form
- 3.1.1: light mode colours back to the original, new logo kept
- 3.1.2: one-colour logo and icon
- 3.1.3: logo spelled Kai$erFinance
- 3.1.4: bold logo
- 3.2.0: logo font Playfair Display Bold

## Next up (in rough order)
1. **Profit per position (requested):** holdings list shows each position's return since it was opened, best first. Tap a row to expand: opened date, holding period, gain in money (only if "every holding" is public), share of the portfolio. Purchase prices and individual trades stay private. Phones: compact rows, details on tap.
2. **Customizable text:** headline, intro and disclaimer in English and German from Settings
3. **Fees on trades:** optional fee field in the trade form, paid from cash
4. **Edit cash entries,** not only delete them
5. **Risk stats:** maximum drawdown and best/worst month
6. **Monthly returns grid** (fund-factsheet style)
7. **Accessibility pass:** keyboard and screen-reader support for chart, chips and switches
8. **No-JavaScript fallback:** a plain table of returns
9. **Automated checks:** PHP/JS syntax and maths tests run on every push (GitHub Actions)

## Integrations: decisions
- **TradingView widgets: no.** They load TradingView's script and cookies on every visit, slow the page, show TradingView's branding, and their prices can differ from ours (Twelve Data), which would confuse visitors. Instead: plain "open on TradingView" links per holding (2.6.0).
- **YouTube / Instagram: yes,** as plain links (2.5.0). No embedded feeds: those need API tokens and load trackers.
- **Later, if wanted:** newsletter sign-up via a hosted form link (e.g. Buttondown), privacy-friendly visitor counts (Plausible or Cloudflare Web Analytics, no cookies), CSV import from a broker export, and an RSS/JSON feed of monthly returns.


## Notes
- German numbers use the Swiss style on purpose (5.86 and 64'868), matching kaiserfinance.ch's audience. Switching to 5,86 would be a one-line change (locale de-CH → de-DE).
