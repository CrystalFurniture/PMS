# AI quotation assistant

The DP and MRP product screens (`CreatedOrderDp.php`, `CreatedOrderMRP.php`) have an **Import customer requirement** panel. The employee pastes a WhatsApp message and/or uploads photos or screenshots of a customer's list. The AI reads the items, the server matches them to the catalogue, and the employee reviews every row before approved rows go into the normal quotation cart. Saving the quotation still happens from the cart page.

Nothing is added to the cart during reading, matching or review. Quotations are never saved, invoiced or sent by the assistant.

## Flow

1. **Input** (`ai_import.php?action=create`). Text and up to 5 images are checked on the server and stored privately under generated names.
2. **Extraction** (`action=process`). Images and text go to the AI provider, which returns structured rows. The server re-checks every field: quantities must be whole numbers from 1 to 9,999, otherwise null. It does not assume quantity 1.
3. **Matching** (same request). The server searches the catalogue separately for each row (exact code/name, then name, tags, category, colour, dimensions and description words). It sends the model **only that row's candidates** (at most 12 by default). Every product id the model returns is checked against that list and the database.
4. **Review** (`AiReview.php`). The original text and images are shown beside an editable table. Changing a row's requirement, quantity or product clears its approval. Rows without a quantity or a chosen product cannot be approved.
5. **Add approved items** (`action=preview`, then `action=apply`). The server rebuilds the plan from database prices and the current cart. It shows repeats and existing cart lines, which need an explicit choice. It then replaces the cart in one step. A one-time plan token stops double clicks or retries from adding the batch twice.

### Quantity rules

- **Default quantity (`default_quantity` in the config; 0 = off):** a line that gives no quantity at all gets this value, for example 1.
  - The row says "Quantity not given in the source: set to 1 by default", and approving doesn't need an extra confirmation.
  - Unreadable, unclear or contradictory quantities, quantities written in words the AI didn't convert, and crossed-out or non-item lines stay blank.
  - The benchmark reports defaulted quantities separately.
- **`default_quantity_scope`:**
  - `missing` (the default): only lines with no quantity written get the default quantity.
  - `all`: every line without a clean quantity gets it. That includes unreadable, unclear or contradictory quantities, quantities written in words, and numbers that look like a size, serial number or price.
  - With `all`, the row warning says what the source had, for example "Quantity set to 1 by default: read as 1800 (looks like a size). Change it if needed."
  - No confirm step is needed; staff correct the quantity on the review screen if necessary.
  - A clean written quantity such as "5 nos" is always kept. Crossed-out and non-item lines get no quantity.
- With `default_quantity` at 0, a quantity the AI could not read stays empty. It is never assumed to be 1.
- A quantity the AI did read is kept exactly as read, but it must be confirmed by the employee before the row can be approved if:
  - it also appears as a size (1800x900, 6 ft, 450 mm);
  - it is the line's serial number ("1)", "3.");
  - it sits next to a price or total (₹, Rs, rate, total, कुल, एकूण, रुपये);
  - it is not written anywhere in the source line;
  - the AI itself marked it unclear or contradictory.
- The review screen shows a **Confirm N** button for these. Typing a different number also counts as confirming.
- Devanagari digits (५, १०) and number words (पाच, दहा) are accepted when the AI reports how the quantity was written.

### Corrections in any language

- When the employee corrects a requirement (English, Hindi, Marathi or mixed), their wording is kept as typed. The row's approval is cleared and it is marked for rematching.
- **Rematch** re-reads the corrected wording with the same extraction prompt to get an English catalogue reading. It then runs the normal catalogue matching, so the model sees both the employee's wording and the English reading.
- If the re-read fails, matching uses the words as typed and the screen says so.
- Manual product search is always available.

### Staff instructions

- **Two kinds:**
  - *Instructions for this requirement*: a box on the import panel, up to 1,000 characters, for example "All beds are king size."
  - *Standing instructions*: entered on **AI Assistant** (sidebar), up to 2,000 characters, by users with the `createProduct` permission (the admin group). They apply to every import.
- **How they're sent:** both go to the AI as a separate `<staff_instructions>` block, kept apart from the customer's text. They're used by the reading step, catalogue matching and the re-reading of corrected rows.
- **Priority:** the customer's own wording takes priority, and conflicts are noted on the row.
- **Visibility:** each row shows "Staff instruction applied: …", and the review screen lists the instructions the import used.
- **Snapshot:** each import keeps a copy of the instructions it was started with, so later edits don't change earlier results.
- **Privacy:** the instruction text is sent to the AI provider but not logged; the log records only its length.

### State price lists

