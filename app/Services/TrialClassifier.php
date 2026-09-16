<?php

namespace App\Services;

use App\Enums\Decision;
use App\Enums\DecisionClassification;

class TrialClassifier
{
    public function classify(
        Decision $expected,
        Decision $actual,
    ): DecisionClassification {
        return match ([$expected, $actual]) {
            [Decision::Allow, Decision::Allow] => DecisionClassification::TruePositive,
            [Decision::Deny, Decision::Deny] => DecisionClassification::TrueNegative,
            [Decision::Deny, Decision::Allow] => DecisionClassification::FalsePositive,
            [Decision::Allow, Decision::Deny] => DecisionClassification::FalseNegative,
        };
    }
}
