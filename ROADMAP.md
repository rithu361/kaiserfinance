# Roadmap

Improvements are shipped one at a time, roughly hourly, each as its own version.

## Done
- 2.3.3: sturdier update check with visible status
- 2.4.0: chart tooltip (hover, tap, arrow keys)

## Next up (in rough order)
1. **Speed:** load the script deferred, cache the public numbers as one blob, lighter first paint
2. **"Prices delayed" note** on the public page if prices are more than 6 hours old
3. **Customizable text:** headline, intro and disclaimer in English and German from Settings
4. **Fees on trades:** optional fee field in the trade form, paid from cash
5. **Edit cash entries,** not only delete them
6. **CSV export** of trades and cash (backup) from the ledger
7. **Risk stats:** maximum drawdown and best/worst month
8. **Monthly returns grid** (fund-factsheet style)
9. **Accessibility pass:** keyboard and screen-reader support for chart, chips and switches
10. **No-JavaScript fallback:** a plain table of returns
11. **Automated checks:** PHP/JS syntax and maths tests run on every push (GitHub Actions)
