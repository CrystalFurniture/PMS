# AI quotation assistant: benchmark and pilot

This folder measures how well the assistant reads real customer requirements before it is used for real quotations. The tools run the same code as the app (`includes/ai_import_lib.php`, `includes/ai_provider.php`).

**Nothing here has been run against the real API yet.** Mocked test results say nothing about real reading accuracy.

## What is measured, separately

| Area | Measures |
|---|---|
| Extraction | Items found, missed, invented; crossed-out and non-item lines handled |
| Quantities | Exact; exact but flagged; wrong and flagged; wrong and **not** flagged; not read. Missing or unclear quantities: kept unresolved, flagged, or guessed |
| Catalogue matching | One right product: correct, offered, wrong, not offered. Several fit: correctly left to the employee, or wrongly preselected. Not in the catalogue: correctly unmatched, or wrongly matched |
| Employee work | Errors to fix and expected decisions (estimated), plus real correction counts from the pilot log |
| Cost and speed | Seconds, API calls, input/output tokens and cost per sample |

There is no single accuracy score. A wrong quantity that is **not** flagged, or a wrong product that is preselected, matters far more than a correct reading that needs one confirmation click.

## 1. Collect 20–30 samples

Aim for this mix (adjust it to what customers actually send):

| Kind | Count |
|---|---|
| Handwritten photos (including one multi-page, one with crossed-out lines) | 8 |
| WhatsApp screenshots | 6 |
| Pasted text: English / Hindi / Marathi / mixed | 3 / 3 / 2 / 2 |
| Photo plus text | 2 |

Across them, include:
- exact model codes;
- generic descriptions ("office table");
- duplicate-name products;
- items not in the catalogue;
- sizes such as 1800x900;
- serial numbers;
- prices and totals;
- missing or unclear quantities;
- greetings and other non-item lines.

### Anonymise before anything leaves the office

- **Photos and screenshots:** crop out or black out customer names, phone numbers, addresses, GST numbers, profile pictures and chat headers. Keep the item lines exactly as they were.
- **Text:** replace names, numbers and addresses with placeholders (`[NAME]`, `[PHONE]`).
- Record who did it in `samples.csv` (`anonymised_by`, `anonymised_on`). The runner skips samples without it.
- Keep samples and results **outside Git** (`benchmark/samples/` and `benchmark/results/` are ignored, but a folder such as `C:/pilot/benchmark` is safer).

Folder layout:

```
samples/
  samples.csv            (template: templates/samples.csv)
  ground_truth.csv       (template: templates/ground_truth.csv)
  S01/list.jpg
  S04/message.txt
```

## 2. Write the ground truth (before running)

Write one row in `ground_truth.csv` for every line in the source that matters, including crossed-out lines and non-item lines.

| Column | Meaning |
|---|---|
| `source_ref` | `text` or `image N` |
| `source_text` | The line as written; it is used to pair the line with extracted rows |
| `expected_presence` | `item`, `crossed_out` (must be flagged, not quoted) or `not_item` (greeting, total, address) |
| `expected_quantity` | The number the customer asked for; blank if not given |
| `quantity_status` | `clear`, `missing`, `unreadable` or `ambiguous` |
| `acceptable_product_ids` | Product ids from the **pilot** catalogue that would be correct, separated by `;` |
| `match_expectation` | `unique` (one right product, so preselecting it is correct), `choice` (several fit, so the employee must choose), or `none` (not in the catalogue) |

The example rows in the template are fictional and use test product ids; replace them all. Ideally the quotation employee writes the ground truth, because they know which products are acceptable.

## 3. Build the pilot database

Copy `templates/benchmark_config.sample.php` to `benchmark/benchmark_config.local.php` and fill it in. Then run:

```
php benchmark/pilot_db.php --config=benchmark/benchmark_config.local.php
```

This copies the catalogue (categories, products, price list, permission groups) from the source database **read-only**. It creates empty quotation tables and the listed pilot users. Customer quotations and real users are not copied. It prints catalogue facts that affect matching: unpriced products, products with several price rows, duplicate names, missing colours and sizes.

## 4. Run

1. Create a separate AI config for the benchmark (copy `docs/ai_config.sample.php`).
2. Keep `refusal_fallback => ''`, so every answer comes from the configured model.
3. Set `effort` explicitly.
4. Set a spend limit for the API workspace in the Claude Console as well as `max_usd` in the benchmark config.

Then run:

```
php benchmark/run.php --config=benchmark/benchmark_config.local.php --sample=S01 --live   # one sample first
php benchmark/run.php --config=benchmark/benchmark_config.local.php --live
php benchmark/score.php --results=<results folder printed by run.php> --truth=<samples>/ground_truth.csv
```

- The runner refuses the live `pms` database and any config with refusal fallback on.
- It refuses a real API without `--live`, and stops before a sample could exceed `max_usd`.
- Each result file records every API call: purpose, time, attempts, tokens, cost, served model, `request-id`, stop reason and refusal category.

**Rough cost before measuring** (Claude Opus 5.5, $4 / $20 per million tokens): about $0.10–$0.40 per sample, depending on image count, number of rows and thinking length, so about $3–$12 for 30 samples. Replace this estimate with the measured figure from the first run.

Check the **alignment** table in `report.md`. If a ground-truth line was paired with the wrong extracted row, add a line to an overrides file (`templates/alignment_overrides.csv`; `none` means not extracted), then score again with `--overrides=`.

## 5. Pilot in the browser

The benchmark measures the automatic part. The pilot measures the real workflow:

1. Run a copy of the app (not the live site) with its `DB/conn.php` pointing at the pilot database and `PMS_AI_CONFIG` pointing at the pilot AI config.
2. The quotation employee logs in with a pilot account and imports each sample through **Import customer requirement**. They review and correct as they normally would, then add to the cart. Nothing they do touches live data.
3. Every completed import writes one metadata line to `<storage_dir>/logs/ai-YYYY-MM.log` (event `apply`). It records rows, rows approved, rows removed, AI products kept, and edits by type (`edit_quantity_changed`, `edit_quantity_confirmed`, `edit_product_chosen`, `edit_requirement`, `edit_rematch`, and so on). These are the measured correction counts.
4. Note anything the counts don't show: confusing warnings, slow steps, wrong translations.

Keep real-API pilot runs separate from the mocked test suites. Report results only from the real runs.
