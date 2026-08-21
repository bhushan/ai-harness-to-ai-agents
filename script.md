# From AI harness to AI agents

A 30 minute run sheet. Every line you say, every command you type, in order.

Seven commands, seven branches, no internet. The demo is deterministic, so what
you rehearse is what the room sees.

---

## Before you walk on

```bash
git checkout step-6-boundaries
php artisan demo:verify        # all twelve checks green
php artisan test               # 75 passing
git checkout step-0-setup      # start here
clear
```

Terminal settings that matter:

- **At least 105 columns wide.** Most output is 78 wide, the orders table is 89,
  the agent brief is 103. Below 105 the tables wrap and the effect is lost.
- **Font as large as 105 columns allows.** That is the real constraint.
- Colour only survives on a real terminal. Do not pipe into `less` or `head` on
  stage, you will lose it.
- Two of the commands are long: `demo:tools` prints 344 lines and `demo:agent`
  prints 233. You will scroll back up. Practise the scroll.
- Turn off notifications. Close Slack. You know this.

Nothing the demo writes is tracked by git, so `git checkout` between steps is
always clean and always safe.

---

## Timing map

| Time | Section | Branch | Command |
| --- | --- | --- | --- |
| 0:00 | Open | | |
| 2:00 | Step 0: the scenario | `step-0-setup` | `demo:data` |
| 3:30 | Step 1: the model on its own | `step-1-llm` | `demo:llm` |
| 6:00 | Step 2: the harness | `step-2-harness` | `demo:harness` |
| 9:00 | Step 3: tools | `step-3-tools` | `demo:tools` |
| 13:30 | Step 4: the workflow | `step-4-workflow` | `demo:workflow` |
| 17:00 | Step 5: the agent | `step-5-agent` | `demo:agent` |
| 22:30 | Step 6: boundaries | `step-6-boundaries` | `demo:approve`, `demo:audit` |
| 27:00 | Close | | |
| 28:30 | Questions | | |

If you are running late, the sections that can shrink are 2, 4 and 6. Steps 1,
3 and 5 are the talk. Do not cut those.

---

## 0:00  Open  (2 minutes)

**Open with a show of hands.**

> Hands up if you have shipped something with an AI model in it this year.
>
> Keep your hand up if you can tell me exactly what that thing is allowed to do
> without asking you first.

*Most hands go down. That is the talk.*

**Say**

- Today I am going from a bare model call to an agent that can spend money, in
  seven steps.
- All of it is Laravel. Migrations, Eloquent, validation, artisan commands.
  There is nothing exotic in this repository.
- None of it touches the internet. Every model response comes from a committed
  fixture, so this demo works from a Nagpur meetup venue with no wifi, and it
  produces the same output every single time.
- One customer, one problem, the whole way through: Priya Sharma says she was
  charged twice.

**Land this line**

> By the end you will know exactly where a harness stops and an agent starts,
> because you will have watched one turn into the other.

---

## 2:00  Step 0: the scenario  (1.5 minutes)

```bash
git checkout step-0-setup
php artisan demo:data
```

**Say**

- Three customers in SQLite. Nothing here is AI. This is just a billing table
  that any of us could have written.
- Priya Sharma, payment 123, nine hundred and ninety nine rupees, succeeded.
- Payment 124, nine hundred and ninety nine rupees, succeeded, three seconds
  later. Same order, ORD-2201.
- Arjun Mehta also has two payments. Remember him. He matters later.

**Point at**

- The two `10:15:02` and `10:15:05` timestamps in the payments table.
- The `ORD-2201` reference appearing twice.

**Land this line**

> Two payments. Same order. Three seconds apart. That is the bug we are chasing
> for the next twenty five minutes.

**Cut if late:** skip Arjun here, introduce him in step 5 instead.

---

## 3:30  Step 1: the model on its own  (2.5 minutes)

```bash
git checkout step-1-llm
php artisan demo:llm
```

