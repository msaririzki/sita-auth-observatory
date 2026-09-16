<?php

namespace Tests\Unit;

use App\Enums\Decision;
use App\Enums\DecisionClassification;
use App\Services\TrialClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TrialClassifierTest extends TestCase
{
    #[DataProvider('decisionPairs')]
    public function test_it_classifies_expected_and_actual_decisions(
        Decision $expected,
        Decision $actual,
        DecisionClassification $classification,
    ): void {
        $this->assertSame(
            $classification,
            (new TrialClassifier)->classify($expected, $actual),
        );
    }

    /**
     * @return array<string, array{Decision, Decision, DecisionClassification}>
     */
    public static function decisionPairs(): array
    {
        return [
            'true positive' => [Decision::Allow, Decision::Allow, DecisionClassification::TruePositive],
            'true negative' => [Decision::Deny, Decision::Deny, DecisionClassification::TrueNegative],
            'false positive' => [Decision::Deny, Decision::Allow, DecisionClassification::FalsePositive],
            'false negative' => [Decision::Allow, Decision::Deny, DecisionClassification::FalseNegative],
        ];
    }
}
