# Run order for the talk

Every command is offline and deterministic. Run `php artisan demo:verify` once
before you walk on stage; it runs everything below against the fixtures and
checks the lines the talk depends on.

Each step lives on its own branch. Check the branch out, run the command, talk,
move on.

## Two surfaces

Keep a terminal and a browser open. They do different jobs.

- **The terminal** is the narrative: step counters, iterations, the loop
  unfolding in order.
- **The browser** is the hood. Each `/step-N` route `dd()`s the payloads, the
  objects and the database rows behind that step, so you can expand and
  collapse a request body at the speed of the room, and point at the code in
  `routes/web.php` that produced it.

```bash
php artisan serve      # leave this running, open http://localhost:8000
```

Refreshing any route is safe. Each request re-bootstraps the application, so the
fixture counters start again and the page is identical every time.

---

## Before the talk

```bash
git checkout step-6-boundaries
php artisan demo:verify
php artisan test
```

Both should be green. Then go back to the beginning:

```bash
git checkout step-0-setup
```

---

## Step 0: the scenario

```bash
git checkout step-0-setup
php artisan demo:data
```

**In the browser:** `/step-0`. Expand *"what stands in for
api.anthropic.com"*. There is no base URL, no client and no key on that object,
just a directory and a counter. Then expand the Stripe charge and show that it
is shape for shape a real one.

Priya Sharma, two successful ₹999 payments against one order, three seconds
apart. Arjun Mehta, two payments that are two ordinary purchases. Say the
double charge out loud here; everything else refers back to it.

---

## Step 1: the model on its own

```bash
git checkout step-1-llm
php artisan demo:llm
```

**In the browser:** `/step-1`. Expand *"the request, exactly as it goes to
/v1/messages"*. Three keys. That is the whole input.

Three fields in the request. A decent, useless answer. It never mentions Priya,
₹999, or payments 123 and 124, because nothing in the request said they exist.

---

## Step 2: the harness

```bash
git checkout step-2-harness
php artisan demo:harness
```

**In the browser:** `/step-2`. The last section is the payment rows that were
sitting in SQLite the entire time. Say it out loud: the data was right there,
and the model could not reach it.

Same model, same question, now with instructions and the customer record. The
answer improves without the model improving, and then stops: it cannot see the
payment records, and it says exactly what it would need.

---

## Step 3: tools

```bash
git checkout step-3-tools
php artisan demo:tools
```

**In the browser:** `/step-3` is the best page in the demo. Seven sections, in
order: the tools array, the request carrying it, `stop_reason: tool_use`, the
tool the model chose, what your code actually ran, the `tool_result` you hand
back, and the answer built from it. Walk down it one section at a time.

The model asks instead of answering: `stop_reason: tool_use`. My code validates
the arguments and runs real Eloquent. The final answer names 123, 124, ₹999 and
both idempotency keys.

---

## Step 4: the workflow

```bash
git checkout step-4-workflow
php artisan demo:workflow
php artisan demo:workflow --customer=2
```

**In the browser:** `/step-4` runs it for both customers on one page. Section 1
is the sequence constant, section 5 is the identical tool order for Arjun, and
section 6 is the ticket it opened him anyway.

A sequence I wrote. Predictable and cheap, and it opens a high priority ticket
for Arjun too, because I told it to. The reply even admits it.

---

## Step 5: the agent

```bash
git checkout step-5-agent
php artisan demo:agent --goal="Handle this customer's billing issue"
php artisan demo:agent --scenario=legitimate
```

**In the browser:** `/step-5`. Expand *"the transcript"* and let the object
graph do the talking: iterations, each with its reasoning, each carrying the
tool calls it made and the results they returned. Then expand *"the
conversation it built"* to show the loop on the wire: user, assistant with a
`tool_use`, user with a `tool_result`, and round again.

Same tools, same goal, two different paths and two different conclusions. If
you only have time for one command in the whole talk, it is this pair.

Optional, if somebody asks what stops it running forever:

```bash
php artisan demo:agent --max-iterations=2
```

---

## Step 6: boundaries

```bash
git checkout step-6-boundaries
php artisan demo:agent --scenario=refund
php artisan demo:approve 1
php artisan demo:audit
```

**In the browser, and this is the one to do live:**

1. Open `/step-6`. Section 1 is every tool with its impact and its permissions.
   Section 4 is the approval waiting for a human, and section 5 is the payment,
   still succeeded, still not refunded.
2. Switch to the terminal and run `php artisan demo:approve 1`.
3. Open `/step-6/after-approval`. That route deliberately does not reset, so
   the money has moved, the refund id is there, and the audit log shows both
   halves under two different actors.

The agent asks for ₹50,000 back and is stopped at the gate. A person releases
it, running the same tool class. The audit table shows both halves under
different actors.

---

## If somebody asks to see the code

`routes/web.php` is the shortest thing to open. It grows by one route per
branch, so on `step-6-boundaries` it reads top to bottom as the arc of the whole
talk, and each route is a handful of lines pointing at the class to open next.

## If somebody asks to see a real payload

```bash
cat storage/demo/requests/agent-double-charge/03-request.json
```

Every outgoing request is written there as it is sent, including the full tools
array and the tool_result messages.

## If a command fails on stage

A missing fixture names the scenario, the call index and the file it wanted.
Nothing falls back to the network, so the failure is always local and always
readable. `php artisan demo:data` puts the database back to a known state.
