<?php

namespace App\Http\Requests;

use App\Enums\AuthenticationProfile;
use App\Enums\ExperimentScenario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExperimentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'profile' => ['required', Rule::enum(AuthenticationProfile::class)],
            'scenario' => ['required', Rule::enum(ExperimentScenario::class)],
            'target' => ['required', Rule::in(config('observatory.allowed_targets'))],
            'git_ref' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'commit_sha' => ['nullable', 'string', 'size:40', 'regex:/^[a-f0-9]{40}$/i'],
            'repetitions' => ['required', 'integer', 'min:1', 'max:30'],
            'cooldown_seconds' => ['required', 'integer', 'min:0', 'max:300'],
        ];
    }
}
