<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveWizardStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'step' => ['required', 'string', 'in:describe,users,models,auth,integrations,review'],
            'data' => ['required', 'array'],
        ];

        if ($this->input('step') === 'describe') {
            $rules['data.name'] = ['required', 'string', 'max:255'];
        }

        if ($this->input('step') === 'models') {
            $rules['data.models'] = ['required', 'array', 'min:1'];
            $rules['data.models.*.name'] = ['required', 'string', 'max:100'];
        }

        return $rules;
    }
}
