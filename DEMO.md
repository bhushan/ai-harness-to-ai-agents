# Run order for the talk

Every command is offline and deterministic. Run `php artisan demo:verify` once
before you walk on stage; it runs everything below against the fixtures and
checks the lines the talk depends on.

Each step lives on its own branch. Check the branch out, run the command, talk,
move on.

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

Priya Sharma, two successful ₹999 payments against one order, three seconds
apart. Arjun Mehta, two payments that are two ordinary purchases. Say the
double charge out loud here; everything else refers back to it.

---

## Step 1: the model on its own

```bash
git checkout step-1-llm
php artisan demo:llm
```

Three fields in the request. A decent, useless answer. It never mentions Priya,
₹999, or payments 123 and 124, because nothing in the request said they exist.

---

## Step 2: the harness

```bash
git checkout step-2-harness
php artisan demo:harness
```

Same model, same question, now with instructions and the customer record. The
answer improves without the model improving, and then stops: it cannot see the
payment records, and it says exactly what it would need.

---

## Step 3: tools

```bash
git checkout step-3-tools
php artisan demo:tools
```

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

A sequence I wrote. Predictable and cheap, and it opens a high priority ticket
for Arjun too, because I told it to. The reply even admits it.

---

## Step 5: the agent

```bash
git checkout step-5-agent
php artisan demo:agent --goal="Handle this customer's billing issue"
php artisan demo:agent --scenario=legitimate
```

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

The agent asks for ₹50,000 back and is stopped at the gate. A person releases
it, running the same tool class. The audit table shows both halves under
different actors.

---

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
