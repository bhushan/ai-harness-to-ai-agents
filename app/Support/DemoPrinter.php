<?php

namespace App\Support;

use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Every line the audience reads comes through here.
 *
 * The rules: wide spacing, one idea per block, loud labels, and no debug noise.
 * A person at the back of the room should be able to follow the run without
 * reading any of the JSON in detail.
 *
 * Note the constructor takes the raw Symfony output rather than the command's
 * OutputStyle. Laravel's console renderer collapses repeated spaces and drops
 * box drawing characters, which is the right call for ordinary command output
 * and the wrong one for a projector. Use DemoPrinter::for() from a command.
 */
final class DemoPrinter
{
    public const WIDTH = 78;

    private const INDENT = '  ';

    public function __construct(private readonly OutputInterface $output) {}

    public static function for(OutputStyle $output): self
    {
        return new self($output->getOutput());
    }

    public function blank(int $lines = 1): void
    {
        for ($i = 0; $i < $lines; $i++) {
            $this->output->writeln('');
        }
    }

    public function rule(string $character = '─', string $colour = 'gray'): void
    {
        $this->output->writeln($this->paint(str_repeat($character, self::WIDTH), $colour));
    }

    /**
     * The banner at the top of a command. One command, one banner.
     */
    public function title(string $title, string $subtitle = ''): void
    {
        $this->blank();
        $this->rule('═', 'cyan');
        $this->output->writeln($this->paint('  '.mb_strtoupper($title), 'cyan;options=bold'));

        if ($subtitle !== '') {
            $this->output->writeln($this->paint('  '.$subtitle, 'gray'));
        }

        $this->rule('═', 'cyan');
        $this->blank();
    }

    /**
     * A labelled block. The label is the thing people read from the back row.
     */
    public function section(string $label, string $meta = '', string $colour = 'white;options=bold'): void
    {
        $this->blank();

        $line = $this->paint($label, $colour);

        if ($meta !== '') {
            $line .= $this->paint('   '.$meta, 'gray');
        }

        $this->output->writeln($line);
        $this->blank();
    }

    public function request(array $payload, string $meta = 'POST /v1/messages'): void
    {
        $this->section('>>> REQUEST', $meta, 'cyan;options=bold');
        $this->json($payload);
    }

    public function response(array $payload, string $meta = ''): void
    {
        $this->section('<<< RESPONSE', $meta, 'green;options=bold');
        $this->json($payload);
    }

    public function answer(string $text): void
    {
        $this->section('<<< ANSWER', '', 'green;options=bold');
        $this->paragraph($text);
    }

    public function toolCall(string $name, array $input): void
    {
        $this->section('--- TOOL CALL', $name, 'yellow;options=bold');
        $this->json($input);
    }

    public function toolResult(string $name, array $result): void
    {
        $this->section('--- TOOL RESULT', $name, 'yellow');
        $this->json($result);
    }

    public function step(int $number, int $total, string $label): void
    {
        $this->blank();
        $this->rule('─', 'magenta');
        $this->output->writeln($this->paint(
            sprintf('  STEP %d OF %d   %s', $number, $total, mb_strtoupper($label)),
            'magenta;options=bold'
        ));
        $this->rule('─', 'magenta');
    }

    public function iteration(int $number, int $cap): void
    {
        $this->blank();
        $this->rule('─', 'magenta');
        $this->output->writeln($this->paint(
            sprintf('  ITERATION %d   (hard cap %d)', $number, $cap),
            'magenta;options=bold'
        ));
        $this->rule('─', 'magenta');
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string|int>>  $rows
     */
    public function table(array $headers, array $rows): void
    {
        $table = new Table($this->output);
        $table->setHeaders($headers)->setRows($rows);
        $table->render();
    }

    public function json(array $data): void
    {
        $encoded = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        foreach (explode("\n", (string) $encoded) as $line) {
            foreach ($this->foldLongLine($line) as $piece) {
                $this->output->writeln(self::INDENT.$this->paint($piece, 'gray'));
            }
        }
    }

    public function paragraph(string $text, string $colour = 'default'): void
    {
        foreach (explode("\n", wordwrap(trim($text), self::WIDTH - 4)) as $line) {
            // Blank lines stay genuinely blank rather than two stray spaces.
            $this->output->writeln($line === '' ? '' : self::INDENT.$this->paint($line, $colour));
        }
    }

    public function kv(string $key, string $value, int $pad = 18): void
    {
        $this->output->writeln(
            self::INDENT
            .$this->paint(str_pad($key, $pad), 'gray')
            .$this->paint($value, 'default;options=bold')
        );
    }

    public function bullet(string $text): void
    {
        $this->output->writeln(self::INDENT.$this->paint('•  '.$text, 'default'));
    }

    public function note(string $text): void
    {
        $this->output->writeln(self::INDENT.$this->paint($text, 'gray'));
    }

    public function good(string $text): void
    {
        $this->output->writeln(self::INDENT.$this->paint('✓  '.$text, 'green;options=bold'));
    }

    public function warn(string $text): void
    {
        $this->output->writeln(self::INDENT.$this->paint('!  '.$text, 'yellow;options=bold'));
    }

    public function bad(string $text): void
    {
        $this->output->writeln(self::INDENT.$this->paint('✗  '.$text, 'red;options=bold'));
    }

    public function outcome(string $headline, string $body = ''): void
    {
        $this->blank();
        $this->rule('═', 'green');
        $this->output->writeln($this->paint('  '.mb_strtoupper($headline), 'green;options=bold'));
        $this->rule('═', 'green');

        if ($body !== '') {
            $this->blank();
            $this->paragraph($body);
        }

        $this->blank();
    }

    /**
     * A long JSON string value would run off a projector. Fold it instead of
     * truncating it, so what is on screen is still every byte that was sent.
     *
     * @return array<int, string>
     */
    private function foldLongLine(string $line): array
    {
        $limit = self::WIDTH - 4;

        if (mb_strlen($line) <= $limit) {
            return [$line];
        }

        $indent = str_repeat(' ', mb_strlen($line) - mb_strlen(ltrim($line)) + 4);
        $continuation = max(20, $limit - mb_strlen($indent));

        $pieces = [mb_substr($line, 0, $limit)];

        foreach (mb_str_split(mb_substr($line, $limit), $continuation) as $piece) {
            $pieces[] = $indent.$piece;
        }

        return $pieces;
    }

    private function paint(string $text, string $style): string
    {
        $escaped = OutputFormatter::escape($text);

        if ($style === 'default') {
            return $escaped;
        }

        return sprintf('<fg=%s>%s</>', $style, $escaped);
    }
}
