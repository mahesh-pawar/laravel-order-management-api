<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TopProductsReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // admin check is done by the 'view-reports' Gate on the route
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'string', 'date_format:Y-m-d'],
            'to'   => [
                'required',
                'string',
                'date_format:Y-m-d',
                // Only compare when 'from' is a string; comparing against an array (?from[]=...) crashes
                Rule::when(is_string($this->input('from')), ['after_or_equal:from']),
            ],
        ];
    }
}
