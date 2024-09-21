<?php

namespace App\Helpers;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTime;
use Recurr\Exception\InvalidRRule;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\ArrayTransformerConfig;
use Recurr\Transformer\Constraint\AfterConstraint;
use Recurr\Transformer\Constraint\BeforeConstraint;
use Throwable;

final class Recurrence
{
    private Rule $rule;

    private ArrayTransformer $transformer;

    /**
     * @throws InvalidRRule
     */
    public function __construct(
        string $rule,
        null|CarbonInterface $recurrenceStart,
    )
    {
        $this->rule = new Rule($rule, $recurrenceStart ?? new CarbonImmutable());

        $transformerConfig = new ArrayTransformerConfig();
        $transformerConfig->enableLastDayOfMonthFix();
        $this->transformer = new ArrayTransformer();
        $this->transformer->setConfig($transformerConfig);
    }

    public function next(CarbonInterface $lastOccurrenceTime): null|CarbonImmutable
    {
        try {
            $next = $this->transformer
                ->transform($this->rule, new AfterConstraint($lastOccurrenceTime))
                ->first();
            return $next ? CarbonImmutable::instance($next->getStart()) : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function previous(CarbonInterface $lastOccurrenceTime): null|CarbonImmutable
    {
        try {
            $last = $this->transformer
                ->transform($this->rule, new BeforeConstraint($lastOccurrenceTime))
                ->last();

            return $last ? CarbonImmutable::instance($last->getStart()) : null;
        } catch (Throwable $e) {
            // @todo: should we be suppressing here?
            return null;
        }
    }

    public function match(null|CarbonInterface $actual): null|CarbonInterface
    {
        $start = $this->rule->getStartDate();

        if (! $actual || ! $start instanceof CarbonInterface) {
            return null;
        }

        $previous = $this->previous($actual) ?? $start;
        $expected = $this->next($previous);

        if (! $expected) {
            return null;
        }

        return $expected->getTimestamp() !== $actual->getTimestamp()
            ? $expected
            : null;
    }
}
