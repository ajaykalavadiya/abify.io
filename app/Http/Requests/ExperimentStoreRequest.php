<?php

namespace App\Http\Requests;

use App\Support\Testing\CombinationBuilder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ExperimentStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request. The test type
     * is not among them — it comes from the route (see routes/dashboard.php),
     * so a form can't submit a type that contradicts the page it was on.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'url_match_type' => ['required', 'in:exact,contains,regex'],
            'url_pattern' => ['required', 'string', 'max:2048'],
            'traffic_allocation' => ['required', 'integer', 'min:1', 'max:100'],
        ];

        if ($this->isMultivariate()) {
            // One factor with several options is just an A/B test — a
            // multivariate test is about combinations across elements.
            $rules['factors'] = ['required', 'array', 'min:2', 'max:4'];
            $rules['factors.*.name'] = ['required', 'string', 'max:255'];
            $rules['factors.*.selector'] = ['required', 'string', 'max:255'];
            $rules['factors.*.action'] = ['required', 'in:text,html,hide'];
            $rules['factors.*.options'] = ['required', 'array', 'min:2', 'max:4'];
            $rules['factors.*.options.*.label'] = ['required', 'string', 'max:255'];
            $rules['factors.*.options.*.value'] = ['nullable', 'string'];
        }

        return $rules;
    }

    public function isMultivariate(): bool
    {
        return $this->route('type') === 'mvt';
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->isMultivariate() || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $factors = $this->input('factors', []);
                $total = (new CombinationBuilder)->countCombinations(is_array($factors) ? $factors : []);
                $max = CombinationBuilder::MAX_COMBINATIONS;

                if ($total > $max) {
                    $validator->errors()->add(
                        'factors',
                        "That's {$total} combinations, which is over the limit of {$max} — each one gets its own slice of traffic, so fewer factors or options will reach a result far sooner."
                    );
                }
            },
        ];
    }
}
