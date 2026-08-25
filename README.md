# From AI harness to AI agents

A Laravel demo repository for a talk of the same name. It runs completely
offline: every model response and every payment gateway response is served from
a committed fixture, so the same command prints the same output every time.

**One branch per teaching step.** Each branch builds on the previous one and
adds exactly one idea, one command, and one section to this README.

| Branch | Command |
| --- | --- |
| `step-0-setup` | `php artisan demo:data` |
| `step-1-llm` | `php artisan demo:llm` |
| `step-2-harness` | `php artisan demo:harness` |
| `step-3-tools` | `php artisan demo:tools` |
| `step-4-workflow` | `php artisan demo:workflow` |
| `step-5-agent` | `php artisan demo:agent` |
| `step-6-boundaries` | `php artisan demo:agent --scenario=refund`, `demo:approve`, `demo:audit` |

## Setup, once

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan demo:data
```

No API key is required. An absent `ANTHROPIC_API_KEY` is the normal state here.

## Two ways to watch it

Every step has a command and a route, and they show different things.

```bash
php artisan demo:data       # the story, in sequence, in the terminal
php artisan serve           # then open http://localhost:8000
```

The commands are the narrative: step counters, iterations, the loop unfolding.
The routes are the hood: each one `dd()`s the payloads, the objects and the
database rows behind that step, so a request body can be expanded and collapsed
at the speed of the room. `routes/web.php` grows by one route per branch, so on
any branch it reads as a table of contents for the talk so far.

## Step 0: setup

**What this step demonstrates.** The plumbing that makes an offline demo
honest. The database holds a real billing scenario, and both outside worlds
this application talks to (the model, and Stripe) are fixture backed, with no
HTTP implementation anywhere in the repository.

**Run it.**

```bash
php artisan demo:data
```

**What the audience should notice.**

- Priya Sharma has two successful payments of ₹999 against one order,
  three seconds apart. That is the double charge the rest of the talk
  investigates.
- Arjun Mehta also has two payments, but against two different orders seventy
  days apart. Same shape of data, completely different answer.
- Nothing here calls the network. `App\AI\Transport\FixtureTransport` reads
  numbered files from `tests/fixtures/llm/` in call order, and
  `App\Billing\FixtureStripeGateway` reads real-shaped Stripe objects from
  `tests/fixtures/stripe/`. A missing fixture stops the run with a message
  naming the scenario, the call index, and the file it expected.

## Step 1: the model on its own

**What this step demonstrates.** A model with a question and nothing else.

**Run it.**

```bash
php artisan demo:llm
```

**What the audience should notice.**

- The outgoing request is three fields: a model id, a token ceiling, and one
  message. That is the entire input.
- The answer is not wrong. It is a competent support article about
  authorisation holds and retried payments. It also never mentions Priya
  Sharma, ₹999, or payments 123 and 124, because nothing in the request said
  they exist.
- The payload printed on screen is the real Anthropic Messages API shape, and
  the copy written to `storage/demo/requests/llm-raw/01-request.json` is the
  same bytes. Open it if somebody asks.

Everything after this step is about closing the gap between a capable model and
a useful answer.

## Step 2: the harness

**What this step demonstrates.** A harness is everything that surrounds the
model call: which instructions go in, which context, in which order, under
which limits. `App\AI\Harness\SupportHarness` assembles a system prompt and
the customer record. It still has no tools.

**Run it.**

```bash
php artisan demo:harness
```

**What the audience should notice.**

- The answer improved without the model improving. Same model, same question.
  Only the harness around it changed.
- It now knows Priya by name and can restate what it was told.
- It still cannot answer the question, and it says so. It names exactly what it
  would need: the payment records with amounts, statuses and timestamps.
  Context is not data access.
- Somebody still has to fetch those records by hand before every call. That is
  the problem step 3 solves.

## Step 3: tools

**What this step demonstrates.** A tool is an ordinary Laravel service with a
name, a description written for the model, a JSON schema for its input,
validation rules for the same input, and a typed `execute()`. There are four in
`app/AI/Tools/`: `get_customer`, `get_orders`, `get_payments` and
`create_ticket`.

**Run it.**

```bash
php artisan demo:tools
```

**What the audience should notice.**

- The model did not answer. It asked. `stop_reason` came back as `tool_use`,
  carrying the tool name and the arguments it wanted.
- My code decided whether to run it. `ToolExecutor` validates the arguments the
  model produced before anything touches the database, because the model is an
  untrusted caller.
- The execution is genuinely real: ordinary Eloquent against SQLite, plus a
  gateway lookup. Only the two model responses come from fixtures.
- The final answer names payments 123 and 124, ₹999, ORD-2201 and both
  idempotency keys. Every one of those came back from the tool result.
- The schema is what we tell the model; the validation rules are what we
  actually enforce. Never let the first do the job of the second.
- The sequence was still mine: one call, one execution, one answer. Deciding
  that sequence is step 4.

## Step 4: the workflow

**What this step demonstrates.** The same four tools, driven by a sequence I
wrote. `DoubleChargeWorkflow::SEQUENCE` is a constant: check the customer,
check the payments, check the orders, open a ticket. The model is used once, at
the end, to write the reply.

**Run it.**

```bash
php artisan demo:workflow
php artisan demo:workflow --customer=2
```

**What the audience should notice.**

- Four steps, one model call, and the model call is last. It writes the reply.
  It does not decide what to look up, in what order, or whether a ticket is
  warranted.
- The sequence is a constant in my code. The model never sees it and cannot add
  to it, skip an entry, or reorder it. Predictable, auditable, cheap.
- Then run it against Arjun, whose two payments are two ordinary purchases. It
  still opens a high priority ticket, because I told it to. The summary even
  says so: *"A high priority ticket has been opened for a colleague to review
  this anyway."*
- A workflow cannot notice that it did not need to run. That is the ceiling
  step 5 goes past.

## Step 5: the agent

**What this step demonstrates.** The loop. Reason, choose a tool, execute it,
observe the result, repeat, until the model stops asking for tools or the cap
is reached. The difference from step 4 is one thing only: the order of the tool
calls is decided one turn at a time by the model, instead of once in advance by
me.

**Run it.**

```bash
php artisan demo:agent --goal="Handle this customer's billing issue"
php artisan demo:agent --scenario=legitimate
php artisan demo:agent --max-iterations=2
```

**What the audience should notice.**

- Nobody wrote the order of those tool calls. Each one was chosen after reading
  the result of the last.
- The agent did not stop at two payments and shout duplicate. It checked what
  the order was actually for before deciding, and said so in its reasoning.
- Then run `--scenario=legitimate`. Same code, same tools, same goal, a
  different customer: three lookups, no ticket, and an explicit "no refund is
  owed". **Two paths out of the same tools is the whole point of the step.**
- The cap is hard. `--max-iterations=2` stops the run mid investigation and
  says so.
- Every decision is in the transcript: iterations, tools called in order, and
  why the loop stopped. An agent that cannot be replayed cannot be trusted.

## Step 6: boundaries

**What this step demonstrates.** Capability is not the hard part. Deciding what
an agent may do on its own is. Every tool now declares an impact and a required
permission, and a fifth tool, `refund_payment`, is high impact.

**Run it, in this order.**

```bash
php artisan demo:agent --scenario=refund
php artisan demo:approve 1
php artisan demo:audit
```

**What the audience should notice.**

- The agent asks for a ₹50,000 refund and is stopped. It gets a real answer
  back, `status: pending_approval` with an approval id, so the loop keeps
  working and it can explain itself. What it does not get is the action.
- Its own reply says so: *"nothing has moved yet"*. The payment row is
  untouched, and a test asserts it.
- `demo:approve 1` runs **the same tool class**, from a person typing a
  command, as `billing-lead`, the only actor holding
  `payments.refund.execute`. The boundary lives in the harness, not in the tool
  and not in the prompt.
- `demo:audit` shows both halves: run 1 by `assistant` ending in
  `pending_approval`, run 2 by `billing-lead` ending in `executed`. Those rows
  are written by the executor on every call, whatever the outcome, and none of
  them came from the model.

**The four checks, in order, in `ToolExecutor`:** is the tool registered, is
this actor allowed to call it, is the input valid, and is this something an
agent may do or only request.

See [DEMO.md](DEMO.md) for the full run order of the talk.

## Before going on stage

```bash
php artisan demo:verify
php artisan test
```

`demo:verify` runs every demo command available on the current branch against
the fixtures and asserts the lines the talk depends on. Commands that belong to
later branches are reported as skipped, not as failures.
