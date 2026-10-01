# Roadmap

Improvements are shipped one at a time, roughly hourly, each as its own version.

## Done
- 2.3.3: sturdier update check with visible status
- 2.4.0: chart tooltip (hover, tap, arrow keys)
- 2.5.0: YouTube and Instagram links

## Next up (in rough order)
1. **TradingView links:** each public holding links to its TradingView chart (plain link, no TradingView script)
2. **Speed:** load the script deferred, cache the public numbers as one blob, lighter first paint
3. **"Prices delayed" note** on the public page if prices are more than 6 hours old
4. **Customizable text:** headline, intro and disclaimer in English and German from Settings
5. **Fees on trades:** optional fee field in the trade form, paid from cash
6. **Edit cash entries,** not only delete them
7. **CSV export** of trades and cash (backup) from the ledger
8. **Risk stats:** maximum drawdown and best/worst month
9. **Monthly returns grid** (fund-factsheet style)
10. **Accessibility pass:** keyboard and screen-reader support for chart, chips and switches
11. **No-JavaScript fallback:** a plain table of returns
12. **Automated checks:** PHP/JS syntax and maths tests run on every push (GitHub Actions)

## Integrations: decisions
- **TradingView widgets: no.** They load TradingView's script and cookies on every visit, slow the page, show TradingView's branding, and their prices can differ from ours (Twelve Data), which would confuse visitors. Instead: plain "open on TradingView" links per holding (next up).
- **YouTube / Instagram: yes,** as plain links (2.5.0). No embedded feeds: those need API tokens and load trackers.
- **Later, if wanted:** newsletter sign-up via a hosted form link (e.g. Buttondown), privacy-friendly visitor counts (Plausible or Cloudflare Web Analytics, no cookies), CSV import from a broker export, and an RSS/JSON feed of monthly returns.
