<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PaginatedRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "page_index" => ["nullable", "integer", "min:1"],

            "row_number" => ["nullable", "integer", "min:1", "max:200"]
        ];
    }

    public function pageIndex(): int
    {
        return (int) ($this->validated('pageIndex') ?? 1);
    }

    public function rowNumber(): int
    {
        return (int) ($this->validated('rowNumber') ?? 20);
    }
}