- **One-time setup per database:** `php cli/migrate_state_pricelists.php --database=<name>`. It creates `price_states` and `state_pricelist` and leaves `pricelist` and `tbl_quotation` unchanged. It refuses the live `pms` database without `--i-approve-live`.
- **Uploading a list:** **State Price Lists** (sidebar, `createProduct` permission). Upload one Excel/CSV per state in the standard price-list format (header row with Code or Item Code, MRP Price, Dealer or DP Price, and optionally Special Price; all sheets are read).
  - **Matching:** each Code must equal a product name, ignoring case and spaces.
  - **Problems are reported, never guessed:** codes shared by several products, unknown codes, bad prices and duplicates.
  - **Preview first:** a preview shows every problem before anything is saved, and uploading a state again replaces its list.
- **Choosing the price list:** on the DP/MRP quotation screens, the **Price list** selector picks Standard or a state while the cart is empty. Once products are added, the list is fixed for that quotation; **Start a new quotation** changes it.
- **Where state prices apply:** the product list, manual Add, AI review, preview and apply, and the cart.
- **Products not in the state's list:** these use the standard price, shown with the warning "Not in the … price list: standard price used" on the product list (`*`), the review row and the cart line.
- **Saved quotations:** each saved line records its price list (`price_list`) inside `q_items`. Reopening a quotation restores that list. The printed quotation does not mention the price list.
- **Stale imports:** an AI import started with one price list is rejected if the quotation's price list has changed in another tab.

### Matching rules

- A product is preselected only when the model says one candidate clearly fits **and** no other quotable product has the same name. Otherwise the row says *Needs review* and the employee chooses.
- Generic wording that fits several products (for example "office table") stays *Needs review*.
- A requested finish or size that the catalogue's colour or dimension fields do not show is flagged as a warning.
- Products with no price row, or more than one, are never offered. A warning appears if the customer named one.
- The model gives a short reason, not a confidence percentage.
- Lines the AI marked as crossed out or not an item (greetings, totals) are not matched automatically.
- The catalogue search index is cached in `storage_dir/cache/` and rebuilt when a checksum of `products`, `pricelist` or `categories` changes. Building it takes about 2 seconds for the full catalogue; a cached load takes about 0.03 seconds. Prices and product details shown or added to the cart are always read fresh from the database, never from the cache.

### Server-side checks on add

Every add re-checks all of the following:
- login, POST and the CSRF token;
- the import belongs to this user and this login session;
- the quotation type, and whether a quotation is being edited (an import started for a different quotation context is rejected);
- each row's revision, product and quantity;
- current database prices. If a price changed since review, the row's approval is cleared and the new price is shown.

Prices from the browser or the model are never used.

### Cart handling

The cart keeps one line per product:
- **Several rows choosing the same product:** the employee chooses *combine* or *use only the first row*. If those rows asked for different finishes or sizes, the screen says the difference is not recorded and must be noted manually.
- **A product already in the cart:** the employee chooses *add*, *replace* or *leave unchanged*. An existing line keeps its price, which matters when editing a saved quotation.

## Setup

1. **PHP extensions:** `curl` (with HTTPS), `fileinfo` and `mbstring`. Code is written for PHP 7.0+. The official Anthropic PHP SDK is not used because it needs PHP 8.1+ and Composer.
2. **Private folder** outside the web root, writable by the web server only, for example `C:/xampp/pms_private/ai_storage`.
3. **Config file:** copy `docs/ai_config.sample.php` to `C:/xampp/pms_private/ai_config.php`, or anywhere else and point the `PMS_AI_CONFIG` environment variable at it (Apache: `SetEnv PMS_AI_CONFIG "D:/secure/ai_config.php"`). Fill in:
   - `api_key`: a Claude API key from the Claude Console;
   - `model`: required, there is no built-in default. The sample uses `claude-opus-5-5`;
   - `storage_dir`.
4. **PHP upload limits:** `upload_max_filesize` must be at least `max_image_bytes`, and `post_max_size` at least `max_total_image_bytes` plus 1 MB.
5. **Cleanup:** schedule `php cli/ai_cleanup.php` hourly with `PMS_AI_CONFIG` set.

If the config is missing or incomplete, the panel shows why and the manual product list works as before.

## Config keys and what they send to the API

The config file uses our own key names. Only some of them become fields in the Claude API request:

| Config key (ai_config.php) | API request | Notes |
|---|---|---|
| `api_url` | Request URL | `https://api.anthropic.com/v1/messages`. Plain HTTP is allowed only for a loopback test server |
| `api_key` | Header `x-api-key` | Never logged |
| (fixed) | Header `anthropic-version: 2023-06-01`, `content-type: application/json` | |
| `model` | Body `model` | Required; e.g. `claude-opus-5-5` (vision supported; thinking always on; default effort `medium`) |
| `max_tokens` | Body `max_tokens` | 16000, non-streaming |
| `effort` | Body `output_config.effort` | Omitted when `''`. Set it explicitly for the benchmark |
| (fixed) | Body `output_config.format` = `{type: "json_schema", schema}` | Structured output; the JSON is read from the `text` content block |
| (fixed) | Body `system`, `messages` | Image blocks are base64 with `media_type` image/jpeg, image/png or image/webp |
| `refusal_fallback` | Body `fallbacks` + header `anthropic-beta: server-side-fallback-2026-07-01` | **Off (`''`) by default**, so answers come from one model. `'default'` turns it on |
| `connect_timeout`, `timeout`, `max_retries`, `retry_max_wait`, `total_deadline`, `max_response_bytes` | (none) | Client-side limits only |