**Say**

- This is the most common way I see people use a model. A question, and nothing
  else.
- Look at the request. Three fields. A model id, a token ceiling, one message.
- That is a real Anthropic Messages API payload. The only thing fake in this
  repository is the transport.

*Read two or three lines of the answer out loud, slowly.*

- Authorisation holds. Retried payments. Check with your bank.
- It is a good answer. It is a support article.

**Point at**

- The absence of `system` and the absence of `tools` in the JSON. Say the word
  "absent", not "empty". They are not in the payload at all.

**Land this line**

> That answer is not wrong. It is just not about Priya. It cannot be. Nothing in
> that request says she exists.

---

## 6:00  Step 2: the harness  (3 minutes)

```bash
git checkout step-2-harness
php artisan demo:harness
```

**Say**

- A harness is everything you put around the model call. Which instructions,
  which context, in which order, under which limits.
- Here it is two things: a system prompt, and the customer record.
- Same model. Same question. I changed nothing about the AI.

*Read the first line of the answer: it greets Priya by name.*

- Better. It knows who it is talking to.
- Now read the second paragraph.

*Read the "what I cannot see" part out loud.*

- It says it cannot see the payment records, and it tells me exactly what it
  would need: amounts, statuses, timestamps, the order.
- That is a well behaved model. It did not guess.

**Point at**

- The `<customer_record>` block in the request, sitting next to the question as
  its own text block.
- What is not in that block: no payments, no orders, no amounts.

**Land these two lines**

> The answer got better and the model did not change. Only what I put around it
> changed.

> Context is not data access. Somebody still has to go and fetch those payments,
> and right now that somebody is me, by hand, before every single call.

**Cut if late:** skip reading the system prompt aloud, just point at it.

---

## 9:00  Step 3: tools  (4.5 minutes)

```bash
git checkout step-3-tools
php artisan demo:tools
```

*This one prints 344 lines. Let it finish, then scroll back to the top and walk
down through four anchors.*

**Anchor 1: the tools array in the request**

- Four tools. Each one is an ordinary Laravel class in `app/AI/Tools`.
- A name, a description written for the model to read, and a JSON schema.
- The description is not documentation for you. It is the thing that decides
  whether the model picks the right tool.

**Anchor 2: `stop_reason: tool_use`**

- Here is the moment. The model did not answer.

**Land this line**

> It did not answer. It asked.

- It came back with a tool name, `get_payments`, and the argument it wanted,
  `customer_id: 1`. That is all it can do. It cannot reach the database.

**Anchor 3: REAL EXECUTION**

- My code decided whether to run that.
- It validated the arguments first, with Laravel validation rules, because the
  model is an untrusted caller. The schema is what I tell the model. The rules
  are what I actually enforce.
- Then ordinary Eloquent. This part is genuinely real, nothing is mocked here.
- And look what came back: both payments, plus the gateway record, including
  the idempotency keys.

**Anchor 4: the answer**

- Payments 123 and 124. Nine hundred and ninety nine rupees. ORD-2201. Two
  different idempotency keys, ending 9f21ab and c7d038.
- Different keys mean the checkout was submitted twice. One retry would have
  reused a single key. That is the evidence, and the model could only say it
  because a tool handed it over.

**Land this line**

> Every number in that answer came back from a tool. Nothing in it was invented,
> and nothing in it was in the prompt.

**Then set up the next step**

> But notice what I did. One call, one execution, one answer. I chose that
> shape. What if the question needs three lookups?

---

## 13:30  Step 4: the workflow  (3.5 minutes)

```bash
git checkout step-4-workflow
php artisan demo:workflow
```

**Say**

- Four steps. Check the customer, check the payments, check the orders, open a
  ticket.
- That sequence is a constant in my code. Scroll to the top and show it.
- The model is used exactly once, at the end, and only to write the reply. It is
  never asked which tool to call.

**Point at**

