<?php

namespace App\Http\Requests;

use App\Enums\SortDirection;
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
            'pageIndex' => ['nullable', 'integer', 'min:1'],

            'rowNumber' => ['nullable', 'integer', 'min:1', 'max:200'],

            'search' => ['nullable', 'string', 'max:255'],

            'sortBy' => ['nullable', 'string', 'max:64'],

            'sortDirection' => ['nullable', 'string', 'in:asc,desc,ASC,DESC'],
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

    public function searchTerm(): ?string
    {
        $v = $this->validated('search');

        return ($v === null || $v === '') ? null : (string) $v;
    }

    public function sortBy(): ?string
    {
        $v = $this->validated('sortBy');

        return ($v === null || $v === '') ? null : (string) $v;
    }

    public function sortDirection(): SortDirection
    {
        return SortDirection::fromInput($this->input('sortDirection'));
    }
}
