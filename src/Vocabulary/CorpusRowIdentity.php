<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Vocabulary;

use InvalidArgumentException;

/**
 * Identity for a corpus row or fixture row that supports later comparison.
 *
 * @api
 */
final class CorpusRowIdentity
{
    public function __construct(
        private readonly string $corpus,
        private readonly string $row,
    ) {
        if (trim($corpus) === '' || trim($row) === '') {
            throw new InvalidArgumentException('Corpus and row identity must be non-empty.');
        }
    }

    public static function fromCorpusAndRow(string $corpus, string $row): self
    {
        return new self($corpus, $row);
    }

    public function corpus(): string
    {
        return $this->corpus;
    }

    public function row(): string
    {
        return $this->row;
    }

    /**
     * @return array{corpus: string, row: string}
     */
    public function toArray(): array
    {
        return ['corpus' => $this->corpus, 'row' => $this->row];
    }
}
