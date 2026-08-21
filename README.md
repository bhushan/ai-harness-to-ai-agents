# From AI harness to AI agents

A Laravel demo repository for a talk of the same name. It runs completely
offline: every model response and every payment gateway response is served from
a committed fixture, so the same command prints the same output every time.

**One branch per teaching step.** Each branch builds on the previous one and
adds exactly one idea, one command, and one section to this README.

| Branch | Command |
| --- | --- |
| `step-0-setup` | `php artisan demo:data` |

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

## Before going on stage

```bash
php artisan demo:verify
php artisan test
```

`demo:verify` runs every demo command available on the current branch against
the fixtures and asserts the lines the talk depends on. Commands that belong to
later branches are reported as skipped, not as failures.
