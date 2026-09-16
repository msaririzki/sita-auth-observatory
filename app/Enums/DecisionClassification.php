<?php

namespace App\Enums;

enum DecisionClassification: string
{
    case TruePositive = 'TP';
    case TrueNegative = 'TN';
    case FalsePositive = 'FP';
    case FalseNegative = 'FN';
}
