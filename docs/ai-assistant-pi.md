# AI Assistant and Proforma Invoices

The AI Assistant (menu: Quotations › AI Assistant, page `AiAssistant.php`) turns a customer requirement into a Proforma Invoice (PI) or a quotation through a chat.

- **Input:** pasted text, attached photos or screenshots, and corrections in plain words (English, Hindi, Marathi or mixed).
- **Where things live:** the server keeps the conversation and one authoritative draft per conversation.
- **The AI's role:** it only chooses operations from a fixed list. The server checks and runs them, and it supplies every price, total, tax line, term and bank detail.

## Setup (per database)

```
php cli/migrate_state_pricelists.php --database=NAME   # if not done yet
php cli/migrate_pi.php --database=NAME                 # refuses "pms" without --i-approve-live
```

`migrate_pi.php` creates these tables:

| Table | Holds |
|---|---|
| `ai_conversations`, `ai_messages` | chat history, one owner per conversation |
| `ai_pi_drafts` | the draft as JSON, with a version number and the confirmation |
| `pi_documents` | generated documents: an immutable snapshot plus the PDF file name |
| `doc_templates` | company, bank, terms, signatory and tax details |

It also adds `version` and `valid_until` to `price_states`. Every upload of a state list raises its version; leaving `valid_until` empty means the list doesn't expire.

`doc_templates` is seeded once from the quotation print template in use (`ExportToExcelDp.php`). A change is added as a new version rather than edited in place, and each PI records the versions it used.

The AI configuration is the same file as for the import feature (see `ai-quotation-assistant.md`). Attached images and PDFs are stored under `storage_dir/chat/` and `storage_dir/pi/`. The download link `PiDownload.php?id=` works only for the owner; if the PDF file is missing, it's rebuilt from the snapshot.

## Rules enforced by the server

- **Prices** come from `state_pricelist` for the chosen state list, or from `pricelist` when the employee explicitly chooses the standard list.
  - A product with no rate in the chosen state list blocks generation until the employee picks another product or presses **Use standard rate** for that item.
  - A rate is never taken from another state's list.
  - A missing, inactive or expired state list is explained and not used.
- **The pricing state and the customer's billing state** (place of supply, used for tax) are separate fields.
  - If the customer state is empty, it's taken from the GSTIN state code.
  - When the pricing state differs from the customer state, the preview warns.
- **Totals:**

  | Line | Calculation |
  |---|---|
  | Total | sum of quantity × rate |
  | Discount | 0–35%, only a number the employee typed |
  | Charges | only amounts the employee typed |
  | Basic | total − discount + charges |
  | GST | basic × the `tax` template rate (18%), shown as one line like on current quotations |

- **₹0 rates** need an explicit confirmation per item. Unclear quantities stay blank, or follow `default_quantity`.
- **Generation** needs one click on **Confirm** on the current preview. The preview carries the draft version and a hash of the lines, prices, customer, price list and templates.
  - Any change, including a price-list upload, invalidates it.
  - A repeated click returns the same document.
- **PI numbers** are `Cry/<financial year>/PI-<n>`, with the financial year running April to March (for example `Cry/2026-27/PI-1`). **This format is provisional.**
- **Quotations** created by the assistant use the existing quotation numbering and print page.

## Tests

- **Mocked:** `chat_e2e.php` (integration) and `chat_browser.mjs` (browser), in the QA scratch folder.
- **Real-AI trials:** run separately, and recorded as such in the delivery notes.

## Product retrieval (stage 2)

Candidates for each requirement line are the union of three searches, bounded to `candidates_per_row` (12):

1. **Exact code or name.** Always first.
2. **Keyword search** over name, tags, category, description and colour. It weights rare words more, accepts spelling variants such as Wallnut/walnut, and gives a bonus when every type word matches.
3. **Semantic search** with Voyage AI multilingual embeddings, merged by reciprocal-rank fusion.

Every id is re-checked against the current catalogue. The model compares the candidates. The server separately flags requested sizes, finishes and specifications that the chosen product's catalogue record doesn't show; similarity never confirms them.

**Setup for semantic search** (optional: without it, keyword and exact search are used):
- **Key:** create a model API key in MongoDB Atlas (AI Model APIs) or on the Voyage AI platform. This is separate from the Claude key; Anthropic has no embedding model.
- **Config:** in `ai_config.php`, set:
  - `embed_api_key`;
  - `embed_api_url`: `https://ai.mongodb.com/v1/embeddings` for Atlas keys, `https://api.voyageai.com/v1/embeddings` for Voyage keys;
  - `embed_model` `voyage-4-lite` and `embed_dimension` 512, which are the defaults.
- **Build the index:** `php cli/index_products.php`. About 4,000 products is roughly 0.3M tokens: free within Voyage's 200M free tokens, otherwise about $0.01.
- **Index storage:** the `product_index` table, one row per quotable product id. It records the indexed text, its hash, the model and dimension, the vector and the indexing time.
- **Keeping it current:** a changed product (hash differs) or a different model is left out of semantic search until re-indexed. Up to `embed_auto_index` changed products are re-indexed automatically before each search. Deleted products are removed. Status and an "Update index now" button are on **AI settings**.

**Measuring:**
- `php benchmark/retrieval_eval.php LABELS.csv` reports recall@1/5/12 and MRR for the old keyword scoring, the current keyword scoring and the hybrid search, separately for each `source`.
- `php benchmark/pi_feedback.php` reports, from finalized PIs, automatic matches kept, matches corrected by the employee, and unresolved lines resolved by the employee.
- `benchmark/labels/retrieval_template.csv` is the format for labelling real requirements.

## Approved documents (stage 3)

**Approved documents** (Admin menu, `ApprovedDocuments.php`) stores company documents. Each one has:
- a type: PI terms, warranty, delivery, payment, installation or other;
- a version and effective dates;
- the states it applies to: ALL, or GST codes such as 27,24;
- a source location.

Only an administrator's **Approve** makes a version usable, and approving a new version retires the previous one. Approved text is split into chunks, one per term or paragraph, with vectors when semantic search is on.

- **PI terms:** the latest approved `pi_terms` document for the customer's state, effective today. It's chosen by these filters, never by similarity. Without one, the quotation template's terms are printed and the preview says so.
- **Questions such as "what warranty do we give?":** answered by excerpts from approved, applicable documents only, each with its document reference. The model doesn't answer from its own knowledge.
- **Sources kept with each PI:** the terms document (id, version, source), the price list (code, version, file), the template versions, and per line the AI suggestion versus the employee's choice and the retrieval method.