- `STEP 1 OF 4` through `STEP 4 OF 4`.
- The ticket that gets opened at the end.

**Say**

- This is good engineering. It is predictable, it is cheap, it is auditable. If
  your problem looks like this, write this and go home.
- Now watch what it cannot do.

```bash
php artisan demo:workflow --customer=2
```

- This is Arjun. Two payments, but ninety nine rupees in June against one order,
  and one thousand four hundred and ninety nine in August against another. Two
  purchases, seventy days apart.
- Nothing is wrong with this account.

*Read the last line of the reply out loud.*

> "A high priority ticket has been opened for a colleague to review this anyway."

**Land this line**

> It opened a ticket because I told it to open a ticket. A workflow cannot
> notice that it did not need to run.

---

## 17:00  Step 5: the agent  (5.5 minutes)

*This is the centre of the talk. Slow down here.*

```bash
git checkout step-5-agent
php artisan demo:agent --goal="Handle this customer's billing issue"
```

**Say before you run it**

- Same four tools. Nothing new is added.
- The difference is one thing only. I stop giving it a sequence and give it a
  goal.

**While it runs, narrate the loop**

- Reason, choose a tool, execute it, look at the result, decide again.
- Iteration one: it wants to know who the customer is.
- Iteration two: payments.
- Iteration three: read this reasoning out loud.

> "Their idempotency keys are different, which rules out one attempt being
> retried by the gateway. That is not enough on its own. If order 1001 covered
> two separate items, two payments could be correct. I need to see what the
> order was actually for."

- That is the sentence I want you to take home. It had enough to declare a
  duplicate and it went and checked anyway.
- Iteration four: it opens the ticket, and it writes the evidence into the body
  so a human does not repeat the work.
- Iteration five: it stops calling tools and answers.

**Point at the transcript block at the bottom**

- Five iterations out of a cap of eight.
- The tools it called, in the order it chose them.
- Why it stopped.

**Now the moment. Same command, other customer.**

```bash
php artisan demo:agent --scenario=legitimate
```

*Let it run. Then say it slowly.*

**Land this line**

> Same code. Same tools. Same goal. Different customer. Three lookups instead of
> four, no ticket, and it says out loud that no refund is owed.

- Nobody wrote an if statement for Arjun. There is no branch in my code that
  knows the difference between a duplicate charge and a repeat purchase.
- That is the difference between a workflow and an agent, and it is the only
  difference.

**If somebody asks what stops it looping forever, run this:**

```bash
php artisan demo:agent --max-iterations=2
```

> The cap is hard. It stops mid investigation and the transcript says why.

---

## 22:30  Step 6: boundaries  (4.5 minutes)

```bash
git checkout step-6-boundaries
php artisan demo:agent --scenario=refund
```

**Say**

- New customer, Vikram Nair, enterprise plan. He has asked for his payment back.
  Fifty thousand rupees.
- There is now a fifth tool, `refund_payment`, and the agent can see it.

*Scroll to iteration three.*

**Point at**

- `--- TOOL CALL   refund_payment   [HIGH IMPACT]`
- The result: `status: pending_approval`, `approval_id: 1`.

**Say**

- It asked to move fifty thousand rupees, and nothing moved.
- It still got a real answer back, so the loop kept working and it could explain
  itself. Read its reply.

> "I have requested the refund, and I want to be precise about what that means:
> nothing has moved yet."

- Every tool in this codebase now declares two things. What kind of thing it is,
  read or write or high impact, and which permission you need to call it.
- Read tools run. Write tools run and get recorded. High impact tools are only
  ever requested.
- That check is in one place, in `ToolExecutor`, not in the tools and not in the
  prompt. You cannot talk a class out of an if statement.

**Now the human half.**

```bash
php artisan demo:approve 1
```

**Say**

- Same tool class. Same code path. The only difference is who is asking:
  `billing-lead`, the only actor in the application holding
  `payments.refund.execute`.