### Checked against the official API documentation (2026-10-10)

| Topic | What the client does |
|---|---|
| Model ID and images | `claude-opus-5-5` is a current model ID; all current models accept image input (base64 blocks, up to 10 MB each on the direct API, 8000x8000 px, 32 MB per request) |
| Structured output | `output_config.format` with `json_schema`, no beta header; every object has `additionalProperties: false`; the extraction schema has 2 nullable fields (limit 16) |
| Stop reasons | `end_turn` is used. `max_tokens` and `model_context_window_exceeded` are reported as truncated. `refusal` is reported with its `stop_details.category` and explanation and is not retried. Anything else (`pause_turn`, `tool_use`, `stop_sequence`) is treated as an unexpected response |
| Refusals and fallback | Fallback is opt-in through the `fallbacks` field. The response `model` and `usage.iterations` (`fallback_message`) are checked, and any answer served by another model is logged as `fallback:1` |
| Errors | 429 with `retry-after` is retried; 429 **without** it (a spend cap) is not. 500, 504 `timeout_error` and 529 `overloaded_error` are retried. 400 spend limit, 401/403, 402 `billing_error`, 404 model not available and 413 are shown with a clear message. Every message includes the `request-id` for support |
| Long requests | Non-streaming, 120 s per attempt, TCP keep-alive on. The documented 10-minute non-streaming guidance is not approached |
| Tokens and cost | `usage.input_tokens` and `usage.output_tokens` (thinking tokens are billed as output) are recorded per call and added up across retried attempts |

Not verifiable without credentials: how an Opus 5.5 response actually looks with this schema and images, real latency, and real refusal behaviour. The first live benchmark sample confirms these.

## What is sent to the AI provider

Requests go to the Claude API (`https://api.anthropic.com/v1/messages`) only:

- **Reading:** the pasted text and the uploaded images, exactly as provided, with fixed instructions. This can include customer names, phone numbers or addresses if they appear in the message or photo.
- **Matching:** each row's extracted requirement (description, code, size, finish, other specifications) and, for each candidate, product id, name, category, colour, dimensions and up to 300 characters of plain-text description. Prices, customer details, user names and quotation data are not sent.

See Anthropic's commercial terms and privacy documentation for how API inputs are retained. Content sent through the API is not used to train models.

## Storage and retention

- Uploads and drafts live in `storage_dir/drafts/<random id>/`, never in the web root, and are served only to the employee who created them (`ai_import.php?action=image`).
- Images are deleted as soon as the approved items are added to the cart, or when the import is discarded.
- Everything else is deleted after `retention_hours` (default 24). This happens on each new import and when `cli/ai_cleanup.php` runs.
- `storage_dir/logs/ai-YYYY-MM.log` records metadata only: time, event, draft id, user id, HTTP status, duration, token counts, served model, `request-id`, refusal category and outcome. Customer text, images, product lists and model output are never logged.
- When items are added to the cart, the log records **correction counts** for the pilot: rows, rows approved, rows removed, AI-chosen products kept, and edits by type (`edit_quantity_changed`, `edit_quantity_confirmed`, `edit_product_chosen`, `edit_requirement`, `edit_rematch`, and so on).

## Limits (configurable)

| Setting | Default |
|---|---|
| Images per requirement | 5 (JPG, PNG, WebP; content and MIME type checked) |
| Size per image / per requirement | 7 MB / 20 MB |
| Image dimensions | 200 to 8000 px per side |
| Pasted text | 20,000 characters |
| Rows kept per requirement | 100 (the reviewer is told if more were found) |
| Candidates per row sent to the model | 12 |
| Timeouts | 10 s connect, 120 s per attempt, 300 s overall |
| Retries | 2, for rate limits, overload, server errors and timeouts (honours `retry-after`) |
| Largest AI response accepted | 2 MB |

## Failure handling

| Situation | What the employee sees |
|---|---|
| Config or API key missing | Panel explains; manual workflow unchanged |
| Invalid upload | Specific message (type, size, dimensions, count); nothing stored |
| Timeout, rate limit, overload, network error | Retried within limits, then "retry or add manually" on the review page |
| Response invalid, truncated or declined | Clear message and a retry button |
| Matching call fails after reading worked | Rows stay reviewable with the closest catalogue matches as choices |
| Browser closed mid-way | Processing finishes on the server; the review page resumes, or offers retry if the server was interrupted |
| Unreadable image / no items found | Notes on the source are shown; nothing to approve |

The PHP session lock is released before every AI call, so other tabs keep working. Results are written back only after re-checking the draft. A rematch result is discarded if the row was edited meanwhile.