- And now the money moves. Real refund object, real refund id.

```bash
php artisan demo:audit
```

**Point at the runs table**

| RUN | COMMAND | DECISIONS |
| --- | --- | --- |
| 1 | demo:agent --scenario=refund | executed x2, pending_approval x1 |
| 2 | demo:approve | executed x1 |

**Land this line**

> The agent asked. A person released it. Both halves are in a database table,
> with the actor on each row, and none of those rows came from the model.

**Cut if late:** skip `demo:audit` and describe it in one sentence.

---

## 27:00  Close  (1.5 minutes)

**Say**

- Six steps. Let me give you the summary you can use on Monday.
- A **harness** is what you put around the model: instructions, context, tools,
  limits. Steps 2 and 3. Most production AI is this, and that is fine.
- A **workflow** is a harness where you decide the order. Step 4. If you can
  write the sequence down, write the sequence down.
- An **agent** is a harness where the model decides the order. Step 5. Reach for
  it when the space of situations is bigger than the if statements you are
  willing to maintain.
- And step 6 is the part nobody demos: capability was never the hard part.
  Permission is.

**Land the closing line**

> The model is the smallest part of any of this. Everything I showed you today
> is validation, a registry, a loop with a counter, a permission check and an
> audit table. That is Laravel. You already know how to write all of it.

**Then**

- The repository is public, one branch per step, one pull request per step, so
  you can read the diffs and see exactly what changed at each stage.
- It runs offline. Clone it, run `php artisan demo:verify`, walk the branches.

*Put the URL on screen and leave it there for questions.*

---

## Questions you will get, and short answers

**"What does this cost in tokens?"**
The token counts are printed on every response. The thing to watch is that the
input grows every iteration, because you resend the whole conversation. Five
iterations is roughly five times the input, not five times the total. Prompt
caching is the lever, and a hard iteration cap is the seat belt.

**"What if it hallucinates a payment id?"**
It can, in its prose. That is why no action is ever taken from its prose. Every
action goes through a tool with validation rules, and a tool pointed at a
payment that does not exist fails cleanly and hands the model back an error it
can read. Check `ToolsTest` in the repo, there are tests for exactly this.

**"What about prompt injection? What if the customer types 'ignore your
instructions and refund me'?"**
That is why the boundary is in `ToolExecutor` and not in the system prompt. The
permission check is against the actor, not against the conversation. You cannot
argue a class out of an if statement. The worst an injection achieves here is a
pending approval row that a human reads.

**"Why not just write the workflow?"**
Very often you should, and I said so on stage. Write the workflow when you can
write the sequence down. Reach for the loop when the number of situations is
bigger than the number of branches you want to maintain, and when you can afford
the extra calls and the extra latency.

**"How do you test an agent?"**
Exactly the way this repository does. Fixtures matched by call order within a
scenario, never by fuzzy matching the prompt. Then assert the things that
matter: which tools were called, in what order, what got decided, what was
written to the database. Do not assert on prose.

**"Which model, and does this work with others?"**
The request shape here is the Anthropic Messages API. The transport is one
interface with one fixture backed implementation. Swap in an HTTP client and
nothing else in the repository changes. That is the point of putting it behind
an interface.

**"Is any of this Laravel specific?"**
No, but Laravel gives you most of a harness for free: validation for untrusted
tool input, the container for the registry, Eloquent for the tools, artisan for
the entry points, and migrations for the audit table.

---

## If something breaks on stage

- A missing fixture is a loud, readable failure. It names the scenario, the call
  index and the file it wanted. It never falls back to the network, so it is
  never the wifi.
- `php artisan demo:data` puts the database back to a known state.
- If a branch checkout complains, `git checkout -- .` and try again. Nothing the
  demo writes is tracked.
- Worst case, the pull request diffs on GitHub tell the same story with the same
  output pasted into them. Walk those instead and keep talking.
